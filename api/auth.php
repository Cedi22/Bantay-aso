<?php
/**
 * Bantay Aso — Authentication API Endpoint
 * Handles login, logout, current user session status, and CSRF token distribution.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'me';

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Auth::jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $username = (string)($input['username'] ?? '');
        $password = (string)($input['password'] ?? '');

        $clientIp = Auth::getClientIp();
        if (!Auth::checkRateLimit($clientIp, 5, 15)) {
            http_response_code(429);
            header('Retry-After: 900');
            Auth::jsonResponse([
                'success' => false,
                'error' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'
            ], 429);
            exit;
        }

        $result = Auth::login($username, $password);
        if ($result['success']) {
            Auth::clearAttempts($clientIp);
            Auth::jsonResponse([
                'success' => true,
                'user' => $result['user'],
                'csrf_token' => $result['csrf_token'],
                'password_change_required' => !empty($result['user']['must_change_password']),
                'redirect' => !empty($result['user']['must_change_password']) ? 'change-password.php' : 'index.php',
                'message' => 'Login successful.',
            ]);
        } else {
            Auth::recordFailedAttempt($clientIp, $username);
            Auth::jsonResponse(['success' => false, 'error' => $result['error']], 401);
        }
        break;

    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Auth::jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
        }
        Auth::logout();
        Auth::jsonResponse([
            'success' => true,
            'message' => 'Logged out successfully.',
            'csrf_token' => Auth::generateCsrfToken(true),
        ]);
        break;

    case 'me':
    default:
        $user = Auth::getCurrentUser();
        $csrfToken = Auth::generateCsrfToken();
        Auth::jsonResponse([
            'success' => true,
            'authenticated' => ($user !== null),
            'password_change_required' => (bool)($user['must_change_password'] ?? false),
            'user' => $user,
            'csrf_token' => $csrfToken,
        ]);
        break;
}
