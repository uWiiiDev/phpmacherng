<?php
/**
 * PHPMacherNG Table Info Class (v_bestellungen_uebersichtinfo.php)
 * Tabelle: v_bestellungen_uebersicht
 */

require_once dirname(__FILE__) . '/phpfn14.php';

class cTable_v_bestellungen_uebersicht
{
    public $TableName = 'v_bestellungen_uebersicht';
    public $TableSlug = 'v_bestellungen_uebersicht';
    public $TableCaption = 'V Bestellungen Uebersicht';
    public $TableType = 'VIEW';
    public $PrimaryKeys = array (
);
    public $Fields = array();
    public $Config = array();

    public function __construct()
    {
        $this->Config = array (
  'name' => 'v_bestellungen_uebersicht',
  'type' => 'VIEW',
  'caption' => 'V Bestellungen Uebersicht',
  'enabled' => true,
  'primary_keys' => 
  array (
  ),
  'display_field' => 'kunde_firma',
  'icon' => 'fa-table',
  'list_page' => true,
  'add_page' => false,
  'edit_page' => false,
  'view_page' => false,
  'delete_page' => false,
  'multi_delete' => false,
  'multi_update' => false,
  'quick_search' => true,
  'extended_search' => true,
  'records_per_page' => 0,
  'default_order_by' => '',
  'default_order_dir' => 'DESC',
  'user_id_field' => '',
  'fields' => 
  array (
    'bestellung_id' => 
    array (
      'name' => 'bestellung_id',
      'caption' => 'Bestell-Nr.',
      'sql_type' => 'int(11)',
      'base_type' => 'int',
      'ew_type' => 'INTEGER',
      'length' => 11,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '0',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => false,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 11,
      'max_length' => 11,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => 'Integer',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'bestelldatum' => 
    array (
      'name' => 'bestelldatum',
      'caption' => 'Bestelldatum',
      'sql_type' => 'date',
      'base_type' => 'date',
      'ew_type' => 'DATE',
      'length' => 0,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => false,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'DATE',
      'edit_tag' => 'DATE',
      'input_size' => 30,
      'max_length' => 0,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => 'Date',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'kunde_firma' => 
    array (
      'name' => 'kunde_firma',
      'caption' => 'Firma / Organisation',
      'sql_type' => 'varchar(120)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 120,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => true,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 30,
      'max_length' => 120,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => '',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'kunde_stadt' => 
    array (
      'name' => 'kunde_stadt',
      'caption' => 'Stadt',
      'sql_type' => 'varchar(80)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 80,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => true,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 30,
      'max_length' => 80,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => '',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'artikelnummer' => 
    array (
      'name' => 'artikelnummer',
      'caption' => 'SKU / Code',
      'sql_type' => 'varchar(40)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 40,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => true,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 40,
      'max_length' => 40,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => '',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'produktname' => 
    array (
      'name' => 'produktname',
      'caption' => 'Produktname',
      'sql_type' => 'varchar(150)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 150,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => true,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 30,
      'max_length' => 150,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => '',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'menge' => 
    array (
      'name' => 'menge',
      'caption' => 'Menge',
      'sql_type' => 'int(11)',
      'base_type' => 'int',
      'ew_type' => 'INTEGER',
      'length' => 11,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '1',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => false,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 11,
      'max_length' => 11,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => 'Integer',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'gesamtbetrag' => 
    array (
      'name' => 'gesamtbetrag',
      'caption' => 'Gesamtbetrag',
      'sql_type' => 'decimal(10,2)',
      'base_type' => 'decimal',
      'ew_type' => 'FLOAT',
      'length' => 10,
      'scale' => 2,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => false,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'CURRENCY',
      'edit_tag' => 'TEXT',
      'input_size' => 10,
      'max_length' => 10,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => 'Float',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'status' => 
    array (
      'name' => 'status',
      'caption' => 'Bestellstatus',
      'sql_type' => 'enum(\'offen\',\'in bearbeitung\',\'versendet\',\'abgeschlossen\',\'storniert\')',
      'base_type' => 'enum',
      'ew_type' => 'STRING',
      'length' => 0,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => 'Offen',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => true,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'SELECT',
      'input_size' => 30,
      'max_length' => 0,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => '',
      'options' => 'offen:Offen,in bearbeitung:In Bearbeitung,versendet:Versendet,abgeschlossen:Abgeschlossen,storniert:Storniert',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
  ),
  'master_details' => 
  array (
  ),
  'charts' => 
  array (
  ),
);
        $this->Fields = $this->Config['fields'];
    }

    public function SqlFrom()
    {
        return '`' . str_replace('`', '``', $this->TableName) . '`';
    }

    public function SqlSelect()
    {
        return 'SELECT * FROM ' . $this->SqlFrom();
    }

    public function SqlCount()
    {
        return 'SELECT COUNT(*) FROM ' . $this->SqlFrom();
    }

    /**
     * Erzeugt einen zusammengesetzten Primärschlüssel-String für URLs/Checkboxen
     */
    public function GetRowKey($row)
    {
        $parts = array();
        foreach ($this->PrimaryKeys as $pk) {
            $parts[] = isset($row[$pk]) ? (string)$row[$pk] : '';
        }
        return implode('|', $parts);
    }

    /**
     * Zerlegt einen Key-String zurück in ein Array für WHERE-Bedingungen
     */
    public function BuildKeyWhere($keyStr, &$params)
    {
        $parts = explode('|', (string)$keyStr);
        $conds = array();
        foreach ($this->PrimaryKeys as $idx => $pk) {
            $col = '`' . str_replace('`', '``', $pk) . '`';
            $conds[] = $col . ' = ?';
            $params[] = isset($parts[$idx]) ? $parts[$idx] : '';
        }
        return '(' . implode(' AND ', $conds) . ')';
    }

    // ========================================================================
    // PHPMacherNG Table Server Events (Projektspezifisch für v_bestellungen_uebersicht)
    // ========================================================================

    public function Recordset_Selecting(&$filter)
    {
        // Modify $filter (SQL WHERE condition) dynamically
    }

    public function Row_Rendering(&$rs)
    {
        // Called before rendering a row
    }

    public function Row_Rendered(&$rs, &$rowAttrs)
    {
        // Called after row values are prepared
    }

    public function Row_Inserting($rsold, &$rsnew)
    {
        // Return false to cancel insert
        return true;
    }

    public function Row_Inserted($rsold, &$rsnew)
    {
        // Called after successful insert
    }

    public function Row_Updating($rsold, &$rsnew)
    {
        // Return false to cancel update
        return true;
    }

    public function Row_Updated($rsold, &$rsnew)
    {
        // Called after successful update
    }

    public function Row_Deleting(&$rs)
    {
        // Return false to cancel delete
        return true;
    }

    public function Row_Deleted(&$rs)
    {
        // Called after successful delete
    }

    public function Form_CustomValidate(&$customError)
    {
        // Return false and set $customError to fail validation
        return true;
    }
}
