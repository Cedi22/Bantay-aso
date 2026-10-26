<?php
/**
 * Bantay Aso — User Profile Management API
 * Allows any authenticated user to view their profile, update email, and change password.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

$currentUser = Auth::requireLogin(true);
$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? $input['action'] ?? '';

if ($currentUser['must_change_password'] && !($method === 'POST' && $action === 'change_password')) {
    Auth::jsonResponse(['success' => false, 'error' => 'Password change required.', 'password_change_required' => true], 403);
}

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, username, email, role, created_at, last_login_at FROM users WHERE id = ?');
    $stmt->execute([$currentUser['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        Auth::jsonResponse(['success' => false, 'error' => 'User profile not found.'], 404);
    }

    Auth::jsonResponse(['success' => true, 'profile' => $user]);
}

// POST actions require CSRF validation
Auth::requireCsrf();

if ($method === 'POST') {
    switch ($action) {
        case 'update_email':
            $email = trim((string)($input['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Auth::jsonResponse(['success' => false, 'error' => 'Please provide a valid email address.'], 400);
            }

            $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
            $stmt->execute([$email, $currentUser['id']]);
            $_SESSION['user_email'] = $email;

            Auth::jsonResponse(['success' => true, 'message' => 'Profile email updated successfully.']);
            break;

        case 'change_password':
            $result = Auth::changePassword($currentUser['id'], (string)($input['current_password'] ?? ''), (string)($input['new_password'] ?? ''), (string)($input['confirm_password'] ?? ''));
            Auth::jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        default:
            Auth::jsonResponse(['success' => false, 'error' => 'Invalid action.'], 400);
    }
}
