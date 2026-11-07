<?php
/**
 * PHPMacherNG (v14) Database Helper (ewdbhelper14.php)
 * Unterstützt PDO_MySQL und MySQLi gleichermaßen, kompatibel mit PHP 5.4 bis PHP 8.4+.
 */

require_once dirname(__FILE__) . '/ewcfg14.php';

class cEwDbConnection
{
    public $driver = '';
    public $pdo = null;
    public $mysqli = null;
    public $lastError = '';
    public $sqlLog = array();

    public function __construct()
    {
        $this->Connect();
    }

    public function Connect()
    {
        $this->lastError = '';
        if (extension_loaded('pdo_mysql')) {
            try {
                $dsn = 'mysql:host=' . EW_CONN_HOST . ';port=' . EW_CONN_PORT . ';dbname=' . EW_CONN_DB . ';charset=' . EW_CONN_CHARSET;
                $options = array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                );
                $this->pdo = new PDO($dsn, EW_CONN_USER, EW_CONN_PASS, $options);
                $this->driver = 'pdo';
                return true;
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif (extension_loaded('mysqli')) {
            $m = @new mysqli(EW_CONN_HOST, EW_CONN_USER, EW_CONN_PASS, EW_CONN_DB, EW_CONN_PORT);
            if ($m->connect_error) {
                $this->lastError = $m->connect_error;
                return false;
            }
            $m->set_charset(EW_CONN_CHARSET);
            $this->mysqli = $m;
            $this->driver = 'mysqli';
            return true;
        } else {
            $this->lastError = 'Keine MySQL-Erweiterung (PDO_MySQL oder MySQLi) geladen.';
            return false;
        }
    }

    public function IsConnected()
    {
        return ($this->driver === 'pdo' && $this->pdo !== null) || ($this->driver === 'mysqli' && $this->mysqli !== null);
    }

    /**
     * Führt eine SQL-Abfrage mit optionalen Prepared-Statement-Parametern aus.
     * @param string $sql
     * @param array $params
     * @return bool
     */
    public function Execute($sql, $params = array())
    {
        if (EW_DEBUG_ENABLED) {
            $this->sqlLog[] = array('sql' => $sql, 'params' => $params);
        }
        if (!$this->IsConnected()) {
            return false;
        }
        $this->lastError = '';

        if ($this->driver === 'pdo') {
            try {
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute(array_values($params));
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif ($this->driver === 'mysqli') {
            $boundSql = $this-> interpolateQuery($sql, $params);
            $res = $this->mysqli->query($boundSql);
            if (!$res) {
                $this->lastError = $this->mysqli->error;
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Lädt alle Datensätze einer SELECT-Abfrage als Array.
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function LoadRows($sql, $params = array())
    {
        if (EW_DEBUG_ENABLED) {
            $this->sqlLog[] = array('sql' => $sql, 'params' => $params);
        }
        if (!$this->IsConnected()) {
            return false;
        }
        $this->lastError = '';

        if ($this->driver === 'pdo') {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_values($params));
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } elseif ($this->driver === 'mysqli') {
            $boundSql = $this->interpolateQuery($sql, $params);
            $res = $this->mysqli->query($boundSql);
            if (!$res) {
                $this->lastError = $this->mysqli->error;
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
     * Lädt eine einzelne Zeile.
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function LoadRow($sql, $params = array())
    {
        $rows = $this->LoadRows($sql, $params);
        if (is_array($rows) && count($rows) > 0) {
            return $rows[0];
        }
        return false;
    }

    /**
     * Gibt den ersten Skalarwert der ersten Zeile zurück (z. B. für COUNT(*)).
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function ExecuteScalar($sql, $params = array())
    {
        $row = $this->LoadRow($sql, $params);
        if (is_array($row) && count($row) > 0) {
            $vals = array_values($row);
            return $vals[0];
        }
        return false;
    }

    /**
     * PHPMacherNG kompatible Alias-Methoden auf dem DbHelper-Objekt:
     * ExecuteRow(), ExecuteRows(), ExecuteHtml(), ExecuteJson()
     */
    public function ExecuteRow($sql, $params = array())
    {
        return $this->LoadRow($sql, $params);
    }

    public function ExecuteRows($sql, $params = array())
    {
        return $this->LoadRows($sql, $params);
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt das Ergebnis direkt als fertig formatierte
     * Bootstrap 3 / PHPMacherNG HTML-Tabelle zurück (wie DbHelper()->ExecuteHtml() in PHPMacherNG).
     *
     * Unterstützte $options:
     * - 'fieldcaption' => true | array('spalte' => 'Anzeigename', ...)
     * - 'tableclass'   => CSS-Klassen der Tabelle (Standard: 'table table-bordered table-striped ewDbTable')
     * - 'horizontal'   => false (Standard: Zeilenweise; bei true: Schlüssel-Wert-Darstellung für 1 Zeile)
     *
     * @param string $sql
     * @param array $options
     * @param array $params
     * @return string HTML-Markup
     */
    public function ExecuteHtml($sql, $options = array(), $params = array())
    {
        $rows = $this->LoadRows($sql, $params);
        if ($rows === false) {
            return '<div class="alert alert-danger">SQL-Fehler: ' . htmlspecialchars($this->lastError, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (empty($rows)) {
            return '<div class="text-muted" style="padding:8px 0;"><em>Keine Datensätze gefunden.</em></div>';
        }

        $tableClass = isset($options['tableclass']) && $options['tableclass'] !== ''
            ? $options['tableclass']
            : 'table table-bordered table-striped table-hover ewDbTable';

        $captions = isset($options['fieldcaption']) ? $options['fieldcaption'] : true;
        $horizontal = !empty($options['horizontal']);

        $firstRow = $rows[0];
        $cols = array_keys($firstRow);

        // Horizontale Darstellung (ideal für Einzel-Datensatz mit LoadRow/LIMIT 1)
        if ($horizontal) {
            $html = '<table class="' . htmlspecialchars($tableClass, ENT_QUOTES, 'UTF-8') . '"><tbody>';
            foreach ($cols as $col) {
                $label = $col;
                if (is_array($captions) && isset($captions[$col])) {
                    $label = $captions[$col];
                }
                $html .= '<tr><th style="width:30%; background:#f9fafc;">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</th>';
                foreach ($rows as $r) {
                    $val = isset($r[$col]) ? (string)$r[$col] : '';
                    $html .= '<td>' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            return $html;
        }

        // Standard-Tabellendarstellung
        $html = '<table class="' . htmlspecialchars($tableClass, ENT_QUOTES, 'UTF-8') . '">';
        if ($captions !== false) {
            $html .= '<thead><tr>';
            foreach ($cols as $col) {
                $label = $col;
                if (is_array($captions) && isset($captions[$col])) {
                    $label = $captions[$col];
                }
                $html .= '<th>' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr></thead>';
        }
        $html .= '<tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            foreach ($cols as $col) {
                $val = isset($r[$col]) && $r[$col] !== null ? (string)$r[$col] : '';
                $html .= '<td>' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt das Ergebnis als JSON-String zurück (wie ExecuteJson() in PHPMacherNG).
     */
    public function ExecuteJson($sql, $options = array(), $params = array())
    {
        $firstOnly = !empty($options['firstonly']);
        if ($firstOnly) {
            $row = $this->LoadRow($sql, $params);
            return json_encode($row ? $row : new stdClass());
        }
        $rows = $this->LoadRows($sql, $params);
        return json_encode(is_array($rows) ? $rows : array());
    }

    /**
     * Führt eine SQL-Abfrage aus und gibt ein interaktives HTML5-Diagramm (Balkendiagramm 'bar' oder Tortendiagramm 'pie') zurück.
     *
     * Unterstützte $options:
     * - 'type'        => 'bar' (Balkendiagramm, Standard) | 'pie' (Tortendiagramm)
     * - 'title'       => Überschrift des Diagramms
     * - 'label_field' => Spaltenname für die Beschriftung/Kategorie (Standard: 1. Spalte des SQL-Ergebnisses)
     * - 'value_field' => Spaltenname für den Zahlenwert (Standard: 2. Spalte des SQL-Ergebnisses, sonst 1. Spalte)
     * - 'height'      => Höhe des Canvas in Pixeln (Standard: 280)
     * - 'show_legend' => Legende/Wertetabelle neben/unter dem Diagramm anzeigen (Standard: true)
     *
     * @param string $sql
     * @param array $options
     * @param array $params
     * @return string HTML-Markup
     */
    public function ExecuteChart($sql, $options = array(), $params = array())
    {
        $rows = $this->LoadRows($sql, $params);
        if ($rows === false) {
            return '<div class="alert alert-danger">SQL-Fehler beim Laden des Diagramms: ' . htmlspecialchars($this->lastError, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if (empty($rows)) {
            return '<div class="text-muted" style="padding:12px;"><em>Keine Daten für die Diagrammdarstellung vorhanden.</em></div>';
        }

        $firstRow = $rows[0];
        $cols = array_keys($firstRow);
        $labelField = isset($options['label_field']) && $options['label_field'] !== '' && isset($firstRow[$options['label_field']])
            ? $options['label_field']
            : $cols[0];
        $valueField = isset($options['value_field']) && $options['value_field'] !== '' && isset($firstRow[$options['value_field']])
            ? $options['value_field']
            : (isset($cols[1]) ? $cols[1] : $cols[0]);

        $labels = array();
        $values = array();
        foreach ($rows as $r) {
            $lbl = isset($r[$labelField]) && $r[$labelField] !== null && trim((string)$r[$labelField]) !== ''
                ? (string)$r[$labelField]
                : '(Leer)';
            $val = isset($r[$valueField]) ? (float)$r[$valueField] : 0.0;
            $labels[] = $lbl;
            $values[] = round($val, 2);
        }

        $validChartTypes = array('bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge');
        $rawType = isset($options['type']) ? strtolower(trim((string)$options['type'])) : 'bar';
        $chartType = in_array($rawType, $validChartTypes, true) ? $rawType : 'bar';
        $title = isset($options['title']) ? (string)$options['title'] : '';
        $height = isset($options['height']) && (int)$options['height'] >= 160 ? (int)$options['height'] : 290;
        $domId = 'ew_chart_' . substr(md5($sql . microtime(true) . mt_rand()), 0, 10);

        $payload = array(
            'type'   => $chartType,
            'title'  => $title,
            'labels' => $labels,
            'values' => $values
        );
        $jsonAttr = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');

        $html = '<div class="ewChartWidgetBox" style="background:#fff; border:1px solid #e2e8f0; border-radius:4px; padding:12px 14px; margin-bottom:15px;">';
        if ($title !== '') {
            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:10px; border-bottom:1px solid #f1f5f9; padding-bottom:6px;">';
            $html .= '<strong style="font-size:13.5px; color:#1e293b;"><i class="fa fa-bar-chart text-primary"></i> ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>';
            $html .= '<div class="btn-group btn-group-xs">';
            $btnTypes = array(
                'bar'      => array('icon' => 'fa-bar-chart',      'lbl' => 'Säulen'),
                'hbar'     => array('icon' => 'fa-align-left',     'lbl' => 'Balken'),
                'line'     => array('icon' => 'fa-line-chart',     'lbl' => 'Linie'),
                'area'     => array('icon' => 'fa-area-chart',     'lbl' => 'Fläche'),
                'pie'      => array('icon' => 'fa-pie-chart',      'lbl' => 'Torte'),
                'doughnut' => array('icon' => 'fa-circle-o-notch', 'lbl' => 'Donut'),
                'funnel'   => array('icon' => 'fa-filter',         'lbl' => 'Trichter'),
                'radar'    => array('icon' => 'fa-compass',        'lbl' => 'Radar'),
                'gauge'    => array('icon' => 'fa-tachometer',     'lbl' => 'Tacho')
            );
            foreach ($btnTypes as $bKey => $bMeta) {
                $html .= '<button type="button" class="btn btn-default' . ($chartType === $bKey ? ' active' : '') . '" onclick="ew_SwitchChartType(\'' . $domId . '\', \'' . $bKey . '\', this);" title="' . $bMeta['lbl'] . '"><i class="fa ' . $bMeta['icon'] . '"></i> ' . $bMeta['lbl'] . '</button>';
            }
            $html .= '<button type="button" class="btn btn-default" onclick="ew_DownloadChartPng(\'' . $domId . '\');" title="Diagramm als PNG-Bild herunterladen"><i class="fa fa-download"></i> PNG</button>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '<div style="position:relative; width:100%;">';
        $html .= '<canvas id="' . $domId . '" class="ewChartCanvas" data-ew-chart="' . $jsonAttr . '" height="' . $height . '" style="width:100%; height:' . $height . 'px; display:block;"></canvas>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Gibt die zuletzt eingefügte Auto-Increment ID zurück.
     * @return string|int
     */
    public function Insert_ID()
    {
        if ($this->driver === 'pdo' && $this->pdo) {
            return $this->pdo->lastInsertId();
        } elseif ($this->driver === 'mysqli' && $this->mysqli) {
            return $this->mysqli->insert_id;
        }
        return 0;
    }

    /**
     * Sicheres Escaping für MySQLi-Fallback.
     */
    protected function interpolateQuery($sql, $params)
    {
        if (empty($params)) {
            return $sql;
        }
        $parts = explode('?', $sql);
        $out = $parts[0];
        $i = 1;
        foreach ($params as $val) {
            if ($val === null) {
                $rep = 'NULL';
            } elseif (is_bool($val)) {
                $rep = $val ? '1' : '0';
            } else {
                $rep = "'" . $this->mysqli->real_escape_string((string)$val) . "'";
            }
            $out .= $rep . (isset($parts[$i]) ? $parts[$i] : '');
            $i++;
        }
        return $out;
    }
}

/**
 * Globale Hilfsfunktionen zum Abrufen der Datenbankverbindung & Ausführen von Abfragen
 * (100% kompatibel mit PHPMacherNG: Conn(), DbHelper(), ew_Execute*, ew_RenderChart, ew_AdjustSql)
 * @return cEwDbConnection
 */
function &ew_Connect()
{
    global $EW_CONN;
    if (!isset($EW_CONN) || !($EW_CONN instanceof cEwDbConnection)) {
        $EW_CONN = new cEwDbConnection();
    }
    return $EW_CONN;
}

function &Conn()
{
    return ew_Connect();
}

function &DbHelper()
{
    return ew_Connect();
}

function ew_Execute($sql, $params = array())
{
    return ew_Connect()->Execute($sql, $params);
}

function ew_ExecuteScalar($sql, $params = array())
{
    return ew_Connect()->ExecuteScalar($sql, $params);
}

function ew_ExecuteRow($sql, $params = array())
{
    return ew_Connect()->LoadRow($sql, $params);
}

function ew_ExecuteRows($sql, $params = array())
{
    return ew_Connect()->LoadRows($sql, $params);
}

function ew_RenderChart($sql, $options = array(), $params = array())
{
    return ew_Connect()->ExecuteChart($sql, $options, $params);
}

function ew_ExecuteHtml($sql, $options = array(), $params = array())
{
    return ew_Connect()->ExecuteHtml($sql, $options, $params);
}

function ew_ExecuteJson($sql, $options = array(), $params = array())
{
    return ew_Connect()->ExecuteJson($sql, $options, $params);
}

function ew_AdjustSql($val)
{
    return addslashes((string)$val);
}