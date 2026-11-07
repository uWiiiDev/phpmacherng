<?php
/**
 * PHPMacherNG Table Info Class (bestellungeninfo.php)
 * Tabelle: bestellungen
 */

require_once dirname(__FILE__) . '/phpfn14.php';

class cTable_bestellungen
{
    public $TableName = 'bestellungen';
    public $TableSlug = 'bestellungen';
    public $TableCaption = 'Bestellungen';
    public $TableType = 'TABLE';
    public $PrimaryKeys = array (
  0 => 'bestellung_id',
);
    public $Fields = array();
    public $Config = array();

    public function __construct()
    {
        $this->Config = array (
  'name' => 'bestellungen',
  'type' => 'TABLE',
  'caption' => 'Bestellungen',
  'enabled' => true,
  'primary_keys' => 
  array (
    0 => 'bestellung_id',
  ),
  'display_field' => 'bestellung_id',
  'icon' => 'fa-shopping-cart',
  'list_page' => true,
  'add_page' => true,
  'edit_page' => true,
  'view_page' => true,
  'delete_page' => true,
  'multi_delete' => true,
  'multi_update' => true,
  'quick_search' => true,
  'extended_search' => true,
  'records_per_page' => 0,
  'default_order_by' => 'bestellung_id',
  'default_order_dir' => 'DESC',
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
      'is_primary' => true,
      'is_auto_increment' => true,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => false,
      'edit' => false,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => false,
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
    'kunde_id' => 
    array (
      'name' => 'kunde_id',
      'caption' => 'Kunde',
      'sql_type' => 'int(11)',
      'base_type' => 'int',
      'ew_type' => 'INTEGER',
      'length' => 11,
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
      'extended_search' => true,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'SELECT',
      'input_size' => 11,
      'max_length' => 11,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => 'Integer',
      'options' => '',
      'lookup_table' => 'kunden',
      'lookup_link_field' => 'kunde_id',
      'lookup_display_field' => 'firma',
      'lookup_order_by' => '',
    ),
    'produkt_id' => 
    array (
      'name' => 'produkt_id',
      'caption' => 'Produkt',
      'sql_type' => 'int(11)',
      'base_type' => 'int',
      'ew_type' => 'INTEGER',
      'length' => 11,
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
      'extended_search' => true,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'SELECT',
      'input_size' => 11,
      'max_length' => 11,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => 'Integer',
      'options' => '',
      'lookup_table' => 'produkte',
      'lookup_link_field' => 'produkt_id',
      'lookup_display_field' => 'produktname',
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
      'view_tag' => 'NUMBER',
      'edit_tag' => 'TEXT',
      'input_size' => 10,
      'max_length' => 11,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
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
      'default_value' => '0.00',
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
      'input_size' => 15,
      'max_length' => 12,
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
      'search' => false,
      'extended_search' => true,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'SELECT',
      'input_size' => 30,
      'max_length' => 0,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => '',
      'options' => 'Offen:Offen,In Bearbeitung:In Bearbeitung,Versendet:Versendet,Abgeschlossen:Abgeschlossen,Storniert:Storniert',
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
      'input_size' => 12,
      'max_length' => 10,
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
    'bemerkung' => 
    array (
      'name' => 'bemerkung',
      'caption' => 'Interne Notiz',
      'sql_type' => 'text',
      'base_type' => 'text',
      'ew_type' => 'MEMO',
      'length' => 0,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => true,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => true,
      'extended_search' => false,
      'allow_sort' => false,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXTAREA',
      'input_size' => 30,
      'max_length' => 0,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => '',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'erstellt_von' => 
    array (
      'name' => 'erstellt_von',
      'caption' => 'Bearbeiter (Eigentümer)',
      'sql_type' => 'int(11)',
      'base_type' => 'int',
      'ew_type' => 'INTEGER',
      'length' => 11,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => true,
      'default_value' => '3',
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
      'edit_tag' => 'SELECT',
      'input_size' => 11,
      'max_length' => 11,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => 'Integer',
      'options' => '',
      'lookup_table' => 'benutzer',
      'lookup_link_field' => 'benutzer_id',
      'lookup_display_field' => 'anzeigename',
      'lookup_order_by' => '',
    ),
    'unterschrift_kunde' => 
    array (
      'name' => 'unterschrift_kunde',
      'caption' => 'Unterschrift Kunde (Touch)',
      'sql_type' => 'longtext',
      'base_type' => 'longtext',
      'ew_type' => 'MEMO',
      'length' => 0,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => true,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => false,
      'multi_update' => true,
      'search' => false,
      'extended_search' => false,
      'allow_sort' => false,
      'view_tag' => 'SIGNATURE',
      'edit_tag' => 'SIGNATURE',
      'input_size' => 30,
      'max_length' => 0,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => '',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
  ),
  'user_id_field' => 'erstellt_von',
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
    // PHPMacherNG Table Server Events (Projektspezifisch für bestellungen)
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
