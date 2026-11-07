<?php
/**
 * PHPMacherNG - Code Generator Engine
 * Generiert eine vollständige, eigenständige PHP Web-Applikation (kompatibel mit PHP 5.4 bis PHP 8.4+)
 * im klassischen PHPMacherNG (v14) Stil mit AdminLTE 2 / Bootstrap 3 UI.
 */

class GeneratorEngine
{
    public $project = array();
    public $baseDir = '';
    public $outputDir = '';
    public $generatedFiles = array();
    public $errors = array();

    public function __construct($project, $baseDir = '')
    {
        $this->project = $project;
        $this->baseDir = ($baseDir !== '') ? rtrim($baseDir, '/\\') : dirname(dirname(__FILE__));
    }

    /**
     * Führt die vollständige Code-Generierung aus.
     * @return array Ergebnisbericht mit generierten Dateien
     */
    public function generateAll()
    {
        $this->generatedFiles = array();
        $this->errors = array();

        $relOut = isset($this->project['settings']['output_folder']) ? trim($this->project['settings']['output_folder']) : 'output/app';
        if ($relOut === '') {
            $relOut = 'output/app';
        }
        // Verhindere Directory-Traversal außerhalb des Projekts, erlaube aber Unterordner
        $relOut = str_replace('..', '', $relOut);
        $relOut = ltrim(str_replace('\\', '/', $relOut), '/');

        $this->outputDir = $this->baseDir . '/' . $relOut;
        if (!is_dir($this->outputDir)) {
            if (!@mkdir($this->outputDir, 0777, true)) {
                $this->errors[] = 'Ausgabeverzeichnis konnte nicht erstellt werden: ' . $this->outputDir;
                return array('success' => false, 'errors' => $this->errors, 'files' => array());
            }
        }

        // 0. Lokale Frontend-Bibliotheken (Bootstrap 3, jQuery, AdminLTE 2, FontAwesome) ins Zielprojekt kopieren
        $this->copyFrontendAssets();

        // 1. Gemeinsame Systemdateien generieren (ewcfg14.php, ewdbhelper14.php, phpfn14.php, header.php, footer.php, index.php)
        $this->writeFile('ewcfg14.php', $this->buildEwCfg());
        $this->writeFile('ewdbhelper14.php', $this->buildEwDbHelper());
        $this->writeFile('phpfn14.php', $this->buildPhpFn());
        $this->writeFile('header.php', $this->buildHeader());
        $this->writeFile('footer.php', $this->buildFooter());
        $this->writeFile('index.php', $this->buildIndex());

        // 1b. Security-Seiten generieren (login.php, logout.php, userpriv.php)
        $sec = isset($this->project['security']) && is_array($this->project['security']) ? $this->project['security'] : array();
        if (!empty($sec['enabled'])) {
            $this->writeFile('login.php', $this->buildLoginPage());
            $this->writeFile('logout.php', $this->buildLogoutPage());
            if (!empty($sec['user_level_security'])) {
                $this->writeFile('userpriv.php', $this->buildUserPrivPage());
            }
        }

        // 2. Pro aktivierter Tabelle alle CRUD-Dateien generieren
        $tables = isset($this->project['tables']) && is_array($this->project['tables']) ? $this->project['tables'] : array();
        foreach ($tables as $tName => $tCfg) {
            if (empty($tCfg['enabled'])) {
                continue;
            }
            $slug = self::tableSlug($tName);
            $this->writeFile($slug . 'info.php', $this->buildTableInfo($tCfg));

            if (!empty($tCfg['list_page'])) {
                $this->writeFile($slug . 'list.php', $this->buildListPage($tCfg));
                $this->writeFile($slug . 'preview.php', $this->buildPreviewPage($tCfg));
            }
            if (!empty($tCfg['add_page']) && !empty($tCfg['primary_keys'])) {
                $this->writeFile($slug . 'add.php', $this->buildAddPage($tCfg));
            }
            if (!empty($tCfg['edit_page']) && !empty($tCfg['primary_keys'])) {
                $this->writeFile($slug . 'edit.php', $this->buildEditPage($tCfg));
            }
            if (!empty($tCfg['view_page']) && !empty($tCfg['primary_keys'])) {
                $this->writeFile($slug . 'view.php', $this->buildViewPage($tCfg));
            }
            if ((!empty($tCfg['delete_page']) || !empty($tCfg['multi_delete'])) && !empty($tCfg['primary_keys'])) {
                $this->writeFile($slug . 'delete.php', $this->buildDeletePage($tCfg));
            }
            if (!empty($tCfg['multi_update']) && !empty($tCfg['primary_keys'])) {
                $this->writeFile($slug . 'update.php', $this->buildMultiUpdatePage($tCfg));
            }
        }

        // 3. Eigene Projektdateien (PHPMacherNG "Custom Files") generieren
        $customFiles = isset($this->project['custom_files']) && is_array($this->project['custom_files']) ? $this->project['custom_files'] : array();
        foreach ($customFiles as $cfKey => $cfCfg) {
            if (empty($cfCfg['enabled'])) {
                continue;
            }
            $cfFileName = isset($cfCfg['filename']) ? trim($cfCfg['filename']) : $cfKey;
            $cfFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($cfFileName));
            if ($cfFileName === '') {
                continue;
            }
            if (substr(strtolower($cfFileName), -4) !== '.php') {
                $cfFileName .= '.php';
            }
            $this->writeFile($cfFileName, $this->buildCustomFilePage($cfCfg, $cfFileName));
        }

        return array(
            'success' => empty($this->errors),
            'output_dir' => $this->outputDir,
            'relative_url' => $relOut . '/index.php',
            'files' => $this->generatedFiles,
            'errors' => $this->errors
        );
    }

    /**
     * Kopiert Bootstrap 3, jQuery, AdminLTE 2 und FontAwesome lokal in den Zielordner,
     * damit die generierte Anwendung zu 100% eigenständig und offline-fähig ist.
     */
    protected function copyFrontendAssets()
    {
        $vendorSrc = $this->baseDir . '/assets/vendor';
        if (!is_dir($vendorSrc)) {
            return;
        }
        $this->copyDirRecursive($vendorSrc, $this->outputDir . '/assets/vendor', 'assets/vendor');
    }

    protected function copyDirRecursive($srcDir, $dstDir, $relPrefix)
    {
        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0777, true);
        }
        $items = @scandir($srcDir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $sPath = $srcDir . '/' . $item;
            $dPath = $dstDir . '/' . $item;
            $rPath = $relPrefix . '/' . $item;
            if (is_dir($sPath)) {
                $this->copyDirRecursive($sPath, $dPath, $rPath);
            } elseif (is_file($sPath)) {
                if (@copy($sPath, $dPath)) {
                    $this->generatedFiles[] = array(
                        'name' => $rPath,
                        'size' => filesize($dPath),
                        'timestamp' => date('H:i:s')
                    );
                }
            }
        }
    }

    protected function writeFile($fileName, $content)
    {
        $fullPath = $this->outputDir . '/' . $fileName;
        if (@file_put_contents($fullPath, $content) !== false) {
            $this->generatedFiles[] = array(
                'name' => $fileName,
                'size' => strlen($content),
                'timestamp' => date('H:i:s')
            );
        } else {
            $this->errors[] = 'Fehler beim Schreiben der Datei: ' . $fileName;
        }
    }

    public static function tableSlug($tableName)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($tableName));
    }

    public static function varExp($val)
    {
        return var_export($val, true);
    }

    /**
     * Entfernt optionale umschließende <?php ... ?> oder <script>...</script> Tags aus Event-Code.
     */
    protected static function cleanPhpEventCode($code)
    {
        $code = trim((string)$code);
        if ($code === '') {
            return '';
        }
        if (preg_match('/^<\?(?:php)?\s*(.*?)\s*(?:\?>)?$/is', $code, $m)) {
            $code = trim($m[1]);
        }
        return $code;
    }

    protected static function cleanJsScriptCode($code)
    {
        $code = trim((string)$code);
        if ($code === '') {
            return '';
        }
        if (preg_match('/^<script[^>]*>\s*(.*?)\s*<\/script>$/is', $code, $m)) {
            $code = trim($m[1]);
        }
        return $code;
    }

    protected function getGlobalServerEvent($eventName)
    {
        if (isset($this->project['server_events']['global'][$eventName])) {
            return self::cleanPhpEventCode($this->project['server_events']['global'][$eventName]);
        }
        return '';
    }

    protected function getTableServerEvent($tableName, $eventName)
    {
        if (isset($this->project['server_events']['tables'][$tableName][$eventName])) {
            return self::cleanPhpEventCode($this->project['server_events']['tables'][$tableName][$eventName]);
        }
        return '';
    }

    protected function getGlobalClientScript($scriptName)
    {
        if (isset($this->project['client_scripts']['global'][$scriptName])) {
            return self::cleanJsScriptCode($this->project['client_scripts']['global'][$scriptName]);
        }
        return '';
    }

    protected function getTableClientScript($tableName, $scriptName)
    {
        if (isset($this->project['client_scripts']['tables'][$tableName][$scriptName])) {
            return self::cleanJsScriptCode($this->project['client_scripts']['tables'][$tableName][$scriptName]);
        }
        return '';
    }

    /**
     * Erzeugt ewcfg14.php (Konfigurationsdatei inkl. Security & Rollen-Bitmasken)
     */
    protected function buildEwCfg()
    {
        $db = isset($this->project['database']) ? $this->project['database'] : array();
        $st = isset($this->project['settings']) ? $this->project['settings'] : array();
        $sec = isset($this->project['security']) && is_array($this->project['security']) ? $this->project['security'] : array();

        $host = isset($db['host']) ? $db['host'] : '127.0.0.1';
        $port = isset($db['port']) ? (int)$db['port'] : 3306;
        $user = isset($db['user']) ? $db['user'] : 'root';
        $pass = isset($db['pass']) ? $db['pass'] : '';
        $dbname = isset($db['dbname']) ? $db['dbname'] : '';
        $charset = isset($db['charset']) && $db['charset'] !== '' ? $db['charset'] : 'utf8';

        $title = isset($st['title']) ? $st['title'] : 'PHPMacherNG Application';
        $uiTemplate = isset($st['ui_template']) && $st['ui_template'] !== '' ? $st['ui_template'] : 'adminlte2';
        $skin = isset($st['theme_skin']) ? $st['theme_skin'] : 'skin-blue';
        $layout = isset($st['layout_style']) ? $st['layout_style'] : 'sidebar-mini';
        $recPerPage = isset($st['records_per_page']) && (int)$st['records_per_page'] > 0 ? (int)$st['records_per_page'] : 20;
        $pageSizes = isset($st['page_sizes']) && $st['page_sizes'] !== '' ? $st['page_sizes'] : '10,20,50,100,ALL';
        $dateFormat = isset($st['date_format']) && $st['date_format'] !== '' ? $st['date_format'] : 'Y-m-d';
        $currency = isset($st['currency_symbol']) && $st['currency_symbol'] !== '' ? $st['currency_symbol'] : '€';
        $timezone = isset($st['timezone']) && $st['timezone'] !== '' ? $st['timezone'] : 'Europe/Berlin';
        $footerText = isset($st['footer_text']) ? $st['footer_text'] : 'Generiert mit PHPMacherNG';
        $debug = !empty($st['show_sql_debug']);
        $confirm = isset($st['confirm_action']) ? (bool)$st['confirm_action'] : true;
        $projId = isset($this->project['project_id']) ? $this->project['project_id'] : '{PHPMACHERNG-2018-APP}';

        $code = "<?php\n";
        $code .= "/**\n";
        $code .= " * PHPMacherNG (v14) Konfigurationsdatei (ewcfg14.php)\n";
        $code .= " * Kompatibel mit PHP 5.4 bis PHP 8.4+\n";
        $code .= " * Generiert am: " . date('Y-m-d H:i:s') . "\n";
        $code .= " */\n\n";
        $code .= "if (function_exists('date_default_timezone_set')) {\n";
        $code .= "    @date_default_timezone_set(" . self::varExp($timezone) . ");\n";
        $code .= "}\n\n";
        $code .= "// Projekt-Identifikation\n";
        $code .= "define('EW_PROJECT_NAME', " . self::varExp($this->project['project_name']) . ");\n";
        $code .= "define('EW_PROJECT_ID', " . self::varExp($projId) . ");\n";
        $code .= "define('EW_PROJECT_TITLE', " . self::varExp($title) . ");\n\n";
        $code .= "// Datenbank-Konfiguration\n";
        $code .= "define('EW_CONN_HOST', " . self::varExp($host) . ");\n";
        $code .= "define('EW_CONN_PORT', " . $port . ");\n";
        $code .= "define('EW_CONN_USER', " . self::varExp($user) . ");\n";
        $code .= "define('EW_CONN_PASS', " . self::varExp($pass) . ");\n";
        $code .= "define('EW_CONN_DB', " . self::varExp($dbname) . ");\n";
        $code .= "define('EW_CONN_CHARSET', " . self::varExp($charset) . ");\n\n";
        $code .= "// Darstellung & Verhalten\n";
        $code .= "define('EW_UI_TEMPLATE', " . self::varExp($uiTemplate) . ");\n";
        $code .= "define('EW_THEME_SKIN', " . self::varExp($skin) . ");\n";
        $code .= "define('EW_LAYOUT_STYLE', " . self::varExp($layout) . ");\n";
        $code .= "define('EW_RECORDS_PER_PAGE', " . $recPerPage . ");\n";
        $code .= "define('EW_PAGE_SIZES', " . self::varExp($pageSizes) . ");\n";
        $code .= "define('EW_DATE_FORMAT', " . self::varExp($dateFormat) . ");\n";
        $code .= "define('EW_CURRENCY_SYMBOL', " . self::varExp($currency) . ");\n";
        $code .= "define('EW_FOOTER_TEXT', " . self::varExp($footerText) . ");\n";
        $code .= "define('EW_DEBUG_ENABLED', " . ($debug ? 'true' : 'false') . ");\n";
        $code .= "define('EW_CONFIRM_ACTION', " . ($confirm ? 'true' : 'false') . ");\n\n";

        // PHPMacherNG Berechtigungs-Bitmasken
        $code .= "// PHPMacherNG Security Bitmasks\n";
        $code .= "define('EW_ALLOW_ADD', 1);\n";
        $code .= "define('EW_ALLOW_DELETE', 2);\n";
        $code .= "define('EW_ALLOW_EDIT', 4);\n";
        $code .= "define('EW_ALLOW_LIST', 8);\n";
        $code .= "define('EW_ALLOW_ADMIN', 16);\n";
        $code .= "define('EW_ALLOW_VIEW', 32);\n";
        $code .= "define('EW_ALLOW_SEARCH', 64);\n";
        $code .= "define('EW_ALLOW_ALL', 127);\n\n";

        $code .= "\$EW_SECURITY_CONFIG = " . self::varExp($sec) . ";\n";

        return $code;
    }

    /**
     * Erzeugt ewdbhelper14.php (Datenbank-Abstraktionsschicht für PHP 5.4 - 8.4+)
     */
    protected function buildEwDbHelper()
    {
        return <<<'PHP'
<?php
/**
 * PHPMacherNG (v14) Database Helper (ewdbhelper14.php)
 * Unterstützt PDO_MySQL und MySQLi gleichermaßen, kompatibel mit PHP 5.4 bis PHP 8.4+.
 */

require_once dirname(__FILE__) . '/ewcfg14.php';

class cEwDbConnection
{
    public $driver = '';
    public $pdo = null;
    public $mysqli = null;
    public $lastError = '';
    public $sqlLog = array();

    public function __construct()
    {
        $this->Connect();
    }

    public function Connect()
    {
        $this->lastError = '';
        if (extension_loaded('pdo_mysql')) {
            try {
                $dsn = 'mysql:host=' . EW_CONN_HOST . ';port=' . EW_CONN_PORT . ';dbname=' . EW_CONN_DB . ';charset=' . EW_CONN_CHARSET;
                $options = array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                );
                $this->pdo = new PDO($dsn, EW_CONN_USER, EW_CONN_PASS, $options);
                $this->driver = 'pdo';
                return true;
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif (extension_loaded('mysqli')) {
            $m = @new mysqli(EW_CONN_HOST, EW_CONN_USER, EW_CONN_PASS, EW_CONN_DB, EW_CONN_PORT);
            if ($m->connect_error) {
                $this->lastError = $m->connect_error;
                return false;
            }
            $m->set_charset(EW_CONN_CHARSET);
            $this->mysqli = $m;
            $this->driver = 'mysqli';
            return true;
        } else {
            $this->lastError = 'Keine MySQL-Erweiterung (PDO_MySQL oder MySQLi) geladen.';
            return false;
        }
    }

    public function IsConnected()
    {
        return ($this->driver === 'pdo' && $this->pdo !== null) || ($this->driver === 'mysqli' && $this->mysqli !== null);
    }

    /**
     * Führt eine SQL-Abfrage mit optionalen Prepared-Statement-Parametern aus.
     * @param string $sql
     * @param array $params
     * @return bool
     */
    public function Execute($sql, $params = array())
    {
        if (EW_DEBUG_ENABLED) {
            $this->sqlLog[] = array('sql' => $sql, 'params' => $params);
        }
        if (!$this->IsConnected()) {
            return false;
        }
        $this->lastError = '';

        if ($this->driver === 'pdo') {
            try {
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute(array_values($params));
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif ($this->driver === 'mysqli') {
            $boundSql = $this-> interpolateQuery($sql, $params);
            $res = $this->mysqli->query($boundSql);
            if (!$res) {
                $this->lastError = $this->mysqli->error;
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Lädt alle Datensätze einer SELECT-Abfrage als Array.
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function LoadRows($sql, $params = array())
    {
        if (EW_DEBUG_ENABLED) {
            $this->sqlLog[] = array('sql' => $sql, 'params' => $params);
        }
        if (!$this->IsConnected()) {
            return false;
        }
        $this->lastError = '';

        if ($this->driver === 'pdo') {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_values($params));
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif ($this->driver === 'mysqli') {
            $boundSql = $this->interpolateQuery($sql, $params);
            $res = $this->mysqli->query($boundSql);
            if (!$res) {
                $this->lastError = $this->mysqli->error;
                return false;
            }
            $rows = array();
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();
            }
            return $rows;
        }
        return false;
    }

    /**
     * Lädt eine einzelne Zeile.
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function LoadRow($sql, $params = array())
    {
        $rows = $this->LoadRows($sql, $params);
        if (is_array($rows) && count($rows) > 0) {
            return $rows[0];
        }
        return false;
    }

    /**
     * Gibt den ersten Skalarwert der ersten Zeile zurück (z. B. für COUNT(*)).
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function ExecuteScalar($sql, $params = array())
    {
        $row = $this->LoadRow($sql, $params);
        if (is_array($row) && count($row) > 0) {
            $vals = array_values($row);
            return $vals[0];
        }
        return false;
    }

    /**
     * PHPMacherNG kompatible Alias-Methoden auf dem DbHelper-Objekt:
     * ExecuteRow(), ExecuteRows(), ExecuteHtml(), ExecuteJson()
     */
    public function ExecuteRow($sql, $params = array())
    {
        return $this->LoadRow($sql, $params);
    }

    public function ExecuteRows($sql, $params = array())
    {
        return $this->LoadRows($sql, $params);
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt das Ergebnis direkt als fertig formatierte
     * Bootstrap 3 / PHPMacherNG HTML-Tabelle zurück (wie DbHelper()->ExecuteHtml() in PHPMacherNG).
     *
     * Unterstützte $options:
     * - 'fieldcaption' => true | array('spalte' => 'Anzeigename', ...)
     * - 'tableclass'   => CSS-Klassen der Tabelle (Standard: 'table table-bordered table-striped ewDbTable')
     * - 'horizontal'   => false (Standard: Zeilenweise; bei true: Schlüssel-Wert-Darstellung für 1 Zeile)
     *
     * @param string $sql
     * @param array $options
     * @param array $params
     * @return string HTML-Markup
     */
    public function ExecuteHtml($sql, $options = array(), $params = array())
    {
        $rows = $this->LoadRows($sql, $params);
        if ($rows === false) {
            return '<div class="alert alert-danger">SQL-Fehler: ' . htmlspecialchars($this->lastError, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (empty($rows)) {
            return '<div class="text-muted" style="padding:8px 0;"><em>Keine Datensätze gefunden.</em></div>';
        }

        $tableClass = isset($options['tableclass']) && $options['tableclass'] !== ''
            ? $options['tableclass']
            : 'table table-bordered table-striped table-hover ewDbTable';

        $captions = isset($options['fieldcaption']) ? $options['fieldcaption'] : true;
        $horizontal = !empty($options['horizontal']);

        $firstRow = $rows[0];
        $cols = array_keys($firstRow);

        // Horizontale Darstellung (ideal für Einzel-Datensatz mit LoadRow/LIMIT 1)
        if ($horizontal) {
            $html = '<table class="' . htmlspecialchars($tableClass, ENT_QUOTES, 'UTF-8') . '"><tbody>';
            foreach ($cols as $col) {
                $label = $col;
                if (is_array($captions) && isset($captions[$col])) {
                    $label = $captions[$col];
                }
                $html .= '<tr><th style="width:30%; background:#f9fafc;">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</th>';
                foreach ($rows as $r) {
                    $val = isset($r[$col]) ? (string)$r[$col] : '';
                    $html .= '<td>' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            return $html;
        }

        // Standard-Tabellendarstellung
        $html = '<table class="' . htmlspecialchars($tableClass, ENT_QUOTES, 'UTF-8') . '">';
        if ($captions !== false) {
            $html .= '<thead><tr>';
            foreach ($cols as $col) {
                $label = $col;
                if (is_array($captions) && isset($captions[$col])) {
                    $label = $captions[$col];
                }
                $html .= '<th>' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr></thead>';
        }
        $html .= '<tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            foreach ($cols as $col) {
                $val = isset($r[$col]) && $r[$col] !== null ? (string)$r[$col] : '';
                $html .= '<td>' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt das Ergebnis als JSON-String zurück (wie ExecuteJson() in PHPMacherNG).
     */
    public function ExecuteJson($sql, $options = array(), $params = array())
    {
        $firstOnly = !empty($options['firstonly']);
        if ($firstOnly) {
            $row = $this->LoadRow($sql, $params);
            return json_encode($row ? $row : new stdClass());
        }
        $rows = $this->LoadRows($sql, $params);
        return json_encode(is_array($rows) ? $rows : array());
    }

    /**
     * Führt eine SQL-Abfrage aus und gibt ein interaktives HTML5-Diagramm (Balkendiagramm 'bar' oder Tortendiagramm 'pie') zurück.
     *
     * Unterstützte $options:
     * - 'type'        => 'bar' (Balkendiagramm, Standard) | 'pie' (Tortendiagramm)
     * - 'title'       => Überschrift des Diagramms
     * - 'label_field' => Spaltenname für die Beschriftung/Kategorie (Standard: 1. Spalte des SQL-Ergebnisses)
     * - 'value_field' => Spaltenname für den Zahlenwert (Standard: 2. Spalte des SQL-Ergebnisses, sonst 1. Spalte)
     * - 'height'      => Höhe des Canvas in Pixeln (Standard: 280)
     * - 'show_legend' => Legende/Wertetabelle neben/unter dem Diagramm anzeigen (Standard: true)
     *
     * @param string $sql
     * @param array $options
     * @param array $params
     * @return string HTML-Markup
     */
    public function ExecuteChart($sql, $options = array(), $params = array())
    {
        $rows = $this->LoadRows($sql, $params);
        if ($rows === false) {
            return '<div class="alert alert-danger">SQL-Fehler beim Laden des Diagramms: ' . htmlspecialchars($this->lastError, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (empty($rows)) {
            return '<div class="text-muted" style="padding:12px;"><em>Keine Daten für die Diagrammdarstellung vorhanden.</em></div>';
        }

        $firstRow = $rows[0];
        $cols = array_keys($firstRow);
        $labelField = isset($options['label_field']) && $options['label_field'] !== '' && isset($firstRow[$options['label_field']])
            ? $options['label_field']
            : $cols[0];
        $valueField = isset($options['value_field']) && $options['value_field'] !== '' && isset($firstRow[$options['value_field']])
            ? $options['value_field']
            : (isset($cols[1]) ? $cols[1] : $cols[0]);

        $labels = array();
        $values = array();
        foreach ($rows as $r) {
            $lbl = isset($r[$labelField]) && $r[$labelField] !== null && trim((string)$r[$labelField]) !== ''
                ? (string)$r[$labelField]
                : '(Leer)';
            $val = isset($r[$valueField]) ? (float)$r[$valueField] : 0.0;
            $labels[] = $lbl;
            $values[] = round($val, 2);
        }

        $validChartTypes = array('bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge');
        $rawType = isset($options['type']) ? strtolower(trim((string)$options['type'])) : 'bar';
        $chartType = in_array($rawType, $validChartTypes, true) ? $rawType : 'bar';
        $title = isset($options['title']) ? (string)$options['title'] : '';
        $height = isset($options['height']) && (int)$options['height'] >= 160 ? (int)$options['height'] : 290;
        $domId = 'ew_chart_' . substr(md5($sql . microtime(true) . mt_rand()), 0, 10);

        $payload = array(
            'type'   => $chartType,
            'title'  => $title,
            'labels' => $labels,
            'values' => $values
        );
        $jsonAttr = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');

        $html = '<div class="ewChartWidgetBox" style="background:#fff; border:1px solid #e2e8f0; border-radius:4px; padding:12px 14px; margin-bottom:15px;">';
        if ($title !== '') {
            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:10px; border-bottom:1px solid #f1f5f9; padding-bottom:6px;">';
            $html .= '<strong style="font-size:13.5px; color:#1e293b;"><i class="fa fa-bar-chart text-primary"></i> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>';
            $html .= '<div class="btn-group btn-group-xs">';
            $btnTypes = array(
                'bar'      => array('icon' => 'fa-bar-chart',      'lbl' => 'Säulen'),
                'hbar'     => array('icon' => 'fa-align-left',     'lbl' => 'Balken'),
                'line'     => array('icon' => 'fa-line-chart',     'lbl' => 'Linie'),
                'area'     => array('icon' => 'fa-area-chart',     'lbl' => 'Fläche'),
                'pie'      => array('icon' => 'fa-pie-chart',      'lbl' => 'Torte'),
                'doughnut' => array('icon' => 'fa-circle-o-notch', 'lbl' => 'Donut'),
                'funnel'   => array('icon' => 'fa-filter',         'lbl' => 'Trichter'),
                'radar'    => array('icon' => 'fa-compass',        'lbl' => 'Radar'),
                'gauge'    => array('icon' => 'fa-tachometer',     'lbl' => 'Tacho')
            );
            foreach ($btnTypes as $bKey => $bMeta) {
                $html .= '<button type="button" class="btn btn-default' . ($chartType === $bKey ? ' active' : '') . '" onclick="ew_SwitchChartType(\'' . $domId . '\', \'' . $bKey . '\', this);" title="' . $bMeta['lbl'] . '"><i class="fa ' . $bMeta['icon'] . '"></i> ' . $bMeta['lbl'] . '</button>';
            }
            $html .= '<button type="button" class="btn btn-default" onclick="ew_DownloadChartPng(\'' . $domId . '\');" title="Diagramm als PNG-Bild herunterladen"><i class="fa fa-download"></i> PNG</button>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '<div style="position:relative; width:100%;">';
        $html .= '<canvas id="' . $domId . '" class="ewChartCanvas" data-ew-chart="' . $jsonAttr . '" height="' . $height . '" style="width:100%; height:' . $height . 'px; display:block;"></canvas>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Gibt die zuletzt eingefügte Auto-Increment ID zurück.
     * @return string|int
     */
    public function Insert_ID()
    {
        if ($this->driver === 'pdo' && $this->pdo) {
            return $this->pdo->lastInsertId();
        } elseif ($this->driver === 'mysqli' && $this->mysqli) {
            return $this->mysqli->insert_id;
        }
        return 0;
    }

    /**
     * Sicheres Escaping für MySQLi-Fallback.
     */
    protected function interpolateQuery($sql, $params)
    {
        if (empty($params)) {
            return $sql;
        }
        $parts = explode('?', $sql);
        $out = $parts[0];
        $i = 1;
        foreach ($params as $val) {
            if ($val === null) {
                $rep = 'NULL';
            } elseif (is_bool($val)) {
                $rep = $val ? '1' : '0';
            } else {
                $rep = "'" . $this->mysqli->real_escape_string((string)$val) . "'";
            }
            $out .= $rep . (isset($parts[$i]) ? $parts[$i] : '');
            $i++;
        }
        return $out;
    }
}

/**
 * Globale Hilfsfunktionen zum Abrufen der Datenbankverbindung & Ausführen von Abfragen
 * (100% kompatibel mit PHPMacherNG: Conn(), DbHelper(), ew_Execute*, ew_RenderChart, ew_AdjustSql)
 * @return cEwDbConnection
 */
function &ew_Connect()
{
    global $EW_CONN;
    if (!isset($EW_CONN) || !($EW_CONN instanceof cEwDbConnection)) {
        $EW_CONN = new cEwDbConnection();
    }
    return $EW_CONN;
}

function &Conn()
{
    return ew_Connect();
}

function &DbHelper()
{
    return ew_Connect();
}

function ew_Execute($sql, $params = array())
{
    return ew_Connect()->Execute($sql, $params);
}

function ew_ExecuteScalar($sql, $params = array())
{
    return ew_Connect()->ExecuteScalar($sql, $params);
}

function ew_ExecuteRow($sql, $params = array())
{
    return ew_Connect()->LoadRow($sql, $params);
}

function ew_ExecuteRows($sql, $params = array())
{
    return ew_Connect()->LoadRows($sql, $params);
}

function ew_RenderChart($sql, $options = array(), $params = array())
{
    return ew_Connect()->ExecuteChart($sql, $options, $params);
}

function ew_ExecuteHtml($sql, $options = array(), $params = array())
{
    return ew_Connect()->ExecuteHtml($sql, $options, $params);
}

function ew_ExecuteJson($sql, $options = array(), $params = array())
{
    return ew_Connect()->ExecuteJson($sql, $options, $params);
}

function ew_AdjustSql($val)
{
    return addslashes((string)$val);
}
PHP;
    }

    /**
     * Erzeugt phpfn14.php (Zentrale Hilfsfunktionen für Formatierung, Formulare, CSRF, Paging, Lookup)
     */
    protected function buildPhpFn()
    {
        return <<<'PHP'
<?php
/**
 * PHPMacherNG (v14) Shared Functions (phpfn14.php)
 * Kompatibel mit PHP 5.4 bis PHP 8.4+
 */

require_once dirname(__FILE__) . '/ewdbhelper14.php';

if (session_id() === '') {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}
ew_SendSecurityHeaders();

/**
 * Sendet empfohlene HTTP-Security-Header (gemäß IT-Sicherheitskonzept / BSI)
 */
function ew_SendSecurityHeaders()
{
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
    }
}

/**
 * HTML-Escaping (sicher für PHP 5.4 bis 8.4+, auch bei null-Werten)
 */
function ew_HtmlEncode($val)
{
    if ($val === null || $val === false) {
        return '';
    }
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

/**
 * Liest einen GET-Parameter sicher aus.
 */
function ew_Get($key, $default = '')
{
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Liest einen POST-Parameter sicher aus.
 */
function ew_Post($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * CSRF-Token erzeugen und prüfen
 */
function ew_GetCsrfToken()
{
    if (empty($_SESSION[EW_PROJECT_ID . '_csrf_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION[EW_PROJECT_ID . '_csrf_token'] = bin2hex(random_bytes(16));
        } else {
            $_SESSION[EW_PROJECT_ID . '_csrf_token'] = md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION[EW_PROJECT_ID . '_csrf_token'];
}

function ew_CheckCsrfToken($token)
{
    $expected = ew_GetCsrfToken();
    return ((string)$token !== '' && (string)$token === (string)$expected);
}

/**
 * Flash-Messages (Erfolg / Fehler / Warnung wie in PHPMacherNG SetMessage / SetFailureMessage)
 */
function ew_SetSuccessMessage($msg)
{
    if (!isset($_SESSION[EW_PROJECT_ID . '_msg_success'])) {
        $_SESSION[EW_PROJECT_ID . '_msg_success'] = array();
    }
    $_SESSION[EW_PROJECT_ID . '_msg_success'][] = $msg;
}

function ew_SetFailureMessage($msg)
{
    if (!isset($_SESSION[EW_PROJECT_ID . '_msg_error'])) {
        $_SESSION[EW_PROJECT_ID . '_msg_error'] = array();
    }
    $_SESSION[EW_PROJECT_ID . '_msg_error'][] = $msg;
}

function ew_ShowMessages()
{
    $html = '';
    if (!empty($_SESSION[EW_PROJECT_ID . '_msg_success'])) {
        foreach ($_SESSION[EW_PROJECT_ID . '_msg_success'] as $m) {
            $html .= '<div class="alert alert-success alert-dismissible ewAlert"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-check"></i> ' . $m . '</div>';
        }
        $_SESSION[EW_PROJECT_ID . '_msg_success'] = array();
    }
    if (!empty($_SESSION[EW_PROJECT_ID . '_msg_error'])) {
        foreach ($_SESSION[EW_PROJECT_ID . '_msg_error'] as $m) {
            $html .= '<div class="alert alert-danger alert-dismissible ewAlert"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-ban"></i> ' . $m . '</div>';
        }
        $_SESSION[EW_PROJECT_ID . '_msg_error'] = array();
    }
    return $html;
}

/**
 * Weiterleitung per HTTP Header
 */
function ew_Redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * Formatiert einen Datenbankwert gemäß dem gewählten PHPMacherNG View-Tag.
 */
function ew_FormatViewValue($val, $fieldMeta)
{
    if ($val === null || $val === '') {
        return '<span class="text-muted">&ndash;</span>';
    }

    // Passwort-Felder in Listen- und Detailansichten immer maskieren
    if ((isset($fieldMeta['edit_tag']) && $fieldMeta['edit_tag'] === 'PASSWORD') ||
        (isset($fieldMeta['name']) && in_array(strtolower($fieldMeta['name']), array('passwort', 'password', 'passwd', 'pwd')))) {
        return '********';
    }

    // Falls Lookup-Tabelle oder statische Optionen definiert sind, Anzeigewert auflösen
    if (!empty($fieldMeta['lookup_table']) && !empty($fieldMeta['lookup_link_field']) && !empty($fieldMeta['lookup_display_field'])) {
        $resolved = ew_ResolveLookupValue($fieldMeta['lookup_table'], $fieldMeta['lookup_link_field'], $fieldMeta['lookup_display_field'], $val);
        if ($resolved !== false) {
            return ew_HtmlEncode($resolved);
        }
    } elseif (!empty($fieldMeta['options']) && $fieldMeta['view_tag'] !== 'BOOLEAN') {
        $opts = ew_ParseOptionsString($fieldMeta['options']);
        if (isset($opts[(string)$val])) {
            return ew_HtmlEncode($opts[(string)$val]);
        }
    }

    $viewTag = isset($fieldMeta['view_tag']) ? $fieldMeta['view_tag'] : 'FORMATTED TEXT';

    switch ($viewTag) {
        case 'CURRENCY':
            if (is_numeric($val)) {
                return ew_HtmlEncode(EW_CURRENCY_SYMBOL . ' ' . number_format((float)$val, 2, ',', '.'));
            }
            return ew_HtmlEncode($val);

        case 'NUMBER':
            if (is_numeric($val)) {
                $decimals = (isset($fieldMeta['scale']) && (int)$fieldMeta['scale'] > 0) ? (int)$fieldMeta['scale'] : 0;
                return ew_HtmlEncode(number_format((float)$val, $decimals, ',', '.'));
            }
            return ew_HtmlEncode($val);

        case 'DATE':
            $ts = @strtotime((string)$val);
            if ($ts !== false && $ts > 0) {
                return ew_HtmlEncode(date(EW_DATE_FORMAT, $ts));
            }
            return ew_HtmlEncode($val);

        case 'DATETIME':
            $ts = @strtotime((string)$val);
            if ($ts !== false && $ts > 0) {
                return ew_HtmlEncode(date(EW_DATE_FORMAT . ' H:i', $ts));
            }
            return ew_HtmlEncode($val);

        case 'BOOLEAN':
            $truthy = ($val == 1 || strtolower((string)$val) === 'true' || strtolower((string)$val) === 'y' || strtolower((string)$val) === 'ja');
            return $truthy
                ? '<span class="label label-success"><i class="fa fa-check"></i> Ja</span>'
                : '<span class="label label-default">Nein</span>';

        case 'EMAIL':
            $safe = ew_HtmlEncode($val);
            return '<a href="mailto:' . $safe . '"><i class="fa fa-envelope-o"></i> ' . $safe . '</a>';

        case 'LINK':
            $safe = ew_HtmlEncode($val);
            return '<a href="' . $safe . '" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> ' . $safe . '</a>';

        case 'IMAGE':
            $safe = ew_HtmlEncode($val);
            return '<img src="' . $safe . '" alt="" class="img-thumbnail" style="max-height:48px;max-width:80px;">';

        case 'SIGNATURE':
            $strSig = trim((string)$val);
            if ($strSig === '') {
                return '<span class="text-muted">&ndash;</span>';
            }
            if (strpos($strSig, 'data:image/') === 0 || preg_match('/\.(png|jpe?g|gif|svg|webp)(\?.*)?$/i', $strSig)) {
                $safeSig = ew_HtmlEncode($strSig);
                return '<div style="display:inline-block; background:#fff; border:1px solid #d2d6de; border-radius:3px; padding:3px 6px;" title="Digitale Unterschrift">'
                    . '<img src="' . $safeSig . '" alt="Unterschrift" style="max-height:54px; max-width:180px; display:block;">'
                    . '</div>';
            }
            return ew_HtmlEncode($strSig);

        case 'HTML':
            return (string)$val;

        default:
            $str = (string)$val;
            // Falls ein Feld eine Base64-Bild-Signatur enthält, auch ohne expliziten SIGNATURE View-Tag als Bild darstellen
            if (strpos($str, 'data:image/png;base64,') === 0) {
                $safeSig = ew_HtmlEncode($str);
                return '<div style="display:inline-block; background:#fff; border:1px solid #d2d6de; border-radius:3px; padding:3px 6px;">'
                    . '<img src="' . $safeSig . '" alt="Unterschrift" style="max-height:54px; max-width:180px; display:block;">'
                    . '</div>';
            }
            if (strlen($str) > 140 && isset($fieldMeta['ew_type']) && $fieldMeta['ew_type'] === 'MEMO') {
                return nl2br(ew_HtmlEncode(substr($str, 0, 140) . '...'));
            }
            return nl2br(ew_HtmlEncode($str));
    }
}

/**
 * Parst statische Optionen im Format "1:Aktiv,0:Inaktiv" in ein assoziatives Array.
 */
function ew_ParseOptionsString($optStr)
{
    $result = array();
    $parts = explode(',', (string)$optStr);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        if (strpos($p, ':') !== false) {
            list($k, $v) = explode(':', $p, 2);
            $result[trim($k)] = trim($v);
        } else {
            $result[$p] = $p;
        }
    }
    return $result;
}

/**
 * Lädt alle Optionen einer Lookup-Tabelle (mit In-Memory-Cache für schnelle Listenansichten).
 */
function ew_GetLookupOptions($table, $linkField, $displayField, $orderBy = '')
{
    static $cache = array();
    $key = $table . '|' . $linkField . '|' . $displayField . '|' . $orderBy;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $conn = ew_Connect();
    $safeTable = '`' . str_replace('`', '``', $table) . '`';
    $safeLink = '`' . str_replace('`', '``', $linkField) . '`';
    $safeDisp = '`' . str_replace('`', '``', $displayField) . '`';

    $sql = "SELECT " . $safeLink . " AS lf, " . $safeDisp . " AS df FROM " . $safeTable;
    if ($orderBy !== '') {
        $sql .= " ORDER BY " . $orderBy;
    } else {
        $sql .= " ORDER BY " . $safeDisp . " ASC";
    }
    $sql .= " LIMIT 500";

    $rows = $conn->LoadRows($sql);
    $opts = array();
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $opts[(string)$r['lf']] = $r['df'];
        }
    }
    $cache[$key] = $opts;
    return $opts;
}

function ew_ResolveLookupValue($table, $linkField, $displayField, $val)
{
    $opts = ew_GetLookupOptions($table, $linkField, $displayField);
    $k = (string)$val;
    return isset($opts[$k]) ? $opts[$k] : false;
}

/**
 * Rendert das passende HTML-Eingabeelement (Edit Tag) für Add, Edit, Extended Search und Multi-Update.
 */
function ew_RenderEditControl($fieldMeta, $currentValue, $mode = 'add', $prefix = 'x_')
{
    $name = $prefix . $fieldMeta['name'];
    $id = $prefix . $fieldMeta['name'];
    $editTag = isset($fieldMeta['edit_tag']) ? $fieldMeta['edit_tag'] : 'TEXT';
    $isReadOnly = ($editTag === 'READONLY') || ($mode === 'edit' && !empty($fieldMeta['read_only_edit']));
    $reqAttr = (!empty($fieldMeta['required']) && $mode !== 'search') ? ' required' : '';

    if ($isReadOnly && $mode !== 'search') {
        if ($editTag === 'SIGNATURE' && strpos((string)$currentValue, 'data:image/') === 0) {
            return '<input type="hidden" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '">'
                . '<div style="display:inline-block; background:#fff; border:1px solid #ccc; padding:4px; border-radius:3px;"><img src="' . ew_HtmlEncode($currentValue) . '" alt="Unterschrift" style="max-height:80px;"></div>';
        }
        return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '" readonly>';
    }

    if ($editTag === 'HIDDEN' && $mode !== 'search') {
        return '<input type="hidden" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '"><span class="form-control-static text-muted">' . ew_HtmlEncode($currentValue) . '</span>';
    }

    // Optionen bestimmen (entweder aus Lookup-Tabelle oder aus Options-String)
    $options = array();
    if (!empty($fieldMeta['lookup_table']) && !empty($fieldMeta['lookup_link_field']) && !empty($fieldMeta['lookup_display_field'])) {
        $options = ew_GetLookupOptions(
            $fieldMeta['lookup_table'],
            $fieldMeta['lookup_link_field'],
            $fieldMeta['lookup_display_field'],
            isset($fieldMeta['lookup_order_by']) ? $fieldMeta['lookup_order_by'] : ''
        );
    } elseif (!empty($fieldMeta['options'])) {
        $options = ew_ParseOptionsString($fieldMeta['options']);
    }

    switch ($editTag) {
        case 'SIGNATURE':
            if ($mode === 'search') {
                return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '" placeholder="Unterschrift vorhanden...">';
            }
            $safeId = ew_HtmlEncode($id);
            $safeName = ew_HtmlEncode($name);
            $safeVal = ew_HtmlEncode((string)$currentValue);
            $sigHtml = '<div class="ewSignaturePadWidget" data-sig-target="' . $safeId . '" style="max-width:460px;">';
            $sigHtml .= '<input type="hidden" name="' . $safeName . '" id="' . $safeId . '" value="' . $safeVal . '"' . $reqAttr . '>';
            $sigHtml .= '<div style="position:relative; border:2px dashed #3c8dbc; border-radius:4px; background:#ffffff; touch-action:none; -ms-touch-action:none; user-select:none;">';
            $sigHtml .= '<canvas id="canvas_' . $safeId . '" width="440" height="150" style="width:100%; height:150px; display:block; cursor:crosshair; touch-action:none;"></canvas>';
            $sigHtml .= '<div style="position:absolute; bottom:30px; left:20px; right:20px; border-bottom:1px dotted #ccc; pointer-events:none;"></div>';
            $sigHtml .= '<span style="position:absolute; bottom:10px; left:20px; font-size:11px; color:#999; pointer-events:none;"><i class="fa fa-pencil"></i> Bitte hier mit Finger, Stift (Stylus) oder Maus unterschreiben</span>';
            $sigHtml .= '</div>';
            $sigHtml .= '<div style="margin-top:6px; display:flex; justify-content:space-between; align-items:center;">';
            $sigHtml .= '<button type="button" class="btn btn-default btn-xs" onclick="ew_ClearSignaturePad(\'' . $safeId . '\');"><i class="fa fa-eraser"></i> Unterschrift löschen / Neu zeichnen</button>';
            $sigHtml .= '<span id="sig_status_' . $safeId . '" class="text-muted" style="font-size:11px;">' . ($safeVal !== '' ? '<i class="fa fa-check text-success"></i> Unterschrift erfasst' : 'Noch keine Unterschrift') . '</span>';
            $sigHtml .= '</div>';
            $sigHtml .= '</div>';
            return $sigHtml;

        case 'TEXTAREA':
            if ($mode === 'search') {
                return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '" placeholder="' . ew_HtmlEncode($fieldMeta['caption']) . '">';
            }
            $rows = isset($fieldMeta['textarea_rows']) && (int)$fieldMeta['textarea_rows'] > 0 ? (int)$fieldMeta['textarea_rows'] : 4;
            return '<textarea class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" rows="' . $rows . '"' . $reqAttr . '>' . ew_HtmlEncode($currentValue) . '</textarea>';

        case 'PASSWORD':
            return '<input type="password" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '"' . $reqAttr . '>';

        case 'SELECT':
            $html = '<select class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '"' . $reqAttr . '>';
            $html .= '<option value="">-- Bitte wählen --</option>';
            foreach ($options as $k => $v) {
                $sel = ((string)$currentValue !== '' && (string)$currentValue === (string)$k) ? ' selected' : '';
                $html .= '<option value="' . ew_HtmlEncode($k) . '"' . $sel . '>' . ew_HtmlEncode($v) . '</option>';
            }
            $html .= '</select>';
            return $html;

        case 'RADIO':
            if (empty($options)) {
                $options = array('1' => 'Ja', '0' => 'Nein');
            }
            $html = '<div class="ewRadioList">';
            if ($mode === 'search') {
                $selAll = ((string)$currentValue === '') ? ' checked' : '';
                $html .= '<label class="radio-inline"><input type="radio" name="' . ew_HtmlEncode($name) . '" value=""' . $selAll . '> Alle</label>';
            }
            foreach ($options as $k => $v) {
                $chk = ((string)$currentValue !== '' && (string)$currentValue === (string)$k) ? ' checked' : '';
                $html .= '<label class="radio-inline"><input type="radio" name="' . ew_HtmlEncode($name) . '" value="' . ew_HtmlEncode($k) . '"' . $chk . '> ' . ew_HtmlEncode($v) . '</label>';
            }
            $html .= '</div>';
            return $html;

        case 'CHECKBOX':
            if ($mode === 'search') {
                $html = '<select class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '">';
                $html .= '<option value="">-- Alle --</option>';
                $html .= '<option value="1"' . ((string)$currentValue === '1' ? ' selected' : '') . '>Ja (1)</option>';
                $html .= '<option value="0"' . ((string)$currentValue === '0' ? ' selected' : '') . '>Nein (0)</option>';
                $html .= '</select>';
                return $html;
            }
            $checked = ($currentValue == 1 || strtolower((string)$currentValue) === 'true') ? ' checked' : '';
            return '<div class="checkbox"><label><input type="hidden" name="' . ew_HtmlEncode($name) . '" value="0"><input type="checkbox" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="1"' . $checked . '> Aktiv / Ja</label></div>';

        case 'DATE':
            $inputType = ($fieldMeta['base_type'] === 'date') ? 'date' : 'text';
            $valFormatted = (string)$currentValue;
            if ($inputType === 'date' && $valFormatted !== '') {
                $ts = @strtotime($valFormatted);
                if ($ts !== false) {
                    $valFormatted = date('Y-m-d', $ts);
                }
            }
            return '<div class="input-group"><span class="input-group-addon"><i class="fa fa-calendar"></i></span><input type="' . $inputType . '" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($valFormatted) . '" placeholder="YYYY-MM-DD"' . $reqAttr . '></div>';

        default: // TEXT
            $maxAttr = (!empty($fieldMeta['max_length']) && (int)$fieldMeta['max_length'] > 0 && $fieldMeta['ew_type'] === 'STRING')
                ? ' maxlength="' . (int)$fieldMeta['max_length'] . '"'
                : '';
            return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '"' . $maxAttr . $reqAttr . '>';
    }
}

/**
 * Validiert einen Formularwert anhand der PHPMacherNG-Feldregeln.
 * @return string Fehlermeldung oder leerer String bei Erfolg
 */
function ew_ValidateField($fieldMeta, $val)
{
    $caption = $fieldMeta['caption'];
    $strVal = trim((string)$val);

    if (!empty($fieldMeta['required']) && $fieldMeta['edit_tag'] !== 'CHECKBOX') {
        if ($strVal === '') {
            return 'Bitte geben Sie einen Wert für das Pflichtfeld <strong>' . ew_HtmlEncode($caption) . '</strong> ein.';
        }
    }

    if ($strVal === '') {
        return '';
    }

    $rule = isset($fieldMeta['validation']) ? $fieldMeta['validation'] : '';
    switch ($rule) {
        case 'Integer':
            if (!preg_match('/^-?\d+$/', $strVal)) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss eine gültige Ganzzahl sein.';
            }
            break;
        case 'Float':
            $norm = str_replace(',', '.', $strVal);
            if (!is_numeric($norm)) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss eine gültige Dezimalzahl sein.';
            }
            break;
        case 'Email':
            if (!filter_var($strVal, FILTER_VALIDATE_EMAIL)) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss eine gültige E-Mail-Adresse enthalten.';
            }
            break;
        case 'Date':
            if (@strtotime($strVal) === false) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss ein gültiges Datum enthalten.';
            }
            break;
    }
    return '';
}

/**
 * Hasht ein Passwort sicher mit bcrypt (PHP 5.5+) oder SHA-256 Fallback (PHP 5.4).
 * Erkennt bereits gehashte Werte (bcrypt $2y$/$2a$, SHA-256 64-Hex, MD5 32-Hex),
 * damit unveränderte Passwörter beim Bearbeiten (*edit.php) nicht doppelt gehasht werden.
 */
function ew_HashPassword($plain)
{
    $plain = (string)$plain;
    if ($plain === '') {
        return '';
    }
    // Bereits ein bcrypt / argon2 Hash?
    if (strpos($plain, '$2y$') === 0 || strpos($plain, '$2a$') === 0 || strpos($plain, '$2b$') === 0 || strpos($plain, '$argon2') === 0) {
        return $plain;
    }
    // Bereits ein SHA-256 (64 Hex) oder MD5 (32 Hex) Hash?
    if (preg_match('/^[a-f0-9]{64}$/i', $plain) || preg_match('/^[a-f0-9]{32}$/i', $plain)) {
        return $plain;
    }
    if (function_exists('password_hash')) {
        return password_hash($plain, PASSWORD_DEFAULT);
    }
    return hash('sha256', $plain);
}

/**
 * Bereitet einen Formularwert für das Speichern in der Datenbank auf.
 */
function ew_PrepareDbValue($fieldMeta, $val)
{
    $strVal = trim((string)$val);
    if ($strVal === '' && !empty($fieldMeta['is_nullable'])) {
        return null;
    }
    // Passwort-Felder automatisch vor dem Speichern mit bcrypt / SHA-256 hashen
    if ((isset($fieldMeta['edit_tag']) && $fieldMeta['edit_tag'] === 'PASSWORD') ||
        (isset($fieldMeta['name']) && in_array(strtolower($fieldMeta['name']), array('passwort', 'password', 'passwd', 'pwd')))) {
        return ew_HashPassword($strVal);
    }
    if ($fieldMeta['ew_type'] === 'FLOAT') {
        return str_replace(',', '.', $strVal);
    }
    if ($fieldMeta['ew_type'] === 'INTEGER' || $fieldMeta['ew_type'] === 'BOOLEAN') {
        return ($strVal === '') ? 0 : (int)$strVal;
    }
    return $strVal;
}

/**
 * PHPMacherNG (v14) Advanced Security Klasse (cAdvancedSecurity)
 * Unterstützt Hardcoded Admin, User Table, Static & Dynamic User Levels und User ID Security.
 * Kompatibel mit PHP 5.4 bis PHP 8.4+.
 */
class cAdvancedSecurity
{
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_SECONDS = 30;

    public $Config = array();
    public $CurrentUserName = '';
    public $CurrentUserID = '';
    public $CurrentParentUserID = '';
    public $CurrentUserLevelID = -2; // -2 = Anonymous, -1 = Admin, 0 = Default
    public $CurrentUserLevelName = 'Anonymous';
    public $UserLevelPriv = array(); // $UserLevelPriv[$tableName] = int Bitmaske
    public $SubordinateUserIDs = array();

    public function __construct()
    {
        global $EW_SECURITY_CONFIG;
        $this->Config = isset($EW_SECURITY_CONFIG) && is_array($EW_SECURITY_CONFIG) ? $EW_SECURITY_CONFIG : array();
        $this->LoadUserFromSession();
        $this->LoadPermissions();
    }

    public function IsEnabled()
    {
        return !empty($this->Config['enabled']);
    }

    public function LoadUserFromSession()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        if (isset($_SESSION[$prefix . 'logged_in']) && $_SESSION[$prefix . 'logged_in'] === true) {
            $this->CurrentUserName = isset($_SESSION[$prefix . 'username']) ? (string)$_SESSION[$prefix . 'username'] : '';
            $this->CurrentUserID = isset($_SESSION[$prefix . 'userid']) ? $_SESSION[$prefix . 'userid'] : '';
            $this->CurrentParentUserID = isset($_SESSION[$prefix . 'parentid']) ? $_SESSION[$prefix . 'parentid'] : '';
            $this->CurrentUserLevelID = isset($_SESSION[$prefix . 'userlevel']) ? (int)$_SESSION[$prefix . 'userlevel'] : 0;
        } else {
            $this->CurrentUserName = '';
            $this->CurrentUserID = '';
            $this->CurrentParentUserID = '';
            $this->CurrentUserLevelID = -2;
        }
    }

    public function IsLoggedIn()
    {
        if (!$this->IsEnabled()) {
            return true;
        }
        return ($this->CurrentUserName !== '' && $this->CurrentUserLevelID > -2);
    }

    public function IsSysAdmin()
    {
        if (!$this->IsEnabled()) {
            return true;
        }
        return ($this->CurrentUserLevelID === -1);
    }

    /**
     * Prüft, ob aktuell eine Brute-Force-Sperre aktiv ist und gibt die verbleibenden Sekunden zurück.
     */
    public function GetLockoutRemaining()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        if (!empty($_SESSION[$prefix . 'lockout_until'])) {
            $rem = (int)$_SESSION[$prefix . 'lockout_until'] - time();
            if ($rem > 0) {
                return $rem;
            }
            unset($_SESSION[$prefix . 'lockout_until']);
            $_SESSION[$prefix . 'failed_attempts'] = 0;
        }
        return 0;
    }

    protected function RecordFailedAttempt()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        $fails = isset($_SESSION[$prefix . 'failed_attempts']) ? (int)$_SESSION[$prefix . 'failed_attempts'] + 1 : 1;
        $_SESSION[$prefix . 'failed_attempts'] = $fails;
        if ($fails >= self::MAX_FAILED_ATTEMPTS) {
            $_SESSION[$prefix . 'lockout_until'] = time() + self::LOCKOUT_SECONDS;
        }
    }

    protected function ResetFailedAttempts()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        $_SESSION[$prefix . 'failed_attempts'] = 0;
        unset($_SESSION[$prefix . 'lockout_until']);
    }

    public function ValidateUser($username, $password)
    {
        if ($this->GetLockoutRemaining() > 0) {
            return false;
        }

        $username = trim((string)$username);
        $password = (string)$password;
        if ($username === '' || $password === '') {
            return false;
        }

        // 1. Hardcoded Admin prüfen
        if (!empty($this->Config['hardcoded_admin_enabled'])) {
            $admUser = isset($this->Config['admin_username']) ? (string)$this->Config['admin_username'] : 'admin';
            $admPass = isset($this->Config['admin_password']) ? (string)$this->Config['admin_password'] : 'admin';
            if ($admUser !== '' && $username === $admUser && $this->VerifyPassword($password, $admPass)) {
                $this->ResetFailedAttempts();
                $this->SaveUserToSession($admUser, -1, '', -1);
                return true;
            }
        }

        // 2. Datenbank Benutzer-Tabelle prüfen
        if (!empty($this->Config['use_user_table']) && !empty($this->Config['user_table'])) {
            $conn = ew_Connect();
            $uTable = '`' . str_replace('`', '``', $this->Config['user_table']) . '`';
            $uField = '`' . str_replace('`', '``', $this->Config['username_field']) . '`';
            $sql = 'SELECT * FROM ' . $uTable . ' WHERE ' . $uField . ' = ?';
            $row = $conn->LoadRow($sql, array($username));
            if ($row) {
                // Falls Spalte "aktiv" existiert und 0 ist -> Login verweigern
                if (isset($row['aktiv']) && (string)$row['aktiv'] === '0') {
                    $this->RecordFailedAttempt();
                    return false;
                }
                $pwCol = $this->Config['password_field'];
                $storedPw = isset($row[$pwCol]) ? (string)$row[$pwCol] : '';
                if ($this->VerifyPassword($password, $storedPw)) {
                    $uidCol = isset($this->Config['user_id_field']) ? $this->Config['user_id_field'] : '';
                    $pidCol = isset($this->Config['parent_user_id_field']) ? $this->Config['parent_user_id_field'] : '';
                    $lvlCol = isset($this->Config['user_level_field']) ? $this->Config['user_level_field'] : '';

                    $uid = ($uidCol !== '' && isset($row[$uidCol])) ? $row[$uidCol] : $username;
                    $pid = ($pidCol !== '' && isset($row[$pidCol])) ? $row[$pidCol] : '';
                    $lvl = ($lvlCol !== '' && isset($row[$lvlCol]) && $row[$lvlCol] !== '') ? (int)$row[$lvlCol] : 0;

                    $this->ResetFailedAttempts();
                    $this->SaveUserToSession($username, $uid, $pid, $lvl);
                    return true;
                }
            }
        }

        $this->RecordFailedAttempt();
        return false;
    }

    protected function VerifyPassword($inputPw, $storedPw)
    {
        if ($storedPw === '') {
            return false;
        }
        // 1. Klartext-Vergleich
        if ($inputPw === $storedPw) {
            return true;
        }
        // 2. PHP 5.5+ password_verify (bcrypt / argon2)
        if (function_exists('password_verify') && (strpos($storedPw, '$2') === 0 || strpos($storedPw, '$argon2') === 0)) {
            if (password_verify($inputPw, $storedPw)) {
                return true;
            }
        }
        // 3. MD5 Hash (32 Hex-Zeichen)
        if (strlen($storedPw) === 32 && strtolower($storedPw) === md5($inputPw)) {
            return true;
        }
        // 4. SHA-256 Hash (64 Hex-Zeichen)
        if (strlen($storedPw) === 64 && strtolower($storedPw) === hash('sha256', $inputPw)) {
            return true;
        }
        return false;
    }

    public function SaveUserToSession($username, $userid, $parentid, $userlevel)
    {
        if (session_id() !== '') {
            @session_regenerate_id(true);
        }
        $prefix = EW_PROJECT_ID . '_sec_';
        $_SESSION[$prefix . 'logged_in'] = true;
        $_SESSION[$prefix . 'username'] = $username;
        $_SESSION[$prefix . 'userid'] = $userid;
        $_SESSION[$prefix . 'parentid'] = $parentid;
        $_SESSION[$prefix . 'userlevel'] = (int)$userlevel;
        $this->LoadUserFromSession();
        $this->LoadPermissions();
    }

    public function Logout()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        unset($_SESSION[$prefix . 'logged_in']);
        unset($_SESSION[$prefix . 'username']);
        unset($_SESSION[$prefix . 'userid']);
        unset($_SESSION[$prefix . 'parentid']);
        unset($_SESSION[$prefix . 'userlevel']);
        if (session_id() !== '') {
            @session_regenerate_id(true);
        }
        $this->LoadUserFromSession();
        $this->LoadPermissions();
    }

    public function LoadPermissions()
    {
        $this->UserLevelPriv = array();
        if (!$this->IsEnabled()) {
            return;
        }

        // Rollenname ermitteln
        $levels = isset($this->Config['static_user_levels']) && is_array($this->Config['static_user_levels'])
            ? $this->Config['static_user_levels']
            : array();
        foreach ($levels as $l) {
            if ((int)$l['id'] === $this->CurrentUserLevelID) {
                $this->CurrentUserLevelName = $l['name'];
                break;
            }
        }

        // 1. Statische Berechtigungen als Basis laden
        $staticPerms = isset($this->Config['static_permissions']) && is_array($this->Config['static_permissions'])
            ? $this->Config['static_permissions']
            : array();
        foreach ($staticPerms as $tName => $lvlMap) {
            $lvlKey = (string)$this->CurrentUserLevelID;
            $this->UserLevelPriv[$tName] = isset($lvlMap[$lvlKey]) ? (int)$lvlMap[$lvlKey] : 0;
        }

        // 2. Falls Dynamic User Levels aktiv sind -> aus Datenbanktabelle (userlevelpermissions) überschreiben
        if (!empty($this->Config['user_level_security']) && !empty($this->Config['dynamic_user_levels'])) {
            $conn = ew_Connect();
            if ($conn->IsConnected()) {
                $lvlTable = isset($this->Config['user_level_table']) ? $this->Config['user_level_table'] : 'userlevels';
                $privTable = isset($this->Config['user_level_priv_table']) ? $this->Config['user_level_priv_table'] : 'userlevelpermissions';

                $safeLvlT = '`' . str_replace('`', '``', $lvlTable) . '`';
                $lvlRow = @$conn->LoadRow('SELECT `userlevelname` FROM ' . $safeLvlT . ' WHERE `userlevelid` = ?', array($this->CurrentUserLevelID));
                if ($lvlRow && isset($lvlRow['userlevelname'])) {
                    $this->CurrentUserLevelName = $lvlRow['userlevelname'];
                }

                $safePrivT = '`' . str_replace('`', '``', $privTable) . '`';
                $privRows = @$conn->LoadRows('SELECT `tablename`, `permission` FROM ' . $safePrivT . ' WHERE `userlevelid` = ?', array($this->CurrentUserLevelID));
                if (is_array($privRows)) {
                    foreach ($privRows as $pr) {
                        $tKey = $pr['tablename'];
                        // Unterstützt sowohl reinen Tabellennamen als auch {PROJECT_ID}tablename
                        $cleanTKey = str_replace(EW_PROJECT_ID, '', $tKey);
                        $this->UserLevelPriv[$cleanTKey] = (int)$pr['permission'];
                    }
                }
            }
        }
    }

    /**
     * Prüft ein spezifisches Recht (z.B. EW_ALLOW_LIST, EW_ALLOW_ADD, EW_ALLOW_EDIT, EW_ALLOW_DELETE, EW_ALLOW_VIEW)
     */
    public function HasPermission($tableName, $permBit)
    {
        if (!$this->IsEnabled() || $this->IsSysAdmin()) {
            return true;
        }
        // Falls User Level Security deaktiviert ist, darf jeder eingeloggte Benutzer alles
        if (empty($this->Config['user_level_security'])) {
            return $this->IsLoggedIn();
        }
        $priv = isset($this->UserLevelPriv[$tableName]) ? (int)$this->UserLevelPriv[$tableName] : 0;
        if (($priv & EW_ALLOW_ADMIN) === EW_ALLOW_ADMIN) {
            return true;
        }
        return (($priv & (int)$permBit) === (int)$permBit);
    }

    public function CanList($tableName) { return $this->HasPermission($tableName, EW_ALLOW_LIST); }
    public function CanView($tableName) { return $this->HasPermission($tableName, EW_ALLOW_VIEW); }
    public function CanAdd($tableName)  { return $this->HasPermission($tableName, EW_ALLOW_ADD); }
    public function CanEdit($tableName) { return $this->HasPermission($tableName, EW_ALLOW_EDIT); }
    public function CanDelete($tableName) { return $this->HasPermission($tableName, EW_ALLOW_DELETE); }
    public function CanSearch($tableName) { return $this->HasPermission($tableName, EW_ALLOW_SEARCH); }
    public function CanAdmin($tableName) { return $this->HasPermission($tableName, EW_ALLOW_ADMIN); }

    /**
     * Prüft die Seitenberechtigung und leitet bei fehlenden Rechten auf login.php oder index.php um.
     */
    public function CheckPagePermission($tableName, $permBit)
    {
        if (!$this->IsEnabled()) {
            return;
        }
        if ($this->HasPermission($tableName, $permBit)) {
            return;
        }
        if (!$this->IsLoggedIn()) {
            ew_SetFailureMessage('Bitte melden Sie sich an, um auf diese Seite zuzugreifen.');
            ew_Redirect('login.php?return=' . urlencode(basename($_SERVER['REQUEST_URI'])));
        } else {
            ew_SetFailureMessage('Sie verfügen nicht über die erforderlichen Berechtigungen (Rolle: <strong>' . ew_HtmlEncode($this->CurrentUserLevelName) . '</strong>) für diese Aktion.');
            ew_Redirect('index.php');
        }
    }

    /**
     * Ermittelt alle gültigen User-IDs für den aktuellen Benutzer (eigene ID + untergeordnete Mitarbeiter via Parent User ID).
     */
    public function GetAllowedUserIDs()
    {
        if ($this->CurrentUserID === '' || $this->IsSysAdmin()) {
            return array();
        }
        $ids = array($this->CurrentUserID);

        // Falls Parent User ID Feld konfiguriert ist, direkte Untergebene aus der Benutzertabelle laden
        if (!empty($this->Config['use_user_table']) && !empty($this->Config['user_table']) && !empty($this->Config['parent_user_id_field']) && !empty($this->Config['user_id_field'])) {
            $conn = ew_Connect();
            $uTable = '`' . str_replace('`', '``', $this->Config['user_table']) . '`';
            $uidCol = '`' . str_replace('`', '``', $this->Config['user_id_field']) . '`';
            $pidCol = '`' . str_replace('`', '``', $this->Config['parent_user_id_field']) . '`';
            $rows = @$conn->LoadRows('SELECT ' . $uidCol . ' AS uid FROM ' . $uTable . ' WHERE ' . $pidCol . ' = ?', array($this->CurrentUserID));
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (isset($r['uid']) && !in_array($r['uid'], $ids)) {
                        $ids[] = $r['uid'];
                    }
                }
            }
        }
        return $ids;
    }

    /**
     * Erzeugt den SQL-WHERE-Filter für User ID Security (Eigentümer-Beschränkung auf Zeilenebene).
     */
    public function BuildUserIDFilter($tableObj, &$params)
    {
        if (!$this->IsEnabled() || $this->IsSysAdmin() || $this->CanAdmin($tableObj->TableName)) {
            return '';
        }
        $userIdField = isset($tableObj->Config['user_id_field']) ? trim($tableObj->Config['user_id_field']) : '';
        if ($userIdField === '' || !isset($tableObj->Fields[$userIdField])) {
            return '';
        }
        if ($this->CurrentUserID === '') {
            return '1=0';
        }
        $allowedIds = $this->GetAllowedUserIDs();
        $col = '`' . str_replace('`', '``', $userIdField) . '`';
        if (count($allowedIds) <= 1) {
            $params[] = $this->CurrentUserID;
            return '(' . $col . ' = ?)';
        }
        $placeholders = array();
        foreach ($allowedIds as $aid) {
            $placeholders[] = '?';
            $params[] = $aid;
        }
        return '(' . $col . ' IN (' . implode(', ', $placeholders) . '))';
    }
}

/**
 * Globale Hilfsfunktionen zum Abrufen der Security-Instanz und des Benutzer-/Zeit-Kontexts
 * (100% kompatibel mit PHPMacherNG Server Events & Custom Files).
 * @return cAdvancedSecurity
 */
function &ew_Security()
{
    global $EW_SECURITY;
    if (!isset($EW_SECURITY) || !($EW_SECURITY instanceof cAdvancedSecurity)) {
        $EW_SECURITY = new cAdvancedSecurity();
    }
    return $EW_SECURITY;
}

function &Security()
{
    return ew_Security();
}

function CurrentUserName()
{
    return ew_Security()->CurrentUserName;
}

function CurrentUserID()
{
    return ew_Security()->CurrentUserID;
}

function CurrentParentUserID()
{
    return ew_Security()->CurrentParentUserID;
}

function CurrentUserLevel()
{
    return ew_Security()->CurrentUserLevelID;
}

function CurrentUserLevelName()
{
    return ew_Security()->CurrentUserLevelName;
}

function IsLoggedIn()
{
    return ew_Security()->IsLoggedIn();
}

function IsSysAdmin()
{
    return ew_Security()->IsSysAdmin();
}

function CurrentDate()
{
    return date('Y-m-d');
}

function CurrentTime()
{
    return date('H:i:s');
}

function CurrentDateTime()
{
    return date('Y-m-d H:i:s');
}

function CurrentUserIP()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}
PHP;

        $globalCode      = $this->getGlobalServerEvent('Global_Code');
        $pageLoading     = $this->getGlobalServerEvent('Page_Loading');
        $pageRendering   = $this->getGlobalServerEvent('Page_Rendering');
        $pageUnloaded    = $this->getGlobalServerEvent('Page_Unloaded');
        $userLoggingIn   = $this->getGlobalServerEvent('User_LoggingIn');
        $userLoggedIn    = $this->getGlobalServerEvent('User_LoggedIn');
        $userLoginError  = $this->getGlobalServerEvent('User_LoginError');
        $userLoggingOut  = $this->getGlobalServerEvent('User_LoggingOut');

        $evCode = "\n\n// ============================================================================\n";
        $evCode .= "// PHPMacherNG Global Server Events (Projektspezifisch)\n";
        $evCode .= "// ============================================================================\n";
        if ($globalCode !== '') {
            $evCode .= "\n// --- Global Code ---\n" . $globalCode . "\n";
        }
        $evCode .= "\nfunction Page_Loading()\n{\n    " . ($pageLoading !== '' ? $pageLoading : '// Global Page_Loading Event') . "\n}\n";
        $evCode .= "\nfunction Page_Rendering()\n{\n    " . ($pageRendering !== '' ? $pageRendering : '// Global Page_Rendering Event') . "\n}\n";
        $evCode .= "\nfunction Page_Unloaded()\n{\n    " . ($pageUnloaded !== '' ? $pageUnloaded : '// Global Page_Unloaded Event') . "\n}\n";
        $evCode .= "\nfunction User_LoggingIn(\$usr, &\$pwd)\n{\n    " . ($userLoggingIn !== '' ? $userLoggingIn : '// Return false to cancel login') . "\n    return true;\n}\n";
        $evCode .= "\nfunction User_LoggedIn(\$usr)\n{\n    " . ($userLoggedIn !== '' ? $userLoggedIn : '// Called after successful login') . "\n}\n";
        $evCode .= "\nfunction User_LoginError(\$usr, \$pwd)\n{\n    " . ($userLoginError !== '' ? $userLoginError : '// Called on failed login') . "\n}\n";
        $evCode .= "\nfunction User_LoggingOut(\$usr)\n{\n    " . ($userLoggingOut !== '' ? $userLoggingOut : '// Return false to cancel logout') . "\n    return true;\n}\n";

        return $code . $evCode;
    }

    /**
     * Erzeugt header.php im klassischen PHPMacherNG (AdminLTE 2 + Bootstrap 3) Design
     */
    protected function buildHeader()
    {
        require_once dirname(__FILE__) . '/ProjectManager.php';
        $rawMenu = ProjectManager::synchronizeMenuItems($this->project);
        $tables = isset($this->project['tables']) && is_array($this->project['tables']) ? $this->project['tables'] : array();
        $customFiles = isset($this->project['custom_files']) && is_array($this->project['custom_files']) ? $this->project['custom_files'] : array();

        $menuItems = array();
        foreach ($rawMenu as $m) {
            if (empty($m['enabled'])) {
                continue;
            }
            $sType = isset($m['source_type']) ? $m['source_type'] : 'custom';
            $sName = isset($m['source_name']) ? $m['source_name'] : '';
            $slug = '';
            $permTable = '';

            if ($sType === 'table') {
                if (!isset($tables[$sName]) || empty($tables[$sName]['enabled']) || empty($tables[$sName]['list_page'])) {
                    continue;
                }
                $slug = self::tableSlug($sName);
                $permTable = $sName;
            } elseif ($sType === 'custom_file') {
                if (!isset($customFiles[$sName]) || empty($customFiles[$sName]['enabled'])) {
                    continue;
                }
                $cfFileName = isset($customFiles[$sName]['filename']) ? trim($customFiles[$sName]['filename']) : $sName;
                $cfFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($cfFileName));
                if (substr(strtolower($cfFileName), -4) !== '.php') {
                    $cfFileName .= '.php';
                }
                $slug = $cfFileName;
                $permTable = $sName;
            } else {
                $urlBase = basename((string)$m['url']);
                $qPos = strpos($urlBase, '?');
                if ($qPos !== false) {
                    $urlBase = substr($urlBase, 0, $qPos);
                }
                $slug = ($urlBase !== '' && $urlBase !== '#') ? $urlBase : '';
            }

            $menuItems[] = array(
                'id'          => (string)$m['id'],
                'parent_id'   => isset($m['parent_id']) ? (string)$m['parent_id'] : '',
                'caption'     => isset($m['text']) && $m['text'] !== '' ? (string)$m['text'] : 'Menüpunkt',
                'url'         => isset($m['url']) && $m['url'] !== '' ? (string)$m['url'] : '#',
                'icon'        => isset($m['icon']) && $m['icon'] !== '' ? (string)$m['icon'] : 'fa-circle-o',
                'target'      => isset($m['target']) && $m['target'] !== '' ? (string)$m['target'] : '_self',
                'is_header'   => !empty($m['is_header']),
                'allow_anon'  => !empty($m['allow_anon']),
                'source_type' => $sType,
                'table'       => $permTable,
                'slug'        => $slug
            );
        }
        $menuExport = self::varExp($menuItems);

        $code = <<<'PHP'
<?php
require_once dirname(__FILE__) . '/phpfn14.php';
$Security = ew_Security();
$ewMenuItems = 
PHP;
        $code .= $menuExport . ";\n";
        $code .= <<<'PHP'
$currentScript = basename($_SERVER['PHP_SELF']);

if (!function_exists('ew_CanViewMenuItem')) {
    function ew_CanViewMenuItem($item, $Security)
    {
        if (!$Security->IsEnabled()) {
            return true;
        }
        if (!empty($item['allow_anon'])) {
            return true;
        }
        if (!empty($item['table'])) {
            return $Security->CanList($item['table']);
        }
        return $Security->IsLoggedIn();
    }

    function ew_BuildMenuTree($items)
    {
        $byId = array();
        foreach ($items as $it) {
            $it['children'] = array();
            $byId[$it['id']] = $it;
        }
        $tree = array();
        foreach ($byId as $id => &$node) {
            $pid = $node['parent_id'];
            if ($pid !== '' && isset($byId[$pid]) && $pid !== $id) {
                $byId[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);
        return $tree;
    }

    function ew_FilterAndMarkMenuTree($nodes, $Security, $currentScript, &$hasActiveOut)
    {
        $visible = array();
        foreach ($nodes as $node) {
            if (!empty($node['is_header'])) {
                if (!$Security->IsEnabled() || $Security->IsLoggedIn() || !empty($node['allow_anon'])) {
                    $node['is_active'] = false;
                    $visible[] = $node;
                }
                continue;
            }

            $childActive = false;
            $children = !empty($node['children'])
                ? ew_FilterAndMarkMenuTree($node['children'], $Security, $currentScript, $childActive)
                : array();
            $node['children'] = $children;

            $selfActive = false;
            if (!empty($node['slug'])) {
                if ($node['source_type'] === 'table') {
                    $selfActive = (strpos($currentScript, $node['slug']) === 0);
                } else {
                    $selfActive = ($currentScript === $node['slug']);
                }
            }

            $hasOwnLink = ($node['url'] !== '' && $node['url'] !== '#');
            if (!empty($children)) {
                $node['is_active'] = ($selfActive || $childActive);
                if ($node['is_active']) {
                    $hasActiveOut = true;
                }
                $visible[] = $node;
            } elseif ($hasOwnLink && ew_CanViewMenuItem($node, $Security)) {
                $node['is_active'] = $selfActive;
                if ($selfActive) {
                    $hasActiveOut = true;
                }
                $visible[] = $node;
            }
        }
        return $visible;
    }

    function ew_RenderSidebarMenuNodes($nodes, $level = 0)
    {
        $html = '';
        foreach ($nodes as $node) {
            if (!empty($node['is_header'])) {
                $html .= '<li class="header">' . ew_HtmlEncode($node['caption']) . '</li>' . "\n";
                continue;
            }
            $hasChildren = !empty($node['children']);
            $classes = array();
            if ($hasChildren) {
                $classes[] = 'treeview';
            }
            if (!empty($node['is_active'])) {
                $classes[] = 'active';
                if ($hasChildren) {
                    $classes[] = 'menu-open';
                }
            }
            $classAttr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
            $href = $hasChildren && ($node['url'] === '' || $node['url'] === '#') ? '#' : $node['url'];
            $targetAttr = (!empty($node['target']) && $node['target'] !== '_self' && !$hasChildren)
                ? ' target="' . ew_HtmlEncode($node['target']) . '" rel="noopener"'
                : '';
            $icon = !empty($node['icon']) ? $node['icon'] : ($level > 0 ? 'fa-circle-o' : 'fa-table');

            $html .= '<li' . $classAttr . '>';
            $html .= '<a href="' . ew_HtmlEncode($href) . '"' . $targetAttr . '>';
            $html .= '<i class="fa ' . ew_HtmlEncode($icon) . '"></i> ';
            if ($level === 0) {
                $html .= '<span>' . ew_HtmlEncode($node['caption']) . '</span>';
            } else {
                $html .= ew_HtmlEncode($node['caption']);
            }
            if ($hasChildren) {
                $html .= '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>';
            }
            $html .= '</a>';
            if ($hasChildren) {
                $html .= "\n" . '<ul class="treeview-menu">' . "\n";
                $html .= ew_RenderSidebarMenuNodes($node['children'], $level + 1);
                $html .= '</ul>' . "\n";
            }
            $html .= '</li>' . "\n";
        }
        return $html;
    }

    function ew_RenderTopNavbarMenuNodes($nodes, $level = 0)
    {
        $html = '';
        foreach ($nodes as $node) {
            if (!empty($node['is_header'])) {
                if ($level > 0) {
                    $html .= '<li class="dropdown-header">' . ew_HtmlEncode($node['caption']) . '</li>' . "\n";
                }
                continue;
            }
            $hasChildren = !empty($node['children']);
            $classes = array();
            if ($hasChildren) {
                $classes[] = ($level === 0) ? 'dropdown' : 'dropdown-submenu';
            }
            if (!empty($node['is_active'])) {
                $classes[] = 'active';
            }
            $classAttr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
            $href = $hasChildren && ($node['url'] === '' || $node['url'] === '#') ? '#' : $node['url'];
            $targetAttr = (!empty($node['target']) && $node['target'] !== '_self' && !$hasChildren)
                ? ' target="' . ew_HtmlEncode($node['target']) . '" rel="noopener"'
                : '';
            $icon = !empty($node['icon']) ? $node['icon'] : ($level > 0 ? 'fa-circle-o' : 'fa-table');

            $html .= '<li' . $classAttr . '>';
            if ($hasChildren && $level === 0) {
                $html .= '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
                $html .= '<i class="fa ' . ew_HtmlEncode($icon) . '"></i> ' . ew_HtmlEncode($node['caption']) . ' <span class="caret"></span>';
                $html .= '</a>';
                $html .= "\n" . '<ul class="dropdown-menu" role="menu">' . "\n";
                $html .= ew_RenderTopNavbarMenuNodes($node['children'], $level + 1);
                $html .= '</ul>' . "\n";
            } elseif ($hasChildren && $level > 0) {
                $html .= '<li class="dropdown-header" style="font-weight:700; color:#334155;"><i class="fa ' . ew_HtmlEncode($icon) . '"></i> ' . ew_HtmlEncode($node['caption']) . '</li>' . "\n";
                $html .= ew_RenderTopNavbarMenuNodes($node['children'], $level + 1);
            } else {
                $html .= '<a href="' . ew_HtmlEncode($href) . '"' . $targetAttr . '>';
                $html .= '<i class="fa ' . ew_HtmlEncode($icon) . '"></i> ' . ew_HtmlEncode($node['caption']);
                $html .= '</a>';
            }
            $html .= '</li>' . "\n";
        }
        return $html;
    }
}
$ewMenuDummyActive = false;
$ewMenuTree = ew_FilterAndMarkMenuTree(ew_BuildMenuTree($ewMenuItems), $Security, $currentScript, $ewMenuDummyActive);
$ewUiTemplate = defined('EW_UI_TEMPLATE') ? EW_UI_TEMPLATE : 'adminlte2';
$ewIsTopNav = ($ewUiTemplate === 'topnav');
$ewBodyClasses = 'hold-transition ' . EW_THEME_SKIN . ' ' . ($ewIsTopNav ? 'layout-top-nav' : EW_LAYOUT_STYLE) . ' ew-tpl-' . $ewUiTemplate;
if (function_exists('Page_Loading')) {
    Page_Loading();
}
if (function_exists('Page_Rendering')) {
    Page_Rendering();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo isset($PageTitle) ? ew_HtmlEncode($PageTitle) . ' - ' : ''; ?><?php echo ew_HtmlEncode(EW_PROJECT_TITLE); ?></title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.7 (Lokal) -->
    <link rel="stylesheet" href="assets/vendor/bootstrap3/css/bootstrap.min.css">
    <!-- Font Awesome 4.7.0 (Lokal) -->
    <link rel="stylesheet" href="assets/vendor/font-awesome/css/font-awesome.min.css">
    <!-- AdminLTE 2.4 (Lokal) -->
    <link rel="stylesheet" href="assets/vendor/adminlte/css/AdminLTE.min.css">
    <link rel="stylesheet" href="assets/vendor/adminlte/css/skins/_all-skins.min.css">
    <style>
        /* PHPMacherNG (v14) Basis-Styles */
        body { font-family: "Source Sans Pro", "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 14px; }
        .ewGrid { background: #fff; border: 1px solid #d2d6de; border-top: 3px solid #3c8dbc; border-radius: 3px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.1); }
        .ewGridUpperPanel, .ewGridLowerPanel { padding: 10px 15px; background-color: #f9fafc; border-bottom: 1px solid #f4f4f4; }
        .ewGridLowerPanel { border-top: 1px solid #f4f4f4; border-bottom: none; }
        .ewTable { margin-bottom: 0 !important; }
        .ewTable > thead > tr > th { background-color: #f5f5f5; border-bottom: 2px solid #d2d6de; white-space: nowrap; vertical-align: middle; }
        .ewTable > thead > tr > th a { color: #333; display: block; text-decoration: none; }
        .ewTable > thead > tr > th a:hover { color: #3c8dbc; }
        .ewTable > tbody > tr:hover { background-color: #ecf0f5 !important; }
        .ewTable > tbody > tr.ewTableRowSelected { background-color: #d9edf7 !important; }
        .ewListOptionBody { white-space: nowrap; width: 1%; }
        .ewListOptionBody .btn { margin-right: 2px; padding: 3px 7px; }
        .ewToolbar { margin-bottom: 12px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; }
        .ewSearchPanel { background: #fff; border: 1px solid #d2d6de; border-radius: 3px; padding: 15px; margin-bottom: 15px; }
        .ewForm { max-width: 900px; }
        .ewRequired { color: #dd4b39; font-weight: bold; margin-left: 3px; }
        .ewPager { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .ewPager .form-control { width: auto; display: inline-block; height: 30px; padding: 3px 8px; }
        .ewSqlDebug { font-family: Consolas, monospace; font-size: 12px; background: #222d32; color: #b8c7ce; padding: 10px 15px; border-radius: 3px; margin-top: 20px; }

        /* ============================================================================
           TEMPLATE 2: TOP-NAVBAR (Horizontales Hauptmenü ohne linke Sidebar)
           ============================================================================ */
        body.ew-tpl-topnav .content-wrapper .container-fluid { max-width: 1600px; margin: 0 auto; padding: 0 20px; }
        body.ew-tpl-topnav .main-header .navbar-brand { font-size: 17px; padding: 15px 18px; }
        body.ew-tpl-topnav .navbar-nav > li > a { font-weight: 500; }

        /* ============================================================================
           TEMPLATE 3: MODERN SAAS / CLEAN ENTERPRISE UI (Tailwind / Indigo Stil)
           ============================================================================ */
        body.ew-tpl-modern { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, sans-serif; background-color: #f8fafc; color: #1e293b; }
        body.ew-tpl-modern .content-wrapper { background-color: #f1f5f9 !important; }
        body.ew-tpl-modern .main-header .logo { background-color: #0f172a !important; color: #f8fafc !important; border-bottom: 1px solid #1e293b; font-weight: 700; }
        body.ew-tpl-modern .main-header .navbar { background-color: #ffffff !important; border-bottom: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        body.ew-tpl-modern .main-header .navbar .nav > li > a,
        body.ew-tpl-modern .main-header .sidebar-toggle { color: #334155 !important; }
        body.ew-tpl-modern .main-header .navbar .nav > li > a:hover,
        body.ew-tpl-modern .main-header .sidebar-toggle:hover { background-color: #f1f5f9 !important; color: #4f46e5 !important; }
        body.ew-tpl-modern .main-sidebar { background-color: #0f172a !important; border-right: 1px solid #1e293b; }
        body.ew-tpl-modern .sidebar-menu > li.header { background: #0f172a !important; color: #64748b !important; font-weight: 700; letter-spacing: 0.6px; }
        body.ew-tpl-modern .sidebar-menu > li > a { color: #cbd5e1 !important; border-left: 3px solid transparent; margin: 2px 8px; border-radius: 6px; padding: 10px 12px; }
        body.ew-tpl-modern .sidebar-menu > li:hover > a,
        body.ew-tpl-modern .sidebar-menu > li.active > a { background-color: #4f46e5 !important; color: #ffffff !important; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35); }
        body.ew-tpl-modern .treeview-menu { background-color: #1e293b !important; margin: 0 8px; border-radius: 6px; }
        body.ew-tpl-modern .ewGrid,
        body.ew-tpl-modern .box,
        body.ew-tpl-modern .ewSearchPanel { border: 1px solid #e2e8f0 !important; border-top: none !important; border-radius: 10px !important; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.04) !important; overflow: hidden; }
        body.ew-tpl-modern .ewGridUpperPanel,
        body.ew-tpl-modern .ewGridLowerPanel,
        body.ew-tpl-modern .box-header { background-color: #ffffff !important; border-bottom: 1px solid #f1f5f9 !important; padding: 14px 18px !important; }
        body.ew-tpl-modern .ewTable > thead > tr > th { background-color: #f8fafc !important; color: #475569 !important; text-transform: uppercase; font-size: 11.5px; letter-spacing: 0.4px; border-bottom: 1px solid #e2e8f0 !important; padding: 11px 12px; }
        body.ew-tpl-modern .ewTable > tbody > tr > td { padding: 10px 12px; border-color: #f1f5f9; vertical-align: middle; }
        body.ew-tpl-modern .ewTable > tbody > tr:hover { background-color: #eef2ff !important; }
        body.ew-tpl-modern .btn { border-radius: 6px !important; font-weight: 500; transition: all 0.15s; }
        body.ew-tpl-modern .btn-primary { background-color: #4f46e5 !important; border-color: #4338ca !important; }
        body.ew-tpl-modern .btn-primary:hover { background-color: #4338ca !important; }
        body.ew-tpl-modern .btn-success { background-color: #10b981 !important; border-color: #059669 !important; }
        body.ew-tpl-modern .form-control { border-radius: 6px !important; border-color: #cbd5e1; }
        body.ew-tpl-modern .form-control:focus { border-color: #6366f1 !important; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important; }

        /* ============================================================================
           TEMPLATE 4: DARK MODE PRO (Durchgängiges dunkles Dashboard-Theme)
           ============================================================================ */
        body.ew-tpl-dark,
        body.ew-tpl-dark .wrapper,
        body.ew-tpl-dark .content-wrapper { background-color: #0f172a !important; color: #e2e8f0 !important; }
        body.ew-tpl-dark .main-header .logo { background-color: #020617 !important; color: #f8fafc !important; border-bottom: 1px solid #1e293b; border-right: 1px solid #1e293b; }
        body.ew-tpl-dark .main-header .navbar { background-color: #1e293b !important; border-bottom: 1px solid #334155; }
        body.ew-tpl-dark .main-sidebar { background-color: #020617 !important; border-right: 1px solid #1e293b; }
        body.ew-tpl-dark .sidebar-menu > li.header { background-color: #020617 !important; color: #64748b !important; }
        body.ew-tpl-dark .sidebar-menu > li > a { color: #94a3b8 !important; }
        body.ew-tpl-dark .sidebar-menu > li.active > a,
        body.ew-tpl-dark .sidebar-menu > li:hover > a { background-color: #1e293b !important; color: #38bdf8 !important; border-left-color: #38bdf8 !important; }
        body.ew-tpl-dark .content-header > h1 { color: #f8fafc !important; }
        body.ew-tpl-dark .content-header > h1 > small { color: #94a3b8 !important; }
        body.ew-tpl-dark . content-header > .breadcrumb > li > a,
        body.ew-tpl-dark .breadcrumb > .active { color: #94a3b8 !important; }
        body.ew-tpl-dark .ewGrid,
        body.ew-tpl-dark .box,
        body.ew-tpl-dark .ewSearchPanel,
        body.ew-tpl-dark .info-box { background-color: #1e293b !important; border-color: #334155 !important; color: #e2e8f0 !important; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25) !important; }
        body.ew-tpl-dark .ewGridUpperPanel,
        body.ew-tpl-dark .ewGridLowerPanel,
        body.ew-tpl-dark .box-header,
        body.ew-tpl-dark .box-footer { background-color: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
        body.ew-tpl-dark .box-title { color: #f8fafc !important; }
        body.ew-tpl-dark .ewTable,
        body.ew-tpl-dark .table { background-color: #1e293b !important; color: #e2e8f0 !important; border-color: #334155 !important; }
        body.ew-tpl-dark .ewTable > thead > tr > th,
        body.ew-tpl-dark .table > thead > tr > th { background-color: #0f172a !important; color: #cbd5e1 !important; border-color: #334155 !important; }
        body.ew-tpl-dark .ewTable > thead > tr > th a { color: #38bdf8 !important; }
        body.ew-tpl-dark .table-striped > tbody > tr:nth-of-type(odd) { background-color: #172033 !important; }
        body.ew-tpl-dark .table-bordered > tbody > tr > td,
        body.ew-tpl-dark .table-bordered > tbody > tr > th { border-color: #334155 !important; }
        body.ew-tpl-dark .ewTable > tbody > tr:hover { background-color: #334155 !important; }
        body.ew-tpl-dark .ewTable > tbody > tr.ewTableRowSelected { background-color: #1e3a8a !important; }
        body.ew-tpl-dark .form-control { background-color: #0f172a !important; border-color: #475569 !important; color: #f8fafc !important; }
        body.ew-tpl-dark .form-control:focus { border-color: #38bdf8 !important; }
        body.ew-tpl-dark .btn-default { background-color: #334155 !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        body.ew-tpl-dark .btn-default:hover { background-color: #475569 !important; color: #ffffff !important; }
        body.ew-tpl-dark .main-footer { background-color: #020617 !important; border-top: 1px solid #1e293b !important; color: #94a3b8 !important; }
        body.ew-tpl-dark .ewDetailPreviewRow { background-color: #0f172a !important; }
        body.ew-tpl-dark .ewChartCanvas { background-color: #f8fafc; border-radius: 6px; padding: 6px; }

        /* ============================================================================
           TEMPLATE 5: CLASSIC COMPACT / ERP HIGH-DENSITY (Kompakte Tabellenansicht)
           ============================================================================ */
        body.ew-tpl-compact { font-family: Tahoma, Arial, Helvetica, sans-serif; font-size: 12px; }
        body.ew-tpl-compact .content-header { padding: 8px 12px 0 12px; }
        body.ew-tpl-compact .content-header > h1 { font-size: 18px; }
        body.ew-tpl-compact .content { padding: 8px 12px; }
        body.ew-tpl-compact .ewSearchPanel { padding: 8px 10px; margin-bottom: 8px; border-radius: 2px; }
        body.ew-tpl-compact .ewGrid,
        body.ew-tpl-compact .box { border-radius: 0 !important; margin-bottom: 10px; border-top-width: 2px; }
        body.ew-tpl-compact .ewGridUpperPanel,
        body.ew-tpl-compact .ewGridLowerPanel { padding: 5px 10px; }
        body.ew-tpl-compact .ewTable > thead > tr > th,
        body.ew-tpl-compact .ewTable > tbody > tr > td { padding: 3px 7px !important; font-size: 12px; line-height: 1.3; }
        body.ew-tpl-compact .btn { padding: 2px 7px; font-size: 11.5px; border-radius: 2px !important; }
        body.ew-tpl-compact .form-control { height: 26px; padding: 2px 6px; font-size: 12px; border-radius: 2px !important; }
        body.ew-tpl-compact .form-group { margin-bottom: 8px; }
        body.ew-tpl-compact .sidebar-menu > li > a { padding: 7px 12px; font-size: 12.5px; }
    </style>
    <script src="assets/vendor/jquery/jquery.min.js"></script>
PHP;

        $globalClientScript = $this->getGlobalClientScript('Client_Script');
        if ($globalClientScript !== '') {
            $code .= "\n    <script>\n    // PHPMacherNG Global Client Script\n    " . $globalClientScript . "\n    </script>";
        }

        $code .= <<<'PHP'
</head>
<body class="<?php echo ew_HtmlEncode($ewBodyClasses); ?>">
<div class="wrapper">

    <!-- Main Header -->
    <header class="main-header">
        <?php if ($ewIsTopNav): ?>
        <nav class="navbar navbar-static-top" role="navigation">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a href="index.php" class="navbar-brand"><b><?php echo ew_HtmlEncode(EW_PROJECT_NAME); ?></b></a>
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#ew-navbar-collapse">
                        <i class="fa fa-bars"></i>
                    </button>
                </div>

                <!-- Horizontales Hauptmenü (Top-Navbar Template) -->
                <div class="collapse navbar-collapse pull-left" id="ew-navbar-collapse">
                    <ul class="nav navbar-nav">
                        <?php echo ew_RenderTopNavbarMenuNodes($ewMenuTree, 0); ?>
                        <?php if ($Security->IsEnabled() && $Security->IsSysAdmin() && is_file(dirname(__FILE__) . '/userpriv.php')): ?>
                        <li class="<?php echo ($currentScript === 'userpriv.php') ? 'active' : ''; ?>">
                            <a href="userpriv.php"><i class="fa fa-shield text-yellow"></i> <span>Rollen-Berechtigungen</span></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <?php if ($Security->IsEnabled()): ?>
                            <?php if ($Security->IsLoggedIn()): ?>
                                <li class="dropdown user user-menu">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                        <i class="fa fa-user-circle"></i>
                                        <span><?php echo ew_HtmlEncode($Security->CurrentUserName); ?></span>
                                        <span class="label label-primary" style="margin-left:4px;"><?php echo ew_HtmlEncode($Security->CurrentUserLevelName); ?></span>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li class="user-header" style="height:auto; padding:15px;">
                                            <p style="margin:0;">
                                                <strong><?php echo ew_HtmlEncode($Security->CurrentUserName); ?></strong><br>
                                                <small>Rolle: <?php echo ew_HtmlEncode($Security->CurrentUserLevelName); ?> (Level <?php echo (int)$Security->CurrentUserLevelID; ?>)</small>
                                                <?php if ($Security->CurrentUserID !== ''): ?>
                                                    <br><small>User-ID: <?php echo ew_HtmlEncode($Security->CurrentUserID); ?></small>
                                                <?php endif; ?>
                                            </p>
                                        </li>
                                        <li class="user-footer">
                                            <?php if ($Security->IsSysAdmin() && is_file(dirname(__FILE__) . '/userpriv.php')): ?>
                                            <div class="pull-left">
                                                <a href="userpriv.php" class="btn btn-default btn-flat"><i class="fa fa-shield"></i> Rollen-Rechte</a>
                                            </div>
                                            <?php endif; ?>
                                            <div class="pull-right">
                                                <a href="logout.php" class="btn btn-default btn-flat"><i class="fa fa-sign-out"></i> Abmelden</a>
                                            </div>
                                        </li>
                                    </ul>
                                </li>
                            <?php else: ?>
                                <li><a href="login.php"><i class="fa fa-sign-in"></i> <span>Anmelden</span></a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <li>
                            <a href="#" title="Datenbank: <?php echo ew_HtmlEncode(EW_CONN_DB); ?>">
                                <i class="fa fa-database"></i> <span class="hidden-xs"><?php echo ew_HtmlEncode(EW_CONN_DB); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <?php else: ?>
        <a href="index.php" class="logo">
            <span class="logo-mini"><b>EW</b>18</span>
            <span class="logo-lg"><b><?php echo ew_HtmlEncode(EW_PROJECT_NAME); ?></b></span>
        </a>
        <nav class="navbar navbar-static-top" role="navigation">
            <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                <span class="sr-only">Navigation umschalten</span>
            </a>
            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    <?php if ($Security->IsEnabled()): ?>
                        <?php if ($Security->IsLoggedIn()): ?>
                            <li class="dropdown user user-menu">
                                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                    <i class="fa fa-user-circle"></i>
                                    <span><?php echo ew_HtmlEncode($Security->CurrentUserName); ?></span>
                                    <span class="label label-primary" style="margin-left:4px;"><?php echo ew_HtmlEncode($Security->CurrentUserLevelName); ?></span>
                                </a>
                                <ul class="dropdown-menu">
                                    <li class="user-header" style="height:auto; padding:15px;">
                                        <p style="margin:0;">
                                            <strong><?php echo ew_HtmlEncode($Security->CurrentUserName); ?></strong><br>
                                            <small>Rolle: <?php echo ew_HtmlEncode($Security->CurrentUserLevelName); ?> (Level <?php echo (int)$Security->CurrentUserLevelID; ?>)</small>
                                            <?php if ($Security->CurrentUserID !== ''): ?>
                                                <br><small>User-ID: <?php echo ew_HtmlEncode($Security->CurrentUserID); ?></small>
                                            <?php endif; ?>
                                        </p>
                                    </li>
                                    <li class="user-footer">
                                        <?php if ($Security->IsSysAdmin() && is_file(dirname(__FILE__) . '/userpriv.php')): ?>
                                        <div class="pull-left">
                                            <a href="userpriv.php" class="btn btn-default btn-flat"><i class="fa fa-shield"></i> Rollen-Rechte</a>
                                        </div>
                                        <?php endif; ?>
                                        <div class="pull-right">
                                            <a href="logout.php" class="btn btn-default btn-flat"><i class="fa fa-sign-out"></i> Abmelden</a>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li>
                                <a href="login.php"><i class="fa fa-sign-in"></i> <span>Anmelden</span></a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li>
                        <a href="#" title="Datenbank: <?php echo ew_HtmlEncode(EW_CONN_DB); ?>">
                            <i class="fa fa-database"></i> <span class="hidden-xs"><?php echo ew_HtmlEncode(EW_CONN_DB); ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <?php endif; ?>
    </header>

    <?php if (!$ewIsTopNav): ?>
    <!-- Left side column. contains the logo and sidebar -->
    <aside class="main-sidebar">
        <section class="sidebar">
            <ul class="sidebar-menu" data-widget="tree">
                <li class="header">HAUPTMENÜ</li>
                <?php echo ew_RenderSidebarMenuNodes($ewMenuTree, 0); ?>

                <?php if ($Security->IsEnabled() && $Security->IsSysAdmin() && is_file(dirname(__FILE__) . '/userpriv.php')): ?>
                <li class="header">ADMINISTRATION &amp; SECURITY</li>
                <li class="<?php echo ($currentScript === 'userpriv.php') ? 'active' : ''; ?>">
                    <a href="userpriv.php">
                        <i class="fa fa-shield text-yellow"></i>
                        <span>Rollen-Berechtigungen</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </section>
    </aside>
    <?php endif; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <?php if ($ewIsTopNav): ?><div class="container-fluid"><?php endif; ?>
        <section class="content-header">
            <h1>
                <?php echo isset($PageTitle) ? ew_HtmlEncode($PageTitle) : ew_HtmlEncode(EW_PROJECT_TITLE); ?>
                <?php if (!empty($PageSubTitle)): ?>
                    <small><?php echo ew_HtmlEncode($PageSubTitle); ?></small>
                <?php endif; ?>
            </h1>
            <ol class="breadcrumb">
                <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
                <?php if (isset($PageTitle)): ?>
                    <li class="active"><?php echo ew_HtmlEncode($PageTitle); ?></li>
                <?php endif; ?>
            </ol>
        </section>

        <section class="content">
            <?php echo ew_ShowMessages(); ?>
            <?php
            $connCheck = ew_Connect();
            if (!$connCheck->IsConnected()):
            ?>
            <div class="alert alert-danger">
                <h4><i class="icon fa fa-ban"></i> Datenbank-Verbindungsfehler!</h4>
                Konnte keine Verbindung zu <strong><?php echo ew_HtmlEncode(EW_CONN_HOST); ?> / <?php echo ew_HtmlEncode(EW_CONN_DB); ?></strong> herstellen:<br>
                <code><?php echo ew_HtmlEncode($connCheck->lastError); ?></code>
            </div>
            <?php endif; ?>
PHP;
        return $code;
    }

    /**
     * Erzeugt footer.php inkl. JavaScript für Multi-Select, Multi-Delete, Multi-Update, Client Scripts & Debug-Log
     */
    protected function buildFooter()
    {
        $globalStartupScript = $this->getGlobalClientScript('Startup_Script');

        $code = <<<'PHP'
            <?php
            $connDbg = ew_Connect();
            if (EW_DEBUG_ENABLED && !empty($connDbg->sqlLog)):
            ?>
            <div class="ewSqlDebug">
                <strong><i class="fa fa-terminal"></i> SQL Debug Log (<?php echo count($connDbg->sqlLog); ?> Queries):</strong>
                <ul style="margin:6px 0 0 0; padding-left:18px;">
                    <?php foreach ($connDbg->sqlLog as $entry): ?>
                        <li>
                            <code><?php echo ew_HtmlEncode($entry['sql']); ?></code>
                            <?php if (!empty($entry['params'])): ?>
                                <span style="color:#00a65a;">Params: <?php echo ew_HtmlEncode(json_encode($entry['params'])); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>
        <?php if (defined('EW_UI_TEMPLATE') && EW_UI_TEMPLATE === 'topnav'): ?></div><?php endif; ?>
    </div>

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="pull-right hidden-xs">
            <b>PHPMacherNG</b> v14 Engine (PHP <?php echo ew_HtmlEncode(PHP_VERSION); ?>)
        </div>
        <?php echo EW_FOOTER_TEXT; ?>
    </footer>
</div>

<!-- Bootstrap 3.3.7 JS (Lokal) -->
<script src="assets/vendor/bootstrap3/js/bootstrap.min.js"></script>
<!-- AdminLTE App (Lokal) -->
<script src="assets/vendor/adminlte/js/adminlte.min.js"></script>
<script>
// PHPMacherNG Client-Side Helpers
function ew_ToggleAllKeys(masterCheckbox) {
    var checkboxes = document.querySelectorAll('input.ewKeyCheckbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = masterCheckbox.checked;
        var row = checkboxes[i].closest('tr');
        if (row) {
            if (masterCheckbox.checked) {
                row.classList.add('ewTableRowSelected');
            } else {
                row.classList.remove('ewTableRowSelected');
            }
        }
    }
}

function ew_OnRowCheckboxChange(cb) {
    var row = cb.closest('tr');
    if (row) {
        if (cb.checked) {
            row.classList.add('ewTableRowSelected');
        } else {
            row.classList.remove('ewTableRowSelected');
        }
    }
}

function ew_SubmitMultiAction(formId, targetUrl) {
    var form = document.getElementById(formId);
    if (!form) return;
    var checked = form.querySelectorAll('input.ewKeyCheckbox:checked');
    if (checked.length === 0) {
        alert('Bitte wählen Sie mindestens einen Datensatz aus.');
        return;
    }
    form.action = targetUrl;
    form.method = 'post';
    form.submit();
}

function ew_ToggleExtSearch(btn) {
    var box = document.getElementById('ewExtSearchCollapse');
    if (!box) return;
    var isOpen = box.classList.contains('in') || box.style.display === 'block';
    if (isOpen) {
        box.classList.remove('in');
        box.style.display = 'none';
        if (btn) btn.setAttribute('aria-expanded', 'false');
    } else {
        box.classList.add('in');
        box.style.display = 'block';
        if (btn) btn.setAttribute('aria-expanded', 'true');
        var firstInput = box.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            try { firstInput.focus(); } catch (e) {}
        }
    }
}

// PHPMacherNG Master/Detail Preview Row Toggle (Aufklappbare Unterliste per AJAX)
function ew_ToggleDetailPreview(btn, rowId, firstUrl) {
    var tr = document.getElementById('ewPreviewRow_' + rowId);
    if (!tr) return;
    var icon = btn.querySelector('i.fa');
    if (tr.style.display === 'none' || tr.style.display === '') {
        tr.style.display = 'table-row';
        if (icon) {
            icon.classList.remove('fa-plus-square-o');
            icon.classList.add('fa-minus-square-o');
        }
        var contentBox = document.getElementById('ewPreviewContent_' + rowId);
        if (contentBox && !contentBox.getAttribute('data-loaded') && firstUrl) {
            ew_LoadDetailPreviewUrl(rowId, firstUrl, null);
        }
    } else {
        tr.style.display = 'none';
        if (icon) {
            icon.classList.remove('fa-minus-square-o');
            icon.classList.add('fa-plus-square-o');
        }
    }
}

function ew_LoadDetailPreviewUrl(rowId, url, tabEl) {
    var contentBox = document.getElementById('ewPreviewContent_' + rowId);
    if (!contentBox) return;
    if (tabEl) {
        var ul = tabEl.closest('ul');
        if (ul) {
            var lis = ul.querySelectorAll('li');
            for (var i = 0; i < lis.length; i++) lis[i].classList.remove('active');
        }
        var li = tabEl.closest('li');
        if (li) li.classList.add('active');
    }
    contentBox.innerHTML = '<div style="padding:12px; color:#666;"><i class="fa fa-spinner fa-spin"></i> Lade Unterliste...</div>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                contentBox.innerHTML = xhr.responseText;
                contentBox.setAttribute('data-loaded', '1');
            } else {
                contentBox.innerHTML = '<div class="text-danger" style="padding:10px;">Fehler beim Laden der Detail-Vorschau (HTTP ' + xhr.status + ').</div>';
            }
        }
    };
    xhr.send();
}

// PHPMacherNG Touch & Mouse HTML5 Signature Pad Engine (für SIGNATURE Felder)
function ew_InitSignaturePads() {
    var widgets = document.querySelectorAll('.ewSignaturePadWidget');
    for (var i = 0; i < widgets.length; i++) {
        (function (widget) {
            if (widget.getAttribute('data-sig-initialized') === '1') return;
            widget.setAttribute('data-sig-initialized', '1');

            var targetId = widget.getAttribute('data-sig-target');
            var hiddenInput = document.getElementById(targetId);
            var canvas = document.getElementById('canvas_' + targetId);
            var statusEl = document.getElementById('sig_status_' + targetId);
            if (!canvas || !hiddenInput) return;

            var ctx = canvas.getContext('2d');
            var isDrawing = false;
            var lastX = 0;
            var lastY = 0;

            function configureBrush() {
                ctx.strokeStyle = '#112244';
                ctx.lineWidth = 2.4;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }
            configureBrush();

            // Falls bereits eine gespeicherte Base64-Unterschrift existiert (z.B. im Edit-Formular), auf Canvas zeichnen
            if (hiddenInput.value && hiddenInput.value.indexOf('data:image/') === 0) {
                var img = new Image();
                img.onload = function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                };
                img.src = hiddenInput.value;
            }

            function getCoords(e) {
                var rect = canvas.getBoundingClientRect();
                var scaleX = canvas.width / (rect.width || canvas.width);
                var scaleY = canvas.height / (rect.height || canvas.height);
                var clientX, clientY;
                if (e.touches && e.touches.length > 0) {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                } else if (e.changedTouches && e.changedTouches.length > 0) {
                    clientX = e.changedTouches[0].clientX;
                    clientY = e.changedTouches[0].clientY;
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }
                return {
                    x: (clientX - rect.left) * scaleX,
                    y: (clientY - rect.top) * scaleY
                };
            }

            function startDraw(e) {
                if (e.cancelable) e.preventDefault();
                isDrawing = true;
                configureBrush();
                var pos = getCoords(e);
                lastX = pos.x;
                lastY = pos.y;
                ctx.beginPath();
                ctx.arc(lastX, lastY, 1.1, 0, Math.PI * 2, false);
                ctx.fillStyle = '#112244';
                ctx.fill();
            }

            function moveDraw(e) {
                if (!isDrawing) return;
                if (e.cancelable) e.preventDefault();
                var pos = getCoords(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                lastX = pos.x;
                lastY = pos.y;
            }

            function endDraw(e) {
                if (!isDrawing) return;
                if (e && e.cancelable) e.preventDefault();
                isDrawing = false;
                try {
                    hiddenInput.value = canvas.toDataURL('image/png');
                    if (statusEl) {
                        statusEl.innerHTML = '<i class="fa fa-check text-success"></i> Unterschrift erfasst';
                    }
                    // Falls Multi-Update Checkbox vorhanden ist, automatisch aktivieren
                    var fieldName = targetId.replace(/^x_/, '');
                    var multiCb = document.getElementById('u_' + fieldName);
                    if (multiCb) multiCb.checked = true;
                } catch (err) {}
            }

            // Maus-Events
            canvas.addEventListener('mousedown', startDraw, false);
            canvas.addEventListener('mousemove', moveDraw, false);
            window.addEventListener('mouseup', endDraw, false);

            // Touchscreen-Events (Smartphone / Tablet / Stylus)
            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', moveDraw, { passive: false });
            canvas.addEventListener('touchend', endDraw, { passive: false });
            canvas.addEventListener('touchcancel', endDraw, { passive: false });
        })(widgets[i]);
    }
}

function ew_ClearSignaturePad(targetId) {
    var hiddenInput = document.getElementById(targetId);
    var canvas = document.getElementById('canvas_' + targetId);
    var statusEl = document.getElementById('sig_status_' + targetId);
    if (canvas) {
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
    if (hiddenInput) {
        hiddenInput.value = '';
    }
    if (statusEl) {
        statusEl.innerHTML = 'Unterschrift gelöscht';
    }
}

// ============================================================================
// PHPMacherNG HTML5 Bar & Pie Chart Engine (100% lokal & ohne externe Abhängigkeiten)
// ============================================================================
var EW_CHART_PALETTE = [
    '#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8',
    '#00c0ef', '#d81b60', '#39cccc', '#ff851b', '#001f3f',
    '#2ecc71', '#e74c3c', '#9b59b6', '#34495e', '#16a085'
];

function ew_FormatChartNum(n) {
    var num = Number(n) || 0;
    var isInt = (Math.abs(num - Math.round(num)) < 0.0001);
    var fixed = isInt ? String(Math.round(num)) : num.toFixed(2).replace('.', ',');
    return fixed.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function ew_DrawChartCanvas(canvas) {
    if (!canvas) return;
    var raw = canvas.getAttribute('data-ew-chart');
    if (!raw) return;
    var cfg;
    try {
        cfg = JSON.parse(raw);
    } catch (e) {
        return;
    }
    var labels = cfg.labels || [];
    var values = cfg.values || [];
    var validTypes = ['bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge'];
    var chartType = (cfg.type && validTypes.indexOf(String(cfg.type).toLowerCase()) !== -1)
        ? String(cfg.type).toLowerCase()
        : 'bar';

    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    var cssW = Math.max(260, Math.floor(rect.width || (canvas.parentElement ? canvas.parentElement.clientWidth : 500) || 500));
    var cssH = parseInt(canvas.getAttribute('height') || '280', 10) || 280;

    canvas.width = cssW * dpr;
    canvas.height = cssH * dpr;
    canvas.style.height = cssH + 'px';

    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);

    if (labels.length === 0) {
        ctx.fillStyle = '#888';
        ctx.font = '13px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Keine Daten für dieses Diagramm vorhanden.', cssW / 2, cssH / 2);
        return;
    }

    var total = 0;
    var maxVal = 0;
    for (var i = 0; i < values.length; i++) {
        var v = Math.max(0, Number(values[i]) || 0);
        total += v;
        if (v > maxVal) maxVal = v;
    }

    if (chartType === 'pie' || chartType === 'doughnut') {
        // 1. TORTENDIAGRAMM (PIE) & 2. RING-/DONUT-DIAGRAMM (DOUGHNUT) MIT LEGENDE & PROZENTWERTEN
        if (total <= 0) {
            ctx.fillStyle = '#888';
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Alle Werte sind 0 – Diagramm kann nicht berechnet werden.', cssW / 2, cssH / 2);
            return;
        }

        var isWide = (cssW >= 460);
        var centerX = isWide ? Math.floor(cssW * 0.34) : Math.floor(cssW * 0.5);
        var centerY = isWide ? Math.floor(cssH * 0.5) : Math.floor(cssH * 0.38);
        var radius = Math.max(45, Math.min(centerX - 24, centerY - 22, 115));

        var startAngle = -Math.PI / 2;
        for (var s = 0; s < values.length; s++) {
            var val = Math.max(0, Number(values[s]) || 0);
            if (val <= 0) continue;
            var sliceAngle = (val / total) * (Math.PI * 2);
            var endAngle = startAngle + sliceAngle;
            var color = EW_CHART_PALETTE[s % EW_CHART_PALETTE.length];

            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = color;
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#ffffff';
            ctx.stroke();

            var pct = (val / total) * 100;
            if (pct >= 6) {
                var midAngle = startAngle + sliceAngle / 2;
                var labelRad = (chartType === 'doughnut') ? (radius * 0.76) : (radius * 0.65);
                var tx = centerX + Math.cos(midAngle) * labelRad;
                var ty = centerY + Math.sin(midAngle) * labelRad;
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(Math.round(pct) + '%', tx, ty);
            }

            startAngle = endAngle;
        }

        if (chartType === 'doughnut') {
            var innerRadius = radius * 0.54;
            ctx.beginPath();
            ctx.arc(centerX, centerY, innerRadius, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();

            ctx.fillStyle = '#64748b';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText('GESAMT', centerX, centerY - 2);

            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 13px sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(ew_FormatChartNum(total), centerX, centerY + 1);
        }

        // Legende rechts daneben (oder darunter bei schmalen Bildschirmen)
        var legX = isWide ? Math.floor(cssW * 0.64) : 16;
        var legY = isWide ? 24 : (centerY + radius + 16);
        var maxLegendItems = Math.min(labels.length, isWide ? 10 : 5);

        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        for (var l = 0; l < maxLegendItems; l++) {
            var lVal = Math.max(0, Number(values[l]) || 0);
            var lPct = total > 0 ? ((lVal / total) * 100).toFixed(1).replace('.', ',') : '0,0';
            var lColor = EW_CHART_PALETTE[l % EW_CHART_PALETTE.length];
            var rowY = legY + l * 22;
            if (rowY > cssH - 10) break;

            ctx.fillStyle = lColor;
            ctx.fillRect(legX, rowY - 6, 12, 12);

            ctx.fillStyle = '#1e293b';
            ctx.font = '12px sans-serif';
            var lblText = String(labels[l]);
            if (lblText.length > 22) lblText = lblText.substring(0, 20) + '…';
            ctx.fillText(lblText + ': ' + ew_FormatChartNum(lVal) + ' (' + lPct + '%)', legX + 18, rowY);
        }
    } else if (chartType === 'hbar') {
        // 3. HORIZONTALES BALKENDIAGRAMM (HBAR)
        var hPadLeft = Math.min(150, Math.max(95, Math.floor(cssW * 0.26)));
        var hPadRight = 64;
        var hPadTop = 18;
        var hPadBottom = 28;
        var hPlotW = Math.max(80, cssW - hPadLeft - hPadRight);
        var hPlotH = Math.max(60, cssH - hPadTop - hPadBottom);
        var hNiceMax = (maxVal > 0 ? maxVal : 10) * 1.12;

        var hSteps = 4;
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        for (var hg = 0; hg <= hSteps; hg++) {
            var hRatio = hg / hSteps;
            var gx = hPadLeft + (hRatio * hPlotW);
            ctx.beginPath();
            ctx.moveTo(gx, hPadTop);
            ctx.lineTo(gx, hPadTop + hPlotH);
            ctx.strokeStyle = (hg === 0) ? '#94a3b8' : '#e2e8f0';
            ctx.lineWidth = (hg === 0) ? 1.5 : 1;
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(ew_FormatChartNum(hNiceMax * hRatio), gx, hPadTop + hPlotH + 6);
        }

        var hCount = labels.length;
        var rowH = hPlotH / hCount;
        var hBarThick = Math.max(8, Math.min(28, rowH * 0.65));

        for (var hi = 0; hi < hCount; hi++) {
            var hVal = Math.max(0, Number(values[hi]) || 0);
            var bw = (hVal / hNiceMax) * hPlotW;
            var byRow = hPadTop + (hi * rowH) + (rowH - hBarThick) / 2;
            var hColor = EW_CHART_PALETTE[hi % EW_CHART_PALETTE.length];

            ctx.fillStyle = hColor;
            ctx.fillRect(hPadLeft, byRow, Math.max(2, bw), hBarThick);

            var yLbl = String(labels[hi]);
            if (yLbl.length > 18) yLbl = yLbl.substring(0, 17) + '…';
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillText(yLbl, hPadLeft - 8, byRow + hBarThick / 2);

            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(ew_FormatChartNum(hVal), hPadLeft + bw + 6, byRow + hBarThick / 2);
        }
    } else if (chartType === 'line' || chartType === 'area') {
        // 4. LINIENDIAGRAMM (LINE) & 5. FLÄCHENDIAGRAMM (AREA)
        var lPadLeft = 58;
        var lPadRight = 24;
        var lPadTop = 26;
        var lPadBottom = 52;
        var lPlotW = Math.max(100, cssW - lPadLeft - lPadRight);
        var lPlotH = Math.max(80, cssH - lPadTop - lPadBottom);
        var lNiceMax = (maxVal > 0 ? maxVal : 10) * 1.15;

        var lSteps = 4;
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (var lg = 0; lg <= lSteps; lg++) {
            var lRatio = lg / lSteps;
            var lgy = lPadTop + lPlotH - (lRatio * lPlotH);
            ctx.beginPath();
            ctx.moveTo(lPadLeft, lgy);
            ctx.lineTo(lPadLeft + lPlotW, lgy);
            ctx.strokeStyle = (lg === 0) ? '#94a3b8' : '#e2e8f0';
            ctx.lineWidth = (lg === 0) ? 1.5 : 1;
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(ew_FormatChartNum(lNiceMax * lRatio), lPadLeft - 8, lgy);
        }

        var lCount = labels.length;
        var pts = [];
        for (var li = 0; li < lCount; li++) {
            var lv = Math.max(0, Number(values[li]) || 0);
            var px = (lCount === 1) ? (lPadLeft + lPlotW / 2) : (lPadLeft + (li / (lCount - 1)) * lPlotW);
            var py = lPadTop + lPlotH - ((lv / lNiceMax) * lPlotH);
            pts.push({ x: px, y: py, val: lv, label: String(labels[li]) });
        }

        if (chartType === 'area' && pts.length > 0) {
            ctx.beginPath();
            ctx.moveTo(pts[0].x, lPadTop + lPlotH);
            for (var ai = 0; ai < pts.length; ai++) {
                ctx.lineTo(pts[ai].x, pts[ai].y);
            }
            ctx.lineTo(pts[pts.length - 1].x, lPadTop + lPlotH);
            ctx.closePath();
            var grad = ctx.createLinearGradient(0, lPadTop, 0, lPadTop + lPlotH);
            grad.addColorStop(0, 'rgba(60, 141, 188, 0.42)');
            grad.addColorStop(1, 'rgba(60, 141, 188, 0.04)');
            ctx.fillStyle = grad;
            ctx.fill();
        }

        if (pts.length > 1) {
            ctx.beginPath();
            ctx.moveTo(pts[0].x, pts[0].y);
            for (var pi = 1; pi < pts.length; pi++) {
                ctx.lineTo(pts[pi].x, pts[pi].y);
            }
            ctx.strokeStyle = '#3c8dbc';
            ctx.lineWidth = 2.5;
            ctx.stroke();
        }

        for (var di = 0; di < pts.length; di++) {
            var pt = pts[di];
            ctx.beginPath();
            ctx.arc(pt.x, pt.y, 4.5, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.lineWidth = 2.5;
            ctx.strokeStyle = '#3c8dbc';
            ctx.stroke();

            ctx.fillStyle = '#1e293b';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(ew_FormatChartNum(pt.val), pt.x, Math.max(12, pt.y - 7));

            var lxLbl = pt.label;
            var lMaxChars = lCount > 8 ? 9 : 14;
            if (lxLbl.length > lMaxChars) lxLbl = lxLbl.substring(0, lMaxChars - 1) + '…';
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(lxLbl, pt.x, lPadTop + lPlotH + 8);
        }
    } else if (chartType === 'funnel') {
        // 6. TRICHTERDIAGRAMM (FUNNEL CHART)
        var fPadTop = 16;
        var fPadBottom = 16;
        var fCount = Math.min(labels.length, 10);
        var fPlotH = Math.max(80, cssH - fPadTop - fPadBottom);
        var stageH = fPlotH / fCount;
        var maxStageW = Math.min(cssW * 0.62, cssW - 140);
        var minStageW = Math.max(48, maxStageW * 0.22);
        var centerXFunnel = Math.floor(cssW * 0.42);
        var fMax = maxVal > 0 ? maxVal : 1;

        for (var fi = 0; fi < fCount; fi++) {
            var fVal = Math.max(0, Number(values[fi]) || 0);
            var nextVal = (fi + 1 < fCount) ? Math.max(0, Number(values[fi + 1]) || 0) : (fVal * 0.65);
            var wTop = minStageW + (fVal / fMax) * (maxStageW - minStageW);
            var wBot = minStageW + (nextVal / fMax) * (maxStageW - minStageW);
            var yTop = fPadTop + fi * stageH;
            var yBot = yTop + stageH - 2;

            ctx.beginPath();
            ctx.moveTo(centerXFunnel - wTop / 2, yTop);
            ctx.lineTo(centerXFunnel + wTop / 2, yTop);
            ctx.lineTo(centerXFunnel + wBot / 2, yBot);
            ctx.lineTo(centerXFunnel - wBot / 2, yBot);
            ctx.closePath();
            ctx.fillStyle = EW_CHART_PALETTE[fi % EW_CHART_PALETTE.length];
            ctx.fill();

            var fPct = total > 0 ? Math.round((fVal / total) * 100) : 0;
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(ew_FormatChartNum(fVal) + ' (' + fPct + '%)', centerXFunnel, (yTop + yBot) / 2);

            var fLbl = String(labels[fi]);
            if (fLbl.length > 20) fLbl = fLbl.substring(0, 18) + '…';
            ctx.fillStyle = '#1e293b';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(fLbl, centerXFunnel + maxStageW / 2 + 12, (yTop + yBot) / 2);
        }
    } else if (chartType === 'radar') {
        // 7. NETZ- / RADARDIAGRAMM (SPIDER / RADAR CHART)
        var rCount = Math.min(labels.length, 12);
        var rCx = Math.floor(cssW * 0.5);
        var rCy = Math.floor(cssH * 0.52);
        var rRadius = Math.max(45, Math.min(rCx - 65, rCy - 32, 110));
        var rMax = (maxVal > 0 ? maxVal : 10) * 1.1;

        var rings = 4;
        for (var rg = 1; rg <= rings; rg++) {
            var rr = (rg / rings) * rRadius;
            ctx.beginPath();
            for (var ra = 0; ra < rCount; ra++) {
                var ang = -Math.PI / 2 + (ra / rCount) * (Math.PI * 2);
                var rx = rCx + Math.cos(ang) * rr;
                var ry = rCy + Math.sin(ang) * rr;
                if (ra === 0) ctx.moveTo(rx, ry);
                else ctx.lineTo(rx, ry);
            }
            ctx.closePath();
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1;
            ctx.stroke();
        }

        for (var ax = 0; ax < rCount; ax++) {
            var axAng = -Math.PI / 2 + (ax / rCount) * (Math.PI * 2);
            var axX = rCx + Math.cos(axAng) * rRadius;
            var axY = rCy + Math.sin(axAng) * rRadius;
            ctx.beginPath();
            ctx.moveTo(rCx, rCy);
            ctx.lineTo(axX, axY);
            ctx.strokeStyle = '#cbd5e1';
            ctx.stroke();

            var lblX = rCx + Math.cos(axAng) * (rRadius + 16);
            var lblY = rCy + Math.sin(axAng) * (rRadius + 14);
            var rLbl = String(labels[ax]);
            if (rLbl.length > 14) rLbl = rLbl.substring(0, 13) + '…';
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textAlign = Math.abs(Math.cos(axAng)) < 0.2 ? 'center' : (Math.cos(axAng) > 0 ? 'left' : 'right');
            ctx.textBaseline = 'middle';
            ctx.fillText(rLbl + ' (' + ew_FormatChartNum(values[ax]) + ')', lblX, lblY);
        }

        ctx.beginPath();
        for (var dp = 0; dp < rCount; dp++) {
            var dVal = Math.max(0, Number(values[dp]) || 0);
            var dRad = (dVal / rMax) * rRadius;
            var dAng = -Math.PI / 2 + (dp / rCount) * (Math.PI * 2);
            var dx = rCx + Math.cos(dAng) * dRad;
            var dy = rCy + Math.sin(dAng) * dRad;
            if (dp === 0) ctx.moveTo(dx, dy);
            else ctx.lineTo(dx, dy);
        }
        ctx.closePath();
        ctx.fillStyle = 'rgba(60, 141, 188, 0.30)';
        ctx.fill();
        ctx.strokeStyle = '#3c8dbc';
        ctx.lineWidth = 2;
        ctx.stroke();

        for (var dp2 = 0; dp2 < rCount; dp2++) {
            var dVal2 = Math.max(0, Number(values[dp2]) || 0);
            var dRad2 = (dVal2 / rMax) * rRadius;
            var dAng2 = -Math.PI / 2 + (dp2 / rCount) * (Math.PI * 2);
            ctx.beginPath();
            ctx.arc(rCx + Math.cos(dAng2) * dRad2, rCy + Math.sin(dAng2) * dRad2, 4, 0, Math.PI * 2);
            ctx.fillStyle = '#3c8dbc';
            ctx.fill();
        }
    } else if (chartType === 'gauge') {
        // 8. KPI-TACHO / GAUGE-DIAGRAMM (HALBKREIS-TACHO MIT DURCHSCHNITT, SUMME & TOP-WERT)
        var gCx = Math.floor(cssW * 0.5);
        var gCy = Math.floor(cssH * 0.68);
        var gRadius = Math.max(60, Math.min(gCx - 40, gCy - 28, 125));
        var avgVal = values.length > 0 ? (total / values.length) : 0;
        var gaugeMax = maxVal > 0 ? maxVal : 100;
        var fillRatio = Math.min(1, Math.max(0, avgVal / gaugeMax));

        // Hintergrundbogen
        ctx.beginPath();
        ctx.arc(gCx, gCy, gRadius, Math.PI, Math.PI * 2);
        ctx.lineWidth = 24;
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineCap = 'round';
        ctx.stroke();

        // Farbiger Wertbogen (Durchschnitt im Verhältnis zum Maximalwert)
        var endAng = Math.PI + fillRatio * Math.PI;
        ctx.beginPath();
        ctx.arc(gCx, gCy, gRadius, Math.PI, endAng);
        ctx.lineWidth = 24;
        ctx.strokeStyle = fillRatio > 0.66 ? '#00a65a' : (fillRatio > 0.33 ? '#f39c12' : '#3c8dbc');
        ctx.lineCap = 'round';
        ctx.stroke();
        ctx.lineCap = 'butt';

        // Tachonadel
        var needleAng = Math.PI + fillRatio * Math.PI;
        var nx = gCx + Math.cos(needleAng) * (gRadius - 18);
        var ny = gCy + Math.sin(needleAng) * (gRadius - 18);
        ctx.beginPath();
        ctx.moveTo(gCx, gCy);
        ctx.lineTo(nx, ny);
        ctx.strokeStyle = '#1e293b';
        ctx.lineWidth = 3;
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(gCx, gCy, 6, 0, Math.PI * 2);
        ctx.fillStyle = '#1e293b';
        ctx.fill();

        // KPI-Zahlen unterhalb/im Zentrum
        ctx.textAlign = 'center';
        ctx.fillStyle = '#0f172a';
        ctx.font = 'bold 18px sans-serif';
        ctx.fillText('Ø ' + ew_FormatChartNum(avgVal), gCx, gCy - 20);

        ctx.fillStyle = '#64748b';
        ctx.font = '12px sans-serif';
        ctx.fillText('Gesamtsumme: ' + ew_FormatChartNum(total) + '  |  Max: ' + ew_FormatChartNum(maxVal) + ' (' + String(labels[0] || '') + ')', gCx, gCy + 24);

        ctx.font = '11px sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText('0', gCx - gRadius - 6, gCy + 16);
        ctx.textAlign = 'right';
        ctx.fillText(ew_FormatChartNum(gaugeMax), gCx + gRadius + 10, gCy + 16);
    } else {
        // 9. SÄULENDIAGRAMM (BAR CHART) MIT GITTERLINIEN, ACHSEN & WERTANZEIGE
        var padLeft = 58;
        var padRight = 18;
        var padTop = 26;
        var padBottom = 56;
        var plotW = Math.max(100, cssW - padLeft - padRight);
        var plotH = Math.max(80, cssH - padTop - padBottom);

        var niceMax = (maxVal > 0 ? maxVal : 10) * 1.12;

        // Horizontale Gitterlinien (4 Stufen)
        var steps = 4;
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (var g = 0; g <= steps; g++) {
            var ratio = g / steps;
            var gy = padTop + plotH - (ratio * plotH);
            var gVal = niceMax * ratio;

            ctx.beginPath();
            ctx.moveTo(padLeft, gy);
            ctx.lineTo(padLeft + plotW, gy);
            ctx.strokeStyle = (g === 0) ? '#94a3b8' : '#e2e8f0';
            ctx.lineWidth = (g === 0) ? 1.5 : 1;
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(ew_FormatChartNum(gVal), padLeft - 8, gy);
        }

        // Säulen zeichnen
        var count = labels.length;
        var slotW = plotW / count;
        var barW = Math.max(10, Math.min(58, slotW * 0.62));

        for (var idx = 0; idx < count; idx++) {
            var valBar = Math.max(0, Number(values[idx]) || 0);
            var barH = (valBar / niceMax) * plotH;
            var bx = padLeft + (idx * slotW) + (slotW - barW) / 2;
            var by = padTop + plotH - barH;
            var barColor = EW_CHART_PALETTE[idx % EW_CHART_PALETTE.length];

            ctx.fillStyle = barColor;
            ctx.fillRect(bx, by, barW, Math.max(1, barH));

            // Zahlenwert über der Säule
            ctx.fillStyle = '#1e293b';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(ew_FormatChartNum(valBar), bx + barW / 2, Math.max(12, by - 4));

            // X-Achsen-Beschriftung
            var xLabel = String(labels[idx]);
            var maxChars = count > 8 ? 10 : 16;
            if (xLabel.length > maxChars) {
                xLabel = xLabel.substring(0, maxChars - 1) + '…';
            }
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(xLabel, bx + barW / 2, padTop + plotH + 8);
        }
    }
}

function ew_SwitchChartType(canvasId, newType, btnEl) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var raw = canvas.getAttribute('data-ew-chart');
    if (!raw) return;
    try {
        var cfg = JSON.parse(raw);
        var validTypes = ['bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge'];
        var t = String(newType || 'bar').toLowerCase();
        cfg.type = (validTypes.indexOf(t) !== -1) ? t : 'bar';
        canvas.setAttribute('data-ew-chart', JSON.stringify(cfg));
        ew_DrawChartCanvas(canvas);
        if (btnEl && btnEl.parentElement) {
            var btns = btnEl.parentElement.querySelectorAll('button');
            for (var i = 0; i < btns.length; i++) {
                btns[i].classList.remove('active');
            }
            btnEl.classList.add('active');
        }
    } catch (e) {}
}

function ew_DownloadChartPng(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    try {
        var tmp = document.createElement('canvas');
        tmp.width = canvas.width;
        tmp.height = canvas.height;
        var tctx = tmp.getContext('2d');
        tctx.fillStyle = '#ffffff';
        tctx.fillRect(0, 0, tmp.width, tmp.height);
        tctx.drawImage(canvas, 0, 0);
        var link = document.createElement('a');
        link.download = (canvasId || 'chart') + '.png';
        link.href = tmp.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (e) {}
}

function ew_InitCharts() {
    var canvases = document.querySelectorAll('canvas.ewChartCanvas');
    for (var i = 0; i < canvases.length; i++) {
        ew_DrawChartCanvas(canvases[i]);
    }
}

window.addEventListener('resize', function () {
    ew_InitCharts();
});

document.addEventListener('DOMContentLoaded', function () {
    ew_InitSignaturePads();
    ew_InitCharts();
PHP;
        if ($globalStartupScript !== '') {
            $code .= "\n    // PHPMacherNG Global Startup Script\n    " . $globalStartupScript . "\n";
        }
        $code .= <<<'PHP'
});
</script>
<?php
if (function_exists('Page_Unloaded')) {
    Page_Unloaded();
}
?>
</body>
</html>
PHP;
        return $code;
    }

    /**
     * Erzeugt index.php (Leitet auf die erste erlaubte Tabelle weiter oder auf login.php)
     */
    protected function buildIndex()
    {
        $defaultTable = isset($this->project['settings']['default_table']) ? $this->project['settings']['default_table'] : '';
        $tables = isset($this->project['tables']) && is_array($this->project['tables']) ? $this->project['tables'] : array();
        $tableCandidates = array();

        if ($defaultTable !== '' && isset($tables[$defaultTable]) && !empty($tables[$defaultTable]['enabled']) && !empty($tables[$defaultTable]['list_page'])) {
            $tableCandidates[$defaultTable] = self::tableSlug($defaultTable) . 'list.php';
        }
        foreach ($tables as $tName => $tCfg) {
            if (!empty($tCfg['enabled']) && !empty($tCfg['list_page']) && !isset($tableCandidates[$tName])) {
                $tableCandidates[$tName] = self::tableSlug($tName) . 'list.php';
            }
        }

        $code = "<?php\n";
        $code .= "require_once dirname(__FILE__) . '/phpfn14.php';\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "\$candidates = " . self::varExp($tableCandidates) . ";\n";
        $code .= "foreach (\$candidates as \$tName => \$url) {\n";
        $code .= "    if (!\$Security->IsEnabled() || \$Security->CanList(\$tName)) {\n";
        $code .= "        ew_Redirect(\$url);\n";
        $code .= "    }\n";
        $code .= "}\n";
        $code .= "if (\$Security->IsEnabled() && !\$Security->IsLoggedIn()) {\n";
        $code .= "    ew_Redirect('login.php');\n";
        $code .= "}\n";
        $code .= "\$PageTitle = 'Dashboard';\n";
        $code .= "require_once dirname(__FILE__) . '/header.php';\n";
        $code .= "echo '<div class=\"callout callout-info\"><h4>Willkommen!</h4>Für Ihre aktuelle Benutzerrolle sind keine Tabellen zur Listenansicht freigegeben oder es sind keine Tabellen aktiviert.</div>';\n";
        $code .= "require_once dirname(__FILE__) . '/footer.php';\n";
        return $code;
    }

    /**
     * Erzeugt login.php im klassischen PHPMacherNG (AdminLTE 2 Login-Box) Design
     */
    protected function buildLoginPage()
    {
        $loginClientScript  = $this->getGlobalClientScript('Login_Client_Script');
        $loginFormValScript = $this->getGlobalClientScript('Login_Form_CustomValidate');
        $loginStartupScript = $this->getGlobalClientScript('Login_Startup_Script');

        $code = <<<'PHP'
<?php
/**
 * PHPMacherNG Login Page (login.php)
 */
require_once dirname(__FILE__) . '/phpfn14.php';

$Security = ew_Security();
if ($Security->IsLoggedIn()) {
    ew_Redirect('index.php');
}

$username = '';
$returnUrl = ew_Get('return', 'index.php');
if (strpos($returnUrl, 'login.php') !== false || strpos($returnUrl, 'logout.php') !== false || $returnUrl === '') {
    $returnUrl = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(ew_Post('username', ''));
    $password = ew_Post('password', '');
    $returnUrl = ew_Post('return_url', 'index.php');

    if (!ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        ew_SetFailureMessage('Ungültiges Sicherheits-Token (CSRF). Bitte versuchen Sie es erneut.');
    } elseif ($Security->GetLockoutRemaining() > 0) {
        ew_SetFailureMessage('Zu viele Fehlversuche! Bitte warten Sie noch <strong>' . (int)$Security->GetLockoutRemaining() . ' Sekunden</strong>.');
    } elseif ($username === '' || $password === '') {
        ew_SetFailureMessage('Bitte geben Sie Benutzername und Passwort ein.');
    } else {
        $canLogin = true;
        if (function_exists('User_LoggingIn')) {
            $canLogin = (bool)User_LoggingIn($username, $password);
        }
        if ($canLogin && $Security->ValidateUser($username, $password)) {
            if (function_exists('User_LoggedIn')) {
                User_LoggedIn($Security->CurrentUserName);
            }
            ew_SetSuccessMessage('Willkommen zurück, <strong>' . ew_HtmlEncode($Security->CurrentUserName) . '</strong> (Rolle: ' . ew_HtmlEncode($Security->CurrentUserLevelName) . ')!');
            ew_Redirect($returnUrl);
        } elseif ($canLogin) {
            if (function_exists('User_LoginError')) {
                User_LoginError($username, $password);
            }
            $lockRem = $Security->GetLockoutRemaining();
            if ($lockRem > 0) {
                ew_SetFailureMessage('Zu viele Fehlversuche! Die Anmeldung ist für <strong>' . (int)$lockRem . ' Sekunden</strong> gesperrt.');
            } else {
                ew_SetFailureMessage('Ungültiger Benutzername oder falsches Passwort.');
            }
        }
    }
}

$PageTitle = 'Anmelden';
$PageSubTitle = 'Benutzer-Authentifizierung';
require_once dirname(__FILE__) . '/header.php';
?>

<div class="row" style="margin-top: 25px;">
    <div class="col-md-4 col-md-offset-4 col-sm-6 col-sm-offset-3">
        <div class="box box-primary" style="box-shadow: 0 2px 8px rgba(0,0,0,0.12);">
            <div class="box-header with-border text-center" style="padding:18px 15px;">
                <h3 class="box-title" style="font-size:20px; font-weight:600;">
                    <i class="fa fa-lock text-primary"></i> <?php echo ew_HtmlEncode(EW_PROJECT_NAME); ?> Login
                </h3>
                <p class="text-muted" style="margin:6px 0 0 0; font-size:13px;">Melden Sie sich an, um Ihre Sitzung zu starten</p>
            </div>
            <form id="flogin" method="post" action="login.php" onsubmit="return ew_LoginFormValidate(this);">
                <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
                <input type="hidden" name="return_url" value="<?php echo ew_HtmlEncode($returnUrl); ?>">
                <div class="box-body" style="padding:20px;">
                    <div class="form-group has-feedback">
                        <label for="username">Benutzername</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?php echo ew_HtmlEncode($username); ?>" placeholder="Benutzername eingeben" required autofocus>
                        <span class="glyphicon glyphicon-user form-control-feedback" style="top:25px;"></span>
                    </div>
                    <div class="form-group has-feedback">
                        <label for="password">Passwort</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Passwort eingeben" required>
                        <span class="glyphicon glyphicon-lock form-control-feedback" style="top:25px;"></span>
                    </div>
                </div>
                <div class="box-footer" style="padding:12px 20px; background:#f9fafc;">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-sign-in"></i> Anmelden</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
PHP;
        if ($loginClientScript !== '') {
            $code .= "// Login Page Client Script\n" . $loginClientScript . "\n";
        }
        $code .= "function ew_LoginFormValidate(fobj) {\n";
        if ($loginFormValScript !== '') {
            $code .= "    " . $loginFormValScript . "\n";
        }
        $code .= "    return true;\n}\n";
        if ($loginStartupScript !== '') {
            $code .= "document.addEventListener('DOMContentLoaded', function() {\n    " . $loginStartupScript . "\n});\n";
        }
        $code .= <<<'PHP'
</script>

<?php require_once dirname(__FILE__) . '/footer.php'; ?>
PHP;
        return $code;
    }

    /**
     * Erzeugt logout.php
     */
    protected function buildLogoutPage()
    {
        return <<<'PHP'
<?php
/**
 * PHPMacherNG Logout Page (logout.php)
 */
require_once dirname(__FILE__) . '/phpfn14.php';
$Security = ew_Security();
$canLogout = true;
if (function_exists('User_LoggingOut')) {
    $canLogout = (bool)User_LoggingOut($Security->CurrentUserName);
}
if ($canLogout) {
    $Security->Logout();
    ew_SetSuccessMessage('Sie wurden erfolgreich abgemeldet.');
    ew_Redirect('login.php');
} else {
    ew_Redirect('index.php');
}
PHP;
    }

    /**
     * Erzeugt userpriv.php (Interaktive Rechte-Matrix für User Levels zur Laufzeit wie in PHPMacherNG!)
     */
    protected function buildUserPrivPage()
    {
        $tableCaptions = array();
        $tables = isset($this->project['tables']) && is_array($this->project['tables']) ? $this->project['tables'] : array();
        foreach ($tables as $tName => $tCfg) {
            if (!empty($tCfg['enabled'])) {
                $tableCaptions[$tName] = isset($tCfg['caption']) ? $tCfg['caption'] : $tName;
            }
        }
        $customFiles = isset($this->project['custom_files']) && is_array($this->project['custom_files']) ? $this->project['custom_files'] : array();
        foreach ($customFiles as $cfKey => $cfCfg) {
            if (!empty($cfCfg['enabled'])) {
                $tableCaptions[$cfKey] = '[Custom File] ' . (isset($cfCfg['caption']) && $cfCfg['caption'] !== '' ? $cfCfg['caption'] : $cfKey);
            }
        }
        $tablesExport = self::varExp($tableCaptions);

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG Dynamic User Level Permissions Matrix (userpriv.php)\n */\n";
        $code .= "require_once dirname(__FILE__) . '/phpfn14.php';\n\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "if (!\$Security->IsSysAdmin()) {\n";
        $code .= "    ew_SetFailureMessage('Zugriff verweigert: Nur Administratoren dürfen Rollen-Berechtigungen verwalten.');\n";
        $code .= "    ew_Redirect('index.php');\n";
        $code .= "}\n\n";
        $code .= "\$conn = ew_Connect();\n";
        $code .= "\$ProjectTables = " . $tablesExport . ";\n";

        $code .= <<<'PHP'
$isDynamic = !empty($Security->Config['dynamic_user_levels']);
$lvlTable = isset($Security->Config['user_level_table']) ? $Security->Config['user_level_table'] : 'userlevels';
$privTable = isset($Security->Config['user_level_priv_table']) ? $Security->Config['user_level_priv_table'] : 'userlevelpermissions';
$safeLvlT = '`' . str_replace('`', '``', $lvlTable) . '`';
$safePrivT = '`' . str_replace('`', '``', $privTable) . '`';

// Alle verfügbaren Rollen laden
$levels = array();
if ($isDynamic && $conn->IsConnected()) {
    $dbLvls = @$conn->LoadRows('SELECT `userlevelid`, `userlevelname` FROM ' . $safeLvlT . ' ORDER BY `userlevelid` ASC');
    if (is_array($dbLvls) && !empty($dbLvls)) {
        foreach ($dbLvls as $r) {
            $levels[(int)$r['userlevelid']] = $r['userlevelname'];
        }
    }
}
if (empty($levels)) {
    $staticLvls = isset($Security->Config['static_user_levels']) ? $Security->Config['static_user_levels'] : array();
    foreach ($staticLvls as $sl) {
        $levels[(int)$sl['id']] = $sl['name'];
    }
}

$selectedLevel = isset($_GET['userlevelid']) ? (int)ew_Get('userlevelid', 0) : (isset($_POST['userlevelid']) ? (int)ew_Post('userlevelid', 0) : 0);
if (!isset($levels[$selectedLevel])) {
    $lKeys = array_keys($levels);
    $selectedLevel = !empty($lKeys) ? $lKeys[0] : 0;
}

// Neue Rolle anlegen (falls Dynamic User Levels aktiv)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ew_Post('action_type', '') === 'add_role' && $isDynamic) {
    if (ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        $newId = (int)ew_Post('new_role_id', 0);
        $newName = trim(ew_Post('new_role_name', ''));
        if ($newId <= 0 || isset($levels[$newId])) {
            ew_SetFailureMessage('Bitte geben Sie eine noch nicht vergebene Rollen-ID größer als 0 an.');
        } elseif ($newName === '') {
            ew_SetFailureMessage('Bitte geben Sie einen Rollen-Namen ein.');
        } else {
            if ($conn->Execute('INSERT INTO ' . $safeLvlT . ' (`userlevelid`, `userlevelname`) VALUES (?, ?)', array($newId, $newName))) {
                ew_SetSuccessMessage('Neue Rolle <strong>' . ew_HtmlEncode($newName) . '</strong> (ID ' . $newId . ') erfolgreich angelegt.');
                ew_Redirect('userpriv.php?userlevelid=' . $newId);
            }
        }
    }
}

// Berechtigungs-Matrix speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ew_Post('action_type', '') === 'save_priv' && $isDynamic) {
    if (!ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        ew_SetFailureMessage('Ungültiges Sicherheits-Token (CSRF).');
    } elseif ($selectedLevel === -1) {
        ew_SetFailureMessage('Die Administrator-Rolle (-1) besitzt immer Vollzugriff und kann nicht eingeschränkt werden.');
    } else {
        $postedPrivs = isset($_POST['priv']) && is_array($_POST['priv']) ? $_POST['priv'] : array();
        foreach ($ProjectTables as $tName => $tCap) {
            $bits = 0;
            if (isset($postedPrivs[$tName]) && is_array($postedPrivs[$tName])) {
                foreach ($postedPrivs[$tName] as $bVal) {
                    $bits |= (int)$bVal;
                }
            }
            $conn->Execute(
                'REPLACE INTO ' . $safePrivT . ' (`tablename`, `userlevelid`, `permission`) VALUES (?, ?, ?)',
                array($tName, $selectedLevel, $bits)
            );
        }
        ew_SetSuccessMessage('Berechtigungen für Rolle <strong>' . ew_HtmlEncode($levels[$selectedLevel]) . '</strong> wurden erfolgreich in der Datenbank gespeichert.');
        ew_Redirect('userpriv.php?userlevelid=' . $selectedLevel);
    }
}

// Aktuelle Berechtigungen für die gewählte Rolle ermitteln
$currentPrivs = array();
$staticMap = isset($Security->Config['static_permissions']) ? $Security->Config['static_permissions'] : array();
foreach ($ProjectTables as $tName => $tCap) {
    $lvlKey = (string)$selectedLevel;
    $currentPrivs[$tName] = ($selectedLevel === -1) ? 127 : (isset($staticMap[$tName][$lvlKey]) ? (int)$staticMap[$tName][$lvlKey] : 0);
}

if ($isDynamic && $conn->IsConnected() && $selectedLevel !== -1) {
    $dbPrivs = @$conn->LoadRows('SELECT `tablename`, `permission` FROM ' . $safePrivT . ' WHERE `userlevelid` = ?', array($selectedLevel));
    if (is_array($dbPrivs)) {
        foreach ($dbPrivs as $pr) {
            $cleanT = str_replace(EW_PROJECT_ID, '', $pr['tablename']);
            if (isset($ProjectTables[$cleanT])) {
                $currentPrivs[$cleanT] = (int)$pr['permission'];
            }
        }
    }
}

$PageTitle = 'Rollen & Berechtigungen (User Level Permissions)';
$PageSubTitle = $levels[$selectedLevel];
require_once dirname(__FILE__) . '/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-users"></i> Benutzerrollen (User Levels)</h3>
            </div>
            <div class="box-body no-padding">
                <ul class="nav nav-pills nav-stacked">
                    <?php foreach ($levels as $lid => $lname): ?>
                    <li class="<?php echo ($lid === $selectedLevel) ? 'active' : ''; ?>">
                        <a href="userpriv.php?userlevelid=<?php echo (int)$lid; ?>">
                            <i class="fa <?php echo ($lid === -1) ? 'fa-star text-yellow' : (($lid === -2) ? 'fa-user-secret' : 'fa-id-badge'); ?>"></i>
                            <strong><?php echo ew_HtmlEncode($lname); ?></strong>
                            <span class="pull-right badge"><?php echo (int)$lid; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if ($isDynamic): ?>
            <div class="box-footer">
                <form method="post" action="userpriv.php">
                    <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
                    <input type="hidden" name="action_type" value="add_role">
                    <label style="font-size:12px;">Neue Rolle anlegen:</label>
                    <div class="row" style="margin-bottom:6px;">
                        <div class="col-xs-4">
                            <input type="number" name="new_role_id" class="form-control input-sm" placeholder="ID (z.B. 3)" min="1" required>
                        </div>
                        <div class="col-xs-8">
                            <input type="text" name="new_role_name" class="form-control input-sm" placeholder="Rollenname" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm btn-block"><i class="fa fa-plus"></i> Rolle hinzufügen</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-8">
        <form method="post" action="userpriv.php?userlevelid=<?php echo (int)$selectedLevel; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
            <input type="hidden" name="action_type" value="save_priv">
            <input type="hidden" name="userlevelid" value="<?php echo (int)$selectedLevel; ?>">

            <div class="ewGrid">
                <div class="ewGridUpperPanel">
                    <div class="ewToolbar">
                        <div>
                            <h4 style="margin:0;"><i class="fa fa-shield text-primary"></i> Berechtigungs-Matrix für: <strong><?php echo ew_HtmlEncode($levels[$selectedLevel]); ?></strong> (ID: <?php echo (int)$selectedLevel; ?>)</h4>
                        </div>
                        <?php if ($selectedLevel !== -1 && $isDynamic): ?>
                        <div>
                            <button type="button" class="btn btn-default btn-xs" onclick="var cbs=document.querySelectorAll('.ewPrivCheck'); for(var i=0;i<cbs.length;i++) cbs[i].checked=true;">Alle auswählen</button>
                            <button type="button" class="btn btn-default btn-xs" onclick="var cbs=document.querySelectorAll('.ewPrivCheck'); for(var i=0;i<cbs.length;i++) cbs[i].checked=false;">Keine</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover ewTable">
                        <thead>
                            <tr>
                                <th>Tabelle / Modul</th>
                                <th class="text-center">Add (Hinzufügen)</th>
                                <th class="text-center">Delete (Löschen)</th>
                                <th class="text-center">Edit (Bearbeiten)</th>
                                <th class="text-center">List (Liste)</th>
                                <th class="text-center">View (Details)</th>
                                <th class="text-center">Search (Suche)</th>
                                <th class="text-center">Admin (Alle Zeilen)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $permCols = array(
                                EW_ALLOW_ADD => 'Add',
                                EW_ALLOW_DELETE => 'Delete',
                                EW_ALLOW_EDIT => 'Edit',
                                EW_ALLOW_LIST => 'List',
                                EW_ALLOW_VIEW => 'View',
                                EW_ALLOW_SEARCH => 'Search',
                                EW_ALLOW_ADMIN => 'Admin'
                            );
                            foreach ($ProjectTables as $tName => $tCap):
                                $pVal = isset($currentPrivs[$tName]) ? (int)$currentPrivs[$tName] : 0;
                                $disabled = ($selectedLevel === -1 || !$isDynamic) ? ' disabled' : '';
                            ?>
                            <tr>
                                <td><strong><?php echo ew_HtmlEncode($tCap); ?></strong> <small class="text-muted">(<?php echo ew_HtmlEncode($tName); ?>)</small></td>
                                <?php foreach ($permCols as $bit => $lbl):
                                    $chk = (($pVal & $bit) === $bit) ? ' checked' : '';
                                ?>
                                <td class="text-center">
                                    <input type="checkbox" class="ewPrivCheck" name="priv[<?php echo ew_HtmlEncode($tName); ?>][]" value="<?php echo (int)$bit; ?>"<?php echo $chk . $disabled; ?>>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="ewGridLowerPanel">
                    <?php if ($selectedLevel === -1): ?>
                        <span class="text-muted"><i class="fa fa-info-circle"></i> Die System-Administrator-Rolle (-1) besitzt uneingeschränkte Vollberechtigung auf alle Tabellen.</span>
                    <?php elseif (!$isDynamic): ?>
                        <span class="text-muted"><i class="fa fa-info-circle"></i> Statische Rollen sind aktiv. Um Rechte zur Laufzeit in der Datenbank zu speichern, aktivieren Sie <strong>Dynamic User Levels</strong> im Studio.</span>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Berechtigungen speichern</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/footer.php'; ?>
PHP;
        return $code;
    }

    /**
     * Erzeugt eine benutzerdefinierte PHPMacherNG Datei (Custom File)
     * Unterstützt sowohl "Include common files" (mit Header, Sidebar, Footer, Security & DbHelper)
     * als auch eigenständige Dateien (z.B. für eigene AJAX/JSON/CSV-Endpunkte mit DbHelper).
     */
    protected function buildCustomFilePage($cfCfg, $cfFileName)
    {
        $caption = isset($cfCfg['caption']) && trim($cfCfg['caption']) !== '' ? trim($cfCfg['caption']) : $cfFileName;
        $includeCommon = isset($cfCfg['include_common']) ? (bool)$cfCfg['include_common'] : true;
        $rawContent = isset($cfCfg['content']) ? (string)$cfCfg['content'] : '';

        $captionExport = self::varExp($caption);
        $fileKeyExport = self::varExp($cfFileName);

        if ($includeCommon) {
            $code = "<?php\n";
            $code .= "/**\n * PHPMacherNG Custom File (" . $cfFileName . ")\n * Include common files: ENABLED\n */\n";
            $code .= "require_once dirname(__FILE__) . '/phpfn14.php';\n\n";
            $code .= "\$Security = ew_Security();\n";
            $code .= "\$Security->CheckPagePermission(" . $fileKeyExport . ", EW_ALLOW_LIST);\n";
            $code .= "\$db = &DbHelper();\n";
            $code .= "\$conn = &Conn();\n\n";
            $code .= "\$PageTitle = " . $captionExport . ";\n";
            $code .= "\$PageSubTitle = 'Custom File';\n";
            $code .= "require_once dirname(__FILE__) . '/header.php';\n";
            $code .= "?>\n";
            $code .= "<div class=\"ewCustomFileContent\">\n";
            $code .= $rawContent . "\n";
            $code .= "</div>\n";
            $code .= "<?php require_once dirname(__FILE__) . '/footer.php'; ?>\n";
            return $code;
        } else {
            $code = "<?php\n";
            $code .= "/**\n * PHPMacherNG Custom File (" . $cfFileName . ")\n * Include common files: DISABLED (Standalone / API Mode mit DbHelper)\n */\n";
            $code .= "require_once dirname(__FILE__) . '/phpfn14.php';\n";
            $code .= "\$Security = ew_Security();\n";
            $code .= "\$db = &DbHelper();\n";
            $code .= "\$conn = &Conn();\n";
            $code .= "?>";
            // Falls $rawContent direkt mit <?php beginnt, nahtlos zusammenfügen
            if (strpos(ltrim($rawContent), '<?php') === 0) {
                $trimmed = substr(ltrim($rawContent), 5);
                $code = substr($code, 0, -2) . "\n" . $trimmed . "\n";
            } else {
                $code .= "\n" . $rawContent . "\n";
            }
            return $code;
        }
    }

    /**
     * Erzeugt <table_slug>info.php (Tabellen-Metadatenklasse wie in PHPMacherNG)
     */
    protected function buildTableInfo($tCfg)
    {
        $tName = $tCfg['name'];
        $slug = self::tableSlug($tName);
        $className = 'cTable_' . $slug;
        $configExport = self::varExp($tCfg);

        $code = "<?php\n";
        $code .= "/**\n";
        $code .= " * PHPMacherNG Table Info Class (" . $slug . "info.php)\n";
        $code .= " * Tabelle: " . $tName . "\n";
        $code .= " */\n\n";
        $code .= "require_once dirname(__FILE__) . '/phpfn14.php';\n\n";
        $code .= "class " . $className . "\n{\n";
        $code .= "    public \$TableName = " . self::varExp($tName) . ";\n";
        $code .= "    public \$TableSlug = " . self::varExp($slug) . ";\n";
        $code .= "    public \$TableCaption = " . self::varExp($tCfg['caption']) . ";\n";
        $code .= "    public \$TableType = " . self::varExp($tCfg['type']) . ";\n";
        $code .= "    public \$PrimaryKeys = " . self::varExp($tCfg['primary_keys']) . ";\n";
        $code .= "    public \$Fields = array();\n";
        $code .= "    public \$Config = array();\n\n";
        $code .= "    public function __construct()\n    {\n";
        $code .= "        \$this->Config = " . $configExport . ";\n";
        $code .= "        \$this->Fields = \$this->Config['fields'];\n";
        $code .= "    }\n\n";
        $code .= "    public function SqlFrom()\n    {\n";
        $code .= "        return '`' . str_replace('`', '``', \$this->TableName) . '`';\n";
        $code .= "    }\n\n";
        $code .= "    public function SqlSelect()\n    {\n";
        $code .= "        return 'SELECT * FROM ' . \$this->SqlFrom();\n";
        $code .= "    }\n\n";
        $code .= "    public function SqlCount()\n    {\n";
        $code .= "        return 'SELECT COUNT(*) FROM ' . \$this->SqlFrom();\n";
        $code .= "    }\n\n";
        $code .= "    /**\n     * Erzeugt einen zusammengesetzten Primärschlüssel-String für URLs/Checkboxen\n     */\n";
        $code .= "    public function GetRowKey(\$row)\n    {\n";
        $code .= "        \$parts = array();\n";
        $code .= "        foreach (\$this->PrimaryKeys as \$pk) {\n";
        $code .= "            \$parts[] = isset(\$row[\$pk]) ? (string)\$row[\$pk] : '';\n";
        $code .= "        }\n";
        $code .= "        return implode('|', \$parts);\n";
        $code .= "    }\n\n";
        $evRecordSetSelecting = $this->getTableServerEvent($tName, 'Recordset_Selecting');
        $evRowRendering       = $this->getTableServerEvent($tName, 'Row_Rendering');
        $evRowRendered        = $this->getTableServerEvent($tName, 'Row_Rendered');
        $evRowInserting       = $this->getTableServerEvent($tName, 'Row_Inserting');
        $evRowInserted        = $this->getTableServerEvent($tName, 'Row_Inserted');
        $evRowUpdating        = $this->getTableServerEvent($tName, 'Row_Updating');
        $evRowUpdated         = $this->getTableServerEvent($tName, 'Row_Updated');
        $evRowDeleting        = $this->getTableServerEvent($tName, 'Row_Deleting');
        $evRowDeleted         = $this->getTableServerEvent($tName, 'Row_Deleted');
        $evFormCustomValidate = $this->getTableServerEvent($tName, 'Form_CustomValidate');

        $code .= "    /**\n     * Zerlegt einen Key-String zurück in ein Array für WHERE-Bedingungen\n     */\n";
        $code .= "    public function BuildKeyWhere(\$keyStr, &\$params)\n    {\n";
        $code .= "        \$parts = explode('|', (string)\$keyStr);\n";
        $code .= "        \$conds = array();\n";
        $code .= "        foreach (\$this->PrimaryKeys as \$idx => \$pk) {\n";
        $code .= "            \$col = '`' . str_replace('`', '``', \$pk) . '`';\n";
        $code .= "            \$conds[] = \$col . ' = ?';\n";
        $code .= "            \$params[] = isset(\$parts[\$idx]) ? \$parts[\$idx] : '';\n";
        $code .= "        }\n";
        $code .= "        return '(' . implode(' AND ', \$conds) . ')';\n";
        $code .= "    }\n\n";

        $code .= "    // ========================================================================\n";
        $code .= "    // PHPMacherNG Table Server Events (Projektspezifisch für " . $tName . ")\n";
        $code .= "    // ========================================================================\n\n";
        $code .= "    public function Recordset_Selecting(&\$filter)\n    {\n        " . ($evRecordSetSelecting !== '' ? $evRecordSetSelecting : '// Modify $filter (SQL WHERE condition) dynamically') . "\n    }\n\n";
        $code .= "    public function Row_Rendering(&\$rs)\n    {\n        " . ($evRowRendering !== '' ? $evRowRendering : '// Called before rendering a row') . "\n    }\n\n";
        $code .= "    public function Row_Rendered(&\$rs, &\$rowAttrs)\n    {\n        " . ($evRowRendered !== '' ? $evRowRendered : '// Called after row values are prepared') . "\n    }\n\n";
        $code .= "    public function Row_Inserting(\$rsold, &\$rsnew)\n    {\n        " . ($evRowInserting !== '' ? $evRowInserting : '// Return false to cancel insert') . "\n        return true;\n    }\n\n";
        $code .= "    public function Row_Inserted(\$rsold, &\$rsnew)\n    {\n        " . ($evRowInserted !== '' ? $evRowInserted : '// Called after successful insert') . "\n    }\n\n";
        $code .= "    public function Row_Updating(\$rsold, &\$rsnew)\n    {\n        " . ($evRowUpdating !== '' ? $evRowUpdating : '// Return false to cancel update') . "\n        return true;\n    }\n\n";
        $code .= "    public function Row_Updated(\$rsold, &\$rsnew)\n    {\n        " . ($evRowUpdated !== '' ? $evRowUpdated : '// Called after successful update') . "\n    }\n\n";
        $code .= "    public function Row_Deleting(&\$rs)\n    {\n        " . ($evRowDeleting !== '' ? $evRowDeleting : '// Return false to cancel delete') . "\n        return true;\n    }\n\n";
        $code .= "    public function Row_Deleted(&\$rs)\n    {\n        " . ($evRowDeleted !== '' ? $evRowDeleted : '// Called after successful delete') . "\n    }\n\n";
        $code .= "    public function Form_CustomValidate(&\$customError)\n    {\n        " . ($evFormCustomValidate !== '' ? $evFormCustomValidate : '// Return false and set $customError to fail validation') . "\n        return true;\n    }\n";
        $code .= "}\n";

        return $code;
    }

    /**
     * Erzeugt <table_slug>list.php (Listenansicht mit Quick Search, Extended Search, Sortierung, Paging, Multi-Delete, Multi-Update, Master/Detail & Security)
     */
    protected function buildListPage($tCfg)
    {
        $tName = $tCfg['name'];
        $slug = self::tableSlug($tName);
        $className = 'cTable_' . $slug;

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG List Page (" . $slug . "list.php)\n */\n";
        $code .= "require_once dirname(__FILE__) . '/" . $slug . "info.php';\n\n";
        $code .= "\$Table = new " . $className . "();\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "\$Security->CheckPagePermission(\$Table->TableName, EW_ALLOW_LIST);\n";
        $code .= "\$conn = ew_Connect();\n";
        $code .= "\$sessionPrefix = EW_PROJECT_ID . '_' . \$Table->TableSlug . '_';\n\n";

        $code .= <<<'PHP'
// 1. Master/Detail-Filter & Suchparameter / Reset verarbeiten
$cmd = strtolower(ew_Get('cmd', ''));
if ($cmd === 'resetmaster') {
    $_SESSION[$sessionPrefix . 'master'] = array();
    $_SESSION[$sessionPrefix . 'page'] = 1;
    ew_Redirect($Table->TableSlug . 'list.php');
}

if (isset($_GET['showmaster'])) {
    $showMasterSlug = preg_replace('/[^a-zA-Z0-9_]/', '', ew_Get('showmaster', ''));
    if ($showMasterSlug === '') {
        $_SESSION[$sessionPrefix . 'master'] = array();
    } else {
        $masterInfoFile = dirname(__FILE__) . '/' . $showMasterSlug . 'info.php';
        $masterClass = 'cTable_' . $showMasterSlug;
        if (is_file($masterInfoFile)) {
            require_once $masterInfoFile;
            if (class_exists($masterClass)) {
                $mObj = new $masterClass();
                $mDetails = isset($mObj->Config['master_details']) && is_array($mObj->Config['master_details'])
                    ? $mObj->Config['master_details']
                    : array();
                foreach ($mDetails as $mdRel) {
                    $relDetailTable = isset($mdRel['detail_table']) ? $mdRel['detail_table'] : '';
                    if ($relDetailTable === $Table->TableName) {
                        $mField = isset($mdRel['master_field']) ? $mdRel['master_field'] : '';
                        $dField = isset($mdRel['detail_field']) ? $mdRel['detail_field'] : '';
                        $fkVal = isset($_GET['fk_' . $mField]) ? ew_Get('fk_' . $mField, '') : ew_Get('fk_' . $dField, '');
                        if ($mField !== '' && $dField !== '' && $fkVal !== '') {
                            $_SESSION[$sessionPrefix . 'master'] = array(
                                'master_table'   => $mObj->TableName,
                                'master_slug'    => $mObj->TableSlug,
                                'master_caption' => $mObj->TableCaption,
                                'master_field'   => $mField,
                                'detail_field'   => $dField,
                                'fk_value'       => $fkVal
                            );
                            $_SESSION[$sessionPrefix . 'page'] = 1;
                        }
                        break;
                    }
                }
            }
        }
    }
}

$activeMaster = isset($_SESSION[$sessionPrefix . 'master']) && is_array($_SESSION[$sessionPrefix . 'master'])
    ? $_SESSION[$sessionPrefix . 'master']
    : array();
$masterRecordRow = null;
$masterTableObj = null;
if (!empty($activeMaster['master_slug']) && !empty($activeMaster['master_field']) && !empty($activeMaster['detail_field'])) {
    $mInfoFile = dirname(__FILE__) . '/' . $activeMaster['master_slug'] . 'info.php';
    $mClass = 'cTable_' . $activeMaster['master_slug'];
    if (is_file($mInfoFile)) {
        require_once $mInfoFile;
        if (class_exists($mClass)) {
            $masterTableObj = new $mClass();
            $mCol = '`' . str_replace('`', '``', $activeMaster['master_field']) . '`';
            $masterRecordRow = $conn->LoadRow($masterTableObj->SqlSelect() . ' WHERE ' . $mCol . ' = ?', array($activeMaster['fk_value']));
        }
    }
}

if ($cmd === 'reset') {
    $_SESSION[$sessionPrefix . 'psearch'] = '';
    $_SESSION[$sessionPrefix . 'psearchtype'] = '';
    $_SESSION[$sessionPrefix . 'advsearch'] = array();
    $_SESSION[$sessionPrefix . 'page'] = 1;
    ew_Redirect($Table->TableSlug . 'list.php');
} elseif ($cmd === 'resetall') {
    $_SESSION[$sessionPrefix . 'psearch'] = '';
    $_SESSION[$sessionPrefix . 'psearchtype'] = '';
    $_SESSION[$sessionPrefix . 'advsearch'] = array();
    $_SESSION[$sessionPrefix . 'orderby'] = '';
    $_SESSION[$sessionPrefix . 'orderdir'] = '';
    $_SESSION[$sessionPrefix . 'master'] = array();
    $_SESSION[$sessionPrefix . 'page'] = 1;
    ew_Redirect($Table->TableSlug . 'list.php');
}

if (isset($_GET['psearch'])) {
    $_SESSION[$sessionPrefix . 'psearch'] = trim(ew_Get('psearch', ''));
    $_SESSION[$sessionPrefix . 'psearchtype'] = ew_Get('psearchtype', '');
    $_SESSION[$sessionPrefix . 'page'] = 1;
}

if (isset($_GET['adv_submit'])) {
    $adv = array();
    foreach ($Table->Fields as $fName => $fMeta) {
        if (!empty($fMeta['extended_search'])) {
            $val = trim(ew_Get('x_' . $fName, ''));
            if ($val !== '') {
                $adv[$fName] = $val;
            }
        }
    }
    $_SESSION[$sessionPrefix . 'advsearch'] = $adv;
    $_SESSION[$sessionPrefix . 'page'] = 1;
}

$psearch = isset($_SESSION[$sessionPrefix . 'psearch']) ? $_SESSION[$sessionPrefix . 'psearch'] : '';
$psearchType = isset($_SESSION[$sessionPrefix . 'psearchtype']) ? $_SESSION[$sessionPrefix . 'psearchtype'] : '';
$advSearch = isset($_SESSION[$sessionPrefix . 'advsearch']) && is_array($_SESSION[$sessionPrefix . 'advsearch']) ? $_SESSION[$sessionPrefix . 'advsearch'] : array();

// 2. Sortierung verarbeiten
if (isset($_GET['order'])) {
    $orderField = ew_Get('order', '');
    if (isset($Table->Fields[$orderField]) && !empty($Table->Fields[$orderField]['allow_sort'])) {
        $curOrder = isset($_SESSION[$sessionPrefix . 'orderby']) ? $_SESSION[$sessionPrefix . 'orderby'] : '';
        $curDir = isset($_SESSION[$sessionPrefix . 'orderdir']) ? $_SESSION[$sessionPrefix . 'orderdir'] : 'ASC';
        if ($curOrder === $orderField) {
            $_SESSION[$sessionPrefix . 'orderdir'] = ($curDir === 'ASC') ? 'DESC' : 'ASC';
        } else {
            $_SESSION[$sessionPrefix . 'orderby'] = $orderField;
            $_SESSION[$sessionPrefix . 'orderdir'] = 'ASC';
        }
    }
}

$orderBy = isset($_SESSION[$sessionPrefix . 'orderby']) && $_SESSION[$sessionPrefix . 'orderby'] !== ''
    ? $_SESSION[$sessionPrefix . 'orderby']
    : (isset($Table->Config['default_order_by']) ? $Table->Config['default_order_by'] : '');
$orderDir = isset($_SESSION[$sessionPrefix . 'orderdir']) && $_SESSION[$sessionPrefix . 'orderdir'] !== ''
    ? $_SESSION[$sessionPrefix . 'orderdir']
    : (isset($Table->Config['default_order_dir']) ? $Table->Config['default_order_dir'] : 'DESC');
if (strtoupper($orderDir) !== 'ASC' && strtoupper($orderDir) !== 'DESC') {
    $orderDir = 'ASC';
}

// 3. Paging / Seitengröße verarbeiten
$defaultRecPerPage = (!empty($Table->Config['records_per_page']) && (int)$Table->Config['records_per_page'] > 0)
    ? (int)$Table->Config['records_per_page']
    : EW_RECORDS_PER_PAGE;

if (isset($_GET['recperpage'])) {
    $rpp = ew_Get('recperpage', '');
    $_SESSION[$sessionPrefix . 'recperpage'] = ($rpp === 'ALL') ? 'ALL' : max(1, (int)$rpp);
    $_SESSION[$sessionPrefix . 'page'] = 1;
}
$recPerPage = isset($_SESSION[$sessionPrefix . 'recperpage']) ? $_SESSION[$sessionPrefix . 'recperpage'] : $defaultRecPerPage;

if (isset($_GET['page'])) {
    $_SESSION[$sessionPrefix . 'page'] = max(1, (int)ew_Get('page', 1));
}
$currentPage = isset($_SESSION[$sessionPrefix . 'page']) ? max(1, (int)$_SESSION[$sessionPrefix . 'page']) : 1;

// 4. WHERE-Klausel für Master/Detail-Filter, User ID Security, Quick Search und Extended Search aufbauen
$whereParts = array();
$sqlParams = array();

if (!empty($activeMaster['detail_field']) && isset($Table->Fields[$activeMaster['detail_field']])) {
    $dCol = '`' . str_replace('`', '``', $activeMaster['detail_field']) . '`';
    $whereParts[] = $dCol . ' = ?';
    $sqlParams[] = $activeMaster['fk_value'];
}

$uidWhere = $Security->BuildUserIDFilter($Table, $sqlParams);
if ($uidWhere !== '') {
    $whereParts[] = $uidWhere;
}

$canSearch = $Security->CanSearch($Table->TableName);

if ($canSearch && $psearch !== '' && !empty($Table->Config['quick_search'])) {
    $searchableCols = array();
    foreach ($Table->Fields as $fName => $fMeta) {
        if (!empty($fMeta['search'])) {
            $searchableCols[] = '`' . str_replace('`', '``', $fName) . '`';
        }
    }
    if (!empty($searchableCols)) {
        if ($psearchType === '=') {
            // Exakte Phrase
            $sub = array();
            foreach ($searchableCols as $col) {
                $sub[] = $col . ' LIKE ?';
                $sqlParams[] = '%' . $psearch . '%';
            }
            $whereParts[] = '(' . implode(' OR ', $sub) . ')';
        } else {
            // Wörter aufteilen (AND oder OR)
            $words = preg_split('/\s+/', $psearch);
            $wordConds = array();
            foreach ($words as $w) {
                if ($w === '') continue;
                $sub = array();
                foreach ($searchableCols as $col) {
                    $sub[] = $col . ' LIKE ?';
                    $sqlParams[] = '%' . $w . '%';
                }
                $wordConds[] = '(' . implode(' OR ', $sub) . ')';
            }
            if (!empty($wordConds)) {
                $glue = ($psearchType === 'AND') ? ' AND ' : ' OR ';
                $whereParts[] = '(' . implode($glue, $wordConds) . ')';
            }
        }
    }
}

if ($canSearch) {
    foreach ($advSearch as $fName => $fVal) {
        if (!isset($Table->Fields[$fName])) continue;
        $fMeta = $Table->Fields[$fName];
        $col = '`' . str_replace('`', '``', $fName) . '`';
        if ($fMeta['ew_type'] === 'STRING' || $fMeta['ew_type'] === 'MEMO') {
            $whereParts[] = $col . ' LIKE ?';
            $sqlParams[] = '%' . $fVal . '%';
        } else {
            $whereParts[] = $col . ' = ?';
            $sqlParams[] = $fVal;
        }
    }
}

$customFilter = '';
$Table->Recordset_Selecting($customFilter);
if (trim($customFilter) !== '') {
    $whereParts[] = '(' . $customFilter . ')';
}

$whereSql = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';

// 5. Gesamtanzahl & Datensätze abfragen
$totalRecords = (int)$conn->ExecuteScalar($Table->SqlCount() . $whereSql, $sqlParams);

if ($recPerPage === 'ALL') {
    $totalPages = 1;
    $currentPage = 1;
    $offset = 0;
    $limitSql = '';
} else {
    $rppInt = max(1, (int)$recPerPage);
    $totalPages = max(1, (int)ceil($totalRecords / $rppInt));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $rppInt;
    $limitSql = ' LIMIT ' . (int)$rppInt . ' OFFSET ' . (int)$offset;
}

$orderSql = '';
if ($orderBy !== '' && isset($Table->Fields[$orderBy])) {
    $orderSql = ' ORDER BY `' . str_replace('`', '``', $orderBy) . '` ' . $orderDir;
}

$rows = $conn->LoadRows($Table->SqlSelect() . $whereSql . $orderSql . $limitSql, $sqlParams);
if (!is_array($rows)) {
    $rows = array();
}

$startRec = ($totalRecords > 0) ? ($offset + 1) : 0;
$endRec = min($offset + count($rows), $totalRecords);

// Sichtbare List-Spalten bestimmen
$listFields = array();
$extSearchFields = array();
foreach ($Table->Fields as $fName => $fMeta) {
    if (!empty($fMeta['list'])) {
        $listFields[$fName] = $fMeta;
    }
    if (!empty($fMeta['extended_search'])) {
        $extSearchFields[$fName] = $fMeta;
    }
}

// 6. Master/Detail-Unterlisten (wenn diese Tabelle selbst Master für andere Detail-Tabellen ist)
$masterDetails = array();
$hasPreviewRow = false;
if (!empty($Table->Config['master_details']) && is_array($Table->Config['master_details'])) {
    foreach ($Table->Config['master_details'] as $mdCfg) {
        $dTable = isset($mdCfg['detail_table']) ? $mdCfg['detail_table'] : '';
        $mField = isset($mdCfg['master_field']) ? $mdCfg['master_field'] : '';
        $dField = isset($mdCfg['detail_field']) ? $mdCfg['detail_field'] : '';
        if ($dTable === '' || $mField === '' || $dField === '') continue;
        if (!$Security->CanList($dTable)) continue;

        $dSlug = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $dTable));
        $dCaption = isset($mdCfg['caption']) && trim($mdCfg['caption']) !== '' ? trim($mdCfg['caption']) : $dTable;
        $dInfoFile = dirname(__FILE__) . '/' . $dSlug . 'info.php';
        $dClass = 'cTable_' . $dSlug;
        if (is_file($dInfoFile)) {
            require_once $dInfoFile;
            if (class_exists($dClass)) {
                $dObj = new $dClass();
                $dSlug = $dObj->TableSlug;
                if (empty($mdCfg['caption'])) {
                    $dCaption = $dObj->TableCaption;
                }
            }
        }
        $mdEntry = array(
            'detail_table' => $dTable,
            'detail_slug'  => $dSlug,
            'master_field' => $mField,
            'detail_field' => $dField,
            'caption'      => $dCaption,
            'preview_row'  => !empty($mdCfg['preview_row']),
            'detail_count' => !empty($mdCfg['detail_count'])
        );
        if ($mdEntry['preview_row']) {
            $hasPreviewRow = true;
        }
        $masterDetails[] = $mdEntry;
    }
}

$hasPk = !empty($Table->PrimaryKeys);
$canAdd = $hasPk && !empty($Table->Config['add_page']) && $Security->CanAdd($Table->TableName);
$canView = $hasPk && !empty($Table->Config['view_page']) && $Security->CanView($Table->TableName);
$canEdit = $hasPk && !empty($Table->Config['edit_page']) && $Security->CanEdit($Table->TableName);
$canDelete = $hasPk && !empty($Table->Config['delete_page']) && $Security->CanDelete($Table->TableName);
$canMultiDelete = $hasPk && !empty($Table->Config['multi_delete']) && $Security->CanDelete($Table->TableName);
$canMultiUpdate = $hasPk && !empty($Table->Config['multi_update']) && $Security->CanEdit($Table->TableName);
$hasMultiActions = ($canMultiDelete || $canMultiUpdate);
$hasActionColumn = ($canView || $canEdit || $canDelete || !empty($masterDetails));

$addUrl = $Table->TableSlug . 'add.php';
if (!empty($activeMaster['detail_field']) && isset($activeMaster['fk_value'])) {
    $addUrl .= '?fk_' . urlencode($activeMaster['detail_field']) . '=' . urlencode($activeMaster['fk_value']);
}

$PageTitle = $Table->TableCaption;
$PageSubTitle = !empty($activeMaster) ? ('Detail-Liste zu ' . $activeMaster['master_caption']) : 'Listenansicht';
require_once dirname(__FILE__) . '/header.php';
PHP;
        $listClientScript = $this->getTableClientScript($tCfg['name'], 'List_Client_Script');
        if ($listClientScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Client Script (List Page)\n" . $listClientScript . "\n</script>\n<?php\n";
        }
        $code .= <<<'PHP'
?>

<!-- Master-Datensatz Info-Box (wenn als gefilterte Detail-Liste geöffnet) -->
<?php if (!empty($activeMaster)): ?>
<div class="box box-info" style="margin-bottom:15px; border-top-width:3px;">
    <div class="box-header with-border" style="padding:8px 14px; background:#f4f9fd;">
        <h3 class="box-title" style="font-size:14px; font-weight:600; color:#1b4f72;">
            <i class="fa fa-sitemap"></i> Übergeordneter Master-Datensatz: <?php echo ew_HtmlEncode($activeMaster['master_caption']); ?>
        </h3>
        <div class="box-tools pull-right">
            <a href="<?php echo ew_HtmlEncode($activeMaster['master_slug'] . 'list.php'); ?>" class="btn btn-default btn-xs" title="Zurück zur Master-Tabelle">
                <i class="fa fa-arrow-left"></i> Zurück zu <?php echo ew_HtmlEncode($activeMaster['master_caption']); ?>
            </a>
            <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php?cmd=resetmaster'); ?>" class="btn btn-warning btn-xs" title="Master-Filter aufheben und alle Datensätze zeigen">
                <i class="fa fa-unlock"></i> Alle <?php echo ew_HtmlEncode($Table->TableCaption); ?> anzeigen
            </a>
        </div>
    </div>
    <?php if ($masterRecordRow && $masterTableObj): ?>
    <div class="box-body no-padding">
        <table class="table table-bordered table-condensed" style="margin-bottom:0; background:#fff;">
            <thead>
                <tr style="background:#f9fafc;">
                    <?php
                    $mShownCols = 0;
                    foreach ($masterTableObj->Fields as $mfName => $mfMeta):
                        if (empty($mfMeta['list'])) continue;
                        $mShownCols++;
                        if ($mShownCols > 6) break;
                    ?>
                    <th style="font-size:12px; color:#555;"><?php echo ew_HtmlEncode($mfMeta['caption']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php
                    $mShownCols = 0;
                    foreach ($masterTableObj->Fields as $mfName => $mfMeta):
                        if (empty($mfMeta['list'])) continue;
                        $mShownCols++;
                        if ($mShownCols > 6) break;
                    ?>
                    <td style="font-size:12px;"><strong><?php echo ew_FormatViewValue(isset($masterRecordRow[$mfName]) ? $masterRecordRow[$mfName] : null, $mfMeta); ?></strong></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Suchbereich (Quick Search & Extended Search) -->
<?php if ($canSearch && (!empty($Table->Config['quick_search']) || !empty($extSearchFields))): ?>
<div class="ewSearchPanel">
    <form method="get" action="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>" class="form-inline">
        <?php if (!empty($Table->Config['quick_search'])): ?>
        <div class="form-group" style="margin-right:10px; margin-bottom:6px;">
            <div class="input-group">
                <input type="text" name="psearch" class="form-control" value="<?php echo ew_HtmlEncode($psearch); ?>" placeholder="Schnellsuche..." style="min-width:220px;">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Suchen</button>
                    <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php?cmd=reset'); ?>" class="btn btn-default" title="Suche zurücksetzen"><i class="fa fa-undo"></i> Alle zeigen</a>
                </span>
            </div>
        </div>
        <div class="form-group" style="margin-right:15px; margin-bottom:6px;">
            <label class="radio-inline"><input type="radio" name="psearchtype" value="" <?php echo ($psearchType === '') ? 'checked' : ''; ?>> Beliebige Wörter</label>
            <label class="radio-inline"><input type="radio" name="psearchtype" value="AND" <?php echo ($psearchType === 'AND') ? 'checked' : ''; ?>> Alle Wörter</label>
            <label class="radio-inline"><input type="radio" name="psearchtype" value="=" <?php echo ($psearchType === '=') ? 'checked' : ''; ?>> Exakte Phrase</label>
        </div>
        <?php endif; ?>

        <?php if (!empty($extSearchFields)): ?>
        <button type="button" class="btn btn-default btn-sm" onclick="ew_ToggleExtSearch(this);" aria-expanded="<?php echo !empty($advSearch) ? 'true' : 'false'; ?>" style="margin-bottom:6px;">
            <i class="fa fa-sliders"></i> Erweiterte Suche <?php echo !empty($advSearch) ? '<span class="badge bg-blue">' . count($advSearch) . '</span>' : ''; ?>
        </button>
        <?php endif; ?>
    </form>

    <?php if (!empty($extSearchFields)): ?>
    <div id="ewExtSearchCollapse" class="collapse <?php echo !empty($advSearch) ? 'in' : ''; ?>" style="<?php echo !empty($advSearch) ? 'display:block;' : 'display:none;'; ?> margin-top:12px; border-top:1px solid #eee; padding-top:12px;">
        <form method="get" action="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>" class="form-horizontal">
            <input type="hidden" name="adv_submit" value="1">
            <div class="row">
                <?php foreach ($extSearchFields as $fName => $fMeta):
                    $curAdvVal = isset($advSearch[$fName]) ? $advSearch[$fName] : '';
                ?>
                <div class="col-md-4 col-sm-6" style="margin-bottom:10px;">
                    <label style="font-weight:600; font-size:12px;"><?php echo ew_HtmlEncode($fMeta['caption']); ?></label>
                    <?php echo ew_RenderEditControl($fMeta, $curAdvVal, 'search', 'x_'); ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:6px;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter anwenden</button>
                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php?cmd=reset'); ?>" class="btn btn-default btn-sm">Filter löschen</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// PHPMacherNG Tabellen-/View-Diagramme (Balkendiagramm & Tortendiagramm) berechnen
$tableChartsTopHtml = '';
$tableChartsBottomHtml = '';
if (!empty($Table->Config['charts']) && is_array($Table->Config['charts'])) {
    foreach ($Table->Config['charts'] as $chIdx => $chCfg) {
        if (isset($chCfg['enabled']) && !$chCfg['enabled']) {
            continue;
        }
        $xField = isset($chCfg['x_field']) ? trim((string)$chCfg['x_field']) : '';
        if ($xField === '' || !isset($Table->Fields[$xField])) {
            continue;
        }
        $yField = isset($chCfg['y_field']) ? trim((string)$chCfg['y_field']) : '';
        $aggr = isset($chCfg['aggregate']) ? strtoupper(trim((string)$chCfg['aggregate'])) : 'COUNT';
        if (!in_array($aggr, array('COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'VALUE'), true)) {
            $aggr = 'COUNT';
        }
        $validChartTypes = array('bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge');
        $rawType = isset($chCfg['type']) ? strtolower(trim((string)$chCfg['type'])) : 'bar';
        $chType = in_array($rawType, $validChartTypes, true) ? $rawType : 'bar';
        $chPos = (isset($chCfg['position']) && strtolower((string)$chCfg['position']) === 'bottom') ? 'bottom' : 'top';
        $chSort = isset($chCfg['sort']) ? strtoupper(trim((string)$chCfg['sort'])) : 'VALUE_DESC';
        $chLimit = isset($chCfg['limit']) && (int)$chCfg['limit'] > 0 ? min(100, (int)$chCfg['limit']) : 15;
        $chHeight = isset($chCfg['height']) && (int)$chCfg['height'] >= 160 ? min(600, (int)$chCfg['height']) : 280;

        $xColSafe = '`' . str_replace('`', '``', $xField) . '`';
        if ($aggr === 'COUNT' || $yField === '' || !isset($Table->Fields[$yField])) {
            $valExpr = 'COUNT(*)';
        } elseif ($aggr === 'VALUE') {
            $yColSafe = '`' . str_replace('`', '``', $yField) . '`';
            $valExpr = $yColSafe;
        } else {
            $yColSafe = '`' . str_replace('`', '``', $yField) . '`';
            $valExpr = $aggr . '(' . $yColSafe . ')';
        }

        $chOrderSql = ' ORDER BY `ew_val` DESC';
        if ($chSort === 'VALUE_ASC') {
            $chOrderSql = ' ORDER BY `ew_val` ASC';
        } elseif ($chSort === 'LABEL_ASC') {
            $chOrderSql = ' ORDER BY `ew_lbl` ASC';
        } elseif ($chSort === 'LABEL_DESC') {
            $chOrderSql = ' ORDER BY `ew_lbl` DESC';
        }

        $tblSafe = '`' . str_replace('`', '``', $Table->TableName) . '`';
        if ($aggr === 'VALUE') {
            $chSql = 'SELECT ' . $xColSafe . ' AS `ew_lbl`, ' . $valExpr . ' AS `ew_val` FROM ' . $tblSafe . $whereSql . $chOrderSql . ' LIMIT ' . (int)$chLimit;
        } else {
            $chSql = 'SELECT ' . $xColSafe . ' AS `ew_lbl`, ' . $valExpr . ' AS `ew_val` FROM ' . $tblSafe . $whereSql . ' GROUP BY ' . $xColSafe . $chOrderSql . ' LIMIT ' . (int)$chLimit;
        }

        $chRows = $conn->LoadRows($chSql, $sqlParams);
        $chLabels = array();
        $chValues = array();
        $xMeta = $Table->Fields[$xField];
        if (is_array($chRows)) {
            foreach ($chRows as $cr) {
                $rawLbl = isset($cr['ew_lbl']) ? $cr['ew_lbl'] : '';
                if (!empty($xMeta['lookup_table'])) {
                    $dispLbl = strip_tags(ew_GetLookupDisplayValue($rawLbl, $xMeta));
                } elseif (!empty($xMeta['options'])) {
                    $opts = ew_ParseOptionsString($xMeta['options']);
                    $dispLbl = isset($opts[(string)$rawLbl]) ? $opts[(string)$rawLbl] : (string)$rawLbl;
                } else {
                    $dispLbl = ($rawLbl !== null && trim((string)$rawLbl) !== '') ? (string)$rawLbl : '(Leer)';
                }
                $chLabels[] = $dispLbl;
                $chValues[] = isset($cr['ew_val']) ? round((float)$cr['ew_val'], 2) : 0.0;
            }
        }

        $chTitle = isset($chCfg['title']) && trim((string)$chCfg['title']) !== ''
            ? trim((string)$chCfg['title'])
            : ($Table->TableCaption . ' – ' . $xMeta['caption']);
        $canvasId = 'ew_tbl_chart_' . $Table->TableSlug . '_' . (int)$chIdx;
        $payloadJson = htmlspecialchars(json_encode(array(
            'type'   => $chType,
            'title'  => $chTitle,
            'labels' => $chLabels,
            'values' => $chValues
        )), ENT_QUOTES, 'UTF-8');

        $typeIcons = array(
            'bar'      => 'fa-bar-chart',
            'hbar'     => 'fa-align-left',
            'line'     => 'fa-line-chart',
            'area'     => 'fa-area-chart',
            'pie'      => 'fa-pie-chart',
            'doughnut' => 'fa-circle-o-notch',
            'funnel'   => 'fa-filter',
            'radar'    => 'fa-bullseye',
            'gauge'    => 'fa-tachometer'
        );
        $iconClass = isset($typeIcons[$chType]) ? $typeIcons[$chType] : 'fa-bar-chart';
        $switchDefs = array(
            'bar'      => array('icon' => 'fa-bar-chart',      'title' => 'Säulendiagramm'),
            'hbar'     => array('icon' => 'fa-align-left',     'title' => 'Horizontales Balkendiagramm'),
            'line'     => array('icon' => 'fa-line-chart',     'title' => 'Liniendiagramm'),
            'area'     => array('icon' => 'fa-area-chart',     'title' => 'Flächendiagramm'),
            'pie'      => array('icon' => 'fa-pie-chart',      'title' => 'Tortendiagramm'),
            'doughnut' => array('icon' => 'fa-circle-o-notch', 'title' => 'Ring-/Donut-Diagramm'),
            'funnel'   => array('icon' => 'fa-filter',         'title' => 'Trichterdiagramm'),
            'radar'    => array('icon' => 'fa-bullseye',       'title' => 'Netz-/Radardiagramm'),
            'gauge'    => array('icon' => 'fa-tachometer',     'title' => 'KPI-Tacho (Gauge)')
        );
        $switchBtnsHtml = '';
        foreach ($switchDefs as $swKey => $swMeta) {
            $activeCls = ($chType === $swKey) ? ' active' : '';
            $switchBtnsHtml .= '<button type="button" class="btn btn-default' . $activeCls . '" onclick="ew_SwitchChartType(\'' . $canvasId . '\', \'' . $swKey . '\', this);" title="' . $swMeta['title'] . '"><i class="fa ' . $swMeta['icon'] . '"></i></button>';
        }
        $boxHtml = '<div class="col-md-' . (count($Table->Config['charts']) > 1 ? '6' : '12') . '">' .
            '<div class="box box-primary" style="margin-bottom:15px;">' .
            '<div class="box-header with-border" style="padding:8px 14px;">' .
            '<h3 class="box-title" style="font-size:14px; font-weight:600;"><i class="fa ' . $iconClass . ' text-primary"></i> ' . ew_HtmlEncode($chTitle) . '</h3>' .
            '<div class="box-tools pull-right">' .
            '<div class="btn-group btn-group-xs">' .
            $switchBtnsHtml .
            '</div> ' .
            '<button type="button" class="btn btn-default btn-xs" onclick="ew_DownloadChartPng(\'' . $canvasId . '\');" title="Diagramm als PNG-Bild herunterladen"><i class="fa fa-download"></i> PNG</button>' .
            '</div>' .
            '</div>' .
            '<div class="box-body" style="padding:10px 14px;">' .
            '<canvas id="' . $canvasId . '" class="ewChartCanvas" data-ew-chart="' . $payloadJson . '" height="' . (int)$chHeight . '" style="width:100%; height:' . (int)$chHeight . 'px; display:block;"></canvas>' .
            '</div>' .
            '</div>' .
            '</div>';

        if ($chPos === 'bottom') {
            $tableChartsBottomHtml .= $boxHtml;
        } else {
            $tableChartsTopHtml .= $boxHtml;
        }
    }
}
?>

<?php if ($tableChartsTopHtml !== ''): ?>
<div class="row ewChartsRowTop">
    <?php echo $tableChartsTopHtml; ?>
</div>
<?php endif; ?>

<!-- Haupt-Grid (PHPMacherNG Stil) -->
<form id="f<?php echo ew_HtmlEncode($Table->TableSlug); ?>list" method="post" action="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">

    <div class="ewGrid">
        <!-- Obere Toolbar & Pager -->
        <div class="ewGridUpperPanel">
            <div class="ewToolbar">
                <div>
                    <?php if ($canAdd): ?>
                        <a href="<?php echo ew_HtmlEncode($addUrl); ?>" class="btn btn-success btn-sm">
                            <i class="fa fa-plus"></i> Hinzufügen
                        </a>
                    <?php endif; ?>
                    <?php if ($canMultiDelete): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="ew_SubmitMultiAction('f<?php echo ew_HtmlEncode($Table->TableSlug); ?>list', '<?php echo ew_HtmlEncode($Table->TableSlug . 'delete.php'); ?>')">
                            <i class="fa fa-trash"></i> Ausgewählte löschen
                        </button>
                    <?php endif; ?>
                    <?php if ($canMultiUpdate): ?>
                        <button type="button" class="btn btn-warning btn-sm" onclick="ew_SubmitMultiAction('f<?php echo ew_HtmlEncode($Table->TableSlug); ?>list', '<?php echo ew_HtmlEncode($Table->TableSlug . 'update.php'); ?>')">
                            <i class="fa fa-pencil-square-o"></i> Multi-Update
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php?cmd=resetall'); ?>" class="btn btn-default btn-sm" title="Suche & Sortierung zurücksetzen">
                        <i class="fa fa-refresh"></i>
                    </a>
                </div>

                <!-- PHPMacherNG Pager -->
                <div class="ewPager">
                    <span>Datensätze <strong><?php echo $startRec; ?></strong> bis <strong><?php echo $endRec; ?></strong> von <strong><?php echo $totalRecords; ?></strong></span>
                    <?php if ($totalPages > 1): ?>
                    <div class="btn-group btn-group-sm" style="margin-left:8px;">
                        <a href="<?php echo ($currentPage > 1) ? ew_HtmlEncode($Table->TableSlug . 'list.php?page=1') : '#'; ?>" class="btn btn-default <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>" title="Erste Seite"><i class="fa fa-step-backward"></i></a>
                        <a href="<?php echo ($currentPage > 1) ? ew_HtmlEncode($Table->TableSlug . 'list.php?page=' . ($currentPage - 1)) : '#'; ?>" class="btn btn-default <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>" title="Vorherige Seite"><i class="fa fa-backward"></i></a>
                        <span class="btn btn-default" style="pointer-events:none;">Seite <?php echo $currentPage; ?> / <?php echo $totalPages; ?></span>
                        <a href="<?php echo ($currentPage < $totalPages) ? ew_HtmlEncode($Table->TableSlug . 'list.php?page=' . ($currentPage + 1)) : '#'; ?>" class="btn btn-default <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>" title="Nächste Seite"><i class="fa fa-forward"></i></a>
                        <a href="<?php echo ($currentPage < $totalPages) ? ew_HtmlEncode($Table->TableSlug . 'list.php?page=' . $totalPages) : '#'; ?>" class="btn btn-default <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>" title="Letzte Seite"><i class="fa fa-step-forward"></i></a>
                    </div>
                    <?php endif; ?>

                    <select class="form-control input-sm" onchange="window.location.href='<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php?recperpage='); ?>' + this.value;" title="Datensätze pro Seite">
                        <?php
                        $sizes = explode(',', EW_PAGE_SIZES);
                        foreach ($sizes as $sz):
                            $sz = trim($sz);
                            if ($sz === '') continue;
                            $sel = ((string)$recPerPage === (string)$sz) ? ' selected' : '';
                            $label = ($sz === 'ALL') ? 'Alle' : $sz . ' / Seite';
                        ?>
                        <option value="<?php echo ew_HtmlEncode($sz); ?>"<?php echo $sel; ?>><?php echo ew_HtmlEncode($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Datentabelle -->
        <?php
        $totalColSpan = count($listFields) + ($hasMultiActions ? 1 : 0) + ($hasActionColumn ? 1 : 0);
        ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover ewTable">
                <thead>
                    <tr>
                        <?php if ($hasMultiActions): ?>
                        <th class="ewListOptionBody" style="text-align:center;">
                            <input type="checkbox" onclick="ew_ToggleAllKeys(this);" title="Alle auswählen">
                        </th>
                        <?php endif; ?>
                        <?php if ($hasActionColumn): ?>
                        <th class="ewListOptionBody">Aktionen<?php echo !empty($masterDetails) ? ' / Unterlisten' : ''; ?></th>
                        <?php endif; ?>
                        <?php foreach ($listFields as $fName => $fMeta):
                            $isSorted = ($orderBy === $fName);
                            $sortIcon = '';
                            if ($isSorted) {
                                $sortIcon = ($orderDir === 'ASC') ? ' <i class="fa fa-sort-amount-asc text-primary"></i>' : ' <i class="fa fa-sort-amount-desc text-primary"></i>';
                            } elseif (!empty($fMeta['allow_sort'])) {
                                $sortIcon = ' <i class="fa fa-sort text-muted" style="opacity:0.35;"></i>';
                            }
                        ?>
                        <th>
                            <?php if (!empty($fMeta['allow_sort'])): ?>
                                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php?order=' . urlencode($fName)); ?>">
                                    <?php echo ew_HtmlEncode($fMeta['caption']); ?><?php echo $sortIcon; ?>
                                </a>
                            <?php else: ?>
                                <?php echo ew_HtmlEncode($fMeta['caption']); ?>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?php echo (int)$totalColSpan; ?>" class="text-center text-muted" style="padding:25px;">
                            <i class="fa fa-info-circle"></i> Keine Datensätze gefunden.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $rowIdx => $row):
                            $rowAttrs = array('class' => '', 'style' => '');
                            $Table->Row_Rendering($row);
                            $Table->Row_Rendered($row, $rowAttrs);
                            $trClassAttr = !empty($rowAttrs['class']) ? ' class="' . ew_HtmlEncode($rowAttrs['class']) . '"' : '';
                            $trStyleAttr = !empty($rowAttrs['style']) ? ' style="' . ew_HtmlEncode($rowAttrs['style']) . '"' : '';

                            $rowKey = $Table->GetRowKey($row);
                            $keyParam = urlencode($rowKey);
                            $previewDomId = $Table->TableSlug . '_' . $rowIdx;

                            // Detail-Informationen für diese Master-Zeile berechnen
                            $rowDetails = array();
                            $firstPreviewUrl = '';
                            foreach ($masterDetails as $md) {
                                $mVal = isset($row[$md['master_field']]) ? (string)$row[$md['master_field']] : '';
                                $dCount = null;
                                if (!empty($md['detail_count']) && $mVal !== '') {
                                    $dTableSafe = '`' . str_replace('`', '``', $md['detail_table']) . '`';
                                    $dFieldSafe = '`' . str_replace('`', '``', $md['detail_field']) . '`';
                                    $dCount = (int)$conn->ExecuteScalar('SELECT COUNT(*) FROM ' . $dTableSafe . ' WHERE ' . $dFieldSafe . ' = ?', array($mVal));
                                }
                                $previewUrl = $md['detail_slug'] . 'preview.php?f=' . urlencode($mVal) . '&master=' . urlencode($Table->TableSlug) . '&mfield=' . urlencode($md['master_field']) . '&dfield=' . urlencode($md['detail_field']);
                                $listUrl = $md['detail_slug'] . 'list.php?showmaster=' . urlencode($Table->TableSlug) . '&fk_' . urlencode($md['master_field']) . '=' . urlencode($mVal);
                                if (!empty($md['preview_row']) && $firstPreviewUrl === '') {
                                    $firstPreviewUrl = $previewUrl;
                                }
                                $rowDetails[] = array(
                                    'caption'     => $md['caption'],
                                    'preview_row' => $md['preview_row'],
                                    'count'       => $dCount,
                                    'preview_url' => $previewUrl,
                                    'list_url'    => $listUrl
                                );
                            }
                        ?>
                        <tr<?php echo $trClassAttr . $trStyleAttr; ?>>
                            <?php if ($hasMultiActions): ?>
                            <td class="ewListOptionBody" style="text-align:center;">
                                <input type="checkbox" name="key_m[]" value="<?php echo ew_HtmlEncode($rowKey); ?>" class="ewKeyCheckbox" onchange="ew_OnRowCheckboxChange(this);">
                            </td>
                            <?php endif; ?>

                            <?php if ($hasActionColumn): ?>
                            <td class="ewListOptionBody">
                                <?php if ($hasPreviewRow && $firstPreviewUrl !== ''): ?>
                                    <button type="button" class="btn btn-info btn-xs" onclick="ew_ToggleDetailPreview(this, '<?php echo ew_HtmlEncode($previewDomId); ?>', '<?php echo ew_HtmlEncode($firstPreviewUrl); ?>');" title="Unterliste auf-/zuklappen (Preview Row)">
                                        <i class="fa fa-plus-square-o ewPreviewBtnIcon"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($canView): ?>
                                    <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'view.php?key=' . $keyParam); ?>" class="btn btn-default btn-xs" title="Ansehen"><i class="fa fa-eye"></i></a>
                                <?php endif; ?>
                                <?php if ($canEdit): ?>
                                    <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'edit.php?key=' . $keyParam); ?>" class="btn btn-primary btn-xs" title="Bearbeiten"><i class="fa fa-pencil"></i></a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'delete.php?key=' . $keyParam); ?>" class="btn btn-danger btn-xs" title="Löschen"><i class="fa fa-trash"></i></a>
                                <?php endif; ?>
                                <?php foreach ($rowDetails as $rd): ?>
                                    <a href="<?php echo ew_HtmlEncode($rd['list_url']); ?>" class="btn btn-default btn-xs" title="Detail-Liste <?php echo ew_HtmlEncode($rd['caption']); ?> öffnen" style="margin-left:2px;">
                                        <i class="fa fa-list-alt text-primary"></i> <?php echo ew_HtmlEncode($rd['caption']); ?>
                                        <?php if ($rd['count'] !== null): ?>
                                            <span class="badge bg-light-blue" style="font-size:10px; padding:1px 5px; margin-left:2px;"><?php echo (int)$rd['count']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </td>
                            <?php endif; ?>

                            <?php foreach ($listFields as $fName => $fMeta):
                                $align = ($fMeta['view_tag'] === 'CURRENCY' || $fMeta['view_tag'] === 'NUMBER') ? ' style="text-align:right;"' : '';
                            ?>
                            <td<?php echo $align; ?>>
                                <?php echo ew_FormatViewValue(isset($row[$fName]) ? $row[$fName] : null, $fMeta); ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php if ($hasPreviewRow && $firstPreviewUrl !== ''): ?>
                        <tr id="ewPreviewRow_<?php echo ew_HtmlEncode($previewDomId); ?>" class="ewDetailPreviewRow" style="display:none; background:#f8fafd;">
                            <td colspan="<?php echo (int)$totalColSpan; ?>" style="padding:10px 16px 12px 28px; border-left:3px solid #3c8dbc;">
                                <?php if (count($rowDetails) > 1): ?>
                                <ul class="nav nav-tabs" style="margin-bottom:8px;">
                                    <?php foreach ($rowDetails as $rdIdx => $rd):
                                        if (!$rd['preview_row']) continue;
                                    ?>
                                    <li class="<?php echo ($rdIdx === 0) ? 'active' : ''; ?>">
                                        <a href="javascript:void(0);" onclick="ew_LoadDetailPreviewUrl('<?php echo ew_HtmlEncode($previewDomId); ?>', '<?php echo ew_HtmlEncode($rd['preview_url']); ?>', this);">
                                            <i class="fa fa-table"></i> <?php echo ew_HtmlEncode($rd['caption']); ?>
                                            <?php if ($rd['count'] !== null): ?>
                                                <span class="badge bg-light-blue"><?php echo (int)$rd['count']; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <div id="ewPreviewContent_<?php echo ew_HtmlEncode($previewDomId); ?>"></div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Unterer Panel -->
        <div class="ewGridLowerPanel">
            <div class="ewToolbar">
                <span class="text-muted">Gesamt: <strong><?php echo $totalRecords; ?></strong> Einträge</span>
                <?php if ($canAdd): ?>
                    <a href="<?php echo ew_HtmlEncode($addUrl); ?>" class="btn btn-success btn-sm">
                        <i class="fa fa-plus"></i> Hinzufügen
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?php if ($tableChartsBottomHtml !== ''): ?>
<div class="row ewChartsRowBottom" style="margin-top:15px;">
    <?php echo $tableChartsBottomHtml; ?>
</div>
<?php endif; ?>

<?php
PHP;
        $listStartupScript = $this->getTableClientScript($tCfg['name'], 'List_Startup_Script');
        if ($listStartupScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Startup Script (List Page)\ndocument.addEventListener('DOMContentLoaded', function() {\n" . $listStartupScript . "\n});\n</script>\n<?php";
        }
        $code .= "\nrequire_once dirname(__FILE__) . '/footer.php'; ?>\n";
        return $code;
    }

    /**
     * Erzeugt <table_slug>preview.php (Inline AJAX-Unterliste / Detail Preview Row wie in PHPMacherNG)
     */
    protected function buildPreviewPage($tCfg)
    {
        $tName = $tCfg['name'];
        $slug = self::tableSlug($tName);
        $className = 'cTable_' . $slug;

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG Detail Preview Row (" . $slug . "preview.php)\n * Liefert eine kompakte HTML-Untertabelle per AJAX für Master/Detail-Listen\n */\n";
        $code .= "require_once dirname(__FILE__) . '/" . $slug . "info.php';\n\n";
        $code .= "\$Table = new " . $className . "();\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "if (!\$Security->CanList(\$Table->TableName)) {\n";
        $code .= "    echo '<div class=\"alert alert-warning\" style=\"margin:0;\"><i class=\"fa fa-lock\"></i> Keine Berechtigung für diese Detail-Tabelle.</div>';\n";
        $code .= "    exit;\n";
        $code .= "}\n";
        $code .= "\$conn = ew_Connect();\n\n";

        $code .= <<<'PHP'
$fkValue = ew_Get('f', '');
$masterSlug = preg_replace('/[^a-zA-Z0-9_]/', '', ew_Get('master', ''));
$masterField = ew_Get('mfield', '');
$detailField = ew_Get('dfield', '');

if ($detailField === '' || !isset($Table->Fields[$detailField])) {
    echo '<div class="text-danger"><i class="fa fa-exclamation-triangle"></i> Ungültiges Detail-Verknüpfungsfeld.</div>';
    exit;
}

$whereParts = array();
$sqlParams = array();

$dCol = '`' . str_replace('`', '``', $detailField) . '`';
$whereParts[] = $dCol . ' = ?';
$sqlParams[] = $fkValue;

$uidWhere = $Security->BuildUserIDFilter($Table, $sqlParams);
if ($uidWhere !== '') {
    $whereParts[] = $uidWhere;
}

$customFilter = '';
$Table->Recordset_Selecting($customFilter);
if (trim($customFilter) !== '') {
    $whereParts[] = '(' . $customFilter . ')';
}

$whereSql = ' WHERE ' . implode(' AND ', $whereParts);
$orderBy = isset($Table->Config['default_order_by']) ? $Table->Config['default_order_by'] : '';
$orderDir = isset($Table->Config['default_order_dir']) ? $Table->Config['default_order_dir'] : 'DESC';
if (strtoupper($orderDir) !== 'ASC' && strtoupper($orderDir) !== 'DESC') {
    $orderDir = 'ASC';
}
$orderSql = '';
if ($orderBy !== '' && isset($Table->Fields[$orderBy])) {
    $orderSql = ' ORDER BY `' . str_replace('`', '``', $orderBy) . '` ' . $orderDir;
}

$totalDetailRecords = (int)$conn->ExecuteScalar($Table->SqlCount() . $whereSql, $sqlParams);
$previewRows = $conn->LoadRows($Table->SqlSelect() . $whereSql . $orderSql . ' LIMIT 50', $sqlParams);
if (!is_array($previewRows)) {
    $previewRows = array();
}

$listFields = array();
foreach ($Table->Fields as $fName => $fMeta) {
    if (!empty($fMeta['list'])) {
        $listFields[$fName] = $fMeta;
    }
}

$hasPk = !empty($Table->PrimaryKeys);
$canAdd = $hasPk && !empty($Table->Config['add_page']) && $Security->CanAdd($Table->TableName);
$canView = $hasPk && !empty($Table->Config['view_page']) && $Security->CanView($Table->TableName);
$canEdit = $hasPk && !empty($Table->Config['edit_page']) && $Security->CanEdit($Table->TableName);
$canDelete = $hasPk && !empty($Table->Config['delete_page']) && $Security->CanDelete($Table->TableName);

$fullListUrl = $Table->TableSlug . 'list.php?showmaster=' . urlencode($masterSlug) . '&fk_' . urlencode($masterField !== '' ? $masterField : $detailField) . '=' . urlencode($fkValue);
$addDetailUrl = $Table->TableSlug . 'add.php?fk_' . urlencode($detailField) . '=' . urlencode($fkValue);
?>
<div class="box box-solid" style="margin-bottom:0; border:1px solid #d2d6de; box-shadow:none;">
    <div class="box-header with-border" style="padding:6px 12px; background:#edf3f8;">
        <h4 class="box-title" style="font-size:13px; font-weight:600; color:#1b4f72;">
            <i class="fa fa-level-up fa-rotate-90"></i> Unterliste: <?php echo ew_HtmlEncode($Table->TableCaption); ?>
            <span class="badge bg-light-blue" style="margin-left:5px;"><?php echo (int)$totalDetailRecords; ?></span>
        </h4>
        <div class="box-tools pull-right" style="top:4px;">
            <?php if ($canAdd): ?>
                <a href="<?php echo ew_HtmlEncode($addDetailUrl); ?>" class="btn btn-success btn-xs">
                    <i class="fa fa-plus"></i> Neu in <?php echo ew_HtmlEncode($Table->TableCaption); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo ew_HtmlEncode($fullListUrl); ?>" class="btn btn-primary btn-xs">
                <i class="fa fa-external-link"></i> Vollständige Detail-Liste öffnen
            </a>
        </div>
    </div>
    <div class="box-body no-padding table-responsive">
        <table class="table table-bordered table-striped table-condensed" style="margin-bottom:0; font-size:12px; background:#fff;">
            <thead>
                <tr style="background:#f9fafc;">
                    <?php if ($canView || $canEdit || $canDelete): ?>
                    <th style="width:90px; white-space:nowrap;">Aktionen</th>
                    <?php endif; ?>
                    <?php foreach ($listFields as $fName => $fMeta): ?>
                    <th><?php echo ew_HtmlEncode($fMeta['caption']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($previewRows)): ?>
                <tr>
                    <td colspan="<?php echo count($listFields) + 1; ?>" class="text-center text-muted" style="padding:12px;">
                        <i class="fa fa-info-circle"></i> Keine zugehörigen Datensätze in <strong><?php echo ew_HtmlEncode($Table->TableCaption); ?></strong> vorhanden.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($previewRows as $pRow):
                        $rowAttrs = array('class' => '', 'style' => '');
                        $Table->Row_Rendering($pRow);
                        $Table->Row_Rendered($pRow, $rowAttrs);
                        $pKey = $Table->GetRowKey($pRow);
                        $pKeyParam = urlencode($pKey);
                    ?>
                    <tr>
                        <?php if ($canView || $canEdit || $canDelete): ?>
                        <td style="white-space:nowrap;">
                            <?php if ($canView): ?>
                                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'view.php?key=' . $pKeyParam); ?>" class="btn btn-default btn-xs" title="Ansehen"><i class="fa fa-eye"></i></a>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'edit.php?key=' . $pKeyParam); ?>" class="btn btn-primary btn-xs" title="Bearbeiten"><i class="fa fa-pencil"></i></a>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'delete.php?key=' . $pKeyParam); ?>" class="btn btn-danger btn-xs" title="Löschen"><i class="fa fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php foreach ($listFields as $fName => $fMeta):
                            $align = ($fMeta['view_tag'] === 'CURRENCY' || $fMeta['view_tag'] === 'NUMBER') ? ' style="text-align:right;"' : '';
                        ?>
                        <td<?php echo $align; ?>>
                            <?php echo ew_FormatViewValue(isset($pRow[$fName]) ? $pRow[$fName] : null, $fMeta); ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
PHP;
        return $code;
    }

    /**
     * Erzeugt <table_slug>add.php (Neuen Datensatz hinzufügen inkl. Security, Master/Detail-Vorbelegung, User ID & Server Events)
     */
    protected function buildAddPage($tCfg)
    {
        $slug = self::tableSlug($tCfg['name']);
        $className = 'cTable_' . $slug;

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG Add Page (" . $slug . "add.php)\n */\n";
        $code .= "require_once dirname(__FILE__) . '/" . $slug . "info.php';\n\n";
        $code .= "\$Table = new " . $className . "();\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "\$Security->CheckPagePermission(\$Table->TableName, EW_ALLOW_ADD);\n";
        $code .= "\$conn = ew_Connect();\n";
        $code .= "\$sessionPrefix = EW_PROJECT_ID . '_' . \$Table->TableSlug . '_';\n\n";

        $code .= <<<'PHP'
$ownerField = isset($Table->Config['user_id_field']) ? trim($Table->Config['user_id_field']) : '';
$enforceOwner = ($Security->IsEnabled() && !$Security->IsSysAdmin() && !$Security->CanAdmin($Table->TableName) && $ownerField !== '' && isset($Table->Fields[$ownerField]));

$activeMaster = isset($_SESSION[$sessionPrefix . 'master']) && is_array($_SESSION[$sessionPrefix . 'master'])
    ? $_SESSION[$sessionPrefix . 'master']
    : array();

$addFields = array();
$formValues = array();
foreach ($Table->Fields as $fName => $fMeta) {
    if (!empty($fMeta['add'])) {
        $addFields[$fName] = $fMeta;
        if (!empty($fMeta['is_auto_increment'])) {
            $formValues[$fName] = '(Automatisch vergeben)';
        } elseif ($enforceOwner && $fName === $ownerField) {
            $formValues[$fName] = $Security->CurrentUserID;
        } elseif (isset($_GET['fk_' . $fName]) && ew_Get('fk_' . $fName, '') !== '') {
            $formValues[$fName] = ew_Get('fk_' . $fName, '');
        } elseif (!empty($activeMaster['detail_field']) && $activeMaster['detail_field'] === $fName && isset($activeMaster['fk_value'])) {
            $formValues[$fName] = $activeMaster['fk_value'];
        } else {
            $formValues[$fName] = isset($fMeta['default_value']) ? $fMeta['default_value'] : '';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        ew_SetFailureMessage('Ungültiges Sicherheits-Token (CSRF). Bitte senden Sie das Formular erneut ab.');
    } else {
        $errors = array();
        $rsnew = array();

        foreach ($addFields as $fName => $fMeta) {
            if (!empty($fMeta['is_auto_increment'])) {
                continue;
            }
            if ($enforceOwner && $fName === $ownerField) {
                $rawVal = (string)$Security->CurrentUserID;
            } else {
                $rawVal = ew_Post('x_' . $fName, '');
            }
            $formValues[$fName] = $rawVal;
            $err = ew_ValidateField($fMeta, $rawVal);
            if ($err !== '') {
                $errors[] = $err;
            } else {
                $rsnew[$fName] = ew_PrepareDbValue($fMeta, $rawVal);
            }
        }

        // Falls das User-ID-Feld nicht als sichtbares Add-Feld aktiviert war, trotzdem automatisch befüllen
        if ($enforceOwner && !isset($addFields[$ownerField])) {
            $rsnew[$ownerField] = $Security->CurrentUserID;
        }

        // Server Event: Form_CustomValidate
        $customError = '';
        if (!$Table->Form_CustomValidate($customError)) {
            $errors[] = ($customError !== '') ? $customError : 'Validierungsfehler in Form_CustomValidate.';
        }

        if (!empty($errors)) {
            foreach ($errors as $e) {
                ew_SetFailureMessage($e);
            }
        } elseif (!empty($rsnew)) {
            // Server Event: Row_Inserting($rsold, &$rsnew)
            $canInsert = $Table->Row_Inserting(null, $rsnew);
            if ($canInsert && !empty($rsnew)) {
                $insertCols = array();
                $insertPlaceholders = array();
                $insertParams = array();
                foreach ($rsnew as $colName => $colVal) {
                    $insertCols[] = '`' . str_replace('`', '``', $colName) . '`';
                    $insertPlaceholders[] = '?';
                    $insertParams[] = $colVal;
                }
                $sql = 'INSERT INTO ' . $Table->SqlFrom() . ' (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                if ($conn->Execute($sql, $insertParams)) {
                    $newId = $conn->Insert_ID();
                    if ($newId && count($Table->PrimaryKeys) === 1) {
                        $pkCol = $Table->PrimaryKeys[0];
                        if (!isset($rsnew[$pkCol]) || $rsnew[$pkCol] === '' || $rsnew[$pkCol] === null) {
                            $rsnew[$pkCol] = $newId;
                        }
                    }
                    // Server Event: Row_Inserted($rsold, &$rsnew)
                    $Table->Row_Inserted(null, $rsnew);
                    ew_SetSuccessMessage('Der neue Datensatz wurde erfolgreich hinzugefügt.');
                    ew_Redirect($Table->TableSlug . 'list.php');
                } else {
                    ew_SetFailureMessage('Datenbankfehler beim Speichern: ' . ew_HtmlEncode($conn->lastError));
                }
            } elseif (!$canInsert && empty($_SESSION[EW_PROJECT_ID . '_FailureMessage'])) {
                ew_SetFailureMessage('Das Einfügen wurde durch ein Server-Event (Row_Inserting) abgebrochen.');
            }
        }
    }
}

$Table->Row_Rendering($formValues);

$PageTitle = $Table->TableCaption;
$PageSubTitle = 'Hinzufügen';
require_once dirname(__FILE__) . '/header.php';
PHP;
        $addClientScript = $this->getTableClientScript($tCfg['name'], 'Add_Client_Script');
        $addFormValidate = $this->getTableClientScript($tCfg['name'], 'Add_Form_CustomValidate');
        if ($addClientScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Client Script (Add Page)\n" . $addClientScript . "\n</script>\n<?php\n";
        }
        $code .= "\n?>\n<script>\nfunction ew_AddFormValidate(fobj) {\n";
        if ($addFormValidate !== '') {
            $code .= "    " . str_replace("\n", "\n    ", $addFormValidate) . "\n";
        }
        $code .= "    if (" . "EW_CONFIRM_ACTION) {\n";
        $code .= "        return confirm('Möchten Sie diesen Datensatz jetzt speichern?');\n";
        $code .= "    }\n";
        $code .= "    return true;\n";
        $code .= "}\n</script>\n<?php\n";

        $code .= <<<'PHP'
?>

<div class="box box-primary ewForm">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-plus-circle"></i> Neuen Datensatz anlegen</h3>
    </div>
    <form class="form-horizontal" method="post" action="<?php echo ew_HtmlEncode($Table->TableSlug . 'add.php'); ?>" onsubmit="return ew_AddFormValidate(this);">
        <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
        <div class="box-body">
            <?php foreach ($addFields as $fName => $fMeta): ?>
            <div class="form-group">
                <label for="x_<?php echo ew_HtmlEncode($fName); ?>" class="col-sm-3 control-label">
                    <?php echo ew_HtmlEncode($fMeta['caption']); ?>
                    <?php if (!empty($fMeta['required']) && empty($fMeta['is_auto_increment'])): ?><span class="ewRequired">*</span><?php endif; ?>
                </label>
                <div class="col-sm-9">
                    <?php if (!empty($fMeta['is_auto_increment'])): ?>
                        <span class="form-control-static text-muted"><em>(Automatisch vergeben)</em></span>
                    <?php elseif ($enforceOwner && $fName === $ownerField): ?>
                        <input type="text" class="form-control" name="x_<?php echo ew_HtmlEncode($fName); ?>" value="<?php echo ew_HtmlEncode($Security->CurrentUserID); ?>" readonly>
                    <?php else: ?>
                        <?php echo ew_RenderEditControl($fMeta, isset($formValues[$fName]) ? $formValues[$fName] : '', 'add', 'x_'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="box-footer">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Hinzufügen</button>
                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>" class="btn btn-default"><i class="fa fa-times"></i> Abbrechen</a>
            </div>
        </div>
    </form>
</div>

<?php
PHP;
        $addStartupScript = $this->getTableClientScript($tCfg['name'], 'Add_Startup_Script');
        if ($addStartupScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Startup Script (Add Page)\ndocument.addEventListener('DOMContentLoaded', function() {\n" . $addStartupScript . "\n});\n</script>\n<?php";
        }
        $code .= "\nrequire_once dirname(__FILE__) . '/footer.php'; ?>\n";
        return $code;
    }

    /**
     * Erzeugt <table_slug>edit.php (Datensatz bearbeiten inkl. Security, User ID Prüfung & Server Events)
     */
    protected function buildEditPage($tCfg)
    {
        $slug = self::tableSlug($tCfg['name']);
        $className = 'cTable_' . $slug;

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG Edit Page (" . $slug . "edit.php)\n */\n";
        $code .= "require_once dirname(__FILE__) . '/" . $slug . "info.php';\n\n";
        $code .= "\$Table = new " . $className . "();\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "\$Security->CheckPagePermission(\$Table->TableName, EW_ALLOW_EDIT);\n";
        $code .= "\$conn = ew_Connect();\n\n";

        $code .= <<<'PHP'
$keyStr = isset($_POST['key']) ? ew_Post('key', '') : ew_Get('key', '');
if ($keyStr === '') {
    ew_SetFailureMessage('Kein Primärschlüssel angegeben.');
    ew_Redirect($Table->TableSlug . 'list.php');
}

$keyParams = array();
$keyWhere = $Table->BuildKeyWhere($keyStr, $keyParams);
$uidFilter = $Security->BuildUserIDFilter($Table, $keyParams);
if ($uidFilter !== '') {
    $keyWhere .= ' AND ' . $uidFilter;
}
$currentRow = $conn->LoadRow($Table->SqlSelect() . ' WHERE ' . $keyWhere, $keyParams);

if (!$currentRow) {
    ew_SetFailureMessage('Der angeforderte Datensatz wurde nicht gefunden oder Sie haben keine Berechtigung, diesen zu bearbeiten.');
    ew_Redirect($Table->TableSlug . 'list.php');
}

$ownerField = isset($Table->Config['user_id_field']) ? trim($Table->Config['user_id_field']) : '';
$enforceOwner = ($Security->IsEnabled() && !$Security->IsSysAdmin() && !$Security->CanAdmin($Table->TableName) && $ownerField !== '' && isset($Table->Fields[$ownerField]));

$editFields = array();
$formValues = array();
foreach ($Table->Fields as $fName => $fMeta) {
    if (!empty($fMeta['edit'])) {
        $editFields[$fName] = $fMeta;
        $formValues[$fName] = isset($currentRow[$fName]) ? $currentRow[$fName] : '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        ew_SetFailureMessage('Ungültiges Sicherheits-Token (CSRF).');
    } else {
        $errors = array();
        $rsnew = array();

        foreach ($editFields as $fName => $fMeta) {
            if (!empty($fMeta['is_auto_increment']) || !empty($fMeta['read_only_edit']) || $fMeta['edit_tag'] === 'READONLY' || ($enforceOwner && $fName === $ownerField)) {
                continue;
            }
            $rawVal = ew_Post('x_' . $fName, '');
            $formValues[$fName] = $rawVal;
            $err = ew_ValidateField($fMeta, $rawVal);
            if ($err !== '') {
                $errors[] = $err;
            } else {
                $rsnew[$fName] = ew_PrepareDbValue($fMeta, $rawVal);
            }
        }

        // Server Event: Form_CustomValidate
        $customError = '';
        if (!$Table->Form_CustomValidate($customError)) {
            $errors[] = ($customError !== '') ? $customError : 'Validierungsfehler in Form_CustomValidate.';
        }

        if (!empty($errors)) {
            foreach ($errors as $e) {
                ew_SetFailureMessage($e);
            }
        } elseif (!empty($rsnew)) {
            // Server Event: Row_Updating($rsold, &$rsnew)
            $canUpdate = $Table->Row_Updating($currentRow, $rsnew);
            if ($canUpdate && !empty($rsnew)) {
                $updateSets = array();
                $updateParams = array();
                foreach ($rsnew as $colName => $colVal) {
                    $col = '`' . str_replace('`', '``', $colName) . '`';
                    $updateSets[] = $col . ' = ?';
                    $updateParams[] = $colVal;
                }
                $whereParams = array();
                $whereClause = $Table->BuildKeyWhere($keyStr, $whereParams);
                $uFilter = $Security->BuildUserIDFilter($Table, $whereParams);
                if ($uFilter !== '') {
                    $whereClause .= ' AND ' . $uFilter;
                }
                $allParams = array_merge($updateParams, $whereParams);
                $sql = 'UPDATE ' . $Table->SqlFrom() . ' SET ' . implode(', ', $updateSets) . ' WHERE ' . $whereClause;

                if ($conn->Execute($sql, $allParams)) {
                    // Server Event: Row_Updated($rsold, &$rsnew)
                    $Table->Row_Updated($currentRow, $rsnew);
                    ew_SetSuccessMessage('Die Änderungen wurden erfolgreich gespeichert.');
                    ew_Redirect($Table->TableSlug . 'list.php');
                } else {
                    ew_SetFailureMessage('Datenbankfehler beim Aktualisieren: ' . ew_HtmlEncode($conn->lastError));
                }
            } elseif (!$canUpdate && empty($_SESSION[EW_PROJECT_ID . '_FailureMessage'])) {
                ew_SetFailureMessage('Das Aktualisieren wurde durch ein Server-Event (Row_Updating) abgebrochen.');
            }
        }
    }
}

$Table->Row_Rendering($formValues);

$PageTitle = $Table->TableCaption;
$PageSubTitle = 'Bearbeiten';
require_once dirname(__FILE__) . '/header.php';
PHP;
        $editClientScript = $this->getTableClientScript($tCfg['name'], 'Edit_Client_Script');
        $editFormValidate = $this->getTableClientScript($tCfg['name'], 'Edit_Form_CustomValidate');
        if ($editClientScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Client Script (Edit Page)\n" . $editClientScript . "\n</script>\n<?php\n";
        }
        $code .= "\n?>\n<script>\nfunction ew_EditFormValidate(fobj) {\n";
        if ($editFormValidate !== '') {
            $code .= "    " . str_replace("\n", "\n    ", $editFormValidate) . "\n";
        }
        $code .= "    if (" . "EW_CONFIRM_ACTION) {\n";
        $code .= "        return confirm('Änderungen jetzt speichern?');\n";
        $code .= "    }\n";
        $code .= "    return true;\n";
        $code .= "}\n</script>\n<?php\n";

        $code .= <<<'PHP'
?>

<div class="box box-primary ewForm">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-pencil"></i> Datensatz bearbeiten</h3>
    </div>
    <form class="form-horizontal" method="post" action="<?php echo ew_HtmlEncode($Table->TableSlug . 'edit.php'); ?>" onsubmit="return ew_EditFormValidate(this);">
        <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
        <input type="hidden" name="key" value="<?php echo ew_HtmlEncode($keyStr); ?>">
        <div class="box-body">
            <?php foreach ($editFields as $fName => $fMeta): ?>
            <div class="form-group">
                <label for="x_<?php echo ew_HtmlEncode($fName); ?>" class="col-sm-3 control-label">
                    <?php echo ew_HtmlEncode($fMeta['caption']); ?>
                    <?php if (!empty($fMeta['required']) && empty($fMeta['is_auto_increment'])): ?><span class="ewRequired">*</span><?php endif; ?>
                </label>
                <div class="col-sm-9">
                    <?php if (!empty($fMeta['is_auto_increment'])): ?>
                        <span class="form-control-static"><strong><?php echo ew_HtmlEncode($formValues[$fName]); ?></strong></span>
                    <?php elseif ($enforceOwner && $fName === $ownerField): ?>
                        <input type="text" class="form-control" value="<?php echo ew_HtmlEncode($formValues[$fName]); ?>" readonly>
                    <?php else: ?>
                        <?php echo ew_RenderEditControl($fMeta, isset($formValues[$fName]) ? $formValues[$fName] : '', 'edit', 'x_'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="box-footer">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Speichern</button>
                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>" class="btn btn-default"><i class="fa fa-times"></i> Abbrechen</a>
            </div>
        </div>
    </form>
</div>

<?php
PHP;
        $editStartupScript = $this->getTableClientScript($tCfg['name'], 'Edit_Startup_Script');
        if ($editStartupScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Startup Script (Edit Page)\ndocument.addEventListener('DOMContentLoaded', function() {\n" . $editStartupScript . "\n});\n</script>\n<?php";
        }
        $code .= "\nrequire_once dirname(__FILE__) . '/footer.php'; ?>\n";
        return $code;
    }

    /**
     * Erzeugt <table_slug>view.php (Detailansicht eines Datensatzes inkl. Security, Detail-Unterlisten & Server Events)
     */
    protected function buildViewPage($tCfg)
    {
        $slug = self::tableSlug($tCfg['name']);
        $className = 'cTable_' . $slug;

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG View Page (" . $slug . "view.php)\n */\n";
        $code .= "require_once dirname(__FILE__) . '/" . $slug . "info.php';\n\n";
        $code .= "\$Table = new " . $className . "();\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "\$Security->CheckPagePermission(\$Table->TableName, EW_ALLOW_VIEW);\n";
        $code .= "\$conn = ew_Connect();\n\n";

        $code .= <<<'PHP'
$keyStr = ew_Get('key', '');
if ($keyStr === '') {
    ew_SetFailureMessage('Kein Primärschlüssel angegeben.');
    ew_Redirect($Table->TableSlug . 'list.php');
}

$keyParams = array();
$keyWhere = $Table->BuildKeyWhere($keyStr, $keyParams);
$uidFilter = $Security->BuildUserIDFilter($Table, $keyParams);
if ($uidFilter !== '') {
    $keyWhere .= ' AND ' . $uidFilter;
}
$row = $conn->LoadRow($Table->SqlSelect() . ' WHERE ' . $keyWhere, $keyParams);

if (!$row) {
    ew_SetFailureMessage('Datensatz nicht gefunden oder Zugriff verweigert.');
    ew_Redirect($Table->TableSlug . 'list.php');
}

$rowAttrs = array('class' => '', 'style' => '');
$Table->Row_Rendering($row);
$Table->Row_Rendered($row, $rowAttrs);

$PageTitle = $Table->TableCaption;
$PageSubTitle = 'Detailansicht';
require_once dirname(__FILE__) . '/header.php';
PHP;
        $viewClientScript = $this->getTableClientScript($tCfg['name'], 'View_Client_Script');
        if ($viewClientScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Client Script (View Page)\n" . $viewClientScript . "\n</script>\n<?php\n";
        }
        $code .= <<<'PHP'
?>

<div class="ewToolbar">
    <div>
        <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Zurück zur Liste</a>
        <?php if (!empty($Table->Config['add_page']) && $Security->CanAdd($Table->TableName)): ?>
            <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'add.php'); ?>" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> Hinzufügen</a>
        <?php endif; ?>
        <?php if (!empty($Table->Config['edit_page']) && $Security->CanEdit($Table->TableName)): ?>
            <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'edit.php?key=' . urlencode($keyStr)); ?>" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i> Bearbeiten</a>
        <?php endif; ?>
        <?php if (!empty($Table->Config['delete_page']) && $Security->CanDelete($Table->TableName)): ?>
            <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'delete.php?key=' . urlencode($keyStr)); ?>" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Löschen</a>
        <?php endif; ?>
    </div>
</div>

<div class="box box-primary ewForm">
    <div class="box-body no-padding">
        <table class="table table-bordered table-striped">
            <tbody>
                <?php foreach ($Table->Fields as $fName => $fMeta):
                    if (empty($fMeta['view'])) continue;
                ?>
                <tr>
                    <th style="width:28%; background:#f9fafc;"><?php echo ew_HtmlEncode($fMeta['caption']); ?></th>
                    <td><?php echo ew_FormatViewValue(isset($row[$fName]) ? $row[$fName] : null, $fMeta); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Verknüpfte Detail-Unterlisten (Master/Detail View) unterhalb des Hauptdatensatzes anzeigen
if (!empty($Table->Config['master_details']) && is_array($Table->Config['master_details'])):
    foreach ($Table->Config['master_details'] as $mdIdx => $mdCfg):
        $dTable = isset($mdCfg['detail_table']) ? $mdCfg['detail_table'] : '';
        $mField = isset($mdCfg['master_field']) ? $mdCfg['master_field'] : '';
        $dField = isset($mdCfg['detail_field']) ? $mdCfg['detail_field'] : '';
        if ($dTable === '' || $mField === '' || $dField === '') continue;
        if (!$Security->CanList($dTable)) continue;
        $dSlug = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $dTable));
        $mVal = isset($row[$mField]) ? (string)$row[$mField] : '';
        $previewUrl = $dSlug . 'preview.php?f=' . urlencode($mVal) . '&master=' . urlencode($Table->TableSlug) . '&mfield=' . urlencode($mField) . '&dfield=' . urlencode($dField);
        $domId = 'view_detail_' . $mdIdx;
?>
<div id="ewPreviewContent_<?php echo ew_HtmlEncode($domId); ?>" style="margin-bottom:15px;"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ew_LoadDetailPreviewUrl === 'function') {
        ew_LoadDetailPreviewUrl('<?php echo ew_HtmlEncode($domId); ?>', '<?php echo ew_HtmlEncode($previewUrl); ?>', null);
    }
});
</script>
<?php
    endforeach;
endif;
?>

<?php
PHP;
        $viewStartupScript = $this->getTableClientScript($tCfg['name'], 'View_Startup_Script');
        if ($viewStartupScript !== '') {
            $code .= "\n?>\n<script>\n// PHPMacherNG Table Startup Script (View Page)\ndocument.addEventListener('DOMContentLoaded', function() {\n" . $viewStartupScript . "\n});\n</script>\n<?php";
        }
        $code .= "\nrequire_once dirname(__FILE__) . '/footer.php'; ?>\n";
        return $code;
    }

    /**
     * Erzeugt <table_slug>delete.php (Einzel- und Multi-Delete mit Vorschau, Bestätigung, Security & Server Events)
     */
    protected function buildDeletePage($tCfg)
    {
        $slug = self::tableSlug($tCfg['name']);
        $className = 'cTable_' . $slug;

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG Delete & Multi-Delete Page (" . $slug . "delete.php)\n */\n";
        $code .= "require_once dirname(__FILE__) . '/" . $slug . "info.php';\n\n";
        $code .= "\$Table = new " . $className . "();\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "\$Security->CheckPagePermission(\$Table->TableName, EW_ALLOW_DELETE);\n";
        $code .= "\$conn = ew_Connect();\n\n";

        $code .= <<<'PHP'
// Keys aus GET (Einzellöschung) oder POST (Multi-Delete) sammeln
$keys = array();
if (isset($_POST['key_m']) && is_array($_POST['key_m'])) {
    foreach ($_POST['key_m'] as $k) {
        if ((string)$k !== '') $keys[] = (string)$k;
    }
} elseif (ew_Get('key', '') !== '') {
    $keys[] = ew_Get('key', '');
}

if (empty($keys)) {
    ew_SetFailureMessage('Keine Datensätze zum Löschen ausgewählt.');
    ew_Redirect($Table->TableSlug . 'list.php');
}

// Wenn Bestätigung abgeschickt wurde -> Löschen ausführen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ew_Post('a_delete', '') === 'D') {
    if (!ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        ew_SetFailureMessage('Ungültiges Sicherheits-Token (CSRF).');
    } else {
        $deletedCount = 0;
        foreach ($keys as $kStr) {
            $delParams = array();
            $delWhere = $Table->BuildKeyWhere($kStr, $delParams);
            $uidFilter = $Security->BuildUserIDFilter($Table, $delParams);
            if ($uidFilter !== '') {
                $delWhere .= ' AND ' . $uidFilter;
            }
            $delRow = $conn->LoadRow($Table->SqlSelect() . ' WHERE ' . $delWhere, $delParams);
            if (!$delRow) {
                continue;
            }
            // Server Event: Row_Deleting(&$rs)
            if ($Table->Row_Deleting($delRow)) {
                $sql = 'DELETE FROM ' . $Table->SqlFrom() . ' WHERE ' . $delWhere;
                if ($conn->Execute($sql, $delParams)) {
                    $deletedCount++;
                    // Server Event: Row_Deleted(&$rs)
                    $Table->Row_Deleted($delRow);
                } else {
                    ew_SetFailureMessage('Fehler beim Löschen von Schlüssel ' . ew_HtmlEncode($kStr) . ': ' . ew_HtmlEncode($conn->lastError));
                }
            }
        }
        if ($deletedCount > 0) {
            ew_SetSuccessMessage($deletedCount . ' Datensatz/Datensätze erfolgreich gelöscht.');
        }
        ew_Redirect($Table->TableSlug . 'list.php');
    }
}

// Zu löschende Datensätze zur Vorschau laden
$orConds = array();
$previewParams = array();
foreach ($keys as $kStr) {
    $orConds[] = $Table->BuildKeyWhere($kStr, $previewParams);
}
$wherePreview = '(' . implode(' OR ', $orConds) . ')';
$uidFilterPrev = $Security->BuildUserIDFilter($Table, $previewParams);
if ($uidFilterPrev !== '') {
    $wherePreview .= ' AND ' . $uidFilterPrev;
}
$previewRows = $conn->LoadRows($Table->SqlSelect() . ' WHERE ' . $wherePreview, $previewParams);
if (!is_array($previewRows) || empty($previewRows)) {
    ew_SetFailureMessage('Die ausgewählten Datensätze wurden nicht gefunden oder Sie haben keine Berechtigung.');
    ew_Redirect($Table->TableSlug . 'list.php');
}

$listFields = array();
foreach ($Table->Fields as $fName => $fMeta) {
    if (!empty($fMeta['list'])) {
        $listFields[$fName] = $fMeta;
    }
}

$PageTitle = $Table->TableCaption;
$PageSubTitle = 'Löschen bestätigen';
require_once dirname(__FILE__) . '/header.php';
?>

<div class="callout callout-danger">
    <h4><i class="fa fa-warning"></i> Löschen bestätigen</h4>
    Möchten Sie die folgenden <strong><?php echo count($previewRows); ?></strong> Datensätze wirklich unwiderruflich löschen?
</div>

<form method="post" action="<?php echo ew_HtmlEncode($Table->TableSlug . 'delete.php'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
    <input type="hidden" name="a_delete" value="D">
    <?php foreach ($keys as $kStr): ?>
        <input type="hidden" name="key_m[]" value="<?php echo ew_HtmlEncode($kStr); ?>">
    <?php endforeach; ?>

    <div class="ewGrid">
        <div class="table-responsive">
            <table class="table table-bordered table-striped ewTable">
                <thead>
                    <tr>
                        <?php foreach ($listFields as $fName => $fMeta): ?>
                            <th><?php echo ew_HtmlEncode($fMeta['caption']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewRows as $r):
                        $rowAttrs = array('class' => '', 'style' => '');
                        $Table->Row_Rendering($r);
                        $Table->Row_Rendered($r, $rowAttrs);
                    ?>
                    <tr>
                        <?php foreach ($listFields as $fName => $fMeta): ?>
                            <td><?php echo ew_FormatViewValue(isset($r[$fName]) ? $r[$fName] : null, $fMeta); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="ewGridLowerPanel">
            <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Endgültig löschen</button>
            <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>" class="btn btn-default"><i class="fa fa-times"></i> Abbrechen</a>
        </div>
    </div>
</form>

<?php require_once dirname(__FILE__) . '/footer.php'; ?>
PHP;
        return $code;
    }

    /**
     * Erzeugt <table_slug>update.php (PHPMacherNG Multi-Update für mehrere ausgewählte Datensätze inkl. Security & Server Events)
     */
    protected function buildMultiUpdatePage($tCfg)
    {
        $slug = self::tableSlug($tCfg['name']);
        $className = 'cTable_' . $slug;

        $code = "<?php\n";
        $code .= "/**\n * PHPMacherNG Multi-Update Page (" . $slug . "update.php)\n */\n";
        $code .= "require_once dirname(__FILE__) . '/" . $slug . "info.php';\n\n";
        $code .= "\$Table = new " . $className . "();\n";
        $code .= "\$Security = ew_Security();\n";
        $code .= "\$Security->CheckPagePermission(\$Table->TableName, EW_ALLOW_EDIT);\n";
        $code .= "\$conn = ew_Connect();\n\n";

        $code .= <<<'PHP'
$keys = array();
if (isset($_POST['key_m']) && is_array($_POST['key_m'])) {
    foreach ($_POST['key_m'] as $k) {
        if ((string)$k !== '') $keys[] = (string)$k;
    }
}

if (empty($keys)) {
    ew_SetFailureMessage('Keine Datensätze für Multi-Update ausgewählt.');
    ew_Redirect($Table->TableSlug . 'list.php');
}

$ownerField = isset($Table->Config['user_id_field']) ? trim($Table->Config['user_id_field']) : '';
$enforceOwner = ($Security->IsEnabled() && !$Security->IsSysAdmin() && !$Security->CanAdmin($Table->TableName) && $ownerField !== '');

$updateFields = array();
foreach ($Table->Fields as $fName => $fMeta) {
    if (!empty($fMeta['multi_update']) && empty($fMeta['is_auto_increment'])) {
        if ($enforceOwner && $fName === $ownerField) {
            continue;
        }
        $updateFields[$fName] = $fMeta;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ew_Post('a_update', '') === 'U') {
    if (!ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        ew_SetFailureMessage('Ungültiges Sicherheits-Token (CSRF).');
    } else {
        $errors = array();
        $baseRsNew = array();

        foreach ($updateFields as $fName => $fMeta) {
            // Nur Felder aktualisieren, deren Multi-Update-Checkbox (u_<field>) aktiviert wurde
            if (ew_Post('u_' . $fName, '') === '1') {
                $rawVal = ew_Post('x_' . $fName, '');
                $err = ew_ValidateField($fMeta, $rawVal);
                if ($err !== '') {
                    $errors[] = $err;
                } else {
                    $baseRsNew[$fName] = ew_PrepareDbValue($fMeta, $rawVal);
                }
            }
        }

        $customError = '';
        if (!$Table->Form_CustomValidate($customError)) {
            $errors[] = ($customError !== '') ? $customError : 'Validierungsfehler in Form_CustomValidate.';
        }

        if (empty($baseRsNew) && empty($errors)) {
            ew_SetFailureMessage('Bitte aktivieren Sie mindestens ein Feld (Checkbox), das aktualisiert werden soll.');
        } elseif (!empty($errors)) {
            foreach ($errors as $e) {
                ew_SetFailureMessage($e);
            }
        } else {
            $updatedCount = 0;
            foreach ($keys as $kStr) {
                $kParams = array();
                $kWhere = $Table->BuildKeyWhere($kStr, $kParams);
                $uidFilter = $Security->BuildUserIDFilter($Table, $kParams);
                if ($uidFilter !== '') {
                    $kWhere .= ' AND ' . $uidFilter;
                }
                $oldRow = $conn->LoadRow($Table->SqlSelect() . ' WHERE ' . $kWhere, $kParams);
                if (!$oldRow) continue;

                $rsnew = $baseRsNew;
                if ($Table->Row_Updating($oldRow, $rsnew) && !empty($rsnew)) {
                    $updateSets = array();
                    $updateParams = array();
                    foreach ($rsnew as $colName => $colVal) {
                        $col = '`' . str_replace('`', '``', $colName) . '`';
                        $updateSets[] = $col . ' = ?';
                        $updateParams[] = $colVal;
                    }
                    $allParams = array_merge($updateParams, $kParams);
                    $sql = 'UPDATE ' . $Table->SqlFrom() . ' SET ' . implode(', ', $updateSets) . ' WHERE ' . $kWhere;
                    if ($conn->Execute($sql, $allParams)) {
                        $updatedCount++;
                        $Table->Row_Updated($oldRow, $rsnew);
                    }
                }
            }
            ew_SetSuccessMessage($updatedCount . ' Datensatz/Datensätze erfolgreich per Multi-Update aktualisiert.');
            ew_Redirect($Table->TableSlug . 'list.php');
        }
    }
}

$PageTitle = $Table->TableCaption;
$PageSubTitle = 'Multi-Update (' . count($keys) . ' Datensätze ausgewählt)';
require_once dirname(__FILE__) . '/header.php';
?>

<div class="box box-warning ewForm">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-pencil-square-o"></i> Multi-Update für <?php echo count($keys); ?> ausgewählte Datensätze</h3>
        <p class="text-muted" style="margin:5px 0 0 0;">Markieren Sie das Kontrollkästchen vor den Feldern, die für alle ausgewählten Datensätze überschrieben werden sollen.</p>
    </div>

    <form class="form-horizontal" method="post" action="<?php echo ew_HtmlEncode($Table->TableSlug . 'update.php'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
        <input type="hidden" name="a_update" value="U">
        <?php foreach ($keys as $kStr): ?>
            <input type="hidden" name="key_m[]" value="<?php echo ew_HtmlEncode($kStr); ?>">
        <?php endforeach; ?>

        <div class="box-body">
            <div class="form-group" style="border-bottom:1px solid #eee; padding-bottom:8px;">
                <div class="col-sm-offset-1 col-sm-11">
                    <label style="cursor:pointer;">
                        <input type="checkbox" onclick="var cbs = document.querySelectorAll('.ewMultiUpdateCheck'); for(var i=0;i<cbs.length;i++) cbs[i].checked=this.checked;">
                        <strong>Alle Felder auswählen</strong>
                    </label>
                </div>
            </div>

            <?php foreach ($updateFields as $fName => $fMeta):
                $checked = (ew_Post('u_' . $fName, '') === '1') ? ' checked' : '';
                $curVal = ew_Post('x_' . $fName, isset($fMeta['default_value']) ? $fMeta['default_value'] : '');
            ?>
            <div class="form-group">
                <div class="col-sm-4 control-label" style="display:flex; align-items:center; justify-content:flex-end; gap:8px;">
                    <input type="checkbox" name="u_<?php echo ew_HtmlEncode($fName); ?>" id="u_<?php echo ew_HtmlEncode($fName); ?>" value="1" class="ewMultiUpdateCheck"<?php echo $checked; ?>>
                    <label for="u_<?php echo ew_HtmlEncode($fName); ?>" style="margin:0; cursor:pointer;">
                        <?php echo ew_HtmlEncode($fMeta['caption']); ?>
                    </label>
                </div>
                <div class="col-sm-8" onclick="document.getElementById('u_<?php echo ew_HtmlEncode($fName); ?>').checked = true;">
                    <?php echo ew_RenderEditControl($fMeta, $curVal, 'edit', 'x_'); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="box-footer">
            <div class="col-sm-offset-4 col-sm-8">
                <button type="submit" class="btn btn-warning"><i class="fa fa-check"></i> Ausgewählte Datensätze aktualisieren</button>
                <a href="<?php echo ew_HtmlEncode($Table->TableSlug . 'list.php'); ?>" class="btn btn-default"><i class="fa fa-times"></i> Abbrechen</a>
            </div>
        </div>
    </form>
</div>

<?php require_once dirname(__FILE__) . '/footer.php'; ?>
PHP;
        return $code;
    }
}
