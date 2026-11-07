<?php
/**
 * PHPMacherNG Delete & Multi-Delete Page (userlevelsdelete.php)
 */
require_once dirname(__FILE__) . '/userlevelsinfo.php';

$Table = new cTable_userlevels();
$Security = ew_Security();
$Security->CheckPagePermission($Table->TableName, EW_ALLOW_DELETE);
$conn = ew_Connect();

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