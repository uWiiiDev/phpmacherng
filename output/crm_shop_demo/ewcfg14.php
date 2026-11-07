<?php
/**
 * PHPMacherNG (v14) Konfigurationsdatei (ewcfg14.php)
 * Kompatibel mit PHP 5.4 bis PHP 8.4+
 * Generiert am: 2026-10-20 16:29:02
 */

if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Berlin');
}

// Projekt-Identifikation
define('EW_PROJECT_NAME', 'CRM_Shop_Demo');
define('EW_PROJECT_ID', '{DEMO2018-4000-8000-PHPMACHERNG}');
define('EW_PROJECT_TITLE', 'CRM & Shop Verwaltung (PHPMacherNG)');

// Datenbank-Konfiguration
define('EW_CONN_HOST', '127.0.0.1');
define('EW_CONN_PORT', 3306);
define('EW_CONN_USER', 'root');
define('EW_CONN_PASS', '');
define('EW_CONN_DB', 'phpmaker_demo');
define('EW_CONN_CHARSET', 'utf8');

// Darstellung & Verhalten
define('EW_UI_TEMPLATE', 'adminlte2');
define('EW_THEME_SKIN', 'skin-green');
define('EW_LAYOUT_STYLE', 'sidebar-mini');
define('EW_RECORDS_PER_PAGE', 20);
define('EW_PAGE_SIZES', '10,20,50,100,ALL');
define('EW_DATE_FORMAT', 'd.m.Y');
define('EW_CURRENCY_SYMBOL', '€');
define('EW_FOOTER_TEXT', '<strong>Copyright &copy; 2026 CRM &amp; Shop Demo.</strong> Generiert mit PHPMacherNG.');
define('EW_DEBUG_ENABLED', false);
define('EW_CONFIRM_ACTION', true);

// PHPMacherNG Security Bitmasks
define('EW_ALLOW_ADD', 1);
define('EW_ALLOW_DELETE', 2);
define('EW_ALLOW_EDIT', 4);
define('EW_ALLOW_LIST', 8);
define('EW_ALLOW_ADMIN', 16);
define('EW_ALLOW_VIEW', 32);
define('EW_ALLOW_SEARCH', 64);
define('EW_ALLOW_ALL', 127);

$EW_SECURITY_CONFIG = array (
  'enabled' => true,
  'hardcoded_admin_enabled' => true,
  'admin_username' => 'admin',
  'admin_password' => '12345678',
  'use_user_table' => true,
  'user_table' => 'benutzer',
  'username_field' => 'benutzername',
  'password_field' => 'passwort',
  'password_hash' => 'PLAIN_OR_HASH',
  'user_id_field' => 'benutzer_id',
  'parent_user_id_field' => 'vorgesetzter_id',
  'user_level_field' => 'userlevel_id',
  'user_level_security' => true,
  'dynamic_user_levels' => true,
  'user_level_table' => 'userlevels',
  'user_level_id_field' => 'userlevelid',
  'user_level_name_field' => 'userlevelname',
  'user_level_priv_table' => 'userlevelpermissions',
  'user_level_priv_table_name_field' => 'tablename',
  'user_level_priv_id_field' => 'userlevelid',
  'user_level_priv_field' => 'permission',
  'static_user_levels' => 
  array (
    0 => 
    array (
      'id' => -2,
      'name' => 'Anonymous',
    ),
    1 => 
    array (
      'id' => -1,
      'name' => 'Administrator',
    ),
    2 => 
    array (
      'id' => 0,
      'name' => 'Default (Mitarbeiter)',
    ),
    3 => 
    array (
      'id' => 1,
      'name' => 'Manager (Abteilungsleiter)',
    ),
    4 => 
    array (
      'id' => 2,
      'name' => 'ReadOnly (Nur Lesen)',
    ),
  ),
  'static_permissions' => 
  array (
    'kategorien' => 
    array (
      0 => 109,
      1 => 111,
      2 => 104,
      -2 => 0,
      -1 => 127,
    ),
    'produkte' => 
    array (
      0 => 109,
      1 => 111,
      2 => 104,
      -2 => 0,
      -1 => 127,
    ),
    'kunden' => 
    array (
      0 => 109,
      1 => 111,
      2 => 104,
      -2 => 0,
      -1 => 127,
    ),
    'bestellungen' => 
    array (
      0 => 109,
      1 => 111,
      2 => 104,
      -2 => 0,
      -1 => 127,
    ),
    'benutzer' => 
    array (
      0 => 0,
      1 => 0,
      2 => 0,
      -2 => 0,
      -1 => 127,
    ),
    'userlevelpermissions' => 
    array (
      0 => 0,
      1 => 0,
      2 => 0,
      -2 => 0,
      -1 => 127,
    ),
    'userlevels' => 
    array (
      0 => 0,
      1 => 0,
      2 => 0,
      -2 => 0,
      -1 => 127,
    ),
    'v_bestellungen_uebersicht' => 
    array (
      0 => 109,
      1 => 111,
      2 => 104,
      -2 => 0,
      -1 => 127,
    ),
    'dashboard.php' => 
    array (
      0 => 104,
      1 => 104,
      2 => 104,
      -2 => 0,
      -1 => 127,
    ),
  ),
);
