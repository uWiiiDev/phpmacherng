<?php
/**
 * PHPMacherNG Dynamic User Level Permissions Matrix (userpriv.php)
 */
require_once dirname(__FILE__) . '/phpfn14.php';

$Security = ew_Security();
if (!$Security->IsSysAdmin()) {
    ew_SetFailureMessage('Zugriff verweigert: Nur Administratoren dürfen Rollen-Berechtigungen verwalten.');
    ew_Redirect('index.php');
}

$conn = ew_Connect();
$ProjectTables = array (
  'benutzer' => 'Benutzer',
  'bestellungen' => 'Bestellungen',
  'kategorien' => 'Kategorien',
  'kunden' => 'Kunden',
  'produkte' => 'Produkte',
  'userlevelpermissions' => 'Userlevelpermissions',
  'userlevels' => 'Userlevels',
  'v_bestellungen_uebersicht' => 'V Bestellungen Uebersicht',
  'dashboard.php' => '[Custom File] Management Dashboard',
);
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