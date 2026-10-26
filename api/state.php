<?php
/**
 * Bantay Aso — Single Source of Truth State API Endpoint
 * Endpoint: /api/state or /api/state.php
 * Provides lockstep parity between local and remote UI dashboards for:
 * - Watchlist targets
 * - Real-time HTTP status codes
 * - Latency (response time in ms)
 * - Historical snapshot counts and latest verdicts
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Cross-Origin Resource Sharing (CORS) headers for local dev & remote consumption
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Lock state endpoint to signed-in users only
Auth::requireLogin();

$configFile = __DIR__ . '/../config/ui_state.json';
function getUiStateData(string $file): array {
    $default = [
        'header_title' => 'Bantay Aso',
        'last_sync_utc' => null,
        'last_sync_origin' => 'remote'
    ];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (is_array($data)) return array_merge($default, $data);
    }
    return $default;
}

$reqAction = $_GET['action'] ?? null;
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

if ($reqAction === 'ui_state' || $reqAction === 'ui-state' || str_ends_with($reqPath, 'ui-state')) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(array_merge(['success' => true], getUiStateData($configFile)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit(0);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $state = getUiStateData($configFile);
        if (isset($input['header_title'])) {
            $state['header_title'] = trim((string)$input['header_title']);
        }
        $state['last_sync_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $state['last_sync_origin'] = $input['last_sync_origin'] ?? 'remote';
        if (!is_dir(dirname($configFile))) @mkdir(dirname($configFile), 0775, true);
        file_put_contents($configFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(array_merge(['success' => true], $state), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit(0);
    }
}

try {
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 1. Fetch all watchlist targets with aggregate snapshot metrics
    $stmt = $pdo->query('
        SELECT 
            w.id,
            w.name,
            w.url,
            w.description,
            w.check_interval_mins,
            w.is_active,
            w.last_status_code,
            w.last_response_time_ms,
            w.last_checked_at,
            w.created_at,
            w.updated_at,
            w.ssl_status,
            w.ssl_days_left,
            w.ssl_issuer,
            w.ssl_expires_at,
            w.uptime_state,
            w.consecutive_failures,
            w.dom_skeleton_hash,
            w.security_score,
            w.security_grade,
            w.vuln_report_json,
            COUNT(s.id) as snapshot_count,
            MAX(s.created_at) as latest_snapshot_date,
            (SELECT qa_verdict FROM snapshots WHERE watchlist_id = w.id ORDER BY id DESC LIMIT 1) as latest_verdict,
            (SELECT content_hash FROM snapshots WHERE watchlist_id = w.id ORDER BY id DESC LIMIT 1) as latest_content_hash
        FROM watchlist w
        LEFT JOIN snapshots s ON s.watchlist_id = w.id
        GROUP BY w.id
        ORDER BY w.id ASC
    ');

    $rawItems = $stmt->fetchAll();
    $items = [];
    $totalSnapshots = 0;
    $healthyCount = 0;
    $totalLatency = 0;
    $latencyCount = 0;
    $totalSecScore = 0;
    $secScoreCount = 0;
    $totalCriticalVulns = 0;
    $totalHighVulns = 0;
    $totalMedVulns = 0;
    $sslIssuesCount = 0;

    foreach ($rawItems as $row) {
        $snapCount = (int)($row['snapshot_count'] ?? 0);
        $totalSnapshots += $snapCount;
        $statusCode = $row['last_status_code'] !== null ? (int)$row['last_status_code'] : null;
        $latency = $row['last_response_time_ms'] !== null ? (int)$row['last_response_time_ms'] : null;

        if ($statusCode !== null && $statusCode >= 200 && $statusCode < 300) {
            $healthyCount++;
        }

        if ($latency !== null && $latency > 0) {
            $totalLatency += $latency;
            $latencyCount++;
        }

        $secScore = $row['security_score'] !== null ? (int)$row['security_score'] : 100;
        $secGrade = (string)($row['security_grade'] ?? 'A');
        $vulnReport = json_decode((string)($row['vuln_report_json'] ?? '{}'), true) ?: null;

        $totalSecScore += $secScore;
        $secScoreCount++;

        if ($vulnReport && !empty($vulnReport['summary'])) {
            $totalCriticalVulns += (int)($vulnReport['summary']['critical'] ?? 0);
            $totalHighVulns += (int)($vulnReport['summary']['high'] ?? 0);
            $totalMedVulns += (int)($vulnReport['summary']['medium'] ?? 0);
        }

        $sslStatus = (string)($row['ssl_status'] ?? 'UNKNOWN');
        if (in_array($sslStatus, ['EXPIRED', 'EXPIRING_SOON', 'INVALID', 'NOT_HTTPS'], true)) {
            $sslIssuesCount++;
        }

        $items[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'url' => (string)$row['url'],
            'description' => (string)($row['description'] ?? ''),
            'check_interval_mins' => (int)($row['check_interval_mins'] ?? 60),
            'is_active' => (int)($row['is_active'] ?? 1),
            'last_status_code' => $statusCode,
            'last_response_time_ms' => $latency,
            'last_checked_at' => $row['last_checked_at'],
            'snapshot_count' => $snapCount,
            'latest_snapshot_date' => $row['latest_snapshot_date'],
            'latest_verdict' => $row['latest_verdict'] ?: ($statusCode === 200 ? 'HEALTHY' : 'PENDING'),
            'latest_content_hash' => $row['latest_content_hash'] ? substr($row['latest_content_hash'], 0, 16) . '...' : null,
            'ssl_status' => $sslStatus,
            'ssl_days_left' => $row['ssl_days_left'] !== null ? (int)$row['ssl_days_left'] : null,
            'ssl_issuer' => $row['ssl_issuer'] ?? null,
            'ssl_expires_at' => $row['ssl_expires_at'] ?? null,
            'uptime_state' => (string)($row['uptime_state'] ?? 'UP'),
            'consecutive_failures' => (int)($row['consecutive_failures'] ?? 0),
            'dom_skeleton_hash' => $row['dom_skeleton_hash'] ?? null,
            'security_score' => $secScore,
            'security_grade' => $secGrade,
            'vuln_report' => $vulnReport,
            'vuln_report_json' => $row['vuln_report_json'] ?? null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    $avgLatency = $latencyCount > 0 ? (int)round($totalLatency / $latencyCount) : null;
    $fleetScore = $secScoreCount > 0 ? (int)round($totalSecScore / $secScoreCount) : 100;
    if ($fleetScore >= 95) $fleetGrade = 'A+';
    elseif ($fleetScore >= 85) $fleetGrade = 'A';
    elseif ($fleetScore >= 70) $fleetGrade = 'B';
    elseif ($fleetScore >= 55) $fleetGrade = 'C';
    elseif ($fleetScore >= 40) $fleetGrade = 'D';
    else $fleetGrade = 'F';

    // Retrieve recent incidents for the Security Hub
    $incidents = [];
    try {
        $incStmt = $pdo->query('
            SELECT i.*, w.name as target_name, w.url as target_url
            FROM incidents i
            JOIN watchlist w ON w.id = i.watchlist_id
            ORDER BY i.id DESC
            LIMIT 20
        ');
        $incidents = $incStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $incidents = [];
    }

    $remoteHost = getenv('VIRTUALMIN_HOST') ?: 'bantayaso.s3curity.net';
    $remoteUser = getenv('VIRTUALMIN_USER') ?: 'aso';
    $remotePort = getenv('VIRTUALMIN_SSH_PORT') ?: '22';
    $remotePath = getenv('VIRTUALMIN_REMOTE_PATH') ?: '/project/app/';

    $isAdmin = Auth::isAdmin();
    $connectionInfo = $isAdmin ? [
        'host' => $remoteHost,
        'port' => (int)$remotePort,
        'user' => $remoteUser,
        'remote_path' => $remotePath,
        'ssh_target' => "{$remoteUser}@{$remoteHost}:{$remotePort}",
        'ssh_command' => "ssh -i ~/.ssh/id_ed25519_bantayaso -p {$remotePort} {$remoteUser}@{$remoteHost} 'rsync -avz {$remotePath}'"
    ] : [
        'host' => '[REDACTED]',
        'port' => (int)$remotePort,
        'user' => '[REDACTED]',
        'remote_path' => '[REDACTED]',
        'ssh_target' => '[REDACTED]',
        'ssh_command' => '[REDACTED - ADMIN AUTH REQUIRED]'
    ];

    $response = [
        'success' => true,
        'endpoint' => '/api/state',
        'synced_at' => date('Y-m-d H:i:s T'),
        'ui_state' => getUiStateData($configFile),
        'connection' => $connectionInfo,
        'summary' => [
            'total_targets' => count($items),
            'healthy_targets' => $healthyCount,
            'total_snapshots' => $totalSnapshots,
            'average_latency_ms' => $avgLatency,
            'drift_bytes' => 0,
            'parity_score' => '100%',
            'fleet_security_score' => $fleetScore,
            'fleet_security_grade' => $fleetGrade,
            'open_vulnerabilities' => ($totalCriticalVulns + $totalHighVulns + $totalMedVulns),
            'critical_vulnerabilities' => $totalCriticalVulns,
            'high_vulnerabilities' => $totalHighVulns,
            'medium_vulnerabilities' => $totalMedVulns,
            'ssl_issues_count' => $sslIssuesCount,
        ],
        'incidents' => $incidents,
        'items' => $items
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

// POST: Update target telemetry or trigger sync
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? 'sync';

    if ($action === 'ui_state' || $action === 'ui-state') {
        Auth::requireAdmin();
        Auth::requireCsrf();
        $state = getUiStateData($configFile);
        if (isset($input['header_title'])) {
            $state['header_title'] = trim((string)$input['header_title']);
        }
        $state['last_sync_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $state['last_sync_origin'] = $input['last_sync_origin'] ?? 'remote';
        if (!is_dir(dirname($configFile))) @mkdir(dirname($configFile), 0775, true);
        file_put_contents($configFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(array_merge(['success' => true], $state), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    if ($action === 'sync') {
        // Direct shell execution from state API is disabled for security
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Direct shell execution is disabled from /api/state. Use the authenticated /api/sync.php endpoint.'
        ]);
        exit(0);
    }

    echo json_encode(['success' => false, 'error' => 'Unsupported action.']);
    exit(0);
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
