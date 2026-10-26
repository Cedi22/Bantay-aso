<?php
/**
 * Bantay Aso — Admin User Management API
 * Provides user listing, creation, and deletion for Administrators.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

// Entire endpoint requires Admin privileges
$admin = Auth::requireAdmin();
$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? $input['action'] ?? '';

// GET: List all registered users
if ($method === 'GET') {
    $stmt = $pdo->query('
        SELECT id, username, email, role, created_at, last_login_at 
        FROM users 
        ORDER BY created_at ASC
    ');
    $users = $stmt->fetchAll();
    Auth::jsonResponse(['success' => true, 'users' => $users]);
}

// POST: Add or delete user (requires CSRF token)
Auth::requireCsrf();

if ($method === 'POST') {
    switch ($action) {
        case 'create':
            $username = trim((string)($input['username'] ?? ''));
            $email = trim((string)($input['email'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $role = (string)($input['role'] ?? 'viewer');

            if ($username === '' || $password === '' || $email === '') {
                Auth::jsonResponse(['success' => false, 'error' => 'Username, email, and password are required.'], 400);
            }

            if (!in_array($role, ['admin', 'viewer'], true)) {
                $role = 'viewer';
            }

            if (strlen($password) < 8) {
                Auth::jsonResponse(['success' => false, 'error' => 'Password must be at least 8 characters.'], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Auth::jsonResponse(['success' => false, 'error' => 'Invalid email address.'], 400);
            }

            // Check if username already exists
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $check->execute([$username]);
            if ($check->fetch()) {
                Auth::jsonResponse(['success' => false, 'error' => 'Username already exists.'], 409);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');

            $ins = $pdo->prepare('
                INSERT INTO users (username, password_hash, email, role, created_at)
                VALUES (?, ?, ?, ?, ?)
            ');
            $ins->execute([$username, $passwordHash, $email, $role, $now]);
            $newUserId = (int)$pdo->lastInsertId();

            Auth::jsonResponse([
                'success' => true,
                'message' => "User '{$username}' created successfully.",
                'user_id' => $newUserId,
            ], 201);
            break;

        case 'delete':
            $targetUserId = (int)($input['user_id'] ?? 0);
            if ($targetUserId <= 0) {
                Auth::jsonResponse(['success' => false, 'error' => 'Valid user_id required.'], 400);
            }

            // Prevent deleting own account
            if ($targetUserId === $admin['id']) {
                Auth::jsonResponse(['success' => false, 'error' => 'You cannot delete your own active administrator account.'], 403);
            }

            $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $del->execute([$targetUserId]);

            Auth::jsonResponse([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);
            break;

        default:
            Auth::jsonResponse(['success' => false, 'error' => 'Invalid action.'], 400);
    }
}
