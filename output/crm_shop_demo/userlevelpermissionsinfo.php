<?php
/**
 * PHPMacherNG Table Info Class (userlevelpermissionsinfo.php)
 * Tabelle: userlevelpermissions
 */

require_once dirname(__FILE__) . '/phpfn14.php';

class cTable_userlevelpermissions
{
    public $TableName = 'userlevelpermissions';
    public $TableSlug = 'userlevelpermissions';
    public $TableCaption = 'Userlevelpermissions';
    public $TableType = 'TABLE';
    public $PrimaryKeys = array (
  0 => 'tablename',
  1 => 'userlevelid',
);
    public $Fields = array();
    public $Config = array();

    public function __construct()
    {
        $this->Config = array (
  'name' => 'userlevelpermissions',
  'type' => 'TABLE',
  'caption' => 'Userlevelpermissions',
  'enabled' => true,
  'primary_keys' => 
  array (
    0 => 'tablename',
    1 => 'userlevelid',
  ),
  'display_field' => 'tablename',
  'icon' => 'fa-list-alt',
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
  'default_order_by' => 'tablename',
  'default_order_dir' => 'DESC',
  'user_id_field' => '',
  'fields' => 
  array (
    'tablename' => 
    array (
      'name' => 'tablename',
      'caption' => 'Tabelle',
      'sql_type' => 'varchar(190)',
      'base_type' => 'varchar',
      'ew_type' => 'STRING',
      'length' => 190,
      'scale' => 0,
      'is_primary' => true,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => true,
      'multi_update' => false,
      'search' => true,
      'extended_search' => false,
      'allow_sort' => true,
      'view_tag' => 'FORMATTED TEXT',
      'edit_tag' => 'TEXT',
      'input_size' => 30,
      'max_length' => 190,
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
    'userlevelid' => 
    array (
      'name' => 'userlevelid',
      'caption' => 'Rollen-ID',
      'sql_type' => 'int(11)',
      'base_type' => 'int',
      'ew_type' => 'INTEGER',
      'length' => 11,
      'scale' => 0,
      'is_primary' => true,
      'is_auto_increment' => false,
      'is_nullable' => false,
      'default_value' => '',
      'list' => true,
      'add' => true,
      'edit' => true,
      'view' => true,
      'read_only_edit' => true,
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
      'required' => true,
      'validation' => 'Integer',
      'options' => '',
      'lookup_table' => '',
      'lookup_link_field' => '',
      'lookup_display_field' => '',
      'lookup_order_by' => '',
    ),
    'permission' => 
    array (
      'name' => 'permission',
      'caption' => 'Berechtigungs-Bitmaske',
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
    // PHPMacherNG Table Server Events (Projektspezifisch für userlevelpermissions)
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
