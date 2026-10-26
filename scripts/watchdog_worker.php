<?php
/**
 * Bantay Aso — Autonomous CLI Watchdog Worker
 *
 * Automated scheduled sentinel for:
 * 1. Fast uptime pinging & latency measurement
 * 2. SSL/TLS certificate expiry auditing
 * 3. Conditional Deep QA, DOM skeleton hashing & defacement detection
 * 4. Multi-channel webhook alerts (Discord/Slack/Generic)
 *
 * Usage:
 *   php scripts/watchdog_worker.php           # Standard scheduled run
 *   php scripts/watchdog_worker.php --dry-run # Preview inspection without commits
 *   php scripts/watchdog_worker.php --force   # Force inspection of all active targets
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: Watchdog worker must be executed via command-line (CLI).\n";
    exit(1);
}

set_time_limit(0);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fetcher.php';
require_once __DIR__ . '/../includes/sanitizer.php';
require_once __DIR__ . '/../includes/differ.php';
require_once __DIR__ . '/../includes/llm_client.php';
require_once __DIR__ . '/../includes/notifier.php';
require_once __DIR__ . '/../includes/security_scanner.php';

// Parse CLI Flags
$isDryRun = in_array('--dry-run', $argv, true);
$isForce = in_array('--force', $argv, true);
$limit = 10;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    }
}

echo "=====================================================================\n";
echo "🐕 Bantay Aso — Autonomous Watchdog Worker Sentinel\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (Preview Only)" : "LIVE EXECUTION") . " | Limit: {$limit} targets\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "=====================================================================\n";

// Concurrency lock to prevent overlapping cron runs
$lockFile = DATA_PATH . DIRECTORY_SEPARATOR . 'watchdog.lock';
if (!is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0750, true);
}
$lockFp = fopen($lockFile, 'c+');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[!] Another watchdog worker instance is already running. Exiting.\n";
    exit(0);
}

$pdo = Database::getConnection();

// Select due targets
if ($isForce) {
    $stmt = $pdo->prepare('
        SELECT * FROM watchlist 
        WHERE is_active = 1 
        ORDER BY last_checked_at ASC LIMIT ?
    ');
    $stmt->execute([$limit]);
} else {
    if (DB_TYPE === 'sqlite') {
        $stmt = $pdo->prepare("
            SELECT * FROM watchlist 
            WHERE is_active = 1 
              AND (last_checked_at IS NULL 
                   OR datetime(last_checked_at, '+' || COALESCE(check_interval_mins, 15) || ' minutes') <= datetime('now'))
            ORDER BY last_checked_at ASC LIMIT ?
        ");
        $stmt->execute([$limit]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM watchlist 
            WHERE is_active = 1 
              AND (last_checked_at IS NULL 
                   OR DATE_ADD(last_checked_at, INTERVAL COALESCE(check_interval_mins, 15) MINUTE) <= NOW())
            ORDER BY last_checked_at ASC LIMIT ?
        ");
        $stmt->execute([$limit]);
    }
}

$targets = $stmt->fetchAll();
$totalTargets = count($targets);

if ($totalTargets === 0) {
    echo "[*] All targets are up to date. No pending inspections.\n";
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

echo "[+] Identified {$totalTargets} target(s) due for inspection.\n\n";

foreach ($targets as $idx => $target) {
    $num = $idx + 1;
    $targetId = (int)$target['id'];
    $name = $target['name'];
    $url = $target['url'];

    echo "---------------------------------------------------------------------\n";
    echo "[{$num}/{$totalTargets}] Inspecting Target #{$targetId}: {$name}\n";
    echo "    URL: {$url}\n";

    // -------------------------------------------------------------------------
    // STEP A: Fast Ping & SSL Inspection
    // -------------------------------------------------------------------------
    $fetchStart = microtime(true);
    $fetch = WebFetcher::fetch($url, 15);
    $ssl = WebFetcher::inspectSsl($url);
    $now = date('Y-m-d H:i:s');

    $statusCode = $fetch['status_code'];
    $latencyMs = $fetch['response_time_ms'];
    $sslStatus = $ssl['status'];
    $sslDays = $ssl['days_left'];
    $sslIssuer = $ssl['issuer'];
    $isUp = ($fetch['success'] && $statusCode >= 200 && $statusCode < 400);

    echo "    [Ping] HTTP {$statusCode} in {$latencyMs}ms | Uptime: " . ($isUp ? "UP" : "FAILURE") . "\n";
    echo "    [SSL]  Status: {$sslStatus} (" . ($sslDays !== null ? "{$sslDays} days left" : "N/A") . ") | Issuer: " . ($sslIssuer ?: "N/A") . "\n";

    // -------------------------------------------------------------------------
    // STEP B: Uptime & SSL Incident Management
    // -------------------------------------------------------------------------
    $consecutiveFailures = (int)($target['consecutive_failures'] ?? 0);

    if (!$isUp) {
        $consecutiveFailures++;
        $uptimeState = ($consecutiveFailures >= 2) ? 'DOWN' : 'DEGRADED';
        echo "    [!] Target check failed ({$consecutiveFailures} consecutive failure(s)).\n";

        if ($consecutiveFailures >= 2 && !$isDryRun) {
            $alertMsg = "Target is DOWN: HTTP {$statusCode} ({$latencyMs}ms). Error: " . ($fetch['error'] ?? 'Unreachable');
            Notifier::sendAlert($target, 'DOWNTIME', 'CRITICAL', $alertMsg);
        }
    } else {
        $consecutiveFailures = 0;
        $uptimeState = 'UP';
        if (!$isDryRun) {
            Notifier::resolveIncident($targetId, 'DOWNTIME', "Target responding with HTTP {$statusCode} ({$latencyMs}ms).");
        }
    }

    // SSL Alerting
    if (!$isDryRun) {
        if ($sslStatus === 'EXPIRED') {
            Notifier::sendAlert($target, 'SSL_EXPIRY', 'CRITICAL', "SSL certificate has EXPIRED on {$ssl['valid_to']} ({$sslIssuer}).");
        } elseif ($sslStatus === 'EXPIRING_SOON') {
            Notifier::sendAlert($target, 'SSL_EXPIRY', 'WARNING', "SSL certificate will expire in {$sslDays} day(s) on {$ssl['valid_to']} ({$sslIssuer}).");
        } elseif ($sslStatus === 'VALID') {
            Notifier::resolveIncident($targetId, 'SSL_EXPIRY', "SSL certificate valid for {$sslDays} days.");
        }
    }

    // -------------------------------------------------------------------------
    // STEP C: Conditional Deep QA & Defacement Check
    // -------------------------------------------------------------------------
    $clean = ContentSanitizer::process($fetch['raw_body'] ?? '');
    $newHash = $clean['content_hash'];
    $newSkeleton = $clean['dom_skeleton_hash'];

    // Retrieve previous snapshot
    $prevStmt = $pdo->prepare('
        SELECT * FROM snapshots 
        WHERE watchlist_id = ? 
        ORDER BY created_at DESC, id DESC LIMIT 1
    ');
    $prevStmt->execute([$targetId]);
    $prevSnapshot = $prevStmt->fetch();

    $prevHash = $prevSnapshot['content_hash'] ?? null;
    $prevMetrics = json_decode($prevSnapshot['dom_metrics_json'] ?? '{}', true) ?: [];
    $prevSkeleton = $prevMetrics['dom_skeleton_hash'] ?? null;

    $hasContentChanged = ($prevSnapshot === false || $newHash !== $prevHash || $newSkeleton !== $prevSkeleton);

    // Run Passive Vulnerability & Security Posture Audit
    $vulnReport = SecurityScanner::auditTarget($url, $fetch, $ssl);
    $securityScore = (int)$vulnReport['security_score'];
    $securityGrade = (string)$vulnReport['security_grade'];
    $vulnReportJson = json_encode($vulnReport, JSON_UNESCAPED_SLASHES);
    echo "    [Sec]  Score: {$securityScore}/100 (Grade {$securityGrade}) | Findings: {$vulnReport['summary']['critical']} Crit, {$vulnReport['summary']['high']} High, {$vulnReport['summary']['medium']} Med\n";

    if ($hasContentChanged) {
        echo "    [Deep QA] Content drift detected (Hash: " . substr($newHash, 0, 12) . "...). Running QA engine...\n";

        $clean['metrics']['dom_skeleton_hash'] = $newSkeleton;
        $clean['metrics']['raw_page_title'] = $clean['page_title'];
        $clean['metrics']['security_score'] = $securityScore;
        $clean['metrics']['security_grade'] = $securityGrade;

        // Auto-generate Semantic Version Label
        $snapshotLabel = Database::generateNextSnapshotVersionLabel($pdo, $targetId, $name);

        if (!$isDryRun) {
            // Commit snapshot
            $insSnap = $pdo->prepare('
                INSERT INTO snapshots (
                    watchlist_id, status_code, response_time_ms, content_type,
                    content_hash, raw_headers, security_headers_json, cleaned_content,
                    raw_content, page_title, meta_description, dom_metrics_json,
                    security_score, security_grade, vuln_report_json, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insSnap->execute([
                $targetId,
                $statusCode,
                $latencyMs,
                $fetch['content_type'],
                $newHash,
                $fetch['raw_headers'],
                json_encode($fetch['security_headers'], JSON_UNESCAPED_SLASHES),
                $clean['canonical_text'],
                $fetch['raw_body'],
                $snapshotLabel,
                $clean['meta_description'],
                json_encode($clean['metrics']),
                $securityScore,
                $securityGrade,
                $vulnReportJson,
                $now,
            ]);
            $newSnapshotId = (int)$pdo->lastInsertId();

            $newSnapshotRow = [
                'id' => $newSnapshotId,
                'watchlist_id' => $targetId,
                'url' => $url,
                'status_code' => $statusCode,
                'response_time_ms' => $latencyMs,
                'content_hash' => $newHash,
                'dom_skeleton_hash' => $newSkeleton,
                'cleaned_content' => $clean['canonical_text'],
                'raw_content' => $fetch['raw_body'],
                'page_title' => $snapshotLabel,
                'security_headers_json' => json_encode($fetch['security_headers']),
                'security_score' => $securityScore,
                'security_grade' => $securityGrade,
                'vuln_report_json' => $vulnReportJson,
                'vuln_report' => $vulnReport,
                'created_at' => $now,
            ];

            if ($prevSnapshot) {
                $prevSnapshot['dom_skeleton_hash'] = $prevSkeleton;
                $diff = Differ::diff((string)($prevSnapshot['cleaned_content'] ?? ''), $clean['canonical_text']);
                $analysis = LlmClient::analyzeDiff($prevSnapshot, $newSnapshotRow, $diff['stats'], $diff['unified']);

                // Commit comparison
                $insComp = $pdo->prepare('
                    INSERT INTO comparisons (
                        watchlist_id, baseline_snapshot_id, comparison_snapshot_id,
                        lines_added, lines_removed, lines_unchanged, raw_diff,
                        semantic_changelog, qa_verdict, model_used, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $insComp->execute([
                    $targetId,
                    $prevSnapshot['id'],
                    $newSnapshotId,
                    $diff['stats']['added'],
                    $diff['stats']['removed'],
                    $diff['stats']['unchanged'],
                    $diff['unified'],
                    $analysis['changelog_markdown'],
                    $analysis['verdict'],
                    $analysis['model'],
                    $now,
                ]);

                // Defacement Alerting
                if ($analysis['verdict'] === 'DO NOT SHIP' && ($analysis['source'] ?? '') === 'defacement_heuristic_guard') {
                    echo "    [🚨 DEFACEMENT DETECTED] Triggering critical watchdog notification!\n";
                    Notifier::sendAlert($target, 'DEFACEMENT', 'CRITICAL', "DEFACEMENT INTERCEPTED on {$name} ({$url})! Structural tampering or defacement keywords identified.");
                } else {
                    Notifier::resolveIncident($targetId, 'DEFACEMENT', "DOM structure and content verified intact.");
                }
            } else {
                $analysis = LlmClient::analyzeSnapshot($newSnapshotRow);
            }

            // Update snapshot report metadata
            $updSnap = $pdo->prepare('UPDATE snapshots SET qa_report = ?, qa_verdict = ?, qa_model = ?, qa_source = ? WHERE id = ?');
            $updSnap->execute([$analysis['changelog_markdown'], $analysis['verdict'], $analysis['model'], $analysis['source'], $newSnapshotId]);
            echo "    [Verdict] {$analysis['verdict']} (Engine: {$analysis['model']})\n";
        } else {
            echo "    [DryRun] Would commit snapshot [{$snapshotLabel}] and evaluate diff against Baseline #{$prevSnapshot['id']}.\n";
        }
    } else {
        echo "    [Deep QA] Zero Drift. Content and DOM skeleton unchanged. Skipping full diff pass.\n";
    }

    // -------------------------------------------------------------------------
    // STEP D: Update Watchlist Record
    // -------------------------------------------------------------------------
    if (!$isDryRun) {
        $updWatch = $pdo->prepare('
            UPDATE watchlist 
            SET last_status_code = ?,
                last_response_time_ms = ?,
                last_checked_at = ?,
                updated_at = ?,
                ssl_expires_at = ?,
                ssl_days_left = ?,
                ssl_issuer = ?,
                ssl_status = ?,
                uptime_state = ?,
                consecutive_failures = ?,
                dom_skeleton_hash = ?,
                security_score = ?,
                security_grade = ?,
                vuln_report_json = ?
            WHERE id = ?
        ');
        $updWatch->execute([
            $statusCode,
            $latencyMs,
            $now,
            $now,
            $ssl['valid_to'],
            $sslDays,
            $sslIssuer,
            $sslStatus,
            $uptimeState,
            $consecutiveFailures,
            $newSkeleton,
            $securityScore,
            $securityGrade,
            $vulnReportJson,
            $targetId,
        ]);
    }
}

echo "---------------------------------------------------------------------\n";
echo "✓ Watchdog inspection pass completed at " . date('Y-m-d H:i:s T') . "\n";
echo "=====================================================================\n";

flock($lockFp, LOCK_UN);
fclose($lockFp);
