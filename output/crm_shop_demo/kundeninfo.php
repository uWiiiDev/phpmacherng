<?php
/**
 * PHPMacherNG Table Info Class (kundeninfo.php)
 * Tabelle: kunden
 */

require_once dirname(__FILE__) . '/phpfn14.php';

class cTable_kunden
{
    public $TableName = 'kunden';
    public $TableSlug = 'kunden';
    public $TableCaption = 'Kunden';
    public $TableType = 'TABLE';
    public $PrimaryKeys = array (
  0 => 'kunde_id',
);
    public $Fields = array();
    public $Config = array();

    public function __construct()
    {
        $this->Config = array (
  'name' => 'kunden',
  'type' => 'TABLE',
  'caption' => 'Kunden',
  'enabled' => true,
  'primary_keys' => 
  array (
    0 => 'kunde_id',
  ),
  'display_field' => 'firma',
  'icon' => 'fa-users',
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
  'default_order_by' => 'kunde_id',
  'default_order_dir' => 'ASC',
  'fields' => 
  array (
    'kunde_id' => 
    array (
      'name' => 'kunde_id',
      'caption' => 'Kunden-Nr.',
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
    'firma' => 
    array (
      'name' => 'firma',
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
      'extended_search' => true,
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
    'ansprechpartner' => 
    array (
      'name' => 'ansprechpartner',
      'caption' => 'Ansprechpartner',
      'sql_type' => 'varchar(100)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 100,
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
      'max_length' => 100,
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
    'email' => 
    array (
      'name' => 'email',
      'caption' => 'E-Mail',
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
      'view_tag' => 'EMAIL',
      'edit_tag' => 'TEXT',
      'input_size' => 30,
      'max_length' => 120,
      'textarea_cols' => 48,
      'textarea_rows' => 4,
      'required' => true,
      'validation' => 'Email',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'telefon' => 
    array (
      'name' => 'telefon',
      'caption' => 'Telefon',
      'sql_type' => 'varchar(50)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 50,
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
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 30,
      'max_length' => 50,
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
    'stadt' => 
    array (
      'name' => 'stadt',
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
      'extended_search' => true,
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
    'kundengruppe' => 
    array (
      'name' => 'kundengruppe',
      'caption' => 'Kundengruppe',
      'sql_type' => 'enum(\'standard\',\'business\',\'vip\',\'behoerde\')',
      'base_type' => 'enum',
      'ew_type' => 'STRING',
      'length' => 0,
      'scale' => 0,
      'is_primary' => false,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => 'Standard',
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
      'options' => 'Standard:Standard,Business:Business,VIP:VIP-Kunde,Behoerde:Behörde',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'aktiv' => 
    array (
      'name' => 'aktiv',
      'caption' => 'Aktiv',
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
    'unterschrift' => 
    array (
      'name' => 'unterschrift',
      'caption' => 'Unterschrift (Touchscreen)',
      'sql_type' => 'varchar(255)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 255,
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
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'PASSWORD',
      'input_size' => 30,
      'max_length' => 255,
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
  'user_id_field' => '',
  'master_details' => 
  array (
    0 => 
    array (
      'detail_table' => 'bestellungen',
      'master_field' => 'kunde_id',
      'detail_field' => 'kunde_id',
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
    // PHPMacherNG Table Server Events (Projektspezifisch für kunden)
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
