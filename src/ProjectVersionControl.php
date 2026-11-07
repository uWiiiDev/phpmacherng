<?php
/**
 * PHPMacherNG - Git-Style Projekt-Versionierung (ProjectVersionControl.php)
 * Ermöglicht vollständige Git-ähnliche Versionskontrolle für PHPMacherNG Projekte:
 * - Commits mit SHA-1 Hash, Autor, Zeitstempel, Branch, Parent-Commits & automatischer Änderungs-Analyse
 * - Branches (main, Feature-Branches anlegen, wechseln / checkout, mergen)
 * - Tags (Release-Tags wie v1.0 anlegen & löschen)
 * - Diff-Engine (Struktureller PHPMacherNG-Diff + Zeilenweiser Unified-JSON-Diff)
 * - Rollback / Restore / Checkout beliebiger Commit-Stände mit automatischem Sicherheits-Backup
 * Kompatibel mit PHP 5.4 bis PHP 8.4+.
 */

class ProjectVersionControl
{
    public $vcsBaseDir = '';

    public function __construct($projectsDir = '')
    {
        if ($projectsDir === '') {
            $projectsDir = dirname(dirname(__FILE__)) . '/projects';
        }
        $this->vcsBaseDir = rtrim($projectsDir, '/\\') . '/.vcs';
        if (!is_dir($this->vcsBaseDir)) {
            @mkdir($this->vcsBaseDir, 0777, true);
        }
    }

    public static function sanitizeSlug($slug)
    {
        $s = basename((string)$slug);
        $s = preg_replace('/\.pmp\.json$/i', '', $s);
        $s = preg_replace('/\.json$/i', '', $s);
        $s = preg_replace('/[^a-zA-Z0-9_-]/', '_', $s);
        return $s !== '' ? $s : 'default_project';
    }

    public static function sanitizeRefName($name)
    {
        $n = trim((string)$name);
        $n = preg_replace('/[^a-zA-Z0-9_.\-\/]/', '-', $n);
        $n = trim($n, '/.-');
        return $n;
    }

    public function getRepoDir($projectSlug)
    {
        $slug = self::sanitizeSlug($projectSlug);
        return $this->vcsBaseDir . '/' . $slug;
    }

    public function getObjectsDir($projectSlug)
    {
        return $this->getRepoDir($projectSlug) . '/objects';
    }

    public function getRepoMetaPath($projectSlug)
    {
        return $this->getRepoDir($projectSlug) . '/repo.json';
    }

    /**
     * Bereinigt flüchtige Felder (wie updated_at) aus dem Projekt-Snapshot für deterministische Hashes und Diffs.
     */
    public static function normalizeSnapshot($projectData)
    {
        if (!is_array($projectData)) {
            return array();
        }
        $copy = $projectData;
        unset($copy['updated_at']);
        unset($copy['_git_info']);
        unset($copy['_access_role']);
        return $copy;
    }

    /**
     * Lädt die Repository-Metadaten (repo.json) oder legt ein leeres Grundgerüst an.
     */
    public function loadRepoMeta($projectSlug)
    {
        $metaPath = $this->getRepoMetaPath($projectSlug);
        if (is_file($metaPath)) {
            $raw = @file_get_contents($metaPath);
            $data = @json_decode($raw, true);
            if (is_array($data) && isset($data['branches']) && isset($data['commits'])) {
                if (!isset($data['tags']) || !is_array($data['tags'])) {
                    $data['tags'] = array();
                }
                if (empty($data['current_branch'])) {
                    $data['current_branch'] = 'main';
                }
                return $data;
            }
        }
        return array(
            'project_slug'   => self::sanitizeSlug($projectSlug),
            'created_at'     => date('Y-m-d H:i:s'),
            'current_branch' => 'main',
            'branches'       => array('main' => ''),
            'tags'           => array(),
            'commits'        => array()
        );
    }

    /**
     * Speichert die Repository-Metadaten (repo.json).
     */
    public function saveRepoMeta($projectSlug, $meta)
    {
        $repoDir = $this->getRepoDir($projectSlug);
        $objDir = $this->getObjectsDir($projectSlug);
        if (!is_dir($repoDir)) {
            @mkdir($repoDir, 0777, true);
        }
        if (!is_dir($objDir)) {
            @mkdir($objDir, 0777, true);
        }
        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;
        return (@file_put_contents($this->getRepoMetaPath($projectSlug), json_encode($meta, $flags)) !== false);
    }

    /**
     * Lädt den gespeicherten Projekt-Snapshot eines bestimmten Commits anhand seiner Commit-ID.
     */
    public function getCommitSnapshot($projectSlug, $commitId)
    {
        $cleanId = preg_replace('/[^a-f0-9]/i', '', (string)$commitId);
        if ($cleanId === '') {
            return null;
        }
        $file = $this->getObjectsDir($projectSlug) . '/' . $cleanId . '.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Stellt sicher, dass für das Projekt ein Git-Repository existiert; legt bei Bedarf den Initial-Commit an.
     */
    public function ensureInitialized($projectSlug, $projectData, $username = 'admin', $displayName = '')
    {
        $meta = $this->loadRepoMeta($projectSlug);
        if (empty($meta['commits']) && is_array($projectData) && !empty($projectData)) {
            $this->commit(
                $projectSlug,
                $projectData,
                'Initialer Projektstand (Git-Repository initialisiert)',
                $username,
                $displayName,
                true
            );
            $meta = $this->loadRepoMeta($projectSlug);
        }
        return $meta;
    }

    /**
     * Erzeugt einen neuen Commit im aktuellen Branch.
     * Falls $skipIfUnchanged = true und sich gegenüber HEAD nichts geändert hat, wird kein redundanter Commit erzeugt.
     */
    public function commit($projectSlug, $projectData, $message, $username = 'admin', $displayName = '', $skipIfUnchanged = false, $secondParent = '')
    {
        if (!is_array($projectData)) {
            return array('success' => false, 'error' => 'Ungültige Projektdaten für Commit.');
        }
        $meta = $this->loadRepoMeta($projectSlug);
        $branch = !empty($meta['current_branch']) ? $meta['current_branch'] : 'main';
        if (!isset($meta['branches'][$branch])) {
            $meta['branches'][$branch] = '';
        }
        $parentCommitId = (string)$meta['branches'][$branch];

        $normalizedNew = self::normalizeSnapshot($projectData);
        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;
        $newJson = json_encode($normalizedNew, $flags);
        $stateHash = sha1($newJson);

        $parentSnap = ($parentCommitId !== '') ? $this->getCommitSnapshot($projectSlug, $parentCommitId) : null;
        if ($parentSnap !== null) {
            $normalizedParent = self::normalizeSnapshot($parentSnap);
            $parentJson = json_encode($normalizedParent, $flags);
            if ($skipIfUnchanged && sha1($parentJson) === $stateHash) {
                return array(
                    'success'   => true,
                    'unchanged' => true,
                    'commit_id' => $parentCommitId,
                    'branch'    => $branch,
                    'message'   => 'Keine Änderungen gegenüber HEAD (' . $parentCommitId . ').'
                );
            }
        }

        $diffSummary = self::computeStructuralDiff($parentSnap, $normalizedNew);

        $now = date('Y-m-d H:i:s');
        $rawCommitSeed = $projectSlug . '|' . $branch . '|' . $parentCommitId . '|' . $username . '|' . $now . '|' . microtime(true) . '|' . $stateHash;
        $fullSha = sha1($rawCommitSeed);
        $commitId = substr($fullSha, 0, 7);

        $objDir = $this->getObjectsDir($projectSlug);
        if (!is_dir($objDir)) {
            @mkdir($objDir, 0777, true);
        }
        $objFile = $objDir . '/' . $commitId . '.json';
        if (@file_put_contents($objFile, json_encode($projectData, $flags)) === false) {
            return array('success' => false, 'error' => 'Commit-Snapshot konnte nicht im Verzeichnis projects/.vcs/ gespeichert werden.');
        }

        $cleanMsg = trim((string)$message);
        if ($cleanMsg === '') {
            $cleanMsg = 'Projekt-Aktualisierung (' . $diffSummary['short_summary'] . ')';
        }

        $commitEntry = array(
            'id'             => $commitId,
            'full_hash'      => $fullSha,
            'state_hash'     => $stateHash,
            'parent'         => $parentCommitId,
            'second_parent'  => (string)$secondParent,
            'branch'         => $branch,
            'author'         => $username !== '' ? $username : 'admin',
            'author_display' => $displayName !== '' ? $displayName : ($username !== '' ? $username : 'admin'),
            'timestamp'      => $now,
            'message'        => $cleanMsg,
            'stats'          => $diffSummary['stats'],
            'short_summary'  => $diffSummary['short_summary'],
            'changed_items'  => array_slice($diffSummary['items'], 0, 25)
        );

        // Neuesten Commit an den Anfang der Commit-Liste setzen
        array_unshift($meta['commits'], $commitEntry);
        $meta['branches'][$branch] = $commitId;

        $this->saveRepoMeta($projectSlug, $meta);

        return array(
            'success' => true,
            'commit'  => $commitEntry,
            'branch'  => $branch,
            'meta'    => $this->getStatus($projectSlug, $projectData)
        );
    }

    /**
     * Gibt den aktuellen Git-Status eines Projekts zurück (Branch, HEAD, uncommittete Änderungen, Branches, Tags, Historie).
     */
    public function getStatus($projectSlug, $currentWorkingProject = null)
    {
        if (is_array($currentWorkingProject)) {
            $meta = $this->ensureInitialized($projectSlug, $currentWorkingProject);
        } else {
            $meta = $this->loadRepoMeta($projectSlug);
        }

        $branch = !empty($meta['current_branch']) ? $meta['current_branch'] : 'main';
        $headCommitId = isset($meta['branches'][$branch]) ? (string)$meta['branches'][$branch] : '';

        $hasUncommitted = false;
        $workingDiff = null;

        if (is_array($currentWorkingProject) && $headCommitId !== '') {
            $headSnap = $this->getCommitSnapshot($projectSlug, $headCommitId);
            if (is_array($headSnap)) {
                $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;
                $headHash = sha1(json_encode(self::normalizeSnapshot($headSnap), $flags));
                $workHash = sha1(json_encode(self::normalizeSnapshot($currentWorkingProject), $flags));
                if ($headHash !== $workHash) {
                    $hasUncommitted = true;
                    $workingDiff = self::computeStructuralDiff($headSnap, $currentWorkingProject);
                }
            }
        }

        // Tags und Branches pro Commit-ID zuordnen für schnelle UI-Badges
        $commitTags = array();
        if (isset($meta['tags']) && is_array($meta['tags'])) {
            foreach ($meta['tags'] as $tagName => $tagObj) {
                $cid = is_array($tagObj) && isset($tagObj['commit_id']) ? $tagObj['commit_id'] : (string)$tagObj;
                if (!isset($commitTags[$cid])) {
                    $commitTags[$cid] = array();
                }
                $commitTags[$cid][] = $tagName;
            }
        }

        $commitBranches = array();
        if (isset($meta['branches']) && is_array($meta['branches'])) {
            foreach ($meta['branches'] as $bName => $cid) {
                if ($cid !== '') {
                    if (!isset($commitBranches[$cid])) {
                        $commitBranches[$cid] = array();
                    }
                    $commitBranches[$cid][] = $bName;
                }
            }
        }

        $enrichedCommits = array();
        foreach ($meta['commits'] as $c) {
            $cid = $c['id'];
            $c['tags'] = isset($commitTags[$cid]) ? $commitTags[$cid] : array();
            $c['branch_heads'] = isset($commitBranches[$cid]) ? $commitBranches[$cid] : array();
            $c['is_head'] = ($cid === $headCommitId);
            $enrichedCommits[] = $c;
        }

        return array(
            'project_slug'        => self::sanitizeSlug($projectSlug),
            'current_branch'      => $branch,
            'head_commit_id'      => $headCommitId,
            'commit_count'        => count($enrichedCommits),
            'last_commit'         => !empty($enrichedCommits) ? $enrichedCommits[0] : null,
            'has_uncommitted'     => $hasUncommitted,
            'working_diff'        => $workingDiff,
            'branches'            => $meta['branches'],
            'tags'                => $meta['tags'],
            'commits'             => $enrichedCommits
        );
    }

    /**
     * Legt einen neuen Branch an (ausgehend von $fromCommitId oder aktuellem HEAD) und wechselt optional direkt dorthin.
     */
    public function createBranch($projectSlug, $newBranchName, $fromCommitId = '', $switchImmediately = true)
    {
        $cleanBranch = self::sanitizeRefName($newBranchName);
        if ($cleanBranch === '') {
            return array('success' => false, 'error' => 'Bitte einen gültigen Branch-Namen eingeben (z. B. feature/ansicht-v2).');
        }
        $meta = $this->loadRepoMeta($projectSlug);
        if (isset($meta['branches'][$cleanBranch])) {
            return array('success' => false, 'error' => 'Der Branch "' . $cleanBranch . '" existiert bereits.');
        }

        $curBranch = !empty($meta['current_branch']) ? $meta['current_branch'] : 'main';
        $baseCommit = ($fromCommitId !== '') ? $fromCommitId : (isset($meta['branches'][$curBranch]) ? $meta['branches'][$curBranch] : '');
        $meta['branches'][$cleanBranch] = $baseCommit;
        if ($switchImmediately) {
            $meta['current_branch'] = $cleanBranch;
        }
        $this->saveRepoMeta($projectSlug, $meta);

        $snap = ($baseCommit !== '') ? $this->getCommitSnapshot($projectSlug, $baseCommit) : null;
        return array(
            'success'        => true,
            'branch'         => $cleanBranch,
            'head_commit_id' => $baseCommit,
            'snapshot'       => $snap,
            'message'        => 'Branch "' . $cleanBranch . '" wurde erfolgreich erstellt' . ($switchImmediately ? ' und ausgecheckt.' : '.')
        );
    }

    /**
     * Wechselt auf einen bestehenden Branch und gibt dessen HEAD-Projektstand zurück.
     */
    public function switchBranch($projectSlug, $branchName)
    {
        $cleanBranch = self::sanitizeRefName($branchName);
        $meta = $this->loadRepoMeta($projectSlug);
        if (!isset($meta['branches'][$cleanBranch])) {
            return array('success' => false, 'error' => 'Branch "' . $cleanBranch . '" existiert nicht.');
        }
        $meta['current_branch'] = $cleanBranch;
        $this->saveRepoMeta($projectSlug, $meta);

        $headCommit = (string)$meta['branches'][$cleanBranch];
        $snap = ($headCommit !== '') ? $this->getCommitSnapshot($projectSlug, $headCommit) : null;
        return array(
            'success'        => true,
            'branch'         => $cleanBranch,
            'head_commit_id' => $headCommit,
            'snapshot'       => $snap,
            'message'        => 'Auf Branch "' . $cleanBranch . '" gewechselt (HEAD: ' . ($headCommit !== '' ? $headCommit : 'leer') . ').'
        );
    }

    /**
     * Löscht einen Branch (solange es nicht der aktuell aktive Branch oder "main" ist).
     */
    public function deleteBranch($projectSlug, $branchName)
    {
        $cleanBranch = self::sanitizeRefName($branchName);
        if ($cleanBranch === 'main') {
            return array('success' => false, 'error' => 'Der Haupt-Branch "main" kann nicht gelöscht werden.');
        }
        $meta = $this->loadRepoMeta($projectSlug);
        if (!isset($meta['branches'][$cleanBranch])) {
            return array('success' => false, 'error' => 'Branch "' . $cleanBranch . '" wurde nicht gefunden.');
        }
        if ($meta['current_branch'] === $cleanBranch) {
            return array('success' => false, 'error' => 'Der aktuell ausgecheckte Branch kann nicht gelöscht werden. Bitte wechseln Sie zuerst auf "main".');
        }
        unset($meta['branches'][$cleanBranch]);
        $this->saveRepoMeta($projectSlug, $meta);
        return array(
            'success' => true,
            'message' => 'Branch "' . $cleanBranch . '" wurde gelöscht.'
        );
    }

    /**
     * Führt einen intelligenten strukturellen Merge eines Quell-Branches ($sourceBranch) in den aktuellen Branch durch.
     */
    public function mergeBranch($projectSlug, $sourceBranch, $username = 'admin', $displayName = '')
    {
        $cleanSource = self::sanitizeRefName($sourceBranch);
        $meta = $this->loadRepoMeta($projectSlug);
        $targetBranch = !empty($meta['current_branch']) ? $meta['current_branch'] : 'main';

        if (!isset($meta['branches'][$cleanSource])) {
            return array('success' => false, 'error' => 'Quell-Branch "' . $cleanSource . '" wurde nicht gefunden.');
        }
        if ($cleanSource === $targetBranch) {
            return array('success' => false, 'error' => 'Quell- und Ziel-Branch sind identisch ("' . $targetBranch . '").');
        }

        $sourceCommitId = (string)$meta['branches'][$cleanSource];
        $targetCommitId = isset($meta['branches'][$targetBranch]) ? (string)$meta['branches'][$targetBranch] : '';

        if ($sourceCommitId === '') {
            return array('success' => false, 'error' => 'Quell-Branch "' . $cleanSource . '" enthält keine Commits.');
        }
        if ($sourceCommitId === $targetCommitId) {
            return array('success' => false, 'error' => 'Beide Branches zeigen bereits auf denselben Commit (' . $sourceCommitId . ').');
        }

        $sourceSnap = $this->getCommitSnapshot($projectSlug, $sourceCommitId);
        $targetSnap = ($targetCommitId !== '') ? $this->getCommitSnapshot($projectSlug, $targetCommitId) : array();
        if (!is_array($sourceSnap)) {
            return array('success' => false, 'error' => 'Snapshot des Quell-Branches konnte nicht geladen werden.');
        }

        // Intelligenter struktureller Merge von Ziel-Projekt + Quell-Branch-Änderungen
        $mergedProject = self::mergeProjectStructures($targetSnap, $sourceSnap);
        $msg = 'Merge Branch "' . $cleanSource . '" (' . $sourceCommitId . ') in "' . $targetBranch . '"';

        $commitRes = $this->commit($projectSlug, $mergedProject, $msg, $username, $displayName, false, $sourceCommitId);
        if (empty($commitRes['success'])) {
            return $commitRes;
        }

        return array(
            'success'  => true,
            'commit'   => $commitRes['commit'],
            'snapshot' => $mergedProject,
            'message'  => 'Branch "' . $cleanSource . '" wurde erfolgreich in "' . $targetBranch . '" gemerged (Commit ' . $commitRes['commit']['id'] . ').'
        );
    }

    /**
     * Kombiniert zwei Projektstrukturen beim Branch-Merge (Tabellen, Felder, Custom Files, Events, Scripts, Settings).
     */
    public static function mergeProjectStructures($targetProj, $sourceProj)
    {
        if (!is_array($targetProj) || empty($targetProj)) {
            return $sourceProj;
        }
        if (!is_array($sourceProj) || empty($sourceProj)) {
            return $targetProj;
        }
        $merged = $targetProj;

        if (isset($sourceProj['settings']) && is_array($sourceProj['settings'])) {
            $merged['settings'] = isset($merged['settings']) && is_array($merged['settings'])
                ? array_merge($merged['settings'], $sourceProj['settings'])
                : $sourceProj['settings'];
        }
        if (isset($sourceProj['database']) && is_array($sourceProj['database'])) {
            $merged['database'] = isset($merged['database']) && is_array($merged['database'])
                ? array_merge($merged['database'], $sourceProj['database'])
                : $sourceProj['database'];
        }
        if (isset($sourceProj['security']) && is_array($sourceProj['security'])) {
            $merged['security'] = isset($merged['security']) && is_array($merged['security'])
                ? array_replace_recursive($merged['security'], $sourceProj['security'])
                : $sourceProj['security'];
        }
        if (isset($sourceProj['tables']) && is_array($sourceProj['tables'])) {
            if (!isset($merged['tables']) || !is_array($merged['tables'])) {
                $merged['tables'] = array();
            }
            foreach ($sourceProj['tables'] as $tName => $tCfg) {
                if (!isset($merged['tables'][$tName])) {
                    $merged['tables'][$tName] = $tCfg;
                } else {
                    $merged['tables'][$tName] = array_replace_recursive($merged['tables'][$tName], $tCfg);
                }
            }
        }
        if (isset($sourceProj['custom_files']) && is_array($sourceProj['custom_files'])) {
            if (!isset($merged['custom_files']) || !is_array($merged['custom_files'])) {
                $merged['custom_files'] = array();
            }
            foreach ($sourceProj['custom_files'] as $cfKey => $cfVal) {
                $merged['custom_files'][$cfKey] = $cfVal;
            }
        }
        if (isset($sourceProj['server_events']) && is_array($sourceProj['server_events'])) {
            $merged['server_events'] = isset($merged['server_events']) && is_array($merged['server_events'])
                ? array_replace_recursive($merged['server_events'], $sourceProj['server_events'])
                : $sourceProj['server_events'];
        }
        if (isset($sourceProj['client_scripts']) && is_array($sourceProj['client_scripts'])) {
            $merged['client_scripts'] = isset($merged['client_scripts']) && is_array($merged['client_scripts'])
                ? array_replace_recursive($merged['client_scripts'], $sourceProj['client_scripts'])
                : $sourceProj['client_scripts'];
        }
        return $merged;
    }

    /**
     * Erstellt einen Git-Tag (z. B. "v1.0", "Release-2026") für einen Commit.
     */
    public function createTag($projectSlug, $tagName, $commitId = '', $username = 'admin')
    {
        $cleanTag = self::sanitizeRefName($tagName);
        if ($cleanTag === '') {
            return array('success' => false, 'error' => 'Bitte einen gültigen Tag-Namen angeben (z. B. v1.0).');
        }
        $meta = $this->loadRepoMeta($projectSlug);
        $curBranch = !empty($meta['current_branch']) ? $meta['current_branch'] : 'main';
        $targetCommit = ($commitId !== '') ? $commitId : (isset($meta['branches'][$curBranch]) ? $meta['branches'][$curBranch] : '');
        if ($targetCommit === '') {
            return array('success' => false, 'error' => 'Kein Commit vorhanden, der getaggt werden könnte.');
        }

        $meta['tags'][$cleanTag] = array(
            'name'       => $cleanTag,
            'commit_id'  => $targetCommit,
            'created_by' => $username,
            'created_at' => date('Y-m-d H:i:s')
        );
        $this->saveRepoMeta($projectSlug, $meta);

        return array(
            'success' => true,
            'tag'     => $meta['tags'][$cleanTag],
            'message' => 'Tag "' . $cleanTag . '" auf Commit ' . $targetCommit . ' gesetzt.'
        );
    }

    /**
     * Entfernt einen Git-Tag.
     */
    public function deleteTag($projectSlug, $tagName)
    {
        $cleanTag = self::sanitizeRefName($tagName);
        $meta = $this->loadRepoMeta($projectSlug);
        if (!isset($meta['tags'][$cleanTag])) {
            return array('success' => false, 'error' => 'Tag "' . $cleanTag . '" existiert nicht.');
        }
        unset($meta['tags'][$cleanTag]);
        $this->saveRepoMeta($projectSlug, $meta);
        return array('success' => true, 'message' => 'Tag "' . $cleanTag . '" wurde gelöscht.');
    }

    /**
     * Berechnet einen detaillierten Diff zwischen zwei Commits (oder zwischen einem Commit und dem aktuellen Arbeitsstand).
     */
    public function compare($projectSlug, $baseCommitId, $targetCommitId = 'WORKING', $workingProjectData = null)
    {
        $baseSnap = ($baseCommitId !== '' && $baseCommitId !== 'EMPTY')
            ? $this->getCommitSnapshot($projectSlug, $baseCommitId)
            : array();

        if ($targetCommitId === 'WORKING' && is_array($workingProjectData)) {
            $targetSnap = $workingProjectData;
            $targetLabel = 'Aktueller Arbeitsstand (Working Tree)';
        } else {
            $targetSnap = $this->getCommitSnapshot($projectSlug, $targetCommitId);
            $targetLabel = 'Commit ' . $targetCommitId;
        }

        if (!is_array($baseSnap)) {
            $baseSnap = array();
        }
        if (!is_array($targetSnap)) {
            return array('success' => false, 'error' => 'Ziel-Commit für Vergleich konnte nicht geladen werden.');
        }

        $structural = self::computeStructuralDiff($baseSnap, $targetSnap);
        $unifiedLines = self::computeUnifiedJsonDiff($baseSnap, $targetSnap);

        return array(
            'success'       => true,
            'base_commit'   => $baseCommitId !== '' ? $baseCommitId : '(Leer)',
            'target_commit' => $targetLabel,
            'structural'    => $structural,
            'unified_diff'  => $unifiedLines
        );
    }

    /**
     * Analysiert strukturelle Änderungen zwischen zwei PHPMacherNG-Projektständen
     * (Tabellen, Views, Felder, Diagramme, Custom Files, Server Events, Client Scripts, Security, Settings).
     */
    public static function computeStructuralDiff($oldProj, $newProj)
    {
        $items = array();
        $stats = array(
            'added'    => 0,
            'modified' => 0,
            'deleted'  => 0
        );

        if (!is_array($oldProj) || empty($oldProj)) {
            $tCount = (isset($newProj['tables']) && is_array($newProj['tables'])) ? count($newProj['tables']) : 0;
            $cfCount = (isset($newProj['custom_files']) && is_array($newProj['custom_files'])) ? count($newProj['custom_files']) : 0;
            $stats['added'] = $tCount + $cfCount + 1;
            $items[] = array(
                'type'     => 'added',
                'category' => 'Projekt',
                'path'     => isset($newProj['project_name']) ? $newProj['project_name'] : 'Projekt',
                'detail'   => 'Initialer Projektstand mit ' . $tCount . ' Tabellen/Views und ' . $cfCount . ' Custom Files'
            );
            return array(
                'stats'         => $stats,
                'short_summary' => 'Initialer Commit (' . $tCount . ' Tabellen)',
                'items'         => $items
            );
        }

        $oldNorm = self::normalizeSnapshot($oldProj);
        $newNorm = self::normalizeSnapshot($newProj);

        // 1. Datenbank-Einstellungen prüfen
        $oldDb = isset($oldNorm['database']) && is_array($oldNorm['database']) ? $oldNorm['database'] : array();
        $newDb = isset($newNorm['database']) && is_array($newNorm['database']) ? $newNorm['database'] : array();
        if (json_encode($oldDb) !== json_encode($newDb)) {
            $stats['modified']++;
            $items[] = array(
                'type'     => 'modified',
                'category' => 'Database',
                'path'     => 'Datenbankverbindung',
                'detail'   => 'DB: ' . (isset($oldDb['dbname']) ? $oldDb['dbname'] : '-') . ' -> ' . (isset($newDb['dbname']) ? $newDb['dbname'] : '-')
            );
        }

        // 2. Globale Projekteinstellungen (Settings / Template / Skin)
        $oldSet = isset($oldNorm['settings']) && is_array($oldNorm['settings']) ? $oldNorm['settings'] : array();
        $newSet = isset($newNorm['settings']) && is_array($newNorm['settings']) ? $newNorm['settings'] : array();
        $changedSettings = array();
        $allSetKeys = array_unique(array_merge(array_keys($oldSet), array_keys($newSet)));
        foreach ($allSetKeys as $sKey) {
            $v1 = isset($oldSet[$sKey]) ? $oldSet[$sKey] : null;
            $v2 = isset($newSet[$sKey]) ? $newSet[$sKey] : null;
            if ($v1 !== $v2) {
                $changedSettings[] = $sKey . ': ' . json_encode($v1) . ' -> ' . json_encode($v2);
            }
        }
        if (!empty($changedSettings)) {
            $stats['modified']++;
            $items[] = array(
                'type'     => 'modified',
                'category' => 'PHP & HTML Settings',
                'path'     => 'settings',
                'detail'   => implode(' | ', array_slice($changedSettings, 0, 5))
            );
        }

        // 3. Security-Konfiguration
        $oldSec = isset($oldNorm['security']) && is_array($oldNorm['security']) ? $oldNorm['security'] : array();
        $newSec = isset($newNorm['security']) && is_array($newNorm['security']) ? $newNorm['security'] : array();
        if (json_encode($oldSec) !== json_encode($newSec)) {
            $stats['modified']++;
            $items[] = array(
                'type'     => 'modified',
                'category' => 'Security',
                'path'     => 'Sicherheit & Berechtigungen',
                'detail'   => 'Security-Einstellungen oder Rollen-Berechtigungen geändert'
            );
        }

        // 4. Tabellen, Views, Felder & Diagramme
        $oldTables = isset($oldNorm['tables']) && is_array($oldNorm['tables']) ? $oldNorm['tables'] : array();
        $newTables = isset($newNorm['tables']) && is_array($newNorm['tables']) ? $newNorm['tables'] : array();
        $allTableNames = array_unique(array_merge(array_keys($oldTables), array_keys($newTables)));

        foreach ($allTableNames as $tName) {
            if (!isset($oldTables[$tName])) {
                $stats['added']++;
                $isView = isset($newTables[$tName]['type']) && $newTables[$tName]['type'] === 'VIEW';
                $items[] = array(
                    'type'     => 'added',
                    'category' => $isView ? 'DB-View' : 'Tabelle',
                    'path'     => $tName,
                    'detail'   => ($isView ? 'Neue Datenbank-View "' : 'Neue Tabelle "') . $tName . '" hinzugefügt'
                );
                continue;
            }
            if (!isset($newTables[$tName])) {
                $stats['deleted']++;
                $isView = isset($oldTables[$tName]['type']) && $oldTables[$tName]['type'] === 'VIEW';
                $items[] = array(
                    'type'     => 'deleted',
                    'category' => $isView ? 'DB-View' : 'Tabelle',
                    'path'     => $tName,
                    'detail'   => ($isView ? 'Datenbank-View "' : 'Tabelle "') . $tName . '" entfernt'
                );
                continue;
            }

            $oldT = $oldTables[$tName];
            $newT = $newTables[$tName];

            // Tabellen-Kopfoptionen ohne fields prüfen
            $oldTNoFields = $oldT;
            $newTNoFields = $newT;
            unset($oldTNoFields['fields']);
            unset($newTNoFields['fields']);
            if (json_encode($oldTNoFields) !== json_encode($newTNoFields)) {
                $stats['modified']++;
                $items[] = array(
                    'type'     => 'modified',
                    'category' => 'Table Setup',
                    'path'     => $tName,
                    'detail'   => 'Tabellen-Optionen, Master/Detail oder Diagramme von "' . $tName . '" geändert'
                );
            }

            // Felder der Tabelle vergleichen
            $oldFields = isset($oldT['fields']) && is_array($oldT['fields']) ? $oldT['fields'] : array();
            $newFields = isset($newT['fields']) && is_array($newT['fields']) ? $newT['fields'] : array();
            $allFieldNames = array_unique(array_merge(array_keys($oldFields), array_keys($newFields)));

            foreach ($allFieldNames as $fName) {
                if (!isset($oldFields[$fName])) {
                    $stats['added']++;
                    $items[] = array(
                        'type'     => 'added',
                        'category' => 'Feld',
                        'path'     => $tName . '.' . $fName,
                        'detail'   => 'Neues Feld "' . $fName . '" in "' . $tName . '"'
                    );
                } elseif (!isset($newFields[$fName])) {
                    $stats['deleted']++;
                    $items[] = array(
                        'type'     => 'deleted',
                        'category' => 'Feld',
                        'path'     => $tName . '.' . $fName,
                        'detail'   => 'Feld "' . $fName . '" aus "' . $tName . '" entfernt'
                    );
                } elseif (json_encode($oldFields[$fName]) !== json_encode($newFields[$fName])) {
                    $stats['modified']++;
                    $fDiffs = array();
                    foreach ($newFields[$fName] as $fk => $fv) {
                        $ov = isset($oldFields[$fName][$fk]) ? $oldFields[$fName][$fk] : null;
                        if ($ov !== $fv) {
                            $fDiffs[] = $fk;
                        }
                    }
                    $items[] = array(
                        'type'     => 'modified',
                        'category' => 'Field Setup',
                        'path'     => $tName . '.' . $fName,
                        'detail'   => 'Geändert: ' . implode(', ', array_slice($fDiffs, 0, 6))
                    );
                }
            }
        }

        // 5. Custom Files vergleichen
        $oldCf = isset($oldNorm['custom_files']) && is_array($oldNorm['custom_files']) ? $oldNorm['custom_files'] : array();
        $newCf = isset($newNorm['custom_files']) && is_array($newNorm['custom_files']) ? $newNorm['custom_files'] : array();
        $allCfKeys = array_unique(array_merge(array_keys($oldCf), array_keys($newCf)));
        foreach ($allCfKeys as $cfName) {
            if (!isset($oldCf[$cfName])) {
                $stats['added']++;
                $items[] = array(
                    'type'     => 'added',
                    'category' => 'Custom File',
                    'path'     => $cfName,
                    'detail'   => 'Custom File "' . $cfName . '" neu angelegt'
                );
            } elseif (!isset($newCf[$cfName])) {
                $stats['deleted']++;
                $items[] = array(
                    'type'     => 'deleted',
                    'category' => 'Custom File',
                    'path'     => $cfName,
                    'detail'   => 'Custom File "' . $cfName . '" gelöscht'
                );
            } elseif (json_encode($oldCf[$cfName]) !== json_encode($newCf[$cfName])) {
                $stats['modified']++;
                $items[] = array(
                    'type'     => 'modified',
                    'category' => 'Custom File',
                    'path'     => $cfName,
                    'detail'   => 'Code/Konfiguration von "' . $cfName . '" bearbeitet'
                );
            }
        }

        // 6. Server Events & Client Scripts vergleichen
        $oldSe = isset($oldNorm['server_events']) ? json_encode($oldNorm['server_events']) : '';
        $newSe = isset($newNorm['server_events']) ? json_encode($newNorm['server_events']) : '';
        if ($oldSe !== $newSe) {
            $stats['modified']++;
            $items[] = array(
                'type'     => 'modified',
                'category' => 'Server Events',
                'path'     => 'server_events',
                'detail'   => 'PHP Server Events wurden angepasst'
            );
        }

        $oldCs = isset($oldNorm['client_scripts']) ? json_encode($oldNorm['client_scripts']) : '';
        $newCs = isset($newNorm['client_scripts']) ? json_encode($newNorm['client_scripts']) : '';
        if ($oldCs !== $newCs) {
            $stats['modified']++;
            $items[] = array(
                'type'     => 'modified',
                'category' => 'Client Scripts',
                'path'     => 'client_scripts',
                'detail'   => 'JavaScript Client Scripts wurden angepasst'
            );
        }

        $parts = array();
        if ($stats['added'] > 0) {
            $parts[] = '+' . $stats['added'] . ' neu';
        }
        if ($stats['modified'] > 0) {
            $parts[] = '~' . $stats['modified'] . ' geändert';
        }
        if ($stats['deleted'] > 0) {
            $parts[] = '-' . $stats['deleted'] . ' gelöscht';
        }
        $shortSummary = !empty($parts) ? implode(', ', $parts) : 'Keine strukturellen Unterschiede';

        return array(
            'stats'         => $stats,
            'short_summary' => $shortSummary,
            'items'         => $items
        );
    }

    /**
     * Erzeugt einen kompakten zeilenweisen Unified-Diff der JSON-Repräsentation (mit Kontextzeilen).
     */
    public static function computeUnifiedJsonDiff($oldProj, $newProj, $maxHunks = 120)
    {
        $flags = defined('JSON_PRETTY_PRINT') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 0;
        $oldLines = explode("\n", json_encode(self::normalizeSnapshot($oldProj), $flags));
        $newLines = explode("\n", json_encode(self::normalizeSnapshot($newProj), $flags));

        $n = count($oldLines);
        $m = count($newLines);
        $diffOut = array();

        $i = 0;
        $j = 0;
        $changesCount = 0;

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $oldLines[$i] === $newLines[$j]) {
                $i++;
                $j++;
                continue;
            }

            // Kontextzeile davor aufnehmen
            if ($i > 0 && isset($oldLines[$i - 1])) {
                $diffOut[] = array('type' => 'ctx', 'old_ln' => $i, 'new_ln' => $j, 'text' => $oldLines[$i - 1]);
            }

            // Suche nächste Synchronisations-Stelle innerhalb eines Suchfensters von 25 Zeilen
            $syncFound = false;
            $window = 25;
            for ($w = 1; $w <= $window; $w++) {
                for ($di = 0; $di <= $w; $di++) {
                    $dj = $w - $di;
                    if (($i + $di) < $n && ($j + $dj) < $m && $oldLines[$i + $di] === $newLines[$j + $dj]) {
                        for ($k = 0; $k < $di; $k++) {
                            $diffOut[] = array('type' => 'del', 'old_ln' => $i + $k + 1, 'new_ln' => '', 'text' => $oldLines[$i + $k]);
                            $changesCount++;
                        }
                        for ($k = 0; $k < $dj; $k++) {
                            $diffOut[] = array('type' => 'add', 'old_ln' => '', 'new_ln' => $j + $k + 1, 'text' => $newLines[$j + $k]);
                            $changesCount++;
                        }
                        $i += $di;
                        $j += $dj;
                        $syncFound = true;
                        break 2;
                    }
                }
            }

            if (!$syncFound) {
                if ($i < $n) {
                    $diffOut[] = array('type' => 'del', 'old_ln' => $i + 1, 'new_ln' => '', 'text' => $oldLines[$i]);
                    $i++;
                    $changesCount++;
                }
                if ($j < $m) {
                    $diffOut[] = array('type' => 'add', 'old_ln' => '', 'new_ln' => $j + 1, 'text' => $newLines[$j]);
                    $j++;
                    $changesCount++;
                }
            }

            // Kontextzeile danach aufnehmen
            if ($i < $n && $j < $m && $oldLines[$i] === $newLines[$j]) {
                $diffOut[] = array('type' => 'ctx', 'old_ln' => $i + 1, 'new_ln' => $j + 1, 'text' => $oldLines[$i]);
            }

            if ($changesCount >= $maxHunks) {
                $diffOut[] = array('type' => 'info', 'old_ln' => '...', 'new_ln' => '...', 'text' => '... (Weitere Zeilen-Unterschiede gekürzt zur besseren Übersicht)');
                break;
            }
        }

        return $diffOut;
    }
}
