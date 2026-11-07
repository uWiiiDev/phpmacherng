<?php
/**
 * PHPMacherNG Add Page (bestellungenadd.php)
 */
require_once dirname(__FILE__) . '/bestellungeninfo.php';

$Table = new cTable_bestellungen();
$Security = ew_Security();
$Security->CheckPagePermission($Table->TableName, EW_ALLOW_ADD);
$conn = ew_Connect();
$sessionPrefix = EW_PROJECT_ID . '_' . $Table->TableSlug . '_';

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
?>
<script>
function ew_AddFormValidate(fobj) {
    if (EW_CONFIRM_ACTION) {
        return confirm('Möchten Sie diesen Datensatz jetzt speichern?');
    }
    return true;
}
</script>
<?php
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
require_once dirname(__FILE__) . '/footer.php'; ?>
