<?php
/**
 * PHPMacherNG Table Info Class (produkteinfo.php)
 * Tabelle: produkte
 */

require_once dirname(__FILE__) . '/phpfn14.php';

class cTable_produkte
{
    public $TableName = 'produkte';
    public $TableSlug = 'produkte';
    public $TableCaption = 'Produkte';
    public $TableType = 'TABLE';
    public $PrimaryKeys = array (
  0 => 'produkt_id',
);
    public $Fields = array();
    public $Config = array();

    public function __construct()
    {
        $this->Config = array (
  'name' => 'produkte',
  'type' => 'TABLE',
  'caption' => 'Produkte',
  'enabled' => true,
  'primary_keys' => 
  array (
    0 => 'produkt_id',
  ),
  'display_field' => 'produktname',
  'icon' => 'fa-cube',
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
  'default_order_by' => 'produkt_id',
  'default_order_dir' => 'ASC',
  'fields' => 
  array (
    'produkt_id' => 
    array (
      'name' => 'produkt_id',
      'caption' => 'Art.-Nr.',
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
    'kategorie_id' => 
    array (
      'name' => 'kategorie_id',
      'caption' => 'Kategorie',
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
      'lookup_table' => 'kategorien',
      'lookup_link_field' => 'kategorie_id',
      'lookup_display_field' => 'bezeichnung',
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
      'multi_update' => false,
      'search' => true,
      'extended_search' => true,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 30,
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
      'extended_search' => true,
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
    'beschreibung' => 
    array (
      'name' => 'beschreibung',
      'caption' => 'Details',
      'sql_type' => 'text',
      'base_type' => 'text',
      'ew_type' => 'MEMO',
      'length' => 0,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => true,
      'default_value' => '',
      'list' => false,
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
    'preis' => 
    array (
      'name' => 'preis',
      'caption' => 'Einzelpreis',
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
    'lagerbestand' => 
    array (
      'name' => 'lagerbestand',
      'caption' => 'Lagerbestand',
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
      'view_tag' => 'NUMBER',
      'edit_tag' => 'TEXT',
      'input_size' => 11,
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
    'lieferbar' => 
    array (
      'name' => 'lieferbar',
      'caption' => 'Sofort lieferbar',
      'sql_type' => 'tinyint(1)',
      'base_type' => 'tinyint',
      'ew_type' => 'BOOLEAN',
      'length' => 1,
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
      'extended_search' => true,
      'allow_sort' => true,
      'view_tag' => 'BOOLEAN',
      'edit_tag' => 'CHECKBOX',
      'input_size' => 1,
      'max_length' => 1,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => '',
      'options' => '1:Ja,0:Nein',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'erstellt_am' => 
    array (
      'name' => 'erstellt_am',
      'caption' => 'Aufgenommen am',
      'sql_type' => 'date',
      'base_type' => 'date',
      'ew_type' => 'DATE',
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
      'allow_sort' => true,
      'view_tag' => 'DATE',
      'edit_tag' => 'DATE',
      'input_size' => 12,
      'max_length' => 10,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => false,
      'validation' => 'Date',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
  ),
  'user_id_field' => '',
  'master_details' => 
  array (
    0 => 
    array (
      'detail_table' => 'bestellungen',
      'master_field' => 'produkt_id',
      'detail_field' => 'produkt_id',
      'preview_row' => true,
      'detail_count' => true,
    ),
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
    // PHPMacherNG Table Server Events (Projektspezifisch für produkte)
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
