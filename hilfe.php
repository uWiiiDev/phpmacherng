<?php
/**
 * PHPMacherNG - Vollständige Hilfedatei & Entwickler-Handbuch (hilfe.php)
 * Kann sowohl eigenständig im Browser als auch direkt im Studio-Reiter "Hilfe (F1)" genutzt werden.
 * Kompatibel mit PHP 5.4 bis PHP 8.4+.
 */
$isEmbedded = defined('PM_STUDIO_EMBEDDED_HELP');
if (!$isEmbedded):
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handbuch &amp; Hilfe - PHPMacherNG (v14 Engine)</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap3/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/font-awesome/css/font-awesome.min.css">
    <style>
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; background: #ecf0f5; color: #222; padding-bottom: 40px; }
        .help-header { background: linear-gradient(135deg, #1e282c 0%, #2c3b41 100%); color: #fff; padding: 22px 0; margin-bottom: 24px; border-bottom: 4px solid #3c8dbc; }
        .help-card { background: #fff; border: 1px solid #d2d6de; border-top: 3px solid #3c8dbc; border-radius: 3px; padding: 20px 24px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
        .help-card h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 18px; font-weight: 700; color: #1e282c; }
        .help-card h4 { margin-top: 18px; font-size: 14.5px; font-weight: 700; color: #3c8dbc; }
        pre { background: #1e1e1e; color: #d4d4d4; border: 1px solid #333; border-radius: 3px; padding: 12px; font-size: 12.5px; }
        code { background: #f3f6f9; color: #c7254e; padding: 2px 5px; border-radius: 3px; }
        pre code { background: transparent; color: inherit; padding: 0; }
        .help-toc a { display: block; padding: 7px 12px; color: #333; text-decoration: none; border-left: 3px solid transparent; font-size: 13px; }
        .help-toc a:hover { background: #f4f6f9; border-left-color: #3c8dbc; color: #3c8dbc; }
    </style>
</head>
<body>
<header class="help-header">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0; font-size:24px; font-weight:700;">
                    <i class="fa fa-book" style="color:#f39c12;"></i> PHPMacherNG &ndash; Benutzerhandbuch &amp; API-Referenz
                </h2>
                <p style="margin:5px 0 0 0; opacity:0.85; font-size:13.5px;">
                    Dokumentation für Datenbank-Synchronisation, CRUD-Generierung, Rollen &amp; Rechte (Advanced Security), Custom Files &amp; Debian-Deployment
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-warning btn-sm"><i class="fa fa-arrow-left"></i> Zurück zum PHPMacherNG</a>
                <button type="button" onclick="window.print();" class="btn btn-default btn-sm"><i class="fa fa-print"></i> Handbuch drucken</button>
            </div>
        </div>
    </div>
</header>
<div class="container">
<?php endif; ?>

<div class="pm-help-wrapper" style="display:grid; grid-template-columns: 250px 1fr; gap:16px; align-items:start;">

    <!-- Linkes Inhaltsverzeichnis (Sticky Navigation) -->
    <div class="pm-card help-toc" style="position:sticky; top:10px; padding:12px;">
        <h4 style="margin:0 0 10px 0; font-size:13.5px; padding-bottom:8px; border-bottom:1px solid #e5e5e5;">
            <i class="fa fa-list-ul"></i> Inhaltsverzeichnis
        </h4>
        <a href="#help-quickstart"><i class="fa fa-rocket"></i> 1. Schnellstart (5 Schritte)</a>
        <a href="#help-tabs"><i class="fa fa-sliders"></i> 2. Die Studio-Reiter erklärt</a>
        <a href="#help-fields"><i class="fa fa-columns"></i> 3. Feld-Typen &amp; Lookup-Tabellen</a>
        <a href="#help-views"><i class="fa fa-eye"></i> 3b. Datenbank-Views &amp; SQL-Generator</a>
        <a href="#help-charts"><i class="fa fa-bar-chart"></i> 3c. Balken- &amp; Tortendiagramme</a>
        <a href="#help-security"><i class="fa fa-shield"></i> 4. Rechte &amp; Rollen (Security)</a>
        <a href="#help-customfiles"><i class="fa fa-file-code-o"></i> 5. Custom Files (Eigene Seiten)</a>
        <a href="#help-events"><i class="fa fa-bolt"></i> 5b. Server Events &amp; Client Scripts</a>
        <a href="#help-dbhelper"><i class="fa fa-database"></i> 6. Datenzugriffs-API (DbHelper)</a>
        <a href="#help-debian"><i class="fa fa-linux"></i> 7. Portierung auf Debian / Linux</a>
        <a href="#help-sec-concept"><i class="fa fa-lock"></i> 8. IT-Sicherheitskonzept (BSI/OWASP)</a>
    </div>

    <!-- Rechter Hauptinhalt der Hilfe -->
    <div>

        <!-- KAPITEL 1: SCHNELLSTART -->
        <div id="help-quickstart" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-rocket"></i> 1. Schnellstart: In 5 Schritten zur fertigen PHP-Anwendung</span></h3>
            <ol style="line-height:1.8; padding-left:20px; margin-bottom:10px;">
                <li><strong>Datenbank verbinden (Reiter <code>1. Database</code>):</strong> Tragen Sie Host, Port, Benutzername, Passwort und den Datenbanknamen Ihrer MySQL/MariaDB-Datenbank ein und klicken Sie auf <em>„Verbinden &amp; Tabellen synchronisieren“</em> (oder nutzen Sie den 1-Klick-Installer für die Demo-Datenbank <code>phpmaker_demo</code>).</li>
                <li><strong>Tabellen &amp; Seiten auswählen (Reiter <code>2. Table Setup</code>):</strong> Legen Sie pro Tabelle fest, welche Seiten generiert werden sollen (<code>List</code>, <code>Add</code>, <code>Edit</code>, <code>View</code>, <code>Delete</code>, <code>Multi-Delete</code>, <code>Multi-Update</code>) und vergeben Sie sprechende deutsche Anzeigenamen (Captions) sowie FontAwesome-Icons.</li>
                <li><strong>Felder &amp; Lookups feinabstimmen (Reiter <code>3. Field Setup</code>):</strong> Wählen Sie links im Baum eine Tabelle aus. Bestimmen Sie pro Spalte das Anzeigeformat (<code>View Tag</code>), das Eingabefeld (<code>Edit Tag</code>), Pflichtfelder (<code>Req.</code>) sowie Fremdschlüssel-Dropdowns (<code>Lookup Table</code>).</li>
                <li><strong>Anmeldeschutz &amp; Rollen aktivieren (Reiter <code>7. Security</code>):</strong> Klicken Sie optional auf <em>„Systemtabellen 1-Klick in DB anlegen &amp; aktivieren“</em>, um Benutzeranmeldung, Rollen-Rechte und Datensatz-Eigentümerschutz einzurichten.</li>
                <li><strong>Code generieren (Reiter <code>9. Generate</code> oder Taste <code>F9</code>):</strong> Klicken Sie auf <em>„Jetzt alle Skripte generieren“</em>. Alle PHP-Dateien inkl. lokalem Bootstrap 3, jQuery, AdminLTE 2 und Font Awesome werden im Zielordner (z. B. <code>output/crm_shop_demo</code>) erzeugt.</li>
            </ol>
            <div style="padding:10px 14px; background:#eef5f9; border-left:4px solid #3c8dbc; font-size:12.5px;">
                <strong>Passwortschutz Hinweis:</strong> Das PHPMacherNG selbst ist mit dem Initial-Passwort <code>12345678</code> geschützt. Sie können dieses Passwort jederzeit oben rechts über das System-Menü (<strong>„Studio-Passwort ändern...“</strong>) anpassen.
            </div>
        </div>

        <!-- KAPITEL 2: DIE STUDIO-REITER -->
        <div id="help-tabs" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-sliders"></i> 2. Übersicht der Studio-Reiter</span></h3>
            <table class="pm-grid-table" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th style="width:200px;">Reiter</th>
                        <th>Funktion &amp; Wichtige Einstellungen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>1. Database</strong></td>
                        <td>MySQL/MariaDB-Verbindung (PDO &amp; MySQLi Dual-Treiber). Liest Tabellen, Views, Primärschlüssel, Auto-Increment-Spalten, <code>ENUM</code>-Werte und Foreign Keys automatisch aus.</td>
                    </tr>
                    <tr>
                        <td><strong>2. Table Setup</strong></td>
                        <td>Steuert pro Tabelle/View die Generierung von <code>*list.php</code>, <code>*add.php</code>, <code>*edit.php</code>, <code>*view.php</code>, <code>*delete.php</code>, <code>*update.php</code> sowie Master/Detail-Unterlisten.</td>
                    </tr>
                    <tr>
                        <td><strong>3. Field Setup</strong></td>
                        <td>Spalten-Editor wie in PHPMacherNG: Sichtbarkeit pro Seite, Schnellsuche (<code>QuickSearch</code>), Erweiterte Suche (<code>ExtSearch</code>), Validierung, Lookup-Verknüpfung und Touchscreen-Unterschriftenfelder (<code>SIGNATURE</code>). Auch Index-/Primärschlüsselfelder lassen sich in allen Ansichten ausblenden.</td>
                    </tr>
                    <tr>
                        <td><strong>4. Custom Files</strong></td>
                        <td>Erlaubt das Anlegen eigener PHP-Dateien (z. B. Dashboards, Auswertungen, PDF/CSV/JSON-Exporte) direkt im jeweiligen Projekt – wahlweise eingebettet in das AdminLTE-Layout (<em>Include common files</em>) oder als Standalone-Skript.</td>
                    </tr>
                    <tr>
                        <td><strong>5. Code (Events &amp; Scripts)</strong></td>
                        <td>Projektspezifische <strong>Server Events (PHP)</strong> wie <code>Row_Inserting</code>, <code>Row_Updating</code>, <code>Row_Rendered</code>, <code>Recordset_Selecting</code> sowie <strong>Client Scripts (JavaScript)</strong> pro Tabelle und global für das aktuelle Projekt.</td>
                    </tr>
                    <tr>
                        <td><strong>6. Menu Editor</strong></td>
                        <td>Hierarchischer Menü-Editor zum Sortieren, Verschachteln in beliebige Untermenü-Ebenen und Hinzufügen eigener Menüpunkte oder Gruppen-Überschriften.</td>
                    </tr>
                    <tr>
                        <td><strong>7. Security</strong></td>
                        <td>Konfiguration von Hardcoded Admin, Datenbank-Benutzertabelle (<code>benutzer</code>), Rollen-Berechtigungen (<code>User Levels</code>) und zeilenbasiertem Eigentümerschutz (<code>User ID Security</code>).</td>
                    </tr>
                    <tr>
                        <td><strong>8. PHP &amp; HTML</strong></td>
                        <td>Wahl des AdminLTE-2-Farbschemas (<code>skin-blue</code>, <code>skin-green</code>, <code>skin-black</code> usw.), Datumsformat (z. B. <code>d.m.Y</code>), Währungssymbol, Datensätze pro Seite und SQL-Debug-Modus.</td>
                    </tr>
                    <tr>
                        <td><strong>9. Generate</strong></td>
                        <td>Erzeugt die komplette Zielanwendung (kompatibel mit <strong>PHP 5.4 bis PHP 8.4+</strong>) inkl. Server Events, Client Scripts und lokal kopierten Frontend-Assets.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- KAPITEL 3: FELD-TYPEN & LOOKUP-TABELLEN -->
        <div id="help-fields" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-columns"></i> 3. Feld-Konfiguration (View Tags, Edit Tags &amp; Lookup Tables)</span></h3>
            <p style="margin-top:0;">Im Reiter <strong>3. Field Setup</strong> bestimmen Sie oben in der Tabelle die Grundeinstellungen eines Feldes und unten im <strong>Detail-Inspektor</strong> dessen Verhalten:</p>
            <ul style="line-height:1.7;">
                <li><strong>View Tag (Anzeigeformat in List/View):</strong>
                    <code>FORMATTED TEXT</code> (Standardtext), <code>NUMBER</code> (Tausendertrennzeichen), <code>CURRENCY</code> (Währungsbetrag z. B. <code>€ 1.499,00</code>), <code>DATE</code> / <code>DATETIME</code>, <code>BOOLEAN</code> (Grünes/Graues Ja/Nein-Badge), <code>EMAIL</code> (klickbarer <code>mailto:</code>-Link), <code>LINK</code>, <code>IMAGE</code>, <code>SIGNATURE</code> (als Grafik dargestellte Touchscreen-Unterschrift), <code>HTML</code>.
                </li>
                <li><strong>Edit Tag (Formular-Eingabeelement in Add/Edit/ExtSearch):</strong>
                    <code>TEXT</code>, <code>TEXTAREA</code>, <code>PASSWORD</code>, <code>SELECT</code> (Dropdown), <code>RADIO</code>, <code>CHECKBOX</code>, <code>DATE</code> (HTML5-Datepicker), <code>SIGNATURE</code> (interaktives HTML5-Touchscreen-Unterschriftenfeld), <code>HIDDEN</code>, <code>READONLY</code>.
                </li>
                <li><strong>Statische Auswahlwerte (User Values):</strong>
                    Für <code>SELECT</code>, <code>RADIO</code> oder <code>CHECKBOX</code> ohne eigene Datenbanktabelle im Format <code>Wert1:Anzeige1,Wert2:Anzeige2</code> (z. B. <code>1:Aktiv,0:Inaktiv</code>). Bei MySQL-<code>ENUM</code>-Spalten werden diese Werte bereits automatisch aus der Datenbank übernommen.
                </li>
                <li><strong>Lookup Table (Fremdschlüssel-Verknüpfung):</strong>
                    Verbindet ein Feld (z. B. <code>kunde_id</code> in <code>bestellungen</code>) mit einer Quelltabelle (z. B. <code>kunden</code>).
                    <ul>
                        <li><code>Link Field</code>: Der gespeicherte Primärschlüssel (z. B. <code>kunde_id</code>).</li>
                        <li><code>Display Field</code>: Der angezeigte Text im Dropdown und in der Listenansicht (z. B. <code>firma</code>).</li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- KAPITEL 3b: DATENBANK-VIEWS & VISUELLER SQL-GENERATOR -->
        <div id="help-views" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-eye" style="color:#8e44ad;"></i> 3b. Datenbank-Views erstellen &amp; bearbeiten (Visueller SQL-Generator &amp; SQL-Editor)</span></h3>
            <p style="margin-top:0;">
                Wie im Original PHPMaker 2018 können Sie in PHPMacherNG direkt auf Datenbankebene eigene <strong>SQL-Views (<code>CREATE OR REPLACE VIEW ... AS SELECT ...</code>)</strong> anlegen, ändern und löschen.
                Öffnen Sie den View-Builder über den Button <strong>„DB-View“</strong> in der oberen Startleiste, über <strong>„+View“</strong> in der linken Seitenleiste oder im Reiter <strong>2. Table Setup</strong>.
            </p>
            <ul style="line-height:1.7; margin-bottom:0;">
                <li><strong>Visueller SQL-Generator (links im Dialog):</strong>
                    Wählen Sie per Mausklick eine oder mehrere beteiligte Tabellen aus. PHPMacherNG erkennt Fremdschlüssel-Beziehungen und gleichnamige ID-Spalten automatisch und schlägt passende <code>LEFT JOIN</code> / <code>INNER JOIN</code>-Verknüpfungen vor. Zusätzlich können Sie Spalten an-/abwählen, Spalten-Aliase (<code>AS ...</code>), Aggregatfunktionen (<code>COUNT</code>, <code>SUM</code>, <code>AVG</code>, <code>MIN</code>, <code>MAX</code>), Berechnungsformeln, <code>GROUP BY</code>, <code>ORDER BY</code> sowie <code>WHERE</code>- und <code>HAVING</code>-Filter bequem zusammenklicken.
                </li>
                <li><strong>Direkter SQL-Editor &amp; Live-Vorschau (rechts im Dialog):</strong>
                    Sie können den generierten SQL-Befehl jederzeit frei von Hand anpassen oder komplexe eigene <code>SELECT</code>-Abfragen direkt eingeben. Mit einem Klick auf <strong>„SQL testen &amp; Vorschau laden“</strong> wird die Abfrage live gegen Ihre MySQL/MariaDB-Datenbank ausgeführt und das Ergebnis sofort als Vorschautabelle angezeigt.
                </li>
                <li><strong>Primärschlüssel (Primary Key) für Views:</strong>
                    Da MySQL bei Views standardmäßig keinen Primärschlüssel meldet, können Sie im View-Builder (oder im Field Setup) eine eindeutige Spalte als <strong>Primary Key der View</strong> markieren. Dadurch stehen auch für Datenbank-Views die Einzel-Detailansicht (<code>*view.php</code>) sowie Master/Detail-Verknüpfungen uneingeschränkt zur Verfügung.
                </li>
            </ul>
        </div>

        <!-- KAPITEL 3c: BALKEN- & TORTENDIAGRAMME -->
        <div id="help-charts" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-bar-chart" style="color:#00a65a;"></i> 3c. Diagramme aus Datenbankdaten (Balken- &amp; Tortendiagramme)</span></h3>
            <p style="margin-top:0;">
                PHPMacherNG bietet zwei komfortable Wege, um Daten aus Ihrer MySQL/MariaDB-Datenbank als interaktives <strong>Balkendiagramm (Bar Chart)</strong> oder <strong>Tortendiagramm (Pie Chart)</strong> auszugeben – komplett lokal per HTML5 Canvas und mit einem Live-Umschalter zwischen Balken- und Tortendarstellung:
            </p>
            <ul style="line-height:1.7; margin-bottom:0;">
                <li><strong>1. Direkt im Reiter <code>2. Table Setup</code> für jede Tabelle oder View:</strong>
                    Wählen Sie im Reiter <strong>2. Table Setup</strong> eine Tabelle oder View aus und klicken Sie im unteren Inspektor-Bereich auf <strong>„+ Balkendiagramm“</strong> oder <strong>„+ Tortendiagramm“</strong>. Sie bestimmen einfach das Kategoriefeld (X-Achse / Gruppierung), die Berechnung (<code>COUNT(*)</code>, <code>SUM</code>, <code>AVG</code>, <code>MIN</code>, <code>MAX</code> oder Direktwert), die Wert-Spalte (Y-Achse) sowie die Position (oberhalb oder unterhalb der Liste). Das Diagramm berücksichtigt in der generierten Anwendung automatisch alle aktiven Suchfilter!
                </li>
                <li><strong>2. In eigenen Seiten / Dashboards (<code>4. Custom Files</code>) per <code>ExecuteChart()</code>:</strong>
                    Über die Snippet-Buttons <strong>„Balkendiagramm (ExecuteChart)“</strong> und <strong>„Tortendiagramm (ExecuteChart)“</strong> fügen Sie mit einem Klick ein beliebiges SQL-Diagramm in Ihre Custom Files ein:
                    <pre style="margin:6px 0 0 0;"><code>&lt;?php
echo DbHelper()-&gt;ExecuteChart(
    "SELECT status, COUNT(*) AS anzahl FROM bestellungen GROUP BY status",
    array('type' =&gt; 'pie', 'title' =&gt; 'Bestellungen nach Status', 'height' =&gt; 280)
);
?&gt;</code></pre>
                </li>
            </ul>
        </div>

        <!-- KAPITEL 4: SECURITY, ROLLEN & RECHTE -->
        <div id="help-security" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-shield"></i> 4. Rechte- &amp; Rollenfunktion (PHPMacherNG Advanced Security)</span></h3>
            <p style="margin-top:0;">Das integrierte Sicherheitssystem bildet alle drei Sicherheitsstufen von PHPMacherNG originalgetreu ab:</p>

            <h4>4.1 Benutzer-Authentifizierung (Login)</h4>
            <ul style="line-height:1.7;">
                <li><strong>Hardcoded Administrator:</strong> Ein fest hinterlegtes System-Admin-Konto (Standard: Benutzer <code>admin</code>, Initial-Passwort <code>12345678</code>), das immer die Rolle <code>-1 (Administrator)</code> besitzt.</li>
                <li><strong>Datenbank-Benutzertabelle (Use Existing Table):</strong> Authentifiziert Benutzer gegen eine Tabelle wie <code>benutzer</code>. Unterstützt automatisch Klartext-Passwörter, <code>MD5</code>, <code>SHA-256</code> sowie <code>bcrypt</code> (<code>password_verify</code>). Ist in der Tabelle eine Spalte <code>aktiv = 0</code> vorhanden, wird der Login automatisch gesperrt.</li>
            </ul>

            <h4>4.2 Rollen &amp; Tabellen-Berechtigungen (User Level Security)</h4>
            <p>Jeder Rolle (<code>User Level</code>) wird pro Tabelle und Custom File eine Bitmaske zugewiesen:</p>
            <table class="pm-grid-table" style="margin-bottom:12px;">
                <thead>
                    <tr>
                        <th>Recht</th>
                        <th>Konstante in <code>ewcfg14.php</code></th>
                        <th>Bit-Wert</th>
                        <th>Auswirkung in der generierten Anwendung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>Add</strong></td><td><code>EW_ALLOW_ADD</code></td><td><code>1</code></td><td>Erlaubt das Anlegen neuer Datensätze (<code>*add.php</code>).</td></tr>
                    <tr><td><strong>Delete</strong></td><td><code>EW_ALLOW_DELETE</code></td><td><code>2</code></td><td>Erlaubt das Löschen &amp; Multi-Delete (<code>*delete.php</code>).</td></tr>
                    <tr><td><strong>Edit</strong></td><td><code>EW_ALLOW_EDIT</code></td><td><code>4</code></td><td>Erlaubt das Bearbeiten &amp; Multi-Update (<code>*edit.php</code>, <code>*update.php</code>).</td></tr>
                    <tr><td><strong>List</strong></td><td><code>EW_ALLOW_LIST</code></td><td><code>8</code></td><td>Zeigt die Tabelle im linken Hauptmenü und erlaubt <code>*list.php</code>.</td></tr>
                    <tr><td><strong>Admin</strong></td><td><code>EW_ALLOW_ADMIN</code></td><td><code>16</code></td><td>Gewährt für diese Tabelle alle Rechte inkl. Sichtbarkeit fremder Datensätze.</td></tr>
                    <tr><td><strong>View</strong></td><td><code>EW_ALLOW_VIEW</code></td><td><code>32</code></td><td>Erlaubt die Einzel-Detailansicht (<code>*view.php</code>).</td></tr>
                    <tr><td><strong>Search</strong></td><td><code>EW_ALLOW_SEARCH</code></td><td><code>64</code></td><td>Schaltet Schnellsuche und Erweiterte Suche frei.</td></tr>
                </tbody>
            </table>
            <p>
                <strong>Dynamic User Levels (`userpriv.php`):</strong> Wenn aktiviert, werden die Rollen in der Tabelle <code>userlevels</code> und die Bitmasken in <code>userlevelpermissions</code> gespeichert. Administratoren können dann im laufenden Betrieb über den Menüpunkt <strong>„Rollen-Rechte“ (`userpriv.php`)</strong> neue Rollen anlegen und Häkchen live ändern, ohne das Projekt neu generieren zu müssen!
            </p>

            <h4>4.3 Zeilenbasierter Eigentümerschutz (User ID Security &amp; Parent User ID)</h4>
            <p>
                Wenn Sie im Reiter <strong>7. Security</strong> in der rechten Spalte der Matrix für eine Tabelle ein <strong>User ID Feld (Zeilen-Eigentümer)</strong> auswählen (z. B. <code>erstellt_von</code> bei <code>bestellungen</code>):
            </p>
            <ul style="line-height:1.7;">
                <li>Normale Mitarbeiter sehen, bearbeiten und löschen in dieser Tabelle <strong>nur ihre eigenen Datensätze</strong> (bei denen <code>erstellt_von = CurrentUserID()</code> gilt).</li>
                <li>Beim Anlegen (<code>*add.php</code>) wird automatisch die <code>CurrentUserID()</code> des eingeloggten Mitarbeiters als Eigentümer eingetragen.</li>
                <li><strong>Vorgesetzten-Hierarchie (Parent User ID):</strong> Ist in der Benutzertabelle ein Vorgesetzten-Feld (<code>vorgesetzter_id</code>) angegeben, sieht ein Abteilungsleiter automatisch sowohl seine eigenen Datensätze als auch alle Datensätze seiner unterstellten Mitarbeiter!</li>
            </ul>
        </div>

        <!-- KAPITEL 5: CUSTOM FILES -->
        <div id="help-customfiles" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-file-code-o"></i> 5. Eigene Dateien erstellen (Custom Files &ndash; projektspezifisch)</span></h3>
            <p style="margin-top:0;">
                Wie im originalen PHPMacherNG können Sie im Reiter <strong>4. Custom Files</strong> (oder links im Strukturbaum unter <em>Custom Files</em>) eigene PHP-Dateien hinzufügen.
                <strong>Wichtig:</strong> Alle angelegten Custom Files gehören immer exklusiv zu dem Projekt (<code>.pmp.json</code>), in dem sie erstellt wurden, und werden nur in diesem Projekt angezeigt und generiert.
            </p>
            <ul style="line-height:1.7;">
                <li><strong>Option <code>Include common files</code> aktiviert (Standard):</strong>
                    Der Generator bettet Ihren PHP-/HTML-Code automatisch in das AdminLTE-2-Template (<code>header.php</code>, linke Navigation, <code>footer.php</code>) ein, prüft vorab die Rollenberechtigung über <code>$Security-&gt;CheckPagePermission()</code> und stellt <code>$db = &amp;DbHelper();</code> bereit.
                </li>
                <li><strong>Option <code>Include common files</code> deaktiviert (Standalone / API-Modus):</strong>
                    Es wird kein HTML-Header/Footer ausgegeben, aber <code>phpfn14.php</code> und <code>ewdbhelper14.php</code> sind bereits geladen. Ideal für eigene JSON-Endpunkte, AJAX-Skripte oder CSV-Downloads.
                </li>
            </ul>
        </div>

        <!-- KAPITEL 5b: SERVER EVENTS & CLIENT SCRIPTS -->
        <div id="help-events" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-bolt" style="color:#f39c12;"></i> 5b. Server Events (PHP) &amp; Client Scripts (JavaScript) &ndash; projektspezifisch</span></h3>
            <p style="margin-top:0;">
                Im Reiter <strong>5. Code (Events &amp; Scripts)</strong> (oder über den Button <strong>„Events &amp; Scripts“</strong> in der oberen Startleiste) können Sie eigene Geschäftslogik direkt in den Lebenszyklus der generierten Seiten einhängen.
                Alle Server Events und Client Scripts werden <strong>strikt getrennt für jedes Projekt einzeln</strong> in dessen <code>.pmp.json</code>-Datei gespeichert.
            </p>

            <h4>Wichtige Tabellen-Server-Events (PHP in <code>&lt;tabelle&gt;info.php</code>):</h4>
            <ul style="line-height:1.7;">
                <li><code>Recordset_Selecting(&amp;$filter)</code>: Wird vor dem Laden der Listenansicht aufgerufen. Mit <code>ew_AddFilter($filter, "`aktiv` = 1");</code> hängen Sie eigene SQL-WHERE-Bedingungen an.</li>
                <li><code>Row_Rendering(&amp;$rs)</code> &amp; <code>Row_Rendered(&amp;$rs, &amp;$rowAttrs)</code>: Wird für jede Zeile vor der Anzeige aufgerufen. Über <code>$rowAttrs['style'] = 'background:#fff3cd;';</code> oder <code>$rowAttrs['class'] = 'danger';</code> heben Sie Zeilen abhängig vom Feldwert farblich hervor.</li>
                <li><code>Row_Inserting($rsold, &amp;$rsnew)</code> &amp; <code>Row_Updating($rsold, &amp;$rsnew)</code>: Wird <strong>vor</strong> dem <code>INSERT</code> bzw. <code>UPDATE</code> ausgeführt. Da <code>$rsnew</code> per Referenz übergeben wird, werden Änderungen wie <code>$rsnew['geaendert_am'] = CurrentDateTime();</code> direkt in die Datenbank geschrieben! Geben Sie <code>false</code> zurück (zusammen mit <code>ew_SetFailureMessage('...')</code>), um das Speichern abzubrechen.</li>
                <li><code>Row_Inserted($rsold, &amp;$rsnew)</code> &amp; <code>Row_Updated($rsold, &amp;$rsnew)</code>: Wird unmittelbar <strong>nach</strong> dem Speichern aufgerufen (z. B. für Folge-SQLs per <code>ew_Execute()</code> oder Audit-Logs).</li>
                <li><code>Row_Deleting(&amp;$rs)</code> &amp; <code>Row_Deleted(&amp;$rs)</code>: Wird vor bzw. nach dem Löschen eines Datensatzes ausgeführt.</li>
                <li><code>Form_CustomValidate(&amp;$customError)</code>: Serverseitige Plausibilitätsprüfung beim Absenden von Add-, Edit- und Multi-Update-Formularen.</li>
            </ul>

            <h4>Globale Server Events &amp; Client Scripts (JavaScript):</h4>
            <ul style="line-height:1.7; margin-bottom:0;">
                <li><strong>Global (PHP):</strong> <code>Global_Code</code> (eigene globale Funktionen in <code>phpfn14.php</code>), <code>Page_Loading()</code>, <code>Page_Rendering()</code>, <code>Page_Unloaded()</code> sowie die Anmelde-Events <code>User_LoggingIn($usr, &amp;$pwd)</code>, <code>User_LoggedIn($usr)</code>, <code>User_LoginError($usr, $pwd)</code> und <code>User_LoggingOut($usr)</code>.</li>
                <li><strong>Client Scripts (JS):</strong> Sowohl global als auch für jede einzelne Tabelle (<code>List</code>, <code>Add</code>, <code>Edit</code>, <code>View</code>, <code>Login</code>) stehen <code>Client_Script</code>, <code>Startup_Script</code> (wird bei <code>DOMContentLoaded</code> ausgeführt) und <code>Form_CustomValidate(fobj)</code> für Browser-Interaktionen und Live-Berechnungen bereit.</li>
            </ul>
        </div>

        <!-- KAPITEL 6: DBHELPER & DATENZUGRIFFS-API -->
        <div id="help-dbhelper" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-database"></i> 6. Entwickler-Referenz: PHPMacherNG Datenzugriffs-Funktionen</span></h3>
            <p style="margin-top:0;">
                In allen <strong>Custom Files</strong> und <strong>Server Events</strong> stehen Ihnen sowohl das <code>DbHelper()</code>-Objekt als auch die globalen <code>ew_Execute*</code>-Funktionen (mit optionalen Prepared-Statement-Parametern <code>?</code> zum Schutz vor SQL-Injection) zur Verfügung:
            </p>

            <h4>6.1 SQL-Abfrage direkt als fertige Bootstrap-HTML-Tabelle ausgeben</h4>
<pre><code>&lt;?php
// Gibt eine komplett formatierte Bootstrap-Tabelle mit eigenen Spaltenüberschriften aus:
$sql = "SELECT firma, stadt, kundengruppe FROM kunden WHERE aktiv = ? ORDER BY firma ASC";
echo DbHelper()-&gt;ExecuteHtml($sql, array(
    'fieldcaption' =&gt; array(
        'firma'        =&gt; 'Firmenname',
        'stadt'        =&gt; 'Standort',
        'kundengruppe' =&gt; 'Kategorie'
    ),
    'horizontal' =&gt; false // Bei true: Vertikale Schlüssel-Wert-Darstellung für Einzeldatensätze
), array(1));
?&gt;</code></pre>

            <h4>6.2 Einzelnen Wert abfragen (z. B. für Zähler, Summen, KPI-Boxen)</h4>
<pre><code>&lt;?php
$anzahlOffen = (int)ew_ExecuteScalar(
    "SELECT COUNT(*) FROM bestellungen WHERE status = ?",
    array('Offen')
);
$gesamtUmsatz = (float)ew_ExecuteScalar("SELECT COALESCE(SUM(gesamtbetrag), 0) FROM bestellungen");
echo "Offene Bestellungen: " . $anzahlOffen . " | Umsatz: " . number_format($gesamtUmsatz, 2, ',', '.') . " €";
?&gt;</code></pre>

            <h4>6.3 Mehrere Datensätze oder eine einzelne Zeile als Array laden</h4>
<pre><code>&lt;?php
// Alle Zeilen als Array von assoziativen Arrays:
$produkte = ew_ExecuteRows("SELECT produkt_id, produktname, preis FROM produkte WHERE lieferbar = ?", array(1));
foreach ($produkte as $p) {
    echo ew_HtmlEncode($p['produktname']) . ": " . $p['preis'] . "&lt;br&gt;";
}

// Genau eine Zeile als assoziatives Array:
$einKunde = ew_ExecuteRow("SELECT * FROM kunden WHERE kunde_id = ?", array(1));
if ($einKunde) {
    echo "Ansprechpartner: " . ew_HtmlEncode($einKunde['ansprechpartner']);
}
?&gt;</code></pre>

            <h4>6.4 Daten einfügen, aktualisieren oder löschen (INSERT / UPDATE / DELETE)</h4>
<pre><code>&lt;?php
// Ausführen einer schreibenden SQL-Anweisung:
$ok = ew_Execute(
    "UPDATE bestellungen SET status = ?, bemerkung = ? WHERE bestellung_id = ?",
    array('Versendet', 'Freigegeben durch ' . CurrentUserName(), 1004)
);

// Nach einem INSERT die neue Auto-Increment-ID abrufen:
$neueId = DbHelper()-&gt;Insert_ID();
?&gt;</code></pre>

            <h4>6.5 JSON-Ausgabe &amp; Aktuelle Benutzer-Informationen</h4>
<pre><code>&lt;?php
// Ergebnis direkt als JSON-Array ausgeben:
echo ew_ExecuteJson("SELECT stadt, COUNT(*) AS anzahl FROM kunden GROUP BY stadt");

// Nur den ersten Datensatz als JSON-Objekt ausgeben:
echo ew_ExecuteJson("SELECT * FROM kunden WHERE kunde_id = ?", array('firstonly' =&gt; true), array(1));

// Globale Benutzer- und Zeit-Funktionen:
$benutzerName = CurrentUserName();      // z.B. "mitarbeiter1"
$benutzerId   = CurrentUserID();        // z.B. 3
$vorgesetzter = CurrentParentUserID();  // z.B. 2
$rollenId     = CurrentUserLevel();     // z.B. -1 (Admin), 0 (Default), 1 (Manager)
$rollenName   = CurrentUserLevelName(); // z.B. "Administrator"
$istAdmin     = IsSysAdmin();           // true / false
$heute        = CurrentDate();          // YYYY-MM-DD
?&gt;</code></pre>
        </div>

        <!-- KAPITEL 7: PORTIERUNG AUF DEBIAN / LINUX & ANDERE SYSTEME -->
        <div id="help-debian" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-linux"></i> 7. Portierung auf ein anderes System (Debian / Ubuntu Linux / Windows)</span></h3>
            <p style="margin-top:0;">
                Da alle Frontend-Bibliotheken lokal im Ordner <code>assets/vendor/</code> liegen und der generierte Code keine externen Abhängigkeiten besitzt, läuft <strong>PHPMacherNG</strong> problemlos auf jedem Zielsystem (PHP 5.4 bis PHP 8.4+):
            </p>
<pre><code># 1. Pakete auf Debian / Ubuntu installieren
sudo apt update
sudo apt install apache2 mariadb-server php libapache2-mod-php php-mysql php-mbstring php-json

# 2. Optional: Demo-Datenbank anlegen &amp; SQL-Datei importieren
sudo mysql -u root -e "CREATE DATABASE phpmaker_demo CHARACTER SET utf8 COLLATE utf8_general_ci;"
sudo mysql -u root phpmaker_demo &lt; database_demo.sql

# 3. WICHTIG: Verzeichnisse anlegen &amp; Lese-/Schreibrechte für den Webserver (www-data) setzen
mkdir -p /var/www/html/phpmaker/projects /var/www/html/phpmaker/output
sudo chown -R www-data:www-data /var/www/html/phpmaker/projects /var/www/html/phpmaker/output
sudo chmod -R 775 /var/www/html/phpmaker/projects /var/www/html/phpmaker/output</code></pre>

            <h4>Hinweise zur Fehlerbehebung beim Portieren („Projektdatei konnte nicht geladen werden“)</h4>
            <div style="padding:12px 14px; background:#fff8e5; border-left:4px solid #f39c12; font-size:12.5px; margin-bottom:12px;">
                <ul style="margin:0; padding-left:18px; line-height:1.7;">
                    <li><strong>Lese- und Schreibrechte unter Linux:</strong> Wenn Dateien per <code>root</code>, SCP oder SFTP auf einen Linux-Server kopiert werden, gehören die Ordner <code>projects/</code> und <code>output/</code> oft dem Benutzer <code>root</code>. Führen Sie in diesem Fall <code>chown -R www-data:www-data projects output</code> und <code>chmod -R 775 projects output</code> aus, damit der Webserver Projektdateien (<code>.pmp.json</code>) lesen und speichern kann.</li>
                    <li><strong>Leeres <code>projects/</code>-Verzeichnis beim Erststart:</strong> Wird PHPMacherNG ohne vorhandene <code>.pmp.json</code>-Dateien auf ein neues System kopiert, erkennt das Studio dies automatisch und initialisiert beim Start ein neues, leeres Standardprojekt (<code>MeinProjekt</code>) ohne Fehlermeldung.</li>
                    <li><strong>Groß-/Kleinschreibung &amp; UTF-8 BOM:</strong> Linux-Dateisysteme unterscheiden strikt zwischen Groß- und Kleinschreibung (z. B. <code>Personen.pmp.json</code> vs. <code>personen.pmp.json</code>). PHPMacherNG löst Projektnamen automatisch case-insensitiv auf und entfernt beim Laden oder Importieren über den Button <strong>„Import“</strong> automatisch eventuelle Windows-UTF-8-BOM-Header.</li>
                    <li><strong>Projekte zwischen Systemen übertragen:</strong> Nutzen Sie in der oberen Kopfleiste den Button <strong>„Download“</strong>, um Ihre <code>.pmp.json</code>-Projektdatei herunterzuladen, und auf dem Zielsystem den Button <strong>„Import“</strong>, um das Projekt wieder einzuspielen.</li>
                </ul>
            </div>

            <p style="margin-bottom:0;">
                <strong>Reines Anwendungs-Deployment (ohne Studio):</strong> Wenn Sie nur die fertig generierte Anwendung (oder das über <em>„Generierte Dateien als ZIP herunterladen“</em> erzeugte Archiv aus <code>output/&lt;projekt&gt;/</code>) auf den Zielserver kopieren, passen Sie dort lediglich in der Datei <code>ewcfg14.php</code> die Datenbank-Zugangsdaten (<code>EW_CONN_HOST</code>, <code>EW_CONN_USER</code>, <code>EW_CONN_PASS</code>, <code>EW_CONN_DB</code>) an.
            </p>
        </div>

        <!-- KAPITEL 8: IT-SICHERHEITSKONZEPT (BSI / OWASP) -->
        <div id="help-sec-concept" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-lock"></i> 8. IT-Sicherheitskonzept &amp; Go-Live-Checkliste (BSI / OWASP Top 10)</span></h3>
            <p style="margin-top:0;">
                Zusammenfassung der technischen und organisatorischen Sicherheitsmaßnahmen (vollständiges Dokument in <code>IT_SICHERHEITSKONZEPT.md</code>):
            </p>
            <ul style="line-height:1.7;">
                <li><strong>SQL-Injection-Schutz (OWASP A03):</strong> Alle generierten CRUD-Seiten, Suchfilter und <code>BuildUserIDFilter()</code>-Abfragen arbeiten mit parametrisierten Prepared Statements (<code>?</code>) über PDO bzw. <code>real_escape_string</code>-Fallback sowie striktem Spalten-Whitelisting bei Sortierungen.</li>
                <li><strong>XSS- &amp; CSRF-Schutz (OWASP A01/A03):</strong> Konsequente HTML-Maskierung über <code>ew_HtmlEncode()</code> (<code>ENT_QUOTES, 'UTF-8'</code>) und kryptografische CSRF-Synchronizer-Tokens (<code>ew_GetCsrfToken()</code> / <code>ew_CheckCsrfToken()</code> mit zeitkonstantem Vergleich).</li>
                <li><strong>4-stufige Zugriffskontrolle (Defense in Depth):</strong>
                    <ol>
                        <li>Passwortschutz für Studio &amp; API (<code>src/StudioAuth.php</code>, SHA-256) + <code>.htaccess</code>-Sperre für <code>projects/*.pmp.json</code>.</li>
                        <li>Session-isolierter Anwendungs-Login (<code>EW_PROJECT_ID . '_sec_'</code>) mit Unterstützung für <code>bcrypt</code> (<code>password_verify</code>), SHA-256 und MD5.</li>
                        <li>Serverseitige Rollen-Bitmaskenprüfung (<code>CheckPagePermission</code>) auf jeder Seite vor jeglicher SQL-Ausführung.</li>
                        <li>Mandanten-/Zeilenschutz (<code>User ID Security</code> + <code>Parent User ID</code>) gegen Insecure Direct Object Reference (IDOR).</li>
                    </ol>
                </li>
                <li><strong>DSGVO &amp; Air-Gap-Fähigkeit:</strong> Alle Frontend-Bibliotheken (<code>Bootstrap 3</code>, <code>jQuery</code>, <code>AdminLTE 2</code>, <code>Font Awesome</code>) werden zu 100 % lokal aus <code>assets/vendor/</code> geladen – kein Abfluss von Besucher-IPs an externe CDNs.</li>
            </ul>

            <h4>Go-Live-Checkliste vor Produktivsetzung</h4>
            <table class="pm-grid-table">
                <thead>
                    <tr>
                        <th>Prüfpunkt</th>
                        <th>Empfohlene Maßnahme für den Produktivbetrieb</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>1. Initial-Passwörter ändern</strong></td><td>Initial-Passwort <code>12345678</code> im Studio (Button <em>Studio-Passwort</em>) sowie beim Hardcoded Admin ändern.</td></tr>
                    <tr><td><strong>2. Studio vom Produktivserver trennen</strong></td><td>Auf öffentlichen Webservern nur den Inhalt des generierten Zielordners (<code>output/&lt;app&gt;/</code>) bereitstellen.</td></tr>
                    <tr><td><strong>3. Dedizierter DB-Benutzer</strong></td><td>In <code>ewcfg14.php</code> keinen MySQL-<code>root</code>-Zugang verwenden, sondern einen Benutzer mit <code>SELECT, INSERT, UPDATE, DELETE</code>.</td></tr>
                    <tr><td><strong>4. SQL-Debug deaktivieren</strong></td><td>In Reiter <code>6. PHP &amp; HTML</code> den SQL-Debug-Modus ausschalten (<code>EW_DEBUG_ENABLED = false</code>) und HTTPS/TLS aktivieren.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- KAPITEL 9: MULTI-USER STUDIO, PROJEKT-FREIGABEN (TEAM SHARING) & GIT-VERSIONIERUNG -->
        <div id="help-multiuser-git" class="pm-card help-card">
            <h3 class="pm-card-title"><span><i class="fa fa-code-fork"></i> 9. Multi-User Studio, Projekt-Sharing im Team &amp; Git-Versionierung</span></h3>
            <p style="margin-top:0;">
                <strong>PHPMacherNG</strong> unterstützt echtes <strong>Multi-User-Arbeiten</strong> mit strikter Projekttrennung pro Benutzer, gezielter Projekt-Freigabe im Team sowie einer integrierten <strong>Git-Versionskontrolle</strong>:
            </p>
            <ul style="line-height:1.7;">
                <li><strong>Multi-User Accounts &amp; Projekt-Eigentümer (Owner):</strong> Jeder Benutzer meldet sich mit seinem eigenen Benutzernamen und Passwort am Studio an (oder registriert ein neues Entwickler-Konto auf dem Login-Bildschirm). Standardmäßig sieht und bearbeitet jeder Benutzer <strong>ausschließlich seine eigenen Projekte</strong>.</li>
                <li><strong>Projekte im Team teilen (Button „Teilen“ in der Topbar):</strong> Der Eigentümer eines Projekts kann sein Projekt gezielt für andere Studio-Benutzer freigeben:
                    <ul>
                        <li><strong>Bearbeiter (Lesen &amp; Schreiben):</strong> Darf das Projekt öffnen, Tabellen/Felder/Events/Einstellungen bearbeiten, speichern, Git-Commits anlegen und den PHP-Code generieren.</li>
                        <li><strong>Betrachter (Nur Lesen):</strong> Darf das Projekt und die Git-Historie im Studio einsehen, kann aber keine Änderungen speichern oder überschreiben.</li>
                        <li><strong>Löschschutz:</strong> Nur der Projekteigentümer (Owner) oder ein Studio-Administrator kann ein Projekt löschen oder dessen Freigaben verwalten.</li>
                    </ul>
                </li>
                <li><strong>Git-Versionierung (Button „Git: main“ in der Topbar):</strong> Jedes Projekt verfügt über ein eigenes Git-artiges Repository unter <code>projects/.vcs/&lt;projekt&gt;/</code>:
                    <ul>
                        <li><strong>Commits mit SHA-1 Hash:</strong> Speichern Sie jederzeit vollständige Projekt-Snapshots mit Commit-Nachricht, Autor und Zeitstempel.</li>
                        <li><strong>Branches (<code>main</code>, <code>feature/...</code>):</strong> Legen Sie eigene Entwicklungs-Branches an, wechseln Sie per 1-Klick zwischen Branches (<code>Checkout</code>) und führen Sie Branches per strukturellem <code>Merge</code> wieder zusammen.</li>
                        <li><strong>Release-Tags (z. B. <code>v1.0</code>):</strong> Markieren Sie wichtige Meilensteine in der Commit-Historie.</li>
                        <li><strong>Visueller Diff-Inspektor &amp; 1-Klick-Rollback:</strong> Vergleichen Sie jeden Commit (oder Ihren aktuellen Arbeitsstand) sowohl strukturell (geänderte Tabellen, Felder, Events, Einstellungen) als auch im zeilenweisen Unified-Diff (<code>git diff</code>) und stellen Sie jeden früheren Projektstand mit 1 Klick wieder her.</li>
                    </ul>
                </li>
            </ul>
        </div>

    </div>
</div>

<?php if (!$isEmbedded): ?>
</div>
</body>
</html>
<?php endif; ?>
