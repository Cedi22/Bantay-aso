<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$pdo = Database::getConnection();

// Check users in table
$stmt = $pdo->query('SELECT id, username, email, role, must_change_password FROM users');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Current users in DB:\n";
print_r($users);

// Test password verify for adminmoe
$moe = $pdo->query("SELECT * FROM users WHERE username = 'adminmoe'")->fetch(PDO::FETCH_ASSOC);
if ($moe) {
    $verify = password_verify('pKCY%o8CeAt*gpsz', $moe['password_hash']);
    echo "adminmoe password verification: " . ($verify ? "SUCCESS" : "FAILED") . "\n";
    echo "adminmoe must_change_password: " . $moe['must_change_password'] . "\n";
} else {
    echo "adminmoe not found!\n";
}
