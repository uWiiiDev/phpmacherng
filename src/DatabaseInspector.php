<?php
/**
 * PHPMacherNG - Database Inspector
 * Kompatibel mit PHP 5.4 bis PHP 8.4+
 * Analysiert MySQL / MariaDB Datenbanken über PDO oder MySQLi.
 */

class DatabaseInspector
{
    public $host = '127.0.0.1';
    public $port = 3306;
    public $user = 'root';
    public $pass = '';
    public $dbname = '';
    public $charset = 'utf8';
    public $driver = '';
    public $conn = null;
    public $lastError = '';

    public function __construct($config = array())
    {
        if (isset($config['host']) && $config['host'] !== '') {
            $this->host = $config['host'];
        }
        if (isset($config['port']) && (int)$config['port'] > 0) {
            $this->port = (int)$config['port'];
        }
        if (isset($config['user'])) {
            $this->user = $config['user'];
        }
        if (isset($config['pass'])) {
            $this->pass = $config['pass'];
        }
        if (isset($config['dbname'])) {
            $this->dbname = $config['dbname'];
        }
        if (isset($config['charset']) && $config['charset'] !== '') {
            $this->charset = $config['charset'];
        }
    }

    /**
     * Stellt eine Verbindung zum MySQL/MariaDB Server her.
     * @param bool $selectDb Ob direkt die Datenbank ausgewählt werden soll
     * @return bool
     */
    public function connect($selectDb = true)
    {
        $this->lastError = '';
        $dbPart = ($selectDb && $this->dbname !== '') ? ';dbname=' . $this->dbname : '';

        if (extension_loaded('pdo_mysql')) {
            try {
                $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . $dbPart . ';charset=' . $this->charset;
                $options = array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5
                );
                $this->conn = new PDO($dsn, $this->user, $this->pass, $options);
                $this->driver = 'pdo';
                return true;
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif (extension_loaded('mysqli')) {
            $dbName = ($selectDb && $this->dbname !== '') ? $this->dbname : '';
            $mysqli = @new mysqli($this->host, $this->user, $this->pass, $dbName, $this->port);
            if ($mysqli->connect_error) {
                $this->lastError = $mysqli->connect_error;
                return false;
            }
            $mysqli->set_charset($this->charset);
            $this->conn = $mysqli;
            $this->driver = 'mysqli';
            return true;
        } else {
            $this->lastError = 'Weder PDO_MySQL noch MySQLi Erweiterung ist in PHP aktiviert.';
            return false;
        }
    }

    /**
     * Führt eine SQL-Abfrage aus und gibt alle Zeilen als assoziatives Array zurück.
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function queryAll($sql, $params = array())
    {
        if (!$this->conn) {
            if (!$this->connect(true)) {
                return false;
            }
        }

        if ($this->driver === 'pdo') {
            try {
                if (empty($params)) {
                    $stmt = $this->conn->query($sql);
                } else {
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute($params);
                }
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif ($this->driver === 'mysqli') {
            if (!empty($params)) {
                // Einfache Ersetzung für Prepared-Statements in Inspector-Abfragen
                foreach ($params as $p) {
                    $escaped = "'" . $this->conn->real_escape_string((string)$p) . "'";
                    $pos = strpos($sql, '?');
                    if ($pos !== false) {
                        $sql = substr_replace($sql, $escaped, $pos, 1);
                    }
                }
            }
            $res = $this->conn->query($sql);
            if (!$res) {
                $this->lastError = $this->conn->error;
                return false;
            }
            $rows = array();
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();
            }
            return $rows;
        }
        return false;
    }

    /**
     * Gibt eine Liste aller verfügbaren Datenbanken zurück.
     * @return array|false
     */
    public function listDatabases()
    {
        if (!$this->connect(false)) {
            return false;
        }
        $rows = $this->queryAll('SHOW DATABASES');
        if ($rows === false) {
            return false;
        }
        $dbs = array();
        $ignore = array('information_schema', 'performance_schema', 'mysql', 'sys');
        foreach ($rows as $row) {
            $vals = array_values($row);
            $db = isset($vals[0]) ? $vals[0] : '';
            if ($db !== '' && !in_array(strtolower($db), $ignore)) {
                $dbs[] = $db;
            }
        }
        return $dbs;
    }

    /**
     * Liest das komplette Schema (Tabellen, Views, Spalten, Fremdschlüssel) der aktuellen Datenbank aus.
     * @return array|false
     */
    public function inspectSchema()
    {
        if ($this->dbname === '') {
            $this->lastError = 'Kein Datenbankname angegeben.';
            return false;
        }
        if (!$this->connect(true)) {
            return false;
        }

        $tablesRows = $this->queryAll('SHOW FULL TABLES FROM `' . str_replace('`', '``', $this->dbname) . '`');
        if ($tablesRows === false) {
            return false;
        }

        // Fremdschlüssel-Beziehungen vorab aus INFORMATION_SCHEMA laden
        $foreignKeys = $this->loadForeignKeys();

        $tables = array();
        foreach ($tablesRows as $tRow) {
            $vals = array_values($tRow);
            $tableName = isset($vals[0]) ? $vals[0] : '';
            $tableType = isset($vals[1]) ? strtoupper($vals[1]) : 'BASE TABLE';
            if ($tableName === '') {
                continue;
            }

            $columns = $this->inspectColumns($tableName, isset($foreignKeys[$tableName]) ? $foreignKeys[$tableName] : array());
            if ($columns === false) {
                continue;
            }

            $primaryKeys = array();
            foreach ($columns as $col) {
                if (!empty($col['is_primary'])) {
                    $primaryKeys[] = $col['name'];
                }
            }

            $viewSql = '';
            if ($tableType === 'VIEW') {
                $viewSql = $this->getViewDefinition($tableName);
            }

            $tables[$tableName] = array(
                'name' => $tableName,
                'type' => ($tableType === 'VIEW') ? 'VIEW' : 'TABLE',
                'caption' => self::humanizeName($tableName),
                'primary_keys' => $primaryKeys,
                'view_sql' => $viewSql,
                'fields' => $columns
            );
        }

        return $tables;
    }

    /**
     * Liest das zugrundeliegende SELECT-Statement einer Datenbank-View aus INFORMATION_SCHEMA.VIEWS oder SHOW CREATE VIEW aus.
     * @param string $viewName
     * @return string
     */
    public function getViewDefinition($viewName)
    {
        $sql = "SELECT VIEW_DEFINITION FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
        $rows = $this->queryAll($sql, array($this->dbname, $viewName));
        if (is_array($rows) && !empty($rows[0]['VIEW_DEFINITION'])) {
            $def = trim($rows[0]['VIEW_DEFINITION']);
            // Datenbank-Präfix wie `dbname`. entfernen für bessere Lesbarkeit
            if ($this->dbname !== '') {
                $dbEsc = '`' . str_replace('`', '``', $this->dbname) . '`.';
                $def = str_replace($dbEsc, '', $def);
            }
            return $def;
        }
        return '';
    }

    /**
     * Erstellt oder aktualisiert eine Datenbank-View per CREATE OR REPLACE VIEW `<viewName>` AS <selectSql>.
     * @param string $viewName
     * @param string $selectSql
     * @return bool
     */
    public function saveDatabaseView($viewName, $selectSql)
    {
        $this->lastError = '';
        if (!$this->connect(true)) {
            return false;
        }
        $cleanSql = trim((string)$selectSql);
        $cleanSql = rtrim($cleanSql, "; \t\n\r\0\x0B");
        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $cleanSql)) {
            $this->lastError = 'Die SQL-Abfrage für eine View muss mit SELECT (oder WITH) beginnen.';
            return false;
        }
        $safeView = '`' . str_replace('`', '``', $viewName) . '`';
        $ddl = "CREATE OR REPLACE VIEW " . $safeView . " AS " . $cleanSql;
        $res = $this->queryAll($ddl);
        if ($res === false && $this->lastError !== '') {
            return false;
        }
        return true;
    }

    /**
     * Löscht eine Datenbank-View per DROP VIEW IF EXISTS `<viewName>`.
     * @param string $viewName
     * @return bool
     */
    public function dropDatabaseView($viewName)
    {
        $this->lastError = '';
        if (!$this->connect(true)) {
            return false;
        }
        $safeView = '`' . str_replace('`', '``', $viewName) . '`';
        $res = $this->queryAll("DROP VIEW IF EXISTS " . $safeView);
        if ($res === false && $this->lastError !== '') {
            return false;
        }
        return true;
    }

    /**
     * Liest Fremdschlüssel der Datenbank aus.
     * @return array
     */
    protected function loadForeignKeys()
    {
        $sql = "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL";
        $rows = $this->queryAll($sql, array($this->dbname));
        $fks = array();
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $tName = isset($r['TABLE_NAME']) ? $r['TABLE_NAME'] : '';
                $cName = isset($r['COLUMN_NAME']) ? $r['COLUMN_NAME'] : '';
                if ($tName !== '' && $cName !== '') {
                    if (!isset($fks[$tName])) {
                        $fks[$tName] = array();
                    }
                    $fks[$tName][$cName] = array(
                        'table' => $r['REFERENCED_TABLE_NAME'],
                        'column' => $r['REFERENCED_COLUMN_NAME']
                    );
                }
            }
        }
        return $fks;
    }

    /**
     * Liest alle Spalten einer Tabelle inkl. Datentyp und Standard-PHPMacherNG-Einstellungen aus.
     * @param string $tableName
     * @param array $tableFks
     * @return array|false
     */
    public function inspectColumns($tableName, $tableFks = array())
    {
        $safeTable = str_replace('`', '``', $tableName);
        $rows = $this->queryAll("SHOW FULL COLUMNS FROM `" . $safeTable . "`");
        if ($rows === false) {
            return false;
        }

        $fields = array();
        foreach ($rows as $row) {
            $colName = $row['Field'];
            $rawType = strtolower($row['Type']);
            $isNullable = (strtoupper($row['Null']) === 'YES');
            $isPrimary = (strtoupper($row['Key']) === 'PRI');
            $defaultVal = $row['Default'];
            $extra = strtolower($row['Extra']);
            $isAutoIncrement = (strpos($extra, 'auto_increment') !== false);
            $comment = isset($row['Comment']) ? $row['Comment'] : '';

            $parsedType = self::parseSqlType($rawType);
            $fkInfo = isset($tableFks[$colName]) ? $tableFks[$colName] : null;

            $fields[$colName] = self::buildDefaultFieldConfig(
                $colName,
                $rawType,
                $parsedType,
                $isNullable,
                $isPrimary,
                $isAutoIncrement,
                $defaultVal,
                $comment,
                $fkInfo
            );
        }

        return $fields;
    }

    /**
     * Analysiert einen MySQL-Typ-String wie "varchar(255)", "decimal(10,2) unsigned", "enum('a','b')".
     * @param string $rawType
     * @return array
     */
    public static function parseSqlType($rawType)
    {
        $baseType = $rawType;
        $length = 0;
        $scale = 0;
        $unsigned = (strpos($rawType, 'unsigned') !== false);
        $enumValues = array();

        if (preg_match('/^([a-z0-9_]+)\s*\((.+)\)/i', $rawType, $m)) {
            $baseType = strtolower($m[1]);
            $inner = $m[2];
            if ($baseType === 'enum' || $baseType === 'set') {
                if (preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $inner, $em)) {
                    foreach ($em[1] as $ev) {
                        $enumValues[] = str_replace("''", "'", stripslashes($ev));
                    }
                }
            } elseif (strpos($inner, ',') !== false) {
                $parts = explode(',', $inner);
                $length = (int)trim($parts[0]);
                $scale = (int)trim($parts[1]);
            } else {
                $length = (int)trim($inner);
            }
        } else {
            $parts = preg_split('/\s+/', trim($rawType));
            $baseType = strtolower($parts[0]);
        }

        // PHPMacherNG Datentyp-Kategorie bestimmen
        $ewDataType = 'STRING';
        if (in_array($baseType, array('tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year'))) {
            if ($baseType === 'tinyint' && $length === 1) {
                $ewDataType = 'BOOLEAN';
            } else {
                $ewDataType = 'INTEGER';
            }
        } elseif (in_array($baseType, array('float', 'double', 'real', 'decimal', 'numeric', 'dec', 'fixed'))) {
            $ewDataType = 'FLOAT';
        } elseif (in_array($baseType, array('date', 'datetime', 'timestamp'))) {
            $ewDataType = 'DATE';
        } elseif ($baseType === 'time') {
            $ewDataType = 'TIME';
        } elseif (in_array($baseType, array('tinytext', 'text', 'mediumtext', 'longtext', 'json'))) {
            $ewDataType = 'MEMO';
        } elseif (in_array($baseType, array('tinyblob', 'blob', 'mediumblob', 'longblob', 'binary', 'varbinary'))) {
            $ewDataType = 'BLOB';
        } elseif ($baseType === 'bit' && $length === 1) {
            $ewDataType = 'BOOLEAN';
        }

        return array(
            'base_type' => $baseType,
            'ew_type' => $ewDataType,
            'length' => $length,
            'scale' => $scale,
            'unsigned' => $unsigned,
            'enum_values' => $enumValues
        );
    }

    /**
     * Erstellt die Standard-PHPMacherNG-Feldkonfiguration anhand der Spalten-Metadaten.
     */
    public static function buildDefaultFieldConfig(
        $colName,
        $rawType,
        $parsedType,
        $isNullable,
        $isPrimary,
        $isAutoIncrement,
        $defaultVal,
        $comment = '',
        $fkInfo = null
    ) {
        $ewType = $parsedType['ew_type'];
        $baseType = $parsedType['base_type'];
        $lowerName = strtolower($colName);

        // Intelligente Erkennung für Edit Tag & View Tag
        $editTag = 'TEXT';
        $viewTag = 'FORMATTED TEXT';
        $validation = '';
        $optionsList = '';

        if ($ewType === 'MEMO') {
            $editTag = 'TEXTAREA';
        } elseif ($ewType === 'BOOLEAN') {
            $editTag = 'CHECKBOX';
            $viewTag = 'BOOLEAN';
            $optionsList = '1:Ja,0:Nein';
        } elseif ($baseType === 'enum' || $baseType === 'set') {
            $editTag = 'SELECT';
            $pairs = array();
            foreach ($parsedType['enum_values'] as $v) {
                $pairs[] = $v . ':' . self::humanizeName($v);
            }
            $optionsList = implode(',', $pairs);
        } elseif ($ewType === 'INTEGER') {
            $validation = 'Integer';
        } elseif ($ewType === 'FLOAT') {
            $validation = 'Float';
            if (strpos($lowerName, 'price') !== false || strpos($lowerName, 'preis') !== false || strpos($lowerName, 'amount') !== false || strpos($lowerName, 'betrag') !== false || strpos($lowerName, 'total') !== false || strpos($lowerName, 'cost') !== false) {
                $viewTag = 'CURRENCY';
            } else {
                $viewTag = 'NUMBER';
            }
        } elseif ($ewType === 'DATE') {
            $editTag = 'DATE';
            $viewTag = ($baseType === 'date') ? 'DATE' : 'DATETIME';
            $validation = 'Date';
        }

        // Spezielle Feldnamen erkennen (Passwort, E-Mail, URL, Bild)
        if ($lowerName === 'password' || $lowerName === 'passwort' || $lowerName === 'passwd' || $lowerName === 'pwd') {
            $editTag = 'PASSWORD';
        } elseif (strpos($lowerName, 'email') !== false || strpos($lowerName, 'e_mail') !== false || $lowerName === 'mail') {
            $validation = 'Email';
            $viewTag = 'EMAIL';
        } elseif ($lowerName === 'url' || $lowerName === 'website' || $lowerName === 'homepage' || strpos($lowerName, '_url') !== false) {
            $viewTag = 'LINK';
        } elseif ($lowerName === 'image' || $lowerName === 'avatar' || $lowerName === 'photo' || $lowerName === 'bild' || $lowerName === 'logo') {
            $viewTag = 'IMAGE';
        } elseif (strpos($lowerName, 'unterschrift') !== false || strpos($lowerName, 'signatur') !== false || strpos($lowerName, 'signature') !== false) {
            $editTag = 'SIGNATURE';
            $viewTag = 'SIGNATURE';
        }

        // Falls Fremdschlüssel vorhanden ist, als SELECT mit Lookup vorkonfigurieren
        $lookupTable = '';
        $lookupLinkField = '';
        $lookupDisplayField = '';
        if (is_array($fkInfo) && !empty($fkInfo['table'])) {
            $editTag = 'SELECT';
            $lookupTable = $fkInfo['table'];
            $lookupLinkField = $fkInfo['column'];
            $lookupDisplayField = $fkInfo['column'];
        }

        $isRequired = (!$isNullable && !$isAutoIncrement && $defaultVal === null && $ewType !== 'BOOLEAN');
        $showInList = ($ewType !== 'MEMO' && $ewType !== 'BLOB' && $editTag !== 'PASSWORD');
        $showInAdd = (!$isAutoIncrement);
        $showInEdit = (!$isAutoIncrement);
        $showInMultiUpdate = (!$isPrimary && !$isAutoIncrement && $editTag !== 'PASSWORD');
        $allowSearch = ($ewType === 'STRING' || $ewType === 'MEMO');

        return array(
            'name' => $colName,
            'caption' => ($comment !== '' && strlen($comment) < 40) ? $comment : self::humanizeName($colName),
            'sql_type' => $rawType,
            'base_type' => $baseType,
            'ew_type' => $ewType,
            'length' => $parsedType['length'],
            'scale' => $parsedType['scale'],
            'is_primary' => $isPrimary,
            'is_auto_increment' => $isAutoIncrement,
            'is_nullable' => $isNullable,
            'default_value' => ($defaultVal !== null && $defaultVal !== 'CURRENT_TIMESTAMP') ? (string)$defaultVal : '',
            // Sichtbarkeit pro Seite (PHPMacherNG Field Setup)
            'list' => $showInList,
            'add' => $showInAdd,
            'edit' => $showInEdit,
            'view' => ($editTag !== 'PASSWORD'),
            'read_only_edit' => ($isPrimary && !$isAutoIncrement),
            'multi_update' => $showInMultiUpdate,
            'search' => $allowSearch,
            'extended_search' => false,
            'allow_sort' => ($ewType !== 'BLOB'),
            // View Tag & Edit Tag
            'view_tag' => $viewTag,
            'edit_tag' => $editTag,
            'input_size' => ($parsedType['length'] > 0 && $parsedType['length'] <= 50) ? $parsedType['length'] : 30,
            'max_length' => ($parsedType['length'] > 0) ? $parsedType['length'] : 0,
            'textarea_cols' => 48,
            'textarea_rows' => 4,
            // Validierung
            'required' => $isRequired,
            'validation' => $validation,
            // Optionen & Lookup
            'options' => $optionsList,
            'lookup_table' => $lookupTable,
            'lookup_link_field' => $lookupLinkField,
            'lookup_display_field' => $lookupDisplayField,
            'lookup_order_by' => ''
        );
    }

    /**
     * Wandelt technische DB-Bezeichner wie "customer_orders" oder "firstName" in lesbare Captions um ("Customer Orders").
     * @param string $name
     * @return string
     */
    public static function humanizeName($name)
    {
        $clean = str_replace(array('_', '-'), ' ', $name);
        $clean = preg_replace('/([a-z])([A-Z])/', '$1 $2', $clean);
        return ucwords(trim($clean));
    }
}
