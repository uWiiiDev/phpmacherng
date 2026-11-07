<?php
/**
 * PHPMacherNG Multi-Update Page (kategorienupdate.php)
 */
require_once dirname(__FILE__) . '/kategorieninfo.php';

$Table = new cTable_kategorien();
$Security = ew_Security();
$Security->CheckPagePermission($Table->TableName, EW_ALLOW_EDIT);
$conn = ew_Connect();

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