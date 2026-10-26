<?php
/**
 * Bantay Aso — dropdown-qa.php alias to qa-dashboard.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_middleware.php';

$currentUser = Auth::getCurrentUser();
if (!$currentUser) {
    header('Location: login.php?redirect=qa-dashboard.php');
    exit;
}
if (!empty($currentUser['must_change_password'])) {
    header('Location: change-password.php');
    exit;
}

require __DIR__ . '/qa-dashboard.php';
