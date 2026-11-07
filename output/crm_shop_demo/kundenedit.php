<?php
/**
 * PHPMacherNG Edit Page (kundenedit.php)
 */
require_once dirname(__FILE__) . '/kundeninfo.php';

$Table = new cTable_kunden();
$Security = ew_Security();
$Security->CheckPagePermission($Table->TableName, EW_ALLOW_EDIT);
$conn = ew_Connect();

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
?>
<script>
function ew_EditFormValidate(fobj) {
    if (EW_CONFIRM_ACTION) {
        return confirm('Änderungen jetzt speichern?');
    }
    return true;
}
</script>
<?php
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
require_once dirname(__FILE__) . '/footer.php'; ?>
