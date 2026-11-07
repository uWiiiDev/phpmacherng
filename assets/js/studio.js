/**
 * PHPMacherNG - Frontend Controller (studio.js)
 * Verwaltet den gesamten Projekt-Zustand, die Baumansicht, Tabellen-/Feldeditoren und Code-Generierung.
 */

var StudioApp = {
    currentFilename: 'crm_shop_demo',
    project: null,
    currentUser: null,
    studioUsers: [],
    currentAccessRole: 'owner',
    gitStatus: null,
    shareState: {
        owner: 'admin',
        canManage: true,
        sharedWith: {}
    },
    selectedTable: null,
    selectedField: null,
    selectedCustomFile: null,
    selectedMenuItem: null,
    selectedEventScope: '__global__',
    selectedEventKey: 'Page_Loading',

    init: function () {
        this.bindTabs();
        this.loadProjectList(function (projects) {
            if (projects && projects.length > 0) {
                var target = projects[0].filename;
                for (var i = 0; i < projects.length; i++) {
                    if (projects[i].filename === 'crm_shop_demo') {
                        target = 'crm_shop_demo';
                        break;
                    }
                }
                StudioApp.currentFilename = target;
                StudioApp.loadProject(target);
            } else {
                // Frisches Zielsystem ohne Projektdateien im Ordner projects/:
                // Automatisch ein neues Standardprojekt initialisieren ohne Fehlermeldung
                StudioApp.apiCall('new_project', { project_name: 'MeinProjekt' }, function (nRes) {
                    if (nRes && nRes.success && nRes.project) {
                        StudioApp.currentFilename = 'MeinProjekt';
                        StudioApp.project = StudioApp.normalizeProjectStructure(nRes.project);
                        StudioApp.selectedTable = null;
                        StudioApp.selectedField = null;
                        StudioApp.selectedCustomFile = null;
                        StudioApp.selectedMenuItem = null;
                        StudioApp.selectedEventScope = '__global__';
                        StudioApp.selectedEventKey = 'Page_Loading';
                        StudioApp.refreshAllViews();
                        StudioApp.setStatus('Neues Standardprojekt initialisiert.');
                    }
                });
            }
        });
    },

    bindTabs: function () {
        var tabs = document.querySelectorAll('.pm-tab');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].addEventListener('click', function () {
                var target = this.getAttribute('data-tab');
                StudioApp.switchTab(target);
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'F1' || e.keyCode === 112) {
                e.preventDefault();
                StudioApp.switchTab('help');
            } else if (e.key === 'F9' || e.keyCode === 120) {
                e.preventDefault();
                StudioApp.switchTab('generate');
                StudioApp.runGeneration();
            } else if (e.key === 'Escape' || e.keyCode === 27) {
                StudioApp.closeAllDropdowns();
            }
        });
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest || !e.target.closest('.pm-dropdown')) {
                StudioApp.closeAllDropdowns();
            }
        });
    },

    toggleDropdown: function (dropdownId, evt) {
        if (evt && evt.stopPropagation) {
            evt.stopPropagation();
        }
        var el = document.getElementById(dropdownId);
        if (!el) return;
        var isOpen = el.classList.contains('open');
        this.closeAllDropdowns();
        if (!isOpen) {
            el.classList.add('open');
        }
    },

    closeAllDropdowns: function () {
        var dds = document.querySelectorAll('.pm-dropdown.open');
        for (var i = 0; i < dds.length; i++) {
            dds[i].classList.remove('open');
        }
    },

    switchTab: function (tabId) {
        var tabs = document.querySelectorAll('.pm-tab');
        for (var i = 0; i < tabs.length; i++) {
            if (tabs[i].getAttribute('data-tab') === tabId) {
                tabs[i].classList.add('active');
            } else {
                tabs[i].classList.remove('active');
            }
        }
        var contents = document.querySelectorAll('.pm-tab-content');
        for (var j = 0; j < contents.length; j++) {
            if (contents[j].id === 'tab-' + tabId) {
                contents[j].classList.add('active');
            } else {
                contents[j].classList.remove('active');
            }
        }

        if (tabId === 'table') {
            this.renderTableTab();
        } else if (tabId === 'fields') {
            this.renderFieldsTab();
        } else if (tabId === 'customfiles') {
            this.renderCustomFilesTab();
        } else if (tabId === 'events') {
            this.renderEventsTab();
        } else if (tabId === 'menu') {
            this.renderMenuTab();
        } else if (tabId === 'security') {
            this.renderSecurityTab();
        } else if (tabId === 'settings') {
            this.renderSettingsTab();
        } else if (tabId === 'generate') {
            this.renderGenerateTab();
        }
    },

    apiCall: function (action, payload, callback) {
        this.setStatus('Verarbeite ' + action + '...');
        payload = payload || {};
        payload.action = action;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php?action=' + encodeURIComponent(action), true);
        xhr.setRequestHeader('Content-Type', 'application/json; charset=utf-8');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        StudioApp.setStatus('Bereit');
                        callback(res);
                    } catch (e) {
                        StudioApp.setStatus('Fehler beim Parsen der Server-Antwort');
                        alert('Ungültige JSON-Antwort vom Server:\n' + xhr.responseText);
                    }
                } else if (xhr.status === 401) {
                    window.location.href = 'index.php';
                } else {
                    StudioApp.setStatus('HTTP Fehler ' + xhr.status);
                    alert('Server-Fehler: HTTP ' + xhr.status);
                }
            }
        };
        xhr.send(JSON.stringify(payload));
    },

    setStatus: function (msg) {
        var el = document.getElementById('status-text');
        if (el) el.textContent = msg;
    },

    loadProjectList: function (cb) {
        this.apiCall('list_projects', {}, function (res) {
            var list = (res && res.success && res.projects) ? res.projects : [];
            if (res && res.success) {
                if (res.current_user) {
                    StudioApp.currentUser = res.current_user;
                    var uBadge = document.getElementById('topbar-current-username');
                    if (uBadge && res.current_user.username) {
                        uBadge.textContent = res.current_user.username;
                    }
                }
                if (res.studio_users) {
                    StudioApp.studioUsers = res.studio_users;
                }
                StudioApp.renderProjectSelectorOptions(list);
                if (res.php_version) {
                    var phpBadge = document.getElementById('php-version-info');
                    var userLabel = (StudioApp.currentUser && StudioApp.currentUser.username) ? (' | Benutzer: ' + StudioApp.currentUser.username) : '';
                    if (phpBadge) phpBadge.textContent = 'Laufzeit: PHP ' + res.php_version + ' | Zielcode: PHP 5.4 - 8.4+' + userLabel;
                }
            }
            if (cb) cb(list);
        });
    },

    renderProjectSelectorOptions: function (list) {
        var sel = document.getElementById('project-selector');
        if (!sel || !Array.isArray(list)) return;
        sel.innerHTML = '';
        for (var i = 0; i < list.length; i++) {
            var p = list[i];
            var opt = document.createElement('option');
            opt.value = p.filename;
            var roleTag = '';
            if (p.access_role === 'admin') {
                roleTag = ' [Admin-Zugriff | Owner: ' + (p.owner || 'admin') + ']';
            } else if (p.access_role === 'write') {
                roleTag = ' [Geteilt von ' + (p.owner || 'admin') + ': Bearbeiter]';
            } else if (p.access_role === 'read') {
                roleTag = ' [Geteilt von ' + (p.owner || 'admin') + ': Nur Lesen]';
            } else if (p.shared_count && p.shared_count > 0) {
                roleTag = ' [Team: +' + p.shared_count + ']';
            }
            opt.textContent = p.project_name + ' (' + p.filename + ')' + roleTag;
            if (p.filename === StudioApp.currentFilename) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        }
    },

    updateTopbarCollabAndGitBadges: function () {
        var accessBadge = document.getElementById('project-access-badge');
        var saveBtn = document.getElementById('btn-save-project');
        var role = this.currentAccessRole || 'owner';
        var owner = (this.project && this.project.owner) ? this.project.owner : 'admin';

        if (accessBadge) {
            if (role === 'owner') {
                accessBadge.innerHTML = '<i class="fa fa-user"></i> Eigentümer (' + this.escapeHtml(owner) + ')';
                accessBadge.style.background = 'rgba(0,166,90,0.45)';
            } else if (role === 'write') {
                accessBadge.innerHTML = '<i class="fa fa-users"></i> Team-Bearbeiter (Owner: ' + this.escapeHtml(owner) + ')';
                accessBadge.style.background = 'rgba(52,152,219,0.55)';
            } else if (role === 'read') {
                accessBadge.innerHTML = '<i class="fa fa-eye"></i> Betrachter / Nur Lesen (Owner: ' + this.escapeHtml(owner) + ')';
                accessBadge.style.background = 'rgba(243,156,18,0.65)';
            }
        }
        if (saveBtn) {
            saveBtn.disabled = (role === 'read');
            saveBtn.style.opacity = (role === 'read') ? '0.55' : '1';
        }

        // Anzahl geteilter Team-Mitglieder am Teilen-Button anzeigen
        var shareCountBadge = document.getElementById('project-share-count-badge');
        if (shareCountBadge) {
            var sharedMap = (this.project && this.project.shared_with && typeof this.project.shared_with === 'object') ? this.project.shared_with : {};
            var cnt = Object.keys(sharedMap).length;
            if (cnt > 0) {
                shareCountBadge.textContent = String(cnt);
                shareCountBadge.style.display = 'inline-block';
            } else {
                shareCountBadge.style.display = 'none';
            }
        }

        // Git Branch & Uncommitted-Indikator in der Topbar aktualisieren
        var gitBranchBadge = document.getElementById('git-branch-badge');
        var gitDirtyDot = document.getElementById('git-dirty-dot');
        if (gitBranchBadge && this.gitStatus) {
            gitBranchBadge.textContent = this.gitStatus.current_branch || 'main';
        }
        if (gitDirtyDot && this.gitStatus) {
            gitDirtyDot.style.display = this.gitStatus.has_uncommitted ? 'inline-block' : 'none';
        }
    },

    toPlainObject: function (val) {
        if (!val || typeof val !== 'object') {
            return {};
        }
        if (Array.isArray(val)) {
            var obj = {};
            for (var k in val) {
                if (Object.prototype.hasOwnProperty.call(val, k) && isNaN(Number(k))) {
                    obj[k] = val[k];
                }
            }
            return obj;
        }
        return val;
    },

    normalizeProjectStructure: function (proj) {
        proj = proj || this.project;
        if (!proj || typeof proj !== 'object') return proj;

        proj.tables = this.toPlainObject(proj.tables);
        var tKeys = Object.keys(proj.tables);
        for (var i = 0; i < tKeys.length; i++) {
            var tName = tKeys[i];
            if (proj.tables[tName] && typeof proj.tables[tName] === 'object') {
                proj.tables[tName].fields = this.toPlainObject(proj.tables[tName].fields);
                if (!Array.isArray(proj.tables[tName].master_details)) {
                    proj.tables[tName].master_details = [];
                }
                if (!Array.isArray(proj.tables[tName].charts)) {
                    proj.tables[tName].charts = [];
                }
            }
        }

        proj.custom_files = this.toPlainObject(proj.custom_files);

        if (!proj.security || typeof proj.security !== 'object' || Array.isArray(proj.security)) {
            proj.security = {};
        }
        proj.security.static_permissions = this.toPlainObject(proj.security.static_permissions);
        var spKeys = Object.keys(proj.security.static_permissions);
        for (var s = 0; s < spKeys.length; s++) {
            proj.security.static_permissions[spKeys[s]] = this.toPlainObject(proj.security.static_permissions[spKeys[s]]);
        }

        if (!proj.server_events || typeof proj.server_events !== 'object' || Array.isArray(proj.server_events)) {
            proj.server_events = { global: {}, tables: {} };
        }
        proj.server_events.global = this.toPlainObject(proj.server_events.global);
        proj.server_events.tables = this.toPlainObject(proj.server_events.tables);
        var seTbls = Object.keys(proj.server_events.tables);
        for (var se = 0; se < seTbls.length; se++) {
            proj.server_events.tables[seTbls[se]] = this.toPlainObject(proj.server_events.tables[seTbls[se]]);
        }

        if (!proj.client_scripts || typeof proj.client_scripts !== 'object' || Array.isArray(proj.client_scripts)) {
            proj.client_scripts = { global: {}, tables: {} };
        }
        proj.client_scripts.global = this.toPlainObject(proj.client_scripts.global);
        proj.client_scripts.tables = this.toPlainObject(proj.client_scripts.tables);
        var csTbls = Object.keys(proj.client_scripts.tables);
        for (var cs = 0; cs < csTbls.length; cs++) {
            proj.client_scripts.tables[csTbls[cs]] = this.toPlainObject(proj.client_scripts.tables[csTbls[cs]]);
        }

        if (!Array.isArray(proj.menu_items)) {
            proj.menu_items = [];
        }
        return proj;
    },

    loadProject: function (filename) {
        if (!filename) return;
        this.apiCall('load_project', { filename: filename }, function (res) {
            if (res.success && res.project) {
                StudioApp.currentFilename = res.filename || filename;
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                StudioApp.currentAccessRole = res.access_role || 'owner';
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                if (res.current_user) {
                    StudioApp.currentUser = res.current_user;
                }
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                var sel = document.getElementById('project-selector');
                if (sel) {
                    sel.value = StudioApp.currentFilename;
                }
                var tableKeys = Object.keys(StudioApp.project.tables || {});
                var cfKeys = Object.keys(StudioApp.project.custom_files || {});
                StudioApp.selectedTable = tableKeys.length > 0 ? tableKeys[0] : null;
                StudioApp.selectedField = null;
                StudioApp.selectedCustomFile = cfKeys.length > 0 ? cfKeys[0] : null;
                StudioApp.selectedMenuItem = null;
                StudioApp.selectedEventScope = '__global__';
                StudioApp.selectedEventKey = 'Page_Loading';
                StudioApp.refreshAllViews();
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.setStatus('Projekt "' + StudioApp.project.project_name + '" geladen (Eigentümer: ' + (StudioApp.project.owner || 'admin') + ').');
            } else {
                alert(res.error || 'Projekt konnte nicht geladen werden.');
            }
        });
    },

    createNewProject: function () {
        var name = prompt('Bitte Namen für das neue PHPMacherNG Projekt eingeben:', 'MeinNeuesProjekt');
        if (!name) return;
        this.apiCall('new_project', { project_name: name }, function (res) {
            if (res.success && res.project) {
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                StudioApp.currentFilename = name.toLowerCase().replace(/[^a-z0-9_-]/g, '_');
                StudioApp.currentAccessRole = 'owner';
                StudioApp.selectedTable = null;
                StudioApp.selectedField = null;
                StudioApp.selectedCustomFile = null;
                StudioApp.selectedMenuItem = null;
                StudioApp.selectedEventScope = '__global__';
                StudioApp.selectedEventKey = 'Page_Loading';
                StudioApp.refreshAllViews();
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.switchTab('database');
                StudioApp.saveCurrentProject();
            }
        });
    },

    saveCurrentProject: function () {
        if (!this.project) return;
        if (this.currentAccessRole === 'read') {
            alert('Schreibschutz: Sie haben für dieses geteilte Projekt nur Lese-Berechtigung ("Betrachter").');
            return;
        }
        this.normalizeProjectStructure(this.project);
        this.syncFormToProject();
        this.apiCall('save_project', {
            filename: this.currentFilename,
            project: this.project
        }, function (res) {
            if (res.success) {
                StudioApp.currentFilename = res.filename;
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.loadProjectList();
                StudioApp.setStatus('Projekt gespeichert (' + res.filename + '.pmp.json).');
            } else {
                alert('Fehler beim Speichern: ' + (res.error || 'Unbekannt'));
            }
        });
    },

    syncFormToProject: function () {
        if (!this.project) return;
        this.normalizeProjectStructure(this.project);
        // Database Tab Werte
        var dbHost = document.getElementById('db-host');
        if (dbHost) {
            this.project.database.host = dbHost.value.trim();
            this.project.database.port = parseInt(document.getElementById('db-port').value, 10) || 3306;
            this.project.database.user = document.getElementById('db-user').value;
            this.project.database.pass = document.getElementById('db-pass').value;
            this.project.database.dbname = document.getElementById('db-name').value.trim();
            this.project.database.charset = document.getElementById('db-charset').value.trim() || 'utf8';
        }
        // Settings Tab Werte
        var stTitle = document.getElementById('st-project-name');
        if (stTitle) {
            this.project.project_name = stTitle.value.trim() || 'MeinProjekt';
            this.project.settings.title = document.getElementById('st-title').value;
            var tplEl = document.getElementById('st-template');
            if (tplEl) {
                this.project.settings.ui_template = tplEl.value || 'adminlte2';
            }
            this.project.settings.theme_skin = document.getElementById('st-skin').value;
            var layoutEl = document.getElementById('st-layout');
            if (layoutEl) {
                this.project.settings.layout_style = layoutEl.value || 'sidebar-mini';
            }
            this.project.settings.records_per_page = parseInt(document.getElementById('st-recperpage').value, 10) || 20;
            this.project.settings.page_sizes = document.getElementById('st-pagesizes').value.trim();
            this.project.settings.date_format = document.getElementById('st-dateformat').value.trim();
            this.project.settings.currency_symbol = document.getElementById('st-currency').value.trim();
            this.project.settings.timezone = document.getElementById('st-timezone').value.trim();
            this.project.settings.output_folder = document.getElementById('st-output').value.trim();
            this.project.settings.default_table = document.getElementById('st-default-table').value;
            this.project.settings.footer_text = document.getElementById('st-footer').value;
            this.project.settings.confirm_action = document.getElementById('st-confirm').checked;
            this.project.settings.show_sql_debug = document.getElementById('st-debug').checked;
        }
        this.syncCustomFileForm();
        this.syncEventCodeEditor();
        this.syncMenuEditorForm();
        this.syncSecurityForm();
    },

    refreshAllViews: function () {
        if (!this.project) return;
        this.renderSidebarTree();
        this.renderDatabaseTab();
        this.renderTableTab();
        this.renderFieldsTab();
        this.renderCustomFilesTab();
        this.renderEventsTab();
        this.renderMenuTab();
        this.renderSecurityTab();
        this.renderSettingsTab();
        this.renderGenerateTab();
    },

    renderSidebarTree: function () {
        var container = document.getElementById('sidebar-tree');
        if (!container || !this.project) return;

        var tables = this.project.tables || {};
        var keys = Object.keys(tables);
        var dbName = (this.project.database && this.project.database.dbname) ? this.project.database.dbname : '(Keine DB)';

        var baseTableKeys = [];
        var viewKeys = [];
        for (var idxT = 0; idxT < keys.length; idxT++) {
            var kName = keys[idxT];
            if (tables[kName] && tables[kName].type === 'VIEW') {
                viewKeys.push(kName);
            } else {
                baseTableKeys.push(kName);
            }
        }

        var html = '<div class="pm-tree-section"><i class="fa fa-database"></i> ' + this.escapeHtml(dbName) + ' &ndash; Tabellen (' + baseTableKeys.length + ')</div>';

        if (keys.length === 0) {
            html += '<div style="padding:12px; color:#777; font-size:12px;">Noch keine Tabellen geladen. Verbinden Sie sich im Reiter <strong>Database</strong> mit Ihrer MySQL/MariaDB Datenbank.</div>';
        } else {
            for (var i = 0; i < baseTableKeys.length; i++) {
                var tName = baseTableKeys[i];
                var tCfg = tables[tName];
                var isSelected = (this.selectedTable === tName);
                var fieldCount = Object.keys(tCfg.fields || {}).length;

                html += '<div class="pm-tree-item tree-item-table ' + (isSelected ? 'active' : '') + '" data-table="' + this.escapeHtml(tName) + '">';
                html += '  <div class="pm-tree-item-left">';
                html += '    <input type="checkbox" class="tree-table-enable" data-table="' + this.escapeHtml(tName) + '" ' + (tCfg.enabled ? 'checked' : '') + ' title="Tabelle für Generierung aktivieren">';
                html += '    <i class="fa fa-table" style="color:#3c8dbc;"></i>';
                html += '    <span>' + this.escapeHtml(tName) + '</span>';
                html += '  </div>';
                html += '  <span class="pm-tree-badge">' + fieldCount + '</span>';
                html += '</div>';
            }
        }

        // Sektion 1B: Datenbank-Views (SQL Views)
        html += '<div class="pm-tree-section" style="margin-top:8px; border-top:1px solid #d2d6de; display:flex; justify-content:space-between; align-items:center;">';
        html += '  <span><i class="fa fa-eye" style="color:#8e44ad;"></i> Views (' + viewKeys.length + ')</span>';
        html += '  <button type="button" class="pm-btn pm-btn-default" style="padding:0 5px; height:18px; font-size:10px;" onclick="StudioApp.openViewBuilderModal(\'\');" title="Neue Datenbank-View mit visuellem SQL-Generator erstellen"><i class="fa fa-plus"></i> View</button>';
        html += '</div>';

        if (viewKeys.length === 0) {
            html += '<div style="padding:6px 12px; color:#888; font-size:11px;">Keine DB-Views vorhanden. Klicken Sie auf <strong>+ View</strong>, um eine SQL-View zu erstellen.</div>';
        } else {
            for (var v = 0; v < viewKeys.length; v++) {
                var vName = viewKeys[v];
                var vCfg = tables[vName];
                var isVSelected = (this.selectedTable === vName);
                var vFieldCount = Object.keys(vCfg.fields || {}).length;

                html += '<div class="pm-tree-item tree-item-table ' + (isVSelected ? 'active' : '') + '" data-table="' + this.escapeHtml(vName) + '">';
                html += '  <div class="pm-tree-item-left">';
                html += '    <input type="checkbox" class="tree-table-enable" data-table="' + this.escapeHtml(vName) + '" ' + (vCfg.enabled ? 'checked' : '') + ' title="View für Generierung aktivieren">';
                html += '    <i class="fa fa-eye" style="color:#8e44ad;"></i>';
                html += '    <span>' + this.escapeHtml(vName) + '</span>';
                html += '  </div>';
                html += '  <div style="display:flex; align-items:center; gap:3px;">';
                html += '    <button type="button" class="pm-btn pm-btn-default tree-view-edit-btn" data-view="' + this.escapeHtml(vName) + '" style="padding:0 4px; height:17px; font-size:10px;" title="SQL-Definition dieser View im View-Builder bearbeiten"><i class="fa fa-code"></i> SQL</button>';
                html += '    <button type="button" class="pm-btn pm-btn-danger tree-view-delete-btn" data-view="' + this.escapeHtml(vName) + '" style="padding:0 4px; height:17px; font-size:10px;" title="Datenbank-View löschen (DROP VIEW)"><i class="fa fa-trash"></i></button>';
                html += '    <span class="pm-tree-badge">' + vFieldCount + '</span>';
                html += '  </div>';
                html += '</div>';
            }
        }

        // Sektion 2: Eigene Dateien (PHPMacherNG Custom Files)
        var customFiles = this.project.custom_files || {};
        var cfKeys = Object.keys(customFiles);
        html += '<div class="pm-tree-section" style="margin-top:10px; border-top:1px solid #d2d6de; display:flex; justify-content:space-between; align-items:center;">';
        html += '  <span><i class="fa fa-file-code-o"></i> Custom Files (' + cfKeys.length + ')</span>';
        html += '  <button type="button" class="pm-btn pm-btn-default" style="padding:0 5px; height:18px; font-size:10px;" onclick="StudioApp.switchTab(\'customfiles\'); StudioApp.addCustomFile();" title="Neue eigene PHP-Datei hinzufügen"><i class="fa fa-plus"></i> Neu</button>';
        html += '</div>';

        for (var c = 0; c < cfKeys.length; c++) {
            var cfKey = cfKeys[c];
            var cfCfg = customFiles[cfKey];
            var isCfSelected = (this.selectedCustomFile === cfKey);
            html += '<div class="pm-tree-item tree-item-cf ' + (isCfSelected ? 'active' : '') + '" data-cf="' + this.escapeHtml(cfKey) + '">';
            html += '  <div class="pm-tree-item-left">';
            html += '    <input type="checkbox" class="tree-cf-enable" data-cf="' + this.escapeHtml(cfKey) + '" ' + (cfCfg.enabled ? 'checked' : '') + ' title="Custom File generieren">';
            html += '    <i class="fa ' + this.escapeHtml(cfCfg.icon || 'fa-file-code-o') + '" style="color:#f39c12;"></i>';
            html += '    <span>' + this.escapeHtml(cfCfg.filename || cfKey) + '</span>';
            html += '  </div>';
            html += '  <span class="pm-tree-badge">PHP</span>';
            html += '</div>';
        }

        container.innerHTML = html;

        // Klick-Events für Tabellen & Views binden
        var items = container.querySelectorAll('.tree-item-table');
        for (var j = 0; j < items.length; j++) {
            items[j].addEventListener('click', function (e) {
                if (e.target && (e.target.classList.contains('tree-table-enable') || e.target.closest('.tree-view-edit-btn') || e.target.closest('.tree-view-delete-btn'))) {
                    return;
                }
                var tbl = this.getAttribute('data-table');
                StudioApp.selectTable(tbl);
            });
        }

        var viewEditBtns = container.querySelectorAll('.tree-view-edit-btn');
        for (var vb = 0; vb < viewEditBtns.length; vb++) {
            viewEditBtns[vb].addEventListener('click', function (e) {
                e.stopPropagation();
                var vNameClick = this.getAttribute('data-view');
                StudioApp.openViewBuilderModal(vNameClick);
            });
        }

        var viewDelBtns = container.querySelectorAll('.tree-view-delete-btn');
        for (var vd = 0; vd < viewDelBtns.length; vd++) {
            viewDelBtns[vd].addEventListener('click', function (e) {
                e.stopPropagation();
                var vNameDel = this.getAttribute('data-view');
                StudioApp.deleteDatabaseView(vNameDel);
            });
        }

        var checks = container.querySelectorAll('.tree-table-enable');
        for (var k = 0; k < checks.length; k++) {
            checks[k].addEventListener('change', function (e) {
                var tbl = this.getAttribute('data-table');
                if (StudioApp.project.tables[tbl]) {
                    StudioApp.project.tables[tbl].enabled = this.checked;
                    StudioApp.renderTableTab();
                }
            });
        }

        // Klick-Events für Custom Files im Baum binden
        var cfItems = container.querySelectorAll('.tree-item-cf');
        for (var m = 0; m < cfItems.length; m++) {
            cfItems[m].addEventListener('click', function (e) {
                if (e.target && e.target.classList.contains('tree-cf-enable')) {
                    return;
                }
                var cf = this.getAttribute('data-cf');
                StudioApp.selectedCustomFile = cf;
                StudioApp.switchTab('customfiles');
                StudioApp.renderSidebarTree();
            });
        }

        var cfChecks = container.querySelectorAll('.tree-cf-enable');
        for (var n = 0; n < cfChecks.length; n++) {
            cfChecks[n].addEventListener('change', function () {
                var cf = this.getAttribute('data-cf');
                if (StudioApp.project.custom_files && StudioApp.project.custom_files[cf]) {
                    StudioApp.project.custom_files[cf].enabled = this.checked;
                    StudioApp.renderCustomFilesTab();
                }
            });
        }
    },

    selectTable: function (tableName) {
        this.selectedTable = tableName;
        var tCfg = this.project.tables[tableName];
        if (tCfg && tCfg.fields) {
            var fKeys = Object.keys(tCfg.fields);
            this.selectedField = fKeys.length > 0 ? fKeys[0] : null;
        } else {
            this.selectedField = null;
        }
        this.renderSidebarTree();
        this.renderTableTab();
        this.renderFieldsTab();

        var activeTab = document.querySelector('.pm-tab.active');
        if (activeTab && activeTab.getAttribute('data-tab') === 'events') {
            this.selectedEventScope = tableName;
            this.renderEventsTab();
        } else if (activeTab && activeTab.getAttribute('data-tab') === 'database') {
            this.switchTab('fields');
        }
    },

    renderDatabaseTab: function () {
        if (!this.project || !this.project.database) return;
        var db = this.project.database;
        document.getElementById('db-host').value = db.host || '127.0.0.1';
        document.getElementById('db-port').value = db.port || 3306;
        document.getElementById('db-user').value = db.user || 'root';
        document.getElementById('db-pass').value = db.pass || '';
        document.getElementById('db-name').value = db.dbname || '';
        document.getElementById('db-charset').value = db.charset || 'utf8';
    },

    fetchDatabasesList: function () {
        this.syncFormToProject();
        this.apiCall('list_databases', { database: this.project.database }, function (res) {
            var statusBox = document.getElementById('db-connection-msg');
            if (res.success) {
                var select = document.getElementById('db-quick-select');
                select.innerHTML = '<option value="">-- Gefundene Datenbank wählen (' + res.databases.length + ') --</option>';
                for (var i = 0; i < res.databases.length; i++) {
                    var d = res.databases[i];
                    var opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    if (d === StudioApp.project.database.dbname) opt.selected = true;
                    select.appendChild(opt);
                }
                select.style.display = 'inline-block';
                statusBox.innerHTML = '<span style="color:#00a65a; font-weight:600;"><i class="fa fa-check-circle"></i> Verbindung erfolgreich (Treiber: ' + res.driver.toUpperCase() + '). ' + res.databases.length + ' Datenbanken gefunden.</span>';
            } else {
                statusBox.innerHTML = '<span style="color:#dd4b39; font-weight:600;"><i class="fa fa-exclamation-triangle"></i> Verbindungsfehler: ' + StudioApp.escapeHtml(res.error) + '</span>';
            }
        });
    },

    connectAndSyncSchema: function () {
        this.syncFormToProject();
        if (!this.project.database.dbname) {
            alert('Bitte geben Sie einen Datenbanknamen ein oder installieren Sie die Demo-Datenbank per Klick.');
            return;
        }
        var statusBox = document.getElementById('db-connection-msg');
        statusBox.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Analysiere Tabellen, Spalten und Fremdschlüssel...';

        this.apiCall('connect_and_sync', { project: this.project }, function (res) {
            if (res.success) {
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                var tKeys = Object.keys(StudioApp.project.tables || {});
                if (!StudioApp.selectedTable && tKeys.length > 0) {
                    StudioApp.selectedTable = tKeys[0];
                }
                StudioApp.refreshAllViews();
                var rep = res.report;
                statusBox.innerHTML = '<span style="color:#00a65a; font-weight:600;"><i class="fa fa-check-circle"></i> Schema erfolgreich synchronisiert! (' +
                    tKeys.length + ' Tabellen gesamt, ' + rep.added_tables.length + ' neu hinzugefügt, ' + rep.added_fields.length + ' neue Felder).</span>';
            } else {
                statusBox.innerHTML = '<span style="color:#dd4b39; font-weight:600;"><i class="fa fa-times-circle"></i> Fehler: ' + StudioApp.escapeHtml(res.error) + '</span>';
            }
        });
    },

    installDemoDatabase: function () {
        this.syncFormToProject();
        if (!this.project.database.dbname) {
            this.project.database.dbname = 'phpmaker_demo';
            document.getElementById('db-name').value = 'phpmaker_demo';
        }
        var statusBox = document.getElementById('db-connection-msg');
        statusBox.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Erstelle und befülle Demo-Datenbank...';

        this.apiCall('install_demo_db', { database: this.project.database }, function (res) {
            if (res.success) {
                statusBox.innerHTML = '<span style="color:#00a65a; font-weight:600;"><i class="fa fa-check-circle"></i> ' + StudioApp.escapeHtml(res.message) + ' Synchronisiere Schema...</span>';
                StudioApp.connectAndSyncSchema();
            } else {
                statusBox.innerHTML = '<span style="color:#dd4b39; font-weight:600;"><i class="fa fa-times-circle"></i> Fehler: ' + StudioApp.escapeHtml(res.error) + '</span>';
            }
        });
    },

    renderTableTab: function () {
        var tbody = document.getElementById('table-setup-tbody');
        if (!tbody || !this.project) return;

        var tables = this.project.tables || {};
        var keys = Object.keys(tables);
        if (keys.length === 0) {
            tbody.innerHTML = '<tr><td colspan="14" style="text-align:center; padding:20px; color:#777;">Keine Tabellen im Projekt vorhanden.</td></tr>';
            this.renderMasterDetailInspector();
            return;
        }

        var html = '';
        for (var i = 0; i < keys.length; i++) {
            var tName = keys[i];
            var t = tables[tName];
            var isSel = (this.selectedTable === tName);
            var pkText = (t.primary_keys && t.primary_keys.length > 0) ? t.primary_keys.join(', ') : '-';
            var mdList = Array.isArray(t.master_details) ? t.master_details : [];
            var mdHtml = '<span style="color:#999; font-size:11px;">&ndash;</span>';
            if (mdList.length > 0) {
                var mdNames = [];
                for (var m = 0; m < mdList.length; m++) {
                    mdNames.push(this.escapeHtml(mdList[m].detail_table));
                }
                mdHtml = '<span style="background:#dbeafe; color:#1e40af; padding:2px 6px; border-radius:3px; font-size:11px; font-weight:600;"><i class="fa fa-sitemap"></i> ' + mdNames.join(', ') + '</span>';
            }

            var chartList = Array.isArray(t.charts) ? t.charts : [];
            var activeChartBadges = [];
            var chartMetaMap = {
                bar:      { label: 'Säulen',  icon: 'fa-bar-chart' },
                hbar:     { label: 'Balken',  icon: 'fa-align-left' },
                line:     { label: 'Linie',   icon: 'fa-line-chart' },
                area:     { label: 'Fläche',  icon: 'fa-area-chart' },
                pie:      { label: 'Torte',   icon: 'fa-pie-chart' },
                doughnut: { label: 'Donut',   icon: 'fa-circle-o-notch' },
                funnel:   { label: 'Trichter',icon: 'fa-filter' },
                radar:    { label: 'Radar',   icon: 'fa-compass' },
                gauge:    { label: 'Tacho',   icon: 'fa-tachometer' }
            };
            for (var chIdx = 0; chIdx < chartList.length; chIdx++) {
                if (chartList[chIdx].enabled === false) continue;
                var rawT = String(chartList[chIdx].type || 'bar').toLowerCase();
                var cm = chartMetaMap[rawT] || chartMetaMap.bar;
                activeChartBadges.push('<span style="background:#dcfce7; color:#166534; padding:1px 6px; border-radius:3px; font-size:10.5px; font-weight:600;"><i class="fa ' + cm.icon + '"></i> ' + cm.label + '</span>');
            }
            var chartsCellHtml = activeChartBadges.length > 0
                ? activeChartBadges.join(' ')
                : '<span style="color:#999; font-size:11px;">&ndash;</span>';

            var typeBadge = (t.type === 'VIEW')
                ? '<span style="background:#f3e8ff; color:#6b21a8; padding:1px 5px; border-radius:3px; font-size:10.5px; font-weight:bold; margin-left:4px;"><i class="fa fa-eye"></i> VIEW</span> ' +
                  '<button type="button" class="pm-btn pm-btn-default" style="padding:1px 6px; font-size:10.5px; margin-left:4px;" onclick="event.stopPropagation(); StudioApp.openViewBuilderModal(\'' + this.escapeHtml(tName) + '\');" title="SQL-Abfrage dieser View im visuellen Generator oder SQL-Editor bearbeiten"><i class="fa fa-code"></i> SQL bearbeiten</button>' +
                  '<button type="button" class="pm-btn pm-btn-danger" style="padding:1px 6px; font-size:10.5px; margin-left:4px;" onclick="event.stopPropagation(); StudioApp.deleteDatabaseView(\'' + this.escapeHtml(tName) + '\');" title="Datenbank-View aus Datenbank und Projekt löschen (DROP VIEW)"><i class="fa fa-trash"></i> Löschen</button>'
                : '<span style="color:#888; font-size:11px;">(TABLE)</span>';

            html += '<tr class="' + (isSel ? 'selected' : '') + '" data-table="' + this.escapeHtml(tName) + '">';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="enabled" ' + (t.enabled ? 'checked' : '') + '></td>';
            html += '  <td><strong>' + this.escapeHtml(tName) + '</strong> ' + typeBadge + '</td>';
            html += '  <td><input type="text" data-prop="caption" value="' + this.escapeHtml(t.caption || '') + '"></td>';
            html += '  <td><input type="text" data-prop="icon" value="' + this.escapeHtml(t.icon || 'fa-table') + '" style="width:95px;"></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="list_page" ' + (t.list_page ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="add_page" ' + (t.add_page ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="edit_page" ' + (t.edit_page ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="view_page" ' + (t.view_page ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="delete_page" ' + (t.delete_page ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="multi_delete" ' + (t.multi_delete ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-prop="multi_update" ' + (t.multi_update ? 'checked' : '') + '></td>';
            html += '  <td>' + chartsCellHtml + '</td>';
            html += '  <td>' + mdHtml + '</td>';
            html += '  <td><code>' + this.escapeHtml(pkText) + '</code></td>';
            html += '</tr>';
        }
        tbody.innerHTML = html;

        // Events für Änderungen im Table-Setup-Grid
        var rows = tbody.querySelectorAll('tr[data-table]');
        for (var r = 0; r < rows.length; r++) {
            rows[r].addEventListener('click', function (e) {
                var tbl = this.getAttribute('data-table');
                StudioApp.selectedTable = tbl;
                var allTr = tbody.querySelectorAll('tr');
                for (var idx = 0; idx < allTr.length; idx++) {
                    allTr[idx].classList.remove('selected');
                }
                this.classList.add('selected');
                StudioApp.renderSidebarTree();
                StudioApp.renderMasterDetailInspector();
            });

            var inputs = rows[r].querySelectorAll('input[data-prop]');
            for (var c = 0; c < inputs.length; c++) {
                inputs[c].addEventListener('change', function () {
                    var tr = this.closest('tr');
                    var tbl = tr.getAttribute('data-table');
                    var prop = this.getAttribute('data-prop');
                    if (StudioApp.project.tables[tbl]) {
                        StudioApp.project.tables[tbl][prop] = (this.type === 'checkbox') ? this.checked : this.value;
                        if (prop === 'enabled') {
                            StudioApp.renderSidebarTree();
                        }
                    }
                });
            }
        }

        this.renderMasterDetailInspector();
    },

    renderMasterDetailInspector: function () {
        var pane = document.getElementById('table-master-detail-inspector');
        if (!pane) return;

        if (!this.selectedTable || !this.project || !this.project.tables || !this.project.tables[this.selectedTable]) {
            pane.innerHTML = '<div style="color:#777; padding:8px;">Bitte wählen Sie oben eine Tabelle oder View aus, um Diagramme (Balken/Torte) und Master/Detail-Verknüpfungen zu verwalten.</div>';
            return;
        }

        var masterName = this.selectedTable;
        var masterTbl = this.project.tables[masterName];
        if (!Array.isArray(masterTbl.master_details)) {
            masterTbl.master_details = [];
        }
        if (!Array.isArray(masterTbl.charts)) {
            masterTbl.charts = [];
        }
        var mdList = masterTbl.master_details;
        var chartList = masterTbl.charts;
        var masterFields = Object.keys(masterTbl.fields || {});
        var allTables = Object.keys(this.project.tables);

        // 1. BEREICH: DIAGRAMME (9 DIAGRAMMARTEN FÜR DIESE TABELLE / VIEW)
        var html = '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px;">';
        html += '  <div>';
        html += '    <strong style="font-size:13px; color:#1e282c;"><i class="fa fa-bar-chart" style="color:#00a65a;"></i> Diagramme (9 Diagrammarten: Säulen, Balken, Linie, Fläche, Torte, Donut, Trichter, Radar &amp; Tacho) für <code>' + this.escapeHtml(masterName) + '</code></strong>';
        html += '    <span style="margin-left:8px; font-size:11.5px; color:#666;">Werden direkt auf der generierten Listen-Seite (<code>' + this.escapeHtml(masterName) + 'list.php</code>) live aus der Datenbank berechnet (inkl. Live-Umschalter &amp; PNG-Download).</span>';
        html += '  </div>';
        html += '  <div style="display:flex; flex-wrap:wrap; gap:5px;">';
        html += '    <button type="button" class="pm-btn pm-btn-primary" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'bar\');"><i class="fa fa-bar-chart"></i> + Säulen</button>';
        html += '    <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'hbar\');"><i class="fa fa-align-left"></i> + Horiz. Balken</button>';
        html += '    <button type="button" class="pm-btn pm-btn-success" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'line\');"><i class="fa fa-line-chart"></i> + Linie</button>';
        html += '    <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'area\');"><i class="fa fa-area-chart"></i> + Fläche</button>';
        html += '    <button type="button" class="pm-btn pm-btn-warning" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'pie\');"><i class="fa fa-pie-chart"></i> + Torte</button>';
        html += '    <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'doughnut\');"><i class="fa fa-circle-o-notch"></i> + Donut</button>';
        html += '    <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'funnel\');"><i class="fa fa-filter"></i> + Trichter</button>';
        html += '    <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'radar\');"><i class="fa fa-compass"></i> + Radar</button>';
        html += '    <button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.addTableChart(\'gauge\');"><i class="fa fa-tachometer"></i> + KPI-Tacho</button>';
        html += '  </div>';
        html += '</div>';

        if (chartList.length === 0) {
            html += '<div style="padding:8px 12px; margin-bottom:12px; background:#fff; border:1px dashed #cbd5e1; border-radius:3px; color:#64748b; font-size:11.5px;">';
            html += '  Noch kein Diagramm für <strong>' + this.escapeHtml(masterName) + '</strong> eingerichtet. Klicken Sie rechts auf eine der <strong>9 Diagrammarten</strong> (z. B. <em>+ Säulen</em>, <em>+ Linie</em>, <em>+ Torte</em>, <em>+ Donut</em>), um Datenbankwerte grafisch auszugeben.';
            html += '</div>';
        } else {
            html += '<table class="pm-grid-table" style="background:#fff; margin-bottom:14px; font-size:11.5px;">';
            html += '  <thead><tr>';
            html += '    <th style="width:40px; text-align:center;">Aktiv</th>';
            html += '    <th style="width:170px;">Diagrammtyp</th>';
            html += '    <th>Diagramm-Titel (Überschrift)</th>';
            html += '    <th style="width:170px;">Kategorie / X-Achse (Gruppierung)</th>';
            html += '    <th style="width:150px;">Berechnung (Aggregat)</th>';
            html += '    <th style="width:170px;">Wert-Spalte / Y-Achse</th>';
            html += '    <th style="width:110px;">Position</th>';
            html += '    <th style="width:130px;">Sortierung</th>';
            html += '    <th style="width:45px; text-align:center;"></th>';
            html += '  </tr></thead><tbody>';

            var chartTypeOptions = [
                { v: 'bar',      l: '📊 Säulendiagramm (Vertikal)' },
                { v: 'hbar',     l: '📏 Balkendiagramm (Horizontal)' },
                { v: 'line',     l: '📈 Liniendiagramm (Kurve/Trend)' },
                { v: 'area',     l: '⛰️ Flächendiagramm (Area)' },
                { v: 'pie',      l: '🥧 Tortendiagramm (Kreis)' },
                { v: 'doughnut', l: '🍩 Ring- / Donut-Diagramm' },
                { v: 'funnel',   l: '🔻 Trichter- / Funnel-Diagramm' },
                { v: 'radar',    l: '🕸️ Netz- / Radar-Diagramm' },
                { v: 'gauge',    l: '⏱️ KPI-Tacho / Gauge' }
            ];

            for (var c = 0; c < chartList.length; c++) {
                var ch = chartList[c];
                var isCount = (!ch.aggregate || ch.aggregate === 'COUNT');
                var curChType = String(ch.type || 'bar').toLowerCase();

                html += '<tr>';
                html += '  <td style="text-align:center;"><input type="checkbox" ' + (ch.enabled !== false ? 'checked' : '') + ' onchange="StudioApp.updateTableChart(' + c + ', \'enabled\', this.checked);"></td>';

                // Typ (alle 9 Diagrammarten)
                html += '  <td><select class="pm-select" style="font-weight:600;" onchange="StudioApp.updateTableChart(' + c + ', \'type\', this.value);">';
                for (var ct = 0; ct < chartTypeOptions.length; ct++) {
                    html += '<option value="' + chartTypeOptions[ct].v + '"' + (curChType === chartTypeOptions[ct].v ? ' selected' : '') + '>' + chartTypeOptions[ct].l + '</option>';
                }
                html += '  </select></td>';

                // Titel
                html += '  <td><input type="text" class="pm-input" value="' + this.escapeHtml(ch.title || '') + '" placeholder="z.B. Umsatz nach Kunde" onchange="StudioApp.updateTableChart(' + c + ', \'title\', this.value);"></td>';

                // X-Feld (Kategorie)
                html += '  <td><select class="pm-select" onchange="StudioApp.updateTableChart(' + c + ', \'x_field\', this.value);">';
                for (var xf = 0; xf < masterFields.length; xf++) {
                    var xfn = masterFields[xf];
                    var xfCap = (masterTbl.fields[xfn] && masterTbl.fields[xfn].caption) ? masterTbl.fields[xfn].caption : xfn;
                    html += '<option value="' + this.escapeHtml(xfn) + '"' + (ch.x_field === xfn ? ' selected' : '') + '>' + this.escapeHtml(xfn + ' (' + xfCap + ')') + '</option>';
                }
                html += '  </select></td>';

                // Aggregatfunktion
                var aggrOpts = [
                    { v: 'COUNT', l: 'COUNT(*) – Anzahl' },
                    { v: 'SUM',   l: 'SUM – Summe' },
                    { v: 'AVG',   l: 'AVG – Durchschnitt' },
                    { v: 'MAX',   l: 'MAX – Maximum' },
                    { v: 'MIN',   l: 'MIN – Minimum' },
                    { v: 'VALUE', l: 'Direktwert (ohne GROUP)' }
                ];
                html += '  <td><select class="pm-select" onchange="StudioApp.updateTableChart(' + c + ', \'aggregate\', this.value);">';
                for (var a = 0; a < aggrOpts.length; a++) {
                    html += '<option value="' + aggrOpts[a].v + '"' + ((ch.aggregate || 'COUNT') === aggrOpts[a].v ? ' selected' : '') + '>' + aggrOpts[a].l + '</option>';
                }
                html += '  </select></td>';

                // Y-Feld (Wert)
                html += '  <td><select class="pm-select"' + (isCount ? ' disabled title="Bei COUNT(*) werden automatisch die Datensätze gezählt"' : '') + ' onchange="StudioApp.updateTableChart(' + c + ', \'y_field\', this.value);">';
                if (isCount) {
                    html += '<option value="">(Anzahl Datensätze)</option>';
                } else {
                    for (var yf = 0; yf < masterFields.length; yf++) {
                        var yfn = masterFields[yf];
                        var yfCap = (masterTbl.fields[yfn] && masterTbl.fields[yfn].caption) ? masterTbl.fields[yfn].caption : yfn;
                        html += '<option value="' + this.escapeHtml(yfn) + '"' + (ch.y_field === yfn ? ' selected' : '') + '>' + this.escapeHtml(yfn + ' (' + yfCap + ')') + '</option>';
                    }
                }
                html += '  </select></td>';

                // Position (Oben über der Liste / Unten unter der Liste)
                html += '  <td><select class="pm-select" onchange="StudioApp.updateTableChart(' + c + ', \'position\', this.value);">';
                html += '    <option value="top"' + (ch.position !== 'bottom' ? ' selected' : '') + '>Oben (über Liste)</option>';
                html += '    <option value="bottom"' + (ch.position === 'bottom' ? ' selected' : '') + '>Unten (unter Liste)</option>';
                html += '  </select></td>';

                // Sortierung
                html += '  <td><select class="pm-select" onchange="StudioApp.updateTableChart(' + c + ', \'sort\', this.value);">';
                html += '    <option value="VALUE_DESC"' + ((!ch.sort || ch.sort === 'VALUE_DESC') ? ' selected' : '') + '>Wert absteigend</option>';
                html += '    <option value="VALUE_ASC"' + (ch.sort === 'VALUE_ASC' ? ' selected' : '') + '>Wert aufsteigend</option>';
                html += '    <option value="LABEL_ASC"' + (ch.sort === 'LABEL_ASC' ? ' selected' : '') + '>Kategorie A-Z</option>';
                html += '    <option value="LABEL_DESC"' + (ch.sort === 'LABEL_DESC' ? ' selected' : '') + '>Kategorie Z-A</option>';
                html += '  </select></td>';

                // Löschen
                html += '  <td style="text-align:center;"><button type="button" class="pm-btn pm-btn-default" style="color:#dd4b39; padding:2px 6px;" onclick="StudioApp.removeTableChart(' + c + ');" title="Diagramm entfernen"><i class="fa fa-trash"></i></button></td>';
                html += '</tr>';
            }

            html += '</tbody></table>';
        }

        // 2. BEREICH: MASTER/DETAIL-VERKNÜPFUNGEN
        html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; border-top:1px solid #e2e8f0; padding-top:8px;">';
        html += '  <div>';
        html += '    <strong style="font-size:13px; color:#1e282c;"><i class="fa fa-sitemap text-primary"></i> Master/Detail-Verknüpfungen für Master-Tabelle: <code>' + this.escapeHtml(masterName) + '</code></strong>';
        html += '    <span style="margin-left:10px; font-size:11.5px; color:#666;">Verknüpfte Detail-Tabellen lassen sich in der Listenansicht direkt per <code>[+]</code> als Unterliste aufklappen.</span>';
        html += '  </div>';
        html += '  <button type="button" class="pm-btn pm-btn-success" style="padding:2px 8px; font-size:11.5px;" onclick="StudioApp.addMasterDetailRelation();"><i class="fa fa-plus"></i> Detail-Tabelle (Unterliste) verknüpfen</button>';
        html += '</div>';

        if (mdList.length === 0) {
            html += '<div style="padding:8px 12px; background:#fff; border:1px dashed #cbd5e1; border-radius:3px; color:#64748b; font-size:11.5px;">';
            html += '  Keine Detail-Tabellen als Unterliste verknüpft.';
            html += '</div>';
        } else {
            html += '<table class="pm-grid-table" style="background:#fff; font-size:11.5px;">';
            html += '  <thead><tr>';
            html += '    <th style="width:220px;">Master-Feld (in <code>' + this.escapeHtml(masterName) + '</code>)</th>';
            html += '    <th style="width:40px; text-align:center;">=</th>';
            html += '    <th style="width:240px;">Detail-Tabelle (Unterliste)</th>';
            html += '    <th style="width:220px;">Fremdschlüssel-Feld (in Detail-Tabelle)</th>';
            html += '    <th style="text-align:center;">Aufklappbare Unterliste [+] (Preview Row)</th>';
            html += '    <th style="text-align:center;">Anzahl-Badge anzeigen (Detail Count)</th>';
            html += '    <th style="width:80px; text-align:center;">Aktion</th>';
            html += '  </tr></thead><tbody>';

            for (var i = 0; i < mdList.length; i++) {
                var rel = mdList[i];
                var dTableObj = this.project.tables[rel.detail_table] || null;
                var dFields = dTableObj && dTableObj.fields ? Object.keys(dTableObj.fields) : [];

                html += '<tr>';
                // Master Field Select
                html += '  <td><select class="pm-select" onchange="StudioApp.updateMasterDetailRelation(' + i + ', \'master_field\', this.value);">';
                for (var mf = 0; mf < masterFields.length; mf++) {
                    var mfn = masterFields[mf];
                    html += '<option value="' + this.escapeHtml(mfn) + '"' + (rel.master_field === mfn ? ' selected' : '') + '>' + this.escapeHtml(mfn) + '</option>';
                }
                html += '  </select></td>';

                html += '  <td style="text-align:center; font-weight:bold; color:#3c8dbc;">&rarr;</td>';

                // Detail Table Select
                html += '  <td><select class="pm-select" style="font-weight:600;" onchange="StudioApp.updateMasterDetailRelation(' + i + ', \'detail_table\', this.value);">';
                for (var dt = 0; dt < allTables.length; dt++) {
                    var dtn = allTables[dt];
                    if (dtn === masterName) continue;
                    var dtCap = this.project.tables[dtn].caption || dtn;
                    html += '<option value="' + this.escapeHtml(dtn) + '"' + (rel.detail_table === dtn ? ' selected' : '') + '>' + this.escapeHtml(dtCap) + ' (' + this.escapeHtml(dtn) + ')</option>';
                }
                html += '  </select></td>';

                // Detail Field Select
                html += '  <td><select class="pm-select" onchange="StudioApp.updateMasterDetailRelation(' + i + ', \'detail_field\', this.value);">';
                for (var df = 0; df < dFields.length; df++) {
                    var dfn = dFields[df];
                    html += '<option value="' + this.escapeHtml(dfn) + '"' + (rel.detail_field === dfn ? ' selected' : '') + '>' + this.escapeHtml(dfn) + '</option>';
                }
                html += '  </select></td>';

                // Preview Row Checkbox
                html += '  <td style="text-align:center;"><input type="checkbox" ' + (rel.preview_row !== false ? 'checked' : '') + ' onchange="StudioApp.updateMasterDetailRelation(' + i + ', \'preview_row\', this.checked);"></td>';

                // Detail Count Checkbox
                html += '  <td style="text-align:center;"><input type="checkbox" ' + (rel.detail_count !== false ? 'checked' : '') + ' onchange="StudioApp.updateMasterDetailRelation(' + i + ', \'detail_count\', this.checked);"></td>';

                // Delete Button
                html += '  <td style="text-align:center;"><button type="button" class="pm-btn pm-btn-default" style="color:#dd4b39; padding:2px 8px;" onclick="StudioApp.removeMasterDetailRelation(' + i + ');" title="Verknüpfung entfernen"><i class="fa fa-trash"></i></button></td>';
                html += '</tr>';
            }

            html += '</tbody></table>';
        }

        pane.innerHTML = html;
    },

    addTableChart: function (chartType) {
        if (!this.selectedTable || !this.project || !this.project.tables[this.selectedTable]) return;
        var tbl = this.project.tables[this.selectedTable];
        if (!Array.isArray(tbl.charts)) {
            tbl.charts = [];
        }
        var fKeys = Object.keys(tbl.fields || {});
        if (fKeys.length === 0) {
            alert('Diese Tabelle besitzt noch keine Spalten.');
            return;
        }

        // Sinnvolles Standard-Kategoriefeld (z.B. erstes Text-/Lookup-/Enum-Feld, das kein Primary Key ist) und Zahlenfeld suchen
        var defaultX = fKeys[0];
        var defaultY = '';
        for (var i = 0; i < fKeys.length; i++) {
            var fn = fKeys[i];
            var fm = tbl.fields[fn];
            if (!fm.is_primary && (fm.ew_type === 'STRING' || fm.lookup_table || fm.options)) {
                defaultX = fn;
                break;
            }
        }
        for (var j = 0; j < fKeys.length; j++) {
            var fn2 = fKeys[j];
            var fm2 = tbl.fields[fn2];
            if (!fm2.is_primary && (fm2.ew_type === 'NUMERIC' || fm2.view_tag === 'CURRENCY' || fm2.view_tag === 'NUMBER')) {
                defaultY = fn2;
                break;
            }
        }

        var defaultAggr = defaultY ? 'SUM' : 'COUNT';
        var xCap = (tbl.fields[defaultX] && tbl.fields[defaultX].caption) ? tbl.fields[defaultX].caption : defaultX;
        var yCap = (defaultY && tbl.fields[defaultY] && tbl.fields[defaultY].caption) ? tbl.fields[defaultY].caption : 'Anzahl';
        var defaultTitle = (defaultAggr === 'SUM' ? (yCap + ' nach ' + xCap) : ('Anzahl nach ' + xCap));

        var validTypes = ['bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge'];
        var cleanType = String(chartType || 'bar').toLowerCase();
        if (validTypes.indexOf(cleanType) === -1) {
            cleanType = 'bar';
        }

        tbl.charts.push({
            enabled: true,
            type: cleanType,
            title: defaultTitle,
            x_field: defaultX,
            aggregate: defaultAggr,
            y_field: defaultY || fKeys[0],
            position: 'top',
            sort: 'VALUE_DESC',
            limit: 15,
            height: 290
        });

        this.renderTableTab();
    },

    updateTableChart: function (index, prop, value) {
        if (!this.selectedTable || !this.project || !this.project.tables[this.selectedTable]) return;
        var tbl = this.project.tables[this.selectedTable];
        if (!Array.isArray(tbl.charts) || !tbl.charts[index]) return;
        var ch = tbl.charts[index];
        ch[prop] = value;

        if (prop === 'aggregate' && value !== 'COUNT' && !ch.y_field) {
            var fKeys = Object.keys(tbl.fields || {});
            ch.y_field = fKeys.length > 0 ? fKeys[0] : '';
        }

        this.renderTableTab();
    },

    removeTableChart: function (index) {
        if (!this.selectedTable || !this.project || !this.project.tables[this.selectedTable]) return;
        var tbl = this.project.tables[this.selectedTable];
        if (!Array.isArray(tbl.charts)) return;
        tbl.charts.splice(index, 1);
        this.renderTableTab();
    },

    addMasterDetailRelation: function () {
        if (!this.selectedTable || !this.project || !this.project.tables[this.selectedTable]) return;
        var masterName = this.selectedTable;
        var masterTbl = this.project.tables[masterName];
        if (!Array.isArray(masterTbl.master_details)) {
            masterTbl.master_details = [];
        }

        var masterFields = Object.keys(masterTbl.fields || {});
        var defaultMasterField = (masterTbl.primary_keys && masterTbl.primary_keys.length > 0)
            ? masterTbl.primary_keys[0]
            : (masterFields.length > 0 ? masterFields[0] : '');

        var allTables = Object.keys(this.project.tables);
        var defaultDetailTable = '';
        var defaultDetailField = '';

        // Suche intelligent nach einer Tabelle, die ein gleichnamiges Fremdschlüsselfeld besitzt
        for (var i = 0; i < allTables.length; i++) {
            var cand = allTables[i];
            if (cand === masterName) continue;
            var candFields = Object.keys(this.project.tables[cand].fields || {});
            if (!defaultDetailTable) {
                defaultDetailTable = cand;
                defaultDetailField = candFields.length > 0 ? candFields[0] : '';
            }
            if (candFields.indexOf(defaultMasterField) !== -1) {
                defaultDetailTable = cand;
                defaultDetailField = defaultMasterField;
                break;
            }
        }

        if (!defaultDetailTable) {
            alert('Es ist keine weitere Tabelle vorhanden, die als Detail-Tabelle verknüpft werden könnte.');
            return;
        }

        masterTbl.master_details.push({
            detail_table: defaultDetailTable,
            master_field: defaultMasterField,
            detail_field: defaultDetailField,
            preview_row: true,
            detail_count: true
        });

        this.renderTableTab();
    },

    updateMasterDetailRelation: function (index, prop, value) {
        if (!this.selectedTable || !this.project || !this.project.tables[this.selectedTable]) return;
        var masterTbl = this.project.tables[this.selectedTable];
        if (!Array.isArray(masterTbl.master_details) || !masterTbl.master_details[index]) return;

        var rel = masterTbl.master_details[index];
        rel[prop] = value;

        if (prop === 'detail_table' && this.project.tables[value]) {
            var dFields = Object.keys(this.project.tables[value].fields || {});
            if (dFields.indexOf(rel.master_field) !== -1) {
                rel.detail_field = rel.master_field;
            } else if (dFields.length > 0) {
                rel.detail_field = dFields[0];
            }
        }

        this.renderTableTab();
    },

    removeMasterDetailRelation: function (index) {
        if (!this.selectedTable || !this.project || !this.project.tables[this.selectedTable]) return;
        var masterTbl = this.project.tables[this.selectedTable];
        if (!Array.isArray(masterTbl.master_details)) return;
        masterTbl.master_details.splice(index, 1);
        this.renderTableTab();
    },

    renderFieldsTab: function () {
        var headerEl = document.getElementById('fields-current-table-title');
        var tbody = document.getElementById('fields-setup-tbody');
        if (!tbody || !this.project) return;

        if (!this.selectedTable || !this.project.tables[this.selectedTable]) {
            if (headerEl) headerEl.textContent = 'Keine Tabelle ausgewählt';
            tbody.innerHTML = '<tr><td colspan="13" style="text-align:center; padding:20px; color:#777;">Bitte wählen Sie links im Baum eine Tabelle aus.</td></tr>';
            this.renderFieldInspector();
            return;
        }

        var tCfg = this.project.tables[this.selectedTable];
        if (headerEl) {
            if (tCfg.type === 'VIEW') {
                headerEl.innerHTML = '<i class="fa fa-eye" style="color:#7c3aed;"></i> View: <strong>' + this.escapeHtml(tCfg.name) + '</strong> (' + this.escapeHtml(tCfg.caption) + ') ' +
                    '<button type="button" class="pm-btn pm-btn-default" style="padding:1px 6px; font-size:11px; margin-left:8px;" onclick="StudioApp.openViewBuilderModal(\'' + this.escapeHtml(tCfg.name) + '\');" title="SQL-Abfrage dieser View bearbeiten"><i class="fa fa-code"></i> SQL bearbeiten</button>' +
                    '<button type="button" class="pm-btn pm-btn-danger" style="padding:1px 6px; font-size:11px; margin-left:4px;" onclick="StudioApp.deleteDatabaseView(\'' + this.escapeHtml(tCfg.name) + '\');" title="View aus Datenbank und Projekt löschen"><i class="fa fa-trash"></i> View löschen</button>';
            } else {
                headerEl.innerHTML = '<i class="fa fa-table"></i> Tabelle: <strong>' + this.escapeHtml(tCfg.name) + '</strong> (' + this.escapeHtml(tCfg.caption) + ')';
            }
        }

        var fields = tCfg.fields || {};
        var fKeys = Object.keys(fields);
        if (!this.selectedField && fKeys.length > 0) {
            this.selectedField = fKeys[0];
        }

        var editTags = ['TEXT', 'TEXTAREA', 'PASSWORD', 'SELECT', 'RADIO', 'CHECKBOX', 'DATE', 'SIGNATURE', 'HIDDEN', 'READONLY'];
        var viewTags = ['FORMATTED TEXT', 'NUMBER', 'CURRENCY', 'DATE', 'DATETIME', 'BOOLEAN', 'EMAIL', 'LINK', 'IMAGE', 'SIGNATURE', 'HTML'];

        var html = '';
        for (var i = 0; i < fKeys.length; i++) {
            var fName = fKeys[i];
            var f = fields[fName];
            var isSel = (this.selectedField === fName);
            var keyIcon = f.is_primary ? '<i class="fa fa-key" style="color:#f39c12;" title="Primary Key"></i> ' : '';

            html += '<tr class="' + (isSel ? 'selected' : '') + '" data-field="' + this.escapeHtml(fName) + '">';
            html += '  <td>' + keyIcon + '<strong>' + this.escapeHtml(fName) + '</strong></td>';
            html += '  <td><span style="color:#555; font-family:Consolas,monospace; font-size:11px;">' + this.escapeHtml(f.sql_type) + '</span></td>';
            html += '  <td><input type="text" data-fprop="caption" value="' + this.escapeHtml(f.caption || '') + '"></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="list" ' + (f.list ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="add" ' + (f.add ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="edit" ' + (f.edit ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="view" ' + (f.view ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="multi_update" ' + (f.multi_update ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="search" ' + (f.search ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="extended_search" ' + (f.extended_search ? 'checked' : '') + '></td>';
            html += '  <td style="text-align:center;"><input type="checkbox" data-fprop="required" ' + (f.required ? 'checked' : '') + '></td>';

            // View Tag Dropdown
            html += '  <td><select data-fprop="view_tag">';
            for (var v = 0; v < viewTags.length; v++) {
                var vt = viewTags[v];
                html += '<option value="' + vt + '"' + (f.view_tag === vt ? ' selected' : '') + '>' + vt + '</option>';
            }
            html += '  </select></td>';

            // Edit Tag Dropdown
            html += '  <td><select data-fprop="edit_tag">';
            for (var e = 0; e < editTags.length; e++) {
                var et = editTags[e];
                html += '<option value="' + et + '"' + (f.edit_tag === et ? ' selected' : '') + '>' + et + '</option>';
            }
            html += '  </select></td>';
            html += '</tr>';
        }

        tbody.innerHTML = html;

        // Klick & Change Listener für Feldzeilen
        var rows = tbody.querySelectorAll('tr[data-field]');
        for (var r = 0; r < rows.length; r++) {
            rows[r].addEventListener('click', function () {
                var fld = this.getAttribute('data-field');
                StudioApp.selectedField = fld;
                var allTr = tbody.querySelectorAll('tr');
                for (var idx = 0; idx < allTr.length; idx++) {
                    allTr[idx].classList.remove('selected');
                }
                this.classList.add('selected');
                StudioApp.renderFieldInspector();
            });

            var controls = rows[r].querySelectorAll('[data-fprop]');
            for (var c = 0; c < controls.length; c++) {
                controls[c].addEventListener('change', function () {
                    var tr = this.closest('tr');
                    var fld = tr.getAttribute('data-field');
                    var prop = this.getAttribute('data-fprop');
                    var fObj = StudioApp.project.tables[StudioApp.selectedTable].fields[fld];
                    if (fObj) {
                        fObj[prop] = (this.type === 'checkbox') ? this.checked : this.value;
                        if (StudioApp.selectedField === fld) {
                            StudioApp.renderFieldInspector();
                        }
                    }
                });
            }
        }

        this.renderFieldInspector();
    },

    renderFieldInspector: function () {
        var pane = document.getElementById('field-inspector-content');
        if (!pane) return;

        if (!this.selectedTable || !this.selectedField || !this.project.tables[this.selectedTable] || !this.project.tables[this.selectedTable].fields[this.selectedField]) {
            pane.innerHTML = '<div style="color:#777;">Wählen Sie oben ein Feld aus, um Edit Tag, Validierung und Lookup-Tabellen zu konfigurieren.</div>';
            return;
        }

        var f = this.project.tables[this.selectedTable].fields[this.selectedField];
        var allTables = Object.keys(this.project.tables || {});

        var tblOptions = '<option value="">-- Keine Lookup-Tabelle (Statische Werte) --</option>';
        for (var i = 0; i < allTables.length; i++) {
            var tn = allTables[i];
            tblOptions += '<option value="' + this.escapeHtml(tn) + '"' + (f.lookup_table === tn ? ' selected' : '') + '>' + this.escapeHtml(tn) + '</option>';
        }

        var html = '<div class="pm-inspector-grid">';

        // Box 1: Edit Tag & Validierung
        html += '<div class="pm-inspector-box">';
        html += '  <h4><i class="fa fa-sliders"></i> Edit Tag & Validierung (' + this.escapeHtml(f.name) + ')</h4>';
        html += '  <div class="pm-form-group" style="margin-bottom:8px;">';
        html += '    <label>Validierungs-Regel (Validate)</label>';
        html += '    <select id="insp-validation" class="pm-select">';
        var vals = ['', 'Integer', 'Float', 'Email', 'Date'];
        for (var v = 0; v < vals.length; v++) {
            var label = vals[v] === '' ? '(Keine spezielle Prüfung)' : vals[v];
            html += '<option value="' + vals[v] + '"' + (f.validation === vals[v] ? ' selected' : '') + '>' + label + '</option>';
        }
        html += '    </select>';
        html += '  </div>';
        html += '  <div class="pm-form-group" style="margin-bottom:8px;">';
        html += '    <label>Standardwert (Default Value für Add-Page)</label>';
        html += '    <input type="text" id="insp-default" class="pm-input" value="' + this.escapeHtml(f.default_value || '') + '">';
        html += '  </div>';
        html += '  <div class="pm-form-group">';
        html += '    <label><input type="checkbox" id="insp-readonly-edit" ' + (f.read_only_edit ? 'checked' : '') + '> Schreibgeschützt beim Bearbeiten (Read-Only in Edit)</label>';
        html += '  </div>';
        html += '</div>';

        // Box 2: Statische Optionen (User Values)
        html += '<div class="pm-inspector-box">';
        html += '  <h4><i class="fa fa-list-ul"></i> Statische Auswahlwerte (User Values)</h4>';
        html += '  <div class="pm-form-group" style="margin-bottom:8px;">';
        html += '    <label>Format: <code>Wert1:Anzeige1,Wert2:Anzeige2</code> (für SELECT / RADIO / CHECKBOX)</label>';
        html += '    <input type="text" id="insp-options" class="pm-input" value="' + this.escapeHtml(f.options || '') + '" placeholder="z.B. 1:Aktiv,0:Inaktiv">';
        html += '  </div>';
        html += '  <div style="display:flex; gap:10px;">';
        html += '    <div class="pm-form-group" style="flex:1;"><label>Max. Zeichenlänge</label><input type="number" id="insp-maxlen" class="pm-input" value="' + (f.max_length || 0) + '"></div>';
        html += '    <div class="pm-form-group" style="flex:1;"><label>Textarea Zeilen</label><input type="number" id="insp-rows" class="pm-input" value="' + (f.textarea_rows || 4) + '"></div>';
        html += '  </div>';
        html += '</div>';

        // Box 3: Lookup Table (Fremdschlüssel / Dynamische Dropdowns)
        html += '<div class="pm-inspector-box">';
        html += '  <h4><i class="fa fa-link"></i> Lookup Table (Datenbank-Verknüpfung)</h4>';
        html += '  <div class="pm-form-group" style="margin-bottom:8px;">';
        html += '    <label>Quell-Tabelle (Lookup Table)</label>';
        html += '    <select id="insp-lookup-table" class="pm-select">' + tblOptions + '</select>';
        html += '  </div>';
        html += '  <div style="display:flex; gap:10px;">';
        html += '    <div class="pm-form-group" style="flex:1;"><label>Link Field (ID)</label><input type="text" id="insp-lookup-link" class="pm-input" value="' + this.escapeHtml(f.lookup_link_field || '') + '" placeholder="z.B. kategorie_id"></div>';
        html += '    <div class="pm-form-group" style="flex:1;"><label>Display Field (Text)</label><input type="text" id="insp-lookup-disp" class="pm-input" value="' + this.escapeHtml(f.lookup_display_field || '') + '" placeholder="z.B. bezeichnung"></div>';
        html += '  </div>';
        html += '</div>';

        html += '</div>';
        pane.innerHTML = html;

        // Bind Inspector Inputs
        var bindFieldProp = function (id, prop, isCheck, isInt) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function () {
                var targetField = StudioApp.project.tables[StudioApp.selectedTable].fields[StudioApp.selectedField];
                if (!targetField) return;
                if (isCheck) {
                    targetField[prop] = this.checked;
                } else if (isInt) {
                    targetField[prop] = parseInt(this.value, 10) || 0;
                } else {
                    targetField[prop] = this.value;
                }
            });
        };

        bindFieldProp('insp-validation', 'validation', false, false);
        bindFieldProp('insp-default', 'default_value', false, false);
        bindFieldProp('insp-readonly-edit', 'read_only_edit', true, false);
        bindFieldProp('insp-options', 'options', false, false);
        bindFieldProp('insp-maxlen', 'max_length', false, true);
        bindFieldProp('insp-rows', 'textarea_rows', false, true);
        bindFieldProp('insp-lookup-link', 'lookup_link_field', false, false);
        bindFieldProp('insp-lookup-disp', 'lookup_display_field', false, false);

        var lkTableEl = document.getElementById('insp-lookup-table');
        if (lkTableEl) {
            lkTableEl.addEventListener('change', function () {
                var targetField = StudioApp.project.tables[StudioApp.selectedTable].fields[StudioApp.selectedField];
                var chosenTbl = this.value;
                targetField.lookup_table = chosenTbl;
                if (chosenTbl && StudioApp.project.tables[chosenTbl]) {
                    var refT = StudioApp.project.tables[chosenTbl];
                    var pk = (refT.primary_keys && refT.primary_keys.length > 0) ? refT.primary_keys[0] : '';
                    targetField.lookup_link_field = pk;
                    targetField.lookup_display_field = refT.display_field || pk;
                    targetField.edit_tag = 'SELECT';
                    StudioApp.renderFieldsTab();
                }
            });
        }
    },

    renderCustomFilesTab: function () {
        if (!this.project) return;
        this.project.custom_files = this.toPlainObject(this.project.custom_files);
        var cFiles = this.project.custom_files;
        var keys = Object.keys(cFiles);
        if (!this.selectedCustomFile || !cFiles[this.selectedCustomFile]) {
            this.selectedCustomFile = keys.length > 0 ? keys[0] : null;
        }

        var sel = document.getElementById('cf-selector');
        var fnInput = document.getElementById('cf-filename');
        var capInput = document.getElementById('cf-caption');
        var iconInput = document.getElementById('cf-icon');
        var incCommonCb = document.getElementById('cf-include-common');
        var inMenuCb = document.getElementById('cf-in-menu');
        var enabledCb = document.getElementById('cf-enabled');
        var contentArea = document.getElementById('cf-content');

        if (!sel || !contentArea) return;

        sel.innerHTML = '';
        if (keys.length === 0) {
            sel.innerHTML = '<option value="">-- Keine Custom File in diesem Projekt vorhanden (bitte "+ Neue Datei" klicken) --</option>';
            fnInput.value = '';
            capInput.value = '';
            iconInput.value = '';
            contentArea.value = '';
            fnInput.disabled = true;
            capInput.disabled = true;
            iconInput.disabled = true;
            incCommonCb.disabled = true;
            inMenuCb.disabled = true;
            enabledCb.disabled = true;
            contentArea.disabled = true;
            return;
        }

        fnInput.disabled = false;
        capInput.disabled = false;
        iconInput.disabled = false;
        incCommonCb.disabled = false;
        inMenuCb.disabled = false;
        enabledCb.disabled = false;
        contentArea.disabled = false;

        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            var cf = cFiles[k];
            var opt = document.createElement('option');
            opt.value = k;
            opt.textContent = (cf.filename || k) + ' (' + (cf.caption || k) + ')';
            if (k === this.selectedCustomFile) opt.selected = true;
            sel.appendChild(opt);
        }

        var activeCf = cFiles[this.selectedCustomFile];
        if (activeCf) {
            fnInput.value = activeCf.filename || this.selectedCustomFile;
            capInput.value = activeCf.caption || '';
            iconInput.value = activeCf.icon || 'fa-file-code-o';
            incCommonCb.checked = (activeCf.include_common !== false);
            inMenuCb.checked = (activeCf.in_menu !== false);
            enabledCb.checked = (activeCf.enabled !== false);
            contentArea.value = activeCf.content || '';
        }
    },

    selectCustomFile: function (cfKey) {
        this.syncCustomFileForm();
        this.selectedCustomFile = cfKey;
        this.renderCustomFilesTab();
        this.renderSidebarTree();
    },

    syncCustomFileForm: function () {
        if (!this.project || !this.project.custom_files || !this.selectedCustomFile) return;
        this.project.custom_files = this.toPlainObject(this.project.custom_files);
        var cf = this.project.custom_files[this.selectedCustomFile];
        if (!cf) return;

        var fnInput = document.getElementById('cf-filename');
        var capInput = document.getElementById('cf-caption');
        var iconInput = document.getElementById('cf-icon');
        var incCommonCb = document.getElementById('cf-include-common');
        var inMenuCb = document.getElementById('cf-in-menu');
        var enabledCb = document.getElementById('cf-enabled');
        var contentArea = document.getElementById('cf-content');

        if (!fnInput || !contentArea) return;

        var newFn = fnInput.value.trim();
        if (newFn !== '') {
            if (newFn.toLowerCase().slice(-4) !== '.php') {
                newFn += '.php';
            }
            cf.filename = newFn;
        }
        cf.caption = capInput.value;
        cf.icon = iconInput.value.trim() || 'fa-file-code-o';
        cf.include_common = incCommonCb.checked;
        cf.in_menu = inMenuCb.checked;
        cf.enabled = enabledCb.checked;
        cf.content = contentArea.value;

        // Zugehörigen Menü-Eintrag in menu_items automatisch aktualisieren
        if (Array.isArray(this.project.menu_items)) {
            for (var m = 0; m < this.project.menu_items.length; m++) {
                var mi = this.project.menu_items[m];
                if (mi && mi.source_type === 'custom_file' && mi.source_name === this.selectedCustomFile) {
                    mi.text = cf.caption || cf.filename || this.selectedCustomFile;
                    mi.url = cf.filename || this.selectedCustomFile;
                    mi.icon = cf.icon || 'fa-file-code-o';
                    mi.enabled = (cf.enabled !== false && cf.in_menu !== false);
                }
            }
        }
    },

    addCustomFile: function () {
        if (!this.project) return;
        this.normalizeProjectStructure(this.project);
        this.syncCustomFileForm();
        var fileName = prompt('Dateiname für die neue eigene Datei (z.B. umsatzbericht.php oder api_export.php):', 'eigene_seite.php');
        if (!fileName || !fileName.trim()) return;
        fileName = fileName.trim().replace(/[^a-zA-Z0-9_.-]/g, '_');
        if (fileName.toLowerCase().slice(-4) !== '.php') {
            fileName += '.php';
        }
        var caption = prompt('Anzeigename im Menü (Caption):', 'Eigene Auswertung');
        if (caption === null) return;

        this.project.custom_files = this.toPlainObject(this.project.custom_files);
        var firstTable = 'kunden';
        var tKeys = Object.keys(this.project.tables || {});
        if (tKeys.length > 0) firstTable = tKeys[0];

        var starterCode = "<?php\n" +
            "// Eigene PHPMacherNG Datei: " + fileName + "\n" +
            "// Zugriff auf Daten über DbHelper() oder ew_Execute* Funktionen:\n" +
            "$db = &DbHelper();\n" +
            "?>\n" +
            "<div class=\"box box-primary\">\n" +
            "    <div class=\"box-header with-border\">\n" +
            "        <h3 class=\"box-title\"><i class=\"fa fa-table\"></i> " + (caption || fileName) + "</h3>\n" +
            "    </div>\n" +
            "    <div class=\"box-body\">\n" +
            "        <?php\n" +
            "        // Beispiel: SQL-Abfrage direkt als formatierte HTML-Tabelle ausgeben\n" +
            "        echo $db->ExecuteHtml(\"SELECT * FROM `" + firstTable + "` LIMIT 10\", array('fieldcaption' => true));\n" +
            "        ?>\n" +
            "    </div>\n" +
            "</div>\n";

        this.project.custom_files[fileName] = {
            filename: fileName,
            caption: caption || fileName,
            enabled: true,
            include_common: true,
            in_menu: true,
            icon: 'fa-file-code-o',
            content: starterCode
        };

        var sec = this.ensureSecurityObject();
        if (sec) {
            sec.static_permissions = this.toPlainObject(sec.static_permissions);
            sec.static_permissions[fileName] = { '-2': 0, '-1': 127, '0': 104, '1': 104, '2': 104 };
        }

        // Neuen Menüeintrag automatisch in menu_items synchronisieren
        this.ensureMenuItems(false);

        this.selectedCustomFile = fileName;
        this.renderSidebarTree();
        this.renderCustomFilesTab();
        this.renderMenuTab();
    },

    deleteCurrentCustomFile: function () {
        if (!this.project || !this.project.custom_files || !this.selectedCustomFile) return;
        this.project.custom_files = this.toPlainObject(this.project.custom_files);
        if (!confirm('Möchten Sie die Custom File "' + this.selectedCustomFile + '" wirklich aus dem Projekt entfernen?')) {
            return;
        }
        var removedKey = this.selectedCustomFile;
        delete this.project.custom_files[removedKey];
        if (Array.isArray(this.project.menu_items)) {
            var newMenu = [];
            for (var i = 0; i < this.project.menu_items.length; i++) {
                var mi = this.project.menu_items[i];
                if (mi && mi.source_type === 'custom_file' && mi.source_name === removedKey) {
                    continue;
                }
                newMenu.push(mi);
            }
            this.project.menu_items = newMenu;
        }
        var keys = Object.keys(this.project.custom_files);
        this.selectedCustomFile = keys.length > 0 ? keys[0] : null;
        this.renderSidebarTree();
        this.renderCustomFilesTab();
        this.renderMenuTab();
    },

    insertCustomFileSnippet: function (type) {
        var area = document.getElementById('cf-content');
        if (!area) return;

        var firstTable = 'kunden';
        var tKeys = Object.keys((this.project && this.project.tables) ? this.project.tables : {});
        if (tKeys.length > 0) firstTable = tKeys[0];

        var snippet = '';
        if (type === 'execute_html') {
            snippet = "\n<?php\n" +
                "// SQL-Abfrage direkt als Bootstrap-Tabelle ausgeben:\n" +
                "$sql = \"SELECT * FROM `" + firstTable + "` LIMIT 20\";\n" +
                "echo DbHelper()->ExecuteHtml($sql, array('fieldcaption' => true));\n" +
                "?>\n";
        } else if (type === 'chart_bar') {
            snippet = "\n<?php\n" +
                "// Säulen- oder horizontales Balkendiagramm ('bar' oder 'hbar') aus einer SQL-Abfrage erzeugen:\n" +
                "$sqlBar = \"SELECT * FROM `" + firstTable + "` LIMIT 10\";\n" +
                "echo DbHelper()->ExecuteChart($sqlBar, array(\n" +
                "    'type'   => 'bar', // 'bar' (Säulen) oder 'hbar' (Horizontale Balken)\n" +
                "    'title'  => 'Säulendiagramm: " + firstTable + "',\n" +
                "    'height' => 290\n" +
                "));\n" +
                "?>\n";
        } else if (type === 'chart_line') {
            snippet = "\n<?php\n" +
                "// Linien- oder Flächendiagramm ('line' oder 'area') aus einer SQL-Abfrage erzeugen:\n" +
                "$sqlLine = \"SELECT * FROM `" + firstTable + "` LIMIT 12\";\n" +
                "echo DbHelper()->ExecuteChart($sqlLine, array(\n" +
                "    'type'   => 'area', // 'line' (Linie) oder 'area' (Gefüllte Fläche)\n" +
                "    'title'  => 'Verlauf / Trend: " + firstTable + "',\n" +
                "    'height' => 290\n" +
                "));\n" +
                "?>\n";
        } else if (type === 'chart_pie') {
            snippet = "\n<?php\n" +
                "// Torten- oder Donut-Diagramm ('pie' oder 'doughnut') aus einer SQL-Abfrage erzeugen:\n" +
                "$sqlPie = \"SELECT * FROM `" + firstTable + "` LIMIT 8\";\n" +
                "echo DbHelper()->ExecuteChart($sqlPie, array(\n" +
                "    'type'   => 'doughnut', // 'pie' (Torte) oder 'doughnut' (Donut mit Gesamtsumme)\n" +
                "    'title'  => 'Anteile: " + firstTable + "',\n" +
                "    'height' => 290\n" +
                "));\n" +
                "?>\n";
        } else if (type === 'chart_gauge') {
            snippet = "\n<?php\n" +
                "// Trichter- ('funnel'), Radar- ('radar') oder KPI-Tacho-Diagramm ('gauge') erzeugen:\n" +
                "$sqlSpecial = \"SELECT * FROM `" + firstTable + "` LIMIT 8\";\n" +
                "echo DbHelper()->ExecuteChart($sqlSpecial, array(\n" +
                "    'type'   => 'funnel', // 'funnel' (Trichter), 'radar' (Netz) oder 'gauge' (KPI-Tacho)\n" +
                "    'title'  => 'Auswertung: " + firstTable + "',\n" +
                "    'height' => 290\n" +
                "));\n" +
                "?>\n";
        } else if (type === 'execute_scalar') {
            snippet = "\n<?php\n" +
                "// Einzelwert (Skalar) abfragen:\n" +
                "$anzahl = (int)ew_ExecuteScalar(\"SELECT COUNT(*) FROM `" + firstTable + "`\");\n" +
                "echo '<p>Anzahl Datensätze: <strong>' . $anzahl . '</strong></p>';\n" +
                "?>\n";
        } else if (type === 'execute_rows') {
            snippet = "\n<?php\n" +
                "// Mehrere Datensätze als assoziatives Array abrufen:\n" +
                "$rows = ew_ExecuteRows(\"SELECT * FROM `" + firstTable + "` LIMIT 10\");\n" +
                "if (is_array($rows)) {\n" +
                "    foreach ($rows as $row) {\n" +
                "        // Zugriff z.B. über $row['spaltenname']\n" +
                "    }\n" +
                "}\n" +
                "?>\n";
        } else if (type === 'execute_row') {
            snippet = "\n<?php\n" +
                "// Einzelne Zeile als assoziatives Array mit Prepared-Parameter abrufen:\n" +
                "$row = ew_ExecuteRow(\"SELECT * FROM `" + firstTable + "` LIMIT 1\", array());\n" +
                "if ($row) {\n" +
                "    echo DbHelper()->ExecuteHtml(\"SELECT * FROM `" + firstTable + "` LIMIT 1\", array('horizontal' => true));\n" +
                "}\n" +
                "?>\n";
        } else if (type === 'execute_update') {
            snippet = "\n<?php\n" +
                "// Schreibende SQL-Operation (INSERT / UPDATE / DELETE) mit Prepared Statements:\n" +
                "// ew_Execute(\"UPDATE `" + firstTable + "` SET `aktiv` = ? WHERE `id` = ?\", array(1, 5));\n" +
                "?>\n";
        } else if (type === 'execute_json') {
            snippet = "\n<?php\n" +
                "// SQL-Abfrage als JSON ausgeben (ideal bei deaktiviertem 'Include common files'):\n" +
                "// header('Content-Type: application/json; charset=utf-8');\n" +
                "echo ew_ExecuteJson(\"SELECT * FROM `" + firstTable + "` LIMIT 20\");\n" +
                "?>\n";
        } else if (type === 'current_user') {
            snippet = "\n<?php\n" +
                "// Aktuelle Benutzer- & Rolleninformationen (PHPMacherNG Security):\n" +
                "echo 'Benutzer: ' . ew_HtmlEncode(CurrentUserName()) . ' (ID: ' . ew_HtmlEncode(CurrentUserID()) . ', Level: ' . (int)CurrentUserLevel() . ')';\n" +
                "?>\n";
        }

        var start = area.selectionStart || area.value.length;
        var end = area.selectionEnd || area.value.length;
        area.value = area.value.substring(0, start) + snippet + area.value.substring(end);
        area.focus();
        this.syncCustomFileForm();
    },

    // ========================================================================
    // PHPMacherNG MENU EDITOR (Mehrstufige Menüs, Ebenen & eigene Einträge)
    // ========================================================================

    ensureMenuItems: function (forceRebuild) {
        if (!this.project) return [];
        this.normalizeProjectStructure(this.project);
        var cFiles = this.project.custom_files || {};
        var cfKeys = Object.keys(cFiles);
        var tables = this.project.tables || {};
        var tKeys = Object.keys(tables);

        if (!forceRebuild && Array.isArray(this.project.menu_items) && this.project.menu_items.length > 0) {
            var existing = this.project.menu_items;
            var foundCf = {};
            var foundTbl = {};
            for (var e = 0; e < existing.length; e++) {
                var it = existing[e];
                if (!it) continue;
                if (it.source_type === 'custom_file' && it.source_name) {
                    foundCf[it.source_name] = true;
                } else if (it.source_type === 'table' && it.source_name) {
                    foundTbl[it.source_name] = true;
                }
            }
            for (var cIdx = 0; cIdx < cfKeys.length; cIdx++) {
                var cKey = cfKeys[cIdx];
                if (!foundCf[cKey]) {
                    var cObj = cFiles[cKey];
                    var cFn = (cObj.filename || cKey).trim();
                    if (cFn.toLowerCase().slice(-4) !== '.php') cFn += '.php';
                    existing.push({
                        id: 'cf_' + cKey.replace(/[^a-zA-Z0-9_]/g, '_'),
                        parent_id: '',
                        text: cObj.caption || cFn,
                        url: cFn,
                        icon: cObj.icon || 'fa-file-code-o',
                        target: '_self',
                        is_header: false,
                        allow_anon: false,
                        enabled: (cObj.enabled !== false && cObj.in_menu !== false),
                        source_type: 'custom_file',
                        source_name: cKey
                    });
                }
            }
            for (var tIdx = 0; tIdx < tKeys.length; tIdx++) {
                var tblKey = tKeys[tIdx];
                if (!foundTbl[tblKey]) {
                    var tblObj = tables[tblKey];
                    var tSlug = tblKey.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                    existing.push({
                        id: 'tbl_' + tSlug,
                        parent_id: '',
                        text: tblObj.caption || tblKey,
                        url: tSlug + 'list.php',
                        icon: tblObj.icon || (tblObj.type === 'VIEW' ? 'fa-eye' : 'fa-table'),
                        target: '_self',
                        is_header: false,
                        allow_anon: false,
                        enabled: (tblObj.enabled !== false && tblObj.list_page !== false),
                        source_type: 'table',
                        source_name: tblKey
                    });
                }
            }
            return existing;
        }

        var items = [];
        for (var c = 0; c < cfKeys.length; c++) {
            var cfKey = cfKeys[c];
            var cf = cFiles[cfKey];
            var fn = (cf.filename || cfKey).trim();
            if (fn.toLowerCase().slice(-4) !== '.php') fn += '.php';
            items.push({
                id: 'cf_' + cfKey.replace(/[^a-zA-Z0-9_]/g, '_'),
                parent_id: '',
                text: cf.caption || fn,
                url: fn,
                icon: cf.icon || 'fa-dashboard',
                target: '_self',
                is_header: false,
                allow_anon: false,
                enabled: (cf.enabled !== false && cf.in_menu !== false),
                source_type: 'custom_file',
                source_name: cfKey
            });
        }

        var hasDemo = (tables['kunden'] && tables['bestellungen'] && tables['produkte']);
        if (hasDemo) {
            items.push({
                id: 'grp_crm_shop',
                parent_id: '',
                text: 'CRM & Shop Betrieb',
                url: '#',
                icon: 'fa-briefcase',
                target: '_self',
                is_header: false,
                allow_anon: false,
                enabled: true,
                source_type: 'custom',
                source_name: ''
            });
            items.push({
                id: 'grp_stammdaten',
                parent_id: 'grp_crm_shop',
                text: 'Katalog & Stammdaten',
                url: '#',
                icon: 'fa-folder-open-o',
                target: '_self',
                is_header: false,
                allow_anon: false,
                enabled: true,
                source_type: 'custom',
                source_name: ''
            });
        }

        var secTables = ['benutzer', 'userlevels', 'userlevelpermissions'];
        var hasSec = false;
        for (var s = 0; s < secTables.length; s++) {
            if (tables[secTables[s]]) {
                hasSec = true;
                break;
            }
        }
        if (hasSec) {
            items.push({
                id: 'grp_system_sec',
                parent_id: '',
                text: 'Benutzer & Rechte',
                url: '#',
                icon: 'fa-users',
                target: '_self',
                is_header: false,
                allow_anon: false,
                enabled: true,
                source_type: 'custom',
                source_name: ''
            });
        }

        for (var i = 0; i < tKeys.length; i++) {
            var tName = tKeys[i];
            var tCfg = tables[tName];
            var slug = tName.toLowerCase().replace(/[^a-z0-9_]/g, '_');
            var pid = '';
            if (hasDemo) {
                if (tName === 'kategorien' || tName === 'produkte') pid = 'grp_stammdaten';
                else if (tName === 'kunden' || tName === 'bestellungen' || tName === 'v_kunden_umsatz') pid = 'grp_crm_shop';
            }
            if (hasSec && secTables.indexOf(tName) !== -1) {
                pid = 'grp_system_sec';
            }
            items.push({
                id: 'tbl_' + slug,
                parent_id: pid,
                text: tCfg.caption || tName,
                url: slug + 'list.php',
                icon: tCfg.icon || (tCfg.type === 'VIEW' ? 'fa-eye' : 'fa-table'),
                target: '_self',
                is_header: false,
                allow_anon: false,
                enabled: (tCfg.enabled !== false && tCfg.list_page !== false),
                source_type: 'table',
                source_name: tName
            });
        }

        this.project.menu_items = items;
        return items;
    },

    getFlattenedMenuTree: function () {
        var items = this.ensureMenuItems(false);
        var byId = {};
        for (var i = 0; i < items.length; i++) {
            byId[items[i].id] = items[i];
        }

        var result = [];
        var visited = {};

        var visitChildren = function (parentId, depth) {
            for (var j = 0; j < items.length; j++) {
                var it = items[j];
                var pid = it.parent_id || '';
                if (pid !== '' && !byId[pid]) {
                    pid = '';
                }
                if (pid === parentId && !visited[it.id]) {
                    visited[it.id] = true;
                    result.push({ item: it, depth: depth, index: j });
                    visitChildren(it.id, depth + 1);
                }
            }
        };

        visitChildren('', 0);

        // Falls verwaiste oder zyklische Einträge übrig blieben, auf Root-Ebene anhängen
        for (var k = 0; k < items.length; k++) {
            if (!visited[items[k].id]) {
                items[k].parent_id = '';
                result.push({ item: items[k], depth: 0, index: k });
            }
        }
        return result;
    },

    isDescendantMenuItem: function (candidateChildId, targetId) {
        if (!candidateChildId || !targetId) return false;
        if (candidateChildId === targetId) return true;
        var items = this.ensureMenuItems(false);
        var byId = {};
        for (var i = 0; i < items.length; i++) {
            byId[items[i].id] = items[i];
        }
        var cur = byId[candidateChildId];
        var guard = 0;
        while (cur && cur.parent_id && guard < 50) {
            if (cur.parent_id === targetId) return true;
            cur = byId[cur.parent_id];
            guard++;
        }
        return false;
    },

    renderMenuTab: function () {
        var items = this.ensureMenuItems(false);
        var treeContainer = document.getElementById('menu-editor-tree-list');
        if (!treeContainer) return;

        if (!this.selectedMenuItem && items.length > 0) {
            this.selectedMenuItem = items[0].id;
        }

        var flat = this.getFlattenedMenuTree();
        var html = '';

        if (flat.length === 0) {
            html = '<div style="padding:20px; text-align:center; color:#777;">Keine Menüeinträge vorhanden. Klicken Sie oben auf <strong>Neuer Menüeintrag</strong> oder <strong>Standard wiederherstellen</strong>.</div>';
        } else {
            for (var i = 0; i < flat.length; i++) {
                var row = flat[i];
                var it = row.item;
                var depth = row.depth;
                var isSel = (this.selectedMenuItem === it.id);
                var indentPx = 10 + (depth * 24);

                var badgeText = 'Link';
                var badgeColor = '#e2e8f0';
                var badgeTextColor = '#334155';
                if (it.is_header) {
                    badgeText = 'HEADER';
                    badgeColor = '#fef3c7';
                    badgeTextColor = '#92400e';
                } else if (it.source_type === 'table') {
                    badgeText = 'Tabelle: ' + it.source_name;
                    badgeColor = '#dbeafe';
                    badgeTextColor = '#1e40af';
                } else if (it.source_type === 'custom_file') {
                    badgeText = 'Custom File';
                    badgeColor = '#fce7f3';
                    badgeTextColor = '#9d174d';
                } else if (it.url === '#' || it.url === '') {
                    badgeText = 'Ordner (Ebene ' + (depth + 1) + ')';
                    badgeColor = '#dcfce7';
                    badgeTextColor = '#166534';
                }

                html += '<div class="me-tree-row" data-id="' + this.escapeHtml(it.id) + '" style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px 6px ' + indentPx + 'px; border-bottom:1px solid #f0f2f5; cursor:pointer; background:' + (isSel ? '#e6f2ff' : (it.is_header ? '#fafafa' : '#fff')) + '; border-left:' + (isSel ? '3px solid #3c8dbc' : '3px solid transparent') + ';">';
                html += '  <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">';
                html += '    <input type="checkbox" class="me-row-enable" data-id="' + this.escapeHtml(it.id) + '" ' + (it.enabled !== false ? 'checked' : '') + ' title="Im Menü sichtbar">';
                if (depth > 0) {
                    html += '    <span style="color:#94a3b8; font-family:monospace;">&#9492;&#9472;</span>';
                }
                if (it.is_header) {
                    html += '    <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:#555;">[' + this.escapeHtml(it.text) + ']</span>';
                } else {
                    html += '    <i class="fa ' + this.escapeHtml(it.icon || 'fa-circle-o') + '" style="color:' + (isSel ? '#005a9e' : '#3c8dbc') + '; width:16px; text-align:center;"></i>';
                    html += '    <span style="font-weight:' + (depth === 0 ? '600' : 'normal') + '; color:' + (it.enabled === false ? '#999; text-decoration:line-through' : '#222') + ';">' + this.escapeHtml(it.text) + '</span>';
                    html += '    <code style="font-size:10.5px; color:#666; background:transparent;">' + this.escapeHtml(it.url) + '</code>';
                }
                html += '  </div>';
                html += '  <span style="font-size:10.5px; padding:1px 6px; border-radius:3px; background:' + badgeColor + '; color:' + badgeTextColor + '; white-space:nowrap;">' + this.escapeHtml(badgeText) + '</span>';
                html += '</div>';
            }
        }

        treeContainer.innerHTML = html;

        // Klick-Events binden
        var rows = treeContainer.querySelectorAll('.me-tree-row');
        for (var r = 0; r < rows.length; r++) {
            rows[r].addEventListener('click', function (e) {
                if (e.target && e.target.classList.contains('me-row-enable')) {
                    return;
                }
                StudioApp.selectMenuItem(this.getAttribute('data-id'));
            });
        }

        var cbs = treeContainer.querySelectorAll('.me-row-enable');
        for (var k = 0; k < cbs.length; k++) {
            cbs[k].addEventListener('change', function () {
                var id = this.getAttribute('data-id');
                var item = StudioApp.findMenuItemById(id);
                if (item) {
                    item.enabled = this.checked;
                    StudioApp.renderMenuTab();
                }
            });
        }

        this.populateMenuInspector();
        this.renderMenuLivePreview();
    },

    findMenuItemById: function (id) {
        var items = this.ensureMenuItems(false);
        for (var i = 0; i < items.length; i++) {
            if (items[i].id === id) return items[i];
        }
        return null;
    },

    selectMenuItem: function (id) {
        this.syncMenuEditorForm();
        this.selectedMenuItem = id;
        this.renderMenuTab();
    },

    populateMenuInspector: function () {
        var cur = this.findMenuItemById(this.selectedMenuItem);
        var textInput = document.getElementById('me-text');
        if (!textInput) return;

        var parentSelect = document.getElementById('me-parent');
        var sourceSelect = document.getElementById('me-source');
        var urlInput = document.getElementById('me-url');
        var iconInput = document.getElementById('me-icon');
        var targetSelect = document.getElementById('me-target');
        var enabledCb = document.getElementById('me-enabled');
        var headerCb = document.getElementById('me-is-header');
        var anonCb = document.getElementById('me-allow-anon');

        if (!cur) {
            textInput.value = '';
            urlInput.value = '';
            iconInput.value = '';
            return;
        }

        // Parent Dropdown mit Ebenen-Einrückung füllen (ohne sich selbst oder eigene Unterpunkte)
        var flat = this.getFlattenedMenuTree();
        var pHtml = '<option value="">-- Hauptebene (Root / Ebene 1) --</option>';
        for (var i = 0; i < flat.length; i++) {
            var candidate = flat[i].item;
            if (candidate.is_header) continue;
            if (this.isDescendantMenuItem(candidate.id, cur.id)) continue;
            var prefix = '';
            for (var d = 0; d < flat[i].depth; d++) prefix += '\u00A0\u00A0\u00A0\u2514 ';
            pHtml += '<option value="' + this.escapeHtml(candidate.id) + '"' + (cur.parent_id === candidate.id ? ' selected' : '') + '>' +
                prefix + this.escapeHtml(candidate.text) + '</option>';
        }
        parentSelect.innerHTML = pHtml;

        // Ziel-Typ / Vorlagen Dropdown füllen
        var sHtml = '<option value="custom:"' + (cur.source_type === 'custom' && cur.url !== '#' ? ' selected' : '') + '>Freie URL / Eigener Link</option>';
        sHtml += '<option value="folder:#"' + (cur.source_type === 'custom' && cur.url === '#' ? ' selected' : '') + '>Menü-Ordner / Aufklappbare Gruppe (#)</option>';

        var tables = this.project.tables || {};
        var tKeys = Object.keys(tables);
        if (tKeys.length > 0) {
            sHtml += '<optgroup label="Datenbank-Tabellen &amp; Views">';
            for (var t = 0; t < tKeys.length; t++) {
                var tName = tKeys[t];
                var isSelected = (cur.source_type === 'table' && cur.source_name === tName);
                sHtml += '<option value="table:' + this.escapeHtml(tName) + '"' + (isSelected ? ' selected' : '') + '>Tabelle: ' + this.escapeHtml(tables[tName].caption || tName) + ' (' + this.escapeHtml(tName) + ')</option>';
            }
            sHtml += '</optgroup>';
        }

        var cFiles = this.project.custom_files || {};
        var cfKeys = Object.keys(cFiles);
        if (cfKeys.length > 0) {
            sHtml += '<optgroup label="Eigene Dateien (Custom Files)">';
            for (var c = 0; c < cfKeys.length; c++) {
                var cfKey = cfKeys[c];
                var isCfSel = (cur.source_type === 'custom_file' && cur.source_name === cfKey);
                sHtml += '<option value="custom_file:' + this.escapeHtml(cfKey) + '"' + (isCfSel ? ' selected' : '') + '>Custom File: ' + this.escapeHtml(cFiles[cfKey].caption || cfKey) + '</option>';
            }
            sHtml += '</optgroup>';
        }
        sourceSelect.innerHTML = sHtml;

        textInput.value = cur.text || '';
        urlInput.value = cur.url || '#';
        iconInput.value = cur.icon || 'fa-circle-o';
        targetSelect.value = cur.target || '_self';
        enabledCb.checked = (cur.enabled !== false);
        headerCb.checked = !!cur.is_header;
        anonCb.checked = !!cur.allow_anon;
    },

    onMenuSourcePresetChange: function (val) {
        var cur = this.findMenuItemById(this.selectedMenuItem);
        if (!cur || !val) return;

        var parts = val.split(':');
        var type = parts[0];
        var name = parts.slice(1).join(':');

        if (type === 'folder') {
            cur.source_type = 'custom';
            cur.source_name = '';
            cur.url = '#';
            if (!cur.icon || cur.icon === 'fa-table' || cur.icon === 'fa-circle-o') {
                cur.icon = 'fa-folder-open-o';
            }
        } else if (type === 'table' && this.project.tables && this.project.tables[name]) {
            var tCfg = this.project.tables[name];
            var slug = name.toLowerCase().replace(/[^a-z0-9_]/g, '_');
            cur.source_type = 'table';
            cur.source_name = name;
            cur.url = slug + 'list.php';
            cur.text = tCfg.caption || name;
            cur.icon = tCfg.icon || 'fa-table';
        } else if (type === 'custom_file' && this.project.custom_files && this.project.custom_files[name]) {
            var cf = this.project.custom_files[name];
            var fn = (cf.filename || name).trim();
            if (fn.toLowerCase().slice(-4) !== '.php') fn += '.php';
            cur.source_type = 'custom_file';
            cur.source_name = name;
            cur.url = fn;
            cur.text = cf.caption || fn;
            cur.icon = cf.icon || 'fa-file-code-o';
        } else {
            cur.source_type = 'custom';
            cur.source_name = '';
        }

        this.renderMenuTab();
    },

    syncMenuEditorForm: function () {
        if (!this.project || !this.selectedMenuItem) return;
        var cur = this.findMenuItemById(this.selectedMenuItem);
        var textInput = document.getElementById('me-text');
        if (!cur || !textInput) return;

        var parentSelect = document.getElementById('me-parent');
        var urlInput = document.getElementById('me-url');
        var iconInput = document.getElementById('me-icon');
        var targetSelect = document.getElementById('me-target');
        var enabledCb = document.getElementById('me-enabled');
        var headerCb = document.getElementById('me-is-header');
        var anonCb = document.getElementById('me-allow-anon');

        cur.text = textInput.value;
        var newParent = parentSelect.value;
        if (!this.isDescendantMenuItem(newParent, cur.id)) {
            cur.parent_id = newParent;
        }
        cur.url = urlInput.value.trim() || '#';
        cur.icon = iconInput.value.trim() || 'fa-circle-o';
        cur.target = targetSelect.value || '_self';
        cur.enabled = enabledCb.checked;
        cur.is_header = headerCb.checked;
        cur.allow_anon = anonCb.checked;

        if (cur.is_header) {
            cur.parent_id = '';
        }

        // Auch an verknüpfte Tabelle / Custom File zurückspiegeln
        if (cur.source_type === 'table' && cur.source_name && this.project.tables && this.project.tables[cur.source_name]) {
            this.project.tables[cur.source_name].caption = cur.text;
            this.project.tables[cur.source_name].icon = cur.icon;
        } else if (cur.source_type === 'custom_file' && cur.source_name && this.project.custom_files && this.project.custom_files[cur.source_name]) {
            this.project.custom_files[cur.source_name].caption = cur.text;
            this.project.custom_files[cur.source_name].icon = cur.icon;
            this.project.custom_files[cur.source_name].in_menu = cur.enabled;
        }

        this.renderMenuLivePreview();

        // Aktuelle Zeile im Baum aktualisieren, ohne Fokus im Input zu verlieren
        var treeContainer = document.getElementById('menu-editor-tree-list');
        if (treeContainer && document.activeElement !== textInput && document.activeElement !== urlInput && document.activeElement !== iconInput) {
            this.renderMenuTab();
        }
    },

    renderMenuLivePreview: function () {
        var previewEl = document.getElementById('menu-editor-live-preview');
        if (!previewEl) return;

        var flat = this.getFlattenedMenuTree();
        var html = '<div style="color:#4b646f; font-size:10.5px; padding:2px 6px; background:#1a2226; margin-bottom:4px;">HAUPTMENÜ</div>';

        for (var i = 0; i < flat.length; i++) {
            var it = flat[i].item;
            var depth = flat[i].depth;
            if (it.enabled === false) continue;

            if (it.is_header) {
                html += '<div style="color:#4b646f; font-size:10.5px; padding:4px 6px; background:#1a2226; margin-top:6px; text-transform:uppercase;">' + this.escapeHtml(it.text) + '</div>';
                continue;
            }

            // Prüfen, ob dieser Punkt Kinder hat
            var hasChildren = false;
            for (var c = 0; c < flat.length; c++) {
                if (flat[c].item.enabled !== false && flat[c].item.parent_id === it.id) {
                    hasChildren = true;
                    break;
                }
            }

            var padLeft = 8 + (depth * 18);
            var isSel = (this.selectedMenuItem === it.id);
            html += '<div style="padding:3px 8px 3px ' + padLeft + 'px; display:flex; align-items:center; justify-content:space-between; background:' + (isSel ? '#1e282c' : 'transparent') + '; color:' + (isSel ? '#fff' : '#b8c7ce') + '; border-left:' + (isSel ? '3px solid #3c8dbc' : '3px solid transparent') + ';">';
            html += '  <span><i class="fa ' + this.escapeHtml(it.icon || 'fa-circle-o') + '" style="width:18px;"></i> ' + this.escapeHtml(it.text) + '</span>';
            if (hasChildren) {
                html += '  <i class="fa fa-angle-down" style="font-size:11px; opacity:0.7;"></i>';
            }
            html += '</div>';
        }

        previewEl.innerHTML = html;
    },

    addMenuItem: function (asChild) {
        var items = this.ensureMenuItems(false);
        this.syncMenuEditorForm();

        var parentId = '';
        if (asChild && this.selectedMenuItem) {
            var sel = this.findMenuItemById(this.selectedMenuItem);
            if (sel && !sel.is_header) {
                parentId = sel.id;
            }
        } else if (this.selectedMenuItem) {
            var cur = this.findMenuItemById(this.selectedMenuItem);
            if (cur) parentId = cur.parent_id || '';
        }

        var newId = 'menu_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
        var newItem = {
            id: newId,
            parent_id: parentId,
            text: asChild ? 'Neuer Unterpunkt' : 'Neuer Menüpunkt / Ordner',
            url: '#',
            icon: asChild ? 'fa-circle-o' : 'fa-folder-open-o',
            target: '_self',
            is_header: false,
            allow_anon: false,
            enabled: true,
            source_type: 'custom',
            source_name: ''
        };

        items.push(newItem);
        this.selectedMenuItem = newId;
        this.renderMenuTab();
        var textInput = document.getElementById('me-text');
        if (textInput) {
            textInput.focus();
            textInput.select();
        }
    },

    addMenuHeader: function () {
        var items = this.ensureMenuItems(false);
        this.syncMenuEditorForm();
        var title = prompt('Text für die neue Abschnitts-Überschrift (Group Header):', 'BERICHTE & AUSWERTUNGEN');
        if (!title || !title.trim()) return;

        var newId = 'hdr_' + Date.now();
        items.push({
            id: newId,
            parent_id: '',
            text: title.trim(),
            url: '#',
            icon: 'fa-header',
            target: '_self',
            is_header: true,
            allow_anon: false,
            enabled: true,
            source_type: 'custom',
            source_name: ''
        });
        this.selectedMenuItem = newId;
        this.renderMenuTab();
    },

    moveMenuItem: function (direction) {
        var items = this.ensureMenuItems(false);
        if (!this.selectedMenuItem || items.length < 2) return;
        this.syncMenuEditorForm();

        var cur = this.findMenuItemById(this.selectedMenuItem);
        if (!cur) return;

        // Alle Geschwister auf derselben Ebene (gleiche parent_id) suchen
        var siblingIndices = [];
        var curPosInSiblings = -1;
        for (var i = 0; i < items.length; i++) {
            if ((items[i].parent_id || '') === (cur.parent_id || '')) {
                if (items[i].id === cur.id) {
                    curPosInSiblings = siblingIndices.length;
                }
                siblingIndices.push(i);
            }
        }

        var targetSiblingPos = curPosInSiblings + direction;
        if (targetSiblingPos < 0 || targetSiblingPos >= siblingIndices.length) {
            return;
        }

        var idxA = siblingIndices[curPosInSiblings];
        var idxB = siblingIndices[targetSiblingPos];
        var tmp = items[idxA];
        items[idxA] = items[idxB];
        items[idxB] = tmp;

        this.renderMenuTab();
    },

    indentMenuItem: function (indentIn) {
        if (!this.selectedMenuItem) return;
        this.syncMenuEditorForm();
        var cur = this.findMenuItemById(this.selectedMenuItem);
        if (!cur || cur.is_header) return;

        var flat = this.getFlattenedMenuTree();
        var flatIdx = -1;
        for (var i = 0; i < flat.length; i++) {
            if (flat[i].item.id === cur.id) {
                flatIdx = i;
                break;
            }
        }
        if (flatIdx === -1) return;

        if (indentIn) {
            // Einrücken: Suche den vorherigen Eintrag auf derselben Ebene
            var curDepth = flat[flatIdx].depth;
            for (var p = flatIdx - 1; p >= 0; p--) {
                if (flat[p].depth === curDepth && !flat[p].item.is_header) {
                    cur.parent_id = flat[p].item.id;
                    break;
                } else if (flat[p].depth < curDepth) {
                    break;
                }
            }
        } else {
            // Ausrücken: Auf die Ebene des bisherigen Elternelements heben
            if (cur.parent_id) {
                var parentItem = this.findMenuItemById(cur.parent_id);
                cur.parent_id = parentItem ? (parentItem.parent_id || '') : '';
            }
        }

        this.renderMenuTab();
    },

    deleteSelectedMenuItem: function () {
        var items = this.ensureMenuItems(false);
        if (!this.selectedMenuItem) return;
        var cur = this.findMenuItemById(this.selectedMenuItem);
        if (!cur) return;

        if (!confirm('Möchten Sie den Menüeintrag "' + cur.text + '" wirklich aus dem Menü entfernen?')) {
            return;
        }

        var newParentForChildren = cur.parent_id || '';
        var filtered = [];
        for (var i = 0; i < items.length; i++) {
            if (items[i].id === cur.id) continue;
            if (items[i].parent_id === cur.id) {
                items[i].parent_id = newParentForChildren;
            }
            filtered.push(items[i]);
        }
        this.project.menu_items = filtered;
        this.selectedMenuItem = filtered.length > 0 ? filtered[0].id : null;
        this.renderMenuTab();
    },

    resetDefaultMenu: function () {
        if (!confirm('Möchten Sie das Menü auf die automatische Standardstruktur (inkl. CRM/Shop-Ordner & Ebenen) zurücksetzen?')) {
            return;
        }
        this.project.menu_items = [];
        this.ensureMenuItems(true);
        this.selectedMenuItem = this.project.menu_items.length > 0 ? this.project.menu_items[0].id : null;
        this.renderMenuTab();
    },

    ensureSecurityObject: function () {
        if (!this.project) return null;
        if (!this.project.security) {
            this.project.security = {
                enabled: false,
                hardcoded_admin_enabled: true,
                admin_username: 'admin',
                admin_password: '12345678',
                use_user_table: false,
                user_table: 'benutzer',
                username_field: 'benutzername',
                password_field: 'passwort',
                user_id_field: 'benutzer_id',
                parent_user_id_field: 'vorgesetzter_id',
                user_level_field: 'userlevel_id',
                user_level_security: true,
                dynamic_user_levels: false,
                user_level_table: 'userlevels',
                user_level_priv_table: 'userlevelpermissions',
                static_user_levels: [
                    { id: -2, name: 'Anonymous' },
                    { id: -1, name: 'Administrator' },
                    { id: 0, name: 'Default' },
                    { id: 1, name: 'Manager' },
                    { id: 2, name: 'ReadOnly' }
                ],
                static_permissions: {}
            };
        }
        return this.project.security;
    },

    renderSecurityTab: function () {
        var sec = this.ensureSecurityObject();
        if (!sec) return;

        var secEnabled = document.getElementById('sec-enabled');
        if (!secEnabled) return;

        secEnabled.checked = !!sec.enabled;
        document.getElementById('sec-admin-enabled').checked = !!sec.hardcoded_admin_enabled;
        document.getElementById('sec-use-user-table').checked = !!sec.use_user_table;
        document.getElementById('sec-admin-user').value = sec.admin_username || 'admin';
        document.getElementById('sec-admin-pass').value = sec.admin_password || 'admin';
        document.getElementById('sec-ulevel-enabled').checked = !!sec.user_level_security;
        document.getElementById('sec-dynamic-enabled').checked = !!sec.dynamic_user_levels;

        var tables = Object.keys(this.project.tables || {});
        var populateTableSelect = function (elId, selectedVal) {
            var sel = document.getElementById(elId);
            if (!sel) return;
            var html = '<option value="">-- Tabelle wählen --</option>';
            for (var i = 0; i < tables.length; i++) {
                var t = tables[i];
                html += '<option value="' + StudioApp.escapeHtml(t) + '"' + (t === selectedVal ? ' selected' : '') + '>' + StudioApp.escapeHtml(t) + '</option>';
            }
            if (selectedVal && tables.indexOf(selectedVal) === -1) {
                html += '<option value="' + StudioApp.escapeHtml(selectedVal) + '" selected>' + StudioApp.escapeHtml(selectedVal) + ' (noch nicht in DB)</option>';
            }
            sel.innerHTML = html;
        };

        populateTableSelect('sec-user-table', sec.user_table || 'benutzer');
        populateTableSelect('sec-level-table', sec.user_level_table || 'userlevels');
        populateTableSelect('sec-priv-table', sec.user_level_priv_table || 'userlevelpermissions');

        this.populateSecurityUserFields(sec.user_table || 'benutzer');

        // Rollen-Auswahl befüllen
        var roleSelect = document.getElementById('sec-active-role');
        if (roleSelect) {
            var currentRoleVal = roleSelect.value !== '' ? parseInt(roleSelect.value, 10) : 0;
            var levels = sec.static_user_levels || [];
            var rHtml = '';
            var foundRole = false;
            for (var r = 0; r < levels.length; r++) {
                var lvl = levels[r];
                if (parseInt(lvl.id, 10) === currentRoleVal) foundRole = true;
                rHtml += '<option value="' + lvl.id + '"' + (parseInt(lvl.id, 10) === currentRoleVal ? ' selected' : '') + '>[' + lvl.id + '] ' + this.escapeHtml(lvl.name) + '</option>';
            }
            roleSelect.innerHTML = rHtml;
            if (!foundRole && levels.length > 0) {
                roleSelect.value = '0';
            }
        }

        this.renderSecurityMatrix();
    },

    populateSecurityUserFields: function (userTableName) {
        var sec = this.ensureSecurityObject();
        var userTable = (this.project.tables && userTableName && this.project.tables[userTableName]) ? this.project.tables[userTableName] : null;
        var fields = userTable && userTable.fields ? Object.keys(userTable.fields) : [];

        var populateFieldSelect = function (elId, selectedVal, allowEmpty) {
            var sel = document.getElementById(elId);
            if (!sel) return;
            var html = allowEmpty ? '<option value="">-- (Kein Feld / Deaktiviert) --</option>' : '';
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                html += '<option value="' + StudioApp.escapeHtml(f) + '"' + (f === selectedVal ? ' selected' : '') + '>' + StudioApp.escapeHtml(f) + '</option>';
            }
            if (selectedVal && fields.indexOf(selectedVal) === -1) {
                html += '<option value="' + StudioApp.escapeHtml(selectedVal) + '" selected>' + StudioApp.escapeHtml(selectedVal) + '</option>';
            }
            sel.innerHTML = html;
        };

        populateFieldSelect('sec-username-field', sec.username_field || 'benutzername', false);
        populateFieldSelect('sec-password-field', sec.password_field || 'passwort', false);
        populateFieldSelect('sec-userid-field', sec.user_id_field || 'benutzer_id', true);
        populateFieldSelect('sec-parent-userid-field', sec.parent_user_id_field || 'vorgesetzter_id', true);
        populateFieldSelect('sec-userlevel-field', sec.user_level_field || 'userlevel_id', true);
    },

    onSecurityUserTableChange: function (newTableName) {
        var sec = this.ensureSecurityObject();
        sec.user_table = newTableName;
        var t = (this.project.tables && this.project.tables[newTableName]) ? this.project.tables[newTableName] : null;
        if (t && t.fields) {
            var fKeys = Object.keys(t.fields);
            if (fKeys.indexOf('benutzername') !== -1) sec.username_field = 'benutzername';
            else if (fKeys.indexOf('username') !== -1) sec.username_field = 'username';
            if (fKeys.indexOf('passwort') !== -1) sec.password_field = 'passwort';
            else if (fKeys.indexOf('password') !== -1) sec.password_field = 'password';
            if (t.primary_keys && t.primary_keys.length > 0) sec.user_id_field = t.primary_keys[0];
        }
        this.populateSecurityUserFields(newTableName);
        this.syncSecurityForm();
    },

    syncSecurityForm: function () {
        var sec = this.ensureSecurityObject();
        var secEnabled = document.getElementById('sec-enabled');
        if (!secEnabled) return;

        sec.enabled = secEnabled.checked;
        sec.hardcoded_admin_enabled = document.getElementById('sec-admin-enabled').checked;
        sec.use_user_table = document.getElementById('sec-use-user-table').checked;
        sec.admin_username = document.getElementById('sec-admin-user').value.trim() || 'admin';
        sec.admin_password = document.getElementById('sec-admin-pass').value;
        sec.user_table = document.getElementById('sec-user-table').value;
        sec.username_field = document.getElementById('sec-username-field').value;
        sec.password_field = document.getElementById('sec-password-field').value;
        sec.user_id_field = document.getElementById('sec-userid-field').value;
        sec.parent_user_id_field = document.getElementById('sec-parent-userid-field').value;
        sec.user_level_field = document.getElementById('sec-userlevel-field').value;
        sec.user_level_security = document.getElementById('sec-ulevel-enabled').checked;
        sec.dynamic_user_levels = document.getElementById('sec-dynamic-enabled').checked;
        sec.user_level_table = document.getElementById('sec-level-table').value;
        sec.user_level_priv_table = document.getElementById('sec-priv-table').value;
    },

    renderSecurityMatrix: function () {
        var tbody = document.getElementById('security-matrix-tbody');
        var roleSelect = document.getElementById('sec-active-role');
        if (!tbody || !roleSelect || !this.project) return;

        var sec = this.ensureSecurityObject();
        var activeRoleId = parseInt(roleSelect.value, 10);
        if (isNaN(activeRoleId)) activeRoleId = 0;
        var isAdminRole = (activeRoleId === -1);

        var tables = this.project.tables || {};
        var tKeys = Object.keys(tables);
        if (tKeys.length === 0) {
            tbody.innerHTML = '<tr><td colspan="11" style="text-align:center; padding:20px; color:#777;">Keine Tabellen im Projekt vorhanden.</td></tr>';
            return;
        }

        if (!sec.static_permissions) sec.static_permissions = {};

        var bits = [1, 2, 4, 8, 32, 64, 16]; // Add, Delete, Edit, List, View, Search, Admin
        var html = '';

        for (var i = 0; i < tKeys.length; i++) {
            var tName = tKeys[i];
            var tCfg = tables[tName];
            if (!sec.static_permissions[tName]) {
                sec.static_permissions[tName] = { '-2': 0, '-1': 127, '0': 109, '1': 111, '2': 104 };
            }
            var currentMask = sec.static_permissions[tName][String(activeRoleId)];
            if (currentMask === undefined) {
                currentMask = isAdminRole ? 127 : 0;
                sec.static_permissions[tName][String(activeRoleId)] = currentMask;
            }
            if (isAdminRole) currentMask = 127;

            html += '<tr data-sec-table="' + this.escapeHtml(tName) + '">';
            html += '  <td><strong>' + this.escapeHtml(tName) + '</strong></td>';
            html += '  <td>' + this.escapeHtml(tCfg.caption || tName) + '</td>';

            for (var b = 0; b < bits.length; b++) {
                var bitVal = bits[b];
                var isChecked = ((currentMask & bitVal) === bitVal);
                html += '  <td style="text-align:center;">';
                html += '    <input type="checkbox" class="sec-perm-cb" data-bit="' + bitVal + '" ' +
                    (isChecked ? 'checked ' : '') +
                    (isAdminRole ? 'disabled title="Administrator (-1) besitzt immer alle Rechte"' : '') + '>';
                html += '  </td>';
            }

            html += '  <td style="text-align:center;"><code class="sec-bitmask-val">' + currentMask + '</code></td>';

            // User ID Field Dropdown (Zeilenbasierter Eigentümerschutz)
            var tFields = Object.keys(tCfg.fields || {});
            var curOwnerField = tCfg.user_id_field || '';
            html += '  <td><select class="pm-select sec-owner-field" style="height:24px; padding:2px 4px; font-size:11.5px;">';
            html += '    <option value="">-- Alle Zeilen sichtbar (Kein Filter) --</option>';
            for (var f = 0; f < tFields.length; f++) {
                var fn = tFields[f];
                html += '    <option value="' + this.escapeHtml(fn) + '"' + (curOwnerField === fn ? ' selected' : '') + '>' + this.escapeHtml(fn) + ' (Nur eigene + Team)</option>';
            }
            html += '  </select></td>';
            html += '</tr>';
        }

        tbody.innerHTML = html;

        // Bind Checkbox & Owner Field Events
        var rows = tbody.querySelectorAll('tr[data-sec-table]');
        for (var r = 0; r < rows.length; r++) {
            var cbs = rows[r].querySelectorAll('.sec-perm-cb');
            for (var c = 0; c < cbs.length; c++) {
                cbs[c].addEventListener('change', function () {
                    var tr = this.closest('tr');
                    var tbl = tr.getAttribute('data-sec-table');
                    var roleId = String(document.getElementById('sec-active-role').value);
                    var allCbs = tr.querySelectorAll('.sec-perm-cb');
                    var mask = 0;
                    for (var k = 0; k < allCbs.length; k++) {
                        if (allCbs[k].checked) {
                            mask |= parseInt(allCbs[k].getAttribute('data-bit'), 10);
                        }
                    }
                    StudioApp.project.security.static_permissions[tbl][roleId] = mask;
                    var badge = tr.querySelector('.sec-bitmask-val');
                    if (badge) badge.textContent = mask;
                });
            }

            var ownerSelect = rows[r].querySelector('.sec-owner-field');
            if (ownerSelect) {
                ownerSelect.addEventListener('change', function () {
                    var tr = this.closest('tr');
                    var tbl = tr.getAttribute('data-sec-table');
                    if (StudioApp.project.tables[tbl]) {
                        StudioApp.project.tables[tbl].user_id_field = this.value;
                    }
                });
            }
        }
    },

    setAllRolePermissions: function (maskValue) {
        var roleSelect = document.getElementById('sec-active-role');
        if (!roleSelect || !this.project) return;
        var roleId = parseInt(roleSelect.value, 10);
        if (roleId === -1) {
            alert('Die Rolle Administrator (-1) besitzt immer Vollzugriff (127).');
            return;
        }
        var sec = this.ensureSecurityObject();
        var tables = Object.keys(this.project.tables || {});
        for (var i = 0; i < tables.length; i++) {
            var tName = tables[i];
            if (!sec.static_permissions[tName]) sec.static_permissions[tName] = {};
            sec.static_permissions[tName][String(roleId)] = maskValue;
        }
        this.renderSecurityMatrix();
    },

    addStaticRole: function () {
        var sec = this.ensureSecurityObject();
        var roleName = prompt('Name der neuen Benutzerrolle (User Level Name):', 'Vertrieb');
        if (!roleName || !roleName.trim()) return;

        var maxId = 0;
        var levels = sec.static_user_levels || [];
        for (var i = 0; i < levels.length; i++) {
            if (levels[i].id > maxId) maxId = levels[i].id;
        }
        var newId = maxId + 1;
        levels.push({ id: newId, name: roleName.trim() });
        sec.static_user_levels = levels;

        var tables = Object.keys(this.project.tables || {});
        for (var j = 0; j < tables.length; j++) {
            var tName = tables[j];
            if (!sec.static_permissions[tName]) sec.static_permissions[tName] = {};
            sec.static_permissions[tName][String(newId)] = 109; // Add+Edit+List+View+Search
        }

        this.renderSecurityTab();
        var roleSelect = document.getElementById('sec-active-role');
        if (roleSelect) {
            roleSelect.value = String(newId);
            this.renderSecurityMatrix();
        }
    },

    installSecurityTables: function () {
        if (!this.project) return;
        this.syncFormToProject();
        var msgBox = document.getElementById('sec-status-msg');
        if (msgBox) {
            msgBox.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Erstelle Systemtabellen (benutzer, userlevels, userlevelpermissions) in Datenbank und richte Demo-Konten ein...';
        }

        this.apiCall('create_security_tables', { project: this.project }, function (res) {
            if (res.success && res.project) {
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                StudioApp.refreshAllViews();
                StudioApp.saveCurrentProject();
                if (msgBox) {
                    msgBox.innerHTML = '<div style="padding:8px 12px; background:#dff0d8; border:1px solid #c3e6cb; color:#155724; border-radius:3px; font-weight:600;">' +
                        '<i class="fa fa-check-circle"></i> ' + StudioApp.escapeHtml(res.message) + '</div>';
                }
            } else {
                if (msgBox) {
                    msgBox.innerHTML = '<div style="padding:8px 12px; background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; border-radius:3px; font-weight:600;">' +
                        '<i class="fa fa-exclamation-triangle"></i> Fehler: ' + StudioApp.escapeHtml(res.error || 'Unbekannter Fehler') + '</div>';
                }
            }
        });
    },

    selectUiTemplate: function (tplName) {
        tplName = tplName || 'adminlte2';
        if (this.project && this.project.settings) {
            this.project.settings.ui_template = tplName;
        }
        var tplSelect = document.getElementById('st-template');
        if (tplSelect) {
            tplSelect.value = tplName;
        }
        var cards = document.querySelectorAll('.pm-tpl-card');
        for (var i = 0; i < cards.length; i++) {
            var isSelected = (cards[i].getAttribute('data-tpl') === tplName);
            cards[i].style.borderColor = isSelected ? '#3c8dbc' : '#cbd5e1';
            cards[i].style.background = isSelected ? '#f0f7ff' : '#ffffff';
            cards[i].style.boxShadow = isSelected ? '0 0 0 2px rgba(60, 141, 188, 0.2)' : 'none';
        }
    },

    renderSettingsTab: function () {
        if (!this.project || !this.project.settings) return;
        var st = this.project.settings;
        document.getElementById('st-project-name').value = this.project.project_name || '';
        document.getElementById('st-title').value = st.title || '';
        var tplVal = st.ui_template || 'adminlte2';
        this.selectUiTemplate(tplVal);
        document.getElementById('st-skin').value = st.theme_skin || 'skin-blue';
        var layoutEl = document.getElementById('st-layout');
        if (layoutEl) {
            layoutEl.value = st.layout_style || 'sidebar-mini';
        }
        document.getElementById('st-recperpage').value = st.records_per_page || 20;
        document.getElementById('st-pagesizes').value = st.page_sizes || '10,20,50,100,ALL';
        document.getElementById('st-dateformat').value = st.date_format || 'Y-m-d';
        document.getElementById('st-currency').value = st.currency_symbol || '€';
        document.getElementById('st-timezone').value = st.timezone || 'Europe/Berlin';
        document.getElementById('st-output').value = st.output_folder || 'output/app';
        document.getElementById('st-footer').value = st.footer_text || '';
        document.getElementById('st-confirm').checked = !!st.confirm_action;
        document.getElementById('st-debug').checked = !!st.show_sql_debug;

        var defSelect = document.getElementById('st-default-table');
        defSelect.innerHTML = '';
        var tables = Object.keys(this.project.tables || {});
        for (var i = 0; i < tables.length; i++) {
            var opt = document.createElement('option');
            opt.value = tables[i];
            opt.textContent = tables[i];
            if (st.default_table === tables[i]) opt.selected = true;
            defSelect.appendChild(opt);
        }
    },

    renderGenerateTab: function () {
        if (!this.project) return;
        this.syncFormToProject();
        var outInput = document.getElementById('gen-output-folder');
        if (outInput) {
            outInput.value = this.project.settings.output_folder || 'output/app';
        }
    },

    runGeneration: function () {
        if (!this.project) return;
        this.syncFormToProject();
        var outInput = document.getElementById('gen-output-folder');
        if (outInput && outInput.value.trim() !== '') {
            this.project.settings.output_folder = outInput.value.trim();
        }

        var logEl = document.getElementById('gen-console');
        var openBtn = document.getElementById('gen-open-app-btn');
        if (openBtn) openBtn.style.display = 'none';

        logEl.innerHTML = '<div class="log-info">[INFO] Starte PHPMacherNG Code-Generator Engine (Ziel: PHP 5.4 - 8.4+)...</div>';

        this.apiCall('generate', {
            filename: this.currentFilename,
            project: this.project
        }, function (res) {
            if (res.files && res.files.length > 0) {
                for (var i = 0; i < res.files.length; i++) {
                    var f = res.files[i];
                    logEl.innerHTML += '<div class="log-ok">[' + f.timestamp + '] Generiert: <strong>' + StudioApp.escapeHtml(f.name) + '</strong> (' + f.size + ' Bytes)</div>';
                }
            }
            if (res.errors && res.errors.length > 0) {
                for (var j = 0; j < res.errors.length; j++) {
                    logEl.innerHTML += '<div class="log-err">[ERROR] ' + StudioApp.escapeHtml(res.errors[j]) + '</div>';
                }
            }
            if (res.success) {
                logEl.innerHTML += '<div class="log-info" style="margin-top:8px;"><strong>[FERTIG] ' + res.files.length + ' Dateien erfolgreich in "' + StudioApp.escapeHtml(res.output_dir) + '" generiert!</strong></div>';
                if (openBtn && res.relative_url) {
                    openBtn.href = res.relative_url;
                    openBtn.style.display = 'inline-flex';
                }
            }
            logEl.scrollTop = logEl.scrollHeight;
        });
    },

    downloadCurrentProject: function () {
        if (!this.project) return;
        this.syncFormToProject();
        var fname = this.currentFilename || this.project.project_name || 'crm_shop_demo';
        // Zuerst aktuellen Stand speichern, danach Download starten
        this.apiCall('save_project', {
            filename: fname,
            project: this.project
        }, function (res) {
            var dlName = (res && res.success && res.filename) ? res.filename : fname;
            StudioApp.currentFilename = dlName;
            window.location.href = 'api.php?action=download_project&filename=' + encodeURIComponent(dlName);
        });
    },

    uploadProjectFile: function (inputEl) {
        if (!inputEl || !inputEl.files || inputEl.files.length === 0) return;
        var file = inputEl.files[0];
        var reader = new FileReader();
        reader.onload = function (evt) {
            var content = evt.target.result;
            var cleanBase = file.name.replace(/\.pmp\.json$/i, '').replace(/\.json$/i, '');
            StudioApp.setStatus('Importiere Projektdatei "' + file.name + '"...');
            StudioApp.apiCall('upload_project', {
                filename: cleanBase,
                project_json: content
            }, function (res) {
                inputEl.value = '';
                if (res.success && res.project) {
                    StudioApp.currentFilename = res.filename;
                    StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                    var tKeys = Object.keys(StudioApp.project.tables || {});
                    var cfKeys = Object.keys(StudioApp.project.custom_files || {});
                    StudioApp.selectedTable = tKeys.length > 0 ? tKeys[0] : null;
                    StudioApp.selectedField = null;
                    StudioApp.selectedCustomFile = cfKeys.length > 0 ? cfKeys[0] : null;
                    StudioApp.selectedMenuItem = null;
                    StudioApp.loadProjectList();
                    StudioApp.refreshAllViews();
                    StudioApp.setStatus(res.message || ('Projekt "' + res.filename + '" geladen.'));
                    alert(res.message || ('Projekt "' + res.filename + '" erfolgreich hochgeladen und geöffnet!'));
                } else {
                    alert('Fehler beim Hochladen des Projekts: ' + (res.error || 'Ungültige Datei'));
                }
            });
        };
        reader.readAsText(file, 'UTF-8');
    },

    deleteCurrentProject: function () {
        var fname = this.currentFilename;
        if (!fname) {
            alert('Kein gespeichertes Projekt zum Löschen ausgewählt.');
            return;
        }
        if (!confirm('Möchten Sie das Projekt "' + fname + '" (.pmp.json) wirklich unwiderruflich löschen?')) {
            return;
        }
        this.apiCall('delete_project', { filename: fname }, function (res) {
            if (res.success) {
                StudioApp.setStatus(res.message || 'Projekt gelöscht.');
                var list = res.projects || [];
                if (list.length > 0) {
                    StudioApp.loadProjectList();
                    StudioApp.loadProject(list[0].filename);
                } else {
                    StudioApp.currentFilename = 'mein_projekt';
                    StudioApp.apiCall('new_project', { project_name: 'MeinProjekt' }, function (nRes) {
                        if (nRes.success) {
                            StudioApp.project = StudioApp.normalizeProjectStructure(nRes.project);
                            StudioApp.selectedTable = null;
                            StudioApp.selectedField = null;
                            StudioApp.selectedCustomFile = null;
                            StudioApp.selectedMenuItem = null;
                            StudioApp.loadProjectList();
                            StudioApp.refreshAllViews();
                        }
                    });
                }
            } else {
                alert('Fehler beim Löschen: ' + (res.error || 'Unbekannter Fehler'));
            }
        });
    },

    downloadGeneratedZip: function () {
        if (!this.project) return;
        this.syncFormToProject();
        var outFolder = (this.project.settings && this.project.settings.output_folder) ? this.project.settings.output_folder : 'output/crm_shop_demo';
        var fname = this.currentFilename || this.project.project_name || 'crm_shop_demo';
        this.setStatus('Erzeuge Anwendung & erstelle ZIP-Archiv zum Download...');
        // Sicherstellen, dass die generierten Dateien auf dem neuesten Stand sind, dann ZIP herunterladen
        this.apiCall('generate', {
            filename: fname,
            project: this.project
        }, function (res) {
            if (res && res.success) {
                var cnt = (res.files && res.files.length) ? res.files.length : 0;
                StudioApp.setStatus('Generierung abgeschlossen (' + cnt + ' Dateien). Starte ZIP-Download...');
                window.location.href = 'api.php?action=download_generated_zip&output_folder=' + encodeURIComponent(outFolder) + '&filename=' + encodeURIComponent(fname);
            } else {
                alert('Fehler bei der Generierung vor dem ZIP-Download: ' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    openAddFieldsModal: function () {
        if (!this.project || !this.project.tables) return;
        var tables = Object.keys(this.project.tables);
        if (tables.length === 0) {
            alert('Bitte verbinden Sie zuerst eine Datenbank oder laden Sie Tabellen ins Projekt.');
            return;
        }
        var selEl = document.getElementById('add-fields-table-select');
        selEl.innerHTML = '';
        for (var i = 0; i < tables.length; i++) {
            var tn = tables[i];
            var tCfg = this.project.tables[tn];
            var opt = document.createElement('option');
            opt.value = tn;
            opt.textContent = tn + (tCfg.caption ? ' (' + tCfg.caption + ')' : '');
            if (tn === this.selectedTable) opt.selected = true;
            selEl.appendChild(opt);
        }

        var tbody = document.getElementById('add-fields-rows-tbody');
        if (tbody) {
            tbody.innerHTML = '';
        }
        // Standardmäßig eine Zeile für Touchscreen-Unterschrift vorbelegen
        this.appendAddFieldRow('unterschrift', 'Unterschrift (Touchscreen)', 'SIGNATURE', 0, false);

        var modal = document.getElementById('pm-add-fields-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    },

    closeAddFieldsModal: function () {
        var modal = document.getElementById('pm-add-fields-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },

    appendAddFieldRow: function (defaultName, defaultCaption, defaultPreset, defaultLen, defaultReq) {
        var tbody = document.getElementById('add-fields-rows-tbody');
        if (!tbody) return;

        var presets = [
            { val: 'SIGNATURE', label: '✍️ Touchscreen-Unterschrift (Signature Pad / Canvas)' },
            { val: 'VARCHAR',   label: '🔤 Textfeld einzeilig (VARCHAR / TEXT)' },
            { val: 'TEXTAREA',  label: '📝 Mehrzeiliges Textfeld / Notiz (TEXT / TEXTAREA)' },
            { val: 'INT',       label: '🔢 Ganzzahl (INT / NUMBER)' },
            { val: 'DECIMAL',   label: '📐 Dezimalzahl (DECIMAL 10,2 / NUMBER)' },
            { val: 'CURRENCY',  label: '💶 Währungsbetrag in € (DECIMAL 10,2 / CURRENCY)' },
            { val: 'BOOLEAN',   label: '☑️ Ja/Nein Checkbox (TINYINT 1 / CHECKBOX)' },
            { val: 'DATE',      label: '📅 Datum mit Datepicker (DATE)' },
            { val: 'DATETIME',  label: '🕒 Datum & Uhrzeit (DATETIME)' },
            { val: 'SELECT',    label: '📋 Dropdown-Auswahlliste (SELECT)' },
            { val: 'RADIO',     label: '🔘 Radio-Buttons (RADIO)' },
            { val: 'EMAIL',     label: '✉️ E-Mail-Adresse (VARCHAR / EMAIL)' },
            { val: 'LINK',      label: '🔗 Web-Link / URL (VARCHAR / LINK)' },
            { val: 'IMAGE',     label: '🖼️ Bild-URL / Base64-Grafik (IMAGE)' },
            { val: 'HTML',      label: '🌐 Formatierter HTML-Inhalt (LONGTEXT / HTML)' },
            { val: 'PASSWORD',  label: '🔒 Passwort-Feld mit Auto-Bcrypt-Hash (PASSWORD)' }
        ];

        var tr = document.createElement('tr');
        var presetOpts = '';
        for (var i = 0; i < presets.length; i++) {
            var p = presets[i];
            var sel = (p.val === (defaultPreset || 'VARCHAR')) ? ' selected' : '';
            presetOpts += '<option value="' + p.val + '"' + sel + '>' + this.escapeHtml(p.label) + '</option>';
        }

        tr.innerHTML =
            '<td><input type="text" class="pm-input af-name" value="' + this.escapeHtml(defaultName || '') + '" placeholder="z.B. unterschrift_kunde" style="width:100%;"></td>' +
            '<td><input type="text" class="pm-input af-caption" value="' + this.escapeHtml(defaultCaption || '') + '" placeholder="z.B. Kunden-Unterschrift" style="width:100%;"></td>' +
            '<td><select class="pm-select af-preset" style="width:100%; font-weight:600;">' + presetOpts + '</select></td>' +
            '<td><input type="number" class="pm-input af-length" value="' + (defaultLen || 255) + '" min="0" max="4000" style="width:100%;"></td>' +
            '<td style="text-align:center;"><input type="checkbox" class="af-required"' + (defaultReq ? ' checked' : '') + '></td>' +
            '<td style="text-align:center;"><button type="button" class="pm-btn pm-btn-default" style="color:#dd4b39; padding:2px 7px;" onclick="var r=this.closest(\'tr\'); if(r) r.parentNode.removeChild(r);" title="Zeile entfernen"><i class="fa fa-times"></i></button></td>';

        tbody.appendChild(tr);
    },

    submitAddFieldsModal: function () {
        var selEl = document.getElementById('add-fields-table-select');
        var alterCb = document.getElementById('add-fields-alter-db');
        var tbody = document.getElementById('add-fields-rows-tbody');
        if (!selEl || !tbody) return;

        var targetTable = selEl.value;
        var alterDb = alterCb ? alterCb.checked : true;
        var trs = tbody.querySelectorAll('tr');
        var fieldsToCreate = [];

        for (var i = 0; i < trs.length; i++) {
            var nameIn = trs[i].querySelector('.af-name');
            var capIn = trs[i].querySelector('.af-caption');
            var presetIn = trs[i].querySelector('.af-preset');
            var lenIn = trs[i].querySelector('.af-length');
            var reqIn = trs[i].querySelector('.af-required');

            var rawName = nameIn ? nameIn.value.trim() : '';
            if (!rawName) continue;

            fieldsToCreate.push({
                name: rawName,
                caption: capIn ? capIn.value.trim() : '',
                preset: presetIn ? presetIn.value : 'VARCHAR',
                length: lenIn ? (parseInt(lenIn.value, 10) || 255) : 255,
                required: reqIn ? reqIn.checked : false
            });
        }

        if (fieldsToCreate.length === 0) {
            alert('Bitte geben Sie mindestens einen gültigen Feldnamen (z.B. "unterschrift") ein.');
            return;
        }

        this.syncFormToProject();
        this.setStatus('Füge ' + fieldsToCreate.length + ' Feld(er) zu Tabelle "' + targetTable + '" hinzu...');

        this.apiCall('add_table_fields', {
            filename: this.currentFilename,
            project: this.project,
            table_name: targetTable,
            alter_db: alterDb,
            fields: fieldsToCreate
        }, function (res) {
            if (res.success && res.project) {
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                StudioApp.selectedTable = targetTable;
                if (res.added_fields && res.added_fields.length > 0) {
                    StudioApp.selectedField = res.added_fields[res.added_fields.length - 1];
                }
                StudioApp.closeAddFieldsModal();
                StudioApp.refreshAllViews();
                StudioApp.setStatus(res.message || 'Felder erfolgreich angelegt.');
                var msg = res.message || 'Felder erfolgreich hinzugefügt!';
                if (res.db_warnings && res.db_warnings.length > 0) {
                    msg += '\n\nHinweise:\n- ' + res.db_warnings.join('\n- ');
                }
                alert(msg);
            } else {
                alert('Fehler beim Anlegen der Felder: ' + (res.error || 'Unbekannter Fehler'));
            }
        });
    },

    changeStudioPassword: function () {
        var curPw = prompt('Bitte aktuelles Studio-Passwort eingeben (Initial-Passwort: 12345678):', '');
        if (curPw === null || curPw === '') return;
        var newPw = prompt('Bitte neues Studio-Passwort eingeben (mindestens 6 Zeichen):', '');
        if (newPw === null || newPw === '') return;
        if (newPw.length < 6) {
            alert('Das neue Passwort muss mindestens 6 Zeichen lang sein.');
            return;
        }
        var confirmPw = prompt('Neues Studio-Passwort zur Bestätigung wiederholen:', '');
        if (newPw !== confirmPw) {
            alert('Die eingegebenen neuen Passwörter stimmen nicht überein.');
            return;
        }
        this.apiCall('change_studio_password', {
            current_password: curPw,
            new_password: newPw
        }, function (res) {
            if (res.success) {
                alert(res.message || 'Studio-Passwort wurde erfolgreich geändert!');
            } else {
                alert('Fehler: ' + (res.error || 'Passwort konnte nicht geändert werden.'));
            }
        });
    },

    // =========================================================================
    // SERVER EVENTS (PHP) & CLIENT SCRIPTS (JS) - PROJEKTSPEZIFISCH
    // =========================================================================

    getEventsCatalog: function (scope) {
        if (scope === '__global__') {
            return [
                {
                    group: 'Server Events (PHP - Global / Alle Seiten)',
                    kind: 'server',
                    items: [
                        {
                            key: 'Global_Code',
                            label: 'Global_Code (Hilfsfunktionen)',
                            sigOpen: '// Globaler PHP-Bereich in phpfn14.php (eigene Funktionen & Konstanten)',
                            sigClose: '// Ende Global_Code',
                            desc: 'Wird in phpfn14.php eingebunden und steht auf jeder Seite der Anwendung zur Verfügung. Ideal für eigene globale PHP-Hilfsfunktionen.',
                            template: "function meineProjektHilfsfunktion($wert) {\n    return strtoupper(trim((string)$wert));\n}"
                        },
                        {
                            key: 'Page_Loading',
                            label: 'Page_Loading()',
                            sigOpen: 'function Page_Loading() {',
                            sigClose: '}',
                            desc: 'Wird beim Start jeder Seite vor der HTML-Ausgabe ausgeführt (in header.php). Ideal für globale Zugriffs- oder Wartungsprüfungen.',
                            template: "// Beispiel: Hinweis im Kopfbereich für Gäste setzen\n// if (!IsLoggedIn()) {\n//     ew_SetSuccessMessage('Willkommen! Bitte melden Sie sich an.');\n// }"
                        },
                        {
                            key: 'Page_Rendering',
                            label: 'Page_Rendering()',
                            sigOpen: 'function Page_Rendering() {',
                            sigClose: '}',
                            desc: 'Wird unmittelbar vor dem Rendern des Seiten-Layouts aufgerufen.',
                            template: "// Eigene Logik kurz vor dem Rendern des Seiten-Headers"
                        },
                        {
                            key: 'Page_Unloaded',
                            label: 'Page_Unloaded()',
                            sigOpen: 'function Page_Unloaded() {',
                            sigClose: '}',
                            desc: 'Wird am Ende jeder Seite im Footer ausgeführt (in footer.php).',
                            template: "// Aufräumarbeiten oder Logging am Seitenende"
                        },
                        {
                            key: 'User_LoggingIn',
                            label: 'User_LoggingIn($usr, &$pwd)',
                            sigOpen: 'function User_LoggingIn($usr, &$pwd) {',
                            sigClose: '    return true;\n}',
                            desc: 'Wird vor der Benutzeranmeldung in login.php aufgerufen. Geben Sie false zurück, um den Login abzubrechen.',
                            template: "// Beispiel: Bestimmten Benutzernamen sperren\nif (strtolower($usr) === 'gesperrt') {\n    ew_SetFailureMessage('Dieses Konto ist vorübergehend deaktiviert.');\n    return false;\n}\nreturn true;"
                        },
                        {
                            key: 'User_LoggedIn',
                            label: 'User_LoggedIn($usr)',
                            sigOpen: 'function User_LoggedIn($usr) {',
                            sigClose: '}',
                            desc: 'Wird direkt nach einer erfolgreichen Anmeldung ausgeführt (z.B. für Audit-Log oder Begrüßung).',
                            template: "// Beispiel: Begrüßungsmeldung nach Login\new_SetSuccessMessage('Willkommen zurück, ' . ew_HtmlEncode($usr) . '!');"
                        },
                        {
                            key: 'User_LoginError',
                            label: 'User_LoginError($usr, $pwd)',
                            sigOpen: 'function User_LoginError($usr, $pwd) {',
                            sigClose: '}',
                            desc: 'Wird bei einer fehlgeschlagenen Anmeldung in login.php aufgerufen.',
                            template: "// Fehlversuch protokollieren"
                        },
                        {
                            key: 'User_LoggingOut',
                            label: 'User_LoggingOut($usr)',
                            sigOpen: 'function User_LoggingOut($usr) {',
                            sigClose: '    return true;\n}',
                            desc: 'Wird vor dem Abmelden in logout.php aufgerufen. Geben Sie false zurück, um das Abmelden zu verhindern.',
                            template: "return true;"
                        }
                    ]
                },
                {
                    group: 'Client Scripts (JavaScript - Global & Login)',
                    kind: 'client',
                    items: [
                        {
                            key: 'Client_Script',
                            label: 'Client_Script (Alle Seiten - Header)',
                            sigOpen: '<script> // Global Client Script (vor Seiteninhalt)',
                            sigClose: '</script>',
                            desc: 'Globales JavaScript im Kopfbereich jeder Seite. Ideal für eigene JS-Hilfsfunktionen, die überall nutzbar sein sollen.',
                            template: "function meineGlobaleJsFunktion(text) {\n    console.log('PHPMacherNG:', text);\n}"
                        },
                        {
                            key: 'Startup_Script',
                            label: 'Startup_Script (Alle Seiten - DOM Ready)',
                            sigOpen: "document.addEventListener('DOMContentLoaded', function() {",
                            sigClose: '});',
                            desc: 'Wird auf jeder Seite ausgeführt, sobald das DOM vollständig geladen ist (im Footer).',
                            template: "// Globales Startup-Script für alle Seiten\nconsole.log('Seite geladen:', document.title);"
                        },
                        {
                            key: 'Login_Client_Script',
                            label: 'Login_Client_Script (Login-Seite)',
                            sigOpen: '<script> // Login Page Client Script',
                            sigClose: '</script>',
                            desc: 'JavaScript im Kopfbereich von login.php.',
                            template: "// Client Script für login.php"
                        },
                        {
                            key: 'Login_Form_CustomValidate',
                            label: 'Login_Form_CustomValidate(fobj)',
                            sigOpen: 'function ew_LoginFormValidate(fobj) {',
                            sigClose: '    return true;\n}',
                            desc: 'Clientseitige Formularprüfung vor dem Absenden des Login-Formulars. Geben Sie false zurück, um das Absenden zu stoppen.',
                            template: "if (!fobj.username.value.trim()) {\n    alert('Bitte geben Sie Ihren Benutzernamen ein.');\n    return false;\n}\nreturn true;"
                        },
                        {
                            key: 'Login_Startup_Script',
                            label: 'Login_Startup_Script (Login DOM Ready)',
                            sigOpen: "document.addEventListener('DOMContentLoaded', function() {",
                            sigClose: '});',
                            desc: 'Wird beim Laden der Login-Seite (login.php) ausgeführt.',
                            template: "// Beispiel: Fokus auf Benutzername-Feld setzen\nvar u = document.querySelector('input[name=\"username\"]');\nif (u) u.focus();"
                        }
                    ]
                }
            ];
        }

        // Tabellen-spezifische Events & Client Scripts
        return [
            {
                group: 'Server Events (PHP - Tabelle: ' + scope + ')',
                kind: 'server',
                items: [
                    {
                        key: 'Recordset_Selecting',
                        label: 'Recordset_Selecting(&$filter)',
                        sigOpen: 'public function Recordset_Selecting(&$filter) {',
                        sigClose: '}',
                        desc: 'Wird vor dem Abfragen der Datensätze in der Listenansicht (' + scope + 'list.php) aufgerufen. Über ew_AddFilter($filter, "...") können Sie dynamische SQL-WHERE-Filter ergänzen.',
                        template: "// Beispiel: Dynamischen SQL-Filter ergänzen\n// ew_AddFilter($filter, \"`aktiv` = 1\");"
                    },
                    {
                        key: 'Row_Rendering',
                        label: 'Row_Rendering(&$rs)',
                        sigOpen: 'public function Row_Rendering(&$rs) {',
                        sigClose: '}',
                        desc: 'Wird vor der Darstellung jedes Datensatzes (List, View, Add, Edit) aufgerufen. Änderungen an $rs wirken sich direkt auf die Anzeige aus.',
                        template: "// Beispiel: Feldwert vor der Anzeige manipulieren\n// if (isset($rs['notiz']) && $rs['notiz'] === '') {\n//     $rs['notiz'] = '(Keine Notiz)';\n// }"
                    },
                    {
                        key: 'Row_Rendered',
                        label: 'Row_Rendered(&$rs, &$rowAttrs)',
                        sigOpen: 'public function Row_Rendered(&$rs, &$rowAttrs) {',
                        sigClose: '}',
                        desc: 'Wird beim Rendern einer Zeile in der Liste/Detailansicht aufgerufen. Setzen Sie $rowAttrs[\'style\'] oder $rowAttrs[\'class\'], um Zeilen farblich hervorzuheben.',
                        template: "// Beispiel: Tabellenzeile farblich hervorheben\n// if (isset($rs['status']) && $rs['status'] === 'Storniert') {\n//     $rowAttrs['style'] = 'background-color: #ffeef0; color: #a94442;';\n// }"
                    },
                    {
                        key: 'Row_Inserting',
                        label: 'Row_Inserting($rsold, &$rsnew)',
                        sigOpen: 'public function Row_Inserting($rsold, &$rsnew) {',
                        sigClose: '    return true;\n}',
                        desc: 'Wird VOR dem Speichern eines neuen Datensatzes (INSERT) aufgerufen. Sie können $rsnew[\'feld\'] verändern oder mit return false das Einfügen abbrechen.',
                        template: "// Beispiel: Zeitstempel oder Ersteller automatisch setzen\n// $rsnew['erstellt_am'] = CurrentDateTime();\n\n// Beispiel: Pflichtprüfung mit Abbruch\n// if (empty($rsnew['name'])) {\n//     ew_SetFailureMessage('Bitte einen Namen angeben!');\n//     return false;\n// }\nreturn true;"
                    },
                    {
                        key: 'Row_Inserted',
                        label: 'Row_Inserted($rsold, &$rsnew)',
                        sigOpen: 'public function Row_Inserted($rsold, &$rsnew) {',
                        sigClose: '}',
                        desc: 'Wird NACH dem erfolgreichen Einfügen eines neuen Datensatzes ausgeführt ($rsnew enthält auch die neue Auto-Increment-ID). Ideal für Folge-Aktionen oder Protokolltabellen.',
                        template: "// Beispiel: Nach dem Anlegen einen Log-Eintrag schreiben\n// ew_Execute(\"INSERT INTO audit_log (aktion, benutzer) VALUES (?, ?)\", array('Neu angelegt', CurrentUserName()));"
                    },
                    {
                        key: 'Row_Updating',
                        label: 'Row_Updating($rsold, &$rsnew)',
                        sigOpen: 'public function Row_Updating($rsold, &$rsnew) {',
                        sigClose: '    return true;\n}',
                        desc: 'Wird VOR dem Aktualisieren eines bestehenden Datensatzes (UPDATE) aufgerufen. $rsold enthält die alten DB-Werte, $rsnew (per Referenz) die neuen Werte.',
                        template: "// Beispiel: Änderungsdatum automatisch aktualisieren\n// $rsnew['geaendert_am'] = CurrentDateTime();\nreturn true;"
                    },
                    {
                        key: 'Row_Updated',
                        label: 'Row_Updated($rsold, &$rsnew)',
                        sigOpen: 'public function Row_Updated($rsold, &$rsnew) {',
                        sigClose: '}',
                        desc: 'Wird NACH dem erfolgreichen Aktualisieren eines Datensatzes in der Datenbank ausgeführt.',
                        template: "// Aktion nach erfolgreichem Update"
                    },
                    {
                        key: 'Row_Deleting',
                        label: 'Row_Deleting(&$rs)',
                        sigOpen: 'public function Row_Deleting(&$rs) {',
                        sigClose: '    return true;\n}',
                        desc: 'Wird VOR dem Löschen eines Datensatzes aufgerufen. Geben Sie false zurück (inkl. ew_SetFailureMessage), um das Löschen zu verhindern.',
                        template: "// Beispiel: Löschen von geschützten Datensätzen verhindern\n// if (isset($rs['id']) && (int)$rs['id'] === 1) {\n//     ew_SetFailureMessage('Der Hauptdatensatz #1 darf nicht gelöscht werden!');\n//     return false;\n// }\nreturn true;"
                    },
                    {
                        key: 'Row_Deleted',
                        label: 'Row_Deleted(&$rs)',
                        sigOpen: 'public function Row_Deleted(&$rs) {',
                        sigClose: '}',
                        desc: 'Wird NACH dem erfolgreichen Löschen eines Datensatzes ausgeführt.',
                        template: "// Nacharbeit nach dem Löschen"
                    },
                    {
                        key: 'Form_CustomValidate',
                        label: 'Form_CustomValidate(&$customError)',
                        sigOpen: 'public function Form_CustomValidate(&$customError) {',
                        sigClose: '    return true;\n}',
                        desc: 'Serverseitige Formular-Validierung bei Add, Edit und Multi-Update. Setzen Sie $customError und geben Sie false zurück, falls eine Eingabe ungültig ist.',
                        template: "// Beispiel: Eigene Plausibilitätsprüfung\n// if (ew_Post('x_preis', 0) < 0) {\n//     $customError = 'Der Preis darf nicht negativ sein.';\n//     return false;\n// }\nreturn true;"
                    }
                ]
            },
            {
                group: 'Client Scripts (JavaScript - Tabelle: ' + scope + ')',
                kind: 'client',
                items: [
                    {
                        key: 'List_Client_Script',
                        label: 'List Page -> Client_Script',
                        sigOpen: '<script> // List Page Client Script (' + scope + 'list.php)',
                        sigClose: '</script>',
                        desc: 'JavaScript-Funktionen für die Listenansicht dieser Tabelle.',
                        template: "// Eigene JS-Funktionen für die Listenansicht"
                    },
                    {
                        key: 'List_Startup_Script',
                        label: 'List Page -> Startup_Script (DOM Ready)',
                        sigOpen: "document.addEventListener('DOMContentLoaded', function() {",
                        sigClose: '});',
                        desc: 'Wird ausgeführt, sobald die Listenansicht (' + scope + 'list.php) im Browser geladen ist.',
                        template: "// Beispiel: Hinweis oder UI-Anpassung auf der List-Seite\nconsole.log('Listenansicht geladen');"
                    },
                    {
                        key: 'Add_Client_Script',
                        label: 'Add Page -> Client_Script',
                        sigOpen: '<script> // Add Page Client Script (' + scope + 'add.php)',
                        sigClose: '</script>',
                        desc: 'JavaScript-Funktionen für die Hinzufügen-Seite dieser Tabelle.',
                        template: "// Eigene JS-Funktionen für die Add-Seite"
                    },
                    {
                        key: 'Add_Form_CustomValidate',
                        label: 'Add Page -> Form_CustomValidate(fobj)',
                        sigOpen: 'function ew_AddFormValidate(fobj) {',
                        sigClose: '    return true;\n}',
                        desc: 'Clientseitige JavaScript-Validierung vor dem Absenden des Hinzufügen-Formulars. Geben Sie false zurück, um das Speichern abzubrechen.',
                        template: "// Beispiel: Formularfeld #x_name im Browser prüfen\n// var el = document.getElementById('x_name');\n// if (el && el.value.trim() === '') {\n//     alert('Bitte füllen Sie das Feld Name aus!');\n//     el.focus();\n//     return false;\n// }\nreturn true;"
                    },
                    {
                        key: 'Add_Startup_Script',
                        label: 'Add Page -> Startup_Script (DOM Ready)',
                        sigOpen: "document.addEventListener('DOMContentLoaded', function() {",
                        sigClose: '});',
                        desc: 'Wird beim Laden der Hinzufügen-Seite (' + scope + 'add.php) ausgeführt. Ideal für Live-Berechnungen oder Event-Listener auf Eingabefeldern (#x_<feld>).',
                        template: "// Beispiel: Automatische Berechnung oder Event-Listener auf Add-Seite\n// var el = document.getElementById('x_menge');\n// if (el) el.addEventListener('input', function() { console.log(this.value); });"
                    },
                    {
                        key: 'Edit_Client_Script',
                        label: 'Edit Page -> Client_Script',
                        sigOpen: '<script> // Edit Page Client Script (' + scope + 'edit.php)',
                        sigClose: '</script>',
                        desc: 'JavaScript-Funktionen für die Bearbeiten-Seite dieser Tabelle.',
                        template: "// Eigene JS-Funktionen für die Edit-Seite"
                    },
                    {
                        key: 'Edit_Form_CustomValidate',
                        label: 'Edit Page -> Form_CustomValidate(fobj)',
                        sigOpen: 'function ew_EditFormValidate(fobj) {',
                        sigClose: '    return true;\n}',
                        desc: 'Clientseitige JavaScript-Validierung vor dem Absenden des Bearbeiten-Formulars.',
                        template: "// Clientseitige Prüfung vor dem Speichern von Änderungen\nreturn true;"
                    },
                    {
                        key: 'Edit_Startup_Script',
                        label: 'Edit Page -> Startup_Script (DOM Ready)',
                        sigOpen: "document.addEventListener('DOMContentLoaded', function() {",
                        sigClose: '});',
                        desc: 'Wird beim Laden der Bearbeiten-Seite (' + scope + 'edit.php) ausgeführt.',
                        template: "// Startup-Script für die Bearbeiten-Seite"
                    },
                    {
                        key: 'View_Client_Script',
                        label: 'View Page -> Client_Script',
                        sigOpen: '<script> // View Page Client Script (' + scope + 'view.php)',
                        sigClose: '</script>',
                        desc: 'JavaScript-Funktionen für die Detailansicht dieser Tabelle.',
                        template: "// Client Script für die Detailansicht"
                    },
                    {
                        key: 'View_Startup_Script',
                        label: 'View Page -> Startup_Script (DOM Ready)',
                        sigOpen: "document.addEventListener('DOMContentLoaded', function() {",
                        sigClose: '});',
                        desc: 'Wird beim Laden der Detailansicht (' + scope + 'view.php) ausgeführt.',
                        template: "// Startup-Script für die Detailansicht"
                    }
                ]
            }
        ];
    },

    ensureProjectEventsStructure: function () {
        if (!this.project) return;
        if (!this.project.server_events || typeof this.project.server_events !== 'object' || Array.isArray(this.project.server_events)) {
            this.project.server_events = { global: {}, tables: {} };
        }
        if (!this.project.server_events.global || typeof this.project.server_events.global !== 'object' || Array.isArray(this.project.server_events.global)) {
            this.project.server_events.global = {};
        }
        if (!this.project.server_events.tables || typeof this.project.server_events.tables !== 'object' || Array.isArray(this.project.server_events.tables)) {
            this.project.server_events.tables = {};
        }
        if (!this.project.client_scripts || typeof this.project.client_scripts !== 'object' || Array.isArray(this.project.client_scripts)) {
            this.project.client_scripts = { global: {}, tables: {} };
        }
        if (!this.project.client_scripts.global || typeof this.project.client_scripts.global !== 'object' || Array.isArray(this.project.client_scripts.global)) {
            this.project.client_scripts.global = {};
        }
        if (!this.project.client_scripts.tables || typeof this.project.client_scripts.tables !== 'object' || Array.isArray(this.project.client_scripts.tables)) {
            this.project.client_scripts.tables = {};
        }
    },

    getEventMeta: function (scope, eventKey) {
        var groups = this.getEventsCatalog(scope);
        for (var g = 0; g < groups.length; g++) {
            var items = groups[g].items;
            for (var i = 0; i < items.length; i++) {
                if (items[i].key === eventKey) {
                    return {
                        kind: groups[g].kind,
                        item: items[i]
                    };
                }
            }
        }
        return null;
    },

    getEventCode: function (scope, kind, eventKey) {
        this.ensureProjectEventsStructure();
        if (!this.project) return '';
        var store = (kind === 'client') ? this.project.client_scripts : this.project.server_events;
        if (scope === '__global__') {
            return (store.global && typeof store.global[eventKey] === 'string') ? store.global[eventKey] : '';
        }
        if (store.tables && store.tables[scope] && typeof store.tables[scope][eventKey] === 'string') {
            return store.tables[scope][eventKey];
        }
        return '';
    },

    setEventCode: function (scope, kind, eventKey, code) {
        this.ensureProjectEventsStructure();
        if (!this.project) return;
        var store = (kind === 'client') ? this.project.client_scripts : this.project.server_events;
        var trimmed = (code || '').trim();
        if (scope === '__global__') {
            if (trimmed === '') {
                delete store.global[eventKey];
            } else {
                store.global[eventKey] = code;
            }
        } else {
            if (!store.tables[scope] || typeof store.tables[scope] !== 'object' || Array.isArray(store.tables[scope])) {
                store.tables[scope] = {};
            }
            if (trimmed === '') {
                delete store.tables[scope][eventKey];
            } else {
                store.tables[scope][eventKey] = code;
            }
        }
    },

    countActiveEventsForScope: function (scope) {
        var groups = this.getEventsCatalog(scope);
        var count = 0;
        for (var g = 0; g < groups.length; g++) {
            for (var i = 0; i < groups[g].items.length; i++) {
                var it = groups[g].items[i];
                if (this.getEventCode(scope, groups[g].kind, it.key).trim() !== '') {
                    count++;
                }
            }
        }
        return count;
    },

    countTotalActiveEventsInProject: function () {
        if (!this.project) return 0;
        var total = this.countActiveEventsForScope('__global__');
        var tables = Object.keys(this.project.tables || {});
        for (var i = 0; i < tables.length; i++) {
            total += this.countActiveEventsForScope(tables[i]);
        }
        return total;
    },

    renderEventsTab: function () {
        if (!this.project) return;
        this.ensureProjectEventsStructure();

        var scopeSel = document.getElementById('ev-scope-select');
        var treeList = document.getElementById('ev-tree-list');
        if (!scopeSel || !treeList) return;

        var tables = Object.keys(this.project.tables || {});
        if (this.selectedEventScope !== '__global__' && !this.project.tables[this.selectedEventScope]) {
            this.selectedEventScope = '__global__';
        }

        // Scope-Auswahl befüllen (Global + alle Tabellen des aktuellen Projekts)
        var globalCnt = this.countActiveEventsForScope('__global__');
        var selHtml = '<option value="__global__"' + (this.selectedEventScope === '__global__' ? ' selected' : '') + '>🌐 Global – Alle Seiten & Login' + (globalCnt > 0 ? ' (' + globalCnt + ' aktiv)' : '') + '</option>';
        for (var t = 0; t < tables.length; t++) {
            var tName = tables[t];
            var tCfg = this.project.tables[tName];
            var tCnt = this.countActiveEventsForScope(tName);
            var label = '📋 Tabelle: ' + tName + (tCfg.caption && tCfg.caption !== tName ? ' (' + tCfg.caption + ')' : '') + (tCnt > 0 ? ' [' + tCnt + ' aktiv]' : '');
            selHtml += '<option value="' + this.escapeHtml(tName) + '"' + (this.selectedEventScope === tName ? ' selected' : '') + '>' + this.escapeHtml(label) + '</option>';
        }
        scopeSel.innerHTML = selHtml;

        // Sicherstellen, dass selectedEventKey im aktuellen Scope existiert
        var curMeta = this.getEventMeta(this.selectedEventScope, this.selectedEventKey);
        if (!curMeta) {
            var groups = this.getEventsCatalog(this.selectedEventScope);
            this.selectedEventKey = groups[0].items[0].key;
        }

        // Gesamt-Aktiv-Badge aktualisieren
        var totalActive = this.countTotalActiveEventsInProject();
        var badgeEl = document.getElementById('ev-active-count-badge');
        if (badgeEl) {
            badgeEl.textContent = totalActive + ' im Projekt aktiv';
            badgeEl.style.background = totalActive > 0 ? '#00a65a' : '#94a3b8';
        }

        // Baum rendern
        var catGroups = this.getEventsCatalog(this.selectedEventScope);
        var treeHtml = '';
        for (var g = 0; g < catGroups.length; g++) {
            var grp = catGroups[g];
            var grpIcon = (grp.kind === 'server') ? 'fa-server' : 'fa-code';
            var grpColor = (grp.kind === 'server') ? '#3c8dbc' : '#d97706';
            treeHtml += '<div style="padding:6px 8px; font-size:11.5px; font-weight:700; color:' + grpColor + '; background:#f8fafc; border-bottom:1px solid #e2e8f0; margin-top:' + (g > 0 ? '8px' : '0') + ';">';
            treeHtml += '<i class="fa ' + grpIcon + '"></i> ' + this.escapeHtml(grp.group);
            treeHtml += '</div>';

            for (var i = 0; i < grp.items.length; i++) {
                var item = grp.items[i];
                var isSelected = (item.key === this.selectedEventKey);
                var hasCode = (this.getEventCode(this.selectedEventScope, grp.kind, item.key).trim() !== '');
                var bg = isSelected ? '#e0f2fe' : (hasCode ? '#f0fdf4' : '#fff');
                var borderLeft = isSelected ? '3px solid #0284c7' : (hasCode ? '3px solid #16a34a' : '3px solid transparent');

                treeHtml += '<div onclick="StudioApp.selectEventItem(\'' + this.escapeHtml(item.key) + '\');" style="padding:7px 10px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; background:' + bg + '; border-left:' + borderLeft + '; font-size:12px;">';
                treeHtml += '  <span style="font-family:Consolas, monospace; font-weight:' + (isSelected || hasCode ? '700' : '400') + '; color:' + (isSelected ? '#0369a1' : '#1e293b') + ';">' + this.escapeHtml(item.label) + '</span>';
                if (hasCode) {
                    treeHtml += '  <span style="background:#16a34a; color:#fff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:8px;">Aktiv</span>';
                } else {
                    treeHtml += '  <span style="color:#94a3b8; font-size:10px;">' + (grp.kind === 'server' ? 'PHP' : 'JS') + '</span>';
                }
                treeHtml += '</div>';
            }
        }
        treeList.innerHTML = treeHtml;

        this.renderSelectedEventEditor();
    },

    onEventScopeChange: function (newScope) {
        this.syncEventCodeEditor();
        this.selectedEventScope = newScope || '__global__';
        if (this.selectedEventScope !== '__global__' && this.project && this.project.tables && this.project.tables[this.selectedEventScope]) {
            this.selectedTable = this.selectedEventScope;
            this.renderSidebarTree();
        }
        var groups = this.getEventsCatalog(this.selectedEventScope);
        this.selectedEventKey = groups[0].items[0].key;
        this.renderEventsTab();
    },

    selectEventItem: function (eventKey) {
        this.syncEventCodeEditor();
        this.selectedEventKey = eventKey;
        this.renderEventsTab();
    },

    renderSelectedEventEditor: function () {
        var meta = this.getEventMeta(this.selectedEventScope, this.selectedEventKey);
        if (!meta) return;

        var typeBadge = document.getElementById('ev-type-badge');
        var titleEl = document.getElementById('ev-title-display');
        var scopeBadge = document.getElementById('ev-scope-badge');
        var descEl = document.getElementById('ev-desc-display');
        var sigOpen = document.getElementById('ev-signature-open');
        var sigClose = document.getElementById('ev-signature-close');
        var editor = document.getElementById('ev-code-editor');

        if (typeBadge) {
            if (meta.kind === 'server') {
                typeBadge.textContent = 'PHP Server Event';
                typeBadge.style.background = '#3c8dbc';
            } else {
                typeBadge.textContent = 'JS Client Script';
                typeBadge.style.background = '#d97706';
            }
        }
        if (titleEl) {
            titleEl.textContent = meta.item.label;
        }
        if (scopeBadge) {
            scopeBadge.textContent = (this.selectedEventScope === '__global__')
                ? '(Projektweit / Global in ' + (this.project.project_name || 'Projekt') + ')'
                : '(Tabelle: ' + this.selectedEventScope + ' in ' + (this.project.project_name || 'Projekt') + ')';
        }
        if (descEl) {
            descEl.textContent = meta.item.desc;
        }
        if (sigOpen) {
            sigOpen.textContent = meta.item.sigOpen;
        }
        if (sigClose) {
            sigClose.innerHTML = '<span>' + this.escapeHtml(meta.item.sigClose) + '</span>' +
                '<span style="font-size:11px; color:#9cdcfe;"><i class="fa fa-check-circle"></i> Projektspezifisch für <strong>' + this.escapeHtml(this.project.project_name || this.currentFilename) + '</strong></span>';
        }
        if (editor) {
            editor.value = this.getEventCode(this.selectedEventScope, meta.kind, meta.item.key);
        }
    },

    syncEventCodeEditor: function () {
        if (!this.project) return;
        var editor = document.getElementById('ev-code-editor');
        if (!editor) return;
        var meta = this.getEventMeta(this.selectedEventScope, this.selectedEventKey);
        if (!meta) return;
        this.setEventCode(this.selectedEventScope, meta.kind, meta.item.key, editor.value);

        var totalActive = this.countTotalActiveEventsInProject();
        var badgeEl = document.getElementById('ev-active-count-badge');
        if (badgeEl) {
            badgeEl.textContent = totalActive + ' im Projekt aktiv';
            badgeEl.style.background = totalActive > 0 ? '#00a65a' : '#94a3b8';
        }
    },

    insertEventTemplate: function () {
        var meta = this.getEventMeta(this.selectedEventScope, this.selectedEventKey);
        var editor = document.getElementById('ev-code-editor');
        if (!meta || !editor) return;
        if (editor.value.trim() !== '' && !confirm('Möchten Sie die Beispiel-Vorlage an den bestehenden Code anhängen?')) {
            return;
        }
        editor.value = (editor.value.trim() !== '')
            ? (editor.value + "\n\n" + meta.item.template)
            : meta.item.template;
        this.syncEventCodeEditor();
        this.renderEventsTab();
    },

    clearCurrentEventCode: function () {
        var editor = document.getElementById('ev-code-editor');
        if (!editor || editor.value.trim() === '') return;
        if (!confirm('Möchten Sie den Code für "' + this.selectedEventKey + '" in diesem Projekt wirklich leeren?')) {
            return;
        }
        editor.value = '';
        this.syncEventCodeEditor();
        this.renderEventsTab();
    },

    insertEventQuickSnippet: function (snippetType) {
        var editor = document.getElementById('ev-code-editor');
        if (!editor) return;
        var snippet = '';
        if (snippetType === 'rsnew_set') {
            snippet = "$rsnew['feldname'] = CurrentDateTime(); // Oder CurrentUserName() / eigener Wert";
        } else if (snippetType === 'cancel_error') {
            snippet = "ew_SetFailureMessage('Aktion abgebrochen: Ungültiger Wert.');\nreturn false;";
        } else if (snippetType === 'row_color') {
            snippet = "if (isset($rs['status']) && $rs['status'] === 'Wichtig') {\n    $rowAttrs['style'] = 'background-color: #fff3cd; font-weight: bold;';\n}";
        } else if (snippetType === 'sql_filter') {
            snippet = "if (!IsSysAdmin()) {\n    ew_AddFilter($filter, \"`aktiv` = 1\");\n}";
        } else if (snippetType === 'db_execute') {
            snippet = "$anzahl = ew_ExecuteScalar(\"SELECT COUNT(*) FROM tabelle WHERE id = ?\", array(1));\n// ew_Execute(\"UPDATE tabelle SET status = ? WHERE id = ?\", array('Erledigt', 1));";
        } else if (snippetType === 'js_field') {
            snippet = "var feld = document.getElementById('x_feldname');\nif (feld && feld.value.trim() === '') {\n    alert('Bitte Feld ausfüllen!');\n    feld.focus();\n    return false;\n}";
        }
        if (!snippet) return;
        var start = editor.selectionStart || editor.value.length;
        var end = editor.selectionEnd || editor.value.length;
        var before = editor.value.substring(0, start);
        var after = editor.value.substring(end);
        var prefix = (before.length > 0 && before.slice(-1) !== '\n') ? '\n' : '';
        editor.value = before + prefix + snippet + '\n' + after;
        this.syncEventCodeEditor();
        this.renderEventsTab();
        editor.focus();
    },

    // =========================================================================
    // DATENBANK-VIEW BUILDER (VISUELLER SQL-GENERATOR & DIREKTER SQL-EDITOR)
    // =========================================================================

    openViewBuilderModal: function (editViewName) {
        if (!this.project || !this.project.tables) {
            alert('Bitte laden Sie zuerst ein Projekt oder verbinden Sie sich mit einer Datenbank.');
            return;
        }
        var modal = document.getElementById('pm-view-builder-modal');
        if (!modal) return;

        // Wenn kein Name übergeben wurde, aber aktuell eine VIEW in der Seitenleiste markiert ist, diese vorladen
        var targetView = '';
        if (typeof editViewName === 'string') {
            targetView = editViewName;
        } else if (this.selectedTable && this.project.tables[this.selectedTable] && this.project.tables[this.selectedTable].type === 'VIEW') {
            targetView = this.selectedTable;
        }

        // Dropdown aller vorhandenen Views im Projekt füllen
        var existSelect = document.getElementById('vb-existing-select');
        var allKeys = Object.keys(this.project.tables);
        var optHtml = '<option value="">-- Neue Datenbank-View anlegen --</option>';
        for (var i = 0; i < allKeys.length; i++) {
            var k = allKeys[i];
            if (this.project.tables[k] && this.project.tables[k].type === 'VIEW') {
                var cap = this.project.tables[k].caption || k;
                optHtml += '<option value="' + this.escapeHtml(k) + '"' + (k === targetView ? ' selected' : '') + '>View bearbeiten: ' + this.escapeHtml(k) + ' (' + this.escapeHtml(cap) + ')</option>';
            }
        }
        if (existSelect) {
            existSelect.innerHTML = optHtml;
        }

        this.loadViewIntoBuilder(targetView);
        modal.style.display = 'flex';
    },

    closeViewBuilderModal: function () {
        var modal = document.getElementById('pm-view-builder-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },

    onSelectExistingView: function (viewName) {
        this.loadViewIntoBuilder(viewName || '');
    },

    loadViewIntoBuilder: function (viewName) {
        var nameInput = document.getElementById('vb-view-name');
        var capInput = document.getElementById('vb-view-caption');
        var pkInput = document.getElementById('vb-view-pk');
        var sqlEditor = document.getElementById('vb-sql-editor');
        var delBtn = document.getElementById('vb-delete-view-btn');
        var whereInput = document.getElementById('vb-where-input');
        var havingInput = document.getElementById('vb-having-input');
        var distinctChk = document.getElementById('vb-distinct-chk');
        var autoSyncChk = document.getElementById('vb-auto-sync-sql');
        var previewBox = document.getElementById('vb-preview-container');
        var previewStatus = document.getElementById('vb-preview-status');

        if (whereInput) whereInput.value = '';
        if (havingInput) havingInput.value = '';
        if (distinctChk) distinctChk.checked = false;
        if (previewStatus) previewStatus.textContent = 'Noch nicht ausgeführt';
        if (previewBox) {
            previewBox.innerHTML = '<div style="color:#777; padding:18px; text-align:center;">Klicken Sie auf <strong>&bdquo;SQL testen &amp; Vorschau laden&ldquo;</strong>, um Ihre SQL-Abfrage live gegen die Datenbank zu prüfen.</div>';
        }

        var tables = this.project.tables || {};
        var allKeys = Object.keys(tables);

        // Beteiligte Tabellen im visuellen Builder zurücksetzen
        var preselectedTables = [];
        if (viewName && tables[viewName] && tables[viewName].type === 'VIEW') {
            var vCfg = tables[viewName];
            if (nameInput) nameInput.value = viewName;
            if (capInput) capInput.value = vCfg.caption || viewName;
            if (pkInput) pkInput.value = Array.isArray(vCfg.primary_keys) ? vCfg.primary_keys.join(', ') : '';
            var existingSql = (vCfg.view_sql && vCfg.view_sql.trim() !== '')
                ? vCfg.view_sql
                : ('SELECT * FROM `' + viewName + '`');
            if (sqlEditor) sqlEditor.value = existingSql;
            if (delBtn) delBtn.style.display = 'inline-flex';
            if (autoSyncChk) autoSyncChk.checked = false; // Bestehendes SQL nicht versehentlich sofort überschreiben

            // Versuchen, im bestehenden SQL erwähnte Tabellen im visuellen Builder vorab anzuhaken
            for (var tIdx = 0; tIdx < allKeys.length; tIdx++) {
                var cand = allKeys[tIdx];
                if (cand === viewName) continue;
                var reTbl = new RegExp('(?:FROM|JOIN)\\s+`?' + cand.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '`?\\b', 'i');
                if (reTbl.test(existingSql)) {
                    preselectedTables.push(cand);
                }
            }
        } else {
            if (nameInput) nameInput.value = 'v_';
            if (capInput) capInput.value = '';
            if (pkInput) pkInput.value = '';
            if (delBtn) delBtn.style.display = 'none';
            if (autoSyncChk) autoSyncChk.checked = true;

            // Falls aktuell eine Basistabelle markiert ist, diese als Startpunkt im visuellen Generator anhaken
            if (this.selectedTable && tables[this.selectedTable] && tables[this.selectedTable].type !== 'VIEW') {
                preselectedTables.push(this.selectedTable);
            } else {
                for (var b = 0; b < allKeys.length; b++) {
                    if (tables[allKeys[b]].type !== 'VIEW') {
                        preselectedTables.push(allKeys[b]);
                        break;
                    }
                }
            }
        }

        // 1A: Tabellen-Checkboxen rendern
        var tblContainer = document.getElementById('vb-tables-container');
        if (tblContainer) {
            var tHtml = '';
            for (var i = 0; i < allKeys.length; i++) {
                var tName = allKeys[i];
                if (tName === viewName) continue; // Eine View kann sich nicht selbst referenzieren
                var tMeta = tables[tName];
                var isChecked = (preselectedTables.indexOf(tName) !== -1);
                var icon = (tMeta.type === 'VIEW') ? 'fa-eye' : 'fa-table';
                var badgeColor = (tMeta.type === 'VIEW') ? '#8e44ad' : '#2b579a';
                tHtml += '<label style="display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border:1px solid #cbd5e1; border-radius:3px; background:' + (isChecked ? '#e0f2fe' : '#f8fafc') + '; cursor:pointer; font-size:11.5px; font-weight:600;">';
                tHtml += '  <input type="checkbox" class="vb-table-chk" value="' + this.escapeHtml(tName) + '"' + (isChecked ? ' checked' : '') + ' onchange="StudioApp.onVbTableSelectionChange(this);">';
                tHtml += '  <i class="fa ' + icon + '" style="color:' + badgeColor + ';"></i> ' + this.escapeHtml(tName);
                tHtml += '</label>';
            }
            tblContainer.innerHTML = tHtml;
        }

        this.rebuildVbJoinsAndColumns(viewName ? false : true);
    },

    getSelectedVbTables: function () {
        var list = [];
        var chks = document.querySelectorAll('#vb-tables-container .vb-table-chk');
        for (var i = 0; i < chks.length; i++) {
            if (chks[i].checked) {
                list.push(chks[i].value);
            }
        }
        return list;
    },

    onVbTableSelectionChange: function (chkEl) {
        if (chkEl && chkEl.parentElement) {
            chkEl.parentElement.style.background = chkEl.checked ? '#e0f2fe' : '#f8fafc';
        }
        var autoSyncChk = document.getElementById('vb-auto-sync-sql');
        if (autoSyncChk) autoSyncChk.checked = true;
        this.rebuildVbJoinsAndColumns(true);
    },

    rebuildVbJoinsAndColumns: function (triggerSqlBuild) {
        var selectedTables = this.getSelectedVbTables();
        var joinsTbody = document.getElementById('vb-joins-tbody');
        var colsTbody = document.getElementById('vb-columns-tbody');
        if (!joinsTbody || !colsTbody) return;

        joinsTbody.innerHTML = '';
        colsTbody.innerHTML = '';

        if (selectedTables.length === 0) {
            joinsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#888; padding:8px;">Wählen Sie mindestens 2 Tabellen aus, um JOIN-Verknüpfungen zu definieren.</td></tr>';
            colsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#888; padding:12px;">Bitte wählen Sie oben mindestens eine Tabelle aus.</td></tr>';
            return;
        }

        // Automatische JOIN-Erkennung für ausgewählte Tabellen ab der 2. Tabelle
        if (selectedTables.length < 2) {
            joinsTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#888; padding:6px;">Einzelne Tabelle (<code>FROM `' + this.escapeHtml(selectedTables[0]) + '`</code>). Wählen Sie weitere Tabellen für <code>JOIN</code>s an.</td></tr>';
        } else {
            for (var tIdx = 1; tIdx < selectedTables.length; tIdx++) {
                var rightTbl = selectedTables[tIdx];
                var detected = this.detectJoinConditionBetweenTables(selectedTables.slice(0, tIdx), rightTbl);
                this.addVbJoinRow('LEFT JOIN', rightTbl, detected.leftExpr, detected.rightExpr, true);
            }
        }

        // Spalten aller ausgewählten Tabellen aufbauen
        var usedColNames = {};
        var pkCandidates = [];
        for (var i = 0; i < selectedTables.length; i++) {
            var tblName = selectedTables[i];
            var tblObj = this.project.tables[tblName];
            if (!tblObj || !tblObj.fields) continue;
            var fKeys = Object.keys(tblObj.fields);
            for (var f = 0; f < fKeys.length; f++) {
                var colName = fKeys[f];
                var fMeta = tblObj.fields[colName];
                var fullExpr = '`' + tblName + '`.`' + colName + '`';
                // Falls ein Spaltenname in mehreren ausgewählten Tabellen vorkommt, automatisch Alias vorschlagen
                var suggestedAlias = '';
                if (usedColNames[colName]) {
                    suggestedAlias = tblName + '_' + colName;
                } else {
                    usedColNames[colName] = true;
                }
                var isFirstTablePk = (i === 0 && fMeta.is_primary);
                if (isFirstTablePk) {
                    pkCandidates.push(suggestedAlias || colName);
                }
                this.appendVbColumnRow(true, fullExpr, '', suggestedAlias, false, '', isFirstTablePk, true);
            }
        }

        var pkInput = document.getElementById('vb-view-pk');
        if (pkInput && pkInput.value.trim() === '' && pkCandidates.length > 0) {
            pkInput.value = pkCandidates.join(', ');
        }

        if (triggerSqlBuild) {
            this.onVbVisualChange();
        }
    },

    detectJoinConditionBetweenTables: function (priorTables, newTable) {
        var tables = this.project.tables || {};
        var newT = tables[newTable];
        var fallbackLeft = '`' + priorTables[0] + '`.`id`';
        var fallbackRight = '`' + newTable + '`.`id`';

        if (!newT) {
            return { leftExpr: fallbackLeft, rightExpr: fallbackRight };
        }

        for (var p = 0; p < priorTables.length; p++) {
            var pName = priorTables[p];
            var pT = tables[pName];
            if (!pT) continue;

            // 1. Prüfen ob pT ein Feld mit lookup_table == newTable hat
            var pFields = Object.keys(pT.fields || {});
            for (var i = 0; i < pFields.length; i++) {
                var pf = pFields[i];
                var pfMeta = pT.fields[pf];
                if (pfMeta.lookup_table === newTable && pfMeta.lookup_link_field) {
                    return {
                        leftExpr: '`' + pName + '`.`' + pf + '`',
                        rightExpr: '`' + newTable + '`.`' + pfMeta.lookup_link_field + '`'
                    };
                }
            }

            // 2. Prüfen ob newT ein Feld mit lookup_table == pName hat
            var nFields = Object.keys(newT.fields || {});
            for (var j = 0; j < nFields.length; j++) {
                var nf = nFields[j];
                var nfMeta = newT.fields[nf];
                if (nfMeta.lookup_table === pName && nfMeta.lookup_link_field) {
                    return {
                        leftExpr: '`' + pName + '`.`' + nfMeta.lookup_link_field + '`',
                        rightExpr: '`' + newTable + '`.`' + nf + '`'
                    };
                }
            }

            // 3. Prüfen auf gleichnamige *_id Spalten (z.B. kunde_id in beiden Tabellen)
            for (var k = 0; k < pFields.length; k++) {
                var candCol = pFields[k];
                if ((candCol.slice(-3) === '_id' || candCol === 'id') && newT.fields && newT.fields[candCol]) {
                    return {
                        leftExpr: '`' + pName + '`.`' + candCol + '`',
                        rightExpr: '`' + newTable + '`.`' + candCol + '`'
                    };
                }
            }
        }

        // 4. Fallback: Primärschlüssel der ersten Tabelle und der neuen Tabelle
        var leftPk = (tables[priorTables[0]] && tables[priorTables[0]].primary_keys && tables[priorTables[0]].primary_keys[0]) || 'id';
        var rightPk = (newT.primary_keys && newT.primary_keys[0]) || 'id';
        return {
            leftExpr: '`' + priorTables[0] + '`.`' + leftPk + '`',
            rightExpr: '`' + newTable + '`.`' + rightPk + '`'
        };
    },

    addVbJoinRow: function (joinType, targetTable, leftField, rightField, suppressUpdate) {
        var tbody = document.getElementById('vb-joins-tbody');
        if (!tbody) return;

        // Falls ein Platzhalter-Text drin steht, diesen entfernen
        if (tbody.querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }

        var selectedTables = this.getSelectedVbTables();
        var allTables = Object.keys(this.project.tables || {});
        var tblPool = selectedTables.length > 0 ? selectedTables : allTables;

        var jType = joinType || 'LEFT JOIN';
        var tTbl = targetTable || (tblPool.length > 1 ? tblPool[tblPool.length - 1] : (tblPool[0] || ''));
        var lExpr = leftField || '';
        var rExpr = rightField || '';

        var tr = document.createElement('tr');
        tr.className = 'vb-join-row';

        var joinOpts = ['LEFT JOIN', 'INNER JOIN', 'RIGHT JOIN'];
        var jTypeHtml = '<select class="pm-select vb-join-type" style="width:100%; font-size:11px; padding:2px 4px;" onchange="StudioApp.onVbVisualChange();">';
        for (var j = 0; j < joinOpts.length; j++) {
            jTypeHtml += '<option value="' + joinOpts[j] + '"' + (joinOpts[j] === jType ? ' selected' : '') + '>' + joinOpts[j] + '</option>';
        }
        jTypeHtml += '</select>';

        var tblOptHtml = '<select class="pm-select vb-join-table" style="width:100%; font-size:11px; padding:2px 4px;" onchange="StudioApp.onVbVisualChange();">';
        for (var t = 0; t < allTables.length; t++) {
            var tn = allTables[t];
            tblOptHtml += '<option value="' + this.escapeHtml(tn) + '"' + (tn === tTbl ? ' selected' : '') + '>' + this.escapeHtml(tn) + '</option>';
        }
        tblOptHtml += '</select>';

        tr.innerHTML =
            '<td>' + jTypeHtml + '</td>' +
            '<td>' + tblOptHtml + '</td>' +
            '<td><input type="text" class="pm-input vb-join-left" value="' + this.escapeHtml(lExpr) + '" placeholder="tab1.id" style="font-family:Consolas,monospace; font-size:11px; padding:2px 5px;" oninput="StudioApp.onVbVisualChange();"></td>' +
            '<td><input type="text" class="pm-input vb-join-right" value="' + this.escapeHtml(rExpr) + '" placeholder="tab2.tab1_id" style="font-family:Consolas,monospace; font-size:11px; padding:2px 5px;" oninput="StudioApp.onVbVisualChange();"></td>' +
            '<td style="text-align:center;"><button type="button" class="pm-btn pm-btn-danger" style="padding:1px 5px; font-size:10px;" onclick="this.closest(\'tr\').remove(); StudioApp.onVbVisualChange();" title="JOIN entfernen"><i class="fa fa-times"></i></button></td>';

        tbody.appendChild(tr);
        if (!suppressUpdate) {
            this.onVbVisualChange();
        }
    },

    appendVbColumnRow: function (checked, expr, aggrFunc, alias, groupBy, sortDir, isPk, suppressUpdate) {
        var tbody = document.getElementById('vb-columns-tbody');
        if (!tbody) return;
        if (tbody.querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }

        var tr = document.createElement('tr');
        tr.className = 'vb-col-row';

        var funcs = [
            { val: '', label: '(Keine)' },
            { val: 'COUNT', label: 'COUNT(...)' },
            { val: 'SUM', label: 'SUM(...)' },
            { val: 'AVG', label: 'AVG(...)' },
            { val: 'MIN', label: 'MIN(...)' },
            { val: 'MAX', label: 'MAX(...)' }
        ];
        var fnHtml = '<select class="pm-select vb-col-func" style="width:100%; font-size:11px; padding:2px 4px;" onchange="StudioApp.onVbVisualChange();">';
        for (var i = 0; i < funcs.length; i++) {
            fnHtml += '<option value="' + funcs[i].val + '"' + (funcs[i].val === (aggrFunc || '') ? ' selected' : '') + '>' + funcs[i].label + '</option>';
        }
        fnHtml += '</select>';

        var sortHtml = '<select class="pm-select vb-col-sort" style="width:100%; font-size:11px; padding:2px 4px;" onchange="StudioApp.onVbVisualChange();">' +
            '<option value=""' + (!sortDir ? ' selected' : '') + '>-</option>' +
            '<option value="ASC"' + (sortDir === 'ASC' ? ' selected' : '') + '>ASC &uarr;</option>' +
            '<option value="DESC"' + (sortDir === 'DESC' ? ' selected' : '') + '>DESC &darr;</option>' +
            '</select>';

        tr.innerHTML =
            '<td style="text-align:center;"><input type="checkbox" class="vb-col-chk" ' + (checked ? 'checked' : '') + ' onchange="StudioApp.onVbVisualChange();"></td>' +
            '<td><input type="text" class="pm-input vb-col-expr" value="' + this.escapeHtml(expr || '') + '" style="font-family:Consolas,monospace; font-size:11px; padding:2px 5px;" oninput="StudioApp.onVbVisualChange();"></td>' +
            '<td>' + fnHtml + '</td>' +
            '<td><input type="text" class="pm-input vb-col-alias" value="' + this.escapeHtml(alias || '') + '" placeholder="optional" style="font-family:Consolas,monospace; font-size:11px; padding:2px 5px;" oninput="StudioApp.onVbVisualChange();"></td>' +
            '<td style="text-align:center;"><input type="checkbox" class="vb-col-group" ' + (groupBy ? 'checked' : '') + ' onchange="StudioApp.onVbVisualChange();"></td>' +
            '<td>' + sortHtml + '</td>' +
            '<td style="text-align:center;"><input type="checkbox" class="vb-col-pk" ' + (isPk ? 'checked' : '') + ' onchange="StudioApp.syncVbPkFromCheckboxes();"></td>';

        tbody.appendChild(tr);
        if (!suppressUpdate) {
            this.onVbVisualChange();
        }
    },

    addVbCustomColumnRow: function () {
        this.appendVbColumnRow(true, 'COUNT(*)', '', 'anzahl', false, '', false, false);
    },

    toggleAllVbColumns: function (state) {
        var chks = document.querySelectorAll('#vb-columns-tbody .vb-col-chk');
        for (var i = 0; i < chks.length; i++) {
            chks[i].checked = !!state;
        }
        this.onVbVisualChange();
    },

    syncVbPkFromCheckboxes: function () {
        var rows = document.querySelectorAll('#vb-columns-tbody .vb-col-row');
        var pks = [];
        for (var i = 0; i < rows.length; i++) {
            var pkChk = rows[i].querySelector('.vb-col-pk');
            if (pkChk && pkChk.checked) {
                var aliasVal = rows[i].querySelector('.vb-col-alias').value.trim();
                var exprVal = rows[i].querySelector('.vb-col-expr').value.trim();
                if (aliasVal !== '') {
                    pks.push(aliasVal);
                } else if (exprVal !== '') {
                    // Spaltenname hinter dem Punkt extrahieren (`tab`.`col` -> col)
                    var parts = exprVal.replace(/`/g, '').split('.');
                    pks.push(parts[parts.length - 1]);
                }
            }
        }
        var pkInput = document.getElementById('vb-view-pk');
        if (pkInput) {
            pkInput.value = pks.join(', ');
        }
    },

    onVbVisualChange: function () {
        var autoSyncChk = document.getElementById('vb-auto-sync-sql');
        if (autoSyncChk && autoSyncChk.checked) {
            this.generateSqlFromVisualBuilder(false);
        }
    },

    generateSqlFromVisualBuilder: function (forceUpdate) {
        var selectedTables = this.getSelectedVbTables();
        if (selectedTables.length === 0) {
            if (forceUpdate) {
                alert('Bitte wählen Sie links oben mindestens eine Basistabelle aus.');
            }
            return;
        }

        var distinctChk = document.getElementById('vb-distinct-chk');
        var isDistinct = distinctChk && distinctChk.checked;

        var colRows = document.querySelectorAll('#vb-columns-tbody .vb-col-row');
        var selectCols = [];
        var groupByCols = [];
        var orderByCols = [];

        for (var i = 0; i < colRows.length; i++) {
            var row = colRows[i];
            var isChecked = row.querySelector('.vb-col-chk').checked;
            var expr = row.querySelector('.vb-col-expr').value.trim();
            var fn = row.querySelector('.vb-col-func').value.trim();
            var alias = row.querySelector('.vb-col-alias').value.trim();
            var isGroup = row.querySelector('.vb-col-group').checked;
            var sortDir = row.querySelector('.vb-col-sort').value.trim();

            if (!expr) continue;

            var fullSelectExpr = fn ? (fn + '(' + expr + ')') : expr;
            if (isChecked) {
                if (alias !== '') {
                    var safeAlias = '`' + alias.replace(/`/g, '') + '`';
                    selectCols.push('  ' + fullSelectExpr + ' AS ' + safeAlias);
                } else {
                    selectCols.push('  ' + fullSelectExpr);
                }
            }

            if (isGroup) {
                groupByCols.push(expr);
            }
            if (sortDir === 'ASC' || sortDir === 'DESC') {
                var sortRef = alias !== '' ? ('`' + alias.replace(/`/g, '') + '`') : fullSelectExpr;
                orderByCols.push(sortRef + ' ' + sortDir);
            }
        }

        if (selectCols.length === 0) {
            selectCols.push('  *');
        }

        var baseTable = selectedTables[0];
        var sqlLines = [];
        sqlLines.push('SELECT' + (isDistinct ? ' DISTINCT' : ''));
        sqlLines.push(selectCols.join(',\n'));
        sqlLines.push('FROM `' + baseTable.replace(/`/g, '') + '`');

        // JOINs auswerten
        var joinRows = document.querySelectorAll('#vb-joins-tbody .vb-join-row');
        for (var j = 0; j < joinRows.length; j++) {
            var jRow = joinRows[j];
            var jType = jRow.querySelector('.vb-join-type').value;
            var jTable = jRow.querySelector('.vb-join-table').value.trim();
            var jLeft = jRow.querySelector('.vb-join-left').value.trim();
            var jRight = jRow.querySelector('.vb-join-right').value.trim();
            if (jTable !== '' && jLeft !== '' && jRight !== '') {
                sqlLines.push(jType + ' `' + jTable.replace(/`/g, '') + '` ON ' + jLeft + ' = ' + jRight);
            }
        }

        var whereInput = document.getElementById('vb-where-input');
        if (whereInput && whereInput.value.trim() !== '') {
            var cleanWhere = whereInput.value.trim().replace(/^WHERE\s+/i, '');
            sqlLines.push('WHERE ' + cleanWhere);
        }

        if (groupByCols.length > 0) {
            sqlLines.push('GROUP BY ' + groupByCols.join(', '));
        }

        var havingInput = document.getElementById('vb-having-input');
        if (havingInput && havingInput.value.trim() !== '') {
            var cleanHaving = havingInput.value.trim().replace(/^HAVING\s+/i, '');
            sqlLines.push('HAVING ' + cleanHaving);
        }

        if (orderByCols.length > 0) {
            sqlLines.push('ORDER BY ' + orderByCols.join(', '));
        }

        var sqlEditor = document.getElementById('vb-sql-editor');
        if (sqlEditor) {
            sqlEditor.value = sqlLines.join('\n');
        }
    },

    previewViewSql: function () {
        this.syncFormToProject();
        var sqlEditor = document.getElementById('vb-sql-editor');
        var previewBox = document.getElementById('vb-preview-container');
        var statusEl = document.getElementById('vb-preview-status');
        if (!sqlEditor || !previewBox) return;

        var sql = sqlEditor.value.trim();
        if (sql === '') {
            alert('Bitte geben Sie zuerst einen SQL-SELECT-Befehl ein oder wählen Sie links Tabellen aus.');
            return;
        }

        if (statusEl) statusEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Führe Abfrage aus...';
        previewBox.innerHTML = '<div style="padding:18px; text-align:center; color:#555;"><i class="fa fa-spinner fa-spin"></i> Lade Live-Vorschau aus MySQL/MariaDB...</div>';

        this.apiCall('preview_view_sql', {
            database: this.project.database,
            sql: sql
        }, function (res) {
            if (!res.success) {
                if (statusEl) statusEl.innerHTML = '<span style="color:#dd4b39; font-weight:bold;"><i class="fa fa-exclamation-triangle"></i> SQL-Fehler</span>';
                previewBox.innerHTML = '<div style="padding:12px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:3px; font-family:Consolas,monospace; font-size:12px;">' +
                    '<strong>MySQL/MariaDB Fehlermeldung:</strong><br>' + StudioApp.escapeHtml(res.error || 'Unbekannter Fehler') + '</div>';
                return;
            }

            var cols = res.columns || [];
            var rows = res.rows || [];
            if (statusEl) {
                statusEl.innerHTML = '<span style="color:#00a65a; font-weight:bold;"><i class="fa fa-check-circle"></i> ' + rows.length + ' Zeile(n) geladen (' + cols.length + ' Spalten)</span>';
            }

            if (rows.length === 0) {
                previewBox.innerHTML = '<div style="padding:14px; color:#0369a1; background:#f0f9ff; border:1px solid #bae6fd; border-radius:3px;">' +
                    '<i class="fa fa-info-circle"></i> Die SQL-Abfrage ist syntaktisch gültig, liefert aber aktuell 0 Datensätze zurück. Sie können die View trotzdem speichern.</div>';
                return;
            }

            var tblHtml = '<table class="pm-grid-table" style="font-size:11px; margin:0;"><thead><tr>';
            for (var c = 0; c < cols.length; c++) {
                tblHtml += '<th>' + StudioApp.escapeHtml(cols[c]) + '</th>';
            }
            tblHtml += '</tr></thead><tbody>';

            for (var r = 0; r < rows.length; r++) {
                tblHtml += '<tr>';
                for (var c2 = 0; c2 < cols.length; c2++) {
                    var val = rows[r][cols[c2]];
                    var displayVal = (val === null || val === undefined)
                        ? '<span style="color:#999; font-style:italic;">NULL</span>'
                        : StudioApp.escapeHtml(String(val).length > 80 ? (String(val).substring(0, 80) + '...') : String(val));
                    tblHtml += '<td>' + displayVal + '</td>';
                }
                tblHtml += '</tr>';
            }
            tblHtml += '</tbody></table>';
            previewBox.innerHTML = tblHtml;
        });
    },

    submitViewBuilderModal: function () {
        this.syncFormToProject();
        var viewName = (document.getElementById('vb-view-name').value || '').trim();
        var caption = (document.getElementById('vb-view-caption').value || '').trim();
        var pkRaw = (document.getElementById('vb-view-pk').value || '').trim();
        var sql = (document.getElementById('vb-sql-editor').value || '').trim();

        if (viewName === '' || viewName === 'v_') {
            alert('Bitte geben Sie einen gültigen technischen Namen für die Datenbank-View ein (z. B. v_kunden_bestellungen).');
            document.getElementById('vb-view-name').focus();
            return;
        }
        if (sql === '') {
            alert('Bitte geben Sie eine SELECT-Abfrage für die View ein.');
            document.getElementById('vb-sql-editor').focus();
            return;
        }

        this.apiCall('save_database_view', {
            project: this.project,
            filename: this.currentFilename,
            view_name: viewName,
            caption: caption,
            primary_keys: pkRaw,
            sql: sql
        }, function (res) {
            if (res.success) {
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                if (res.saved_filename) {
                    StudioApp.currentFilename = res.saved_filename;
                }
                StudioApp.selectedTable = res.view_name;
                var vFields = Object.keys((StudioApp.project.tables[res.view_name] && StudioApp.project.tables[res.view_name].fields) || {});
                StudioApp.selectedField = vFields.length > 0 ? vFields[0] : null;
                StudioApp.closeViewBuilderModal();
                StudioApp.refreshAllViews();
                StudioApp.setStatus(res.message || ('View ' + res.view_name + ' gespeichert.'));
                alert(res.message);
            } else {
                alert('Fehler beim Speichern der Datenbank-View:\n' + (res.error || 'Unbekannter Fehler'));
            }
        });
    },

    deleteDatabaseView: function (viewName) {
        viewName = (viewName || '').trim();
        if (!viewName || !this.project) return;
        if (!confirm('Möchten Sie die Datenbank-View "' + viewName + '" wirklich per DROP VIEW aus Ihrer MySQL/MariaDB-Datenbank und aus dem Projekt löschen?')) {
            return;
        }
        this.syncFormToProject();
        this.apiCall('drop_database_view', {
            project: this.project,
            filename: this.currentFilename,
            view_name: viewName
        }, function (res) {
            if (res.success) {
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                if (res.saved_filename) {
                    StudioApp.currentFilename = res.saved_filename;
                }
                var remKeys = Object.keys(StudioApp.project.tables || {});
                if (StudioApp.selectedTable === viewName || !StudioApp.project.tables[StudioApp.selectedTable]) {
                    StudioApp.selectedTable = remKeys.length > 0 ? remKeys[0] : null;
                    var fKeys = StudioApp.selectedTable && StudioApp.project.tables[StudioApp.selectedTable]
                        ? Object.keys(StudioApp.project.tables[StudioApp.selectedTable].fields || {})
                        : [];
                    StudioApp.selectedField = fKeys.length > 0 ? fKeys[0] : null;
                }
                StudioApp.closeViewBuilderModal();
                StudioApp.refreshAllViews();
                StudioApp.setStatus(res.message || ('View ' + viewName + ' gelöscht.'));
            } else {
                alert('Fehler beim Löschen der View:\n' + (res.error || 'Unbekannter Fehler'));
            }
        });
    },

    deleteDatabaseViewFromModal: function () {
        var viewName = (document.getElementById('vb-existing-select').value || document.getElementById('vb-view-name').value || '').trim();
        if (!viewName || viewName === 'v_') return;
        this.deleteDatabaseView(viewName);
    },

    // =========================================================================
    // 1. MULTI-USER PROJEKT TEILEN & TEAM-ZUSAMMENARBEIT (SHARE MODAL)
    // =========================================================================
    openShareModal: function () {
        if (!this.project || !this.currentFilename) return;
        var modal = document.getElementById('pm-share-modal');
        if (!modal) return;
        modal.style.display = 'flex';

        this.apiCall('get_project_sharing', { filename: this.currentFilename }, function (res) {
            if (!res || !res.success) {
                alert((res && res.error) ? res.error : 'Freigabe-Informationen konnten nicht geladen werden.');
                return;
            }
            StudioApp.studioUsers = res.studio_users || StudioApp.studioUsers || [];
            StudioApp.shareState = {
                owner: res.owner || 'admin',
                canManage: !!res.can_manage,
                isAdmin: !!res.is_admin,
                sharedWith: StudioApp.toPlainObject(res.shared_with || {})
            };

            var nameEl = document.getElementById('share-modal-project-name');
            var fileEl = document.getElementById('share-modal-filename');
            var ownEl = document.getElementById('share-modal-owner');
            var ownSel = document.getElementById('share-owner-select');
            if (nameEl) nameEl.textContent = res.project_name || StudioApp.currentFilename;
            if (fileEl) fileEl.textContent = (res.filename || StudioApp.currentFilename) + '.pmp.json';
            if (ownEl) {
                ownEl.textContent = StudioApp.shareState.owner;
                ownEl.style.display = StudioApp.shareState.canManage ? 'none' : 'inline';
            }
            if (ownSel) {
                ownSel.style.display = StudioApp.shareState.canManage ? 'inline-block' : 'none';
                ownSel.innerHTML = '';
                var foundOwner = false;
                for (var oi = 0; oi < StudioApp.studioUsers.length; oi++) {
                    var ou = StudioApp.studioUsers[oi];
                    var oOpt = document.createElement('option');
                    oOpt.value = ou.username;
                    oOpt.textContent = ou.username + ' (' + (ou.display_name || ou.username) + ')' + (ou.active === false ? ' [Deaktiviert]' : '');
                    if (ou.username === StudioApp.shareState.owner) {
                        oOpt.selected = true;
                        foundOwner = true;
                    }
                    ownSel.appendChild(oOpt);
                }
                if (!foundOwner && StudioApp.shareState.owner) {
                    var fallbackOpt = document.createElement('option');
                    fallbackOpt.value = StudioApp.shareState.owner;
                    fallbackOpt.textContent = StudioApp.shareState.owner + ' (Ehemaliger Benutzer)';
                    fallbackOpt.selected = true;
                    ownSel.appendChild(fallbackOpt);
                }
            }

            var addCtrl = document.getElementById('share-add-controls');
            var saveBtn = document.getElementById('share-save-btn');
            if (addCtrl) addCtrl.style.display = StudioApp.shareState.canManage ? 'block' : 'none';
            if (saveBtn) saveBtn.style.display = StudioApp.shareState.canManage ? 'inline-flex' : 'none';

            StudioApp.renderShareModalContent();
        });
    },

    onShareOwnerChange: function (newOwnerUsername) {
        if (!newOwnerUsername) return;
        this.shareState.owner = newOwnerUsername;
        if (this.shareState.sharedWith && this.shareState.sharedWith[newOwnerUsername]) {
            delete this.shareState.sharedWith[newOwnerUsername];
        }
        var ownEl = document.getElementById('share-modal-owner');
        if (ownEl) ownEl.textContent = newOwnerUsername;
        this.renderShareModalContent();
    },

    closeShareModal: function () {
        var modal = document.getElementById('pm-share-modal');
        if (modal) modal.style.display = 'none';
    },

    renderShareModalContent: function () {
        var userSel = document.getElementById('share-add-user-select');
        var tbody = document.getElementById('share-collaborators-tbody');
        var owner = this.shareState.owner || 'admin';
        var sharedWith = this.shareState.sharedWith || {};
        var canManage = this.shareState.canManage;

        if (userSel) {
            userSel.innerHTML = '';
            var availableCount = 0;
            for (var i = 0; i < this.studioUsers.length; i++) {
                var u = this.studioUsers[i];
                if (u.username === owner || sharedWith[u.username]) {
                    continue;
                }
                var opt = document.createElement('option');
                opt.value = u.username;
                opt.textContent = u.username + ' (' + (u.display_name || u.username) + ')' + (u.active === false ? ' [Deaktiviert]' : '');
                userSel.appendChild(opt);
                availableCount++;
            }
            if (availableCount === 0) {
                var emptyOpt = document.createElement('option');
                emptyOpt.value = '';
                emptyOpt.textContent = '-- Alle Studio-Benutzer sind bereits freigegeben --';
                userSel.appendChild(emptyOpt);
            }
        }

        if (!tbody) return;
        var keys = Object.keys(sharedWith);
        if (keys.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:16px; color:#777;">' +
                '<i class="fa fa-lock"></i> Dieses Projekt ist aktuell <strong>privat</strong> und nur für den Eigentümer (<strong>' + this.escapeHtml(owner) + '</strong>) sichtbar.' +
                '</td></tr>';
            return;
        }

        var html = '';
        for (var k = 0; k < keys.length; k++) {
            var uName = keys[k];
            var meta = sharedWith[uName] || {};
            var role = (meta.role === 'read') ? 'read' : 'write';
            var sharedAt = meta.shared_at || '-';

            var dispName = uName;
            for (var su = 0; su < this.studioUsers.length; su++) {
                if (this.studioUsers[su].username === uName) {
                    dispName = this.studioUsers[su].display_name + ' (' + uName + ')';
                    break;
                }
            }

            html += '<tr>';
            html += '  <td><i class="fa fa-user" style="color:#2980b9;"></i> <strong>' + this.escapeHtml(dispName) + '</strong></td>';
            html += '  <td>';
            if (canManage) {
                html += '<select class="pm-select" style="width:100%;" onchange="StudioApp.changeShareCollaboratorRole(\'' + this.escapeHtml(uName) + '\', this.value);">';
                html += '  <option value="write"' + (role === 'write' ? ' selected' : '') + '>Bearbeiter (Lesen &amp; Schreiben)</option>';
                html += '  <option value="read"' + (role === 'read' ? ' selected' : '') + '>Betrachter (Nur Lesen)</option>';
                html += '</select>';
            } else {
                html += (role === 'write')
                    ? '<span style="color:#00a65a; font-weight:bold;">Bearbeiter (Lesen &amp; Schreiben)</span>'
                    : '<span style="color:#f39c12; font-weight:bold;">Betrachter (Nur Lesen)</span>';
            }
            html += '  </td>';
            html += '  <td style="font-size:11px; color:#666;">' + this.escapeHtml(sharedAt) + '</td>';
            html += '  <td style="text-align:center;">';
            if (canManage) {
                html += '<button type="button" class="pm-btn pm-btn-danger" style="padding:2px 7px; font-size:11px;" onclick="StudioApp.removeShareCollaborator(\'' + this.escapeHtml(uName) + '\');" title="Freigabe entziehen"><i class="fa fa-trash"></i></button>';
            } else {
                html += '-';
            }
            html += '  </td>';
            html += '</tr>';
        }
        tbody.innerHTML = html;
    },

    addShareCollaborator: function () {
        var userSel = document.getElementById('share-add-user-select');
        var roleSel = document.getElementById('share-add-role-select');
        if (!userSel || !userSel.value) {
            alert('Bitte wählen Sie einen Studio-Benutzer aus (oder legen Sie über die Benutzerverwaltung einen neuen Benutzer an).');
            return;
        }
        var uName = userSel.value;
        var role = (roleSel && roleSel.value === 'read') ? 'read' : 'write';
        var now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        this.shareState.sharedWith[uName] = {
            role: role,
            shared_at: now
        };
        this.renderShareModalContent();
    },

    changeShareCollaboratorRole: function (username, newRole) {
        if (!this.shareState.sharedWith[username]) return;
        this.shareState.sharedWith[username].role = (newRole === 'read') ? 'read' : 'write';
    },

    removeShareCollaborator: function (username) {
        if (this.shareState.sharedWith && this.shareState.sharedWith[username]) {
            delete this.shareState.sharedWith[username];
            this.renderShareModalContent();
        }
    },

    saveShareModal: function () {
        this.apiCall('save_project_sharing', {
            filename: this.currentFilename,
            new_owner: this.shareState.owner,
            shared_with: this.shareState.sharedWith
        }, function (res) {
            if (res && res.success) {
                if (StudioApp.project) {
                    if (res.owner) {
                        StudioApp.project.owner = res.owner;
                    }
                    StudioApp.project.shared_with = StudioApp.toPlainObject(res.shared_with || {});
                }
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.closeShareModal();
                StudioApp.setStatus(res.message || 'Projekt-Freigaben gespeichert.');
                alert(res.message || 'Team-Freigaben wurden gespeichert.');
            } else {
                alert('Fehler beim Speichern der Team-Freigaben:\n' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    // =========================================================================
    // 2. STUDIO-BENUTZERVERWALTUNG (MULTI-USER ACCOUNTS MODAL)
    // =========================================================================
    openUsersModal: function () {
        var modal = document.getElementById('pm-users-modal');
        if (!modal) return;
        modal.style.display = 'flex';
        this.cancelEditStudioUser();
        this.refreshStudioUsersModal();
    },

    closeUsersModal: function () {
        var modal = document.getElementById('pm-users-modal');
        if (modal) modal.style.display = 'none';
        this.cancelEditStudioUser();
    },

    refreshStudioUsersModal: function () {
        this.apiCall('list_studio_users', {}, function (res) {
            if (!res || !res.success) return;
            StudioApp.currentUser = res.current_user || StudioApp.currentUser;
            StudioApp.studioUsers = res.users || [];

            var isAdmin = !!(StudioApp.currentUser && StudioApp.currentUser.is_admin);
            var adminBox = document.getElementById('studio-users-admin-create-box');
            if (adminBox) {
                adminBox.style.display = isAdmin ? 'block' : 'none';
            }

            var tbody = document.getElementById('studio-users-tbody');
            if (!tbody) return;
            var html = '';
            var curUsername = (StudioApp.currentUser && StudioApp.currentUser.username) ? StudioApp.currentUser.username : '';

            for (var i = 0; i < StudioApp.studioUsers.length; i++) {
                var u = StudioApp.studioUsers[i];
                var isSelf = (u.username === curUsername);
                var isActive = (u.active !== false);
                var roleBadge = (u.role === 'admin')
                    ? '<span style="background:#8e44ad; color:#fff; padding:2px 7px; border-radius:3px; font-size:11px; font-weight:bold;"><i class="fa fa-shield"></i> Studio-Admin</span>'
                    : '<span style="background:#2980b9; color:#fff; padding:2px 7px; border-radius:3px; font-size:11px; font-weight:bold;"><i class="fa fa-code"></i> Entwickler</span>';

                var statusBadge = isActive
                    ? '<span style="background:#00a65a; color:#fff; padding:2px 7px; border-radius:3px; font-size:11px; font-weight:bold;"><i class="fa fa-check-circle"></i> Aktiv</span>'
                    : '<span style="background:#dd4b39; color:#fff; padding:2px 7px; border-radius:3px; font-size:11px; font-weight:bold;"><i class="fa fa-ban"></i> Deaktiviert</span>';

                var projCount = parseInt(u.projects_count || 0, 10);
                var projNames = Array.isArray(u.project_names) && u.project_names.length > 0 ? u.project_names.join(', ') : 'Keine eigenen Projekte';
                var projBadge = '<span style="background:' + (projCount > 0 ? '#eef5fb' : '#f4f6f9') + '; color:' + (projCount > 0 ? '#1e3c72' : '#777') + '; border:1px solid ' + (projCount > 0 ? '#b8d4f0' : '#ddd') + '; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:bold;" title="' + StudioApp.escapeHtml(projNames) + '">' +
                    '<i class="fa fa-folder-open-o"></i> ' + projCount + '</span>';

                html += '<tr style="' + (!isActive ? 'opacity:0.68; background:#fcf2f2;' : '') + '">';
                html += '  <td><code>' + StudioApp.escapeHtml(u.username) + '</code>' + (isSelf ? ' <span style="background:#00a65a; color:#fff; padding:1px 5px; border-radius:3px; font-size:10px;">Sie</span>' : '') + '</td>';
                html += '  <td><strong>' + StudioApp.escapeHtml(u.display_name || u.username) + '</strong>' +
                    (u.email ? '<div style="font-size:11px; color:#666;"><i class="fa fa-envelope-o"></i> ' + StudioApp.escapeHtml(u.email) + '</div>' : '') +
                    '</td>';
                html += '  <td>' + roleBadge + '</td>';
                html += '  <td>' + statusBadge + '</td>';
                html += '  <td style="text-align:center;">' + projBadge + '</td>';
                html += '  <td style="text-align:center; white-space:nowrap;">';
                if (isAdmin || isSelf) {
                    html += '<button type="button" class="pm-btn pm-btn-default" style="padding:2px 7px; font-size:11px; margin-right:3px;" onclick="StudioApp.openEditStudioUser(\'' + StudioApp.escapeHtml(u.username) + '\');" title="Benutzerdaten, Rolle &amp; Passwort bearbeiten"><i class="fa fa-pencil"></i> Bearbeiten</button>';
                }
                if (isAdmin) {
                    if (u.username !== 'admin' && !isSelf) {
                        if (isActive) {
                            html += '<button type="button" class="pm-btn pm-btn-warning" style="padding:2px 7px; font-size:11px; margin-right:3px;" onclick="StudioApp.toggleStudioUserActive(\'' + StudioApp.escapeHtml(u.username) + '\', false);" title="Benutzerkonto deaktivieren (Login sperren, Projekte behalten)"><i class="fa fa-ban"></i> Sperren</button>';
                        } else {
                            html += '<button type="button" class="pm-btn pm-btn-success" style="padding:2px 7px; font-size:11px; margin-right:3px;" onclick="StudioApp.toggleStudioUserActive(\'' + StudioApp.escapeHtml(u.username) + '\', true);" title="Benutzerkonto wieder aktivieren"><i class="fa fa-check"></i> Aktivieren</button>';
                        }
                        html += '<button type="button" class="pm-btn pm-btn-danger" style="padding:2px 7px; font-size:11px; background:#dd4b39; border-color:#d73925; color:#fff;" onclick="StudioApp.deleteStudioUser(\'' + StudioApp.escapeHtml(u.username) + '\');" title="Benutzer löschen (Zugeordnete Projekte bleiben erhalten und gehen an den Admin über)"><i class="fa fa-trash"></i> Löschen</button>';
                    } else if (u.username === 'admin') {
                        html += '<span style="font-size:10.5px; color:#888;" title="Das Haupt-Administratorkonto kann nicht gelöscht oder gesperrt werden"><i class="fa fa-lock"></i> Haupt-Admin</span>';
                    } else if (isSelf) {
                        html += '<span style="font-size:10.5px; color:#888;" title="Sie sind aktuell mit diesem Konto angemeldet"><i class="fa fa-user"></i> Aktives Konto</span>';
                    }
                }
                html += '  </td>';
                html += '</tr>';
            }
            tbody.innerHTML = html;
        });
    },

    createStudioUser: function () {
        var uName = (document.getElementById('su-new-username').value || '').trim();
        var disp = (document.getElementById('su-new-displayname').value || '').trim();
        var emailEl = document.getElementById('su-new-email');
        var email = emailEl ? (emailEl.value || '').trim() : '';
        var pw = (document.getElementById('su-new-password').value || '');
        var role = (document.getElementById('su-new-role').value || 'developer');

        if (!uName || !pw) {
            alert('Bitte Benutzername und Start-Passwort (mind. 6 Zeichen) eingeben.');
            return;
        }
        this.apiCall('create_studio_user', {
            username: uName,
            display_name: disp,
            email: email,
            password: pw,
            role: role
        }, function (res) {
            if (res && res.success) {
                document.getElementById('su-new-username').value = '';
                document.getElementById('su-new-displayname').value = '';
                if (emailEl) emailEl.value = '';
                document.getElementById('su-new-password').value = '';
                StudioApp.refreshStudioUsersModal();
                alert(res.message || 'Benutzer angelegt.');
            } else {
                alert('Fehler: ' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    openEditStudioUser: function (username) {
        var target = null;
        for (var i = 0; i < (this.studioUsers || []).length; i++) {
            if (this.studioUsers[i].username === username) {
                target = this.studioUsers[i];
                break;
            }
        }
        if (!target) return;

        var box = document.getElementById('studio-users-edit-box');
        if (!box) return;
        box.style.display = 'block';

        var isAdmin = !!(this.currentUser && this.currentUser.is_admin);
        var isSelf = (this.currentUser && this.currentUser.username === target.username);

        document.getElementById('su-edit-username').value = target.username;
        document.getElementById('su-edit-username-label').textContent = target.username;
        document.getElementById('su-edit-displayname').value = target.display_name || target.username;
        document.getElementById('su-edit-email').value = target.email || '';
        document.getElementById('su-edit-password').value = '';

        var roleSel = document.getElementById('su-edit-role');
        if (roleSel) {
            roleSel.value = target.role || 'developer';
            roleSel.disabled = (!isAdmin || target.username === 'admin');
        }
        var actSel = document.getElementById('su-edit-active');
        if (actSel) {
            actSel.value = (target.active !== false) ? '1' : '0';
            actSel.disabled = (!isAdmin || target.username === 'admin' || isSelf);
        }
        document.getElementById('su-edit-displayname').focus();
    },

    cancelEditStudioUser: function () {
        var box = document.getElementById('studio-users-edit-box');
        if (box) box.style.display = 'none';
    },

    saveEditStudioUser: function () {
        var uName = (document.getElementById('su-edit-username').value || '').trim();
        if (!uName) return;
        var disp = (document.getElementById('su-edit-displayname').value || '').trim();
        var email = (document.getElementById('su-edit-email').value || '').trim();
        var role = (document.getElementById('su-edit-role').value || 'developer');
        var activeVal = (document.getElementById('su-edit-active').value === '1');
        var newPw = (document.getElementById('su-edit-password').value || '');

        this.apiCall('update_studio_user', {
            username: uName,
            display_name: disp,
            email: email,
            role: role,
            active: activeVal,
            new_password: newPw
        }, function (res) {
            if (res && res.success) {
                StudioApp.cancelEditStudioUser();
                StudioApp.refreshStudioUsersModal();
                alert(res.message || 'Benutzer erfolgreich aktualisiert.');
            } else {
                alert('Fehler: ' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    toggleStudioUserActive: function (username, makeActive) {
        var actionText = makeActive
            ? ('Möchten Sie das Studio-Benutzerkonto "' + username + '" wieder aktivieren?')
            : ('Möchten Sie das Studio-Benutzerkonto "' + username + '" deaktivieren (Login sperren)?\nAlle Projekte des Benutzers bleiben unverändert bestehen.');
        if (!confirm(actionText)) return;

        this.apiCall('update_studio_user', {
            username: username,
            active: !!makeActive
        }, function (res) {
            if (res && res.success) {
                StudioApp.refreshStudioUsersModal();
            } else {
                alert('Fehler: ' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    resetStudioUserPassword: function (username) {
        this.openEditStudioUser(username);
    },

    deleteStudioUser: function (username) {
        var projCount = 0;
        var projList = '';
        for (var i = 0; i < (this.studioUsers || []).length; i++) {
            if (this.studioUsers[i].username === username) {
                projCount = parseInt(this.studioUsers[i].projects_count || 0, 10);
                if (Array.isArray(this.studioUsers[i].project_names) && this.studioUsers[i].project_names.length > 0) {
                    projList = '\n\nZugeordnete Projekte (' + projCount + '):\n- ' + this.studioUsers[i].project_names.join('\n- ');
                }
                break;
            }
        }
        var curAdmin = (this.currentUser && this.currentUser.username) ? this.currentUser.username : 'admin';
        var msg = 'Möchten Sie das Studio-Benutzerkonto "' + username + '" wirklich löschen?' +
            projList +
            '\n\nHinweis: Sämtliche Projekte dieses Benutzers bleiben vollständig erhalten und werden automatisch an "' + curAdmin + '" übertragen, sodass Sie diese jederzeit teilen oder einem anderen Benutzer neu zuordnen können.';

        if (!confirm(msg)) {
            return;
        }
        this.apiCall('delete_studio_user', {
            username: username,
            reassign_to: curAdmin
        }, function (res) {
            if (res && res.success) {
                StudioApp.cancelEditStudioUser();
                StudioApp.refreshStudioUsersModal();
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                alert(res.message || ('Benutzer "' + username + '" wurde gelöscht.'));
            } else {
                alert('Fehler: ' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    // =========================================================================
    // 3. GIT-VERSIONIERUNG (COMMITS, BRANCHES, TAGS, DIFF & ROLLBACK)
    // =========================================================================
    openGitModal: function () {
        if (!this.project || !this.currentFilename) return;
        this.syncFormToProject();
        var modal = document.getElementById('pm-git-modal');
        if (!modal) return;
        modal.style.display = 'flex';
        this.refreshGitStatus(true);
    },

    closeGitModal: function () {
        var modal = document.getElementById('pm-git-modal');
        if (modal) modal.style.display = 'none';
    },

    refreshGitStatus: function (autoSelectLatest) {
        this.syncFormToProject();
        this.apiCall('git_status', {
            filename: this.currentFilename,
            project: this.project
        }, function (res) {
            if (!res || !res.success || !res.git_status) {
                alert((res && res.error) ? res.error : 'Git-Status konnte nicht geladen werden.');
                return;
            }
            StudioApp.gitStatus = res.git_status;
            StudioApp.updateTopbarCollabAndGitBadges();
            StudioApp.renderGitModalUI(autoSelectLatest);
        });
    },

    renderGitModalUI: function (autoSelectLatest) {
        var st = this.gitStatus;
        if (!st) return;

        var titleEl = document.getElementById('git-modal-project-title');
        var branchPill = document.getElementById('git-modal-branch-pill');
        var headPill = document.getElementById('git-modal-head-pill');
        var countLabel = document.getElementById('git-commit-count-label');

        if (titleEl) titleEl.textContent = (this.project && this.project.project_name) ? this.project.project_name : this.currentFilename;
        if (branchPill) branchPill.innerHTML = '<i class="fa fa-code-fork"></i> ' + this.escapeHtml(st.current_branch || 'main');
        if (headPill) headPill.textContent = 'HEAD: ' + (st.head_commit_id || '-');
        if (countLabel) countLabel.textContent = String((st.commits && st.commits.length) || 0);

        // Branch-Auswahl füllen
        var bSel = document.getElementById('git-branch-select');
        if (bSel) {
            bSel.innerHTML = '';
            var branches = st.branches || { main: '' };
            var bNames = Object.keys(branches);
            for (var b = 0; b < bNames.length; b++) {
                var bName = bNames[b];
                var opt = document.createElement('option');
                opt.value = bName;
                opt.textContent = bName + (branches[bName] ? (' (' + branches[bName] + ')') : '');
                if (bName === st.current_branch) {
                    opt.selected = true;
                }
                bSel.appendChild(opt);
            }
        }

        // Uncommittete Änderungen im Arbeitsstand (Working Tree Banner)
        var banner = document.getElementById('git-working-banner');
        var summaryTxt = document.getElementById('git-working-summary-text');
        if (banner) {
            if (st.has_uncommitted && st.working_diff) {
                banner.style.display = 'flex';
                if (summaryTxt) {
                    summaryTxt.textContent = st.working_diff.short_summary || 'Lokale Änderungen vorhanden';
                }
            } else {
                banner.style.display = 'none';
            }
        }

        // Commit-Historie rendern
        var listBox = document.getElementById('git-commits-list');
        if (!listBox) return;

        var commits = st.commits || [];
        if (commits.length === 0) {
            listBox.innerHTML = '<div style="padding:18px; text-align:center; color:#777;">Noch keine Commits vorhanden.</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < commits.length; i++) {
            var c = commits[i];
            var isHead = !!c.is_head;
            var branchBadges = '';
            if (c.branch_heads && c.branch_heads.length > 0) {
                for (var bh = 0; bh < c.branch_heads.length; bh++) {
                    branchBadges += '<span style="background:#8e44ad; color:#fff; padding:1px 6px; border-radius:3px; font-size:10px; font-family:Consolas,monospace; margin-right:4px;"><i class="fa fa-code-fork"></i> ' + this.escapeHtml(c.branch_heads[bh]) + '</span>';
                }
            }
            var tagBadges = '';
            if (c.tags && c.tags.length > 0) {
                for (var tg = 0; tg < c.tags.length; tg++) {
                    var tName = c.tags[tg];
                    tagBadges += '<span style="background:#f39c12; color:#fff; padding:1px 6px; border-radius:3px; font-size:10px; font-family:Consolas,monospace; margin-right:4px;" title="Klick zum Löschen des Tags" onclick="event.stopPropagation(); StudioApp.gitDeleteTag(\'' + this.escapeHtml(tName) + '\');"><i class="fa fa-tag"></i> ' + this.escapeHtml(tName) + ' &times;</span>';
                }
            }

            html += '<div class="git-commit-card" id="git-commit-card-' + this.escapeHtml(c.id) + '" onclick="StudioApp.gitInspectCommit(\'' + this.escapeHtml(c.id) + '\', \'' + this.escapeHtml(c.parent || '') + '\');" style="background:#fff; border:1px solid ' + (isHead ? '#8e44ad' : '#dce4ec') + '; border-left:4px solid ' + (isHead ? '#8e44ad' : '#3c8dbc') + '; border-radius:3px; padding:9px 11px; cursor:pointer; transition:background 0.15s;">';
            html += '  <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; margin-bottom:4px; flex-wrap:wrap;">';
            html += '    <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">';
            html += '      <code style="background:#eef1f6; color:#2c3e50; padding:1px 6px; border-radius:3px; font-weight:bold; font-size:11.5px;">' + this.escapeHtml(c.id) + '</code>';
            if (isHead) {
                html += '<span style="background:#00a65a; color:#fff; padding:1px 5px; border-radius:3px; font-size:10px; font-weight:bold;">HEAD</span>';
            }
            html += branchBadges + tagBadges;
            html += '    </div>';
            html += '    <span style="font-size:11px; color:#666;"><i class="fa fa-clock-o"></i> ' + this.escapeHtml(c.timestamp || '') + '</span>';
            html += '  </div>';
            html += '  <div style="font-size:12.5px; font-weight:700; color:#222; margin-bottom:5px;">' + this.escapeHtml(c.message || '') + '</div>';
            html += '  <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#555;">';
            html += '    <span><i class="fa fa-user"></i> ' + this.escapeHtml(c.author_display || c.author || 'admin') + ' &bull; <span style="color:#4a235a;">' + this.escapeHtml(c.short_summary || '') + '</span></span>';
            html += '    <div style="display:flex; gap:4px;" onclick="event.stopPropagation();">';
            html += '      <button type="button" class="pm-btn pm-btn-default" style="padding:1px 6px; font-size:10.5px;" onclick="StudioApp.gitPromptCreateTag(\'' + this.escapeHtml(c.id) + '\');" title="Tag auf diesen Commit setzen"><i class="fa fa-tag"></i></button>';
            html += '      <button type="button" class="pm-btn pm-btn-warning" style="padding:1px 7px; font-size:10.5px;" onclick="StudioApp.gitCheckoutCommit(\'' + this.escapeHtml(c.id) + '\');" title="Projekt auf genau diesen Commit-Stand zurücksetzen (Rollback / Restore)"><i class="fa fa-undo"></i> Wiederherstellen</button>';
            html += '    </div>';
            html += '  </div>';
            html += '</div>';
        }
        listBox.innerHTML = html;

        if (autoSelectLatest) {
            if (st.has_uncommitted) {
                this.gitCompareWorkingTree();
            } else if (commits.length > 0) {
                this.gitInspectCommit(commits[0].id, commits[0].parent || '');
            }
        }
    },

    gitCreateCommit: function () {
        if (this.currentAccessRole === 'read') {
            alert('Schreibschutz: Betrachter können keine Git-Commits anlegen.');
            return;
        }
        this.syncFormToProject();
        var msgInput = document.getElementById('git-commit-message');
        var msg = msgInput ? msgInput.value.trim() : '';
        if (msg === '') {
            msg = prompt('Bitte geben Sie eine kurze Commit-Beschreibung für diesen Versionsstand ein:', 'Projekt-Aktualisierung');
            if (msg === null) return;
        }

        this.apiCall('git_commit', {
            filename: this.currentFilename,
            project: this.project,
            message: msg
        }, function (res) {
            if (res && res.success) {
                if (msgInput) msgInput.value = '';
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.renderGitModalUI(true);
                StudioApp.setStatus('Git Commit erfolgreich erstellt (' + ((res.commit && res.commit.id) || res.commit_id || '') + ').');
            } else {
                alert('Fehler beim Erstellen des Commits:\n' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    gitInspectCommit: function (commitId, parentCommitId) {
        var base = parentCommitId || 'EMPTY';
        this.apiCall('git_diff', {
            filename: this.currentFilename,
            base_commit: base,
            target_commit: commitId
        }, function (res) {
            if (!res || !res.success) return;
            var hdr = document.getElementById('git-diff-header-title');
            var act = document.getElementById('git-diff-actions');
            if (hdr) {
                hdr.innerHTML = '<i class="fa fa-exchange"></i> Commit <code>' + StudioApp.escapeHtml(commitId) + '</code> im Vergleich zu Vorgänger <code>' + StudioApp.escapeHtml(parentCommitId || 'Initial') + '</code>';
            }
            if (act) {
                act.innerHTML = '<button type="button" class="pm-btn pm-btn-warning" style="padding:2px 8px; font-size:11px;" onclick="StudioApp.gitCheckoutCommit(\'' + StudioApp.escapeHtml(commitId) + '\');"><i class="fa fa-undo"></i> Diesen Stand (' + StudioApp.escapeHtml(commitId) + ') wiederherstellen</button>';
            }
            StudioApp.renderGitDiffBoxes(res.structural, res.unified_diff);
        });
    },

    gitCompareWorkingTree: function () {
        this.syncFormToProject();
        var headId = (this.gitStatus && this.gitStatus.head_commit_id) ? this.gitStatus.head_commit_id : 'EMPTY';
        this.apiCall('git_diff', {
            filename: this.currentFilename,
            base_commit: headId,
            target_commit: 'WORKING',
            project: this.project
        }, function (res) {
            if (!res || !res.success) return;
            var hdr = document.getElementById('git-diff-header-title');
            var act = document.getElementById('git-diff-actions');
            if (hdr) {
                hdr.innerHTML = '<i class="fa fa-exchange"></i> Aktueller Arbeitsstand im Vergleich zu HEAD (<code>' + StudioApp.escapeHtml(headId) + '</code>)';
            }
            if (act) {
                act.innerHTML = '';
            }
            StudioApp.renderGitDiffBoxes(res.structural, res.unified_diff);
        });
    },

    renderGitDiffBoxes: function (structural, unifiedLines) {
        var structBox = document.getElementById('git-structural-diff-box');
        var uniBox = document.getElementById('git-unified-diff-box');

        if (structBox) {
            var items = (structural && structural.items) ? structural.items : [];
            if (items.length === 0) {
                structBox.innerHTML = '<div style="color:#00a65a; font-weight:bold;"><i class="fa fa-check-circle"></i> Keine strukturellen Unterschiede zwischen diesen beiden Ständen.</div>';
            } else {
                var sHtml = '<table class="pm-grid-table" style="margin:0; font-size:11.5px;"><thead><tr><th style="width:90px;">Status</th><th style="width:120px;">Bereich</th><th style="width:180px;">Element</th><th>Änderungs-Details</th></tr></thead><tbody>';
                for (var i = 0; i < items.length; i++) {
                    var it = items[i];
                    var badge = '';
                    if (it.type === 'added') {
                        badge = '<span style="background:#00a65a; color:#fff; padding:1px 6px; border-radius:3px; font-size:10.5px; font-weight:bold;">+ NEU</span>';
                    } else if (it.type === 'deleted') {
                        badge = '<span style="background:#dd4b39; color:#fff; padding:1px 6px; border-radius:3px; font-size:10.5px; font-weight:bold;">- GELÖSCHT</span>';
                    } else {
                        badge = '<span style="background:#f39c12; color:#fff; padding:1px 6px; border-radius:3px; font-size:10.5px; font-weight:bold;">~ GEÄNDERT</span>';
                    }
                    sHtml += '<tr>';
                    sHtml += '  <td>' + badge + '</td>';
                    sHtml += '  <td><strong>' + this.escapeHtml(it.category || '') + '</strong></td>';
                    sHtml += '  <td><code>' + this.escapeHtml(it.path || '') + '</code></td>';
                    sHtml += '  <td>' + this.escapeHtml(it.detail || '') + '</td>';
                    sHtml += '</tr>';
                }
                sHtml += '</tbody></table>';
                structBox.innerHTML = sHtml;
            }
        }

        if (uniBox) {
            var lines = Array.isArray(unifiedLines) ? unifiedLines : [];
            if (lines.length === 0) {
                uniBox.innerHTML = '<div style="color:#2ecc71; padding:8px;">// Identischer JSON-Projektstand (0 Zeilen Differenz)</div>';
            } else {
                var uHtml = '';
                for (var l = 0; l < lines.length; l++) {
                    var ln = lines[l];
                    var bg = 'transparent';
                    var fg = '#d2dae2';
                    var prefix = '  ';
                    if (ln.type === 'add') {
                        bg = 'rgba(46, 204, 113, 0.18)';
                        fg = '#7bed9f';
                        prefix = '+ ';
                    } else if (ln.type === 'del') {
                        bg = 'rgba(231, 76, 60, 0.22)';
                        fg = '#ff7f50';
                        prefix = '- ';
                    } else if (ln.type === 'info') {
                        fg = '#f1c40f';
                        prefix = '@@ ';
                    }
                    var lineNum = (ln.new_ln || ln.old_ln || '');
                    uHtml += '<div style="background:' + bg + '; color:' + fg + '; padding:1px 6px; white-space:pre;">' +
                        '<span style="display:inline-block; width:42px; color:#7f8c8d; user-select:none;">' + this.escapeHtml(lineNum) + '</span>' +
                        this.escapeHtml(prefix + (ln.text || '')) + '</div>';
                }
                uniBox.innerHTML = uHtml;
            }
        }
    },

    gitCheckoutCommit: function (commitId) {
        if (!commitId) return;
        if (this.currentAccessRole === 'read') {
            alert('Schreibschutz: Betrachter können keinen Rollback durchführen.');
            return;
        }
        if (!confirm('Möchten Sie das Projekt wirklich auf den Versionsstand von Commit "' + commitId + '" zurücksetzen?\n\nHinweis: PHPMacherNG legt vor der Wiederherstellung automatisch einen Sicherheits-Commit Ihres aktuellen Arbeitsstands an.')) {
            return;
        }
        this.apiCall('git_checkout_commit', {
            filename: this.currentFilename,
            commit_id: commitId
        }, function (res) {
            if (res && res.success && res.project) {
                StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                StudioApp.refreshAllViews();
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.renderGitModalUI(true);
                StudioApp.setStatus(res.message || ('Rollback auf Commit ' + commitId + ' ausgeführt.'));
                alert(res.message || ('Projekt auf Commit ' + commitId + ' wiederhergestellt!'));
            } else {
                alert('Fehler beim Rollback:\n' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    gitPromptCreateBranch: function () {
        var name = prompt('Name für den neuen Git-Branch eingeben (z. B. feature/neue-berichte oder entwicklung):', 'feature/');
        if (!name || name.trim() === '' || name.trim() === 'feature/') return;
        this.apiCall('git_branch_create', {
            filename: this.currentFilename,
            branch_name: name.trim(),
            switch_branch: true
        }, function (res) {
            if (res && res.success) {
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.renderGitModalUI(true);
                StudioApp.setStatus(res.message || 'Branch erstellt.');
            } else {
                alert('Fehler: ' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    gitSwitchBranch: function (branchName) {
        if (!branchName) return;
        this.syncFormToProject();
        this.apiCall('git_branch_switch', {
            filename: this.currentFilename,
            branch_name: branchName
        }, function (res) {
            if (res && res.success) {
                if (res.project) {
                    StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                    StudioApp.refreshAllViews();
                }
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.renderGitModalUI(true);
                StudioApp.setStatus(res.message || ('Auf Branch ' + branchName + ' gewechselt.'));
            } else {
                alert('Fehler beim Branch-Wechsel:\n' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    gitPromptMergeBranch: function () {
        if (!this.gitStatus || !this.gitStatus.branches) return;
        var cur = this.gitStatus.current_branch || 'main';
        var otherBranches = [];
        var bKeys = Object.keys(this.gitStatus.branches);
        for (var i = 0; i < bKeys.length; i++) {
            if (bKeys[i] !== cur) {
                otherBranches.push(bKeys[i]);
            }
        }
        if (otherBranches.length === 0) {
            alert('Es existiert aktuell nur der Branch "' + cur + '". Legen Sie zuerst über "+ Branch" einen weiteren Branch an.');
            return;
        }
        var source = prompt('Welcher Branch soll in den aktuellen Branch "' + cur + '" zusammengeführt (gemerged) werden?\nVerfügbare Branches: ' + otherBranches.join(', '), otherBranches[0]);
        if (!source || source.trim() === '') return;

        this.apiCall('git_branch_merge', {
            filename: this.currentFilename,
            source_branch: source.trim()
        }, function (res) {
            if (res && res.success) {
                if (res.project) {
                    StudioApp.project = StudioApp.normalizeProjectStructure(res.project);
                    StudioApp.refreshAllViews();
                }
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                if (res.projects) {
                    StudioApp.renderProjectSelectorOptions(res.projects);
                }
                StudioApp.updateTopbarCollabAndGitBadges();
                StudioApp.renderGitModalUI(true);
                alert(res.message || 'Branch erfolgreich gemerged!');
            } else {
                alert('Merge-Fehler:\n' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    gitPromptCreateTag: function (commitId) {
        var tagName = prompt('Name für das Git Release-Tag eingeben (z. B. v1.0, Release-Kandidat):', 'v1.0');
        if (!tagName || tagName.trim() === '') return;
        this.apiCall('git_tag_create', {
            filename: this.currentFilename,
            tag_name: tagName.trim(),
            commit_id: commitId || ''
        }, function (res) {
            if (res && res.success) {
                if (res.git_status) {
                    StudioApp.gitStatus = res.git_status;
                }
                StudioApp.renderGitModalUI(false);
                StudioApp.setStatus(res.message || 'Tag gesetzt.');
            } else {
                alert('Fehler: ' + ((res && res.error) ? res.error : 'Unbekannter Fehler'));
            }
        });
    },

    gitDeleteTag: function (tagName) {
        if (!tagName) return;
        if (!confirm('Tag "' + tagName + '" entfernen?')) return;
        this.apiCall('git_tag_delete', {
            filename: this.currentFilename,
            tag_name: tagName
        }, function (res) {
            if (res && res.success && res.git_status) {
                StudioApp.gitStatus = res.git_status;
                StudioApp.renderGitModalUI(false);
            }
        });
    },

    escapeHtml: function (str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', function () {
    StudioApp.init();
});
