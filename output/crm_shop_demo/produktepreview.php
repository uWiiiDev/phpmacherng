<?php
/**
 * PHPMacherNG Detail Preview Row (produktepreview.php)
 * Liefert eine kompakte HTML-Untertabelle per AJAX für Master/Detail-Listen
 */
require_once dirname(__FILE__) . '/produkteinfo.php';

$Table = new cTable_produkte();
$Security = ew_Security();
if (!$Security->CanList($Table->TableName)) {
    echo '<div class="alert alert-warning" style="margin:0;"><i class="fa fa-lock"></i> Keine Berechtigung für diese Detail-Tabelle.</div>';
    exit;
}
$conn = ew_Connect();

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