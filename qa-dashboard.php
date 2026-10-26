<?php
/**
 * Bantay Aso — QA Dashboard HTML Wrapper
 * Protected entry point: Enforces strict session authentication.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_middleware.php';

$currentUser = Auth::getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}
if (!empty($currentUser['must_change_password'])) {
    header('Location: change-password.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$dashboardFile = __DIR__ . '/qa-dashboard.html';

if (file_exists($dashboardFile)) {
    readfile($dashboardFile);
} else {
    http_response_code(404);
    echo "Dashboard not found.";
}
