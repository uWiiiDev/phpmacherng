<?php
/**
 * PHPMacherNG - Project Manager
 * Verwaltet Projekteinstellungen (.pmp.json), Tabellen- und Feld-Konfigurationen sowie Datenbank-Synchronisation.
 * Kompatibel mit PHP 5.4 bis PHP 8.4+
 */

require_once dirname(__FILE__) . '/DatabaseInspector.php';
require_once dirname(__FILE__) . '/ProjectVersionControl.php';

class ProjectManager
{
    public $projectsDir = '';
    public $vcs = null;

    public function __construct($projectsDir = '')
    {
        if ($projectsDir === '') {
            $this->projectsDir = dirname(dirname(__FILE__)) . '/projects';
        } else {
            $this->projectsDir = rtrim($projectsDir, '/\\');
        }
        if (!is_dir($this->projectsDir)) {
            @mkdir($this->projectsDir, 0777, true);
        }
        $this->vcs = new ProjectVersionControl($this->projectsDir);
    }

    /**
     * Ermittelt die effektive Zugriffsrolle eines Benutzers auf ein Projekt ('owner', 'write', 'read' oder '').
     * @param array $projectData
     * @param string $username
     * @return string
     */
    public static function getUserAccessRole($projectData, $username)
    {
        if ($username === '') {
            return 'owner';
        }
        $u = strtolower(trim((string)$username));
        $owner = (is_array($projectData) && !empty($projectData['owner']))
            ? strtolower(trim((string)$projectData['owner']))
            : 'admin';
        if ($u === $owner) {
            return 'owner';
        }
        if (is_array($projectData) && isset($projectData['shared_with']) && is_array($projectData['shared_with'])) {
            foreach ($projectData['shared_with'] as $shareUser => $shareMeta) {
                if (strtolower(trim((string)$shareUser)) === $u) {
                    if (is_array($shareMeta) && isset($shareMeta['role'])) {
                        return ($shareMeta['role'] === 'read') ? 'read' : 'write';
                    }
                    return ($shareMeta === 'read') ? 'read' : 'write';
                }
            }
        }
        return '';
    }

    /**
     * Gibt alle gespeicherten Projektdateien zurück, auf die der übergebene Benutzer Zugriff hat
     * (eigene Projekte + mit ihm geteilte Projekte).
     * @param string $username Optional: Filtert auf diesen Benutzer (leer = ungefiltert)
     * @param bool $includeAllForAdmin Optional: Falls true, sieht der Studio-Admin auch Projekte anderer Nutzer
     * @return array
     */
    public function listProjects($username = '', $includeAllForAdmin = false)
    {
        $projects = array();
        if (!is_dir($this->projectsDir)) {
            @mkdir($this->projectsDir, 0777, true);
        }
        $entries = @scandir($this->projectsDir);
        if (!is_array($entries)) {
            $entries = array();
            $globFiles = @glob($this->projectsDir . '/*.pmp.json');
            if (is_array($globFiles)) {
                foreach ($globFiles as $gf) {
                    $entries[] = basename($gf);
                }
            }
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || substr($entry, 0, 1) === '.') {
                continue;
            }
            $f = $this->projectsDir . '/' . $entry;
            if (!is_file($f)) {
                continue;
            }
            if (preg_match('/\.pmp\.json$/i', $entry)) {
                $base = preg_replace('/\.pmp\.json$/i', '', $entry);
            } elseif (preg_match('/\.json$/i', $entry)) {
                $base = preg_replace('/\.json$/i', '', $entry);
            } else {
                continue;
            }
            if (substr($base, 0, 1) === '.' || $base === '_studio_auth' || $base === '_studio_users') {
                continue;
            }
            $raw = @file_get_contents($f);
            $data = ($raw !== false) ? self::decodeProjectJson($raw) : null;
            if (!is_array($data)) {
                continue;
            }

            $owner = !empty($data['owner']) ? strtolower(trim((string)$data['owner'])) : 'admin';
            $sharedWith = (isset($data['shared_with']) && is_array($data['shared_with'])) ? $data['shared_with'] : array();
            $role = self::getUserAccessRole($data, $username);

            if ($username !== '' && $role === '') {
                if ($includeAllForAdmin) {
                    $role = 'admin';
                } else {
                    continue;
                }
            }

            $repoMeta = $this->vcs->loadRepoMeta($base);
            $gitBranch = !empty($repoMeta['current_branch']) ? $repoMeta['current_branch'] : 'main';
            $gitCommitCount = isset($repoMeta['commits']) && is_array($repoMeta['commits']) ? count($repoMeta['commits']) : 0;

            $projects[] = array(
                'filename'         => $base,
                'project_name'     => isset($data['project_name']) ? $data['project_name'] : $base,
                'db_name'          => isset($data['database']['dbname']) ? $data['database']['dbname'] : '',
                'updated_at'       => isset($data['updated_at']) ? $data['updated_at'] : date('Y-m-d H:i:s', @filemtime($f)),
                'tables_count'     => (isset($data['tables']) && is_array($data['tables'])) ? count($data['tables']) : 0,
                'owner'            => $owner,
                'access_role'      => $role !== '' ? $role : 'owner',
                'shared_with'      => $sharedWith,
                'shared_count'     => count($sharedWith),
                'last_modified_by' => isset($data['last_modified_by']) ? $data['last_modified_by'] : $owner,
                'git_branch'       => $gitBranch,
                'git_commits'      => $gitCommitCount
            );
        }
        return $projects;
    }

    /**
     * Bereinigt einen JSON-String (UTF-8 BOM, Kontrollzeichen, ungültige UTF-8 Sequenzen) und dekodiert ihn robust.
     * @param string $jsonString
     * @return array|null
     */
    public static function decodeProjectJson($jsonString)
    {
        if (!is_string($jsonString) || $jsonString === '') {
            return null;
        }
        // UTF-8 BOM (\xEF\xBB\xBF), UTF-16 BOMs und führende/abschließende Leerzeichen entfernen
        if (substr($jsonString, 0, 3) === "\xEF\xBB\xBF") {
            $jsonString = substr($jsonString, 3);
        }
        $jsonString = trim($jsonString);
        $data = @json_decode($jsonString, true);
        if (is_array($data)) {
            return $data;
        }
        // Fallback: Falls Datei unter Windows in ISO-8859-1 / Windows-1252 gespeichert wurde
        if (function_exists('mb_convert_encoding')) {
            $utf8 = @mb_convert_encoding($jsonString, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $data = @json_decode($utf8, true);
            if (is_array($data)) {
                return $data;
            }
        } elseif (function_exists('utf8_encode')) {
            $data = @json_decode(@utf8_encode($jsonString), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Erzeugt eine leere Standard-Projektstruktur im PHPMacherNG Stil.
     * @param string $projectName
     * @param string $ownerUsername
     * @return array
     */
    public static function createDefaultProject($projectName = 'MeinProjekt', $ownerUsername = 'admin')
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($projectName));
        if ($slug === '') {
            $slug = 'mein_projekt';
        }
        $cleanOwner = strtolower(trim((string)$ownerUsername));
        if ($cleanOwner === '') {
            $cleanOwner = 'admin';
        }
        $isCrmDemo = (strtolower($slug) === 'crm_shop_demo');
        return array(
            'version' => '2018.0.14',
            'project_name' => $projectName,
            'project_id' => '{' . strtoupper(substr(md5($projectName . time()), 0, 8)) . '-2018-4000-8000-PHPMACHERNG}',
            'owner' => $cleanOwner,
            'shared_with' => array(),
            'last_modified_by' => $cleanOwner,
            'updated_at' => date('Y-m-d H:i:s'),
            'database' => array(
                'type' => 'MYSQL',
                'host' => '127.0.0.1',
                'port' => 3306,
                'user' => 'root',
                'pass' => '',
                'dbname' => '',
                'charset' => 'utf8'
            ),
            'settings' => array(
                'title' => $projectName . ' - Administration',
                'ui_template' => 'adminlte2',
                'theme_skin' => 'skin-blue',
                'layout_style' => 'sidebar-mini',
                'records_per_page' => 20,
                'page_sizes' => '10,20,50,100,ALL',
                'date_format' => 'Y-m-d',
                'currency_symbol' => '€',
                'timezone' => 'Europe/Berlin',
                'output_folder' => 'output/' . $slug,
                'default_table' => '',
                'show_sql_debug' => false,
                'use_modal_lookup' => false,
                'confirm_action' => true,
                'footer_text' => '<strong>Copyright &copy; ' . date('Y') . ' ' . $projectName . '.</strong> Generiert mit PHPMacherNG.'
            ),
            'security' => self::createDefaultSecurityConfig(),
            'tables' => array(),
            'custom_files' => $isCrmDemo ? self::createDefaultCustomFiles() : array(),
            'menu_items' => array(),
            'server_events' => array('global' => array(), 'tables' => array()),
            'client_scripts' => array('global' => array(), 'tables' => array())
        );
    }

    /**
     * Erzeugt eine Standard-Beispiel-Datei für PHPMacherNG "Custom Files" (inkl. DbHelper() / ew_Execute* Nutzung).
     * @return array
     */
    public static function createDefaultCustomFiles()
    {
        $demoCode = <<<'EOCODE'
<?php
// ============================================================================
// PHPMacherNG Custom File: Management Dashboard (dashboard.php)
// Verfügbare PHPMacherNG Datenzugriffs-Funktionen:
//   $db = &DbHelper();                   // Datenbank-Helper-Objekt abrufen
//   ew_ExecuteScalar($sql, $params)      // Einzelwert abrufen (z.B. COUNT, SUM)
//   ew_ExecuteRow($sql, $params)         // Einzelne Zeile als Array abrufen
//   ew_ExecuteRows($sql, $params)        // Alle Zeilen als Array-Liste abrufen
//   ew_ExecuteHtml($sql, $options)       // SQL-Ergebnis direkt als Bootstrap-Tabelle ausgeben!
//   ew_Execute($sql, $params)            // INSERT / UPDATE / DELETE ausführen
//   CurrentUserName(), CurrentUserID(), CurrentUserLevel(), IsLoggedIn(), IsSysAdmin()
// ============================================================================

$anzahlKunden       = (int)ew_ExecuteScalar("SELECT COUNT(*) FROM `kunden`");
$anzahlProdukte     = (int)ew_ExecuteScalar("SELECT COUNT(*) FROM `produkte`");
$anzahlBestellungen = (int)ew_ExecuteScalar("SELECT COUNT(*) FROM `bestellungen`");
$gesamtUmsatz       = (float)ew_ExecuteScalar("SELECT COALESCE(SUM(`gesamtbetrag`), 0) FROM `bestellungen` WHERE `status` <> 'Storniert'");
?>

<div class="callout callout-info" style="margin-bottom:20px;">
    <h4><i class="fa fa-file-code-o"></i> Eigene PHPMacherNG-Datei (Custom File: <code>dashboard.php</code>)</h4>
    <p style="margin:0;">
        Angemeldet als: <strong><?php echo ew_HtmlEncode(CurrentUserName() ? CurrentUserName() : 'Gast'); ?></strong>
        (User-ID: <code><?php echo ew_HtmlEncode(CurrentUserID()); ?></code> |
        User-Level: <code><?php echo (int)CurrentUserLevel(); ?></code> |
        Datum: <code><?php echo CurrentDate(); ?></code>).
        Diese Seite wurde über die <strong>Custom Files</strong> Funktion mit <code>ew_ExecuteScalar()</code>, <code>DbHelper()-&gt;ExecuteHtml()</code> und <code>ew_ExecuteRows()</code> erstellt.
    </p>
</div>

<!-- KPI Info-Boxen mit ew_ExecuteScalar() -->
<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Kunden gesamt</span>
                <span class="info-box-number"><?php echo $anzahlKunden; ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-cubes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Produkte im Katalog</span>
                <span class="info-box-number"><?php echo $anzahlProdukte; ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-shopping-cart"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Bestellungen</span>
                <span class="info-box-number"><?php echo $anzahlBestellungen; ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-eur"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Gesamtumsatz</span>
                <span class="info-box-number"><?php echo number_format($gesamtUmsatz, 2, ',', '.'); ?> &euro;</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Linke Spalte: Automatische HTML-Tabelle per DbHelper()->ExecuteHtml() -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-table"></i> Umsatz pro Kunde (direkt per <code>DbHelper()-&gt;ExecuteHtml()</code>)</h3>
            </div>
            <div class="box-body table-responsive">
                <?php
                $sqlUmsatzKunden = "
                    SELECT
                        k.firma AS Firma,
                        k.stadt AS Stadt,
                        k.kundengruppe AS Gruppe,
                        COUNT(b.bestellung_id) AS Bestellungen,
                        CONCAT(FORMAT(COALESCE(SUM(b.gesamtbetrag), 0), 2, 'de_DE'), ' €') AS Umsatz
                    FROM kunden k
                    LEFT JOIN bestellungen b ON b.kunde_id = k.kunde_id
                    GROUP BY k.kunde_id, k.firma, k.stadt, k.kundengruppe
                    ORDER BY COALESCE(SUM(b.gesamtbetrag), 0) DESC
                ";
                echo DbHelper()->ExecuteHtml($sqlUmsatzKunden, array(
                    'fieldcaption' => array(
                        'Firma'        => 'Firma / Kunde',
                        'Stadt'        => 'Standort',
                        'Gruppe'       => 'Kundengruppe',
                        'Bestellungen' => 'Anz. Aufträge',
                        'Umsatz'       => 'Gesamtumsatz'
                    )
                ));
                ?>
            </div>
        </div>
    </div>

    <!-- Rechte Spalte: Eigene Schleife mit ew_ExecuteRows() -->
    <div class="col-md-5">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cubes"></i> Lagerbestand (per <code>ew_ExecuteRows()</code>)</h3>
            </div>
            <div class="box-body">
                <ul class="products-list product-list-in-box">
                    <?php
                    $produkte = ew_ExecuteRows("SELECT produktname, artikelnummer, preis, lagerbestand FROM produkte ORDER BY lagerbestand ASC");
                    if (is_array($produkte)):
                        foreach ($produkte as $p):
                            $badgeClass = ((int)$p['lagerbestand'] > 5) ? 'label-success' : (((int)$p['lagerbestand'] > 0) ? 'label-warning' : 'label-danger');
                    ?>
                        <li class="item" style="padding:8px 0; border-bottom:1px solid #f4f4f4;">
                            <div class="product-info" style="margin-left:5px;">
                                <strong><?php echo ew_HtmlEncode($p['produktname']); ?></strong>
                                <span class="label <?php echo $badgeClass; ?> pull-right">Bestand: <?php echo (int)$p['lagerbestand']; ?></span>
                                <span class="product-description" style="display:block; color:#777; font-size:12px;">
                                    Art.-Nr.: <code><?php echo ew_HtmlEncode($p['artikelnummer']); ?></code> |
                                    Einzelpreis: <?php echo number_format((float)$p['preis'], 2, ',', '.'); ?> &euro;
                                </span>
                            </div>
                        </li>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>
EOCODE;

        return array(
            'dashboard.php' => array(
                'filename'       => 'dashboard.php',
                'caption'        => 'Management Dashboard',
                'enabled'        => true,
                'include_common' => true,
                'in_menu'        => true,
                'icon'           => 'fa-dashboard',
                'content'        => $demoCode
            )
        );
    }

    /**
     * Erzeugt die Standard-Konfiguration für das PHPMacherNG Security-Modul
     * (Hardcoded Admin, User Table, Static & Dynamic User Levels, User ID Security).
     * Bitmaske: Add=1, Delete=2, Edit=4, List=8, Admin=16, View=32, Search=64
     * @return array
     */
    public static function createDefaultSecurityConfig()
    {
        return array(
            'enabled' => false,
            'hardcoded_admin_enabled' => true,
            'admin_username' => 'admin',
            'admin_password' => '12345678',
            'use_user_table' => false,
            'user_table' => 'benutzer',
            'username_field' => 'benutzername',
            'password_field' => 'passwort',
            'password_hash' => 'PLAIN_OR_HASH', // Unterstützt Klartext, MD5, SHA256 und bcrypt (password_verify)
            'user_id_field' => 'benutzer_id',
            'parent_user_id_field' => 'vorgesetzter_id',
            'user_level_field' => 'userlevel_id',
            // User Level Security
            'user_level_security' => true,
            'dynamic_user_levels' => false,
            'user_level_table' => 'userlevels',
            'user_level_id_field' => 'userlevelid',
            'user_level_name_field' => 'userlevelname',
            'user_level_priv_table' => 'userlevelpermissions',
            'user_level_priv_table_name_field' => 'tablename',
            'user_level_priv_id_field' => 'userlevelid',
            'user_level_priv_field' => 'permission',
            // Statische Rollen & Berechtigungen (als Basis und für den 1-Klick DB-Installer)
            'static_user_levels' => array(
                array('id' => -2, 'name' => 'Anonymous'),
                array('id' => -1, 'name' => 'Administrator'),
                array('id' => 0, 'name' => 'Default (Mitarbeiter)'),
                array('id' => 1, 'name' => 'Manager (Abteilungsleiter)'),
                array('id' => 2, 'name' => 'ReadOnly (Nur Lesen)')
            ),
            // Format: $static_permissions[$tableName][$levelId] = int Bitmaske
            'static_permissions' => array()
        );
    }

    /**
     * Ergänzt eine aus DatabaseInspector geladene Tabelle um alle PHPMacherNG Tabellen-Einstellungen.
     * @param array $rawTable
     * @return array
     */
    public static function buildDefaultTableConfig($rawTable)
    {
        $hasPk = !empty($rawTable['primary_keys']);
        $isView = (isset($rawTable['type']) && $rawTable['type'] === 'VIEW');
        $pkField = $hasPk ? $rawTable['primary_keys'][0] : '';

        // Automatisches Display-Feld für Lookups auf diese Tabelle finden
        $displayField = $pkField;
        if (isset($rawTable['fields']) && is_array($rawTable['fields'])) {
            foreach ($rawTable['fields'] as $fName => $fMeta) {
                if ($fMeta['ew_type'] === 'STRING' && !$fMeta['is_primary']) {
                    $displayField = $fName;
                    break;
                }
            }
        }

        return array(
            'name' => $rawTable['name'],
            'type' => isset($rawTable['type']) ? $rawTable['type'] : 'TABLE',
            'caption' => isset($rawTable['caption']) ? $rawTable['caption'] : DatabaseInspector::humanizeName($rawTable['name']),
            'enabled' => true,
            'primary_keys' => isset($rawTable['primary_keys']) ? $rawTable['primary_keys'] : array(),
            'view_sql' => isset($rawTable['view_sql']) ? $rawTable['view_sql'] : '',
            'display_field' => $displayField,
            'icon' => $isView ? 'fa-eye' : 'fa-list-alt',
            // Seiten-Generierung (Table Setup)
            'list_page' => true,
            'add_page' => ($hasPk && !$isView),
            'edit_page' => ($hasPk && !$isView),
            'view_page' => $hasPk,
            'delete_page' => ($hasPk && !$isView),
            'multi_delete' => ($hasPk && !$isView),
            'multi_update' => ($hasPk && !$isView),
            // Suche & Sortierung
            'quick_search' => true,
            'extended_search' => true,
            'records_per_page' => 0, // 0 = Globaler Standardwert
            'default_order_by' => $pkField,
            'default_order_dir' => 'DESC',
            // User ID Security (Eigentümer-Spalte für zeilenbasierte Berechtigung)
            'user_id_field' => '',
            // Master/Detail-Verknüpfungen (Detail-Tabellen, die in der Liste als Unterliste aufklappbar sind)
            'master_details' => array(),
            // Diagramme (Balken- und Tortendiagramme für diese Tabelle/View)
            'charts' => array(),
            // Felder
            'fields' => isset($rawTable['fields']) ? $rawTable['fields'] : array()
        );
    }

    /**
     * Synchronisiert ein bestehendes Projekt mit dem aktuellen Datenbank-Schema,
     * ohne bereits gemachte Einstellungen an vorhandenen Tabellen/Feldern zu überschreiben!
     * @param array $project
     * @param array $inspectedTables
     * @return array Aktualisiertes Projekt + Sync-Bericht
     */
    public static function synchronizeSchema($project, $inspectedTables)
    {
        $report = array(
            'added_tables' => array(),
            'removed_tables' => array(),
            'added_fields' => array(),
            'removed_fields' => array(),
            'updated_fields' => array()
        );

        $existingTables = isset($project['tables']) && is_array($project['tables']) ? $project['tables'] : array();
        $newTables = array();

        foreach ($inspectedTables as $tName => $rawTable) {
            if (!isset($existingTables[$tName])) {
                // Neue Tabelle / View
                $newTables[$tName] = self::buildDefaultTableConfig($rawTable);
                $report['added_tables'][] = $tName;
            } else {
                // Bestehende Tabelle behalten, aber Keys & Felder abgleichen
                $tConfig = $existingTables[$tName];
                $tConfig['type'] = $rawTable['type'];
                if ($rawTable['type'] === 'VIEW' && !empty($rawTable['view_sql']) && empty($tConfig['view_sql'])) {
                    $tConfig['view_sql'] = $rawTable['view_sql'];
                }
                // Bei Datenbank-Views die vom Benutzer konfigurierten Primary Keys beibehalten, falls die DB keine meldet
                if ($rawTable['type'] === 'VIEW' && empty($rawTable['primary_keys']) && !empty($tConfig['primary_keys'])) {
                    $keepPks = array();
                    foreach ($tConfig['primary_keys'] as $pkCandidate) {
                        if (isset($rawTable['fields'][$pkCandidate])) {
                            $keepPks[] = $pkCandidate;
                        }
                    }
                    $tConfig['primary_keys'] = $keepPks;
                } else {
                    $tConfig['primary_keys'] = $rawTable['primary_keys'];
                }

                $existingFields = isset($tConfig['fields']) && is_array($tConfig['fields']) ? $tConfig['fields'] : array();
                $mergedFields = array();

                foreach ($rawTable['fields'] as $fName => $rawField) {
                    $isCustomViewPk = ($rawTable['type'] === 'VIEW' && in_array($fName, $tConfig['primary_keys']));
                    if (!isset($existingFields[$fName])) {
                        if ($isCustomViewPk) {
                            $rawField['is_primary'] = true;
                        }
                        $mergedFields[$fName] = $rawField;
                        $report['added_fields'][] = $tName . '.' . $fName;
                    } else {
                        // Benutzereinstellungen behalten, aber technische DB-Eigenschaften aktualisieren
                        $fConfig = $existingFields[$fName];
                        if ($fConfig['sql_type'] !== $rawField['sql_type'] || ($rawTable['type'] !== 'VIEW' && $fConfig['is_primary'] !== $rawField['is_primary'])) {
                            $report['updated_fields'][] = $tName . '.' . $fName;
                        }
                        $fConfig['sql_type'] = $rawField['sql_type'];
                        $fConfig['base_type'] = $rawField['base_type'];
                        $fConfig['ew_type'] = $rawField['ew_type'];
                        $fConfig['length'] = $rawField['length'];
                        $fConfig['scale'] = $rawField['scale'];
                        $fConfig['is_primary'] = ($rawTable['type'] === 'VIEW') ? ($isCustomViewPk || !empty($fConfig['is_primary'])) : $rawField['is_primary'];
                        $fConfig['is_auto_increment'] = $rawField['is_auto_increment'];
                        $fConfig['is_nullable'] = $rawField['is_nullable'];
                        $mergedFields[$fName] = $fConfig;
                    }
                }

                foreach ($existingFields as $oldFName => $oldF) {
                    if (!isset($rawTable['fields'][$oldFName])) {
                        $report['removed_fields'][] = $tName . '.' . $oldFName;
                    }
                }

                $tConfig['fields'] = $mergedFields;
                $newTables[$tName] = $tConfig;
            }
        }

        foreach ($existingTables as $oldTName => $oldT) {
            if (!isset($inspectedTables[$oldTName])) {
                $report['removed_tables'][] = $oldTName;
            }
        }

        // Lookup-Display-Felder automatisch verbessern, falls noch auf dem PK-Feld stehend
        foreach ($newTables as $tName => $tCfg) {
            foreach ($tCfg['fields'] as $fName => $fCfg) {
                if (!empty($fCfg['lookup_table']) && isset($newTables[$fCfg['lookup_table']])) {
                    $refTable = $newTables[$fCfg['lookup_table']];
                    if ($fCfg['lookup_display_field'] === $fCfg['lookup_link_field'] && !empty($refTable['display_field'])) {
                        $newTables[$tName]['fields'][$fName]['lookup_display_field'] = $refTable['display_field'];
                    }
                }
            }
        }

        $project['tables'] = $newTables;
        $project['tables'] = self::synchronizeMasterDetails($project['tables']);
        if (empty($project['settings']['default_table']) && !empty($newTables)) {
            $keys = array_keys($newTables);
            $project['settings']['default_table'] = $keys[0];
        }

        // Security-Konfiguration und Standard-Berechtigungen für neue Tabellen ergänzen
        if (!isset($project['security']) || !is_array($project['security'])) {
            $project['security'] = self::createDefaultSecurityConfig();
        } else {
            $project['security'] = array_merge(self::createDefaultSecurityConfig(), $project['security']);
        }
        if (!isset($project['security']['static_permissions']) || !is_array($project['security']['static_permissions'])) {
            $project['security']['static_permissions'] = array();
        }
        foreach ($newTables as $tName => $tCfg) {
            if (!isset($project['security']['static_permissions'][$tName])) {
                // Bitmasken: -2 (Anon)=0, -1 (Admin)=127, 0 (Default)=109 (Add+Edit+List+View+Search), 1 (Manager)=111 (+Delete), 2 (ReadOnly)=104 (List+View+Search)
                $project['security']['static_permissions'][$tName] = array(
                    '-2' => 0,
                    '-1' => 127,
                    '0' => 109,
                    '1' => 111,
                    '2' => 104
                );
            }
        }

        $project['menu_items'] = self::synchronizeMenuItems($project);
        $project['updated_at'] = date('Y-m-d H:i:s');

        return array(
            'project' => $project,
            'report' => $report
        );
    }

    /**
     * Erzeugt oder synchronisiert die hierarchische PHPMacherNG Menü-Struktur (Menu Editor).
     * Unterstützt beliebig tief verschachtelte Ebenen (parent_id), Trennlinien/Gruppen-Header (is_header),
     * Tabellen/Views, Custom Files sowie frei definierbare eigene Menüeinträge & Ordner.
     *
     * @param array $project
     * @param bool $forceReset Wenn true, wird das Standardmenü komplett neu aufgebaut
     * @return array
     */
    public static function synchronizeMenuItems($project, $forceReset = false)
    {
        $existing = (!$forceReset && isset($project['menu_items']) && is_array($project['menu_items']))
            ? $project['menu_items']
            : array();

        $tables = isset($project['tables']) && is_array($project['tables']) ? $project['tables'] : array();
        $customFiles = isset($project['custom_files']) && is_array($project['custom_files']) ? $project['custom_files'] : array();

        // Falls noch kein Menü existiert (oder Reset angefordert), sinnvolle PHPMacherNG Standardstruktur mit verschachtelten Ebenen aufbauen
        if (empty($existing)) {
            $items = array();

            // 1. Custom Files
            foreach ($customFiles as $cfKey => $cfCfg) {
                $cfFileName = isset($cfCfg['filename']) ? trim($cfCfg['filename']) : $cfKey;
                $cfFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($cfFileName));
                if ($cfFileName === '') continue;
                if (substr(strtolower($cfFileName), -4) !== '.php') $cfFileName .= '.php';

                $items[] = array(
                    'id'          => 'cf_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $cfKey),
                    'parent_id'   => '',
                    'text'        => isset($cfCfg['caption']) && $cfCfg['caption'] !== '' ? $cfCfg['caption'] : $cfFileName,
                    'url'         => $cfFileName,
                    'icon'        => isset($cfCfg['icon']) && $cfCfg['icon'] !== '' ? $cfCfg['icon'] : 'fa-dashboard',
                    'target'      => '_self',
                    'is_header'   => false,
                    'allow_anon'  => false,
                    'enabled'     => !empty($cfCfg['enabled']) && (!isset($cfCfg['in_menu']) || !empty($cfCfg['in_menu'])),
                    'source_type' => 'custom_file',
                    'source_name' => $cfKey
                );
            }

            // Prüfen, ob die typischen CRM-Demo-Tabellen vorhanden sind -> beispielhafte Verschachtelung (Ebenen) anlegen
            $hasDemoTables = (isset($tables['kunden']) && isset($tables['bestellungen']) && isset($tables['produkte']));
            if ($hasDemoTables) {
                $items[] = array(
                    'id'          => 'grp_crm_shop',
                    'parent_id'   => '',
                    'text'        => 'CRM & Shop Betrieb',
                    'url'         => '#',
                    'icon'        => 'fa-briefcase',
                    'target'      => '_self',
                    'is_header'   => false,
                    'allow_anon'  => false,
                    'enabled'     => true,
                    'source_type' => 'custom',
                    'source_name' => ''
                );
                $items[] = array(
                    'id'          => 'grp_stammdaten',
                    'parent_id'   => 'grp_crm_shop',
                    'text'        => 'Katalog & Stammdaten',
                    'url'         => '#',
                    'icon'        => 'fa-folder-open-o',
                    'target'      => '_self',
                    'is_header'   => false,
                    'allow_anon'  => false,
                    'enabled'     => true,
                    'source_type' => 'custom',
                    'source_name' => ''
                );
            }

            $secTables = array('benutzer', 'userlevels', 'userlevelpermissions');
            $hasSecFolder = false;
            foreach ($secTables as $st) {
                if (isset($tables[$st])) {
                    $hasSecFolder = true;
                    break;
                }
            }
            if ($hasSecFolder) {
                $items[] = array(
                    'id'          => 'grp_system_sec',
                    'parent_id'   => '',
                    'text'        => 'Benutzer & Rechte',
                    'url'         => '#',
                    'icon'        => 'fa-users',
                    'target'      => '_self',
                    'is_header'   => false,
                    'allow_anon'  => false,
                    'enabled'     => true,
                    'source_type' => 'custom',
                    'source_name' => ''
                );
            }

            foreach ($tables as $tName => $tCfg) {
                $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($tName));
                $parentId = '';
                if ($hasDemoTables) {
                    if ($tName === 'kategorien' || $tName === 'produkte') {
                        $parentId = 'grp_stammdaten';
                    } elseif ($tName === 'kunden' || $tName === 'bestellungen' || $tName === 'v_kunden_umsatz') {
                        $parentId = 'grp_crm_shop';
                    }
                }
                if (in_array($tName, $secTables) && $hasSecFolder) {
                    $parentId = 'grp_system_sec';
                }

                $items[] = array(
                    'id'          => 'tbl_' . $slug,
                    'parent_id'   => $parentId,
                    'text'        => isset($tCfg['caption']) && $tCfg['caption'] !== '' ? $tCfg['caption'] : $tName,
                    'url'         => $slug . 'list.php',
                    'icon'        => isset($tCfg['icon']) && $tCfg['icon'] !== '' ? $tCfg['icon'] : (($tCfg['type'] === 'VIEW') ? 'fa-eye' : 'fa-table'),
                    'target'      => '_self',
                    'is_header'   => false,
                    'allow_anon'  => false,
                    'enabled'     => !empty($tCfg['enabled']) && !empty($tCfg['list_page']),
                    'source_type' => 'table',
                    'source_name' => $tName
                );
            }

            return $items;
        }

        // Bestehendes Menü mit neuen/gelöschten Tabellen & Custom Files abgleichen (Benutzer-Hierarchie & eigene Einträge bleiben erhalten!)
        $synced = array();
        $foundTables = array();
        $foundCustomFiles = array();

        foreach ($existing as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            $sType = isset($item['source_type']) ? $item['source_type'] : 'custom';
            $sName = isset($item['source_name']) ? $item['source_name'] : '';

            if ($sType === 'table') {
                if (!isset($tables[$sName])) {
                    // Tabelle existiert nicht mehr in DB
                    continue;
                }
                $foundTables[$sName] = true;
            } elseif ($sType === 'custom_file') {
                if (!isset($customFiles[$sName])) {
                    continue;
                }
                $foundCustomFiles[$sName] = true;
                $cfCfg = $customFiles[$sName];
                $cfFileName = isset($cfCfg['filename']) ? trim($cfCfg['filename']) : $sName;
                $cfFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($cfFileName));
                if (substr(strtolower($cfFileName), -4) !== '.php') $cfFileName .= '.php';
                $item['url'] = $cfFileName;
                if (isset($cfCfg['caption']) && $cfCfg['caption'] !== '') {
                    $item['text'] = $cfCfg['caption'];
                }
                if (isset($cfCfg['icon']) && $cfCfg['icon'] !== '') {
                    $item['icon'] = $cfCfg['icon'];
                }
                $item['enabled'] = !empty($cfCfg['enabled']) && (!isset($cfCfg['in_menu']) || !empty($cfCfg['in_menu']));
            }

            $synced[] = array(
                'id'          => (string)$item['id'],
                'parent_id'   => isset($item['parent_id']) ? (string)$item['parent_id'] : '',
                'text'        => isset($item['text']) ? (string)$item['text'] : 'Menüpunkt',
                'url'         => isset($item['url']) ? (string)$item['url'] : '#',
                'icon'        => isset($item['icon']) ? (string)$item['icon'] : 'fa-circle-o',
                'target'      => isset($item['target']) ? (string)$item['target'] : '_self',
                'is_header'   => !empty($item['is_header']),
                'allow_anon'  => !empty($item['allow_anon']),
                'enabled'     => isset($item['enabled']) ? (bool)$item['enabled'] : true,
                'source_type' => $sType,
                'source_name' => $sName
            );
        }

        // Neu hinzugekommene Custom Files anhängen
        foreach ($customFiles as $cfKey => $cfCfg) {
            if (!isset($foundCustomFiles[$cfKey])) {
                $cfFileName = isset($cfCfg['filename']) ? trim($cfCfg['filename']) : $cfKey;
                $cfFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($cfFileName));
                if ($cfFileName === '') continue;
                if (substr(strtolower($cfFileName), -4) !== '.php') $cfFileName .= '.php';

                $synced[] = array(
                    'id'          => 'cf_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $cfKey),
                    'parent_id'   => '',
                    'text'        => isset($cfCfg['caption']) && $cfCfg['caption'] !== '' ? $cfCfg['caption'] : $cfFileName,
                    'url'         => $cfFileName,
                    'icon'        => isset($cfCfg['icon']) && $cfCfg['icon'] !== '' ? $cfCfg['icon'] : 'fa-file-code-o',
                    'target'      => '_self',
                    'is_header'   => false,
                    'allow_anon'  => false,
                    'enabled'     => !empty($cfCfg['enabled']) && (!isset($cfCfg['in_menu']) || !empty($cfCfg['in_menu'])),
                    'source_type' => 'custom_file',
                    'source_name' => $cfKey
                );
            }
        }

        // Neu hinzugekommene Tabellen anhängen
        foreach ($tables as $tName => $tCfg) {
            if (!isset($foundTables[$tName])) {
                $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($tName));
                $synced[] = array(
                    'id'          => 'tbl_' . $slug,
                    'parent_id'   => '',
                    'text'        => isset($tCfg['caption']) && $tCfg['caption'] !== '' ? $tCfg['caption'] : $tName,
                    'url'         => $slug . 'list.php',
                    'icon'        => isset($tCfg['icon']) && $tCfg['icon'] !== '' ? $tCfg['icon'] : 'fa-table',
                    'target'      => '_self',
                    'is_header'   => false,
                    'allow_anon'  => false,
                    'enabled'     => !empty($tCfg['enabled']) && !empty($tCfg['list_page']),
                    'source_type' => 'table',
                    'source_name' => $tName
                );
            }
        }

        return $synced;
    }

    /**
     * Bereinigt einen übergebenen Projektnamen/Dateinamen ohne Endung.
     * @param string $filename
     * @return string
     */
    public static function sanitizeProjectSlug($filename)
    {
        $base = basename((string)$filename);
        $base = preg_replace('/\.pmp\.json$/i', '', $base);
        $base = preg_replace('/\.json$/i', '', $base);
        $base = preg_replace('/\.pmp$/i', '', $base);
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $base);
    }

    /**
     * Speichert ein Projekt als JSON-Datei (.pmp.json).
     * @param string $filename
     * @param array $projectData
     * @param string $actingUser Optional: Benutzername des speichernden Benutzers
     * @return bool|string Dateiname bei Erfolg, sonst false
     */
    public function saveProject($filename, $projectData, $actingUser = '')
    {
        if (!is_dir($this->projectsDir)) {
            @mkdir($this->projectsDir, 0777, true);
        }
        $cleanName = self::sanitizeProjectSlug($filename);
        if ($cleanName === '') {
            $cleanName = 'project_' . time();
        }
        $existingPath = $this->getProjectFilePath($cleanName);
        $existingData = null;
        if ($existingPath !== false && is_file($existingPath)) {
            $rawOld = @file_get_contents($existingPath);
            $existingData = ($rawOld !== false) ? self::decodeProjectJson($rawOld) : null;
        }
        if (is_array($projectData)) {
            // Owner & Sharing-Metadaten bewahren (oder für neue Projekte setzen)
            if (is_array($existingData) && !empty($existingData['owner'])) {
                $projectData['owner'] = strtolower(trim((string)$existingData['owner']));
            } elseif (empty($projectData['owner'])) {
                $projectData['owner'] = ($actingUser !== '') ? strtolower(trim((string)$actingUser)) : 'admin';
            }
            if (is_array($existingData) && isset($existingData['shared_with']) && is_array($existingData['shared_with']) && !isset($projectData['shared_with'])) {
                $projectData['shared_with'] = $existingData['shared_with'];
            } elseif (!isset($projectData['shared_with']) || !is_array($projectData['shared_with'])) {
                $projectData['shared_with'] = array();
            }
            if ($actingUser !== '') {
                $projectData['last_modified_by'] = strtolower(trim((string)$actingUser));
            } elseif (empty($projectData['last_modified_by'])) {
                $projectData['last_modified_by'] = $projectData['owner'];
            }
            $projectData['menu_items'] = self::synchronizeMenuItems($projectData);
            $projectData['updated_at'] = date('Y-m-d H:i:s');
        }
        $path = $this->projectsDir . '/' . $cleanName . '.pmp.json';
        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;
        $json = json_encode($projectData, $flags);
        if (@file_put_contents($path, $json) !== false) {
            // Sicherstellen, dass das Git-Repository für dieses Projekt initialisiert ist
            if ($this->vcs !== null && is_array($projectData)) {
                $this->vcs->ensureInitialized(
                    $cleanName,
                    $projectData,
                    !empty($projectData['last_modified_by']) ? $projectData['last_modified_by'] : 'admin'
                );
            }
            return $cleanName;
        }
        return false;
    }

    /**
     * Aktualisiert die Team-Freigaben (shared_with) eines Projekts.
     * @param string $filename
     * @param array $sharedWith Map username => array('role' => 'write'|'read', ...)
     * @param string $actingUser
     * @param string $newOwner Optionaler Eigentümerwechsel
     * @return array
     */
    public function updateProjectSharing($filename, $sharedWith, $actingUser = 'admin', $newOwner = '')
    {
        $path = $this->getProjectFilePath($filename);
        if ($path === false || !is_file($path)) {
            return array('success' => false, 'error' => 'Projektdatei "' . $filename . '" wurde nicht gefunden.');
        }
        $raw = @file_get_contents($path);
        $data = ($raw !== false) ? self::decodeProjectJson($raw) : null;
        if (!is_array($data)) {
            return array('success' => false, 'error' => 'Projektdatei konnte nicht gelesen werden.');
        }

        $owner = !empty($data['owner']) ? strtolower(trim((string)$data['owner'])) : 'admin';
        if ($newOwner !== '') {
            $cleanNewOwner = strtolower(trim((string)$newOwner));
            if ($cleanNewOwner !== '') {
                $owner = $cleanNewOwner;
            }
        }
        $data['owner'] = $owner;

        $cleanShared = array();
        if (is_array($sharedWith)) {
            foreach ($sharedWith as $uKey => $meta) {
                $uName = is_array($meta) && isset($meta['username']) ? strtolower(trim((string)$meta['username'])) : strtolower(trim((string)$uKey));
                $uName = preg_replace('/[^a-z0-9_.-]/', '', $uName);
                if ($uName === '' || $uName === $owner) {
                    continue;
                }
                $role = 'write';
                if (is_array($meta) && isset($meta['role'])) {
                    $role = ($meta['role'] === 'read') ? 'read' : 'write';
                } elseif (is_string($meta) && $meta === 'read') {
                    $role = 'read';
                }
                $cleanShared[$uName] = array(
                    'role'      => $role,
                    'shared_by' => $actingUser,
                    'shared_at' => (is_array($meta) && !empty($meta['shared_at'])) ? $meta['shared_at'] : date('Y-m-d H:i:s')
                );
            }
        }
        $data['shared_with'] = $cleanShared;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;
        if (@file_put_contents($path, json_encode($data, $flags)) === false) {
            return array('success' => false, 'error' => 'Freigabe-Einstellungen konnten nicht gespeichert werden.');
        }

        return array(
            'success'     => true,
            'owner'       => $owner,
            'shared_with' => $cleanShared,
            'message'     => 'Team-Freigaben und Eigentümer für Projekt "' . (isset($data['project_name']) ? $data['project_name'] : $filename) . '" wurden gespeichert.'
        );
    }

    /**
     * Überträgt alle Projekte eines gelöschten (oder umzuziehenden) Benutzers auf einen neuen Eigentümer
     * (standardmäßig 'admin') und entfernt den gelöschten Benutzer aus allen Freigabelisten (shared_with),
     * sodass sämtliche Projekte vollständig erhalten bleiben und vom Admin neu zugeordnet oder geteilt werden können.
     *
     * @param string $deletedUsername
     * @param string $newOwnerUsername
     * @return array Liste der übernommenen Projektnamen
     */
    public function reassignUserProjects($deletedUsername, $newOwnerUsername = 'admin')
    {
        $delUser = strtolower(trim((string)$deletedUsername));
        $newOwner = strtolower(trim((string)$newOwnerUsername));
        if ($newOwner === '' || $newOwner === $delUser) {
            $newOwner = 'admin';
        }
        $reassigned = array();
        if ($delUser === '' || !is_dir($this->projectsDir)) {
            return $reassigned;
        }

        $entries = @scandir($this->projectsDir);
        if (!is_array($entries)) {
            return $reassigned;
        }
        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || substr($entry, 0, 1) === '.') {
                continue;
            }
            if (!preg_match('/\.pmp\.json$/i', $entry) && !preg_match('/\.json$/i', $entry)) {
                continue;
            }
            $f = $this->projectsDir . '/' . $entry;
            if (!is_file($f)) {
                continue;
            }
            $raw = @file_get_contents($f);
            $data = ($raw !== false) ? self::decodeProjectJson($raw) : null;
            if (!is_array($data)) {
                continue;
            }

            $changed = false;
            $curOwner = !empty($data['owner']) ? strtolower(trim((string)$data['owner'])) : 'admin';
            if ($curOwner === $delUser) {
                $data['owner'] = $newOwner;
                $data['previous_owner'] = $delUser;
                $changed = true;
                $reassigned[] = isset($data['project_name']) ? $data['project_name'] : $entry;
            }

            if (isset($data['shared_with']) && is_array($data['shared_with'])) {
                foreach (array_keys($data['shared_with']) as $shKey) {
                    $shNorm = strtolower(trim((string)$shKey));
                    if ($shNorm === $delUser || $shNorm === $data['owner']) {
                        unset($data['shared_with'][$shKey]);
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $data['updated_at'] = date('Y-m-d H:i:s');
                @file_put_contents($f, json_encode($data, $flags));
            }
        }

        return $reassigned;
    }

    /**
     * Löscht eine gespeicherte Projektdatei (.pmp.json).
     * @param string $filename
     * @return bool
     */
    public function deleteProject($filename)
    {
        $path = $this->getProjectFilePath($filename);
        if ($path !== false && is_file($path)) {
            return @unlink($path);
        }
        return false;
    }

    /**
     * Gibt den absoluten Dateipfad zu einer gespeicherten Projektdatei (.pmp.json) zurück.
     * Unterstützt unter Linux/Debian auch Case-Insensitive-Fallback sowie .json Endungen.
     * @param string $filename
     * @return string|false
     */
    public function getProjectFilePath($filename)
    {
        $cleanName = self::sanitizeProjectSlug($filename);
        if ($cleanName === '') {
            return false;
        }
        $exactPath = $this->projectsDir . '/' . $cleanName . '.pmp.json';
        if (is_file($exactPath)) {
            return $exactPath;
        }
        $altJsonPath = $this->projectsDir . '/' . $cleanName . '.json';
        if (is_file($altJsonPath)) {
            return $altJsonPath;
        }
        // Case-Insensitive Fallback für Linux/Unix-Dateisysteme
        if (is_dir($this->projectsDir)) {
            $entries = @scandir($this->projectsDir);
            if (is_array($entries)) {
                $targetLower1 = strtolower($cleanName . '.pmp.json');
                $targetLower2 = strtolower($cleanName . '.json');
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $entryLower = strtolower($entry);
                    if ($entryLower === $targetLower1 || $entryLower === $targetLower2) {
                        $foundPath = $this->projectsDir . '/' . $entry;
                        if (is_file($foundPath)) {
                            return $foundPath;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Lädt ein Projekt anhand des Dateinamens.
     * Falls das Projekt auf einem neu portierten System noch nicht existiert und das Verzeichnis
     * leer ist (oder das angeforderte Projekt nicht existiert, aber ein anderes Projekt vorhanden ist),
     * wird intelligent auf ein verfügbares Projekt oder ein neues Standardprojekt zurückgegriffen.
     * @param string $filename
     * @return array|false
     */
    public function loadProject($filename)
    {
        $path = $this->getProjectFilePath($filename);
        if ($path === false || !is_file($path)) {
            // Prüfen, ob andere Projekte im Verzeichnis liegen (z.B. nach Portierung ohne crm_shop_demo)
            $existing = $this->listProjects();
            if (!empty($existing) && isset($existing[0]['filename'])) {
                $path = $this->getProjectFilePath($existing[0]['filename']);
            }
        }
        if ($path === false || !is_file($path)) {
            // Wenn überhaupt noch keine Projektdatei auf dem Zielsystem existiert:
            // Standardprojekt automatisch initialisieren (und nach Möglichkeit speichern)
            $defaultName = ($filename !== '' && $filename !== 'crm_shop_demo') ? self::sanitizeProjectSlug($filename) : 'MeinProjekt';
            $defaultProj = self::createDefaultProject($defaultName);
            $this->saveProject($defaultName, $defaultProj);
            return $defaultProj;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return false;
        }
        $data = self::decodeProjectJson($content);
        if (!is_array($data)) {
            return false;
        }
        if (!isset($data['security']) || !is_array($data['security'])) {
            $data['security'] = self::createDefaultSecurityConfig();
        } else {
            $data['security'] = array_merge(self::createDefaultSecurityConfig(), $data['security']);
        }
        if (isset($data['tables']) && is_array($data['tables'])) {
            foreach ($data['tables'] as $tName => $tCfg) {
                if (!isset($data['tables'][$tName]['user_id_field'])) {
                    $data['tables'][$tName]['user_id_field'] = '';
                }
                if (!isset($data['security']['static_permissions'][$tName])) {
                    $data['security']['static_permissions'][$tName] = array(
                        '-2' => 0,
                        '-1' => 127,
                        '0' => 109,
                        '1' => 111,
                        '2' => 104
                    );
                }
            }
            $data['tables'] = self::synchronizeMasterDetails($data['tables']);
        }
        if (!isset($data['custom_files']) || !is_array($data['custom_files'])) {
            $data['custom_files'] = array();
        }
        // Sicherstellen, dass das CRM-Demo-Dashboard (dashboard.php aus createDefaultCustomFiles)
        // nur im CRM-Demo-Projekt (oder Projekten mit den CRM-Tabellen kunden/bestellungen/produkte) angezeigt wird
        $hasCrmTables = (isset($data['tables']['kunden']) && isset($data['tables']['bestellungen']) && isset($data['tables']['produkte']));
        $isCrmProject = (strtolower(basename($path, '.pmp.json')) === 'crm_shop_demo') || $hasCrmTables;
        if (!$isCrmProject && isset($data['custom_files']['dashboard.php'])) {
            $cfContent = isset($data['custom_files']['dashboard.php']['content']) ? (string)$data['custom_files']['dashboard.php']['content'] : '';
            if (strpos($cfContent, 'SELECT COUNT(*) FROM `kunden`') !== false && strpos($cfContent, 'SELECT COUNT(*) FROM `bestellungen`') !== false) {
                unset($data['custom_files']['dashboard.php']);
            }
        }
        foreach ($data['custom_files'] as $cfKey => $cfCfg) {
            if (!isset($data['security']['static_permissions'][$cfKey])) {
                $data['security']['static_permissions'][$cfKey] = array(
                    '-2' => 0,
                    '-1' => 127,
                    '0'  => 104,
                    '1'  => 104,
                    '2'  => 104
                );
            }
        }
        if (!isset($data['server_events']) || !is_array($data['server_events'])) {
            $data['server_events'] = array('global' => array(), 'tables' => array());
        } else {
            if (!isset($data['server_events']['global']) || !is_array($data['server_events']['global'])) {
                $data['server_events']['global'] = array();
            }
            if (!isset($data['server_events']['tables']) || !is_array($data['server_events']['tables'])) {
                $data['server_events']['tables'] = array();
            }
        }
        if (!isset($data['client_scripts']) || !is_array($data['client_scripts'])) {
            $data['client_scripts'] = array('global' => array(), 'tables' => array());
        } else {
            if (!isset($data['client_scripts']['global']) || !is_array($data['client_scripts']['global'])) {
                $data['client_scripts']['global'] = array();
            }
            if (!isset($data['client_scripts']['tables']) || !is_array($data['client_scripts']['tables'])) {
                $data['client_scripts']['tables'] = array();
            }
        }
        $data['menu_items'] = self::synchronizeMenuItems($data);
        if (empty($data['owner'])) {
            $data['owner'] = 'admin';
        }
        if (!isset($data['shared_with']) || !is_array($data['shared_with'])) {
            $data['shared_with'] = array();
        }
        if (empty($data['last_modified_by'])) {
            $data['last_modified_by'] = $data['owner'];
        }
        $actualSlug = self::sanitizeProjectSlug(basename($path));
        if ($this->vcs !== null && $actualSlug !== '') {
            $this->vcs->ensureInitialized($actualSlug, $data, $data['owner']);
        }
        return $data;
    }

    /**
     * Erkennt automatisch Master/Detail-Verknüpfungen anhand von Lookup-/Foreign-Key-Feldern,
     * falls für eine Master-Tabelle noch keine Master/Detail-Beziehungen definiert wurden.
     *
     * @param array $tables
     * @return array
     */
    public static function synchronizeMasterDetails($tables)
    {
        if (!is_array($tables)) {
            return array();
        }

        // Sicherstellen, dass jede Tabelle das Array 'master_details' besitzt
        foreach ($tables as $tName => $tCfg) {
            if (!isset($tables[$tName]['master_details']) || !is_array($tables[$tName]['master_details'])) {
                $tables[$tName]['master_details'] = array();
            } else {
                // Ungültige Referenzen auf nicht mehr existierende Tabellen bereinigen
                $validRels = array();
                foreach ($tables[$tName]['master_details'] as $rel) {
                    if (!empty($rel['detail_table']) && isset($tables[$rel['detail_table']])) {
                        $validRels[] = array(
                            'detail_table' => (string)$rel['detail_table'],
                            'master_field' => (string)$rel['master_field'],
                            'detail_field' => (string)$rel['detail_field'],
                            'preview_row'  => isset($rel['preview_row']) ? (bool)$rel['preview_row'] : true,
                            'detail_count' => isset($rel['detail_count']) ? (bool)$rel['detail_count'] : true
                        );
                    }
                }
                $tables[$tName]['master_details'] = $validRels;
            }
        }

        // Prüfen, ob überhaupt schon irgendeine Master/Detail-Beziehung im Projekt existiert
        $hasAnyRelation = false;
        foreach ($tables as $tName => $tCfg) {
            if (!empty($tCfg['master_details'])) {
                $hasAnyRelation = true;
                break;
            }
        }

        // Wenn noch gar keine Master/Detail-Verknüpfung existiert, aus Foreign-Keys / Lookup-Feldern ableiten
        if (!$hasAnyRelation) {
            foreach ($tables as $detailName => $detailCfg) {
                if (isset($detailCfg['type']) && $detailCfg['type'] === 'VIEW') {
                    continue;
                }
                // Systemtabellen (userlevelpermissions, benutzer) nicht automatisch als Detail-Unterliste an userlevels hängen
                if ($detailName === 'userlevelpermissions' || $detailName === 'benutzer') {
                    continue;
                }
                $fields = isset($detailCfg['fields']) && is_array($detailCfg['fields']) ? $detailCfg['fields'] : array();
                foreach ($fields as $fName => $fMeta) {
                    if (!empty($fMeta['lookup_table']) && !empty($fMeta['lookup_link_field'])) {
                        $masterName = $fMeta['lookup_table'];
                        $masterField = $fMeta['lookup_link_field'];
                        if ($masterName !== $detailName && isset($tables[$masterName]) && isset($tables[$masterName]['fields'][$masterField])) {
                            $tables[$masterName]['master_details'][] = array(
                                'detail_table' => $detailName,
                                'master_field' => $masterField,
                                'detail_field' => $fName,
                                'preview_row'  => true,
                                'detail_count' => true
                            );
                        }
                    }
                }
            }
        }

        return $tables;
    }
}
