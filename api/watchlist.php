<?php
/**
 * Bantay Aso — Watchlist API Endpoint
 * Provides CRUD management for target websites and web apps.
 * Admins have full write/edit/delete access; standard Users have read-only access.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Entire watchlist endpoint strictly requires authentication
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Handle JSON input body
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? $input['action'] ?? '';

// GET: Read watchlist or single item (accessible by all authenticated users and viewers)
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare('
            SELECT w.*, 
                   COUNT(s.id) as snapshot_count,
                   MAX(s.created_at) as latest_snapshot_date
            FROM watchlist w
            LEFT JOIN snapshots s ON s.watchlist_id = w.id
            WHERE w.id = ?
            GROUP BY w.id
        ');
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if (!$item) {
            Auth::jsonResponse(['success' => false, 'error' => 'Watchlist item not found.'], 404);
        }

        // Fetch recent snapshots for this item
        $snapStmt = $pdo->prepare('
            SELECT id, status_code, response_time_ms, content_hash, page_title, created_at
            FROM snapshots
            WHERE watchlist_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ');
        $snapStmt->execute([$id]);
        $item['recent_snapshots'] = $snapStmt->fetchAll();

        Auth::jsonResponse(['success' => true, 'item' => $item]);
    } else {
        // List all watchlist items
        $stmt = $pdo->query('
            SELECT w.*, 
                   COUNT(s.id) as snapshot_count,
                   MAX(s.created_at) as latest_snapshot_date
            FROM watchlist w
            LEFT JOIN snapshots s ON s.watchlist_id = w.id
            GROUP BY w.id
            ORDER BY w.updated_at DESC
        ');
        $items = $stmt->fetchAll();
        Auth::jsonResponse(['success' => true, 'items' => $items]);
    }
}

// Write Operations: Require Admin and valid CSRF token
Auth::requireAdmin();
Auth::requireCsrf();

if ($method === 'POST') {
    switch ($action) {
        case 'create':
            $name = trim((string)($input['name'] ?? ''));
            $url = trim((string)($input['url'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $interval = max(5, (int)($input['check_interval_mins'] ?? 60));

            if ($name === '' || $url === '') {
                Auth::jsonResponse(['success' => false, 'error' => 'Name and URL are required.'], 400);
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Auth::jsonResponse(['success' => false, 'error' => 'Please provide a valid HTTP/HTTPS URL.'], 400);
            }

            // Check duplicate URL
            $dupStmt = $pdo->prepare('SELECT id FROM watchlist WHERE url = ? LIMIT 1');
            $dupStmt->execute([$url]);
            if ($dupStmt->fetch()) {
                Auth::jsonResponse(['success' => false, 'error' => 'A watchlist entry with this URL already exists.'], 409);
            }

            $now = date('Y-m-d H:i:s');
            $ins = $pdo->prepare('
                INSERT INTO watchlist (name, url, description, check_interval_mins, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, ?, ?)
            ');
            $ins->execute([$name, $url, $description, $interval, $now, $now]);
            $newId = (int)$pdo->lastInsertId();

            Auth::jsonResponse([
                'success' => true,
                'message' => 'Target added to Watchlist successfully.',
                'item_id' => $newId,
            ], 201);
            break;

        case 'update':
            $id = (int)($input['id'] ?? 0);
            $name = trim((string)($input['name'] ?? ''));
            $url = trim((string)($input['url'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
            $interval = max(5, (int)($input['check_interval_mins'] ?? 60));

            if ($id <= 0 || $name === '' || $url === '') {
                Auth::jsonResponse(['success' => false, 'error' => 'Target ID, name, and valid URL are required.'], 400);
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Auth::jsonResponse(['success' => false, 'error' => 'Invalid URL provided.'], 400);
            }

            $now = date('Y-m-d H:i:s');
            $upd = $pdo->prepare('
                UPDATE watchlist 
                SET name = ?, url = ?, description = ?, check_interval_mins = ?, is_active = ?, updated_at = ?
                WHERE id = ?
            ');
            $upd->execute([$name, $url, $description, $interval, $isActive, $now, $id]);

            Auth::jsonResponse(['success' => true, 'message' => 'Watchlist entry updated.']);
            break;

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                Auth::jsonResponse(['success' => false, 'error' => 'Valid Target ID required for deletion.'], 400);
            }

            $del = $pdo->prepare('DELETE FROM watchlist WHERE id = ?');
            $del->execute([$id]);

            Auth::jsonResponse(['success' => true, 'message' => 'Watchlist target deleted.']);
            break;

        default:
            Auth::jsonResponse(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)], 400);
    }
}
