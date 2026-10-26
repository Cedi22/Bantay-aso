<?php
/**
 * Bantay Aso — QA Execution Pipeline & Snapshot API
 * Coordinates on-demand web fetching, noisy tag stripping, snapshot logging,
 * automated diff calculation, and Bionic + Qwen3.5 semantic changelog generation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/fetcher.php';
require_once __DIR__ . '/../includes/sanitizer.php';
require_once __DIR__ . '/../includes/differ.php';
require_once __DIR__ . '/../includes/llm_client.php';
require_once __DIR__ . '/../includes/notifier.php';
require_once __DIR__ . '/../includes/security_scanner.php';

header('Content-Type: application/json; charset=utf-8');

// Entire QA endpoint strictly requires authentication
Auth::requireLogin();

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? $input['action'] ?? '';

// GET: Historical snapshots and individual snapshot inspection
if ($method === 'GET') {
    switch ($action) {
        case 'history':
            $watchlistId = (int)($_GET['watchlist_id'] ?? 0);
            if ($watchlistId <= 0) {
                Auth::jsonResponse(['success' => false, 'error' => 'Invalid watchlist_id.'], 400);
            }

            $stmt = $pdo->prepare('
                SELECT id, watchlist_id, status_code, response_time_ms, content_type,
                       content_hash, page_title, dom_metrics_json, created_at, qa_report, qa_verdict, qa_model, qa_source,
                       security_score, security_grade, vuln_report_json
                FROM snapshots
                WHERE watchlist_id = ?
                ORDER BY created_at DESC
            ');
            $stmt->execute([$watchlistId]);
            $snapshots = $stmt->fetchAll();

            Auth::jsonResponse(['success' => true, 'snapshots' => $snapshots]);
            break;

        case 'snapshot':
            $snapshotId = (int)($_GET['id'] ?? 0);
            if ($snapshotId <= 0) {
                Auth::jsonResponse(['success' => false, 'error' => 'Invalid snapshot id.'], 400);
            }

            $stmt = $pdo->prepare('SELECT * FROM snapshots WHERE id = ?');
            $stmt->execute([$snapshotId]);
            $snap = $stmt->fetch();

            if (!$snap) {
                Auth::jsonResponse(['success' => false, 'error' => 'Snapshot not found.'], 404);
            }

            $snap['security_headers'] = json_decode($snap['security_headers_json'] ?? '{}', true);
            $snap['dom_metrics'] = json_decode($snap['dom_metrics_json'] ?? '{}', true);
            $snap['vuln_report'] = json_decode($snap['vuln_report_json'] ?? '{}', true);
            Auth::jsonResponse(['success' => true, 'snapshot' => $snap]);
            break;

        case 'reports':
            $limit = (int)($_GET['limit'] ?? 50);
            if ($limit <= 0 || $limit > 200) $limit = 50;

            $stmt = $pdo->prepare('
                SELECT 
                    s.id as snapshot_id,
                    s.watchlist_id,
                    s.status_code,
                    s.response_time_ms,
                    s.content_hash,
                    s.page_title,
                    s.created_at,
                    s.qa_report,
                    s.qa_verdict,
                    s.qa_model,
                    s.dom_metrics_json,
                    s.security_headers_json,
                    s.security_score,
                    s.security_grade,
                    s.vuln_report_json,
                    COALESCE(s.triggered_by, \'adminmoe\') as triggered_by,
                    w.name as target_name,
                    w.url as target_url,
                    c.id as comparison_id,
                    c.semantic_changelog,
                    c.lines_added,
                    c.lines_removed
                FROM snapshots s
                JOIN watchlist w ON w.id = s.watchlist_id
                LEFT JOIN comparisons c ON c.comparison_snapshot_id = s.id
                ORDER BY s.id DESC
                LIMIT ?
            ');
            $stmt->execute([$limit]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Auth::jsonResponse(['success' => true, 'reports' => $reports]);
            break;

        default:
            Auth::jsonResponse(['success' => false, 'error' => 'Action required (history, snapshot, reports).'], 400);
    }
}

// POST: Trigger "NEW QA" Pipeline (Admin only)
Auth::requireAdmin();
Auth::requireCsrf();

if ($method === 'POST' && ($action === 'run_qa' || $action === 'trigger')) {
    $watchlistId = (int)($input['watchlist_id'] ?? 0);
    if ($watchlistId <= 0) {
        Auth::jsonResponse(['success' => false, 'error' => 'Watchlist target ID is required.'], 400);
    }

    // Retrieve target item
    $stmt = $pdo->prepare('SELECT * FROM watchlist WHERE id = ? LIMIT 1');
    $stmt->execute([$watchlistId]);
    $target = $stmt->fetch();

    if (!$target) {
        Auth::jsonResponse(['success' => false, 'error' => 'Watchlist target not found.'], 404);
    }

    $targetUrl = $target['url'];
    $executionLog = [];

    // =========================================================================
    // STEP 1: Fetch Live Web Content via Native cURL
    // =========================================================================
    $fetchStart = microtime(true);
    $fetchResult = WebFetcher::fetch($targetUrl);
    $fetchDuration = (int)round((microtime(true) - $fetchStart) * 1000);

    if (!$fetchResult['success'] && $fetchResult['status_code'] === 0) {
        Auth::jsonResponse([
            'success' => false,
            'step_failed' => 'fetch',
            'error' => 'Failed to reach website: ' . ($fetchResult['error'] ?? 'Unknown network error'),
        ], 502);
    }

    $executionLog[] = "Step 1: Fetched {$targetUrl} (HTTP {$fetchResult['status_code']}, {$fetchResult['response_time_ms']}ms)";

    // Run SSL Certificate Inspection
    $sslResult = WebFetcher::inspectSsl($targetUrl);
    $executionLog[] = "Step 1b: SSL {$sslResult['status']} (" . ($sslResult['days_left'] !== null ? "{$sslResult['days_left']}d left" : "N/A") . ", " . ($sslResult['issuer'] ?: "N/A") . ")";

    // Run Passive Vulnerability & Security Posture Audit
    $vulnReport = SecurityScanner::auditTarget($targetUrl, $fetchResult, $sslResult);
    $securityScore = (int)$vulnReport['security_score'];
    $securityGrade = (string)$vulnReport['security_grade'];
    $vulnReportJson = json_encode($vulnReport, JSON_UNESCAPED_SLASHES);
    $executionLog[] = "Step 1c: Security Audit complete (Score: {$securityScore}/100, Grade: {$securityGrade}, {$vulnReport['summary']['critical']} Critical, {$vulnReport['summary']['high']} High findings)";

    $isUp = ($fetchResult['status_code'] >= 200 && $fetchResult['status_code'] < 400);
    $consecutiveFailures = $isUp ? 0 : ((int)($target['consecutive_failures'] ?? 0) + 1);
    $uptimeState = ($consecutiveFailures >= 2) ? 'DOWN' : 'UP';

    // =========================================================================
    // STEP 2: Strip Noisy Dynamic Tags & Canonicalize (ECC Directive)
    // =========================================================================
    $cleanResult = ContentSanitizer::process($fetchResult['raw_body'] ?? '');
    $cleanResult['metrics']['dom_skeleton_hash'] = $cleanResult['dom_skeleton_hash'] ?? null;
    $cleanResult['metrics']['raw_page_title'] = $cleanResult['page_title'];
    $cleanResult['metrics']['security_score'] = $securityScore;
    $cleanResult['metrics']['security_grade'] = $securityGrade;
    $executionLog[] = "Step 2: Cleaned dynamic tags (Hash: " . substr($cleanResult['content_hash'], 0, 12) . "...)";

    // Auto-generate Semantic Version Label (e.g. Target Name (v1.3 Security Sentinel))
    $versionTag = !empty($input['version_tag']) ? trim($input['version_tag']) : (!empty($input['codename']) ? trim($input['codename']) : null);
    $snapshotLabel = Database::generateNextSnapshotVersionLabel($pdo, $watchlistId, $target['name'], $versionTag);

    // =========================================================================
    // STEP 3: Store Snapshot in Database
    // =========================================================================
    $currentAdmin = Auth::getCurrentUser();
    $triggeredBy = !empty($currentAdmin['username']) ? $currentAdmin['username'] : 'adminmoe';
    $now = gmdate('Y-m-d H:i:s');
    $ins = $pdo->prepare('
        INSERT INTO snapshots (
            watchlist_id, status_code, response_time_ms, content_type,
            content_hash, raw_headers, security_headers_json, cleaned_content,
            raw_content, page_title, meta_description, dom_metrics_json,
            security_score, security_grade, vuln_report_json, triggered_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $ins->execute([
        $watchlistId,
        $fetchResult['status_code'],
        $fetchResult['response_time_ms'],
        $fetchResult['content_type'],
        $cleanResult['content_hash'],
        $fetchResult['raw_headers'],
        json_encode($fetchResult['security_headers'], JSON_UNESCAPED_SLASHES),
        $cleanResult['canonical_text'],
        $fetchResult['raw_body'],
        $snapshotLabel,
        $cleanResult['meta_description'],
        json_encode($cleanResult['metrics']),
        $securityScore,
        $securityGrade,
        $vulnReportJson,
        $triggeredBy,
        $now,
    ]);
    $newSnapshotId = (int)$pdo->lastInsertId();

    // Update watchlist item with latest check stats and watchdog posture
    $upd = $pdo->prepare('
        UPDATE watchlist 
        SET last_status_code = ?, last_response_time_ms = ?, last_checked_at = ?, updated_at = ?,
            ssl_expires_at = ?, ssl_days_left = ?, ssl_issuer = ?, ssl_status = ?,
            uptime_state = ?, consecutive_failures = ?, dom_skeleton_hash = ?,
            security_score = ?, security_grade = ?, vuln_report_json = ?
        WHERE id = ?
    ');
    $upd->execute([
        $fetchResult['status_code'],
        $fetchResult['response_time_ms'],
        $now,
        $now,
        $sslResult['valid_to'],
        $sslResult['days_left'],
        $sslResult['issuer'],
        $sslResult['status'],
        $uptimeState,
        $consecutiveFailures,
        $cleanResult['dom_skeleton_hash'] ?? null,
        $securityScore,
        $securityGrade,
        $vulnReportJson,
        $watchlistId,
    ]);

    $executionLog[] = "Step 3: Stored Snapshot #{$newSnapshotId} [{$snapshotLabel}]";

    // =========================================================================
    // STEP 4: Retrieve Preceding Snapshot & Run Diff + configured QA report engine
    // =========================================================================
    $prevStmt = $pdo->prepare('
        SELECT * FROM snapshots 
        WHERE watchlist_id = ? AND id != ? 
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ');
    $prevStmt->execute([$watchlistId, $newSnapshotId]);
    $previousSnapshot = $prevStmt->fetch();

    $newSnapshot = [
        'id' => $newSnapshotId,
        'watchlist_id' => $watchlistId,
        'url' => $targetUrl,
        'dom_metrics_json' => json_encode($cleanResult['metrics']),
        'dom_skeleton_hash' => $cleanResult['dom_skeleton_hash'] ?? null,
        'status_code' => $fetchResult['status_code'],
        'response_time_ms' => $fetchResult['response_time_ms'],
        'content_type' => $fetchResult['content_type'],
        'content_hash' => $cleanResult['content_hash'],
        'cleaned_content' => $cleanResult['canonical_text'],
        'raw_content' => $fetchResult['raw_body'],
        'page_title' => $snapshotLabel,
        'meta_description' => $cleanResult['meta_description'],
        'security_headers_json' => json_encode($fetchResult['security_headers']),
        'security_score' => $securityScore,
        'security_grade' => $securityGrade,
        'vuln_report_json' => $vulnReportJson,
        'vuln_report' => $vulnReport,
        'triggered_by' => $triggeredBy,
        'created_at' => $now,
    ];

    $comparisonRecord = null;

    if ($previousSnapshot) {
        $prevMetrics = json_decode($previousSnapshot['dom_metrics_json'] ?? '{}', true) ?: [];
        $previousSnapshot['dom_skeleton_hash'] = $prevMetrics['dom_skeleton_hash'] 
            ?? ContentSanitizer::extractDomSkeletonHash((string)($previousSnapshot['cleaned_content'] ?? ''));

        // Calculate structural line diff
        $diff = Differ::diff(
            (string)($previousSnapshot['cleaned_content'] ?? ''),
            $cleanResult['canonical_text']
        );

        $executionLog[] = "Step 4a: Diff calculated (+{$diff['stats']['added']} / -{$diff['stats']['removed']})";

        // Run configured QA report engine Semantic Analysis
        try {
            $llmAnalysis = LlmClient::analyzeDiff(
                $previousSnapshot,
                $newSnapshot,
                $diff['stats'],
                $diff['unified']
            );
        } catch (RuntimeException $e) {
            Auth::jsonResponse(['success' => false, 'step_failed' => 'analysis', 'snapshot_id' => $newSnapshotId, 'error' => $e->getMessage()], 502);
        }

        // Defacement Notification Dispatch
        if ($llmAnalysis['verdict'] === 'DO NOT SHIP' && ($llmAnalysis['source'] ?? '') === 'defacement_heuristic_guard') {
            Notifier::sendAlert($target, 'DEFACEMENT', 'CRITICAL', "DEFACEMENT INTERCEPTED on {$target['name']} ({$targetUrl})! Structural tampering or defacement keywords identified.");
        } else {
            Notifier::resolveIncident($watchlistId, 'DEFACEMENT', "DOM structure verified healthy.");
        }

        $executionLog[] = "Step 4b: LLM Semantic Analysis complete (Verdict: {$llmAnalysis['verdict']}, Source: {$llmAnalysis['source']})";

        // Store comparison record
        $compIns = $pdo->prepare('
            INSERT INTO comparisons (
                watchlist_id, baseline_snapshot_id, comparison_snapshot_id,
                lines_added, lines_removed, lines_unchanged, raw_diff,
                semantic_changelog, qa_verdict, model_used, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $compIns->execute([
            $watchlistId,
            $previousSnapshot['id'],
            $newSnapshotId,
            $diff['stats']['added'],
            $diff['stats']['removed'],
            $diff['stats']['unchanged'],
            $diff['unified'],
            $llmAnalysis['changelog_markdown'],
            $llmAnalysis['verdict'],
            $llmAnalysis['model'],
            $now,
        ]);
        $comparisonId = (int)$pdo->lastInsertId();

        $comparisonRecord = [
            'id' => $comparisonId,
            'baseline_id' => $previousSnapshot['id'],
            'baseline_date' => $previousSnapshot['created_at'],
            'comparison_id' => $newSnapshotId,
            'comparison_date' => $now,
            'stats' => $diff['stats'],
            'verdict' => $llmAnalysis['verdict'],
            'changelog' => $llmAnalysis['changelog_markdown'],
            'model' => $llmAnalysis['model'],
            'source' => $llmAnalysis['source'],
        ];
    } else {
        try {
            $llmAnalysis = LlmClient::analyzeSnapshot($newSnapshot);
        } catch (RuntimeException $e) {
            Auth::jsonResponse(['success' => false, 'step_failed' => 'analysis', 'snapshot_id' => $newSnapshotId, 'error' => $e->getMessage()], 502);
        }
        $executionLog[] = "Step 4: Initial QA report generated (Source: {$llmAnalysis['source']})";
    }
    $report = $pdo->prepare('UPDATE snapshots SET qa_report = ?, qa_verdict = ?, qa_model = ?, qa_source = ? WHERE id = ?');
    $report->execute([$llmAnalysis['changelog_markdown'], $llmAnalysis['verdict'], $llmAnalysis['model'], $llmAnalysis['source'], $newSnapshotId]);
    $newSnapshot['qa_report'] = $llmAnalysis['changelog_markdown'];
    $newSnapshot['qa_verdict'] = $llmAnalysis['verdict'];
    $newSnapshot['qa_model'] = $llmAnalysis['model'];
    $newSnapshot['qa_source'] = $llmAnalysis['source'];

    Auth::jsonResponse([
        'success' => true,
        'message' => 'QA Run completed successfully.',
        'snapshot_id' => $newSnapshotId,
        'execution_log' => $executionLog,
        'snapshot' => $newSnapshot,
        'has_comparison' => ($comparisonRecord !== null),
        'comparison' => $comparisonRecord,
    ]);
}
