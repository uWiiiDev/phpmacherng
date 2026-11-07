<?php
/**
 * PHPMacherNG List Page (kategorienlist.php)
 */
require_once dirname(__FILE__) . '/kategorieninfo.php';

$Table = new cTable_kategorien();
$Security = ew_Security();
$Security->CheckPagePermission($Table->TableName, EW_ALLOW_LIST);
$conn = ew_Connect();
$sessionPrefix = EW_PROJECT_ID . '_' . $Table->TableSlug . '_';

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
require_once dirname(__FILE__) . '/header.php';?>

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
require_once dirname(__FILE__) . '/footer.php'; ?>
