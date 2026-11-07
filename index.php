<?php
/**
 * PHPMacherNG - Hauptoberfläche (index.php)
 * Kompatibel mit PHP 5.4 bis PHP 8.4+
 */
require_once dirname(__FILE__) . '/src/StudioAuth.php';

$studioAuthError = '';
$studioAuthSuccess = '';
if (isset($_GET['studio_logout'])) {
    StudioAuth::logout();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studio_register_username'])) {
    $regUser = isset($_POST['studio_register_username']) ? trim((string)$_POST['studio_register_username']) : '';
    $regName = isset($_POST['studio_register_displayname']) ? trim((string)$_POST['studio_register_displayname']) : '';
    $regPass = isset($_POST['studio_register_password']) ? (string)$_POST['studio_register_password'] : '';
    $regRes = StudioAuth::createUser($regUser, $regPass, $regName, '', 'developer');
    if (!empty($regRes['success'])) {
        StudioAuth::login($regUser, $regPass);
        header('Location: index.php');
        exit;
    } else {
        $studioAuthError = isset($regRes['error']) ? $regRes['error'] : 'Registrierung fehlgeschlagen.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studio_login_password'])) {
    $loginUsername = isset($_POST['studio_login_username']) && trim((string)$_POST['studio_login_username']) !== ''
        ? trim((string)$_POST['studio_login_username'])
        : 'admin';
    $lockRem = StudioAuth::getLockoutRemaining();
    if ($lockRem > 0) {
        $studioAuthError = 'Zu viele Fehlversuche! Bitte warten Sie noch ' . $lockRem . ' Sekunden.';
    } elseif (StudioAuth::login($loginUsername, $_POST['studio_login_password'])) {
        header('Location: index.php');
        exit;
    } else {
        $lockRemAfter = StudioAuth::getLockoutRemaining();
        if (!empty($_SESSION[StudioAuth::SESSION_KEY . '_deactivated'])) {
            $studioAuthError = 'Dieses Benutzerkonto ist aktuell deaktiviert. Bitte wenden Sie sich an den Studio-Administrator.';
        } elseif ($lockRemAfter > 0) {
            $studioAuthError = 'Zu viele Fehlversuche! Login für ' . $lockRemAfter . ' Sekunden gesperrt.';
        } else {
            $studioAuthError = 'Ungültiger Benutzername oder Passwort. Bitte erneut versuchen.';
        }
    }
}

$studioAuthCfg = StudioAuth::loadConfig();
$studioUsersList = StudioAuth::listPublicUsers();
$isStudioLoggedIn = StudioAuth::isLoggedIn();
$currentStudioUser = StudioAuth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHPMacherNG (PHP 5.4 - 8.4+ Code Generator)</title>
    <link rel="stylesheet" href="assets/vendor/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/studio.css?v=<?php echo @filemtime(dirname(__FILE__) . '/assets/css/studio.css'); ?>">
</head>
<body>
<?php if (!$isStudioLoggedIn): ?>
<div style="display:flex; align-items:center; justify-content:center; min-height:100vh; width:100vw; background:linear-gradient(135deg, #1e282c 0%, #2c3b41 100%); padding:20px;">
    <div style="width:450px; max-width:96vw; background:#fff; border-radius:4px; box-shadow:0 8px 30px rgba(0,0,0,0.35); overflow:hidden; border-top:4px solid #3c8dbc;">
        <div style="padding:18px 24px; background:#f8fafc; border-bottom:1px solid #e5e9ec; display:flex; align-items:center; gap:10px;">
            <i class="fa fa-cogs" style="font-size:24px; color:#f39c12;"></i>
            <div>
                <div style="font-size:16px; font-weight:700; color:#222;">PHPMacherNG Multi-User Studio</div>
                <div style="font-size:11.5px; color:#666;">Teamfähige Entwicklungsumgebung mit Projekt-Freigaben &amp; Git-Versionierung</div>
            </div>
        </div>

        <div style="padding:22px 24px;">
            <?php if ($studioAuthError !== ''): ?>
                <div style="padding:10px 12px; margin-bottom:16px; background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; border-radius:3px; font-size:12.5px; font-weight:600;">
                    <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($studioAuthError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Login-Formular -->
            <form method="post" action="index.php" id="studio-login-form">
                <div class="pm-form-group" style="margin-bottom:12px;">
                    <label for="studio_login_username" style="font-size:12.5px; margin-bottom:5px; display:block; font-weight:600;">
                        <i class="fa fa-user"></i> Benutzername:
                    </label>
                    <input type="text" id="studio_login_username" name="studio_login_username" class="pm-input" style="height:36px; font-size:14px;" value="<?php echo isset($_POST['studio_login_username']) ? htmlspecialchars($_POST['studio_login_username'], ENT_QUOTES, 'UTF-8') : 'admin'; ?>" placeholder="z. B. admin, entwickler1, entwickler2" required>
                </div>

                <div class="pm-form-group" style="margin-bottom:16px;">
                    <label for="studio_login_password" style="font-size:12.5px; margin-bottom:5px; display:block; font-weight:600;">
                        <i class="fa fa-lock"></i> Passwort:
                    </label>
                    <input type="password" id="studio_login_password" name="studio_login_password" class="pm-input" style="height:36px; font-size:14px;" placeholder="Passwort..." autofocus required>
                </div>

                <button type="submit" class="pm-btn pm-btn-primary" style="width:100%; height:36px; justify-content:center; font-size:13.5px;">
                    <i class="fa fa-sign-in"></i> Im Studio anmelden
                </button>
            </form>

            <!-- Schnellauswahl der vorhandenen Benutzerkonten -->
            <div style="margin-top:14px; padding:10px 12px; background:#f0f6fc; border:1px solid #cde0ef; border-radius:3px; font-size:11.5px; color:#1e3c72;">
                <div style="font-weight:700; margin-bottom:5px;"><i class="fa fa-users"></i> Verfügbare Studio-Konten (Schnellauswahl per Klick):</div>
                <div style="display:flex; flex-wrap:wrap; gap:5px;">
                    <?php foreach ($studioUsersList as $su): if (isset($su['active']) && !$su['active']) continue; ?>
                        <button type="button" class="pm-btn pm-btn-default" style="height:22px; font-size:11px; padding:0 8px; background:#fff; color:#1e3c72; border-color:#9bc0df;" onclick="document.getElementById('studio_login_username').value='<?php echo htmlspecialchars($su['username'], ENT_QUOTES, 'UTF-8'); ?>'; document.getElementById('studio_login_password').focus();">
                            <i class="fa <?php echo ($su['role'] === 'admin') ? 'fa-shield' : 'fa-user'; ?>"></i>
                            <?php echo htmlspecialchars($su['username'], ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:6px; color:#555; font-size:11px;">
                    Standard-Passwort für Initial- &amp; Demo-Konten: <code>12345678</code>
                </div>
            </div>

            <!-- Aufklappbare Selbst-Registrierung für neue Team-Mitglieder -->
            <div style="margin-top:14px; border-top:1px solid #e5e9ec; padding-top:10px;">
                <button type="button" class="pm-btn pm-btn-default" style="width:100%; justify-content:center; background:#f8fafc; color:#333; border-color:#d2d6de;" onclick="var b=document.getElementById('studio-reg-box'); b.style.display=(b.style.display==='none'?'block':'none');">
                    <i class="fa fa-user-plus"></i> Neues Entwickler-Konto registrieren...
                </button>
                <form method="post" action="index.php" id="studio-reg-box" style="display:none; margin-top:12px; padding:12px; background:#f9fbfd; border:1px solid #dce4ec; border-radius:3px;">
                    <div style="font-size:12px; font-weight:700; color:#1e3c72; margin-bottom:8px;">Neues Benutzerkonto anlegen</div>
                    <div class="pm-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px;">Benutzername (z. B. <code>name</code>):</label>
                        <input type="text" name="studio_register_username" class="pm-input" placeholder="mind. 3 Zeichen (a-z, 0-9)" required>
                    </div>
                    <div class="pm-form-group" style="margin-bottom:8px;">
                        <label style="font-size:11.5px;">Anzeigename (voller Name):</label>
                        <input type="text" name="studio_register_displayname" class="pm-input" placeholder="z. B. Julia Weber">
                    </div>
                    <div class="pm-form-group" style="margin-bottom:10px;">
                        <label style="font-size:11.5px;">Passwort (mind. 6 Zeichen):</label>
                        <input type="password" name="studio_register_password" class="pm-input" placeholder="Passwort..." required>
                    </div>
                    <button type="submit" class="pm-btn pm-btn-success" style="width:100%; justify-content:center;">
                        <i class="fa fa-check"></i> Konto erstellen &amp; direkt anmelden
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php exit; endif; ?>

<!-- Obere Anwendungsleiste (Topbar) -->
<header class="pm-topbar">
    <div class="pm-brand">
        <i class="fa fa-cogs" style="font-size:18px; color:#f39c12;"></i>
        <span>PHPMacherNG</span>
        <span class="pm-brand-badge">v14 Engine (PHP 5.4+)</span>
    </div>

    <div class="pm-toolbar-actions">

        <!-- Gruppe 1: Projekt-Auswahl, Speichern, Team-Freigabe & Git-Versionierung -->
        <div class="pm-toolbar-group" title="Projekt-Verwaltung, Team-Sharing &amp; Git-Versionierung">
            <span class="pm-toolbar-group-label"><i class="fa fa-folder-open-o"></i> Projekt</span>
            <select id="project-selector" class="pm-select" style="width:190px; height:26px; color:#222;" onchange="StudioApp.loadProject(this.value);"></select>
            <span id="project-access-badge" style="font-size:10.5px; padding:2px 6px; border-radius:3px; background:rgba(255,255,255,0.18); color:#fff; font-weight:600; white-space:nowrap;" title="Ihre Berechtigung für dieses Projekt">Eigentümer</span>

            <button type="button" class="pm-btn" id="btn-save-project" onclick="StudioApp.saveCurrentProject();" title="Aktuelles Projekt speichern (.pmp.json)">
                <i class="fa fa-floppy-o"></i> Speichern
            </button>

            <button type="button" class="pm-btn" onclick="StudioApp.openShareModal();" title="Projekt mit anderen Benutzern teilen &amp; Team-Berechtigungen verwalten" style="background:rgba(52,152,219,0.35); border-color:#5dade2;">
                <i class="fa fa-share-alt"></i> Teilen <span id="project-share-count-badge" style="display:none; background:#f39c12; color:#fff; border-radius:8px; padding:0 5px; font-size:10px; margin-left:2px;">0</span>
            </button>

            <button type="button" class="pm-btn" onclick="StudioApp.openGitModal();" title="Git-Versionierung: Commits, Branches, Tags, Diff-Vergleich &amp; Rollback" style="background:rgba(142,68,173,0.45); border-color:#a569bd;">
                <i class="fa fa-code-fork"></i> Git: <span id="git-branch-badge" style="font-family:Consolas,monospace; font-weight:bold;">main</span>
                <span id="git-dirty-dot" style="display:none; width:7px; height:7px; border-radius:50%; background:#f39c12; display:inline-block; margin-left:3px;" title="Ungespeicherte / nicht committete Änderungen"></span>
            </button>

            <div class="pm-dropdown" id="pm-dd-project">
                <button type="button" class="pm-btn" onclick="StudioApp.toggleDropdown('pm-dd-project', event);" title="Weitere Projekt-Aktionen (Neu, Import, Download, Löschen)">
                    <i class="fa fa-caret-down"></i> Datei
                </button>
                <div class="pm-dropdown-menu">
                    <div class="pm-dropdown-header">Projekt-Datei (.pmp.json)</div>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.createNewProject();">
                        <i class="fa fa-file-o"></i> Neues Projekt anlegen...
                    </button>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.saveCurrentProject();">
                        <i class="fa fa-floppy-o"></i> Projekt speichern
                    </button>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.openShareModal();">
                        <i class="fa fa-share-alt"></i> Projekt teilen / Team-Zugriff...
                    </button>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.openGitModal();">
                        <i class="fa fa-code-fork"></i> Git-Versionierung &amp; Historie...
                    </button>
                    <div class="pm-dropdown-sep"></div>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.downloadCurrentProject();">
                        <i class="fa fa-download"></i> Projekt herunterladen (.pmp.json)
                    </button>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); document.getElementById('project-upload-input').click();">
                        <i class="fa fa-upload"></i> Projekt importieren / hochladen...
                    </button>
                    <div class="pm-dropdown-sep"></div>
                    <button type="button" class="pm-dropdown-item pm-dropdown-item-danger" onclick="StudioApp.closeAllDropdowns(); StudioApp.deleteCurrentProject();">
                        <i class="fa fa-trash"></i> Aktuelles Projekt löschen
                    </button>
                </div>
            </div>
            <input type="file" id="project-upload-input" accept=".json,.pmp.json" style="display:none;" onchange="StudioApp.uploadProjectFile(this);">
        </div>

        <!-- Gruppe 2: Struktur & Werkzeuge -->
        <div class="pm-toolbar-group" title="Datenbank &amp; Struktur-Werkzeuge">
            <button type="button" class="pm-btn pm-btn-warning" onclick="StudioApp.connectAndSyncSchema();" title="Datenbank-Struktur abgleichen (Tabellen &amp; Spalten neu einlesen)">
                <i class="fa fa-refresh"></i> DB Sync
            </button>
            <button type="button" class="pm-btn" onclick="StudioApp.openViewBuilderModal();" title="Datenbank-View (SQL View) erstellen oder mit visuellem Query-Builder bearbeiten">
                <i class="fa fa-eye"></i> DB-View
            </button>
            <button type="button" class="pm-btn" onclick="StudioApp.switchTab('events');" title="Server Events (PHP) &amp; Client Scripts (JS) für dieses Projekt bearbeiten">
                <i class="fa fa-bolt"></i> Events &amp; Scripts
            </button>
            <button type="button" class="pm-btn" onclick="StudioApp.switchTab('menu');" title="PHPMacherNG Menu Editor öffnen (Menüeinträge &amp; Ebenen verschachteln)">
                <i class="fa fa-bars"></i> Menü
            </button>
        </div>

        <!-- Gruppe 3: Code-Generierung & Export -->
        <div class="pm-toolbar-group" title="Code-Generierung &amp; ZIP-Paket">
            <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.switchTab('generate'); StudioApp.runGeneration();" title="PHP-Anwendung jetzt generieren (F9)">
                <i class="fa fa-cogs"></i> Generieren (F9)
            </button>
            <button type="button" class="pm-btn" onclick="StudioApp.downloadGeneratedZip();" title="Generierte Web-Anwendung komplett als ZIP-Archiv herunterladen" style="background:rgba(0,166,90,0.35); border-color:#00a65a;">
                <i class="fa fa-file-archive-o"></i> App-ZIP
            </button>
        </div>

        <!-- Gruppe 4: Hilfe & Multi-User Konto -->
        <div class="pm-toolbar-group" title="Hilfe &amp; Benutzerkonto">
            <button type="button" class="pm-btn" onclick="StudioApp.switchTab('help');" title="Handbuch &amp; Entwickler-Referenz anzeigen (F1)">
                <i class="fa fa-question-circle"></i> Hilfe
            </button>

            <div class="pm-dropdown" id="pm-dd-system">
                <button type="button" class="pm-btn" onclick="StudioApp.toggleDropdown('pm-dd-system', event);" title="Angemeldeter Studio-Benutzer, Benutzerverwaltung &amp; Abmelden">
                    <i class="fa fa-user-circle"></i>
                    <span id="topbar-current-username"><?php echo htmlspecialchars(isset($currentStudioUser['username']) ? $currentStudioUser['username'] : 'admin', ENT_QUOTES, 'UTF-8'); ?></span>
                    <i class="fa fa-caret-down"></i>
                </button>
                <div class="pm-dropdown-menu">
                    <div class="pm-dropdown-header">
                        Angemeldet: <?php echo htmlspecialchars(isset($currentStudioUser['display_name']) ? $currentStudioUser['display_name'] : 'Admin', ENT_QUOTES, 'UTF-8'); ?>
                        (<?php echo (!empty($currentStudioUser['is_admin'])) ? 'Studio-Admin' : 'Entwickler'; ?>)
                    </div>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.openUsersModal();">
                        <i class="fa fa-users"></i> Studio-Benutzerverwaltung &amp; Profil...
                    </button>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.openShareModal();">
                        <i class="fa fa-share-alt"></i> Aktuelles Projekt teilen...
                    </button>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.openGitModal();">
                        <i class="fa fa-code-fork"></i> Git-Versionierung öffnen...
                    </button>
                    <button type="button" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns(); StudioApp.changeStudioPassword();">
                        <i class="fa fa-key"></i> Eigenes Passwort ändern...
                    </button>
                    <a href="hilfe.php" target="_blank" class="pm-dropdown-item" onclick="StudioApp.closeAllDropdowns();">
                        <i class="fa fa-book"></i> Handbuch in neuem Tab öffnen
                    </a>
                    <div class="pm-dropdown-sep"></div>
                    <a href="index.php?studio_logout=1" class="pm-dropdown-item pm-dropdown-item-danger">
                        <i class="fa fa-sign-out"></i> Aus Studio abmelden (Benutzer wechseln)
                    </a>
                </div>
            </div>
        </div>

    </div>
</header>

<!-- Haupt-Arbeitsbereich (Links: Tabellenbaum, Rechts: PHPMacherNG-Reiter) -->
<div class="pm-workspace">

    <!-- Linke Seitenleiste: Datenbank- & Tabellenbaum -->
    <aside class="pm-sidebar">
        <div class="pm-sidebar-header">
            <span><i class="fa fa-sitemap"></i> Datenbank-Struktur</span>
            <div style="display:flex; gap:4px;">
                <button type="button" class="pm-btn pm-btn-primary" style="padding:2px 6px; font-size:11px;" onclick="StudioApp.openViewBuilderModal();" title="Neue Datenbank-View (SQL View) erstellen">
                    <i class="fa fa-eye"></i> +View
                </button>
                <button type="button" class="pm-btn pm-btn-default" style="padding:2px 6px; font-size:11px;" onclick="StudioApp.connectAndSyncSchema();" title="Schema neu einlesen">
                    <i class="fa fa-refresh"></i>
                </button>
            </div>
        </div>
        <div id="sidebar-tree" class="pm-tree"></div>
    </aside>

    <!-- Rechter Hauptbereich -->
    <main class="pm-main">

        <!-- Die PHPMacherNG Reiter inkl. Server Events, Menu Editor & Hilfe -->
        <nav class="pm-tabs">
            <div class="pm-tab" data-tab="database">
                <i class="fa fa-database"></i> 1. Database
            </div>
            <div class="pm-tab" data-tab="table">
                <i class="fa fa-table"></i> 2. Table Setup
            </div>
            <div class="pm-tab active" data-tab="fields">
                <i class="fa fa-columns"></i> 3. Field Setup
            </div>
            <div class="pm-tab" data-tab="customfiles">
                <i class="fa fa-file-code-o"></i> 4. Custom Files
            </div>
            <div class="pm-tab" data-tab="events">
                <i class="fa fa-bolt"></i> 5. Code (Events &amp; Scripts)
            </div>
            <div class="pm-tab" data-tab="menu">
                <i class="fa fa-bars"></i> 6. Menu Editor
            </div>
            <div class="pm-tab" data-tab="security">
                <i class="fa fa-shield"></i> 7. Security
            </div>
            <div class="pm-tab" data-tab="settings">
                <i class="fa fa-sliders"></i> 8. PHP &amp; HTML
            </div>
            <div class="pm-tab" data-tab="generate">
                <i class="fa fa-code"></i> 9. Generate
            </div>
            <div class="pm-tab" data-tab="help">
                <i class="fa fa-book"></i> 10. Hilfe (F1)
            </div>
        </nav>

        <!-- TAB 1: DATABASE -->
        <section id="tab-database" class="pm-tab-content">
            <div class="pm-card">
                <h3 class="pm-card-title">
                    <span><i class="fa fa-plug"></i> MySQL / MariaDB Datenbankverbindung</span>
                    <button type="button" class="pm-btn pm-btn-warning" onclick="StudioApp.installDemoDatabase();">
                        <i class="fa fa-magic"></i> Demo-Datenbank ("phpmaker_demo") 1-Klick installieren
                    </button>
                </h3>

                <div class="pm-form-grid">
                    <div class="pm-form-group">
                        <label for="db-host">Server / Hostname</label>
                        <input type="text" id="db-host" class="pm-input" value="127.0.0.1" placeholder="127.0.0.1 oder localhost">
                    </div>
                    <div class="pm-form-group">
                        <label for="db-port">Port</label>
                        <input type="number" id="db-port" class="pm-input" value="3306">
                    </div>
                    <div class="pm-form-group">
                        <label for="db-user">Benutzername</label>
                        <input type="text" id="db-user" class="pm-input" value="root">
                    </div>
                    <div class="pm-form-group">
                        <label for="db-pass">Passwort</label>
                        <input type="password" id="db-pass" class="pm-input" value="">
                    </div>
                    <div class="pm-form-group">
                        <label for="db-name">Datenbankname</label>
                        <div style="display:flex; gap:6px;">
                            <input type="text" id="db-name" class="pm-input" value="phpmaker_demo" placeholder="Name der Datenbank">
                            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.fetchDatabasesList();" title="Verfügbare Datenbanken vom Server abrufen">
                                <i class="fa fa-folder-open-o"></i> Liste
                            </button>
                        </div>
                        <select id="db-quick-select" class="pm-select" style="display:none; margin-top:5px;" onchange="if(this.value) document.getElementById('db-name').value=this.value;"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="db-charset">Zeichensatz (Charset)</label>
                        <input type="text" id="db-charset" class="pm-input" value="utf8">
                    </div>
                </div>

                <div style="margin-top:16px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <button type="button" class="pm-btn pm-btn-primary" onclick="StudioApp.connectAndSyncSchema();">
                        <i class="fa fa-check"></i> Verbinden &amp; Tabellen synchronisieren
                    </button>
                    <div id="db-connection-msg" style="font-size:12.5px;"></div>
                </div>
            </div>

            <div class="pm-card">
                <h3 class="pm-card-title"><span><i class="fa fa-info-circle"></i> Hinweise zur PHP 5.4+ Kompatibilität</span></h3>
                <p style="margin:0; line-height:1.6; color:#444;">
                    Der Generator liest automatisch alle <strong>Tabellen</strong>, <strong>Views</strong>, <strong>Primärschlüssel</strong>, <strong>Auto-Increment-Spalten</strong>, <strong>ENUM-Werte</strong> sowie <strong>Fremdschlüssel-Beziehungen (Foreign Keys)</strong> aus Ihrer MySQL/MariaDB-Datenbank aus und richtet passende Lookup-Dropdowns und Datentyp-Validierungen vorab ein.
                </p>
            </div>
        </section>

        <!-- TAB 2: TABLE SETUP & MASTER/DETAIL -->
        <section id="tab-table" class="pm-tab-content">
            <div class="pm-card" style="flex:1; display:flex; flex-direction:column; margin-bottom:0; overflow:hidden;">
                <h3 class="pm-card-title">
                    <span><i class="fa fa-table"></i> Tabellen-Konfiguration (Table Setup), Diagramme &amp; Master/Detail-Verknüpfungen</span>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="pm-btn pm-btn-primary" onclick="StudioApp.openViewBuilderModal();">
                            <i class="fa fa-eye"></i> Datenbank-View erstellen / bearbeiten (Visueller SQL-Generator)
                        </button>
                        <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.openAddFieldsModal();">
                            <i class="fa fa-plus-circle"></i> Feld(er) zur ausgewählten Tabelle hinzufügen (inkl. Touch-Unterschrift)
                        </button>
                    </div>
                </h3>

                <div class="pm-grid-wrapper" style="flex:1;">
                    <table class="pm-grid-table">
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">Aktiv</th>
                                <th>Tabelle / View</th>
                                <th>Anzeigename (Caption)</th>
                                <th style="width:100px;">Menü-Icon</th>
                                <th style="text-align:center;">List</th>
                                <th style="text-align:center;">Add</th>
                                <th style="text-align:center;">Edit</th>
                                <th style="text-align:center;">View</th>
                                <th style="text-align:center;">Delete</th>
                                <th style="text-align:center;">Multi-Delete</th>
                                <th style="text-align:center;">Multi-Update</th>
                                <th>Diagramme (Balken/Torte)</th>
                                <th>Master/Detail (Unterlisten)</th>
                                <th>Primary Key</th>
                            </tr>
                        </thead>
                        <tbody id="table-setup-tbody"></tbody>
                    </table>
                </div>

                <!-- Untere Hälfte: PHPMacherNG Master/Detail- & Diagramm-Editor für die ausgewählte Master-Tabelle -->
                <div class="pm-inspector-pane" style="min-height:220px; max-height:270px; overflow-y:auto;">
                    <div id="table-master-detail-inspector"></div>
                </div>
            </div>
        </section>

        <!-- TAB 3: FIELD SETUP -->
        <section id="tab-fields" class="pm-tab-content active">
            <div class="pm-card" style="margin-bottom:0; flex:1; display:flex; flex-direction:column; overflow:hidden;">
                <h3 class="pm-card-title">
                    <span id="fields-current-table-title"><i class="fa fa-columns"></i> Feld-Konfiguration</span>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.openAddFieldsModal();">
                            <i class="fa fa-plus-circle"></i> Neue Felder / Touch-Unterschrift hinzufügen
                        </button>
                    </div>
                </h3>

                <!-- Obere Hälfte: Alle Spalten der gewählten Tabelle -->
                <div class="pm-grid-wrapper" style="flex:1;">
                    <table class="pm-grid-table">
                        <thead>
                            <tr>
                                <th>Feldname</th>
                                <th>SQL-Typ</th>
                                <th>Caption (Label)</th>
                                <th style="text-align:center;" title="In Listenansicht anzeigen">List</th>
                                <th style="text-align:center;" title="Im Hinzufügen-Formular">Add</th>
                                <th style="text-align:center;" title="Im Bearbeiten-Formular">Edit</th>
                                <th style="text-align:center;" title="In Detailansicht">View</th>
                                <th style="text-align:center;" title="Für Multi-Update freigeben">Multi-Upd</th>
                                <th style="text-align:center;" title="In Schnellsuche einbeziehen">QuickSearch</th>
                                <th style="text-align:center;" title="Im Erweiterten Suchfilter anzeigen">ExtSearch</th>
                                <th style="text-align:center;" title="Pflichtfeld">Req.</th>
                                <th>View Tag (Format)</th>
                                <th>Edit Tag (Input)</th>
                            </tr>
                        </thead>
                        <tbody id="fields-setup-tbody"></tbody>
                    </table>
                </div>

                <!-- Untere Hälfte: Detail-Inspektor für das ausgewählte Feld (wie in PHPMacherNG) -->
                <div class="pm-inspector-pane">
                    <div id="field-inspector-content"></div>
                </div>
            </div>
        </section>

        <!-- TAB 4: CUSTOM FILES (EIGENE DATEIEN & DATENZUGRIFFS-HELFER) -->
        <section id="tab-customfiles" class="pm-tab-content">
            <div class="pm-card" style="flex:1; display:flex; flex-direction:column; margin-bottom:0; overflow:hidden;">
                <h3 class="pm-card-title">
                    <span><i class="fa fa-file-code-o"></i> PHPMacherNG Custom Files (Eigene PHP-Seiten, Dashboards, Berichte &amp; API-Endpunkte)</span>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.addCustomFile();">
                            <i class="fa fa-plus"></i> Neue Custom File anlegen
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" style="color:#dd4b39;" onclick="StudioApp.deleteCurrentCustomFile();">
                            <i class="fa fa-trash"></i> Datei löschen
                        </button>
                    </div>
                </h3>

                <!-- Obere Leiste: Auswahl & Eigenschaften der eigenen Datei -->
                <div class="pm-form-grid" style="grid-template-columns: 220px 200px 220px 140px 1fr; margin-bottom:10px; align-items:end; background:#f8fafc; padding:10px 12px; border:1px solid #d2d6de; border-radius:3px;">
                    <div class="pm-form-group">
                        <label for="cf-selector">Ausgewählte Custom File</label>
                        <select id="cf-selector" class="pm-select" style="font-weight:bold;" onchange="StudioApp.selectCustomFile(this.value);"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="cf-filename">Dateiname (z.B. <code>dashboard.php</code>)</label>
                        <input type="text" id="cf-filename" class="pm-input" onchange="StudioApp.syncCustomFileForm();">
                    </div>
                    <div class="pm-form-group">
                        <label for="cf-caption">Menü-Titel (Caption)</label>
                        <input type="text" id="cf-caption" class="pm-input" onchange="StudioApp.syncCustomFileForm();">
                    </div>
                    <div class="pm-form-group">
                        <label for="cf-icon">FontAwesome Icon</label>
                        <input type="text" id="cf-icon" class="pm-input" placeholder="fa-dashboard" onchange="StudioApp.syncCustomFileForm();">
                    </div>
                    <div style="display:flex; gap:16px; flex-wrap:wrap; padding-bottom:4px;">
                        <label style="cursor:pointer; font-weight:600;" title="Genau wie in PHPMacherNG: Bindet Header, Sidebar, Footer, Security-Prüfung und DbHelper() automatisch ein.">
                            <input type="checkbox" id="cf-include-common" checked onchange="StudioApp.syncCustomFileForm();">
                            Include common files (Header/Footer/Security)
                        </label>
                        <label style="cursor:pointer; font-weight:600;">
                            <input type="checkbox" id="cf-in-menu" checked onchange="StudioApp.syncCustomFileForm();">
                            Im linken Hauptmenü anzeigen
                        </label>
                        <label style="cursor:pointer; font-weight:600;">
                            <input type="checkbox" id="cf-enabled" checked onchange="StudioApp.syncCustomFileForm();">
                            Datei generieren (Aktiv)
                        </label>
                    </div>
                </div>

                <!-- 1-Klick PHPMacherNG Datenzugriffs-Snippets -->
                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:8px; padding:6px 10px; background:#eef5f9; border:1px solid #c8d9e6; border-radius:3px;">
                    <strong style="font-size:11.5px; color:#1e5799; margin-right:4px;"><i class="fa fa-database"></i> PHPMacherNG Datenzugriffs-Funktion einfügen:</strong>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('execute_html');" title="Führt ein SELECT aus und gibt direkt eine fertige Bootstrap-HTML-Tabelle aus">
                        <code>DbHelper()-&gt;ExecuteHtml($sql)</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-primary" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('chart_bar');" title="Gibt eine SQL-Abfrage als interaktives Säulen-/Balkendiagramm aus">
                        <i class="fa fa-bar-chart"></i> <code>Säulen / Balken</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-success" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('chart_line');" title="Gibt eine SQL-Abfrage als Linien- oder Flächendiagramm aus">
                        <i class="fa fa-line-chart"></i> <code>Linie / Fläche</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-warning" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('chart_pie');" title="Gibt eine SQL-Abfrage als Torten- oder Donut-Diagramm aus">
                        <i class="fa fa-pie-chart"></i> <code>Torte / Donut</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('chart_gauge');" title="Gibt eine SQL-Abfrage als Trichter-, Radar- oder Tacho-Diagramm aus">
                        <i class="fa fa-tachometer"></i> <code>Trichter / Radar / Tacho</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('execute_scalar');" title="Liest einen einzelnen Wert (z.B. COUNT oder SUM) aus der Datenbank">
                        <code>ew_ExecuteScalar($sql)</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('execute_rows');" title="Lädt alle Ergebniszeilen als assoziatives Array für eigene foreach-Schleifen">
                        <code>ew_ExecuteRows($sql)</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('execute_row');" title="Lädt genau eine Ergebniszeile als assoziatives Array">
                        <code>ew_ExecuteRow($sql)</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('execute_update');" title="Führt ein INSERT, UPDATE oder DELETE mit Prepared-Statement-Parametern aus">
                        <code>ew_Execute($sql, $params)</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('execute_json');" title="Gibt ein SQL-Ergebnis direkt als JSON-String aus">
                        <code>ew_ExecuteJson($sql)</code>
                    </button>
                    <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 8px;" onclick="StudioApp.insertCustomFileSnippet('current_user');" title="Gibt Informationen zum aktuell angemeldeten Benutzer aus">
                        <code>CurrentUserName() / CurrentUserID()</code>
                    </button>
                </div>

                <!-- Zweigeteilter Bereich: Links Code-Editor, Rechts Kurzreferenz -->
                <div style="flex:1; display:grid; grid-template-columns: 1fr 310px; gap:12px; min-height:320px;">
                    <div style="display:flex; flex-direction:column;">
                        <textarea id="cf-content" style="flex:1; width:100%; resize:none; font-family:Consolas, 'Courier New', monospace; font-size:12.5px; line-height:1.45; padding:12px; background:#1e1e1e; color:#d4d4d4; border:1px solid #333; border-radius:3px;" spellcheck="false" onchange="StudioApp.syncCustomFileForm();" onkeyup="StudioApp.syncCustomFileForm();"></textarea>
                    </div>
                    <div style="background:#f9fafc; border:1px solid #d2d6de; border-radius:3px; padding:10px 12px; overflow-y:auto; font-size:11.5px; line-height:1.5;">
                        <h4 style="margin:0 0 8px 0; font-size:12.5px; color:#222; border-bottom:1px solid #ddd; padding-bottom:5px;">
                            <i class="fa fa-book"></i> PHPMacherNG API-Referenz
                        </h4>
                        <p style="margin:0 0 8px 0; color:#555;">
                            In jeder Custom File stehen Ihnen sofort <code>$db = &amp;DbHelper();</code>, <code>$conn = &amp;Conn();</code> und <code>$Security</code> sowie alle globalen PHPMacherNG-Funktionen zur Verfügung:
                        </p>
                        <div style="margin-bottom:8px;">
                            <strong>1. Automatische HTML-Tabelle:</strong><br>
                            <code>echo DbHelper()-&gt;ExecuteHtml($sql, array('fieldcaption' =&gt; true));</code><br>
                            <span style="color:#666;">Oder horizontal für 1 Datensatz:</span><br>
                            <code>echo ew_ExecuteHtml($sql, array('horizontal' =&gt; true));</code>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong>2. 9 Diagrammarten (ExecuteChart / ew_RenderChart):</strong><br>
                            <code>echo DbHelper()-&gt;ExecuteChart($sql, array('type' =&gt; 'bar', 'title' =&gt; 'Umsatz'));</code><br>
                            <span style="color:#555;">Typen: <code>bar</code>, <code>hbar</code>, <code>line</code>, <code>area</code>, <code>pie</code>, <code>doughnut</code>, <code>funnel</code>, <code>radar</code>, <code>gauge</code></span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong>3. Einzelwert abfragen (KPIs):</strong><br>
                            <code>$cnt = ew_ExecuteScalar("SELECT COUNT(*) FROM kunden");</code>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong>4. Mehrere Zeilen als Array:</strong><br>
                            <code>$rows = ew_ExecuteRows("SELECT * FROM produkte WHERE aktiv = ?", array(1));</code>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong>5. Einzelne Zeile als Array:</strong><br>
                            <code>$row = ew_ExecuteRow("SELECT * FROM kunden WHERE kunde_id = ?", array(5));</code>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong>6. Daten ändern (INSERT/UPDATE/DELETE):</strong><br>
                            <code>ew_Execute("UPDATE bestellungen SET status = ? WHERE bestellung_id = ?", array('Versendet', 1001));</code>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong>7. JSON für AJAX / Charts:</strong><br>
                            <code>echo ew_ExecuteJson("SELECT stadt, COUNT(*) AS anz FROM kunden GROUP BY stadt");</code>
                        </div>
                        <div>
                            <strong>8. Aktueller Benutzer &amp; Datum:</strong><br>
                            <code>CurrentUserName()</code>, <code>CurrentUserID()</code>,<br>
                            <code>CurrentParentUserID()</code>, <code>CurrentUserLevel()</code>,<br>
                            <code>CurrentUserLevelName()</code>, <code>IsLoggedIn()</code>,<br>
                            <code>IsSysAdmin()</code>, <code>CurrentDate()</code>, <code>CurrentDateTime()</code>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 5: CODE (SERVER EVENTS & CLIENT SCRIPTS) -->
        <section id="tab-events" class="pm-tab-content">
            <div class="pm-card" style="flex:1; display:flex; flex-direction:column; margin-bottom:0; overflow:hidden;">
                <h3 class="pm-card-title">
                    <span><i class="fa fa-bolt" style="color:#f39c12;"></i> PHPMacherNG Server Events (PHP) &amp; Client Scripts (JavaScript)</span>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span style="font-size:12px; font-weight:normal; color:#555;">Kontext / Tabelle:</span>
                        <select id="ev-scope-select" class="pm-select" style="min-width:240px; font-weight:600;" onchange="StudioApp.onEventScopeChange(this.value);">
                            <option value="__global__">🌐 Global (Alle Seiten &amp; Login)</option>
                        </select>
                        <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.insertEventTemplate();" title="Beispiel-Code-Vorlage für das aktuell ausgewählte Event einfügen">
                            <i class="fa fa-magic"></i> Beispiel-Vorlage einfügen
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" style="color:#dd4b39;" onclick="StudioApp.clearCurrentEventCode();" title="Code des aktuell ausgewählten Events leeren">
                            <i class="fa fa-eraser"></i> Event leeren
                        </button>
                    </div>
                </h3>

                <!-- Zweigeteilter Bereich: Links Event-Baum (Server Events & Client Scripts), Rechts Code-Editor mit Funktions-Signatur -->
                <div style="flex:1; display:grid; grid-template-columns: 310px 1fr; gap:12px; min-height:380px; overflow:hidden;">

                    <!-- Linke Spalte: Hierarchischer Event-Baum -->
                    <div style="display:flex; flex-direction:column; border:1px solid #d2d6de; border-radius:3px; background:#fff; overflow:hidden;">
                        <div style="padding:8px 10px; background:#f5f7fa; border-bottom:1px solid #d2d6de; font-weight:600; font-size:12px; display:flex; justify-content:space-between; align-items:center;">
                            <span><i class="fa fa-sitemap text-primary"></i> Events &amp; Scripts</span>
                            <span id="ev-active-count-badge" class="pm-badge" style="background:#00a65a; color:#fff; font-size:10.5px; padding:1px 6px; border-radius:10px;">0 aktiv</span>
                        </div>
                        <div id="ev-tree-list" style="flex:1; overflow-y:auto; padding:6px;"></div>
                    </div>

                    <!-- Rechte Spalte: Funktionskopf, Snippets, Code-Editor & Beschreibung -->
                    <div style="display:flex; flex-direction:column; overflow:hidden; border:1px solid #d2d6de; border-radius:3px; background:#fff;">

                        <!-- Event-Info & Beschreibung -->
                        <div style="padding:8px 12px; background:#f8fafc; border-bottom:1px solid #e1e6eb; display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span id="ev-type-badge" style="font-size:11px; font-weight:700; padding:2px 7px; border-radius:3px; background:#3c8dbc; color:#fff;">PHP Server Event</span>
                                    <strong id="ev-title-display" style="font-size:13.5px; color:#1e282c;">Row_Inserting</strong>
                                    <span id="ev-scope-badge" style="font-size:11.5px; color:#666;"></span>
                                </div>
                                <div id="ev-desc-display" style="font-size:12px; color:#555; margin-top:4px;"></div>
                            </div>
                        </div>

                        <!-- 1-Klick Kurz-Snippets passend zu Server Events / Client Scripts -->
                        <div id="ev-snippets-bar" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; padding:6px 10px; background:#eef5f9; border-bottom:1px solid #c8d9e6;">
                            <strong style="font-size:11px; color:#1e5799;"><i class="fa fa-code"></i> Snippet:</strong>
                            <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 7px;" onclick="StudioApp.insertEventQuickSnippet('rsnew_set');" title="Feldwert vor dem Speichern setzen ($rsnew)">
                                <code>$rsnew['feld'] = ...</code>
                            </button>
                            <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 7px;" onclick="StudioApp.insertEventQuickSnippet('cancel_error');" title="Vorgang mit Fehlermeldung abbrechen (return false)">
                                <code>Abbruch + Fehlermeldung</code>
                            </button>
                            <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 7px;" onclick="StudioApp.insertEventQuickSnippet('row_color');" title="Tabellenzeile in Row_Rendered farblich hervorheben">
                                <code>Zeile einfärben ($rowAttrs)</code>
                            </button>
                            <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 7px;" onclick="StudioApp.insertEventQuickSnippet('sql_filter');" title="SQL-Bedingung in Recordset_Selecting ergänzen">
                                <code>ew_AddFilter($filter, ...)</code>
                            </button>
                            <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 7px;" onclick="StudioApp.insertEventQuickSnippet('db_execute');" title="SQL-Befehl oder Abfrage in Event ausführen">
                                <code>ew_Execute / ew_ExecuteScalar</code>
                            </button>
                            <button type="button" class="pm-btn pm-btn-default" style="font-size:11px; padding:2px 7px;" onclick="StudioApp.insertEventQuickSnippet('js_field');" title="JavaScript-Zugriff auf Formularfeld (#x_feld)">
                                <code>JS: Feldwert prüfen (#x_...)</code>
                            </button>
                        </div>

                        <!-- Öffnende Funktions-Signatur (Read-Only Header wie in PHPMaker) -->
                        <div id="ev-signature-open" style="padding:6px 12px; background:#252526; color:#569cd6; font-family:Consolas, 'Courier New', monospace; font-size:12.5px; border-bottom:1px solid #333; user-select:none;">
                            function Row_Inserting($rsold, &amp;$rsnew) {
                        </div>

                        <!-- Haupt-Code-Textarea -->
                        <textarea id="ev-code-editor" style="flex:1; width:100%; resize:none; font-family:Consolas, 'Courier New', monospace; font-size:12.5px; line-height:1.5; padding:10px 16px; background:#1e1e1e; color:#d4d4d4; border:none; outline:none;" spellcheck="false" placeholder="// Eigenen Code hier eingeben (wird automatisch im aktuellen Projekt gespeichert)..." oninput="StudioApp.syncEventCodeEditor();"></textarea>

                        <!-- Schließende Funktions-Signatur (Read-Only Footer) -->
                        <div id="ev-signature-close" style="padding:6px 12px; background:#252526; color:#569cd6; font-family:Consolas, 'Courier New', monospace; font-size:12.5px; border-top:1px solid #333; display:flex; justify-content:space-between; align-items:center; user-select:none;">
                            <span>}</span>
                            <span style="font-size:11px; color:#9cdcfe;"><i class="fa fa-check-circle"></i> Projektspezifisch gespeichert in <code>.pmp.json</code></span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- TAB 6: MENU EDITOR (MENÜ BEARBEITEN, VERSCHACHTELN & EIGENE EINTRÄGE) -->
        <section id="tab-menu" class="pm-tab-content">
            <div class="pm-card" style="flex:1; display:flex; flex-direction:column; margin-bottom:0; overflow:hidden;">
                <h3 class="pm-card-title">
                    <span><i class="fa fa-bars"></i> PHPMacherNG Menu Editor (Menüeinträge &amp; Untermenü-Ebenen verwalten)</span>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.addMenuItem(false);">
                            <i class="fa fa-plus"></i> Neuer Menüeintrag / Ordner
                        </button>
                        <button type="button" class="pm-btn pm-btn-primary" onclick="StudioApp.addMenuItem(true);" title="Erstellt ein Untermenü direkt unterhalb des aktuell gewählten Eintrags">
                            <i class="fa fa-level-down"></i> Als Unterpunkt (Sub-Item) hinzufügen
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.addMenuHeader();" title="Fügt eine Abschnitts-Überschrift (Group Header) im Seitenmenü ein">
                            <i class="fa fa-header"></i> Gruppen-Überschrift
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.moveMenuItem(-1);" title="Ausgewählten Eintrag nach oben verschieben">
                            <i class="fa fa-arrow-up"></i> Hoch
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.moveMenuItem(1);" title="Ausgewählten Eintrag nach unten verschieben">
                            <i class="fa fa-arrow-down"></i> Runter
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.indentMenuItem(true);" title="Eine Ebene tiefer einrücken (Untermenü des vorherigen Eintrags)">
                            <i class="fa fa-indent"></i> Einrücken (&rarr;)
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.indentMenuItem(false);" title="Eine Ebene höher ausrücken">
                            <i class="fa fa-outdent"></i> Ausrücken (&larr;)
                        </button>
                        <button type="button" class="pm-btn pm-btn-default" style="color:#dd4b39;" onclick="StudioApp.deleteSelectedMenuItem();" title="Ausgewählten Menüeintrag löschen">
                            <i class="fa fa-trash"></i> Löschen
                        </button>
                        <button type="button" class="pm-btn pm-btn-warning" onclick="StudioApp.resetDefaultMenu();" title="Menü-Hierarchie aus Tabellen &amp; Custom Files neu aufbauen">
                            <i class="fa fa-refresh"></i> Standard wiederherstellen
                        </button>
                    </div>
                </h3>

                <!-- Zweigeteilter Bereich: Links interaktiver Menü-Baum, Rechts Eigenschaften-Inspektor & Live-Vorschau -->
                <div style="flex:1; display:grid; grid-template-columns: 1.25fr 1fr; gap:14px; min-height:360px; overflow:hidden;">

                    <!-- Linke Spalte: Hierarchische Menü-Baumansicht -->
                    <div style="display:flex; flex-direction:column; border:1px solid #d2d6de; border-radius:3px; background:#fff; overflow:hidden;">
                        <div style="padding:8px 12px; background:#f5f7fa; border-bottom:1px solid #d2d6de; font-weight:600; font-size:12px; display:flex; justify-content:space-between; align-items:center;">
                            <span><i class="fa fa-sitemap text-primary"></i> Menü-Struktur &amp; Verschachtelung (Ebenen)</span>
                            <span style="font-weight:normal; color:#666; font-size:11px;">Klicken zum Bearbeiten &bull; Ein-/Ausrücken für Ebenen</span>
                        </div>
                        <div id="menu-editor-tree-list" style="flex:1; overflow-y:auto; padding:6px;"></div>
                    </div>

                    <!-- Rechte Spalte: Eigenschaften des gewählten Menüpunkts + AdminLTE 2 Live-Vorschau -->
                    <div style="display:flex; flex-direction:column; gap:12px; overflow-y:auto;">
                        <div style="background:#f8fafc; border:1px solid #d2d6de; border-radius:3px; padding:12px;">
                            <h4 style="margin:0 0 10px 0; font-size:13px; color:#1e282c; border-bottom:1px solid #e1e6eb; padding-bottom:6px;">
                                <i class="fa fa-sliders text-primary"></i> Eigenschaften des ausgewählten Menüeintrags
                            </h4>

                            <div class="pm-form-grid" style="grid-template-columns: 1fr 1fr; gap:10px;">
                                <div class="pm-form-group" style="grid-column: span 2;">
                                    <label for="me-text">Anzeigetext im Menü (Menu Text / Caption)</label>
                                    <input type="text" id="me-text" class="pm-input" placeholder="z.B. Stammdaten, Kunden oder Externe Doku" oninput="StudioApp.syncMenuEditorForm();">
                                </div>

                                <div class="pm-form-group" style="grid-column: span 2;">
                                    <label for="me-parent">Übergeordneter Menüpunkt (Parent Item / Ebene)</label>
                                    <select id="me-parent" class="pm-select" onchange="StudioApp.syncMenuEditorForm();"></select>
                                </div>

                                <div class="pm-form-group">
                                    <label for="me-source">Ziel-Typ / Verknüpfung</label>
                                    <select id="me-source" class="pm-select" onchange="StudioApp.onMenuSourcePresetChange(this.value);"></select>
                                </div>

                                <div class="pm-form-group">
                                    <label for="me-url">Ziel-URL (Href / Skript / <code>#</code> für Ordner)</label>
                                    <input type="text" id="me-url" class="pm-input" placeholder="z.B. kundenlist.php, dashboard.php oder #" oninput="StudioApp.syncMenuEditorForm();">
                                </div>

                                <div class="pm-form-group">
                                    <label for="me-icon">FontAwesome Icon-Klasse</label>
                                    <div style="display:flex; gap:6px;">
                                        <input type="text" id="me-icon" class="pm-input" placeholder="fa-folder-o" oninput="StudioApp.syncMenuEditorForm();">
                                        <select class="pm-select" style="width:125px;" onchange="if(this.value){document.getElementById('me-icon').value=this.value; StudioApp.syncMenuEditorForm(); this.value='';}">
                                            <option value="">Icon wählen...</option>
                                            <option value="fa-dashboard">fa-dashboard</option>
                                            <option value="fa-briefcase">fa-briefcase</option>
                                            <option value="fa-folder-open-o">fa-folder-open-o</option>
                                            <option value="fa-table">fa-table</option>
                                            <option value="fa-users">fa-users</option>
                                            <option value="fa-shopping-cart">fa-shopping-cart</option>
                                            <option value="fa-cubes">fa-cubes</option>
                                            <option value="fa-tags">fa-tags</option>
                                            <option value="fa-bar-chart">fa-bar-chart</option>
                                            <option value="fa-file-code-o">fa-file-code-o</option>
                                            <option value="fa-cog">fa-cog</option>
                                            <option value="fa-shield">fa-shield</option>
                                            <option value="fa-external-link">fa-external-link</option>
                                            <option value="fa-circle-o">fa-circle-o</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="pm-form-group">
                                    <label for="me-target">Link-Ziel (Target)</label>
                                    <select id="me-target" class="pm-select" onchange="StudioApp.syncMenuEditorForm();">
                                        <option value="_self">Gleiches Fenster (_self)</option>
                                        <option value="_blank">Neuer Browser-Tab (_blank)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top:12px; display:flex; flex-direction:column; gap:6px; padding-top:8px; border-top:1px solid #e1e6eb; font-size:12px;">
                                <label style="cursor:pointer; font-weight:600;">
                                    <input type="checkbox" id="me-enabled" onchange="StudioApp.syncMenuEditorForm();">
                                    Menüeintrag aktivieren (Im Seitenmenü anzeigen)
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="checkbox" id="me-is-header" onchange="StudioApp.syncMenuEditorForm();">
                                    Als <strong>Abschnitts-Überschrift (Group Header)</strong> darstellen (nicht klickbar)
                                </label>
                                <label style="cursor:pointer;">
                                    <input type="checkbox" id="me-allow-anon" onchange="StudioApp.syncMenuEditorForm();">
                                    Auch für nicht angemeldete Besucher (Anonymous) sichtbar
                                </label>
                            </div>
                        </div>

                        <!-- Live-Vorschau des generierten AdminLTE 2 Seitenmenüs -->
                        <div style="background:#222d32; color:#b8c7ce; border-radius:3px; padding:10px 12px; flex:1;">
                            <div style="font-size:11px; color:#8aa4af; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; border-bottom:1px solid #2c3b41; padding-bottom:4px;">
                                <i class="fa fa-eye"></i> AdminLTE 2 Sidebar Live-Vorschau
                            </div>
                            <div id="menu-editor-live-preview" style="font-size:12.5px; line-height:1.7;"></div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- TAB 6: SECURITY (RECHTE, ROLLEN & BENUTZERANMELDUNG) -->
        <section id="tab-security" class="pm-tab-content">
            <!-- 1. Quick-Setup & Hardcoded Admin -->
            <div class="pm-card">
                <h3 class="pm-card-title">
                    <span><i class="fa fa-shield"></i> PHPMacherNG Advanced Security (Anmeldung, Rollen &amp; Rechte)</span>
                    <button type="button" class="pm-btn pm-btn-warning" onclick="StudioApp.installSecurityTables();">
                        <i class="fa fa-magic"></i> Systemtabellen (benutzer, userlevels, userlevelpermissions) 1-Klick in DB anlegen &amp; aktivieren
                    </button>
                </h3>

                <div id="sec-status-msg" style="margin-bottom:10px; font-size:12.5px;"></div>

                <div style="display:flex; gap:24px; flex-wrap:wrap; margin-bottom:14px; padding:10px 12px; background:#f8fafc; border:1px solid #d2d6de; border-radius:3px;">
                    <label style="cursor:pointer; font-weight:600; color:#222;">
                        <input type="checkbox" id="sec-enabled" onchange="StudioApp.syncSecurityForm(); StudioApp.renderSecurityTab();">
                        <i class="fa fa-lock"></i> Anmeldeschutz (Security) für generierte Anwendung aktivieren
                    </label>
                    <label style="cursor:pointer; font-weight:600; color:#222;">
                        <input type="checkbox" id="sec-admin-enabled" onchange="StudioApp.syncSecurityForm();">
                        Hardcoded Administrator-Konto aktivieren
                    </label>
                    <label style="cursor:pointer; font-weight:600; color:#222;">
                        <input type="checkbox" id="sec-use-user-table" onchange="StudioApp.syncSecurityForm(); StudioApp.renderSecurityTab();">
                        Datenbank-Benutzertabelle (Use Existing Table) verwenden
                    </label>
                </div>

                <div class="pm-form-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="pm-form-group">
                        <label for="sec-admin-user">Hardcoded Admin Login-Name</label>
                        <input type="text" id="sec-admin-user" class="pm-input" value="admin" onchange="StudioApp.syncSecurityForm();">
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-admin-pass">Hardcoded Admin Passwort</label>
                        <input type="text" id="sec-admin-pass" class="pm-input" value="admin" onchange="StudioApp.syncSecurityForm();">
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-user-table">Benutzer-Tabelle (Users Table)</label>
                        <select id="sec-user-table" class="pm-select" onchange="StudioApp.onSecurityUserTableChange(this.value);"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-username-field">Benutzername-Feld (Login Name Field)</label>
                        <select id="sec-username-field" class="pm-select" onchange="StudioApp.syncSecurityForm();"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-password-field">Passwort-Feld (Password Field)</label>
                        <select id="sec-password-field" class="pm-select" onchange="StudioApp.syncSecurityForm();"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-userid-field">Primäre User-ID (für Datensatz-Eigentümer)</label>
                        <select id="sec-userid-field" class="pm-select" onchange="StudioApp.syncSecurityForm();"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-parent-userid-field">Vorgesetzten-Feld (Parent User ID - Hierarchie)</label>
                        <select id="sec-parent-userid-field" class="pm-select" onchange="StudioApp.syncSecurityForm();"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-userlevel-field">Rollen-Feld (User Level Field - Integer)</label>
                        <select id="sec-userlevel-field" class="pm-select" onchange="StudioApp.syncSecurityForm();"></select>
                    </div>
                </div>
            </div>

            <!-- 2. User Level Security (Statisch & Dynamisch) + Rechte-Matrix -->
            <div class="pm-card" style="flex:1; display:flex; flex-direction:column;">
                <h3 class="pm-card-title">
                    <span><i class="fa fa-key"></i> Rollenbasierte Zugriffsrechte (User Level Security) &amp; Datensatz-Eigentümerschutz (User ID Security)</span>
                    <div style="display:flex; gap:14px; align-items:center; font-weight:normal; font-size:12px;">
                        <label style="cursor:pointer;">
                            <input type="checkbox" id="sec-ulevel-enabled" onchange="StudioApp.syncSecurityForm(); StudioApp.renderSecurityMatrix();">
                            <strong>User Level Security</strong> (Rollen &amp; Tabellenrechte) aktivieren
                        </label>
                        <label style="cursor:pointer;">
                            <input type="checkbox" id="sec-dynamic-enabled" onchange="StudioApp.syncSecurityForm(); StudioApp.renderSecurityMatrix();">
                            <strong>Dynamic User Levels</strong> (Rechte zur Laufzeit in DB-Tabellen verwalten)
                        </label>
                    </div>
                </h3>

                <div class="pm-form-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:12px;">
                    <div class="pm-form-group">
                        <label for="sec-level-table">Rollen-Tabelle (User Level Table)</label>
                        <select id="sec-level-table" class="pm-select" onchange="StudioApp.syncSecurityForm();"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-priv-table">Berechtigungs-Tabelle (Permissions Table)</label>
                        <select id="sec-priv-table" class="pm-select" onchange="StudioApp.syncSecurityForm();"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="sec-active-role">Rolle für Rechte-Matrix auswählen:</label>
                        <div style="display:flex; gap:6px;">
                            <select id="sec-active-role" class="pm-select" style="font-weight:bold;" onchange="StudioApp.renderSecurityMatrix();"></select>
                            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.addStaticRole();" title="Neue Benutzerrolle hinzufügen">
                                <i class="fa fa-plus"></i> Rolle
                            </button>
                        </div>
                    </div>
                    <div class="pm-form-group">
                        <label>Schnellauswahl für gewählte Rolle:</label>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.setAllRolePermissions(127);">Alle erlauben</button>
                            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.setAllRolePermissions(104);">Nur Lesen (List/View/Search)</button>
                            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.setAllRolePermissions(0);">Alle entziehen</button>
                        </div>
                    </div>
                </div>

                <div class="pm-grid-wrapper" style="flex:1; max-height:none;">
                    <table class="pm-grid-table">
                        <thead>
                            <tr>
                                <th>Tabelle / View</th>
                                <th>Caption</th>
                                <th style="text-align:center; width:65px;">Add (1)</th>
                                <th style="text-align:center; width:65px;">Delete (2)</th>
                                <th style="text-align:center; width:65px;">Edit (4)</th>
                                <th style="text-align:center; width:65px;">List (8)</th>
                                <th style="text-align:center; width:65px;">View (32)</th>
                                <th style="text-align:center; width:65px;">Search (64)</th>
                                <th style="text-align:center; width:75px;">Admin (16)</th>
                                <th style="text-align:center; width:70px;">Bitmaske</th>
                                <th style="width:240px;" title="User ID Security: Benutzer sehen/bearbeiten nur eigene Datensätze (plus unterstellte Mitarbeiter)">
                                    <i class="fa fa-user-secret"></i> User ID Feld (Zeilen-Eigentümer)
                                </th>
                            </tr>
                        </thead>
                        <tbody id="security-matrix-tbody"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- TAB 5: PHP & HTML SETTINGS -->
        <section id="tab-settings" class="pm-tab-content">
            <div class="pm-card">
                <h3 class="pm-card-title"><span><i class="fa fa-paint-brush"></i> Design-Template (Theme), Layout &amp; Globale PHP-Einstellungen</span></h3>

                <!-- Visuelle 1-Klick Template-Auswahlkarten -->
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:12px; color:#334155; margin-bottom:8px;">
                        <i class="fa fa-desktop text-primary"></i> Design-Template für die generierte Web-Anwendung auswählen:
                    </label>
                    <div id="st-template-cards" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(195px, 1fr)); gap:10px;">
                        <div class="pm-tpl-card" data-tpl="adminlte2" onclick="StudioApp.selectUiTemplate('adminlte2');" style="cursor:pointer; border:2px solid #3c8dbc; border-radius:6px; padding:10px; background:#f8fafc; transition:all 0.15s;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                <strong style="font-size:12.5px; color:#1e293b;"><i class="fa fa-columns" style="color:#3c8dbc;"></i> AdminLTE 2 Classic</strong>
                                <span class="pm-tpl-badge" style="font-size:10px; background:#3c8dbc; color:#fff; padding:1px 6px; border-radius:10px;">Standard</span>
                            </div>
                            <div style="height:44px; border:1px solid #cbd5e1; border-radius:3px; display:flex; overflow:hidden; margin-bottom:6px; background:#ecf0f5;">
                                <div style="width:26%; background:#222d32;"></div>
                                <div style="flex:1; display:flex; flex-direction:column;">
                                    <div style="height:10px; background:#3c8dbc;"></div>
                                    <div style="margin:4px; flex:1; background:#fff; border-top:2px solid #3c8dbc; border-radius:2px;"></div>
                                </div>
                            </div>
                            <div style="font-size:11px; color:#64748b; line-height:1.35;">Klassisches PHPMaker v2018 Layout mit linker Sidebar &amp; 12 Farb-Skins.</div>
                        </div>

                        <div class="pm-tpl-card" data-tpl="topnav" onclick="StudioApp.selectUiTemplate('topnav');" style="cursor:pointer; border:2px solid #cbd5e1; border-radius:6px; padding:10px; background:#fff; transition:all 0.15s;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                <strong style="font-size:12.5px; color:#1e293b;"><i class="fa fa-window-maximize" style="color:#0284c7;"></i> Top-Navbar (Horizontal)</strong>
                            </div>
                            <div style="height:44px; border:1px solid #cbd5e1; border-radius:3px; display:flex; flex-direction:column; overflow:hidden; margin-bottom:6px; background:#f1f5f9;">
                                <div style="height:12px; background:#0284c7; display:flex; align-items:center; gap:4px; padding:0 6px;">
                                    <span style="width:14px; height:4px; background:#fff; border-radius:1px;"></span>
                                    <span style="width:10px; height:3px; background:rgba(255,255,255,0.6);"></span>
                                    <span style="width:10px; height:3px; background:rgba(255,255,255,0.6);"></span>
                                </div>
                                <div style="margin:4px 8px; flex:1; background:#fff; border-radius:2px; border:1px solid #e2e8f0;"></div>
                            </div>
                            <div style="font-size:11px; color:#64748b; line-height:1.35;">Horizontale Menüleiste oben mit Dropdowns &amp; 100% Seitenbreite ohne Sidebar.</div>
                        </div>

                        <div class="pm-tpl-card" data-tpl="modern" onclick="StudioApp.selectUiTemplate('modern');" style="cursor:pointer; border:2px solid #cbd5e1; border-radius:6px; padding:10px; background:#fff; transition:all 0.15s;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                <strong style="font-size:12.5px; color:#1e293b;"><i class="fa fa-diamond" style="color:#4f46e5;"></i> Modern SaaS / Clean UI</strong>
                            </div>
                            <div style="height:44px; border:1px solid #e2e8f0; border-radius:5px; display:flex; overflow:hidden; margin-bottom:6px; background:#f8fafc;">
                                <div style="width:26%; background:#0f172a; padding:4px;">
                                    <div style="height:4px; width:80%; background:#6366f1; border-radius:2px; margin-bottom:3px;"></div>
                                    <div style="height:3px; width:65%; background:#475569; border-radius:2px;"></div>
                                </div>
                                <div style="flex:1; display:flex; flex-direction:column;">
                                    <div style="height:10px; background:#ffffff; border-bottom:1px solid #e2e8f0;"></div>
                                    <div style="margin:4px; flex:1; background:#fff; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,0.08);"></div>
                                </div>
                            </div>
                            <div style="font-size:11px; color:#64748b; line-height:1.35;">Modernes Enterprise-Design (Tailwind/Indigo-Stil) mit abgerundeten Cards &amp; Schatten.</div>
                        </div>

                        <div class="pm-tpl-card" data-tpl="dark" onclick="StudioApp.selectUiTemplate('dark');" style="cursor:pointer; border:2px solid #cbd5e1; border-radius:6px; padding:10px; background:#fff; transition:all 0.15s;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                <strong style="font-size:12.5px; color:#1e293b;"><i class="fa fa-moon-o" style="color:#334155;"></i> Dark Mode Pro</strong>
                            </div>
                            <div style="height:44px; border:1px solid #334155; border-radius:4px; display:flex; overflow:hidden; margin-bottom:6px; background:#0f172a;">
                                <div style="width:26%; background:#020617; border-right:1px solid #1e293b;"></div>
                                <div style="flex:1; display:flex; flex-direction:column;">
                                    <div style="height:10px; background:#1e293b; border-bottom:1px solid #334155;"></div>
                                    <div style="margin:4px; flex:1; background:#1e293b; border:1px solid #334155; border-radius:3px;"></div>
                                </div>
                            </div>
                            <div style="font-size:11px; color:#64748b; line-height:1.35;">Durchgängiges dunkles Theme (Slate/Anthrazit) für augenschonendes Arbeiten.</div>
                        </div>

                        <div class="pm-tpl-card" data-tpl="compact" onclick="StudioApp.selectUiTemplate('compact');" style="cursor:pointer; border:2px solid #cbd5e1; border-radius:6px; padding:10px; background:#fff; transition:all 0.15s;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                <strong style="font-size:12.5px; color:#1e293b;"><i class="fa fa-compress" style="color:#0d9488;"></i> Classic Compact (ERP)</strong>
                            </div>
                            <div style="height:44px; border:1px solid #cbd5e1; border-radius:2px; display:flex; overflow:hidden; margin-bottom:6px; background:#e2e8f0;">
                                <div style="width:22%; background:#334155;"></div>
                                <div style="flex:1; display:flex; flex-direction:column;">
                                    <div style="height:8px; background:#0f766e;"></div>
                                    <div style="margin:2px; flex:1; background:#fff; border:1px solid #94a3b8;"></div>
                                </div>
                            </div>
                            <div style="font-size:11px; color:#64748b; line-height:1.35;">Kompaktes High-Density-Layout (wie PHPMaker v10–v12) für große Datenmengen.</div>
                        </div>
                    </div>
                </div>

                <div class="pm-form-grid">
                    <div class="pm-form-group">
                        <label for="st-project-name">Projekt-Kurzname (Logo)</label>
                        <input type="text" id="st-project-name" class="pm-input">
                    </div>
                    <div class="pm-form-group">
                        <label for="st-title">Anwendungs-Titel (Browser &amp; Header)</label>
                        <input type="text" id="st-title" class="pm-input">
                    </div>
                    <div class="pm-form-group">
                        <label for="st-template">Oberflächen-Template (UI Theme)</label>
                        <select id="st-template" class="pm-select" onchange="StudioApp.selectUiTemplate(this.value);">
                            <option value="adminlte2">AdminLTE 2 Classic (Sidebar links)</option>
                            <option value="topnav">Top-Navbar Layout (Horizontales Menü oben)</option>
                            <option value="modern">Modern SaaS / Clean Enterprise UI</option>
                            <option value="dark">Dark Mode Pro (Dunkles Dashboard)</option>
                            <option value="compact">Classic Compact / ERP High-Density</option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label for="st-skin">Farbschema (Skin / Akzentfarbe)</label>
                        <select id="st-skin" class="pm-select">
                            <option value="skin-blue">skin-blue (Klassisch Blau / Dunkle Sidebar)</option>
                            <option value="skin-blue-light">skin-blue-light (Blau / Helle Sidebar)</option>
                            <option value="skin-black">skin-black (Schwarz-Weiß / Dunkle Sidebar)</option>
                            <option value="skin-black-light">skin-black-light (Schwarz-Weiß / Helle Sidebar)</option>
                            <option value="skin-green">skin-green (Grün / Dunkle Sidebar)</option>
                            <option value="skin-green-light">skin-green-light (Grün / Helle Sidebar)</option>
                            <option value="skin-purple">skin-purple (Violett / Dunkle Sidebar)</option>
                            <option value="skin-purple-light">skin-purple-light (Violett / Helle Sidebar)</option>
                            <option value="skin-red">skin-red (Rot / Dunkle Sidebar)</option>
                            <option value="skin-red-light">skin-red-light (Rot / Helle Sidebar)</option>
                            <option value="skin-yellow">skin-yellow (Gelb-Orange / Dunkle Sidebar)</option>
                            <option value="skin-yellow-light">skin-yellow-light (Gelb-Orange / Helle Sidebar)</option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label for="st-layout">Seiten-Layout-Modus (Sidebar-Verhalten)</label>
                        <select id="st-layout" class="pm-select">
                            <option value="sidebar-mini">sidebar-mini (Einklappbare Icon-Sidebar)</option>
                            <option value="fixed sidebar-mini">fixed (Fixierte Kopfzeile &amp; Sidebar)</option>
                            <option value="sidebar-mini sidebar-collapse">sidebar-collapse (Beim Start eingeklappt)</option>
                            <option value="layout-boxed sidebar-mini">layout-boxed (Zentriertes Box-Layout)</option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label for="st-default-table">Startseite / Standard-Tabelle</label>
                        <select id="st-default-table" class="pm-select"></select>
                    </div>
                    <div class="pm-form-group">
                        <label for="st-recperpage">Standard-Datensätze pro Seite</label>
                        <input type="number" id="st-recperpage" class="pm-input" value="20">
                    </div>
                    <div class="pm-form-group">
                        <label for="st-pagesizes">Wählbare Seitengrößen (Komma-getrennt)</label>
                        <input type="text" id="st-pagesizes" class="pm-input" value="10,20,50,100,ALL">
                    </div>
                    <div class="pm-form-group">
                        <label for="st-dateformat">PHP Datumsformat (z.B. d.m.Y oder Y-m-d)</label>
                        <input type="text" id="st-dateformat" class="pm-input" value="d.m.Y">
                    </div>
                    <div class="pm-form-group">
                        <label for="st-currency">Währungssymbol</label>
                        <input type="text" id="st-currency" class="pm-input" value="€">
                    </div>
                    <div class="pm-form-group">
                        <label for="st-timezone">PHP Zeitzone</label>
                        <input type="text" id="st-timezone" class="pm-input" value="Europe/Berlin">
                    </div>
                    <div class="pm-form-group">
                        <label for="st-output">Ausgabe-Verzeichnis (Relativ zum Studio)</label>
                        <input type="text" id="st-output" class="pm-input" value="output/crm_shop_demo">
                    </div>
                    <div class="pm-form-group" style="grid-column: span 2;">
                        <label for="st-footer">Fußzeilen-Text (Footer HTML)</label>
                        <input type="text" id="st-footer" class="pm-input">
                    </div>
                </div>

                <div style="margin-top:16px; display:flex; gap:24px;">
                    <label style="cursor:pointer;">
                        <input type="checkbox" id="st-confirm" checked> Bestätigungsdialog vor dem Speichern (Add/Edit) anzeigen
                    </label>
                    <label style="cursor:pointer;">
                        <input type="checkbox" id="st-debug"> SQL-Debug-Modus aktivieren (zeigt alle ausgeführten SQL-Abfragen im Footer)
                    </label>
                </div>
            </div>
        </section>

        <!-- TAB 8: GENERATE -->
        <section id="tab-generate" class="pm-tab-content">
            <div class="pm-card">
                <h3 class="pm-card-title"><span><i class="fa fa-rocket"></i> PHP-Applikation generieren (PHP 5.4 bis PHP 8.4+ kompatibel)</span></h3>

                <div style="display:flex; align-items:flex-end; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
                    <div class="pm-form-group" style="flex:1; min-width:260px;">
                        <label for="gen-output-folder">Zielverzeichnis für generierte Dateien</label>
                        <input type="text" id="gen-output-folder" class="pm-input" value="output/crm_shop_demo">
                    </div>
                    <button type="button" class="pm-btn pm-btn-success" style="height:32px; padding:0 18px; font-size:13px;" onclick="StudioApp.runGeneration();">
                        <i class="fa fa-cogs"></i> Jetzt alle Skripte generieren
                    </button>
                    <button type="button" id="gen-download-zip-btn" class="pm-btn" style="height:32px; padding:0 18px; font-size:13px; background:#00a65a; color:#fff; border-color:#008d4c;" onclick="StudioApp.downloadGeneratedZip();">
                        <i class="fa fa-file-archive-o"></i> Generierte Dateien als ZIP herunterladen
                    </button>
                    <a id="gen-open-app-btn" href="#" target="_blank" class="pm-btn pm-btn-primary" style="height:32px; padding:0 18px; font-size:13px; display:none;">
                        <i class="fa fa-external-link"></i> Generierte Web-Anwendung im Browser öffnen
                    </a>
                </div>

                <div id="gen-console" class="pm-console">
                    <div class="log-info">Bereit zur Code-Generierung. Klicken Sie auf "Jetzt alle Skripte generieren".</div>
                </div>
            </div>
        </section>

        <!-- TAB 9: HILFE & HANDBUCH -->
        <section id="tab-help" class="pm-tab-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="margin:0; font-size:15px; color:#1e282c;">
                    <i class="fa fa-book" style="color:#3c8dbc;"></i> PHPMacherNG &ndash; Integriertes Benutzerhandbuch &amp; Entwickler-Referenz
                </h3>
                <a href="hilfe.php" target="_blank" class="pm-btn pm-btn-primary" style="text-decoration:none;">
                    <i class="fa fa-external-link"></i> Hilfe in eigenem Browser-Tab öffnen / Druckansicht
                </a>
            </div>
            <?php
            define('PM_STUDIO_EMBEDDED_HELP', true);
            include dirname(__FILE__) . '/hilfe.php';
            ?>
        </section>

    </main>
</div>

<!-- Modal-Dialog: Ein oder mehrere Felder zur Tabelle hinzufügen (inkl. Touchscreen-Unterschrift & alle Feldarten) -->
<div id="pm-add-fields-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; width:100%; max-width:940px; border-radius:4px; box-shadow:0 10px 35px rgba(0,0,0,0.35); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;">
        <div style="background:#2b579a; color:#fff; padding:12px 18px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:15px; font-weight:600;">
                <i class="fa fa-plus-circle"></i> Neue Felder hinzufügen (Table / Field Setup Assistent)
            </h3>
            <button type="button" onclick="StudioApp.closeAddFieldsModal();" style="background:transparent; border:none; color:#fff; font-size:18px; cursor:pointer;">&times;</button>
        </div>

        <div style="padding:16px 18px; overflow-y:auto; flex:1;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:14px; background:#f4f8fb; border:1px solid #cde0ef; padding:10px 14px; border-radius:3px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <label for="add-fields-table-select" style="font-weight:bold; font-size:12.5px; margin:0;">Zieltabelle:</label>
                    <select id="add-fields-table-select" class="pm-select" style="min-width:220px; font-weight:bold;"></select>
                </div>
                <label style="cursor:pointer; font-weight:600; font-size:12.5px; color:#1b4f72; margin:0;">
                    <input type="checkbox" id="add-fields-alter-db" checked>
                    Spalte(n) auch direkt per <code>ALTER TABLE ... ADD COLUMN</code> in der MySQL/MariaDB-Datenbank anlegen
                </label>
            </div>

            <div style="margin-bottom:10px; font-size:12px; color:#555;">
                <i class="fa fa-hand-pointer-o" style="color:#3c8dbc;"></i> <strong>Tipp:</strong> Wählen Sie als Feldart <strong>Touchscreen-Unterschrift (Signature Pad)</strong>, um ein interaktives HTML5-Unterschriftenfeld für Finger-, Tablet-, Stylus- und Mauseingaben zu erzeugen. Sie können beliebig viele Zeilen auf einmal anlegen!
            </div>

            <table class="pm-grid-table" style="margin-bottom:12px;">
                <thead>
                    <tr>
                        <th style="width:22%;">Feldname (Spalte, z.B. <code>unterschrift</code>)</th>
                        <th style="width:22%;">Anzeigename (Caption)</th>
                        <th style="width:30%;">Feldart / Eingabetyp (Preset)</th>
                        <th style="width:10%;">Länge</th>
                        <th style="width:10%; text-align:center;">Pflichtfeld</th>
                        <th style="width:6%; text-align:center;">Aktion</th>
                    </tr>
                </thead>
                <tbody id="add-fields-rows-tbody"></tbody>
            </table>

            <div style="display:flex; gap:8px;">
                <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.appendAddFieldRow('', '', 'VARCHAR', 255, false);">
                    <i class="fa fa-plus"></i> Weiteres Feld hinzufügen
                </button>
                <button type="button" class="pm-btn pm-btn-warning" onclick="StudioApp.appendAddFieldRow('unterschrift', 'Unterschrift (Touch)', 'SIGNATURE', 0, false);">
                    <i class="fa fa-pencil-square-o"></i> + Touchscreen-Unterschriftsfeld einfügen
                </button>
            </div>
        </div>

        <div style="padding:12px 18px; background:#f5f7fa; border-top:1px solid #d2d6de; display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.closeAddFieldsModal();">Abbrechen</button>
            <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.submitAddFieldsModal();">
                <i class="fa fa-check"></i> Ausgewählte Felder jetzt anlegen
            </button>
        </div>
    </div>
</div>

<!-- Modal-Dialog: PHPMaker-Style Datenbank-View erstellen / bearbeiten (Visueller SQL-Generator + Direkter SQL-Editor) -->
<div id="pm-view-builder-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:10000; align-items:center; justify-content:center; padding:14px;">
    <div style="background:#fff; width:100%; max-width:1260px; border-radius:4px; box-shadow:0 12px 40px rgba(0,0,0,0.45); overflow:hidden; display:flex; flex-direction:column; height:92vh; max-height:92vh;">
        <div style="background:#1e3c72; color:#fff; padding:10px 18px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:8px;">
                <i class="fa fa-eye"></i> <span id="vb-modal-title">Datenbank-View erstellen / bearbeiten (Visueller SQL-Generator &amp; SQL-Editor)</span>
            </h3>
            <button type="button" onclick="StudioApp.closeViewBuilderModal();" style="background:transparent; border:none; color:#fff; font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <!-- Kopfbereich: View-Name, Anzeigename, Primärschlüssel & Vorhandene Views laden -->
        <div style="padding:10px 16px; background:#f4f8fb; border-bottom:1px solid #cde0ef; display:grid; grid-template-columns:1.1fr 1.1fr 1.1fr 1.1fr; gap:12px; align-items:end;">
            <div>
                <label for="vb-existing-select" style="display:block; font-size:11.5px; font-weight:bold; color:#1b4f72; margin-bottom:3px;">
                    <i class="fa fa-folder-open-o"></i> Aktion / Vorhandene View laden:
                </label>
                <select id="vb-existing-select" class="pm-select" style="width:100%; font-weight:bold;" onchange="StudioApp.onSelectExistingView(this.value);">
                    <option value="">-- Neue Datenbank-View anlegen --</option>
                </select>
            </div>
            <div>
                <label for="vb-view-name" style="display:block; font-size:11.5px; font-weight:bold; color:#333; margin-bottom:3px;">
                    Technischer View-Name in DB (z.B. <code>v_kunden_bestellungen</code>):
                </label>
                <input type="text" id="vb-view-name" class="pm-input" placeholder="v_meine_auswertung" style="font-family:Consolas, monospace; font-weight:bold;">
            </div>
            <div>
                <label for="vb-view-caption" style="display:block; font-size:11.5px; font-weight:bold; color:#333; margin-bottom:3px;">
                    Anzeigename im Menü (Caption):
                </label>
                <input type="text" id="vb-view-caption" class="pm-input" placeholder="z.B. Kunden-Bestellübersicht (View)">
            </div>
            <div>
                <label for="vb-view-pk" style="display:block; font-size:11.5px; font-weight:bold; color:#333; margin-bottom:3px;" title="Wichtig für Detailansicht (*view.php) und Master/Detail-Verknüpfungen">
                    <i class="fa fa-key" style="color:#f39c12;"></i> Eindeutige Spalte (Primary Key der View):
                </label>
                <input type="text" id="vb-view-pk" class="pm-input" placeholder="z.B. bestellung_id (optional, Komma-getrennt)">
            </div>
        </div>

        <!-- Hauptbereich: Links Visueller Query Builder, Rechts Direkter SQL-Editor + Live-Datenvorschau -->
        <div style="flex:1; display:grid; grid-template-columns:1.25fr 1fr; gap:0; overflow:hidden; min-height:0;">

            <!-- LINKE SPALTE: VISUELLER SQL-GENERATOR -->
            <div style="border-right:1px solid #d2d6de; padding:12px 15px; overflow-y:auto; display:flex; flex-direction:column; gap:12px; background:#fafbfc;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e1e6eb; padding-bottom:6px;">
                    <strong style="font-size:13px; color:#1e3c72;">
                        <i class="fa fa-sitemap"></i> 1. Visueller SQL-Generator (Tabellen, JOINs, Spalten &amp; Bedingungen)
                    </strong>
                    <label style="font-size:11.5px; cursor:pointer; color:#333; margin:0; font-weight:600;">
                        <input type="checkbox" id="vb-auto-sync-sql" checked> SQL bei Klicks automatisch aktualisieren
                    </label>
                </div>

                <!-- 1A: Tabellen-Auswahl -->
                <div>
                    <div style="font-size:11.5px; font-weight:bold; color:#444; margin-bottom:5px;">
                        <i class="fa fa-table"></i> Beteiligte Tabellen / Views auswählen (erste Tabelle = <code>FROM</code>, weitere = <code>JOIN</code>):
                    </div>
                    <div id="vb-tables-container" style="display:flex; flex-wrap:wrap; gap:6px; max-height:92px; overflow-y:auto; background:#fff; border:1px solid #ccd5e0; padding:7px; border-radius:3px;"></div>
                </div>

                <!-- 1B: JOIN-Verknüpfungen -->
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <span style="font-size:11.5px; font-weight:bold; color:#444;">
                            <i class="fa fa-link"></i> Tabellen-Verknüpfungen (<code>JOIN ... ON</code> &ndash; Fremdschlüssel werden automatisch erkannt):
                        </span>
                        <button type="button" class="pm-btn pm-btn-default" style="padding:2px 8px; font-size:11px;" onclick="StudioApp.addVbJoinRow();">
                            <i class="fa fa-plus"></i> + JOIN-Zeile
                        </button>
                    </div>
                    <div style="border:1px solid #ccd5e0; background:#fff; border-radius:3px; max-height:125px; overflow-y:auto;">
                        <table class="pm-grid-table" style="margin:0; font-size:11.5px;">
                            <thead>
                                <tr>
                                    <th style="width:24%;">JOIN-Typ</th>
                                    <th style="width:24%;">Ziel-Tabelle</th>
                                    <th style="width:23%;">Linkes Feld (<code>Tab.Spalte</code>)</th>
                                    <th style="width:23%;">Rechtes Feld (<code>Tab.Spalte</code>)</th>
                                    <th style="width:6%; text-align:center;"></th>
                                </tr>
                            </thead>
                            <tbody id="vb-joins-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 1C: Spalten-Auswahl, Alias, Aggregatfunktionen, Sortierung & Gruppierung -->
                <div style="flex:1; display:flex; flex-direction:column; min-height:170px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px; flex-wrap:wrap; gap:6px;">
                        <span style="font-size:11.5px; font-weight:bold; color:#444;">
                            <i class="fa fa-columns"></i> Spalten-Auswahl (<code>SELECT</code>), Alias (<code>AS</code>), Funktionen &amp; Sortierung:
                        </span>
                        <div style="display:flex; gap:5px;">
                            <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.toggleAllVbColumns(true);">Alle anwählen</button>
                            <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.toggleAllVbColumns(false);">Alle abwählen</button>
                            <button type="button" class="pm-btn pm-btn-primary" style="padding:2px 8px; font-size:11px;" onclick="StudioApp.addVbCustomColumnRow();">
                                <i class="fa fa-plus"></i> Berechnetes Feld / Ausdruck
                            </button>
                        </div>
                    </div>
                    <div style="flex:1; border:1px solid #ccd5e0; background:#fff; border-radius:3px; overflow-y:auto; max-height:220px;">
                        <table class="pm-grid-table" style="margin:0; font-size:11.5px;">
                            <thead>
                                <tr>
                                    <th style="width:30px; text-align:center;">Anz.</th>
                                    <th>Tabelle.Spalte / SQL-Ausdruck</th>
                                    <th style="width:105px;">Funktion</th>
                                    <th style="width:125px;">Alias (<code>AS ...</code>)</th>
                                    <th style="width:60px; text-align:center;" title="In GROUP BY aufnehmen">Group</th>
                                    <th style="width:85px;">Sortierung</th>
                                    <th style="width:34px; text-align:center;" title="Als Primary Key der View verwenden">PK</th>
                                </tr>
                            </thead>
                            <tbody id="vb-columns-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 1D: WHERE / HAVING / DISTINCT -->
                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:8px; align-items:end;">
                    <div>
                        <label for="vb-where-input" style="display:block; font-size:11px; font-weight:bold; color:#444; margin-bottom:2px;">
                            <code>WHERE</code>-Bedingung (ohne das Wort WHERE):
                        </label>
                        <input type="text" id="vb-where-input" class="pm-input" placeholder="z.B. bestellungen.status = 'Bezahlt' AND gesamtbetrag > 50" oninput="StudioApp.onVbVisualChange();">
                    </div>
                    <div>
                        <label for="vb-having-input" style="display:block; font-size:11px; font-weight:bold; color:#444; margin-bottom:2px;">
                            <code>HAVING</code>-Bedingung (optional bei GROUP BY):
                        </label>
                        <input type="text" id="vb-having-input" class="pm-input" placeholder="z.B. COUNT(*) > 1" oninput="StudioApp.onVbVisualChange();">
                    </div>
                    <div style="padding-bottom:4px;">
                        <label style="font-size:11.5px; font-weight:bold; cursor:pointer; white-space:nowrap;">
                            <input type="checkbox" id="vb-distinct-chk" onchange="StudioApp.onVbVisualChange();"> <code>DISTINCT</code>
                        </label>
                    </div>
                </div>

                <div>
                    <button type="button" class="pm-btn pm-btn-primary" style="width:100%;" onclick="StudioApp.generateSqlFromVisualBuilder(true);">
                        <i class="fa fa-magic"></i> SQL-Befehl jetzt aus visueller Auswahl in den SQL-Editor übertragen &rarr;
                    </button>
                </div>
            </div>

            <!-- RECHTE SPALTE: DIREKTER SQL-EDITOR & LIVE-DATENVORSCHAU -->
            <div style="padding:12px 15px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; background:#fff;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e1e6eb; padding-bottom:6px;">
                    <strong style="font-size:13px; color:#1e3c72;">
                        <i class="fa fa-code"></i> 2. Direkter SQL-Befehl (<code>SELECT ...</code>) &amp; Live-Vorschau
                    </strong>
                    <button type="button" class="pm-btn pm-btn-warning" style="padding:3px 10px; font-size:11.5px;" onclick="StudioApp.previewViewSql();">
                        <i class="fa fa-play"></i> SQL testen &amp; Vorschau laden
                    </button>
                </div>

                <div style="font-size:11.5px; color:#555; line-height:1.4;">
                    Sie können das generierte <code>SELECT</code>-Statement hier beliebig von Hand anpassen oder Ihren eigenen SQL-Befehl (inkl. Subqueries, <code>CASE WHEN</code>, <code>CONCAT</code>, <code>UNION</code>) direkt eingeben. PHPMacherNG erzeugt daraus <code>CREATE OR REPLACE VIEW &lt;name&gt; AS ...</code>.
                </div>

                <textarea id="vb-sql-editor" class="pm-code-editor" style="min-height:195px; height:210px; font-family:Consolas, 'Courier New', monospace; font-size:12.5px; line-height:1.45;" placeholder="SELECT&#10;  b.bestellung_id,&#10;  b.bestelldatum,&#10;  k.firma AS kunde_firma,&#10;  b.gesamtbetrag,&#10;  b.status&#10;FROM `bestellungen` b&#10;LEFT JOIN `kunden` k ON b.kunde_id = k.kunde_id"></textarea>

                <!-- Ergebnis-Vorschau -->
                <div style="flex:1; display:flex; flex-direction:column; min-height:160px; border:1px solid #d2d6de; border-radius:3px; overflow:hidden;">
                    <div style="background:#f4f6f9; padding:6px 10px; border-bottom:1px solid #d2d6de; font-size:11.5px; font-weight:bold; display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fa fa-table"></i> Live-Ergebnisvorschau (max. 15 Datensätze aus MySQL/MariaDB)</span>
                        <span id="vb-preview-status" style="font-weight:normal; color:#666;">Noch nicht ausgeführt</span>
                    </div>
                    <div id="vb-preview-container" style="flex:1; overflow:auto; padding:8px; font-size:11.5px; background:#fff;">
                        <div style="color:#777; padding:18px; text-align:center;">
                            Klicken Sie auf <strong>&bdquo;SQL testen &amp; Vorschau laden&ldquo;</strong>, um Ihre SQL-Abfrage live gegen die Datenbank zu prüfen.
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Fußzeile -->
        <div style="padding:10px 18px; background:#f5f7fa; border-top:1px solid #d2d6de; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <button type="button" id="vb-delete-view-btn" class="pm-btn pm-btn-danger" style="display:none;" onclick="StudioApp.deleteDatabaseViewFromModal();">
                    <i class="fa fa-trash"></i> Diese View in der Datenbank löschen (DROP VIEW)
                </button>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.closeViewBuilderModal();">Abbrechen</button>
                <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.submitViewBuilderModal();">
                    <i class="fa fa-check"></i> View in Datenbank speichern &amp; ins Projekt übernehmen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL 1: PROJEKT TEILEN & TEAM-ZUSAMMENARBEIT (MULTI-USER SHARING)     -->
<!-- ====================================================================== -->
<div id="pm-share-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:10005; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; width:100%; max-width:740px; border-radius:4px; box-shadow:0 12px 40px rgba(0,0,0,0.45); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;">
        <div style="background:#2980b9; color:#fff; padding:12px 18px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:8px;">
                <i class="fa fa-share-alt"></i> <span>Projekt teilen &amp; Team-Berechtigungen verwalten</span>
            </h3>
            <button type="button" onclick="StudioApp.closeShareModal();" style="background:transparent; border:none; color:#fff; font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <div style="padding:16px 18px; overflow-y:auto; flex:1;">
            <div style="padding:10px 14px; background:#f0f7fd; border:1px solid #bcdff1; border-radius:3px; margin-bottom:14px; font-size:12px; color:#1b4f72; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <i class="fa fa-folder-open"></i> Projekt: <strong id="share-modal-project-name">-</strong>
                    (<code id="share-modal-filename">-</code>)
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <i class="fa fa-crown" style="color:#f39c12;"></i> Eigentümer (Owner):
                    <strong id="share-modal-owner">-</strong>
                    <select id="share-owner-select" class="pm-select" style="display:none; height:26px; font-weight:bold; min-width:170px;" onchange="StudioApp.onShareOwnerChange(this.value);" title="Projekt-Eigentümer ändern / Projekt einem anderen Benutzer neu zuordnen"></select>
                </div>
            </div>

            <p style="font-size:12px; color:#555; margin-top:0; margin-bottom:12px; line-height:1.45;">
                Standardmäßig ist jedes Projekt <strong>privat</strong> und nur für seinen Eigentümer sichtbar. Hier können Sie andere Studio-Benutzer einladen, damit mehrere Entwickler gemeinsam am selben Projekt arbeiten können:
            </p>

            <!-- Neuen Team-Mitarbeiter hinzufügen -->
            <div id="share-add-controls" style="padding:12px; background:#f8fafc; border:1px solid #dce4ec; border-radius:3px; margin-bottom:14px;">
                <div style="font-size:12px; font-weight:700; color:#2c3e50; margin-bottom:8px;">
                    <i class="fa fa-user-plus"></i> Benutzer zum Projekt hinzufügen:
                </div>
                <div style="display:grid; grid-template-columns:1.3fr 1.2fr auto; gap:10px; align-items:end;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; color:#444; margin-bottom:3px;">Studio-Benutzer auswählen:</label>
                        <select id="share-add-user-select" class="pm-select" style="width:100%; height:30px;"></select>
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; color:#444; margin-bottom:3px;">Berechtigungs-Rolle im Projekt:</label>
                        <select id="share-add-role-select" class="pm-select" style="width:100%; height:30px;">
                            <option value="write">Bearbeiter (Lesen, Bearbeiten, Speichern, Git &amp; Generieren)</option>
                            <option value="read">Betrachter (Nur Lesen / Ansehen)</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="pm-btn pm-btn-primary" style="height:30px;" onclick="StudioApp.addShareCollaborator();">
                            <i class="fa fa-plus"></i> Freigeben
                        </button>
                    </div>
                </div>
            </div>

            <!-- Liste der aktuell freigegebenen Team-Mitglieder -->
            <div style="font-size:12px; font-weight:700; color:#2c3e50; margin-bottom:6px;">
                <i class="fa fa-users"></i> Aktuell freigegebene Team-Mitglieder:
            </div>
            <div style="border:1px solid #d2d6de; border-radius:3px; overflow:hidden;">
                <table class="pm-grid-table" style="margin:0; font-size:12px;">
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th style="width:240px;">Zugriffsrecht / Rolle</th>
                            <th style="width:140px;">Freigegeben am</th>
                            <th style="width:80px; text-align:center;">Aktion</th>
                        </tr>
                    </thead>
                    <tbody id="share-collaborators-tbody"></tbody>
                </table>
            </div>
        </div>

        <div style="padding:12px 18px; background:#f5f7fa; border-top:1px solid #d2d6de; display:flex; justify-content:space-between; align-items:center;">
            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.closeShareModal(); StudioApp.openUsersModal();">
                <i class="fa fa-user-plus"></i> Neue Studio-Benutzer verwalten...
            </button>
            <div style="display:flex; gap:10px;">
                <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.closeShareModal();">Schließen</button>
                <button type="button" id="share-save-btn" class="pm-btn pm-btn-success" onclick="StudioApp.saveShareModal();">
                    <i class="fa fa-check"></i> Freigaben speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL 2: GIT-VERSIONIERUNG (COMMITS, BRANCHES, TAGS, DIFF & ROLLBACK)  -->
<!-- ====================================================================== -->
<div id="pm-git-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.65); z-index:10006; align-items:center; justify-content:center; padding:14px;">
    <div style="background:#fff; width:100%; max-width:1280px; border-radius:4px; box-shadow:0 12px 45px rgba(0,0,0,0.5); overflow:hidden; display:flex; flex-direction:column; height:92vh; max-height:92vh;">
        <div style="background:#4a235a; color:#fff; padding:10px 18px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:10px;">
                <i class="fa fa-code-fork"></i>
                <span>Git Projekt-Versionierung &ndash; <span id="git-modal-project-title">Projekt</span></span>
                <span id="git-modal-branch-pill" style="background:#8e44ad; padding:2px 9px; border-radius:10px; font-size:12px; font-family:Consolas,monospace;">main</span>
                <span id="git-modal-head-pill" style="background:rgba(255,255,255,0.18); padding:2px 8px; border-radius:4px; font-size:11.5px; font-family:Consolas,monospace;">HEAD</span>
            </h3>
            <button type="button" onclick="StudioApp.closeGitModal();" style="background:transparent; border:none; color:#fff; font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <!-- Obere Aktionsleiste: Branch wechseln / anlegen / mergen, Tag setzen & Neuer Commit -->
        <div style="padding:10px 16px; background:#f6f2f8; border-bottom:1px solid #dcd0e5; display:grid; grid-template-columns:1.15fr 1.35fr; gap:14px; align-items:center;">
            <!-- Links: Branch- & Tag-Steuerung -->
            <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                <div style="display:flex; align-items:center; gap:5px;">
                    <label style="font-size:11.5px; font-weight:bold; color:#4a235a; white-space:nowrap;"><i class="fa fa-code-fork"></i> Branch:</label>
                    <select id="git-branch-select" class="pm-select" style="width:155px; font-family:Consolas,monospace; font-weight:bold;" onchange="StudioApp.gitSwitchBranch(this.value);"></select>
                </div>
                <button type="button" class="pm-btn pm-btn-default" style="padding:3px 9px; font-size:11.5px;" onclick="StudioApp.gitPromptCreateBranch();" title="Neuen Entwicklungs-Branch (z. B. feature/...) vom aktuellen Stand abzweigen">
                    <i class="fa fa-plus"></i> + Branch
                </button>
                <button type="button" class="pm-btn pm-btn-default" style="padding:3px 9px; font-size:11.5px;" onclick="StudioApp.gitPromptMergeBranch();" title="Einen anderen Branch in den aktuellen Branch zusammenführen (git merge)">
                    <i class="fa fa-random"></i> Merge...
                </button>
                <button type="button" class="pm-btn pm-btn-default" style="padding:3px 9px; font-size:11.5px;" onclick="StudioApp.gitPromptCreateTag('');" title="Aktuellen HEAD-Commit mit einem Release-Tag (z. B. v1.0) versehen">
                    <i class="fa fa-tag"></i> + Tag
                </button>
                <button type="button" class="pm-btn pm-btn-default" style="padding:3px 9px; font-size:11.5px;" onclick="StudioApp.gitCompareWorkingTree();" title="Aktuellen Arbeitsstand gegen HEAD vergleichen (git status / git diff)">
                    <i class="fa fa-exchange"></i> Arbeitsstand-Diff
                </button>
            </div>

            <!-- Rechts: Direkt neuen Git-Commit erstellen -->
            <div style="display:flex; gap:8px; align-items:center;">
                <input type="text" id="git-commit-message" class="pm-input" placeholder="Commit-Nachricht eingeben (z. B. 'Kunden-Tabelle erweitert &amp; Umsatz-Diagramm ergänzt')..." style="flex:1; height:30px;">
                <button type="button" class="pm-btn pm-btn-success" style="height:30px; white-space:nowrap;" onclick="StudioApp.gitCreateCommit();">
                    <i class="fa fa-check-circle"></i> Jetzt committen (Snapshot)
                </button>
            </div>
        </div>

        <!-- Status-Banner für uncommittete Änderungen im Arbeitsstand -->
        <div id="git-working-banner" style="display:none; padding:8px 16px; background:#fff8e5; border-bottom:1px solid #ffeeba; color:#856404; font-size:12px; justify-content:space-between; align-items:center;">
            <div>
                <i class="fa fa-exclamation-circle"></i>
                <strong>Ungespeicherte / uncommittete Änderungen im Arbeitsstand erkannt:</strong>
                <span id="git-working-summary-text"></span>
            </div>
            <button type="button" class="pm-btn pm-btn-warning" style="padding:2px 9px; font-size:11px;" onclick="StudioApp.gitCompareWorkingTree();">
                <i class="fa fa-search"></i> Änderungen im Diff-Viewer anzeigen
            </button>
        </div>

        <!-- Hauptbereich: Links Commit-Historie (Git Log), Rechts Visueller Diff-Inspector -->
        <div style="flex:1; display:grid; grid-template-columns:1.15fr 1.25fr; gap:0; overflow:hidden; min-height:0;">

            <!-- LINKE SPALTE: COMMIT-HISTORIE (GIT LOG) -->
            <div style="border-right:1px solid #d2d6de; display:flex; flex-direction:column; overflow:hidden; background:#fafbfc;">
                <div style="padding:8px 14px; background:#eef1f6; border-bottom:1px solid #d2d6de; font-size:12px; font-weight:700; color:#2c3e50; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa fa-history"></i> Commit-Historie (<span id="git-commit-count-label">0</span> Commits)</span>
                    <span style="font-size:11px; font-weight:normal; color:#666;">Klick auf Commit zeigt Diff &amp; Rollback</span>
                </div>
                <div id="git-commits-list" style="flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px;"></div>
            </div>

            <!-- RECHTE SPALTE: DIFF-VIEWER (STRUKTURELLER DIFF + UNIFIED JSON DIFF) -->
            <div style="display:flex; flex-direction:column; overflow:hidden; background:#fff;">
                <div style="padding:8px 14px; background:#eef1f6; border-bottom:1px solid #d2d6de; font-size:12px; font-weight:700; color:#2c3e50; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
                    <span id="git-diff-header-title"><i class="fa fa-exchange"></i> Änderungs-Details &amp; Diff-Inspektor</span>
                    <div id="git-diff-actions" style="display:flex; gap:6px;"></div>
                </div>

                <div style="flex:1; overflow-y:auto; padding:12px 14px; display:flex; flex-direction:column; gap:12px;">
                    <!-- Strukturelle Änderungen (Tabellen, Felder, Events, Settings) -->
                    <div>
                        <div style="font-size:12px; font-weight:700; color:#4a235a; margin-bottom:6px;">
                            <i class="fa fa-list-ul"></i> 1. Strukturelle PHPMacherNG-Änderungen (Tabellen, Felder, Events, Einstellungen):
                        </div>
                        <div id="git-structural-diff-box" style="border:1px solid #dce4ec; border-radius:3px; background:#fafbfc; padding:10px; font-size:12px; max-height:220px; overflow-y:auto;">
                            <span style="color:#777;">Wählen Sie links einen Commit aus oder klicken Sie auf „Arbeitsstand-Diff“.</span>
                        </div>
                    </div>

                    <!-- Unified Code / JSON Diff (wie git diff) -->
                    <div style="flex:1; display:flex; flex-direction:column; min-height:180px;">
                        <div style="font-size:12px; font-weight:700; color:#4a235a; margin-bottom:6px;">
                            <i class="fa fa-file-code-o"></i> 2. Zeilenweiser Projekt-Diff (<code>git diff</code> Ansicht):
                        </div>
                        <div id="git-unified-diff-box" style="flex:1; border:1px solid #2c3e50; border-radius:3px; background:#1e272e; color:#d2dae2; font-family:Consolas, 'Courier New', monospace; font-size:11.5px; line-height:1.45; overflow:auto; padding:8px;">
                            <div style="color:#95a5a6; padding:10px;">Noch kein Commit zum Vergleich ausgewählt.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div style="padding:10px 18px; background:#f5f7fa; border-top:1px solid #d2d6de; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:11.5px; color:#555;">
                <i class="fa fa-info-circle"></i> Alle Commits, Branches und Snapshots werden revisionssicher im Ordner <code>projects/.vcs/&lt;projekt&gt;/</code> gespeichert.
            </div>
            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.closeGitModal();">Schließen</button>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL 3: STUDIO-BENUTZERVERWALTUNG (MULTI-USER ACCOUNTS & ROLLEN)      -->
<!-- ====================================================================== -->
<div id="pm-users-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:10007; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; width:100%; max-width:960px; border-radius:4px; box-shadow:0 12px 40px rgba(0,0,0,0.45); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;">
        <div style="background:#2c3e50; color:#fff; padding:12px 18px; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:8px;">
                <i class="fa fa-users"></i> <span>PHPMacherNG Studio &ndash; Benutzerverwaltung &amp; Team-Accounts</span>
            </h3>
            <button type="button" onclick="StudioApp.closeUsersModal();" style="background:transparent; border:none; color:#fff; font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <div style="padding:16px 18px; overflow-y:auto; flex:1;">
            <!-- Neues Benutzerkonto anlegen (nur für Studio-Admins sichtbar) -->
            <div id="studio-users-admin-create-box" style="padding:12px; background:#f8fafc; border:1px solid #dce4ec; border-radius:3px; margin-bottom:14px;">
                <div style="font-size:12.5px; font-weight:700; color:#1e3c72; margin-bottom:8px;">
                    <i class="fa fa-user-plus"></i> Neuen Studio-Benutzer anlegen:
                </div>
                <div style="display:grid; grid-template-columns:1fr 1.2fr 1.1fr 1fr 0.9fr auto; gap:8px; align-items:end;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Benutzername:</label>
                        <input type="text" id="su-new-username" class="pm-input" placeholder="z. B. entwickler3">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Anzeigename (Name):</label>
                        <input type="text" id="su-new-displayname" class="pm-input" placeholder="z. B. Thomas Meyer">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">E-Mail (optional):</label>
                        <input type="text" id="su-new-email" class="pm-input" placeholder="name@firma.de">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Start-Passwort:</label>
                        <input type="password" id="su-new-password" class="pm-input" placeholder="mind. 6 Zeichen">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Studio-Rolle:</label>
                        <select id="su-new-role" class="pm-select" style="width:100%;">
                            <option value="developer">Entwickler</option>
                            <option value="admin">Studio-Admin</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="pm-btn pm-btn-success" onclick="StudioApp.createStudioUser();">
                            <i class="fa fa-plus"></i> Anlegen
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bestehenden Studio-Benutzer bearbeiten (Inline-Editor, klappt bei Klick auf "Bearbeiten" auf) -->
            <div id="studio-users-edit-box" style="display:none; padding:12px 14px; background:#fffdf5; border:1px solid #f39c12; border-left:4px solid #f39c12; border-radius:3px; margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div style="font-size:12.5px; font-weight:700; color:#b9770e;">
                        <i class="fa fa-pencil-square-o"></i> Studio-Benutzer bearbeiten: <code id="su-edit-username-label" style="font-size:12.5px;">-</code>
                    </div>
                    <button type="button" class="pm-btn pm-btn-default" style="padding:1px 8px; font-size:11px;" onclick="StudioApp.cancelEditStudioUser();">Abbrechen</button>
                </div>
                <input type="hidden" id="su-edit-username" value="">
                <div style="display:grid; grid-template-columns:1.2fr 1.1fr 0.9fr 0.9fr 1fr auto; gap:8px; align-items:end;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Anzeigename (Name):</label>
                        <input type="text" id="su-edit-displayname" class="pm-input" placeholder="Anzeigename">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">E-Mail-Adresse:</label>
                        <input type="text" id="su-edit-email" class="pm-input" placeholder="E-Mail">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Studio-Rolle:</label>
                        <select id="su-edit-role" class="pm-select" style="width:100%;">
                            <option value="developer">Entwickler</option>
                            <option value="admin">Studio-Admin</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Konto-Status:</label>
                        <select id="su-edit-active" class="pm-select" style="width:100%;">
                            <option value="1">Aktiv (Login erlaubt)</option>
                            <option value="0">Deaktiviert (Gesperrt)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:bold; margin-bottom:2px;">Neues Passwort (optional):</label>
                        <input type="password" id="su-edit-password" class="pm-input" placeholder="Leer = unverändert">
                    </div>
                    <div>
                        <button type="button" class="pm-btn pm-btn-primary" onclick="StudioApp.saveEditStudioUser();">
                            <i class="fa fa-check"></i> Speichern
                        </button>
                    </div>
                </div>
            </div>

            <div style="font-size:12.5px; font-weight:700; color:#2c3e50; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fa fa-list"></i> Registrierte Studio-Benutzer:</span>
                <span style="font-size:11px; font-weight:normal; color:#666;"><i class="fa fa-info-circle"></i> Beim Löschen eines Benutzers bleiben alle seine Projekte erhalten und gehen an den Admin über.</span>
            </div>
            <div style="border:1px solid #d2d6de; border-radius:3px; overflow:hidden;">
                <table class="pm-grid-table" style="margin:0; font-size:12px;">
                    <thead>
                        <tr>
                            <th>Benutzername</th>
                            <th>Anzeigename &amp; E-Mail</th>
                            <th style="width:120px;">Rolle</th>
                            <th style="width:110px;">Status</th>
                            <th style="width:110px; text-align:center;">Projekte</th>
                            <th style="width:255px; text-align:center;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="studio-users-tbody"></tbody>
                </table>
            </div>
        </div>

        <div style="padding:12px 18px; background:#f5f7fa; border-top:1px solid #d2d6de; display:flex; justify-content:flex-end;">
            <button type="button" class="pm-btn pm-btn-default" onclick="StudioApp.closeUsersModal();">Schließen</button>
        </div>
    </div>
</div>

<!-- Untere Statusleiste -->
<footer class="pm-statusbar">
    <div id="status-text">Bereit</div>
    <div id="php-version-info">Ziel-Kompatibilität: PHP 5.4 - 8.4+ | UI: AdminLTE 2 / Bootstrap 3</div>
</footer>

<script src="assets/js/studio.js?v=<?php echo @filemtime(dirname(__FILE__) . '/assets/js/studio.js'); ?>"></script>
</body>
</html>
