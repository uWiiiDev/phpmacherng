<?php
/**
 * PHPMacherNG - AJAX Backend API (api.php)
 * Kompatibel mit PHP 5.4 bis PHP 8.4+
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__FILE__) . '/src/StudioAuth.php';
require_once dirname(__FILE__) . '/src/DatabaseInspector.php';
require_once dirname(__FILE__) . '/src/ProjectManager.php';
require_once dirname(__FILE__) . '/src/GeneratorEngine.php';
require_once dirname(__FILE__) . '/src/ZipBuilder.php';

if (!StudioAuth::isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array(
        'success' => false,
        'error' => 'Nicht im PHPMacherNG angemeldet. Bitte laden Sie die Seite neu und geben Sie das Studio-Passwort ein.'
    ));
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = @json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($payload['action']) ? $payload['action'] : '');
$pm = new ProjectManager();
$currentUser = StudioAuth::getCurrentUsername();
$currentUserProfile = StudioAuth::getCurrentUser();
$isStudioAdmin = StudioAuth::isAdmin();

switch ($action) {

    case 'list_projects':
        echo json_encode(array(
            'success'      => true,
            'projects'     => $pm->listProjects($currentUser, $isStudioAdmin),
            'current_user' => $currentUserProfile,
            'studio_users' => StudioAuth::listPublicUsers(),
            'php_version'  => PHP_VERSION
        ));
        break;

    case 'load_project':
        $filename = isset($payload['filename']) ? $payload['filename'] : (isset($_GET['filename']) ? $_GET['filename'] : '');
        $visibleProjects = $pm->listProjects($currentUser, $isStudioAdmin);
        $resolvedPath = $pm->getProjectFilePath($filename);

        // Falls das angefragte Projekt nicht existiert oder der Benutzer keinen Zugriff darauf hat:
        if ($resolvedPath !== false && is_file($resolvedPath)) {
            $rawChk = @file_get_contents($resolvedPath);
            $chkData = ($rawChk !== false) ? ProjectManager::decodeProjectJson($rawChk) : null;
            $chkRole = is_array($chkData) ? ProjectManager::getUserAccessRole($chkData, $currentUser) : '';
            if ($chkRole === '' && !$isStudioAdmin) {
                if (!empty($visibleProjects) && isset($visibleProjects[0]['filename'])) {
                    $filename = $visibleProjects[0]['filename'];
                    $resolvedPath = $pm->getProjectFilePath($filename);
                } else {
                    $newOwnSlug = 'projekt_' . $currentUser;
                    $ownProj = ProjectManager::createDefaultProject('Projekt (' . $currentUser . ')', $currentUser);
                    $pm->saveProject($newOwnSlug, $ownProj, $currentUser);
                    $filename = $newOwnSlug;
                    $resolvedPath = $pm->getProjectFilePath($filename);
                }
            }
        } elseif (empty($visibleProjects)) {
            $newOwnSlug = ($filename !== '' && $filename !== 'crm_shop_demo') ? ProjectManager::sanitizeProjectSlug($filename) : ('projekt_' . $currentUser);
            $ownProj = ProjectManager::createDefaultProject('Projekt (' . $currentUser . ')', $currentUser);
            $pm->saveProject($newOwnSlug, $ownProj, $currentUser);
            $filename = $newOwnSlug;
            $resolvedPath = $pm->getProjectFilePath($filename);
        } elseif (!empty($visibleProjects) && isset($visibleProjects[0]['filename'])) {
            $filename = $visibleProjects[0]['filename'];
            $resolvedPath = $pm->getProjectFilePath($filename);
        }

        $proj = $pm->loadProject($filename);
        if ($proj === false) {
            $errMsg = 'Projektdatei konnte nicht geladen werden.';
            if ($resolvedPath !== false && !is_readable($resolvedPath)) {
                $errMsg .= ' Fehlende Leseberechtigung für "' . basename($resolvedPath) . '" im Verzeichnis projects/ (bitte Dateirechte z.B. chmod 664 / chown www-data prüfen).';
            } elseif ($resolvedPath !== false) {
                $errMsg .= ' Die Datei "' . basename($resolvedPath) . '" enthält ungültiges JSON oder eine inkompatible Zeichenkodierung.';
            }
            echo json_encode(array('success' => false, 'error' => $errMsg));
        } else {
            if ($resolvedPath !== false) {
                $actualFilename = ProjectManager::sanitizeProjectSlug(basename($resolvedPath));
            } else {
                $existingList = $pm->listProjects($currentUser, $isStudioAdmin);
                $actualFilename = (!empty($existingList) && isset($existingList[0]['filename']))
                    ? $existingList[0]['filename']
                    : ProjectManager::sanitizeProjectSlug(isset($proj['project_name']) ? $proj['project_name'] : 'MeinProjekt');
            }
            $accessRole = ProjectManager::getUserAccessRole($proj, $currentUser);
            if ($accessRole === '' && $isStudioAdmin) {
                $accessRole = 'owner';
            }
            $gitStatus = $pm->vcs->getStatus($actualFilename, $proj);
            echo json_encode(array(
                'success'      => true,
                'project'      => $proj,
                'filename'     => $actualFilename,
                'access_role'  => $accessRole !== '' ? $accessRole : 'owner',
                'git_status'   => $gitStatus,
                'current_user' => $currentUserProfile,
                'projects'     => $pm->listProjects($currentUser, $isStudioAdmin)
            ));
        }
        break;

    case 'new_project':
        $name = isset($payload['project_name']) && trim($payload['project_name']) !== '' ? trim($payload['project_name']) : 'MeinProjekt';
        $proj = ProjectManager::createDefaultProject($name, $currentUser);
        echo json_encode(array('success' => true, 'project' => $proj, 'access_role' => 'owner'));
        break;

    case 'save_project':
        $projectData = isset($payload['project']) ? $payload['project'] : null;
        $filename = isset($payload['filename']) && trim($payload['filename']) !== ''
            ? trim($payload['filename'])
            : (isset($projectData['project_name']) ? $projectData['project_name'] : 'project');

        if (!is_array($projectData)) {
            echo json_encode(array('success' => false, 'error' => 'Ungültige Projektdaten.'));
            break;
        }

        // Zugriffsrecht prüfen, falls die Projektdatei bereits existiert
        $existingFile = $pm->getProjectFilePath($filename);
        if ($existingFile !== false && is_file($existingFile)) {
            $rawEx = @file_get_contents($existingFile);
            $exData = ($rawEx !== false) ? ProjectManager::decodeProjectJson($rawEx) : null;
            if (is_array($exData)) {
                $role = ProjectManager::getUserAccessRole($exData, $currentUser);
                if ($role === 'read') {
                    echo json_encode(array(
                        'success' => false,
                        'error'   => 'Schreibschutz: Dieses Projekt wurde für Sie nur mit Lese-Berechtigung ("Betrachter") freigegeben. Nur der Eigentümer (' . (isset($exData['owner']) ? $exData['owner'] : 'admin') . ') oder Bearbeiter mit Schreibrecht können Änderungen speichern.'
                    ));
                    break;
                }
                if ($role === '' && !$isStudioAdmin) {
                    // Falls Dateiname bereits einem anderen Benutzer gehört, eigenen Suffix anhängen
                    $filename = ProjectManager::sanitizeProjectSlug($filename) . '_' . $currentUser;
                }
            }
        }

        $savedName = $pm->saveProject($filename, $projectData, $currentUser);
        if ($savedName !== false) {
            $autoCommitMsg = isset($payload['commit_message']) ? trim((string)$payload['commit_message']) : '';
            if ($autoCommitMsg !== '') {
                $dispName = isset($currentUserProfile['display_name']) ? $currentUserProfile['display_name'] : $currentUser;
                $pm->vcs->commit($savedName, $projectData, $autoCommitMsg, $currentUser, $dispName, true);
            }
            $loadedSaved = $pm->loadProject($savedName);
            $gitStatus = $pm->vcs->getStatus($savedName, is_array($loadedSaved) ? $loadedSaved : $projectData);
            echo json_encode(array(
                'success'     => true,
                'filename'    => $savedName,
                'git_status'  => $gitStatus,
                'projects'    => $pm->listProjects($currentUser, $isStudioAdmin)
            ));
        } else {
            echo json_encode(array(
                'success' => false,
                'error' => 'Projektdatei konnte nicht im Verzeichnis projects/ gespeichert werden. Bitte prüfen Sie die Schreibrechte des Webservers (z.B. chown -R www-data:www-data projects output && chmod -R 775 projects output).'
            ));
        }
        break;

    case 'delete_project':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : (isset($_GET['filename']) ? trim((string)$_GET['filename']) : '');
        if ($filename === '') {
            echo json_encode(array('success' => false, 'error' => 'Kein Projektname zum Löschen angegeben.'));
            break;
        }
        $existingFile = $pm->getProjectFilePath($filename);
        if ($existingFile !== false && is_file($existingFile)) {
            $rawEx = @file_get_contents($existingFile);
            $exData = ($rawEx !== false) ? ProjectManager::decodeProjectJson($rawEx) : null;
            if (is_array($exData)) {
                $role = ProjectManager::getUserAccessRole($exData, $currentUser);
                if ($role !== 'owner' && !$isStudioAdmin) {
                    echo json_encode(array(
                        'success' => false,
                        'error'   => 'Nur der Projekteigentümer (' . (isset($exData['owner']) ? $exData['owner'] : 'admin') . ') darf dieses Projekt endgültig löschen.'
                    ));
                    break;
                }
            }
        }
        if ($pm->deleteProject($filename)) {
            $remaining = $pm->listProjects($currentUser, $isStudioAdmin);
            echo json_encode(array(
                'success' => true,
                'projects' => $remaining,
                'message' => 'Projekt "' . $filename . '" wurde gelöscht.'
            ));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Projektdatei "' . $filename . '" konnte nicht gefunden oder nicht gelöscht werden.'));
        }
        break;

    case 'download_project':
        $filename = isset($_GET['filename']) ? trim((string)$_GET['filename']) : '';
        $filePath = $pm->getProjectFilePath($filename);
        if ($filePath === false || !is_file($filePath)) {
            header('Content-Type: text/plain; charset=utf-8');
            header('HTTP/1.1 404 Not Found');
            echo 'Projektdatei "' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '" wurde nicht gefunden. Bitte speichern Sie das Projekt zuerst.';
            exit;
        }
        $rawDl = @file_get_contents($filePath);
        $dlData = ($rawDl !== false) ? ProjectManager::decodeProjectJson($rawDl) : null;
        if (is_array($dlData) && ProjectManager::getUserAccessRole($dlData, $currentUser) === '' && !$isStudioAdmin) {
            header('Content-Type: text/plain; charset=utf-8');
            header('HTTP/1.1 403 Forbidden');
            echo 'Zugriff verweigert: Sie haben keine Berechtigung für dieses Projekt.';
            exit;
        }
        $dlName = basename($filePath);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $dlName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($filePath);
        exit;

    case 'upload_project':
        $jsonContent = '';
        $suggestedName = '';
        if (isset($_FILES['project_file']) && isset($_FILES['project_file']['tmp_name']) && is_uploaded_file($_FILES['project_file']['tmp_name'])) {
            $jsonContent = @file_get_contents($_FILES['project_file']['tmp_name']);
            $origName = isset($_FILES['project_file']['name']) ? (string)$_FILES['project_file']['name'] : 'imported_project.pmp.json';
            $suggestedName = ProjectManager::sanitizeProjectSlug($origName);
        } elseif (isset($payload['project_json']) && is_string($payload['project_json'])) {
            $jsonContent = $payload['project_json'];
            $suggestedName = isset($payload['filename']) ? ProjectManager::sanitizeProjectSlug((string)$payload['filename']) : '';
        }

        if ($jsonContent === '' || $jsonContent === false) {
            echo json_encode(array('success' => false, 'error' => 'Keine gültige Projektdatei (.pmp.json) empfangen.'));
            break;
        }
        $decoded = ProjectManager::decodeProjectJson($jsonContent);
        if (!is_array($decoded) || (!isset($decoded['project_name']) && !isset($decoded['tables']))) {
            echo json_encode(array('success' => false, 'error' => 'Die hochgeladene Datei ist keine gültige PHPMacherNG Projektdatei (.pmp.json).'));
            break;
        }
        if ($suggestedName === '') {
            $suggestedName = isset($decoded['project_name']) ? ProjectManager::sanitizeProjectSlug((string)$decoded['project_name']) : ('projekt_' . time());
        }
        // Falls bereits ein fremdes Projekt gleichen Namens existiert, eigenen Benutzernamen anhängen
        $exUploadPath = $pm->getProjectFilePath($suggestedName);
        if ($exUploadPath !== false && is_file($exUploadPath)) {
            $exUploadData = ProjectManager::decodeProjectJson(@file_get_contents($exUploadPath));
            if (is_array($exUploadData) && ProjectManager::getUserAccessRole($exUploadData, $currentUser) === '' && !$isStudioAdmin) {
                $suggestedName .= '_' . $currentUser;
            }
        }
        $decoded['owner'] = $currentUser;
        if (!isset($decoded['shared_with']) || !is_array($decoded['shared_with'])) {
            $decoded['shared_with'] = array();
        }
        $savedName = $pm->saveProject($suggestedName, $decoded, $currentUser);
        if ($savedName === false) {
            echo json_encode(array(
                'success' => false,
                'error' => 'Hochgeladenes Projekt konnte nicht im Verzeichnis projects/ gespeichert werden. Bitte prüfen Sie die Schreibrechte des Webservers auf den Ordner projects/.'
            ));
            break;
        }
        $loadedProj = $pm->loadProject($savedName);
        echo json_encode(array(
            'success' => true,
            'filename' => $savedName,
            'project' => $loadedProj,
            'access_role' => 'owner',
            'git_status' => $pm->vcs->getStatus($savedName, $loadedProj),
            'projects' => $pm->listProjects($currentUser, $isStudioAdmin),
            'message' => 'Projekt "' . $savedName . '" erfolgreich importiert und geladen!'
        ));
        break;

    case 'add_table_fields':
        $project = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : null;
        $tableName = isset($payload['table_name']) ? trim((string)$payload['table_name']) : '';
        $newFields = isset($payload['fields']) && is_array($payload['fields']) ? $payload['fields'] : array();
        $alterDb = isset($payload['alter_db']) ? (bool)$payload['alter_db'] : true;

        if (!is_array($project) || $tableName === '' || !isset($project['tables'][$tableName])) {
            echo json_encode(array('success' => false, 'error' => 'Ungültige Tabelle oder fehlende Projektdaten.'));
            break;
        }
        if (empty($newFields)) {
            echo json_encode(array('success' => false, 'error' => 'Bitte geben Sie mindestens ein neues Feld an.'));
            break;
        }

        $tConfig = $project['tables'][$tableName];
        $isView = (isset($tConfig['type']) && strtoupper($tConfig['type']) === 'VIEW');
        $dbCfg = isset($project['database']) && is_array($project['database']) ? $project['database'] : array();
        $inspector = null;
        $dbAlteredCols = array();
        $dbErrors = array();

        if ($alterDb && !$isView && !empty($dbCfg['dbname'])) {
            $inspector = new DatabaseInspector($dbCfg);
            if (!$inspector->connect(true)) {
                $dbErrors[] = 'DB-Verbindung nicht möglich (' . $inspector->lastError . ') – Felder wurden nur im Projekt angelegt.';
                $inspector = null;
            }
        }

        $addedNames = array();
        foreach ($newFields as $nf) {
            $rawColName = isset($nf['name']) ? trim((string)$nf['name']) : '';
            $colName = preg_replace('/[^a-zA-Z0-9_]/', '_', $rawColName);
            if ($colName === '' || preg_match('/^[0-9]/', $colName)) {
                continue;
            }
            $caption = isset($nf['caption']) && trim((string)$nf['caption']) !== ''
                ? trim((string)$nf['caption'])
                : DatabaseInspector::humanizeName($colName);
            $preset = isset($nf['preset']) ? strtoupper(trim((string)$nf['preset'])) : 'VARCHAR';
            $length = isset($nf['length']) && (int)$nf['length'] > 0 ? (int)$nf['length'] : 255;
            $required = !empty($nf['required']);
            $defaultVal = isset($nf['default_value']) ? trim((string)$nf['default_value']) : '';

            // SQL-Typ, Edit Tag und View Tag aus dem gewählten Feldtyp-Preset ableiten
            $sqlType = 'varchar(' . $length . ')';
            $forceEditTag = '';
            $forceViewTag = '';
            $forceValidation = '';
            $forceOptions = '';

            switch ($preset) {
                case 'SIGNATURE':
                    $sqlType = 'longtext';
                    $forceEditTag = 'SIGNATURE';
                    $forceViewTag = 'SIGNATURE';
                    break;
                case 'VARCHAR':
                case 'TEXT_SHORT':
                    $sqlType = 'varchar(' . max(1, min(2000, $length)) . ')';
                    $forceEditTag = 'TEXT';
                    $forceViewTag = 'FORMATTED TEXT';
                    break;
                case 'TEXTAREA':
                case 'TEXT':
                    $sqlType = 'text';
                    $forceEditTag = 'TEXTAREA';
                    $forceViewTag = 'FORMATTED TEXT';
                    break;
                case 'HTML':
                    $sqlType = 'longtext';
                    $forceEditTag = 'TEXTAREA';
                    $forceViewTag = 'HTML';
                    break;
                case 'INT':
                case 'INTEGER':
                    $sqlType = 'int(11)';
                    $forceEditTag = 'TEXT';
                    $forceViewTag = 'NUMBER';
                    $forceValidation = 'Integer';
                    break;
                case 'DECIMAL':
                    $sqlType = 'decimal(10,2)';
                    $forceEditTag = 'TEXT';
                    $forceViewTag = 'NUMBER';
                    $forceValidation = 'Float';
                    break;
                case 'CURRENCY':
                    $sqlType = 'decimal(10,2)';
                    $forceEditTag = 'TEXT';
                    $forceViewTag = 'CURRENCY';
                    $forceValidation = 'Float';
                    break;
                case 'BOOLEAN':
                case 'CHECKBOX':
                    $sqlType = 'tinyint(1)';
                    $forceEditTag = 'CHECKBOX';
                    $forceViewTag = 'BOOLEAN';
                    $forceOptions = '1:Ja,0:Nein';
                    if ($defaultVal === '') $defaultVal = '0';
                    break;
                case 'DATE':
                    $sqlType = 'date';
                    $forceEditTag = 'DATE';
                    $forceViewTag = 'DATE';
                    $forceValidation = 'Date';
                    break;
                case 'DATETIME':
                    $sqlType = 'datetime';
                    $forceEditTag = 'DATE';
                    $forceViewTag = 'DATETIME';
                    $forceValidation = 'Date';
                    break;
                case 'SELECT':
                    $sqlType = 'varchar(' . max(1, min(255, $length)) . ')';
                    $forceEditTag = 'SELECT';
                    $forceViewTag = 'FORMATTED TEXT';
                    $forceOptions = isset($nf['options']) && trim((string)$nf['options']) !== '' ? trim((string)$nf['options']) : 'Option1:Option 1,Option2:Option 2';
                    break;
                case 'RADIO':
                    $sqlType = 'varchar(100)';
                    $forceEditTag = 'RADIO';
                    $forceViewTag = 'FORMATTED TEXT';
                    $forceOptions = isset($nf['options']) && trim((string)$nf['options']) !== '' ? trim((string)$nf['options']) : '1:Ja,0:Nein';
                    break;
                case 'EMAIL':
                    $sqlType = 'varchar(190)';
                    $forceEditTag = 'TEXT';
                    $forceViewTag = 'EMAIL';
                    $forceValidation = 'Email';
                    break;
                case 'LINK':
                case 'URL':
                    $sqlType = 'varchar(255)';
                    $forceEditTag = 'TEXT';
                    $forceViewTag = 'LINK';
                    break;
                case 'IMAGE':
                    $sqlType = 'longtext';
                    $forceEditTag = 'TEXT';
                    $forceViewTag = 'IMAGE';
                    break;
                case 'PASSWORD':
                    $sqlType = 'varchar(255)';
                    $forceEditTag = 'PASSWORD';
                    $forceViewTag = 'FORMATTED TEXT';
                    break;
                default:
                    if (!empty($nf['sql_type'])) {
                        $sqlType = strtolower(trim((string)$nf['sql_type']));
                    }
                    break;
            }

            // 1. Optional per ALTER TABLE direkt in der MySQL/MariaDB-Tabelle anlegen
            if ($inspector !== null) {
                $safeTbl = '`' . str_replace('`', '``', $tableName) . '`';
                $safeCol = '`' . str_replace('`', '``', $colName) . '`';
                $existsRows = $inspector->queryAll("SHOW COLUMNS FROM " . $safeTbl . " LIKE '" . addslashes($colName) . "'");
                if (is_array($existsRows) && count($existsRows) === 0) {
                    $parsedForDb = DatabaseInspector::parseSqlType($sqlType);
                    $isTextOrBlob = in_array($parsedForDb['ew_type'], array('MEMO', 'BLOB'));
                    $nullSql = ($required && !$isTextOrBlob && $defaultVal !== '') ? 'NOT NULL' : 'NULL';
                    $defSql = '';
                    if (!$isTextOrBlob && $defaultVal !== '') {
                        $defSql = " DEFAULT '" . addslashes($defaultVal) . "'";
                    }
                    $commentSql = " COMMENT '" . addslashes($caption) . "'";
                    $alterSql = "ALTER TABLE " . $safeTbl . " ADD COLUMN " . $safeCol . " " . $sqlType . " " . $nullSql . $defSql . $commentSql;
                    $resAlter = $inspector->queryAll($alterSql);
                    if ($resAlter === false && $inspector->lastError !== '') {
                        $dbErrors[] = 'SQL-Hinweis bei `' . $colName . '`: ' . $inspector->lastError;
                    } else {
                        $dbAlteredCols[] = $colName;
                    }
                }
            }

            // 2. PHPMacherNG-Feld-Metadaten aufbauen und in $project['tables'][$tableName]['fields'][$colName] eintragen
            $parsedType = DatabaseInspector::parseSqlType($sqlType);
            $fieldMeta = DatabaseInspector::buildDefaultFieldConfig(
                $colName,
                $sqlType,
                $parsedType,
                !$required,
                false,
                false,
                $defaultVal !== '' ? $defaultVal : null,
                $caption,
                null
            );
            $fieldMeta['caption'] = $caption;
            $fieldMeta['required'] = $required;
            if ($forceEditTag !== '') {
                $fieldMeta['edit_tag'] = $forceEditTag;
            }
            if ($forceViewTag !== '') {
                $fieldMeta['view_tag'] = $forceViewTag;
            }
            if ($forceValidation !== '') {
                $fieldMeta['validation'] = $forceValidation;
            }
            if ($forceOptions !== '') {
                $fieldMeta['options'] = $forceOptions;
            }
            if ($fieldMeta['edit_tag'] === 'SIGNATURE') {
                // Unterschriften standardmäßig auch in der Listenansicht als kompaktes Thumbnail anzeigen!
                $fieldMeta['list'] = true;
                $fieldMeta['add'] = true;
                $fieldMeta['edit'] = true;
                $fieldMeta['view'] = true;
                $fieldMeta['search'] = false;
                $fieldMeta['allow_sort'] = false;
            }

            $project['tables'][$tableName]['fields'][$colName] = $fieldMeta;
            $addedNames[] = $colName;
        }

        if (empty($addedNames)) {
            echo json_encode(array('success' => false, 'error' => 'Keine gültigen Feldnamen angegeben.'));
            break;
        }

        $fNameSave = isset($payload['filename']) && trim((string)$payload['filename']) !== ''
            ? trim((string)$payload['filename'])
            : (isset($project['project_name']) ? $project['project_name'] : 'crm_shop_demo');
        $savedName = $pm->saveProject($fNameSave, $project, $currentUser);

        echo json_encode(array(
            'success' => true,
            'project' => $project,
            'saved_filename' => $savedName,
            'added_fields' => $addedNames,
            'db_altered' => $dbAlteredCols,
            'db_warnings' => $dbErrors,
            'message' => count($addedNames) . ' Feld(er) (' . implode(', ', $addedNames) . ') erfolgreich zu "' . $tableName . '" hinzugefügt!'
        ));
        break;

    case 'preview_view_sql':
        $dbCfg = isset($payload['database']) && is_array($payload['database']) ? $payload['database'] : array();
        $sqlRaw = isset($payload['sql']) ? trim((string)$payload['sql']) : '';
        $sqlClean = rtrim($sqlRaw, "; \t\n\r\0\x0B");
        if ($sqlClean === '') {
            echo json_encode(array('success' => false, 'error' => 'Bitte geben Sie eine SELECT-Abfrage ein.'));
            break;
        }
        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $sqlClean)) {
            echo json_encode(array('success' => false, 'error' => 'Aus Sicherheitsgründen sind in der View-Vorschau nur SELECT-Abfragen (oder WITH ... SELECT) zulässig.'));
            break;
        }

        $inspector = new DatabaseInspector($dbCfg);
        if (!$inspector->connect(true)) {
            echo json_encode(array('success' => false, 'error' => 'DB-Verbindung fehlgeschlagen: ' . $inspector->lastError));
            break;
        }

        // Falls kein LIMIT am Ende steht, für die Vorschau auf 15 Zeilen begrenzen
        $previewSql = $sqlClean;
        if (!preg_match('/\bLIMIT\s+\d+/i', $previewSql)) {
            $previewSql .= ' LIMIT 15';
        }

        $rows = $inspector->queryAll($previewSql);
        if ($rows === false) {
            echo json_encode(array(
                'success' => false,
                'error' => 'SQL-Fehler: ' . $inspector->lastError
            ));
            break;
        }

        $columns = array();
        if (!empty($rows) && is_array($rows[0])) {
            $columns = array_keys($rows[0]);
        }

        echo json_encode(array(
            'success' => true,
            'columns' => $columns,
            'rows' => $rows,
            'executed_sql' => $previewSql
        ));
        break;

    case 'save_database_view':
        $project = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : null;
        if (!is_array($project)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Projektdaten übergeben.'));
            break;
        }
        $viewNameRaw = isset($payload['view_name']) ? trim((string)$payload['view_name']) : '';
        $viewName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $viewNameRaw));
        $viewName = preg_replace('/_+/', '_', trim($viewName, '_'));
        $caption = isset($payload['caption']) ? trim((string)$payload['caption']) : '';
        $selectSql = isset($payload['sql']) ? trim((string)$payload['sql']) : '';
        $primaryKeysRaw = isset($payload['primary_keys']) ? $payload['primary_keys'] : array();

        if ($viewName === '' || is_numeric(substr($viewName, 0, 1))) {
            echo json_encode(array('success' => false, 'error' => 'Bitte geben Sie einen gültigen View-Namen ein (z. B. v_bestellungen_details).'));
            break;
        }

        // Prüfen, ob bereits eine Basistabelle (BASE TABLE) mit diesem Namen existiert
        if (isset($project['tables'][$viewName]) && isset($project['tables'][$viewName]['type']) && $project['tables'][$viewName]['type'] === 'TABLE') {
            echo json_encode(array('success' => false, 'error' => 'Es existiert bereits eine physische Datenbanktabelle "' . $viewName . '". Bitte wählen Sie einen anderen Namen für die View (z. B. "v_' . $viewName . '").'));
            break;
        }

        $dbCfg = isset($project['database']) && is_array($project['database']) ? $project['database'] : array();
        $inspector = new DatabaseInspector($dbCfg);
        if (!$inspector->saveDatabaseView($viewName, $selectSql)) {
            echo json_encode(array('success' => false, 'error' => 'View konnte nicht in der Datenbank angelegt werden: ' . $inspector->lastError));
            break;
        }

        // Schema neu einlesen, damit alle Spalten und Typen der View exakt aus MySQL/MariaDB übernommen werden
        $inspectedTables = $inspector->inspectSchema();
        if ($inspectedTables === false) {
            echo json_encode(array('success' => false, 'error' => 'View wurde gespeichert, aber Schema-Sync schlug fehl: ' . $inspector->lastError));
            break;
        }

        // In $inspectedTables sicherstellen, dass unser formatierter SQL-Text in view_sql steht
        $cleanSelectSql = rtrim($selectSql, "; \t\n\r\0\x0B");
        if (isset($inspectedTables[$viewName])) {
            $inspectedTables[$viewName]['view_sql'] = $cleanSelectSql;
        }

        $syncResult = ProjectManager::synchronizeSchema($project, $inspectedTables);
        $syncedProject = $syncResult['project'];

        if (isset($syncedProject['tables'][$viewName])) {
            $syncedProject['tables'][$viewName]['type'] = 'VIEW';
            $syncedProject['tables'][$viewName]['view_sql'] = $cleanSelectSql;
            if ($caption !== '') {
                $syncedProject['tables'][$viewName]['caption'] = $caption;
                // Auch im Menü-Eintrag aktualisieren
                if (isset($syncedProject['menu_items']) && is_array($syncedProject['menu_items'])) {
                    foreach ($syncedProject['menu_items'] as $mIdx => $mItem) {
                        if (isset($mItem['source_type']) && $mItem['source_type'] === 'table' && isset($mItem['source_name']) && $mItem['source_name'] === $viewName) {
                            $syncedProject['menu_items'][$mIdx]['text'] = $caption;
                        }
                    }
                }
            }

            // Primärschlüssel für die View setzen (wichtig für Detailansicht *view.php & Master/Detail)
            $pkList = array();
            if (is_array($primaryKeysRaw)) {
                foreach ($primaryKeysRaw as $pkItem) {
                    $pkClean = trim((string)$pkItem);
                    if ($pkClean !== '' && isset($syncedProject['tables'][$viewName]['fields'][$pkClean])) {
                        $pkList[] = $pkClean;
                    }
                }
            } elseif (is_string($primaryKeysRaw) && trim($primaryKeysRaw) !== '') {
                foreach (explode(',', $primaryKeysRaw) as $pkPart) {
                    $pkClean = trim($pkPart);
                    if ($pkClean !== '' && isset($syncedProject['tables'][$viewName]['fields'][$pkClean])) {
                        $pkList[] = $pkClean;
                    }
                }
            }

            if (!empty($pkList)) {
                $syncedProject['tables'][$viewName]['primary_keys'] = array_values(array_unique($pkList));
                $syncedProject['tables'][$viewName]['view_page'] = true;
                foreach ($syncedProject['tables'][$viewName]['fields'] as $fKey => $fMeta) {
                    $syncedProject['tables'][$viewName]['fields'][$fKey]['is_primary'] = in_array($fKey, $pkList, true);
                }
            } else {
                // Falls kein PK gewählt wurde, erstes Feld als virtuellen PK vorschlagen, falls es auf _id oder id endet
                $autoPk = array();
                foreach ($syncedProject['tables'][$viewName]['fields'] as $fKey => $fMeta) {
                    if ($fKey === 'id' || substr($fKey, -3) === '_id') {
                        $autoPk[] = $fKey;
                        $syncedProject['tables'][$viewName]['fields'][$fKey]['is_primary'] = true;
                        break;
                    }
                }
                $syncedProject['tables'][$viewName]['primary_keys'] = $autoPk;
                $syncedProject['tables'][$viewName]['view_page'] = !empty($autoPk);
            }
        }

        $syncedProject['menu_items'] = ProjectManager::synchronizeMenuItems($syncedProject);

        $fNameSave = isset($payload['filename']) && trim((string)$payload['filename']) !== ''
            ? trim((string)$payload['filename'])
            : (isset($syncedProject['project_name']) ? $syncedProject['project_name'] : 'crm_shop_demo');
        $savedName = $pm->saveProject($fNameSave, $syncedProject, $currentUser);

        echo json_encode(array(
            'success' => true,
            'project' => $syncedProject,
            'saved_filename' => $savedName,
            'view_name' => $viewName,
            'message' => 'Datenbank-View `' . $viewName . '` wurde erfolgreich in MySQL/MariaDB gespeichert und mit dem Projekt synchronisiert!'
        ));
        break;

    case 'drop_database_view':
        $project = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : null;
        $viewName = isset($payload['view_name']) ? trim((string)$payload['view_name']) : '';
        if (!is_array($project) || $viewName === '') {
            echo json_encode(array('success' => false, 'error' => 'Ungültige Parameter zum Löschen der View.'));
            break;
        }

        // Sicherheitscheck: Keine physische Basistabelle (TABLE) über diesen Endpunkt löschen
        if (isset($project['tables'][$viewName]['type']) && strtoupper((string)$project['tables'][$viewName]['type']) === 'TABLE') {
            echo json_encode(array('success' => false, 'error' => 'Die Tabelle "' . $viewName . '" ist eine physische Datenbanktabelle und keine View.'));
            break;
        }

        $dbCfg = isset($project['database']) && is_array($project['database']) ? $project['database'] : array();
        $dbDropped = false;
        $dbWarning = '';
        if (!empty($dbCfg['dbname'])) {
            $inspector = new DatabaseInspector($dbCfg);
            if ($inspector->connect(true)) {
                if ($inspector->dropDatabaseView($viewName)) {
                    $dbDropped = true;
                } else {
                    $dbWarning = 'Hinweis: DROP VIEW in DB meldete: ' . $inspector->lastError;
                }
            } else {
                $dbWarning = 'Hinweis: DB aktuell nicht erreichbar (' . $inspector->lastError . '), View wurde aus der Projektkonfiguration entfernt.';
            }
        }

        if (isset($project['tables'][$viewName])) {
            unset($project['tables'][$viewName]);
        }
        if (isset($project['security']['static_permissions'][$viewName])) {
            unset($project['security']['static_permissions'][$viewName]);
        }
        if (isset($project['server_events']['tables'][$viewName])) {
            unset($project['server_events']['tables'][$viewName]);
        }
        if (isset($project['client_scripts']['tables'][$viewName])) {
            unset($project['client_scripts']['tables'][$viewName]);
        }
        if (isset($project['settings']['default_table']) && $project['settings']['default_table'] === $viewName) {
            $remTables = isset($project['tables']) && is_array($project['tables']) ? array_keys($project['tables']) : array();
            $project['settings']['default_table'] = !empty($remTables) ? $remTables[0] : '';
        }

        $project['tables'] = ProjectManager::synchronizeMasterDetails(isset($project['tables']) ? $project['tables'] : array());
        $project['menu_items'] = ProjectManager::synchronizeMenuItems($project);

        // Falls im Ausgabeordner bereits generierte Skripte für diese View liegen, diese ebenfalls bereinigen
        $relOut = isset($project['settings']['output_folder']) ? trim((string)$project['settings']['output_folder']) : '';
        if ($relOut !== '') {
            $relOutClean = ltrim(str_replace(array('\\', '..'), array('/', ''), $relOut), '/');
            $outDir = dirname(__FILE__) . '/' . $relOutClean;
            if (is_dir($outDir)) {
                $vSlug = GeneratorEngine::tableSlug($viewName);
                $suffixes = array('info.php', 'list.php', 'preview.php', 'view.php', 'add.php', 'edit.php', 'delete.php', 'update.php');
                foreach ($suffixes as $sfx) {
                    $oldFile = $outDir . '/' . $vSlug . $sfx;
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }
            }
        }

        $fNameSave = isset($payload['filename']) && trim((string)$payload['filename']) !== ''
            ? trim((string)$payload['filename'])
            : (isset($project['project_name']) ? $project['project_name'] : 'crm_shop_demo');
        $savedName = $pm->saveProject($fNameSave, $project, $currentUser);

        $msg = 'Datenbank-View `' . $viewName . '` wurde erfolgreich gelöscht und aus dem Projekt entfernt.';
        if ($dbWarning !== '') {
            $msg .= ' (' . $dbWarning . ')';
        }

        echo json_encode(array(
            'success' => true,
            'project' => $project,
            'saved_filename' => $savedName,
            'view_name' => $viewName,
            'db_dropped' => $dbDropped,
            'message' => $msg
        ));
        break;

    case 'download_generated_zip':
        $outputFolder = isset($_GET['output_folder']) ? trim((string)$_GET['output_folder']) : '';
        $projectName = isset($_GET['filename']) ? trim((string)$_GET['filename']) : '';

        if ($outputFolder === '' && $projectName !== '') {
            $proj = $pm->loadProject($projectName);
            if (is_array($proj) && !empty($proj['settings']['output_folder'])) {
                $outputFolder = $proj['settings']['output_folder'];
            }
        }
        if ($outputFolder === '') {
            $outputFolder = 'output/crm_shop_demo';
        }

        // Sicherheitscheck: Nur Verzeichnisse unterhalb des Studio-Wurzelverzeichnisses erlauben
        $cleanRel = ltrim(str_replace(array('\\', '..'), array('/', ''), $outputFolder), '/');
        $absOutDir = realpath(dirname(__FILE__) . '/' . $cleanRel);
        $studioRoot = realpath(dirname(__FILE__));

        if ($absOutDir === false || !is_dir($absOutDir) || strpos($absOutDir, $studioRoot) !== 0) {
            header('Content-Type: text/plain; charset=utf-8');
            header('HTTP/1.1 404 Not Found');
            echo 'Das Ausgabeverzeichnis "' . htmlspecialchars($outputFolder, ENT_QUOTES, 'UTF-8') . '" existiert noch nicht. Bitte klicken Sie zuerst auf "Generieren (F9)".';
            exit;
        }

        $folderBase = basename($absOutDir);
        $zipBinary = ZipBuilder::createZipFromDirectory($absOutDir, $folderBase);
        if ($zipBinary === false || strlen($zipBinary) === 0) {
            header('Content-Type: text/plain; charset=utf-8');
            header('HTTP/1.1 500 Internal Server Error');
            echo 'ZIP-Archiv konnte nicht erstellt werden.';
            exit;
        }

        $zipFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folderBase) . '_generated_' . date('Ymd_His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
        header('Content-Length: ' . strlen($zipBinary));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $zipBinary;
        exit;

    case 'list_databases':
        $dbCfg = isset($payload['database']) && is_array($payload['database']) ? $payload['database'] : array();
        $inspector = new DatabaseInspector($dbCfg);
        $dbs = $inspector->listDatabases();
        if ($dbs === false) {
            echo json_encode(array('success' => false, 'error' => $inspector->lastError));
        } else {
            echo json_encode(array('success' => true, 'databases' => $dbs, 'driver' => $inspector->driver));
        }
        break;

    case 'connect_and_sync':
        $project = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : ProjectManager::createDefaultProject();
        $dbCfg = isset($project['database']) && is_array($project['database']) ? $project['database'] : array();
        $inspector = new DatabaseInspector($dbCfg);
        $inspectedTables = $inspector->inspectSchema();

        if ($inspectedTables === false) {
            echo json_encode(array(
                'success' => false,
                'error' => $inspector->lastError
            ));
            break;
        }

        $syncResult = ProjectManager::synchronizeSchema($project, $inspectedTables);
        echo json_encode(array(
            'success' => true,
            'project' => $syncResult['project'],
            'report' => $syncResult['report'],
            'driver' => $inspector->driver
        ));
        break;

    case 'install_demo_db':
        $dbCfg = isset($payload['database']) && is_array($payload['database']) ? $payload['database'] : array();
        $dbName = isset($dbCfg['dbname']) && trim($dbCfg['dbname']) !== '' ? trim($dbCfg['dbname']) : 'phpmaker_demo';
        $inspector = new DatabaseInspector($dbCfg);
        if (!$inspector->connect(false)) {
            echo json_encode(array('success' => false, 'error' => $inspector->lastError));
            break;
        }

        $sqlFile = dirname(__FILE__) . '/database_demo.sql';
        if (!is_file($sqlFile)) {
            echo json_encode(array('success' => false, 'error' => 'database_demo.sql nicht gefunden.'));
            break;
        }

        $safeDb = '`' . str_replace('`', '``', $dbName) . '`';
        $inspector->queryAll('CREATE DATABASE IF NOT EXISTS ' . $safeDb . ' DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci');
        $inspector->dbname = $dbName;
        if (!$inspector->connect(true)) {
            echo json_encode(array('success' => false, 'error' => $inspector->lastError));
            break;
        }

        $sqlContent = file_get_contents($sqlFile);
        // Kommentare entfernen und Statements einzeln ausführen
        $lines = explode("\n", $sqlContent);
        $cleanSql = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            $cleanSql .= $line . "\n";
        }
        $statements = explode(';', $cleanSql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                $inspector->queryAll($stmt);
            }
        }

        echo json_encode(array(
            'success' => true,
            'dbname' => $dbName,
            'message' => 'Demo-Datenbank "' . $dbName . '" wurde erfolgreich angelegt und befüllt.'
        ));
        break;

    case 'create_security_tables':
        $project = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : ProjectManager::createDefaultProject();
        $dbCfg = isset($project['database']) && is_array($project['database']) ? $project['database'] : array();
        $sec = isset($project['security']) && is_array($project['security'])
            ? array_merge(ProjectManager::createDefaultSecurityConfig(), $project['security'])
            : ProjectManager::createDefaultSecurityConfig();

        $inspector = new DatabaseInspector($dbCfg);
        if (!$inspector->connect(true)) {
            echo json_encode(array('success' => false, 'error' => 'DB-Verbindung fehlgeschlagen: ' . $inspector->lastError));
            break;
        }

        $userTable = preg_replace('/[^a-zA-Z0-9_]/', '', $sec['user_table']) ?: 'benutzer';
        $lvlTable = preg_replace('/[^a-zA-Z0-9_]/', '', $sec['user_level_table']) ?: 'userlevels';
        $privTable = preg_replace('/[^a-zA-Z0-9_]/', '', $sec['user_level_priv_table']) ?: 'userlevelpermissions';

        // 1. User Levels Tabelle (PHPMacherNG Standard: userlevels)
        $sqlLvl = "CREATE TABLE IF NOT EXISTS `" . $lvlTable . "` (
            `userlevelid` int(11) NOT NULL COMMENT 'Rollen-ID',
            `userlevelname` varchar(80) NOT NULL COMMENT 'Rollen-Bezeichnung',
            PRIMARY KEY (`userlevelid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $inspector->queryAll($sqlLvl);

        // Standard-Rollen eintragen (-2 Anonymous, -1 Administrator, 0 Default, 1 Manager, 2 ReadOnly)
        $levels = isset($sec['static_user_levels']) && is_array($sec['static_user_levels'])
            ? $sec['static_user_levels']
            : array(
                array('id' => -2, 'name' => 'Anonymous'),
                array('id' => -1, 'name' => 'Administrator'),
                array('id' => 0, 'name' => 'Default (Mitarbeiter)'),
                array('id' => 1, 'name' => 'Manager (Abteilungsleiter)'),
                array('id' => 2, 'name' => 'ReadOnly (Nur Lesen)')
            );
        foreach ($levels as $lvl) {
            $lid = (int)$lvl['id'];
            $lname = "'" . addslashes($lvl['name']) . "'";
            $inspector->queryAll("REPLACE INTO `" . $lvlTable . "` (`userlevelid`, `userlevelname`) VALUES (" . $lid . ", " . $lname . ")");
        }

        // 2. User Level Permissions Tabelle (PHPMacherNG Standard: userlevelpermissions)
        $sqlPriv = "CREATE TABLE IF NOT EXISTS `" . $privTable . "` (
            `tablename` varchar(190) NOT NULL COMMENT 'Tabelle',
            `userlevelid` int(11) NOT NULL COMMENT 'Rollen-ID',
            `permission` int(11) NOT NULL DEFAULT '0' COMMENT 'Berechtigungs-Bitmaske',
            PRIMARY KEY (`tablename`, `userlevelid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $inspector->queryAll($sqlPriv);

        // 3. Benutzer-Tabelle (benutzer)
        $sqlUsers = "CREATE TABLE IF NOT EXISTS `" . $userTable . "` (
            `benutzer_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'User-ID',
            `benutzername` varchar(80) NOT NULL COMMENT 'Benutzername',
            `passwort` varchar(255) NOT NULL COMMENT 'Passwort',
            `anzeigename` varchar(120) NOT NULL COMMENT 'Voller Name',
            `email` varchar(120) DEFAULT NULL COMMENT 'E-Mail',
            `userlevel_id` int(11) NOT NULL DEFAULT '0' COMMENT 'Rolle (User Level)',
            `vorgesetzter_id` int(11) DEFAULT NULL COMMENT 'Parent User ID (Vorgesetzter)',
            `aktiv` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Aktiv',
            PRIMARY KEY (`benutzer_id`),
            UNIQUE KEY `idx_benutzername` (`benutzername`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $inspector->queryAll($sqlUsers);

        // Beispiel-Benutzer für jede Rolle anlegen (falls Tabelle noch leer)
        $cntRows = $inspector->queryAll("SELECT COUNT(*) AS cnt FROM `" . $userTable . "`");
        $userCount = (is_array($cntRows) && isset($cntRows[0]['cnt'])) ? (int)$cntRows[0]['cnt'] : 0;
        if ($userCount === 0) {
            $hashFn = function ($plain) {
                return function_exists('password_hash') ? password_hash($plain, PASSWORD_DEFAULT) : hash('sha256', $plain);
            };
            $pwAdmin   = addslashes($hashFn('admin123'));
            $pwManager = addslashes($hashFn('manager123'));
            $pwUser    = addslashes($hashFn('user123'));
            $pwGuest   = addslashes($hashFn('gast123'));
            $inspector->queryAll("INSERT INTO `" . $userTable . "` (`benutzer_id`, `benutzername`, `passwort`, `anzeigename`, `email`, `userlevel_id`, `vorgesetzter_id`, `aktiv`) VALUES
                (1, 'cheffe', '" . $pwAdmin . "', 'Dr. Thomas Berg (Admin)', 'admin@firma.example.com', -1, NULL, 1),
                (2, 'leitung', '" . $pwManager . "', 'Sabine Krüger (Abteilungsleitung)', 'krueger@firma.example.com', 1, 1, 1),
                (3, 'mitarbeiter1', '" . $pwUser . "', 'Janine Scholz (Vertrieb)', 'scholz@firma.example.com', 0, 2, 1),
                (4, 'mitarbeiter2', '" . $pwUser . "', 'kevin.braun', 'braun@firma.example.com', 0, 2, 1),
                (5, 'gast', '" . $pwGuest . "', 'Prüfer / Gast (Nur Lesen)', 'pruefer@firma.example.com', 2, NULL, 1)");
        }

        // Falls Tabelle "bestellungen" existiert und noch keine Spalte "erstellt_von" (für User ID Security Demo) hat, optional anlegen
        $colsBest = $inspector->queryAll("SHOW COLUMNS FROM `bestellungen` LIKE 'erstellt_von'");
        if (is_array($colsBest) && count($colsBest) === 0) {
            @$inspector->queryAll("ALTER TABLE `bestellungen` ADD COLUMN `erstellt_von` int(11) DEFAULT '3' COMMENT 'Bearbeiter (User-ID)'");
            @$inspector->queryAll("UPDATE `bestellungen` SET `erstellt_von` = 3 WHERE `bestellung_id` IN (1001, 1002, 1004)");
            @$inspector->queryAll("UPDATE `bestellungen` SET `erstellt_von` = 4 WHERE `bestellung_id` IN (1003, 1005)");
        }

        // Schema neu einlesen und mit Projekt synchronisieren
        $inspectedTables = $inspector->inspectSchema();
        $project['security']['enabled'] = true;
        $project['security']['use_user_table'] = true;
        $project['security']['user_table'] = $userTable;
        $project['security']['username_field'] = 'benutzername';
        $project['security']['password_field'] = 'passwort';
        $project['security']['user_id_field'] = 'benutzer_id';
        $project['security']['parent_user_id_field'] = 'vorgesetzter_id';
        $project['security']['user_level_field'] = 'userlevel_id';
        $project['security']['user_level_security'] = true;
        $project['security']['dynamic_user_levels'] = true;
        $project['security']['user_level_table'] = $lvlTable;
        $project['security']['user_level_priv_table'] = $privTable;

        $syncResult = ProjectManager::synchronizeSchema($project, $inspectedTables);
        $syncedProject = $syncResult['project'];

        // Falls bestellungen.erstellt_von existiert, automatisch als User ID Security Feld (Zeilen-Eigentümer) + Lookup einrichten
        if (isset($syncedProject['tables']['bestellungen']['fields']['erstellt_von'])) {
            $syncedProject['tables']['bestellungen']['user_id_field'] = 'erstellt_von';
            $syncedProject['tables']['bestellungen']['fields']['erstellt_von']['caption'] = 'Bearbeiter (Eigentümer)';
            $syncedProject['tables']['bestellungen']['fields']['erstellt_von']['edit_tag'] = 'SELECT';
            $syncedProject['tables']['bestellungen']['fields']['erstellt_von']['lookup_table'] = $userTable;
            $syncedProject['tables']['bestellungen']['fields']['erstellt_von']['lookup_link_field'] = 'benutzer_id';
            $syncedProject['tables']['bestellungen']['fields']['erstellt_von']['lookup_display_field'] = 'anzeigename';
        }

        // Systemtabellen (benutzer, userlevels, userlevelpermissions) standardmäßig nur für Administratoren (-1) freigeben
        $sysTables = array($userTable, $lvlTable, $privTable);
        foreach ($sysTables as $stName) {
            if (isset($syncedProject['tables'][$stName])) {
                $syncedProject['security']['static_permissions'][$stName] = array(
                    '-2' => 0,
                    '-1' => 127,
                    '0'  => 0,
                    '1'  => 0,
                    '2'  => 0
                );
            }
        }

        // Berechtigungen für alle Projekt-Tabellen & Custom Files initial in userlevelpermissions schreiben
        foreach ($syncedProject['tables'] as $tName => $tCfg) {
            $permsForTable = isset($syncedProject['security']['static_permissions'][$tName])
                ? $syncedProject['security']['static_permissions'][$tName]
                : array('-2' => 0, '-1' => 127, '0' => 109, '1' => 111, '2' => 104);
            foreach ($permsForTable as $lvlId => $permBits) {
                $safeT = "'" . addslashes($tName) . "'";
                $inspector->queryAll("REPLACE INTO `" . $privTable . "` (`tablename`, `userlevelid`, `permission`) VALUES (" . $safeT . ", " . (int)$lvlId . ", " . (int)$permBits . ")");
            }
        }
        if (isset($syncedProject['custom_files']) && is_array($syncedProject['custom_files'])) {
            foreach ($syncedProject['custom_files'] as $cfKey => $cfCfg) {
                $permsForCf = isset($syncedProject['security']['static_permissions'][$cfKey])
                    ? $syncedProject['security']['static_permissions'][$cfKey]
                    : array('-2' => 0, '-1' => 127, '0' => 104, '1' => 104, '2' => 104);
                foreach ($permsForCf as $lvlId => $permBits) {
                    $safeCf = "'" . addslashes($cfKey) . "'";
                    $inspector->queryAll("REPLACE INTO `" . $privTable . "` (`tablename`, `userlevelid`, `permission`) VALUES (" . $safeCf . ", " . (int)$lvlId . ", " . (int)$permBits . ")");
                }
            }
        }

        echo json_encode(array(
            'success' => true,
            'project' => $syncedProject,
            'message' => 'Systemtabellen (`' . $userTable . '`, `' . $lvlTable . '`, `' . $privTable . '`) inkl. Test-Benutzern und Rollen erfolgreich angelegt!'
        ));
        break;

    case 'generate':
        $project = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : null;
        if (!is_array($project)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Projektdaten übergeben.'));
            break;
        }
        $fName = isset($payload['filename']) && trim($payload['filename']) !== '' ? trim($payload['filename']) : $project['project_name'];

        // Schreibrecht prüfen
        $exGenFile = $pm->getProjectFilePath($fName);
        if ($exGenFile !== false && is_file($exGenFile)) {
            $exGenData = ProjectManager::decodeProjectJson(@file_get_contents($exGenFile));
            if (is_array($exGenData)) {
                $genRole = ProjectManager::getUserAccessRole($exGenData, $currentUser);
                if ($genRole === 'read') {
                    echo json_encode(array(
                        'success' => false,
                        'error'   => 'Schreibschutz: Sie haben für dieses Projekt nur Lese-Berechtigung ("Betrachter") und können keinen Code generieren oder überschreiben.'
                    ));
                    break;
                }
            }
        }

        // Projekt vor der Generierung automatisch sichern
        $savedName = $pm->saveProject($fName, $project, $currentUser);

        $engine = new GeneratorEngine($project, dirname(__FILE__));
        $res = $engine->generateAll();
        $res['saved_filename'] = $savedName;
        if ($savedName !== false) {
            $res['git_status'] = $pm->vcs->getStatus($savedName, $project);
        }
        echo json_encode($res);
        break;

    case 'change_studio_password':
        $curPw = isset($payload['current_password']) ? (string)$payload['current_password'] : '';
        $newPw = isset($payload['new_password']) ? (string)$payload['new_password'] : '';
        $res = StudioAuth::changePassword($curPw, $newPw);
        echo json_encode($res);
        break;

    // ========================================================================
    // MULTI-USER BENUTZERVERWALTUNG & PROJEKT-FREIGABEN (TEAM SHARING)
    // ========================================================================
    case 'list_studio_users':
        $allProjects = $pm->listProjects('', true);
        $projectCounts = array();
        foreach ($allProjects as $pr) {
            $own = isset($pr['owner']) ? strtolower((string)$pr['owner']) : 'admin';
            if (!isset($projectCounts[$own])) {
                $projectCounts[$own] = array('count' => 0, 'names' => array());
            }
            $projectCounts[$own]['count']++;
            $projectCounts[$own]['names'][] = $pr['project_name'] . ' (' . $pr['filename'] . ')';
        }
        $pubUsers = StudioAuth::listPublicUsers();
        foreach ($pubUsers as &$pu) {
            $uKey = strtolower($pu['username']);
            $pu['projects_count'] = isset($projectCounts[$uKey]) ? $projectCounts[$uKey]['count'] : 0;
            $pu['project_names']  = isset($projectCounts[$uKey]) ? $projectCounts[$uKey]['names'] : array();
        }
        unset($pu);
        echo json_encode(array(
            'success'      => true,
            'current_user' => $currentUserProfile,
            'users'        => $pubUsers
        ));
        break;

    case 'create_studio_user':
        if (!$isStudioAdmin) {
            echo json_encode(array('success' => false, 'error' => 'Nur Studio-Administratoren können neue Benutzerkonten über die Benutzerverwaltung anlegen.'));
            break;
        }
        $uName = isset($payload['username']) ? (string)$payload['username'] : '';
        $uPass = isset($payload['password']) ? (string)$payload['password'] : '';
        $uDisp = isset($payload['display_name']) ? (string)$payload['display_name'] : '';
        $uMail = isset($payload['email']) ? (string)$payload['email'] : '';
        $uRole = isset($payload['role']) ? (string)$payload['role'] : 'developer';
        $res = StudioAuth::createUser($uName, $uPass, $uDisp, $uMail, $uRole);
        $res['users'] = StudioAuth::listPublicUsers();
        echo json_encode($res);
        break;

    case 'update_studio_user':
        $targetUser = isset($payload['username']) ? (string)$payload['username'] : $currentUser;
        $res = StudioAuth::updateUser($targetUser, $payload, $currentUser);
        $res['users'] = StudioAuth::listPublicUsers();
        $res['current_user'] = StudioAuth::getCurrentUser();
        echo json_encode($res);
        break;

    case 'delete_studio_user':
        $targetUser = isset($payload['username']) ? trim((string)$payload['username']) : '';
        $reassignTo = isset($payload['reassign_to']) && trim((string)$payload['reassign_to']) !== ''
            ? StudioAuth::normalizeUsername($payload['reassign_to'])
            : ($currentUser !== '' && $currentUser !== StudioAuth::normalizeUsername($targetUser) ? $currentUser : 'admin');
        $res = StudioAuth::deleteUser($targetUser);
        if (!empty($res['success'])) {
            $reassignedProjects = $pm->reassignUserProjects($targetUser, $reassignTo);
            $res['reassigned_projects'] = $reassignedProjects;
            $res['reassigned_to'] = $reassignTo;
            if (!empty($reassignedProjects)) {
                $res['message'] .= ' Die ' . count($reassignedProjects) . ' zugeordneten Projekte (' . implode(', ', $reassignedProjects) . ') blieben vollständig erhalten und wurden an "' . $reassignTo . '" übertragen.';
            } else {
                $res['message'] .= ' Eventuelle Projekt-Freigaben dieses Benutzers wurden bereinigt.';
            }
            $res['projects'] = $pm->listProjects($currentUser, $isStudioAdmin);
        }
        $res['users'] = StudioAuth::listPublicUsers();
        echo json_encode($res);
        break;

    case 'get_project_sharing':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $proj = $pm->loadProject($filename);
        if (!is_array($proj)) {
            echo json_encode(array('success' => false, 'error' => 'Projekt konnte nicht geladen werden.'));
            break;
        }
        $role = ProjectManager::getUserAccessRole($proj, $currentUser);
        if ($role === '' && !$isStudioAdmin) {
            echo json_encode(array('success' => false, 'error' => 'Keine Berechtigung für dieses Projekt.'));
            break;
        }
        echo json_encode(array(
            'success'      => true,
            'filename'     => ProjectManager::sanitizeProjectSlug($filename),
            'project_name' => isset($proj['project_name']) ? $proj['project_name'] : $filename,
            'owner'        => !empty($proj['owner']) ? $proj['owner'] : 'admin',
            'shared_with'  => (isset($proj['shared_with']) && is_array($proj['shared_with'])) ? $proj['shared_with'] : array(),
            'can_manage'   => ($role === 'owner' || $isStudioAdmin),
            'is_admin'     => $isStudioAdmin,
            'studio_users' => StudioAuth::listPublicUsers()
        ));
        break;

    case 'save_project_sharing':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $sharedWith = isset($payload['shared_with']) && is_array($payload['shared_with']) ? $payload['shared_with'] : array();
        $newOwner = isset($payload['new_owner']) ? trim((string)$payload['new_owner']) : '';

        $filePath = $pm->getProjectFilePath($filename);
        if ($filePath === false || !is_file($filePath)) {
            echo json_encode(array('success' => false, 'error' => 'Projektdatei wurde noch nicht gespeichert. Bitte speichern Sie das Projekt zuerst.'));
            break;
        }
        $proj = $pm->loadProject($filename);
        $role = is_array($proj) ? ProjectManager::getUserAccessRole($proj, $currentUser) : '';
        if ($role !== 'owner' && !$isStudioAdmin) {
            echo json_encode(array('success' => false, 'error' => 'Nur der Projekteigentümer (' . (isset($proj['owner']) ? $proj['owner'] : 'admin') . ') oder ein Studio-Administrator kann die Team-Freigaben ändern.'));
            break;
        }

        $res = $pm->updateProjectSharing($filename, $sharedWith, $currentUser, $newOwner);
        if (!empty($res['success'])) {
            $res['projects'] = $pm->listProjects($currentUser, $isStudioAdmin);
        }
        echo json_encode($res);
        break;

    // ========================================================================
    // GIT-STYLE PROJEKT-VERSIONIERUNG (COMMITS, BRANCHES, TAGS, DIFF, ROLLBACK)
    // ========================================================================
    case 'git_status':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $workingProj = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : $pm->loadProject($filename);
        if (!is_array($workingProj)) {
            echo json_encode(array('success' => false, 'error' => 'Projektdaten für Git-Status nicht gefunden.'));
            break;
        }
        $role = ProjectManager::getUserAccessRole($workingProj, $currentUser);
        if ($role === '' && !$isStudioAdmin) {
            echo json_encode(array('success' => false, 'error' => 'Kein Zugriff auf dieses Projekt.'));
            break;
        }
        $status = $pm->vcs->getStatus($filename, $workingProj);
        echo json_encode(array(
            'success'     => true,
            'git_status'  => $status,
            'access_role' => $role !== '' ? $role : 'owner'
        ));
        break;

    case 'git_commit':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $workingProj = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : null;
        $commitMessage = isset($payload['message']) ? trim((string)$payload['message']) : '';

        if (!is_array($workingProj)) {
            $workingProj = $pm->loadProject($filename);
        }
        if (!is_array($workingProj)) {
            echo json_encode(array('success' => false, 'error' => 'Keine gültigen Projektdaten für Commit vorhanden.'));
            break;
        }

        $exPath = $pm->getProjectFilePath($filename);
        if ($exPath !== false && is_file($exPath)) {
            $exData = ProjectManager::decodeProjectJson(@file_get_contents($exPath));
            if (is_array($exData)) {
                $role = ProjectManager::getUserAccessRole($exData, $currentUser);
                if ($role === 'read') {
                    echo json_encode(array('success' => false, 'error' => 'Schreibschutz: Betrachter (Nur-Lesen) können keine Git-Commits erstellen.'));
                    break;
                }
                if ($role === '' && !$isStudioAdmin) {
                    echo json_encode(array('success' => false, 'error' => 'Keine Berechtigung für dieses Projekt.'));
                    break;
                }
            }
        }

        // Arbeitsstand zuerst in der .pmp.json sichern
        $savedName = $pm->saveProject($filename, $workingProj, $currentUser);
        if ($savedName === false) {
            echo json_encode(array('success' => false, 'error' => 'Projekt konnte vor dem Commit nicht gespeichert werden.'));
            break;
        }
        $freshProj = $pm->loadProject($savedName);
        $dispName = isset($currentUserProfile['display_name']) ? $currentUserProfile['display_name'] : $currentUser;
        $commitRes = $pm->vcs->commit($savedName, $freshProj, $commitMessage, $currentUser, $dispName, false);
        if (!empty($commitRes['success'])) {
            $commitRes['git_status'] = $pm->vcs->getStatus($savedName, $freshProj);
            $commitRes['projects'] = $pm->listProjects($currentUser, $isStudioAdmin);
        }
        echo json_encode($commitRes);
        break;

    case 'git_diff':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $baseCommit = isset($payload['base_commit']) ? trim((string)$payload['base_commit']) : '';
        $targetCommit = isset($payload['target_commit']) ? trim((string)$payload['target_commit']) : 'WORKING';
        $workingProj = isset($payload['project']) && is_array($payload['project']) ? $payload['project'] : $pm->loadProject($filename);

        $diffRes = $pm->vcs->compare($filename, $baseCommit, $targetCommit, $workingProj);
        echo json_encode($diffRes);
        break;

    case 'git_checkout_commit':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $commitId = isset($payload['commit_id']) ? trim((string)$payload['commit_id']) : '';
        if ($filename === '' || $commitId === '') {
            echo json_encode(array('success' => false, 'error' => 'Projekt oder Commit-ID fehlt.'));
            break;
        }

        $curProj = $pm->loadProject($filename);
        if (!is_array($curProj)) {
            echo json_encode(array('success' => false, 'error' => 'Projekt konnte nicht geladen werden.'));
            break;
        }
        $role = ProjectManager::getUserAccessRole($curProj, $currentUser);
        if ($role === 'read' || ($role === '' && !$isStudioAdmin)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Schreibberechtigung zum Zurücksetzen (Rollback) dieses Projekts.'));
            break;
        }

        $targetSnap = $pm->vcs->getCommitSnapshot($filename, $commitId);
        if (!is_array($targetSnap)) {
            echo json_encode(array('success' => false, 'error' => 'Commit-Snapshot "' . $commitId . '" wurde nicht gefunden.'));
            break;
        }

        // Aktuelle Owner- & Team-Freigaben beim Rollback beibehalten
        $targetSnap['owner'] = !empty($curProj['owner']) ? $curProj['owner'] : 'admin';
        $targetSnap['shared_with'] = isset($curProj['shared_with']) ? $curProj['shared_with'] : array();
        $targetSnap['last_modified_by'] = $currentUser;

        $dispName = isset($currentUserProfile['display_name']) ? $currentUserProfile['display_name'] : $currentUser;
        // Automatischer Sicherheits-Commit falls ungespeicherte Änderungen vorlagen
        $pm->vcs->commit($filename, $curProj, 'Auto-Backup vor Rollback auf Commit ' . $commitId, $currentUser, $dispName, true);

        $savedName = $pm->saveProject($filename, $targetSnap, $currentUser);
        $restoredProj = $pm->loadProject($savedName);
        $pm->vcs->commit($savedName, $restoredProj, 'Rollback / Wiederherstellung auf Commit ' . $commitId, $currentUser, $dispName, true);

        echo json_encode(array(
            'success'    => true,
            'filename'   => $savedName,
            'project'    => $restoredProj,
            'git_status' => $pm->vcs->getStatus($savedName, $restoredProj),
            'projects'   => $pm->listProjects($currentUser, $isStudioAdmin),
            'message'    => 'Projekt wurde erfolgreich auf den Stand von Commit ' . $commitId . ' wiederhergestellt!'
        ));
        break;

    case 'git_branch_create':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $branchName = isset($payload['branch_name']) ? trim((string)$payload['branch_name']) : '';
        $fromCommit = isset($payload['from_commit']) ? trim((string)$payload['from_commit']) : '';
        $switchNow = isset($payload['switch_branch']) ? (bool)$payload['switch_branch'] : true;

        $curProj = $pm->loadProject($filename);
        if (!is_array($curProj)) {
            echo json_encode(array('success' => false, 'error' => 'Projekt nicht gefunden.'));
            break;
        }
        $role = ProjectManager::getUserAccessRole($curProj, $currentUser);
        if ($role === 'read' || ($role === '' && !$isStudioAdmin)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Schreibberechtigung für Branch-Operationen.'));
            break;
        }

        $res = $pm->vcs->createBranch($filename, $branchName, $fromCommit, $switchNow);
        if (!empty($res['success'])) {
            $res['git_status'] = $pm->vcs->getStatus($filename, $curProj);
            $res['projects'] = $pm->listProjects($currentUser, $isStudioAdmin);
        }
        echo json_encode($res);
        break;

    case 'git_branch_switch':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $branchName = isset($payload['branch_name']) ? trim((string)$payload['branch_name']) : '';

        $curProj = $pm->loadProject($filename);
        if (!is_array($curProj)) {
            echo json_encode(array('success' => false, 'error' => 'Projekt nicht gefunden.'));
            break;
        }
        $role = ProjectManager::getUserAccessRole($curProj, $currentUser);
        if ($role === 'read' || ($role === '' && !$isStudioAdmin)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Schreibberechtigung zum Wechseln des aktiven Projekt-Branches.'));
            break;
        }

        // Vor dem Branch-Wechsel evtl. uncommittete Änderungen auf dem bisherigen Branch automatisch sichern
        $dispName = isset($currentUserProfile['display_name']) ? $currentUserProfile['display_name'] : $currentUser;
        $pm->vcs->commit($filename, $curProj, 'Auto-Commit vor Wechsel auf Branch ' . $branchName, $currentUser, $dispName, true);

        $res = $pm->vcs->switchBranch($filename, $branchName);
        if (!empty($res['success']) && is_array($res['snapshot'])) {
            $snap = $res['snapshot'];
            $snap['owner'] = !empty($curProj['owner']) ? $curProj['owner'] : 'admin';
            $snap['shared_with'] = isset($curProj['shared_with']) ? $curProj['shared_with'] : array();
            $pm->saveProject($filename, $snap, $currentUser);
            $curProj = $pm->loadProject($filename);
        }
        $res['project'] = $curProj;
        $res['git_status'] = $pm->vcs->getStatus($filename, $curProj);
        $res['projects'] = $pm->listProjects($currentUser, $isStudioAdmin);
        echo json_encode($res);
        break;

    case 'git_branch_delete':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $branchName = isset($payload['branch_name']) ? trim((string)$payload['branch_name']) : '';
        $curProj = $pm->loadProject($filename);
        $role = is_array($curProj) ? ProjectManager::getUserAccessRole($curProj, $currentUser) : '';
        if ($role === 'read' || ($role === '' && !$isStudioAdmin)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Schreibberechtigung.'));
            break;
        }
        $res = $pm->vcs->deleteBranch($filename, $branchName);
        $res['git_status'] = $pm->vcs->getStatus($filename, $curProj);
        echo json_encode($res);
        break;

    case 'git_branch_merge':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $sourceBranch = isset($payload['source_branch']) ? trim((string)$payload['source_branch']) : '';
        $curProj = $pm->loadProject($filename);
        if (!is_array($curProj)) {
            echo json_encode(array('success' => false, 'error' => 'Projekt nicht gefunden.'));
            break;
        }
        $role = ProjectManager::getUserAccessRole($curProj, $currentUser);
        if ($role === 'read' || ($role === '' && !$isStudioAdmin)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Schreibberechtigung für Branch-Merge.'));
            break;
        }
        $dispName = isset($currentUserProfile['display_name']) ? $currentUserProfile['display_name'] : $currentUser;
        $res = $pm->vcs->mergeBranch($filename, $sourceBranch, $currentUser, $dispName);
        if (!empty($res['success']) && is_array($res['snapshot'])) {
            $merged = $res['snapshot'];
            $merged['owner'] = !empty($curProj['owner']) ? $curProj['owner'] : 'admin';
            $merged['shared_with'] = isset($curProj['shared_with']) ? $curProj['shared_with'] : array();
            $pm->saveProject($filename, $merged, $currentUser);
            $curProj = $pm->loadProject($filename);
        }
        $res['project'] = $curProj;
        $res['git_status'] = $pm->vcs->getStatus($filename, $curProj);
        $res['projects'] = $pm->listProjects($currentUser, $isStudioAdmin);
        echo json_encode($res);
        break;

    case 'git_tag_create':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $tagName = isset($payload['tag_name']) ? trim((string)$payload['tag_name']) : '';
        $commitId = isset($payload['commit_id']) ? trim((string)$payload['commit_id']) : '';
        $curProj = $pm->loadProject($filename);
        $role = is_array($curProj) ? ProjectManager::getUserAccessRole($curProj, $currentUser) : '';
        if ($role === 'read' || ($role === '' && !$isStudioAdmin)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Schreibberechtigung.'));
            break;
        }
        $res = $pm->vcs->createTag($filename, $tagName, $commitId, $currentUser);
        $res['git_status'] = $pm->vcs->getStatus($filename, $curProj);
        echo json_encode($res);
        break;

    case 'git_tag_delete':
        $filename = isset($payload['filename']) ? trim((string)$payload['filename']) : '';
        $tagName = isset($payload['tag_name']) ? trim((string)$payload['tag_name']) : '';
        $curProj = $pm->loadProject($filename);
        $role = is_array($curProj) ? ProjectManager::getUserAccessRole($curProj, $currentUser) : '';
        if ($role === 'read' || ($role === '' && !$isStudioAdmin)) {
            echo json_encode(array('success' => false, 'error' => 'Keine Schreibberechtigung.'));
            break;
        }
        $res = $pm->vcs->deleteTag($filename, $tagName);
        $res['git_status'] = $pm->vcs->getStatus($filename, $curProj);
        echo json_encode($res);
        break;

    default:
        echo json_encode(array(
            'success' => false,
            'error' => 'Unbekannte Aktion: ' . $action
        ));
        break;
}
