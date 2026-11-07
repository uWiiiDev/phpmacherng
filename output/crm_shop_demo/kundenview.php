<?php
/**
 * PHPMacherNG View Page (kundenview.php)
 */
require_once dirname(__FILE__) . '/kundeninfo.php';

$Table = new cTable_kunden();
$Security = ew_Security();
$Security->CheckPagePermission($Table->TableName, EW_ALLOW_VIEW);
$conn = ew_Connect();

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
require_once dirname(__FILE__) . '/header.php';?>

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
require_once dirname(__FILE__) . '/footer.php'; ?>
