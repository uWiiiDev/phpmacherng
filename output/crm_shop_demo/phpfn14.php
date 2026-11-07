<?php
/**
 * PHPMacherNG (v14) Shared Functions (phpfn14.php)
 * Kompatibel mit PHP 5.4 bis PHP 8.4+
 */

require_once dirname(__FILE__) . '/ewdbhelper14.php';

if (session_id() === '') {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}
ew_SendSecurityHeaders();

/**
 * Sendet empfohlene HTTP-Security-Header (gemäß IT-Sicherheitskonzept / BSI)
 */
function ew_SendSecurityHeaders()
{
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
    }
}

/**
 * HTML-Escaping (sicher für PHP 5.4 bis 8.4+, auch bei null-Werten)
 */
function ew_HtmlEncode($val)
{
    if ($val === null || $val === false) {
        return '';
    }
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

/**
 * Liest einen GET-Parameter sicher aus.
 */
function ew_Get($key, $default = '')
{
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Liest einen POST-Parameter sicher aus.
 */
function ew_Post($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * CSRF-Token erzeugen und prüfen
 */
function ew_GetCsrfToken()
{
    if (empty($_SESSION[EW_PROJECT_ID . '_csrf_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION[EW_PROJECT_ID . '_csrf_token'] = bin2hex(random_bytes(16));
        } else {
            $_SESSION[EW_PROJECT_ID . '_csrf_token'] = md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION[EW_PROJECT_ID . '_csrf_token'];
}

function ew_CheckCsrfToken($token)
{
    $expected = ew_GetCsrfToken();
    return ((string)$token !== '' && (string)$token === (string)$expected);
}

/**
 * Flash-Messages (Erfolg / Fehler / Warnung wie in PHPMacherNG SetMessage / SetFailureMessage)
 */
function ew_SetSuccessMessage($msg)
{
    if (!isset($_SESSION[EW_PROJECT_ID . '_msg_success'])) {
        $_SESSION[EW_PROJECT_ID . '_msg_success'] = array();
    }
    $_SESSION[EW_PROJECT_ID . '_msg_success'][] = $msg;
}

function ew_SetFailureMessage($msg)
{
    if (!isset($_SESSION[EW_PROJECT_ID . '_msg_error'])) {
        $_SESSION[EW_PROJECT_ID . '_msg_error'] = array();
    }
    $_SESSION[EW_PROJECT_ID . '_msg_error'][] = $msg;
}

function ew_ShowMessages()
{
    $html = '';
    if (!empty($_SESSION[EW_PROJECT_ID . '_msg_success'])) {
        foreach ($_SESSION[EW_PROJECT_ID . '_msg_success'] as $m) {
            $html .= '<div class="alert alert-success alert-dismissible ewAlert"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-check"></i> ' . $m . '</div>';
        }
        $_SESSION[EW_PROJECT_ID . '_msg_success'] = array();
    }
    if (!empty($_SESSION[EW_PROJECT_ID . '_msg_error'])) {
        foreach ($_SESSION[EW_PROJECT_ID . '_msg_error'] as $m) {
            $html .= '<div class="alert alert-danger alert-dismissible ewAlert"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-ban"></i> ' . $m . '</div>';
        }
        $_SESSION[EW_PROJECT_ID . '_msg_error'] = array();
    }
    return $html;
}

/**
 * Weiterleitung per HTTP Header
 */
function ew_Redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * Formatiert einen Datenbankwert gemäß dem gewählten PHPMacherNG View-Tag.
 */
function ew_FormatViewValue($val, $fieldMeta)
{
    if ($val === null || $val === '') {
        return '<span class="text-muted">&ndash;</span>';
    }

    // Passwort-Felder in Listen- und Detailansichten immer maskieren
    if ((isset($fieldMeta['edit_tag']) && $fieldMeta['edit_tag'] === 'PASSWORD') ||
        (isset($fieldMeta['name']) && in_array(strtolower($fieldMeta['name']), array('passwort', 'password', 'passwd', 'pwd')))) {
        return '********';
    }

    // Falls Lookup-Tabelle oder statische Optionen definiert sind, Anzeigewert auflösen
    if (!empty($fieldMeta['lookup_table']) && !empty($fieldMeta['lookup_link_field']) && !empty($fieldMeta['lookup_display_field'])) {
        $resolved = ew_ResolveLookupValue($fieldMeta['lookup_table'], $fieldMeta['lookup_link_field'], $fieldMeta['lookup_display_field'], $val);
        if ($resolved !== false) {
            return ew_HtmlEncode($resolved);
        }
    } elseif (!empty($fieldMeta['options']) && $fieldMeta['view_tag'] !== 'BOOLEAN') {
        $opts = ew_ParseOptionsString($fieldMeta['options']);
        if (isset($opts[(string)$val])) {
            return ew_HtmlEncode($opts[(string)$val]);
        }
    }

    $viewTag = isset($fieldMeta['view_tag']) ? $fieldMeta['view_tag'] : 'FORMATTED TEXT';

    switch ($viewTag) {
        case 'CURRENCY':
            if (is_numeric($val)) {
                return ew_HtmlEncode(EW_CURRENCY_SYMBOL . ' ' . number_format((float)$val, 2, ',', '.'));
            }
            return ew_HtmlEncode($val);

        case 'NUMBER':
            if (is_numeric($val)) {
                $decimals = (isset($fieldMeta['scale']) && (int)$fieldMeta['scale'] > 0) ? (int)$fieldMeta['scale'] : 0;
                return ew_HtmlEncode(number_format((float)$val, $decimals, ',', '.'));
            }
            return ew_HtmlEncode($val);

        case 'DATE':
            $ts = @strtotime((string)$val);
            if ($ts !== false && $ts > 0) {
                return ew_HtmlEncode(date(EW_DATE_FORMAT, $ts));
            }
            return ew_HtmlEncode($val);

        case 'DATETIME':
            $ts = @strtotime((string)$val);
            if ($ts !== false && $ts > 0) {
                return ew_HtmlEncode(date(EW_DATE_FORMAT . ' H:i', $ts));
            }
            return ew_HtmlEncode($val);

        case 'BOOLEAN':
            $truthy = ($val == 1 || strtolower((string)$val) === 'true' || strtolower((string)$val) === 'y' || strtolower((string)$val) === 'ja');
            return $truthy
                ? '<span class="label label-success"><i class="fa fa-check"></i> Ja</span>'
                : '<span class="label label-default">Nein</span>';

        case 'EMAIL':
            $safe = ew_HtmlEncode($val);
            return '<a href="mailto:' . $safe . '"><i class="fa fa-envelope-o"></i> ' . $safe . '</a>';

        case 'LINK':
            $safe = ew_HtmlEncode($val);
            return '<a href="' . $safe . '" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> ' . $safe . '</a>';

        case 'IMAGE':
            $safe = ew_HtmlEncode($val);
            return '<img src="' . $safe . '" alt="" class="img-thumbnail" style="max-height:48px;max-width:80px;">';

        case 'SIGNATURE':
            $strSig = trim((string)$val);
            if ($strSig === '') {
                return '<span class="text-muted">&ndash;</span>';
            }
            if (strpos($strSig, 'data:image/') === 0 || preg_match('/\.(png|jpe?g|gif|svg|webp)(\?.*)?$/i', $strSig)) {
                $safeSig = ew_HtmlEncode($strSig);
                return '<div style="display:inline-block; background:#fff; border:1px solid #d2d6de; border-radius:3px; padding:3px 6px;" title="Digitale Unterschrift">'
                    . '<img src="' . $safeSig . '" alt="Unterschrift" style="max-height:54px; max-width:180px; display:block;">'
                    . '</div>';
            }
            return ew_HtmlEncode($strSig);

        case 'HTML':
            return (string)$val;

        default:
            $str = (string)$val;
            // Falls ein Feld eine Base64-Bild-Signatur enthält, auch ohne expliziten SIGNATURE View-Tag als Bild darstellen
            if (strpos($str, 'data:image/png;base64,') === 0) {
                $safeSig = ew_HtmlEncode($str);
                return '<div style="display:inline-block; background:#fff; border:1px solid #d2d6de; border-radius:3px; padding:3px 6px;">'
                    . '<img src="' . $safeSig . '" alt="Unterschrift" style="max-height:54px; max-width:180px; display:block;">'
                    . '</div>';
            }
            if (strlen($str) > 140 && isset($fieldMeta['ew_type']) && $fieldMeta['ew_type'] === 'MEMO') {
                return nl2br(ew_HtmlEncode(substr($str, 0, 140) . '...'));
            }
            return nl2br(ew_HtmlEncode($str));
    }
}

/**
 * Parst statische Optionen im Format "1:Aktiv,0:Inaktiv" in ein assoziatives Array.
 */
function ew_ParseOptionsString($optStr)
{
    $result = array();
    $parts = explode(',', (string)$optStr);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        if (strpos($p, ':') !== false) {
            list($k, $v) = explode(':', $p, 2);
            $result[trim($k)] = trim($v);
        } else {
            $result[$p] = $p;
        }
    }
    return $result;
}

/**
 * Lädt alle Optionen einer Lookup-Tabelle (mit In-Memory-Cache für schnelle Listenansichten).
 */
function ew_GetLookupOptions($table, $linkField, $displayField, $orderBy = '')
{
    static $cache = array();
    $key = $table . '|' . $linkField . '|' . $displayField . '|' . $orderBy;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $conn = ew_Connect();
    $safeTable = '`' . str_replace('`', '``', $table) . '`';
    $safeLink = '`' . str_replace('`', '``', $linkField) . '`';
    $safeDisp = '`' . str_replace('`', '``', $displayField) . '`';

    $sql = "SELECT " . $safeLink . " AS lf, " . $safeDisp . " AS df FROM " . $safeTable;
    if ($orderBy !== '') {
        $sql .= " ORDER BY " . $orderBy;
    } else {
        $sql .= " ORDER BY " . $safeDisp . " ASC";
    }
    $sql .= " LIMIT 500";

    $rows = $conn->LoadRows($sql);
    $opts = array();
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $opts[(string)$r['lf']] = $r['df'];
        }
    }
    $cache[$key] = $opts;
    return $opts;
}

function ew_ResolveLookupValue($table, $linkField, $displayField, $val)
{
    $opts = ew_GetLookupOptions($table, $linkField, $displayField);
    $k = (string)$val;
    return isset($opts[$k]) ? $opts[$k] : false;
}

/**
 * Rendert das passende HTML-Eingabeelement (Edit Tag) für Add, Edit, Extended Search und Multi-Update.
 */
function ew_RenderEditControl($fieldMeta, $currentValue, $mode = 'add', $prefix = 'x_')
{
    $name = $prefix . $fieldMeta['name'];
    $id = $prefix . $fieldMeta['name'];
    $editTag = isset($fieldMeta['edit_tag']) ? $fieldMeta['edit_tag'] : 'TEXT';
    $isReadOnly = ($editTag === 'READONLY') || ($mode === 'edit' && !empty($fieldMeta['read_only_edit']));
    $reqAttr = (!empty($fieldMeta['required']) && $mode !== 'search') ? ' required' : '';

    if ($isReadOnly && $mode !== 'search') {
        if ($editTag === 'SIGNATURE' && strpos((string)$currentValue, 'data:image/') === 0) {
            return '<input type="hidden" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '">'
                . '<div style="display:inline-block; background:#fff; border:1px solid #ccc; padding:4px; border-radius:3px;"><img src="' . ew_HtmlEncode($currentValue) . '" alt="Unterschrift" style="max-height:80px;"></div>';
        }
        return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '" readonly>';
    }

    if ($editTag === 'HIDDEN' && $mode !== 'search') {
        return '<input type="hidden" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '"><span class="form-control-static text-muted">' . ew_HtmlEncode($currentValue) . '</span>';
    }

    // Optionen bestimmen (entweder aus Lookup-Tabelle oder aus Options-String)
    $options = array();
    if (!empty($fieldMeta['lookup_table']) && !empty($fieldMeta['lookup_link_field']) && !empty($fieldMeta['lookup_display_field'])) {
        $options = ew_GetLookupOptions(
            $fieldMeta['lookup_table'],
            $fieldMeta['lookup_link_field'],
            $fieldMeta['lookup_display_field'],
            isset($fieldMeta['lookup_order_by']) ? $fieldMeta['lookup_order_by'] : ''
        );
    } elseif (!empty($fieldMeta['options'])) {
        $options = ew_ParseOptionsString($fieldMeta['options']);
    }

    switch ($editTag) {
        case 'SIGNATURE':
            if ($mode === 'search') {
                return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '" placeholder="Unterschrift vorhanden...">';
            }
            $safeId = ew_HtmlEncode($id);
            $safeName = ew_HtmlEncode($name);
            $safeVal = ew_HtmlEncode((string)$currentValue);
            $sigHtml = '<div class="ewSignaturePadWidget" data-sig-target="' . $safeId . '" style="max-width:460px;">';
            $sigHtml .= '<input type="hidden" name="' . $safeName . '" id="' . $safeId . '" value="' . $safeVal . '"' . $reqAttr . '>';
            $sigHtml .= '<div style="position:relative; border:2px dashed #3c8dbc; border-radius:4px; background:#ffffff; touch-action:none; -ms-touch-action:none; user-select:none;">';
            $sigHtml .= '<canvas id="canvas_' . $safeId . '" width="440" height="150" style="width:100%; height:150px; display:block; cursor:crosshair; touch-action:none;"></canvas>';
            $sigHtml .= '<div style="position:absolute; bottom:30px; left:20px; right:20px; border-bottom:1px dotted #ccc; pointer-events:none;"></div>';
            $sigHtml .= '<span style="position:absolute; bottom:10px; left:20px; font-size:11px; color:#999; pointer-events:none;"><i class="fa fa-pencil"></i> Bitte hier mit Finger, Stift (Stylus) oder Maus unterschreiben</span>';
            $sigHtml .= '</div>';
            $sigHtml .= '<div style="margin-top:6px; display:flex; justify-content:space-between; align-items:center;">';
            $sigHtml .= '<button type="button" class="btn btn-default btn-xs" onclick="ew_ClearSignaturePad(\'' . $safeId . '\');"><i class="fa fa-eraser"></i> Unterschrift löschen / Neu zeichnen</button>';
            $sigHtml .= '<span id="sig_status_' . $safeId . '" class="text-muted" style="font-size:11px;">' . ($safeVal !== '' ? '<i class="fa fa-check text-success"></i> Unterschrift erfasst' : 'Noch keine Unterschrift') . '</span>';
            $sigHtml .= '</div>';
            $sigHtml .= '</div>';
            return $sigHtml;

        case 'TEXTAREA':
            if ($mode === 'search') {
                return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '" placeholder="' . ew_HtmlEncode($fieldMeta['caption']) . '">';
            }
            $rows = isset($fieldMeta['textarea_rows']) && (int)$fieldMeta['textarea_rows'] > 0 ? (int)$fieldMeta['textarea_rows'] : 4;
            return '<textarea class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" rows="' . $rows . '"' . $reqAttr . '>' . ew_HtmlEncode($currentValue) . '</textarea>';

        case 'PASSWORD':
            return '<input type="password" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '"' . $reqAttr . '>';

        case 'SELECT':
            $html = '<select class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '"' . $reqAttr . '>';
            $html .= '<option value="">-- Bitte wählen --</option>';
            foreach ($options as $k => $v) {
                $sel = ((string)$currentValue !== '' && (string)$currentValue === (string)$k) ? ' selected' : '';
                $html .= '<option value="' . ew_HtmlEncode($k) . '"' . $sel . '>' . ew_HtmlEncode($v) . '</option>';
            }
            $html .= '</select>';
            return $html;

        case 'RADIO':
            if (empty($options)) {
                $options = array('1' => 'Ja', '0' => 'Nein');
            }
            $html = '<div class="ewRadioList">';
            if ($mode === 'search') {
                $selAll = ((string)$currentValue === '') ? ' checked' : '';
                $html .= '<label class="radio-inline"><input type="radio" name="' . ew_HtmlEncode($name) . '" value=""' . $selAll . '> Alle</label>';
            }
            foreach ($options as $k => $v) {
                $chk = ((string)$currentValue !== '' && (string)$currentValue === (string)$k) ? ' checked' : '';
                $html .= '<label class="radio-inline"><input type="radio" name="' . ew_HtmlEncode($name) . '" value="' . ew_HtmlEncode($k) . '"' . $chk . '> ' . ew_HtmlEncode($v) . '</label>';
            }
            $html .= '</div>';
            return $html;

        case 'CHECKBOX':
            if ($mode === 'search') {
                $html = '<select class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '">';
                $html .= '<option value="">-- Alle --</option>';
                $html .= '<option value="1"' . ((string)$currentValue === '1' ? ' selected' : '') . '>Ja (1)</option>';
                $html .= '<option value="0"' . ((string)$currentValue === '0' ? ' selected' : '') . '>Nein (0)</option>';
                $html .= '</select>';
                return $html;
            }
            $checked = ($currentValue == 1 || strtolower((string)$currentValue) === 'true') ? ' checked' : '';
            return '<div class="checkbox"><label><input type="hidden" name="' . ew_HtmlEncode($name) . '" value="0"><input type="checkbox" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="1"' . $checked . '> Aktiv / Ja</label></div>';

        case 'DATE':
            $inputType = ($fieldMeta['base_type'] === 'date') ? 'date' : 'text';
            $valFormatted = (string)$currentValue;
            if ($inputType === 'date' && $valFormatted !== '') {
                $ts = @strtotime($valFormatted);
                if ($ts !== false) {
                    $valFormatted = date('Y-m-d', $ts);
                }
            }
            return '<div class="input-group"><span class="input-group-addon"><i class="fa fa-calendar"></i></span><input type="' . $inputType . '" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($valFormatted) . '" placeholder="YYYY-MM-DD"' . $reqAttr . '></div>';

        default: // TEXT
            $maxAttr = (!empty($fieldMeta['max_length']) && (int)$fieldMeta['max_length'] > 0 && $fieldMeta['ew_type'] === 'STRING')
                ? ' maxlength="' . (int)$fieldMeta['max_length'] . '"'
                : '';
            return '<input type="text" class="form-control" name="' . ew_HtmlEncode($name) . '" id="' . ew_HtmlEncode($id) . '" value="' . ew_HtmlEncode($currentValue) . '"' . $maxAttr . $reqAttr . '>';
    }
}

/**
 * Validiert einen Formularwert anhand der PHPMacherNG-Feldregeln.
 * @return string Fehlermeldung oder leerer String bei Erfolg
 */
function ew_ValidateField($fieldMeta, $val)
{
    $caption = $fieldMeta['caption'];
    $strVal = trim((string)$val);

    if (!empty($fieldMeta['required']) && $fieldMeta['edit_tag'] !== 'CHECKBOX') {
        if ($strVal === '') {
            return 'Bitte geben Sie einen Wert für das Pflichtfeld <strong>' . ew_HtmlEncode($caption) . '</strong> ein.';
        }
    }

    if ($strVal === '') {
        return '';
    }

    $rule = isset($fieldMeta['validation']) ? $fieldMeta['validation'] : '';
    switch ($rule) {
        case 'Integer':
            if (!preg_match('/^-?\d+$/', $strVal)) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss eine gültige Ganzzahl sein.';
            }
            break;
        case 'Float':
            $norm = str_replace(',', '.', $strVal);
            if (!is_numeric($norm)) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss eine gültige Dezimalzahl sein.';
            }
            break;
        case 'Email':
            if (!filter_var($strVal, FILTER_VALIDATE_EMAIL)) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss eine gültige E-Mail-Adresse enthalten.';
            }
            break;
        case 'Date':
            if (@strtotime($strVal) === false) {
                return 'Das Feld <strong>' . ew_HtmlEncode($caption) . '</strong> muss ein gültiges Datum enthalten.';
            }
            break;
    }
    return '';
}

/**
 * Hasht ein Passwort sicher mit bcrypt (PHP 5.5+) oder SHA-256 Fallback (PHP 5.4).
 * Erkennt bereits gehashte Werte (bcrypt $2y$/$2a$, SHA-256 64-Hex, MD5 32-Hex),
 * damit unveränderte Passwörter beim Bearbeiten (*edit.php) nicht doppelt gehasht werden.
 */
function ew_HashPassword($plain)
{
    $plain = (string)$plain;
    if ($plain === '') {
        return '';
    }
    // Bereits ein bcrypt / argon2 Hash?
    if (strpos($plain, '$2y$') === 0 || strpos($plain, '$2a$') === 0 || strpos($plain, '$2b$') === 0 || strpos($plain, '$argon2') === 0) {
        return $plain;
    }
    // Bereits ein SHA-256 (64 Hex) oder MD5 (32 Hex) Hash?
    if (preg_match('/^[a-f0-9]{64}$/i', $plain) || preg_match('/^[a-f0-9]{32}$/i', $plain)) {
        return $plain;
    }
    if (function_exists('password_hash')) {
        return password_hash($plain, PASSWORD_DEFAULT);
    }
    return hash('sha256', $plain);
}

/**
 * Bereitet einen Formularwert für das Speichern in der Datenbank auf.
 */
function ew_PrepareDbValue($fieldMeta, $val)
{
    $strVal = trim((string)$val);
    if ($strVal === '' && !empty($fieldMeta['is_nullable'])) {
        return null;
    }
    // Passwort-Felder automatisch vor dem Speichern mit bcrypt / SHA-256 hashen
    if ((isset($fieldMeta['edit_tag']) && $fieldMeta['edit_tag'] === 'PASSWORD') ||
        (isset($fieldMeta['name']) && in_array(strtolower($fieldMeta['name']), array('passwort', 'password', 'passwd', 'pwd')))) {
        return ew_HashPassword($strVal);
    }
    if ($fieldMeta['ew_type'] === 'FLOAT') {
        return str_replace(',', '.', $strVal);
    }
    if ($fieldMeta['ew_type'] === 'INTEGER' || $fieldMeta['ew_type'] === 'BOOLEAN') {
        return ($strVal === '') ? 0 : (int)$strVal;
    }
    return $strVal;
}

/**
 * PHPMacherNG (v14) Advanced Security Klasse (cAdvancedSecurity)
 * Unterstützt Hardcoded Admin, User Table, Static & Dynamic User Levels und User ID Security.
 * Kompatibel mit PHP 5.4 bis PHP 8.4+.
 */
class cAdvancedSecurity
{
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_SECONDS = 30;

    public $Config = array();
    public $CurrentUserName = '';
    public $CurrentUserID = '';
    public $CurrentParentUserID = '';
    public $CurrentUserLevelID = -2; // -2 = Anonymous, -1 = Admin, 0 = Default
    public $CurrentUserLevelName = 'Anonymous';
    public $UserLevelPriv = array(); // $UserLevelPriv[$tableName] = int Bitmaske
    public $SubordinateUserIDs = array();

    public function __construct()
    {
        global $EW_SECURITY_CONFIG;
        $this->Config = isset($EW_SECURITY_CONFIG) && is_array($EW_SECURITY_CONFIG) ? $EW_SECURITY_CONFIG : array();
        $this->LoadUserFromSession();
        $this->LoadPermissions();
    }

    public function IsEnabled()
    {
        return !empty($this->Config['enabled']);
    }

    public function LoadUserFromSession()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        if (isset($_SESSION[$prefix . 'logged_in']) && $_SESSION[$prefix . 'logged_in'] === true) {
            $this->CurrentUserName = isset($_SESSION[$prefix . 'username']) ? (string)$_SESSION[$prefix . 'username'] : '';
            $this->CurrentUserID = isset($_SESSION[$prefix . 'userid']) ? $_SESSION[$prefix . 'userid'] : '';
            $this->CurrentParentUserID = isset($_SESSION[$prefix . 'parentid']) ? $_SESSION[$prefix . 'parentid'] : '';
            $this->CurrentUserLevelID = isset($_SESSION[$prefix . 'userlevel']) ? (int)$_SESSION[$prefix . 'userlevel'] : 0;
        } else {
            $this->CurrentUserName = '';
            $this->CurrentUserID = '';
            $this->CurrentParentUserID = '';
            $this->CurrentUserLevelID = -2;
        }
    }

    public function IsLoggedIn()
    {
        if (!$this->IsEnabled()) {
            return true;
        }
        return ($this->CurrentUserName !== '' && $this->CurrentUserLevelID > -2);
    }

    public function IsSysAdmin()
    {
        if (!$this->IsEnabled()) {
            return true;
        }
        return ($this->CurrentUserLevelID === -1);
    }

    /**
     * Prüft, ob aktuell eine Brute-Force-Sperre aktiv ist und gibt die verbleibenden Sekunden zurück.
     */
    public function GetLockoutRemaining()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        if (!empty($_SESSION[$prefix . 'lockout_until'])) {
            $rem = (int)$_SESSION[$prefix . 'lockout_until'] - time();
            if ($rem > 0) {
                return $rem;
            }
            unset($_SESSION[$prefix . 'lockout_until']);
            $_SESSION[$prefix . 'failed_attempts'] = 0;
        }
        return 0;
    }

    protected function RecordFailedAttempt()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        $fails = isset($_SESSION[$prefix . 'failed_attempts']) ? (int)$_SESSION[$prefix . 'failed_attempts'] + 1 : 1;
        $_SESSION[$prefix . 'failed_attempts'] = $fails;
        if ($fails >= self::MAX_FAILED_ATTEMPTS) {
            $_SESSION[$prefix . 'lockout_until'] = time() + self::LOCKOUT_SECONDS;
        }
    }

    protected function ResetFailedAttempts()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        $_SESSION[$prefix . 'failed_attempts'] = 0;
        unset($_SESSION[$prefix . 'lockout_until']);
    }

    public function ValidateUser($username, $password)
    {
        if ($this->GetLockoutRemaining() > 0) {
            return false;
        }

        $username = trim((string)$username);
        $password = (string)$password;
        if ($username === '' || $password === '') {
            return false;
        }

        // 1. Hardcoded Admin prüfen
        if (!empty($this->Config['hardcoded_admin_enabled'])) {
            $admUser = isset($this->Config['admin_username']) ? (string)$this->Config['admin_username'] : 'admin';
            $admPass = isset($this->Config['admin_password']) ? (string)$this->Config['admin_password'] : 'admin';
            if ($admUser !== '' && $username === $admUser && $this->VerifyPassword($password, $admPass)) {
                $this->ResetFailedAttempts();
                $this->SaveUserToSession($admUser, -1, '', -1);
                return true;
            }
        }

        // 2. Datenbank Benutzer-Tabelle prüfen
        if (!empty($this->Config['use_user_table']) && !empty($this->Config['user_table'])) {
            $conn = ew_Connect();
            $uTable = '`' . str_replace('`', '``', $this->Config['user_table']) . '`';
            $uField = '`' . str_replace('`', '``', $this->Config['username_field']) . '`';
            $sql = 'SELECT * FROM ' . $uTable . ' WHERE ' . $uField . ' = ?';
            $row = $conn->LoadRow($sql, array($username));
            if ($row) {
                // Falls Spalte "aktiv" existiert und 0 ist -> Login verweigern
                if (isset($row['aktiv']) && (string)$row['aktiv'] === '0') {
                    $this->RecordFailedAttempt();
                    return false;
                }
                $pwCol = $this->Config['password_field'];
                $storedPw = isset($row[$pwCol]) ? (string)$row[$pwCol] : '';
                if ($this->VerifyPassword($password, $storedPw)) {
                    $uidCol = isset($this->Config['user_id_field']) ? $this->Config['user_id_field'] : '';
                    $pidCol = isset($this->Config['parent_user_id_field']) ? $this->Config['parent_user_id_field'] : '';
                    $lvlCol = isset($this->Config['user_level_field']) ? $this->Config['user_level_field'] : '';

                    $uid = ($uidCol !== '' && isset($row[$uidCol])) ? $row[$uidCol] : $username;
                    $pid = ($pidCol !== '' && isset($row[$pidCol])) ? $row[$pidCol] : '';
                    $lvl = ($lvlCol !== '' && isset($row[$lvlCol]) && $row[$lvlCol] !== '') ? (int)$row[$lvlCol] : 0;

                    $this->ResetFailedAttempts();
                    $this->SaveUserToSession($username, $uid, $pid, $lvl);
                    return true;
                }
            }
        }

        $this->RecordFailedAttempt();
        return false;
    }

    protected function VerifyPassword($inputPw, $storedPw)
    {
        if ($storedPw === '') {
            return false;
        }
        // 1. Klartext-Vergleich
        if ($inputPw === $storedPw) {
            return true;
        }
        // 2. PHP 5.5+ password_verify (bcrypt / argon2)
        if (function_exists('password_verify') && (strpos($storedPw, '$2') === 0 || strpos($storedPw, '$argon2') === 0)) {
            if (password_verify($inputPw, $storedPw)) {
                return true;
            }
        }
        // 3. MD5 Hash (32 Hex-Zeichen)
        if (strlen($storedPw) === 32 && strtolower($storedPw) === md5($inputPw)) {
            return true;
        }
        // 4. SHA-256 Hash (64 Hex-Zeichen)
        if (strlen($storedPw) === 64 && strtolower($storedPw) === hash('sha256', $inputPw)) {
            return true;
        }
        return false;
    }

    public function SaveUserToSession($username, $userid, $parentid, $userlevel)
    {
        if (session_id() !== '') {
            @session_regenerate_id(true);
        }
        $prefix = EW_PROJECT_ID . '_sec_';
        $_SESSION[$prefix . 'logged_in'] = true;
        $_SESSION[$prefix . 'username'] = $username;
        $_SESSION[$prefix . 'userid'] = $userid;
        $_SESSION[$prefix . 'parentid'] = $parentid;
        $_SESSION[$prefix . 'userlevel'] = (int)$userlevel;
        $this->LoadUserFromSession();
        $this->LoadPermissions();
    }

    public function Logout()
    {
        $prefix = EW_PROJECT_ID . '_sec_';
        unset($_SESSION[$prefix . 'logged_in']);
        unset($_SESSION[$prefix . 'username']);
        unset($_SESSION[$prefix . 'userid']);
        unset($_SESSION[$prefix . 'parentid']);
        unset($_SESSION[$prefix . 'userlevel']);
        if (session_id() !== '') {
            @session_regenerate_id(true);
        }
        $this->LoadUserFromSession();
        $this->LoadPermissions();
    }

    public function LoadPermissions()
    {
        $this->UserLevelPriv = array();
        if (!$this->IsEnabled()) {
            return;
        }

        // Rollenname ermitteln
        $levels = isset($this->Config['static_user_levels']) && is_array($this->Config['static_user_levels'])
            ? $this->Config['static_user_levels']
            : array();
        foreach ($levels as $l) {
            if ((int)$l['id'] === $this->CurrentUserLevelID) {
                $this->CurrentUserLevelName = $l['name'];
                break;
            }
        }

        // 1. Statische Berechtigungen als Basis laden
        $staticPerms = isset($this->Config['static_permissions']) && is_array($this->Config['static_permissions'])
            ? $this->Config['static_permissions']
            : array();
        foreach ($staticPerms as $tName => $lvlMap) {
            $lvlKey = (string)$this->CurrentUserLevelID;
            $this->UserLevelPriv[$tName] = isset($lvlMap[$lvlKey]) ? (int)$lvlMap[$lvlKey] : 0;
        }

        // 2. Falls Dynamic User Levels aktiv sind -> aus Datenbanktabelle (userlevelpermissions) überschreiben
        if (!empty($this->Config['user_level_security']) && !empty($this->Config['dynamic_user_levels'])) {
            $conn = ew_Connect();
            if ($conn->IsConnected()) {
                $lvlTable = isset($this->Config['user_level_table']) ? $this->Config['user_level_table'] : 'userlevels';
                $privTable = isset($this->Config['user_level_priv_table']) ? $this->Config['user_level_priv_table'] : 'userlevelpermissions';

                $safeLvlT = '`' . str_replace('`', '``', $lvlTable) . '`';
                $lvlRow = @$conn->LoadRow('SELECT `userlevelname` FROM ' . $safeLvlT . ' WHERE `userlevelid` = ?', array($this->CurrentUserLevelID));
                if ($lvlRow && isset($lvlRow['userlevelname'])) {
                    $this->CurrentUserLevelName = $lvlRow['userlevelname'];
                }

                $safePrivT = '`' . str_replace('`', '``', $privTable) . '`';
                $privRows = @$conn->LoadRows('SELECT `tablename`, `permission` FROM ' . $safePrivT . ' WHERE `userlevelid` = ?', array($this->CurrentUserLevelID));
                if (is_array($privRows)) {
                    foreach ($privRows as $pr) {
                        $tKey = $pr['tablename'];
                        // Unterstützt sowohl reinen Tabellennamen als auch {PROJECT_ID}tablename
                        $cleanTKey = str_replace(EW_PROJECT_ID, '', $tKey);
                        $this->UserLevelPriv[$cleanTKey] = (int)$pr['permission'];
                    }
                }
            }
        }
    }

    /**
     * Prüft ein spezifisches Recht (z.B. EW_ALLOW_LIST, EW_ALLOW_ADD, EW_ALLOW_EDIT, EW_ALLOW_DELETE, EW_ALLOW_VIEW)
     */
    public function HasPermission($tableName, $permBit)
    {
        if (!$this->IsEnabled() || $this->IsSysAdmin()) {
            return true;
        }
        // Falls User Level Security deaktiviert ist, darf jeder eingeloggte Benutzer alles
        if (empty($this->Config['user_level_security'])) {
            return $this->IsLoggedIn();
        }
        $priv = isset($this->UserLevelPriv[$tableName]) ? (int)$this->UserLevelPriv[$tableName] : 0;
        if (($priv & EW_ALLOW_ADMIN) === EW_ALLOW_ADMIN) {
            return true;
        }
        return (($priv & (int)$permBit) === (int)$permBit);
    }

    public function CanList($tableName) { return $this->HasPermission($tableName, EW_ALLOW_LIST); }
    public function CanView($tableName) { return $this->HasPermission($tableName, EW_ALLOW_VIEW); }
    public function CanAdd($tableName)  { return $this->HasPermission($tableName, EW_ALLOW_ADD); }
    public function CanEdit($tableName) { return $this->HasPermission($tableName, EW_ALLOW_EDIT); }
    public function CanDelete($tableName) { return $this->HasPermission($tableName, EW_ALLOW_DELETE); }
    public function CanSearch($tableName) { return $this->HasPermission($tableName, EW_ALLOW_SEARCH); }
    public function CanAdmin($tableName) { return $this->HasPermission($tableName, EW_ALLOW_ADMIN); }

    /**
     * Prüft die Seitenberechtigung und leitet bei fehlenden Rechten auf login.php oder index.php um.
     */
    public function CheckPagePermission($tableName, $permBit)
    {
        if (!$this->IsEnabled()) {
            return;
        }
        if ($this->HasPermission($tableName, $permBit)) {
            return;
        }
        if (!$this->IsLoggedIn()) {
            ew_SetFailureMessage('Bitte melden Sie sich an, um auf diese Seite zuzugreifen.');
            ew_Redirect('login.php?return=' . urlencode(basename($_SERVER['REQUEST_URI'])));
        } else {
            ew_SetFailureMessage('Sie verfügen nicht über die erforderlichen Berechtigungen (Rolle: <strong>' . ew_HtmlEncode($this->CurrentUserLevelName) . '</strong>) für diese Aktion.');
            ew_Redirect('index.php');
        }
    }

    /**
     * Ermittelt alle gültigen User-IDs für den aktuellen Benutzer (eigene ID + untergeordnete Mitarbeiter via Parent User ID).
     */
    public function GetAllowedUserIDs()
    {
        if ($this->CurrentUserID === '' || $this->IsSysAdmin()) {
            return array();
        }
        $ids = array($this->CurrentUserID);

        // Falls Parent User ID Feld konfiguriert ist, direkte Untergebene aus der Benutzertabelle laden
        if (!empty($this->Config['use_user_table']) && !empty($this->Config['user_table']) && !empty($this->Config['parent_user_id_field']) && !empty($this->Config['user_id_field'])) {
            $conn = ew_Connect();
            $uTable = '`' . str_replace('`', '``', $this->Config['user_table']) . '`';
            $uidCol = '`' . str_replace('`', '``', $this->Config['user_id_field']) . '`';
            $pidCol = '`' . str_replace('`', '``', $this->Config['parent_user_id_field']) . '`';
            $rows = @$conn->LoadRows('SELECT ' . $uidCol . ' AS uid FROM ' . $uTable . ' WHERE ' . $pidCol . ' = ?', array($this->CurrentUserID));
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (isset($r['uid']) && !in_array($r['uid'], $ids)) {
                        $ids[] = $r['uid'];
                    }
                }
            }
        }
        return $ids;
    }

    /**
     * Erzeugt den SQL-WHERE-Filter für User ID Security (Eigentümer-Beschränkung auf Zeilenebene).
     */
    public function BuildUserIDFilter($tableObj, &$params)
    {
        if (!$this->IsEnabled() || $this->IsSysAdmin() || $this->CanAdmin($tableObj->TableName)) {
            return '';
        }
        $userIdField = isset($tableObj->Config['user_id_field']) ? trim($tableObj->Config['user_id_field']) : '';
        if ($userIdField === '' || !isset($tableObj->Fields[$userIdField])) {
            return '';
        }
        if ($this->CurrentUserID === '') {
            return '1=0';
        }
        $allowedIds = $this->GetAllowedUserIDs();
        $col = '`' . str_replace('`', '``', $userIdField) . '`';
        if (count($allowedIds) <= 1) {
            $params[] = $this->CurrentUserID;
            return '(' . $col . ' = ?)';
        }
        $placeholders = array();
        foreach ($allowedIds as $aid) {
            $placeholders[] = '?';
            $params[] = $aid;
        }
        return '(' . $col . ' IN (' . implode(', ', $placeholders) . '))';
    }
}

/**
 * Globale Hilfsfunktionen zum Abrufen der Security-Instanz und des Benutzer-/Zeit-Kontexts
 * (100% kompatibel mit PHPMacherNG Server Events & Custom Files).
 * @return cAdvancedSecurity
 */
function &ew_Security()
{
    global $EW_SECURITY;
    if (!isset($EW_SECURITY) || !($EW_SECURITY instanceof cAdvancedSecurity)) {
        $EW_SECURITY = new cAdvancedSecurity();
    }
    return $EW_SECURITY;
}

function &Security()
{
    return ew_Security();
}

function CurrentUserName()
{
    return ew_Security()->CurrentUserName;
}

function CurrentUserID()
{
    return ew_Security()->CurrentUserID;
}

function CurrentParentUserID()
{
    return ew_Security()->CurrentParentUserID;
}

function CurrentUserLevel()
{
    return ew_Security()->CurrentUserLevelID;
}

function CurrentUserLevelName()
{
    return ew_Security()->CurrentUserLevelName;
}

function IsLoggedIn()
{
    return ew_Security()->IsLoggedIn();
}

function IsSysAdmin()
{
    return ew_Security()->IsSysAdmin();
}

function CurrentDate()
{
    return date('Y-m-d');
}

function CurrentTime()
{
    return date('H:i:s');
}

function CurrentDateTime()
{
    return date('Y-m-d H:i:s');
}

function CurrentUserIP()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}