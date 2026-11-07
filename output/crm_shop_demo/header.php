<?php
require_once dirname(__FILE__) . '/phpfn14.php';
$Security = ew_Security();
$ewMenuItems = array (
  0 => 
  array (
    'id' => 'cf_dashboard_php',
    'parent_id' => '',
    'caption' => 'Management Dashboard',
    'url' => 'dashboard.php',
    'icon' => 'fa-dashboard',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'custom_file',
    'table' => 'dashboard.php',
    'slug' => 'dashboard.php',
  ),
  1 => 
  array (
    'id' => 'grp_crm_shop',
    'parent_id' => '',
    'caption' => 'CRM & Shop Betrieb',
    'url' => '#',
    'icon' => 'fa-briefcase',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'custom',
    'table' => '',
    'slug' => '',
  ),
  2 => 
  array (
    'id' => 'grp_stammdaten',
    'parent_id' => 'grp_crm_shop',
    'caption' => 'Katalog & Stammdaten',
    'url' => '#',
    'icon' => 'fa-folder-open-o',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'custom',
    'table' => '',
    'slug' => '',
  ),
  3 => 
  array (
    'id' => 'grp_system_sec',
    'parent_id' => '',
    'caption' => 'Benutzer & Rechte',
    'url' => '#',
    'icon' => 'fa-users',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'custom',
    'table' => '',
    'slug' => '',
  ),
  4 => 
  array (
    'id' => 'tbl_benutzer',
    'parent_id' => 'grp_system_sec',
    'caption' => 'Benutzer',
    'url' => 'benutzerlist.php',
    'icon' => 'fa-list-alt',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'benutzer',
    'slug' => 'benutzer',
  ),
  5 => 
  array (
    'id' => 'tbl_bestellungen',
    'parent_id' => 'grp_crm_shop',
    'caption' => 'Bestellungen',
    'url' => 'bestellungenlist.php',
    'icon' => 'fa-shopping-cart',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'bestellungen',
    'slug' => 'bestellungen',
  ),
  6 => 
  array (
    'id' => 'tbl_kategorien',
    'parent_id' => 'grp_stammdaten',
    'caption' => 'Kategorien',
    'url' => 'kategorienlist.php',
    'icon' => 'fa-folder-open',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'kategorien',
    'slug' => 'kategorien',
  ),
  7 => 
  array (
    'id' => 'tbl_kunden',
    'parent_id' => 'grp_crm_shop',
    'caption' => 'Kunden',
    'url' => 'kundenlist.php',
    'icon' => 'fa-users',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'kunden',
    'slug' => 'kunden',
  ),
  8 => 
  array (
    'id' => 'tbl_produkte',
    'parent_id' => 'grp_stammdaten',
    'caption' => 'Produkte',
    'url' => 'produktelist.php',
    'icon' => 'fa-cube',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'produkte',
    'slug' => 'produkte',
  ),
  9 => 
  array (
    'id' => 'tbl_userlevelpermissions',
    'parent_id' => 'grp_system_sec',
    'caption' => 'Userlevelpermissions',
    'url' => 'userlevelpermissionslist.php',
    'icon' => 'fa-list-alt',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'userlevelpermissions',
    'slug' => 'userlevelpermissions',
  ),
  10 => 
  array (
    'id' => 'tbl_userlevels',
    'parent_id' => 'grp_system_sec',
    'caption' => 'Userlevels',
    'url' => 'userlevelslist.php',
    'icon' => 'fa-list-alt',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'userlevels',
    'slug' => 'userlevels',
  ),
  11 => 
  array (
    'id' => 'tbl_v_bestellungen_uebersicht',
    'parent_id' => '',
    'caption' => 'V Bestellungen Uebersicht',
    'url' => 'v_bestellungen_uebersichtlist.php',
    'icon' => 'fa-table',
    'target' => '_self',
    'is_header' => false,
    'allow_anon' => false,
    'source_type' => 'table',
    'table' => 'v_bestellungen_uebersicht',
    'slug' => 'v_bestellungen_uebersicht',
  ),
);
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
    <script src="assets/vendor/jquery/jquery.min.js"></script></head>
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