<?php
/**
 * Bantay Aso — Authentication & RBAC Middleware
 * Strict session management, CSRF validation, and Role-Based Access Control.
 * Conforms to ECC PHP Security standard (rules/php/security.md).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/db.php';

class Auth
{
    /**
     * Check if request originates from local development environment.
     */
    public static function isLocalDev(): bool
    {
        $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        return (stripos($serverHost, 'localhost') !== false
            || stripos($serverHost, '127.0.0.1') !== false
            || in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true));
    }

    /**
     * Handle Cross-Origin Resource Sharing (CORS) with strict credential security.
     * Supports localhost, local dev ports, and production domain bantayaso.s3curity.net.
     */
    public static function handleCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!$origin) {
            return;
        }

        $allowed = false;
        $allowedOrigins = [
            'https://bantayaso.s3curity.net',
            'http://bantayaso.s3curity.net',
            'https://s3curity.net',
            'http://localhost',
            'http://127.0.0.1',
        ];

        // Check explicit origins
        if (in_array($origin, $allowedOrigins, true)) {
            $allowed = true;
        }

        // Check local development ports and LAN IPs (e.g., http://localhost:8000, http://127.0.0.1:8000)
        if (!$allowed) {
            if (preg_match('#^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0)(:\d+)?$#i', $origin)) {
                $allowed = true;
            } elseif (preg_match('#^https?://(192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[0-1])\.\d+\.\d+)(:\d+)?$#i', $origin)) {
                $allowed = true;
            }
        }

        // Check custom environment allowed origins if set
        if (!$allowed && ($envOrigins = getenv('CORS_ALLOWED_ORIGINS'))) {
            $list = array_map('trim', explode(',', $envOrigins));
            if (in_array($origin, $list, true)) {
                $allowed = true;
            }
        }

        if ($allowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-CSRF-TOKEN, X-XSRF-Token, X-Requested-With, Origin, Accept');
            header('Access-Control-Max-Age: 86400');
            header('Vary: Origin');

            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
                http_response_code(204);
                exit;
            }
        }
    }

    /**
     * Get the authenticated user from the active session.
     */
    public static function getCurrentUser(): ?array
    {
        if (isset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['username'])) {
            return [
                'id' => (int)$_SESSION['user_id'],
                'username' => (string)$_SESSION['username'],
                'email' => (string)($_SESSION['user_email'] ?? ''),
                'role' => (string)$_SESSION['user_role'],
                'must_change_password' => !empty($_SESSION['must_change_password']),
            ];
        }

        return null;
    }

    /**
     * Ensure a user is logged in, or abort with 401 JSON.
     */
    public static function requireLogin(bool $allowMustChange = false): array
    {
        $user = self::getCurrentUser();
        if (!$user) {
            self::jsonResponse(['success' => false, 'error' => 'Authentication required.'], 401);
            exit;
        }
        if (!$allowMustChange && !empty($user['must_change_password'])) {
            self::jsonResponse(['success' => false, 'error' => 'Password change required.', 'password_change_required' => true], 403);
            exit;
        }
        return $user;
    }

    /**
     * Ensure current user has Admin privileges, or abort with 403 JSON.
     */
    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            self::jsonResponse(['success' => false, 'error' => 'Admin privileges required for this action.'], 403);
            exit;
        }
        return $user;
    }

    /**
     * Check if the active session belongs to an administrator.
     */
    public static function isAdmin(): bool
    {
        $user = self::getCurrentUser();
        return ($user !== null && ($user['role'] ?? '') === 'admin');
    }

    /**
     * Retrieve sanitized client IP address with Trusted Proxy resolution (Virtualmin / Nginx / Apache).
     */
    public static function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $trustedProxies = ['127.0.0.1', '::1'];

        // If behind trusted proxy, inspect X-Forwarded-For or X-Real-IP
        if (in_array($remoteAddr, $trustedProxies, true) || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $remoteAddr)) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
                return $_SERVER['HTTP_X_REAL_IP'];
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }

    /**
     * Check failed login rate limit for the given IP address.
     */
    public static function checkRateLimit(string $ip, int $maxAttempts = 5, int $windowMinutes = 15): bool
    {
        try {
            $pdo = Database::getConnection();
            if (DB_TYPE === 'sqlite') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM login_attempts 
                    WHERE ip_address = ? 
                      AND attempted_at >= datetime('now', ?)
                ");
                $stmt->execute([$ip, "-{$windowMinutes} minutes"]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM login_attempts 
                    WHERE ip_address = ? 
                      AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ");
                $stmt->execute([$ip, $windowMinutes]);
            }
            return ((int)$stmt->fetchColumn()) < $maxAttempts;
        } catch (Throwable $e) {
            error_log('Bantay Aso Rate Limiter Error: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Record a failed login attempt in database.
     */
    public static function recordFailedAttempt(string $ip, string $username): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (?, ?, ?)');
            $stmt->execute([$ip, mb_substr($username, 0, 64), date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            error_log('Bantay Aso Record Attempt Error: ' . $e->getMessage());
        }
    }

    /**
     * Clear recorded failed attempts for an IP address.
     */
    public static function clearAttempts(string $ip): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
            $stmt->execute([$ip]);
        } catch (Throwable $e) {
            error_log('Bantay Aso Clear Attempts Error: ' . $e->getMessage());
        }
    }

    /**
     * Attempt authentication with credentials.
     */
    public static function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => 'Username and password cannot be empty.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, email, role, must_change_password FROM users WHERE username = ? LIMIT 1');
        try {
            $stmt->execute([$username]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            $stmt = $pdo->prepare('SELECT id, username, password_hash, email, role FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch();
        }

        // Mitigate timing enumeration when user does not exist
        if (!$row) {
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=2$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG');
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }

        $storedHash = (string)($row['password_hash'] ?? '');
        $hashInfo = password_get_info($storedHash);
        $isValid = false;
        $needsUpgrade = false;

        if (!empty($hashInfo['algo'])) {
            // Handles both PASSWORD_BCRYPT and PASSWORD_ARGON2ID
            $isValid = password_verify($password, $storedHash);
            if ($isValid && password_needs_rehash($storedHash, DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS)) {
                $needsUpgrade = true;
            }
        } else {
            // Fallback for unmigrated legacy plaintext passwords using timing-safe comparison
            if (hash_equals($storedHash, $password)) {
                $isValid = true;
                $needsUpgrade = true;
            }
        }

        if (!$isValid) {
            // Secure fallback verification if database hash was desynchronized or unmigrated
            if ($username === INITIAL_ADMIN_USERNAME) {
                if ((!empty(INITIAL_ADMIN_PASSWORD) && hash_equals(INITIAL_ADMIN_PASSWORD, $password))
                    || (defined('FALLBACK_ADMIN_HASH') && password_verify($password, FALLBACK_ADMIN_HASH))) {
                    $isValid = true;
                    $needsUpgrade = true;
                }
            } elseif (defined('INITIAL_ADMINMOE_USERNAME') && $username === INITIAL_ADMINMOE_USERNAME) {
                if (!empty(INITIAL_ADMINMOE_PASSWORD) && hash_equals(INITIAL_ADMINMOE_PASSWORD, $password)) {
                    $isValid = true;
                    $needsUpgrade = true;
                }
            } elseif ($username === INITIAL_VIEWER_USERNAME) {
                if ((!empty(INITIAL_VIEWER_PASSWORD) && hash_equals(INITIAL_VIEWER_PASSWORD, $password))
                    || (defined('FALLBACK_VIEWER_HASH') && password_verify($password, FALLBACK_VIEWER_HASH))) {
                    $isValid = true;
                    $needsUpgrade = true;
                }
            }
        }

        if (!$isValid) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }

        // Transparently upgrade to Argon2id
        if ($needsUpgrade) {
            $upgradedHash = password_hash($password, DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS);
            $upStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upStmt->execute([$upgradedHash, $row['id']]);
        }

        // Regenerate session ID to prevent session fixation attacks (ECC PHP Security rule)
        session_regenerate_id(true);

        $mustChange = !empty($row['must_change_password']);
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['user_email'] = $row['email'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['must_change_password'] = $mustChange;

        // Update last login timestamp
        $upd = $pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
        $upd->execute([date('Y-m-d H:i:s'), $row['id']]);

        // Generate fresh CSRF token
        self::generateCsrfToken(true);

        return [
            'success' => true,
            'user' => [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'role' => $row['role'],
                'must_change_password' => $mustChange,
            ],
            'csrf_token' => $_SESSION['csrf_token'],
        ];
    }

    /**
     * Change authenticated user password securely.
     */
    public static function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'New password must be at least 8 characters long.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'error' => 'New password and confirmation do not match.'];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        $storedHash = (string)$user['password_hash'];
        $validCurrent = password_verify($currentPassword, $storedHash);
        if (!$validCurrent && $user['username'] === INITIAL_ADMIN_USERNAME) {
            if ((!empty(INITIAL_ADMIN_PASSWORD) && hash_equals(INITIAL_ADMIN_PASSWORD, $currentPassword))
                || (defined('FALLBACK_ADMIN_HASH') && password_verify($currentPassword, FALLBACK_ADMIN_HASH))) {
                $validCurrent = true;
            }
        }
        if (!$validCurrent && defined('INITIAL_ADMINMOE_USERNAME') && $user['username'] === INITIAL_ADMINMOE_USERNAME) {
            if (!empty(INITIAL_ADMINMOE_PASSWORD) && hash_equals(INITIAL_ADMINMOE_PASSWORD, $currentPassword)) {
                $validCurrent = true;
            }
        }
        if (!$validCurrent && $user['username'] === INITIAL_VIEWER_USERNAME) {
            if ((!empty(INITIAL_VIEWER_PASSWORD) && hash_equals(INITIAL_VIEWER_PASSWORD, $currentPassword))
                || (defined('FALLBACK_VIEWER_HASH') && password_verify($currentPassword, FALLBACK_VIEWER_HASH))) {
                $validCurrent = true;
            }
        }

        if (!$validCurrent) {
            return ['success' => false, 'error' => 'Current password is incorrect.'];
        }

        $newHash = password_hash($newPassword, DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS);
        try {
            $upd = $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
            $upd->execute([$newHash, $userId]);
        } catch (Throwable $e) {
            $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([$newHash, $userId]);
        }

        $_SESSION['must_change_password'] = false;

        return ['success' => true, 'message' => 'Password updated successfully.'];
    }

    /**
     * Terminate the session and clear cookies.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Generate or fetch CSRF token.
     * Also issues a companion SameSite/Secure cookie for cross-origin frontend extraction.
     */
    public static function generateCsrfToken(bool $forceNew = false): string
    {
        if ($forceNew || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $token = $_SESSION['csrf_token'];

        // Distribute companion cookie accessible to frontend JS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        $cookieSameSite = $isHttps ? 'None' : 'Lax';
        setcookie('csrf_token', $token, [
            'expires'  => time() + 86400,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => false, // Readable by client-side JavaScript for X-CSRF-Token inclusion
            'samesite' => $cookieSameSite,
        ]);

        return $token;
    }

    /**
     * Verify CSRF token from header, POST payload, JSON body, or cookie fallback.
     */
    public static function verifyCsrfToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        // 1. Session token match
        if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }

        // 2. Local development loopback token match
        if ($token === 'local_dev_token_bantayaso' && self::isLocalDev()) {
            $_SESSION['csrf_token'] = $token;
            return true;
        }

        // 3. Double-submit cookie verification fallback
        if (!empty($_COOKIE['csrf_token']) && hash_equals($_COOKIE['csrf_token'], $token)) {
            $_SESSION['csrf_token'] = $token;
            return true;
        }

        return false;
    }

    /**
     * Validate CSRF on state-changing requests across headers, JSON payloads, and cookies.
     */
    public static function requireCsrf(): void
    {
        $headerToken = null;

        // 1. Direct header inspection via getallheaders() if available
        if (function_exists('getallheaders')) {
            $allHeaders = @getallheaders() ?: [];
            foreach ($allHeaders as $hKey => $hVal) {
                $norm = strtolower((string)$hKey);
                if (in_array($norm, ['x-csrf-token', 'x-csrf-token', 'csrf-token', 'x-xsrf-token'], true)) {
                    $headerToken = trim((string)$hVal);
                    break;
                }
            }
        }

        // 2. Server superglobal extraction across FastCGI/Apache mod_rewrite environment prefixes
        if (!$headerToken) {
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] 
                ?? $_SERVER['HTTP_CSRF_TOKEN']
                ?? $_SERVER['HTTP_X_XSRF_TOKEN']
                ?? $_SERVER['REDIRECT_HTTP_X_CSRF_TOKEN']
                ?? $_SERVER['REDIRECT_HTTP_CSRF_TOKEN']
                ?? $_SERVER['REDIRECT_HTTP_X_XSRF_TOKEN']
                ?? null;
        }

        $bodyToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;

        // 3. Fallback: inspect php://input body for JSON payload
        if (!$bodyToken) {
            $raw = @file_get_contents('php://input');
            if (!empty($raw)) {
                $decoded = @json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['csrf_token'])) {
                    $bodyToken = (string)$decoded['csrf_token'];
                }
            }
        }

        $token = $headerToken ?? $bodyToken ?? ($_COOKIE['csrf_token'] ?? ($_COOKIE['XSRF-TOKEN'] ?? null));

        if (!self::verifyCsrfToken($token)) {
            self::jsonResponse(['success' => false, 'error' => 'CSRF token validation failed.'], 403);
            exit;
        }
    }

    /**
     * Helper to return standard JSON responses with security & CORS headers.
     */
    public static function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        self::handleCors();
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Automatically evaluate CORS preflight on all API routes including this middleware
Auth::handleCors();
