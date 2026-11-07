<?php
/**
 * PHPMacherNG - Multi-User Studio Authentifizierung & Benutzerverwaltung (StudioAuth.php)
 * Schützt die Studio-Oberfläche (index.php) und die Generator-API (api.php) mit Multi-User-Accounts,
 * Rollen (admin / developer), Brute-Force-Schutz und Session-Hardening.
 * Kompatibel mit PHP 5.4 bis PHP 8.4+.
 */

class StudioAuth
{
    const SESSION_KEY = 'phpmaker2018_studio_authenticated';
    const SESSION_USER_KEY = 'phpmaker2018_studio_username';
    const DEFAULT_PASSWORD = '12345678';
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_SECONDS = 30;

    public static function startSession()
    {
        if (!headers_sent()) {
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.use_only_cookies', '1');
            self::sendSecurityHeaders();
        }
        if (!headers_sent() && session_id() === '') {
            @session_start();
        }
    }

    /**
     * Sendet empfohlene HTTP-Security-Header gemäß IT-Sicherheitskonzept (BSI / OWASP).
     */
    public static function sendSecurityHeaders()
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
    }

    public static function getAuthFilePath()
    {
        return dirname(__DIR__) . '/projects/.studio_auth.json';
    }

    public static function getUsersFilePath()
    {
        return dirname(__DIR__) . '/projects/.studio_users.json';
    }

    /**
     * Bereinigt einen Benutzernamen (kleingeschrieben, alphanumerisch + _ . -).
     */
    public static function normalizeUsername($username)
    {
        $u = strtolower(trim((string)$username));
        $u = preg_replace('/[^a-z0-9_.-]/', '', $u);
        return $u;
    }

    /**
     * Lädt die Legacy-Auth-Konfiguration oder legt sie mit dem Initial-Passwort "12345678" an.
     */
    public static function loadConfig()
    {
        $file = self::getAuthFilePath();
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $data = @json_decode($raw, true);
            if (is_array($data) && !empty($data['password_hash'])) {
                return $data;
            }
        }

        $defaultConfig = array(
            'password_hash' => hash('sha256', self::DEFAULT_PASSWORD),
            'is_initial'    => true,
            'updated_at'    => date('Y-m-d H:i:s')
        );
        @file_put_contents($file, json_encode($defaultConfig));
        return $defaultConfig;
    }

    /**
     * Lädt alle Studio-Benutzer aus projects/.studio_users.json.
     * Falls die Datei noch nicht existiert, wird sie mit dem Admin-Account (migriert aus .studio_auth.json)
     * sowie zwei Team-Demo-Accounts ("entwickler1" und "entwickler2") initialisiert.
     * @return array
     */
    public static function loadUsers()
    {
        $file = self::getUsersFilePath();
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $data = @json_decode($raw, true);
            if (is_array($data) && isset($data['users']) && is_array($data['users']) && !empty($data['users'])) {
                return $data['users'];
            }
        }

        $legacyCfg = self::loadConfig();
        $adminHash = isset($legacyCfg['password_hash']) ? $legacyCfg['password_hash'] : hash('sha256', self::DEFAULT_PASSWORD);
        $now = date('Y-m-d H:i:s');

        $defaultUsers = array(
            'admin' => array(
                'username'      => 'admin',
                'display_name'  => 'Studio Administrator',
                'email'         => 'admin@phpmacher.local',
                'role'          => 'admin',
                'active'        => true,
                'password_hash' => $adminHash,
                'is_initial'    => !empty($legacyCfg['is_initial']),
                'created_at'    => $now,
                'updated_at'    => $now
            ),
            'entwickler1' => array(
                'username'      => 'entwickler1',
                'display_name'  => 'Max Mustermann (Entwickler 1)',
                'email'         => 'entwickler1@phpmacher.local',
                'role'          => 'developer',
                'active'        => true,
                'password_hash' => hash('sha256', self::DEFAULT_PASSWORD),
                'is_initial'    => true,
                'created_at'    => $now,
                'updated_at'    => $now
            ),
            'entwickler2' => array(
                'username'      => 'entwickler2',
                'display_name'  => 'Erika Musterfrau (Entwickler 2)',
                'email'         => 'entwickler2@phpmacher.local',
                'role'          => 'developer',
                'active'        => true,
                'password_hash' => hash('sha256', self::DEFAULT_PASSWORD),
                'is_initial'    => true,
                'created_at'    => $now,
                'updated_at'    => $now
            )
        );

        self::saveUsers($defaultUsers);
        return $defaultUsers;
    }

    /**
     * Speichert das Benutzer-Array in projects/.studio_users.json.
     */
    public static function saveUsers($users)
    {
        $file = self::getUsersFilePath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $payload = array(
            'updated_at' => date('Y-m-d H:i:s'),
            'users'      => $users
        );
        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;
        return (@file_put_contents($file, json_encode($payload, $flags)) !== false);
    }

    /**
     * Gibt alle Benutzer ohne Passwort-Hashes zurück (für UI / Teilen-Dialog / Benutzerverwaltung).
     * @return array
     */
    public static function listPublicUsers()
    {
        $users = self::loadUsers();
        $list = array();
        foreach ($users as $uKey => $u) {
            $isActive = (!isset($u['active']) || !empty($u['active']));
            $list[] = array(
                'username'     => isset($u['username']) ? $u['username'] : $uKey,
                'display_name' => isset($u['display_name']) && $u['display_name'] !== '' ? $u['display_name'] : $uKey,
                'email'        => isset($u['email']) ? $u['email'] : '',
                'role'         => isset($u['role']) ? $u['role'] : 'developer',
                'active'       => $isActive,
                'created_at'   => isset($u['created_at']) ? $u['created_at'] : '',
                'updated_at'   => isset($u['updated_at']) ? $u['updated_at'] : ''
            );
        }
        return $list;
    }

    /**
     * Prüft, ob der aktuelle Benutzer im Studio angemeldet ist.
     */
    public static function isLoggedIn()
    {
        self::startSession();
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        $users = self::loadUsers();
        $uName = isset($_SESSION[self::SESSION_USER_KEY]) ? self::normalizeUsername($_SESSION[self::SESSION_USER_KEY]) : 'admin';
        if ($uName === '' || !isset($users[$uName])) {
            // Fallback für bestehende Sessions vor dem Multi-User-Upgrade
            if (isset($users['admin'])) {
                $_SESSION[self::SESSION_USER_KEY] = 'admin';
                return true;
            }
            return false;
        }
        if (isset($users[$uName]['active']) && !$users[$uName]['active']) {
            self::logout();
            return false;
        }
        return true;
    }

    /**
     * Gibt den Benutzernamen des aktuell angemeldeten Studio-Benutzers zurück.
     * @return string
     */
    public static function getCurrentUsername()
    {
        self::startSession();
        if (!self::isLoggedIn()) {
            return '';
        }
        return isset($_SESSION[self::SESSION_USER_KEY]) ? self::normalizeUsername($_SESSION[self::SESSION_USER_KEY]) : 'admin';
    }

    /**
     * Gibt das Profil des aktuell angemeldeten Benutzers (ohne Passwort-Hash) zurück.
     * @return array|null
     */
    public static function getCurrentUser()
    {
        $uName = self::getCurrentUsername();
        if ($uName === '') {
            return null;
        }
        $users = self::loadUsers();
        if (!isset($users[$uName])) {
            return null;
        }
        $u = $users[$uName];
        return array(
            'username'     => $uName,
            'display_name' => isset($u['display_name']) && $u['display_name'] !== '' ? $u['display_name'] : $uName,
            'email'        => isset($u['email']) ? $u['email'] : '',
            'role'         => isset($u['role']) ? $u['role'] : 'developer',
            'is_admin'     => (isset($u['role']) && $u['role'] === 'admin')
        );
    }

    /**
     * Prüft, ob der aktuell angemeldete Benutzer Studio-Administrator ist.
     * @return bool
     */
    public static function isAdmin()
    {
        $u = self::getCurrentUser();
        return (is_array($u) && !empty($u['is_admin']));
    }

    /**
     * Prüft, ob aktuell eine Brute-Force-Zeitsperre aktiv ist.
     * @return int Verbleibende Sperrsekunden (0 = keine Sperre)
     */
    public static function getLockoutRemaining()
    {
        self::startSession();
        $lockUntil = isset($_SESSION[self::SESSION_KEY . '_lock_until']) ? (int)$_SESSION[self::SESSION_KEY . '_lock_until'] : 0;
        $rem = $lockUntil - time();
        return ($rem > 0) ? $rem : 0;
    }

    /**
     * Prüft Benutzername & Passwort und meldet den Benutzer im Studio an.
     * Rückwärtskompatibel: Falls nur 1 Parameter übergeben wird, wird als Benutzer "admin" angenommen.
     * @param string $usernameOrPassword
     * @param string|null $password
     * @return bool
     */
    public static function login($usernameOrPassword, $password = null)
    {
        self::startSession();
        if (self::getLockoutRemaining() > 0) {
            return false;
        }

        if ($password === null) {
            $username = 'admin';
            $plainPassword = (string)$usernameOrPassword;
        } else {
            $username = self::normalizeUsername($usernameOrPassword);
            if ($username === '') {
                $username = 'admin';
            }
            $plainPassword = (string)$password;
        }

        $users = self::loadUsers();
        $inputHash = hash('sha256', $plainPassword);

        if (isset($users[$username]) && isset($users[$username]['password_hash']) && $inputHash === $users[$username]['password_hash']) {
            if (isset($users[$username]['active']) && !$users[$username]['active']) {
                $_SESSION[self::SESSION_KEY . '_deactivated'] = true;
                return false;
            }
            unset($_SESSION[self::SESSION_KEY . '_deactivated']);
            if (!headers_sent()) {
                @session_regenerate_id(true);
            }
            $_SESSION[self::SESSION_KEY] = true;
            $_SESSION[self::SESSION_USER_KEY] = $username;
            $_SESSION[self::SESSION_KEY . '_time'] = time();
            $_SESSION[self::SESSION_KEY . '_fails'] = 0;
            $_SESSION[self::SESSION_KEY . '_lock_until'] = 0;
            return true;
        }
        unset($_SESSION[self::SESSION_KEY . '_deactivated']);

        $fails = isset($_SESSION[self::SESSION_KEY . '_fails']) ? (int)$_SESSION[self::SESSION_KEY . '_fails'] + 1 : 1;
        $_SESSION[self::SESSION_KEY . '_fails'] = $fails;
        if ($fails >= self::MAX_FAILED_ATTEMPTS) {
            $_SESSION[self::SESSION_KEY . '_lock_until'] = time() + self::LOCKOUT_SECONDS;
            $_SESSION[self::SESSION_KEY . '_fails'] = 0;
        }
        return false;
    }

    /**
     * Registriert ein neues Entwickler-Benutzerkonto oder legt über die Admin-Verwaltung einen Benutzer an.
     */
    public static function createUser($username, $password, $displayName = '', $email = '', $role = 'developer')
    {
        $uName = self::normalizeUsername($username);
        if (strlen($uName) < 3) {
            return array('success' => false, 'error' => 'Der Benutzername muss mindestens 3 Zeichen lang sein (a-z, 0-9, _, -).');
        }
        if (strlen((string)$password) < 6) {
            return array('success' => false, 'error' => 'Das Passwort muss mindestens 6 Zeichen lang sein.');
        }

        $users = self::loadUsers();
        if (isset($users[$uName])) {
            return array('success' => false, 'error' => 'Der Benutzername "' . $uName . '" ist bereits vergeben.');
        }

        $cleanRole = ($role === 'admin') ? 'admin' : 'developer';
        $now = date('Y-m-d H:i:s');
        $users[$uName] = array(
            'username'      => $uName,
            'display_name'  => trim((string)$displayName) !== '' ? trim((string)$displayName) : $uName,
            'email'         => trim((string)$email),
            'role'          => $cleanRole,
            'active'        => true,
            'password_hash' => hash('sha256', (string)$password),
            'is_initial'    => false,
            'created_at'    => $now,
            'updated_at'    => $now
        );

        if (!self::saveUsers($users)) {
            return array('success' => false, 'error' => 'Benutzerdatei konnte nicht gespeichert werden.');
        }

        return array(
            'success' => true,
            'user'    => array(
                'username'     => $uName,
                'display_name' => $users[$uName]['display_name'],
                'email'        => $users[$uName]['email'],
                'role'         => $cleanRole,
                'active'       => true
            ),
            'message' => 'Benutzer "' . $uName . '" wurde erfolgreich angelegt.'
        );
    }

    /**
     * Aktualisiert einen bestehenden Benutzer (Admin oder eigenes Profil):
     * Anzeigename, E-Mail, Rolle (admin/developer), Aktiv-Status (active) und optionales neues Passwort.
     */
    public static function updateUser($targetUsername, $data, $actingUsername = '')
    {
        $uName = self::normalizeUsername($targetUsername);
        $users = self::loadUsers();
        if (!isset($users[$uName])) {
            return array('success' => false, 'error' => 'Benutzer "' . $uName . '" wurde nicht gefunden.');
        }

        $acting = $actingUsername !== '' ? self::normalizeUsername($actingUsername) : self::getCurrentUsername();
        $isActingAdmin = (isset($users[$acting]) && isset($users[$acting]['role']) && $users[$acting]['role'] === 'admin');
        if (!$isActingAdmin && $acting !== $uName) {
            return array('success' => false, 'error' => 'Keine Berechtigung zum Bearbeiten dieses Benutzers.');
        }

        if (isset($data['display_name'])) {
            $disp = trim((string)$data['display_name']);
            $users[$uName]['display_name'] = ($disp !== '') ? $disp : $uName;
        }
        if (isset($data['email'])) {
            $users[$uName]['email'] = trim((string)$data['email']);
        }
        if ($isActingAdmin && isset($data['role'])) {
            $newRole = ($data['role'] === 'admin') ? 'admin' : 'developer';
            if ($uName === 'admin' && $newRole !== 'admin') {
                return array('success' => false, 'error' => 'Das Hauptkonto "admin" muss immer die Rolle Studio-Admin behalten.');
            }
            // Verhindern, dass der letzte aktive Admin degradiert wird
            if ($users[$uName]['role'] === 'admin' && $newRole !== 'admin') {
                $adminCount = 0;
                foreach ($users as $chk) {
                    $chkActive = (!isset($chk['active']) || !empty($chk['active']));
                    if ($chkActive && isset($chk['role']) && $chk['role'] === 'admin') {
                        $adminCount++;
                    }
                }
                if ($adminCount <= 1) {
                    return array('success' => false, 'error' => 'Die Rolle des letzten aktiven Studio-Administrators kann nicht entzogen werden.');
                }
            }
            $users[$uName]['role'] = $newRole;
        }
        if ($isActingAdmin && array_key_exists('active', $data)) {
            $newActive = !empty($data['active']) && $data['active'] !== '0' && $data['active'] !== 'false';
            if (!$newActive) {
                if ($uName === 'admin') {
                    return array('success' => false, 'error' => 'Das Hauptkonto "admin" kann nicht deaktiviert werden.');
                }
                if ($uName === $acting) {
                    return array('success' => false, 'error' => 'Sie können Ihr aktuell angemeldetes eigenes Konto nicht selbst deaktivieren.');
                }
                // Prüfen, dass mindestens ein aktiver Admin verbleibt
                if (isset($users[$uName]['role']) && $users[$uName]['role'] === 'admin') {
                    $activeAdmins = 0;
                    foreach ($users as $chkKey => $chk) {
                        $chkAct = (!isset($chk['active']) || !empty($chk['active']));
                        if ($chkKey !== $uName && $chkAct && isset($chk['role']) && $chk['role'] === 'admin') {
                            $activeAdmins++;
                        }
                    }
                    if ($activeAdmins < 1) {
                        return array('success' => false, 'error' => 'Der letzte aktive Studio-Administrator kann nicht deaktiviert werden.');
                    }
                }
            }
            $users[$uName]['active'] = $newActive;
        }
        if (isset($data['new_password']) && trim((string)$data['new_password']) !== '') {
            $newPw = (string)$data['new_password'];
            if (strlen($newPw) < 6) {
                return array('success' => false, 'error' => 'Das neue Passwort muss mindestens 6 Zeichen lang sein.');
            }
            $users[$uName]['password_hash'] = hash('sha256', $newPw);
            $users[$uName]['is_initial'] = false;
            if ($uName === 'admin') {
                @file_put_contents(self::getAuthFilePath(), json_encode(array(
                    'password_hash' => $users[$uName]['password_hash'],
                    'is_initial'    => false,
                    'updated_at'    => date('Y-m-d H:i:s')
                )));
            }
        }
        if (!isset($users[$uName]['active'])) {
            $users[$uName]['active'] = true;
        }
        $users[$uName]['updated_at'] = date('Y-m-d H:i:s');

        if (!self::saveUsers($users)) {
            return array('success' => false, 'error' => 'Fehler beim Speichern der Benutzerdaten.');
        }
        return array(
            'success' => true,
            'user'    => $users[$uName],
            'message' => 'Benutzer "' . $uName . '" wurde erfolgreich aktualisiert.'
        );
    }

    /**
     * Löscht einen Studio-Benutzer (nur durch Studio-Admin möglich, letzter Admin kann nicht gelöscht werden).
     */
    public static function deleteUser($targetUsername)
    {
        if (!self::isAdmin()) {
            return array('success' => false, 'error' => 'Nur Studio-Administratoren dürfen Benutzerkonten löschen.');
        }
        $uName = self::normalizeUsername($targetUsername);
        if ($uName === '' || $uName === 'admin') {
            return array('success' => false, 'error' => 'Das Hauptkonto "admin" kann nicht gelöscht werden.');
        }
        if ($uName === self::getCurrentUsername()) {
            return array('success' => false, 'error' => 'Sie können Ihr aktuell angemeldetes Konto nicht selbst löschen.');
        }

        $users = self::loadUsers();
        if (!isset($users[$uName])) {
            return array('success' => false, 'error' => 'Benutzer "' . $uName . '" existiert nicht.');
        }
        unset($users[$uName]);
        if (!self::saveUsers($users)) {
            return array('success' => false, 'error' => 'Fehler beim Speichern der Benutzerliste.');
        }
        return array('success' => true, 'message' => 'Benutzer "' . $uName . '" wurde gelöscht.');
    }

    /**
     * Meldet den Benutzer vom Studio ab.
     */
    public static function logout()
    {
        self::startSession();
        unset($_SESSION[self::SESSION_KEY]);
        unset($_SESSION[self::SESSION_USER_KEY]);
        unset($_SESSION[self::SESSION_KEY . '_time']);
        if (!headers_sent()) {
            @session_regenerate_id(true);
        }
    }

    /**
     * Ändert das Passwort des aktuell angemeldeten Benutzers nach Überprüfung des bisherigen Passworts.
     */
    public static function changePassword($currentPassword, $newPassword)
    {
        $uName = self::getCurrentUsername();
        if ($uName === '') {
            $uName = 'admin';
        }
        $users = self::loadUsers();
        if (!isset($users[$uName])) {
            return array('success' => false, 'error' => 'Benutzerkonto nicht gefunden.');
        }

        if (hash('sha256', (string)$currentPassword) !== $users[$uName]['password_hash']) {
            return array('success' => false, 'error' => 'Das aktuelle Passwort ist nicht korrekt.');
        }
        if (strlen((string)$newPassword) < 6) {
            return array('success' => false, 'error' => 'Das neue Passwort muss mindestens 6 Zeichen lang sein.');
        }

        $newHash = hash('sha256', (string)$newPassword);
        $users[$uName]['password_hash'] = $newHash;
        $users[$uName]['is_initial'] = false;
        $users[$uName]['updated_at'] = date('Y-m-d H:i:s');

        if (!self::saveUsers($users)) {
            return array('success' => false, 'error' => 'Fehler beim Speichern der Benutzer-Datei.');
        }

        if ($uName === 'admin') {
            $newCfg = array(
                'password_hash' => $newHash,
                'is_initial'    => false,
                'updated_at'    => date('Y-m-d H:i:s')
            );
            @file_put_contents(self::getAuthFilePath(), json_encode($newCfg));
        }

        return array('success' => true, 'message' => 'Ihr Passwort für "' . $uName . '" wurde erfolgreich geändert.');
    }
}
