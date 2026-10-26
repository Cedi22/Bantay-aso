<?php
/**
 * Bantay Aso — Date-to-Date Diff & Semantic Changelog API
 * Computes side-by-side / unified diffs and fetches or generates AI semantic changelogs
 * between any two historical snapshot dates.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/differ.php';
require_once __DIR__ . '/../includes/llm_client.php';
require_once __DIR__ . '/../includes/hrms_qac_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Entire diff endpoint strictly requires authentication
Auth::requireLogin();

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$action = $_GET['action'] ?? $input['action'] ?? 'compare';

if ($action === 'compare') {
    $baselineId = (int)($_GET['baseline_id'] ?? $input['baseline_id'] ?? 0);
    $comparisonId = (int)($_GET['comparison_id'] ?? $input['comparison_id'] ?? 0);

    if ($baselineId <= 0 || $comparisonId <= 0) {
        Auth::jsonResponse(['success' => false, 'error' => 'Valid baseline_id and comparison_id are required.'], 400);
    }

    if ($baselineId === $comparisonId) {
        Auth::jsonResponse(['success' => false, 'error' => 'Baseline and comparison dates must be different.'], 400);
    }

    // Retrieve both snapshots
    $stmt = $pdo->prepare('SELECT * FROM snapshots WHERE id IN (?, ?)');
    $stmt->execute([$baselineId, $comparisonId]);
    $rows = $stmt->fetchAll();

    $snapshots = [];
    foreach ($rows as $r) {
        $snapshots[(int)$r['id']] = $r;
    }

    if (!isset($snapshots[$baselineId]) || !isset($snapshots[$comparisonId])) {
        Auth::jsonResponse(['success' => false, 'error' => 'One or both snapshot dates were not found.'], 404);
    }

    $baseline = $snapshots[$baselineId];
    $comparison = $snapshots[$comparisonId];

    // Compute live line-by-line diff (both side-by-side array and unified text)
    $diffResult = Differ::diff(
        (string)($baseline['cleaned_content'] ?? ''),
        (string)($comparison['cleaned_content'] ?? '')
    );

    // Check for cached comparison in database
    $compStmt = $pdo->prepare('
        SELECT * FROM comparisons
        WHERE baseline_snapshot_id = ? AND comparison_snapshot_id = ?
        ORDER BY created_at DESC LIMIT 1
    ');
    $compStmt->execute([$baselineId, $comparisonId]);
    $cached = $compStmt->fetch();

    $semanticChangelog = null;
    $qaVerdict = 'INCONCLUSIVE';
    $modelUsed = null;
    $source = 'cache';

    if ($cached && !empty($cached['semantic_changelog'])) {
        $semanticChangelog = $cached['semantic_changelog'];
        $qaVerdict = $cached['qa_verdict'];
        $modelUsed = $cached['model_used'];
    } else {
        // Run AI Semantic Analysis on demand
        $llmRes = LlmClient::analyzeDiff(
            $baseline,
            $comparison,
            $diffResult['stats'],
            $diffResult['unified']
        );

        $semanticChangelog = $llmRes['changelog_markdown'];
        $qaVerdict = $llmRes['verdict'];
        $modelUsed = $llmRes['model'];
        $source = $llmRes['source'];

        // Store comparison record
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare('
            INSERT INTO comparisons (
                watchlist_id, baseline_snapshot_id, comparison_snapshot_id,
                lines_added, lines_removed, lines_unchanged, raw_diff,
                semantic_changelog, qa_verdict, model_used, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $ins->execute([
            $baseline['watchlist_id'],
            $baselineId,
            $comparisonId,
            $diffResult['stats']['added'],
            $diffResult['stats']['removed'],
            $diffResult['stats']['unchanged'],
            $diffResult['unified'],
            $semanticChangelog,
            $qaVerdict,
            $modelUsed,
            $now,
        ]);
    }

    Auth::jsonResponse([
        'success' => true,
        'baseline' => [
            'id' => $baseline['id'],
            'created_at' => $baseline['created_at'],
            'status_code' => $baseline['status_code'],
            'response_time_ms' => $baseline['response_time_ms'],
            'page_title' => $baseline['page_title'],
            'content_hash' => $baseline['content_hash'],
            'security_headers' => json_decode($baseline['security_headers_json'] ?? '{}', true),
        ],
        'comparison' => [
            'id' => $comparison['id'],
            'created_at' => $comparison['created_at'],
            'status_code' => $comparison['status_code'],
            'response_time_ms' => $comparison['response_time_ms'],
            'page_title' => $comparison['page_title'],
            'content_hash' => $comparison['content_hash'],
            'security_headers' => json_decode($comparison['security_headers_json'] ?? '{}', true),
        ],
        'stats' => $diffResult['stats'],
        'side_by_side' => $diffResult['side_by_side'],
        'unified' => $diffResult['unified'],
        'semantic_changelog' => $semanticChangelog,
        'verdict' => $qaVerdict,
        'model' => $modelUsed,
        'source' => $source,
        'qac_data' => ((int)$baseline['watchlist_id'] === 3 || (int)$baseline['watchlist_id'] === 6 || str_contains($baseline['page_title'] ?? '', 'QAC') || str_contains($comparison['page_title'] ?? '', 'QAC')) ? HrmsQacHelper::getSummary($baseline, $comparison) : null,
    ]);
}

// POST action=regenerate_ai: Force re-run LLM semantic analysis (Admin only)
if ($action === 'regenerate_ai') {
    Auth::requireAdmin();
    Auth::requireCsrf();

    $baselineId = (int)($input['baseline_id'] ?? 0);
    $comparisonId = (int)($input['comparison_id'] ?? 0);

    if ($baselineId <= 0 || $comparisonId <= 0) {
        Auth::jsonResponse(['success' => false, 'error' => 'Valid IDs required.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM snapshots WHERE id IN (?, ?)');
    $stmt->execute([$baselineId, $comparisonId]);
    $rows = $stmt->fetchAll();

    $snapshots = [];
    foreach ($rows as $r) {
        $snapshots[(int)$r['id']] = $r;
    }

    if (!isset($snapshots[$baselineId]) || !isset($snapshots[$comparisonId])) {
        Auth::jsonResponse(['success' => false, 'error' => 'Snapshots not found.'], 404);
    }

    $baseline = $snapshots[$baselineId];
    $comparison = $snapshots[$comparisonId];

    $diffResult = Differ::diff(
        (string)($baseline['cleaned_content'] ?? ''),
        (string)($comparison['cleaned_content'] ?? '')
    );

    $llmRes = LlmClient::analyzeDiff(
        $baseline,
        $comparison,
        $diffResult['stats'],
        $diffResult['unified']
    );

    $now = date('Y-m-d H:i:s');
    // Update or insert comparison record
    $del = $pdo->prepare('DELETE FROM comparisons WHERE baseline_snapshot_id = ? AND comparison_snapshot_id = ?');
    $del->execute([$baselineId, $comparisonId]);

    $ins = $pdo->prepare('
        INSERT INTO comparisons (
            watchlist_id, baseline_snapshot_id, comparison_snapshot_id,
            lines_added, lines_removed, lines_unchanged, raw_diff,
            semantic_changelog, qa_verdict, model_used, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $ins->execute([
        $baseline['watchlist_id'],
        $baselineId,
        $comparisonId,
        $diffResult['stats']['added'],
        $diffResult['stats']['removed'],
        $diffResult['stats']['unchanged'],
        $diffResult['unified'],
        $llmRes['changelog_markdown'],
        $llmRes['verdict'],
        $llmRes['model'],
        $now,
    ]);

    Auth::jsonResponse([
        'success' => true,
        'message' => 'AI Semantic Changelog regenerated.',
        'semantic_changelog' => $llmRes['changelog_markdown'],
        'verdict' => $llmRes['verdict'],
        'model' => $llmRes['model'],
        'source' => $llmRes['source'],
    ]);
}
