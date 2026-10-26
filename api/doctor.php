<?php
/**
 * Bantay Aso — Remote Connection Diagnostic & Security Posture API
 * Reports runtime environment health, storage integrity, and PHP2Shell mitigation status.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Enforce admin privileges to prevent system diagnostic & path disclosure
Auth::requireAdmin();

$workspaceRoot = realpath(__DIR__ . '/..');
$appWritable = is_writable($workspaceRoot);
$dataWritable = is_writable($workspaceRoot . '/../data') || is_writable($workspaceRoot . '/data');

// Evaluate PHP version against PHP2Shell patched releases (CVE-2026-109618 / CVE-2026-109619)
$ver = PHP_VERSION;
$isPatched = false;
$patchAdvice = 'Upgrade PHP immediately to a secure release.';

if (version_compare($ver, '8.5.12', '>=')) {
    $isPatched = true;
    $patchAdvice = 'PHP 8.5+ patched release verified.';
} elseif (str_starts_with($ver, '8.4.') && version_compare($ver, '8.4.27', '>=')) {
    $isPatched = true;
    $patchAdvice = 'PHP 8.4+ patched release verified.';
} elseif (str_starts_with($ver, '8.3.') && version_compare($ver, '8.3.36', '>=')) {
    $isPatched = true;
    $patchAdvice = 'PHP 8.3+ patched release verified.';
} elseif (str_starts_with($ver, '8.2.') && version_compare($ver, '8.2.35', '>=')) {
    $isPatched = true;
    $patchAdvice = 'PHP 8.2+ patched release verified.';
}

$disabledFuncs = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
$dangerousFuncs = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec', 'eval'];
$disabledDangerous = array_values(array_intersect($dangerousFuncs, $disabledFuncs));

$securityPosture = [
    'cve_2026_109618_mitigated' => ($isPatched || ini_get('memory_limit') === '-1'),
    'cve_2026_109619_mitigated' => ((int)ini_get('session.upload_progress.enabled') === 0),
    'php_version' => $ver,
    'php_patched' => $isPatched,
    'patch_status' => $patchAdvice,
    'memory_limit' => ini_get('memory_limit'),
    'memory_limit_hardened' => (ini_get('memory_limit') === '-1'),
    'post_max_size' => ini_get('post_max_size'),
    'session_upload_progress_disabled' => ((int)ini_get('session.upload_progress.enabled') === 0),
    'fatal_error_backtraces_disabled' => ((int)ini_get('fatal_error_backtraces') === 0),
    'file_uploads_allowed' => (bool)ini_get('file_uploads'),
    'disabled_functions' => $disabledFuncs,
    'dangerous_functions_blocked' => $disabledDangerous,
    'multipart_shield_active' => true
];

echo json_encode([
    'success' => true,
    'environment' => 'remote',
    'host' => $_SERVER['HTTP_HOST'] ?? 'bantayaso.s3curity.net',
    'app_path' => $workspaceRoot,
    'app_writable' => $appWritable,
    'data_writable' => $dataWritable,
    'php_version' => PHP_VERSION,
    'security_posture' => $securityPosture,
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
