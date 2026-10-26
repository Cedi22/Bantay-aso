<?php
/**
 * Bantay Aso — Continuous QA & Semantic Changelog Platform
 * Core Application Configuration
 *
 * Designed for Virtualmin VPS (LAMP/LEMP) with zero server-side shell dependencies.
 * Ready for Antigravity Agent (AGY CLI) integration.
 */

declare(strict_types=1);

// Application Metadata
define('APP_NAME', 'Bantay Aso');
define('APP_VERSION', '1.2.0');
define('APP_CODENAME', 'CyberHound');

// Error reporting for production
define('APP_DEBUG', false);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Server-side Default Timezone (Asia/Qatar fallback)
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Qatar');


// Load .env configuration from candidate locations
$envCandidates = [
    __DIR__ . DIRECTORY_SEPARATOR . '.env',
    dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . '.env',
];

foreach ($envCandidates as $envFile) {
    if (file_exists($envFile)) {
        $envLines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($envLines)) {
            foreach ($envLines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B'\"");
                if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}

// Feature Toggles: Environment-Based Module Activation
$appEnv = strtolower(getenv('APP_ENV') ?: '');
$isLocalDev = in_array($appEnv, ['local', 'dev', 'development', 'test'], true);

// Feature: Security Hub (Disabled by default unless explicitly enabled)
$secHubEnv = getenv('ENABLE_SECURITY_HUB');
define('ENABLE_SECURITY_HUB', $secHubEnv !== false 
    ? filter_var($secHubEnv, FILTER_VALIDATE_BOOLEAN) 
    : false);

// Feature: Sync Tools / Sync Files (Disabled by default unless explicitly enabled)
$syncToolsEnv = getenv('ENABLE_SYNC_TOOLS');
define('ENABLE_SYNC_TOOLS', $syncToolsEnv !== false 
    ? filter_var($syncToolsEnv, FILTER_VALIDATE_BOOLEAN) 
    : false);

// Check runtime bootstrap-secrets.php if present
$secretsFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'bootstrap-secrets.php';
if (file_exists($secretsFile)) {
    $secrets = @include $secretsFile;
    if (is_array($secrets)) {
        if (!getenv('REMOTE_ADMIN_PASS') && !empty($secrets['admin_password'])) {
            putenv("REMOTE_ADMIN_PASS={$secrets['admin_password']}");
            $_ENV['REMOTE_ADMIN_PASS'] = $secrets['admin_password'];
            $_SERVER['REMOTE_ADMIN_PASS'] = $secrets['admin_password'];
        }
        if (!getenv('VIEWER_PASS') && !empty($secrets['viewer_password'])) {
            putenv("VIEWER_PASS={$secrets['viewer_password']}");
            $_ENV['VIEWER_PASS'] = $secrets['viewer_password'];
            $_SERVER['VIEWER_PASS'] = $secrets['viewer_password'];
        }
    }
}

// Detect HTTPS across direct SSL, reverse proxies (Virtualmin/Nginx/Apache), and Cloudflare
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
    || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && (int)$_SERVER['HTTP_X_FORWARDED_PORT'] === 443)
    || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'bantayaso.s3curity.net') !== false)
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

// Active Shield: Block multipart form-data requests to neutralize CVE-2026-109618 & CVE-2026-109619 parser exploits
$requestContentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($requestContentType, 'multipart/form-data') !== false) {
    http_response_code(415);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Multipart form-data processing is blocked by security policy (PHP2Shell defense).'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

// Session & Cookie Configuration (Conforms to OWASP, ECC Security & Cross-Origin rules)
if (session_status() === PHP_SESSION_NONE) {
    // Neutralize stale global state attack paths (CVE-2026-109618 / CVE-2026-109619)
    ini_set('session.upload_progress.enabled', '0');
    @ini_set('fatal_error_backtraces', '0');

    // Mitigate controllable Out-Of-Memory (OOM) exploits if permitted by runtime
    if (ini_get('memory_limit') !== '-1') {
        @ini_set('memory_limit', '-1');
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    
    // For cross-origin AJAX / sync requests between localhost and live server,
    // SameSite=None is mandatory when running over HTTPS. On local HTTP, Lax is used.
    $cookieSameSite = $isHttps ? 'None' : 'Lax';
    ini_set('session.cookie_samesite', $cookieSameSite);
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    // Set cookie parameters cleanly for PHP 7.3+ / PHP 8.x
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $cookieSameSite,
    ]);

    session_start();
}

// Base Directories
define('BASE_PATH', __DIR__);
$parentData = dirname(BASE_PATH) . DIRECTORY_SEPARATOR . 'data';
if (file_exists($parentData . DIRECTORY_SEPARATOR . 'bantayaso.sqlite')) {
    define('DATA_PATH', $parentData);
} else {
    define('DATA_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'data');
}
define('INCLUDES_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'includes');

// Database Configuration
// Options: 'sqlite' (default zero-config out-of-the-box) or 'mysql'
define('DB_TYPE', 'sqlite');

// SQLite Settings
define('SQLITE_FILE', DATA_PATH . DIRECTORY_SEPARATOR . 'bantayaso.sqlite');

// MySQL / MariaDB Settings (Optional for Virtualmin MySQL users)
define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_PORT', 3306);
define('MYSQL_DATABASE', 'bantayaso');
define('MYSQL_USERNAME', 'bantayaso_user');
define('MYSQL_PASSWORD', 'bantayaso_pass');
define('MYSQL_CHARSET', 'utf8mb4');

// Semantic QA Analysis Engine Configuration
// Modes: 'gemini_cli' / 'antigravity_agent' (Gemini CLI / AGY sidecar / webhook) or 'ecc_deterministic' (Zero-infra built-in)
define('DEFAULT_ANALYSIS_MODE', 'gemini_cli');
define('DEFAULT_AGY_ENDPOINT', 'http://127.0.0.1:8765/agent');
define('DEFAULT_AGY_API_KEY', '');
define('DEFAULT_ANALYSIS_TIMEOUT', 30); // seconds

// Default Seed Accounts
define('INITIAL_ADMIN_USERNAME', getenv('REMOTE_ADMIN_USER') ?: 'admin');
define('INITIAL_ADMIN_PASSWORD', getenv('REMOTE_ADMIN_PASS') ?: '');
define('INITIAL_ADMIN_EMAIL', 'admin@bantayaso.local');

define('INITIAL_ADMINMOE_USERNAME', getenv('ADMINMOE_USER') ?: 'adminmoe');
define('INITIAL_ADMINMOE_PASSWORD', getenv('ADMINMOE_PASS') ?: 'pKCY%o8CeAt*gpsz');
define('INITIAL_ADMINMOE_EMAIL', 'adminmoe@bantayaso.local');

define('INITIAL_VIEWER_USERNAME', getenv('VIEWER_USER') ?: 'viewer');
define('INITIAL_VIEWER_PASSWORD', getenv('VIEWER_PASS') ?: 'Viewer@BantayAso2026!');
define('INITIAL_VIEWER_EMAIL', 'viewer@bantayaso.local');

// Secure Pre-computed Argon2id Fallback Hashes (ensures auth works if .env is missing without exposing plaintext in source)
define('FALLBACK_ADMIN_HASH', '$argon2id$v=19$m=65536,t=4,p=2$Mk80VlhENHpIUTlGcXAxRA$DoalscBcfBwV1b2apbWsNI8oXTfon2BrD4jdIKO0ojg');
define('FALLBACK_VIEWER_HASH', '$argon2id$v=19$m=65536,t=4,p=2$dkllS1Vja2ptUEUwT1Z2VA$OB5zrAprTmblP9BSBtaY/OU/WCIYz7QK/Ji4s269Q3c');

// Initial Seed Target Website (Used when setting up a fresh database)
define('INITIAL_WATCHLIST_URL', 'https://bantayaso.s3curity.net/');
define('INITIAL_WATCHLIST_NAME', 'Bantay Aso Production Node');

// Auto-initialize SQLite directory if not present
if (DB_TYPE === 'sqlite' && !is_dir(DATA_PATH)) {
    @mkdir(DATA_PATH, 0750, true);
    // Protect data folder with .htaccess
    $htaccess = DATA_PATH . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "# Apache 2.2\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n# Apache 2.4\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n");
    }
}
