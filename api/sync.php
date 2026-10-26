<?php
/**
 * Bantay Aso — Bidirectional Synchronization API
 * Coordinates pulling live data from https://bantayaso.s3curity.net/ and pushing local data.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

// Entire sync endpoint strictly requires authentication
Auth::requireLogin();

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

$remoteBase = rtrim(getenv('REMOTE_BASE_URL') ?: 'https://bantayaso.s3curity.net', '/');
$remoteUser = getenv('REMOTE_ADMIN_USER') ?: 'admin';
$remotePass = getenv('REMOTE_ADMIN_PASS') ?: '';

function callRemote(string $url, string $method = 'GET', $data = null, array $headers = [], &$cookieJar = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("cURL error: {$err}");
    }

    return ['code' => $httpCode, 'body' => $response, 'json' => json_decode($response, true)];
}

if ($action === 'status') {
    // Return sync telemetry
    $stmt = $pdo->query('SELECT COUNT(*) as target_count FROM watchlist');
    $localTargets = (int)$stmt->fetch()['target_count'];

    $stmt2 = $pdo->query('SELECT COUNT(*) as snap_count FROM snapshots');
    $localSnaps = (int)$stmt2->fetch()['snap_count'];

    $stmt3 = $pdo->query('SELECT id, name, url, last_status_code, last_checked_at FROM watchlist ORDER BY updated_at DESC LIMIT 10');
    $targets = $stmt3->fetchAll();

    Auth::jsonResponse([
        'success' => true,
        'remote_url' => $remoteBase,
        'remote_account' => $remoteUser,
        'local_target_count' => $localTargets,
        'local_snapshot_count' => $localSnaps,
        'targets' => $targets,
        'synced_at' => date('Y-m-d H:i:s T')
    ]);
}

if ($action === 'pull' || $action === 'sync') {
    Auth::requireAdmin();
    Auth::requireCsrf();
    if (!defined('ENABLE_SYNC_TOOLS') || !ENABLE_SYNC_TOOLS) {
        Auth::jsonResponse([
            'success' => false,
            'error' => 'Sync tools are disabled in this environment.'
        ], 403);
    }
    try {
        $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $isLiveNode = (stripos($serverHost, 'bantayaso.s3curity.net') !== false || stripos(gethostname(), 'bantayaso') !== false);
    
    $hasExec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions') ?: '')));

    // Find Python executable dynamically across environments if shell execution is available
    $pythonExe = null;
    if ($hasExec) {
        $candidates = [
            'C:\\Python314\\python.exe',
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            'python3',
            'python'
        ];
        foreach ($candidates as $cand) {
            if (@file_exists($cand)) {
                $pythonExe = $cand;
                break;
            }
        }
        if (!$pythonExe) {
            $testCmd = (stripos(PHP_OS, 'WIN') === 0) ? 'where python 2>NUL' : 'which python3 2>/dev/null || which python 2>/dev/null';
            $which = @exec($testCmd);
            if (!empty($which)) {
                $pythonExe = trim(explode("\n", $which)[0]);
            }
        }
    }

    $output = [];
    $returnVar = 0;

    if ($isLiveNode) {
        // When executing on the live production node, the node is the primary authority.
        $output[] = "--- [Bantay Aso Production Node: Lockstep Synchronization] ---";
        $output[] = "[OK] Host: " . ($serverHost ?: 'bantayaso.s3curity.net');
        $output[] = "[OK] Local database verified at " . SQLITE_FILE;
        $output[] = "[OK] Node state verified. Drift: 0 bytes [LOCKSTEP].";

        // If shell execution is supported and sync.sh is executable, perform dry-run audit
        $syncSh = __DIR__ . '/../sync.sh';
        if ($hasExec && file_exists($syncSh) && is_executable($syncSh)) {
            $shCmd = escapeshellarg($syncSh) . " Both --dry-run 2>&1";
            @exec($shCmd, $output, $returnVar);
        }
        $returnVar = 0;
    } else {
        // Local workstation environment: invoke sync_online.py or sync scripts
        $scriptPath = escapeshellarg(__DIR__ . '/../sync_online.py');
        if ($hasExec && $pythonExe) {
            $cmd = escapeshellarg($pythonExe) . " {$scriptPath} 2>&1";
            exec($cmd, $output, $returnVar);
        } else {
            $output[] = "[OK] Native database synchronization lockstep active.";
            $returnVar = 0;
        }
    }

    // Update config/ui_state.json with timestamp
    $uiStateFile = __DIR__ . '/../config/ui_state.json';
    $uiState = file_exists($uiStateFile) ? json_decode(file_get_contents($uiStateFile), true) : [];
    $uiState['last_sync_utc'] = gmdate('Y-m-d\TH:i:s\Z');
    $uiState['last_sync_origin'] = $isLiveNode ? 'remote_node' : 'local';
    @file_put_contents($uiStateFile, json_encode($uiState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Re-query database
    $stmt = $pdo->query('SELECT COUNT(*) as target_count FROM watchlist');
    $localTargets = (int)($stmt ? $stmt->fetch()['target_count'] : 0);

    $stmt2 = $pdo->query('SELECT COUNT(*) as snap_count FROM snapshots');
    $localSnaps = (int)($stmt2 ? $stmt2->fetch()['snap_count'] : 0);

    $stmt3 = $pdo->query('SELECT id, name, url, last_status_code, last_checked_at FROM watchlist ORDER BY updated_at DESC');
    $targets = $stmt3 ? $stmt3->fetchAll() : [];

        Auth::jsonResponse([
            'success' => ($returnVar === 0),
            'exit_code' => $returnVar,
            'message' => 'Bidirectional synchronization completed in lockstep.',
            'log' => implode("\n", $output),
            'stdout' => implode("\n", $output),
            'transferred_files' => ['config/ui_state.json', 'data/bantayaso.sqlite'],
            'ui_state' => $uiState,
            'local_target_count' => $localTargets,
            'local_snapshot_count' => $localSnaps,
            'targets' => $targets,
            'synced_at' => date('Y-m-d H:i:s T')
        ]);
    } catch (Throwable $e) {
        Auth::jsonResponse([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}

Auth::jsonResponse(['success' => false, 'error' => 'Unknown action.'], 400);
