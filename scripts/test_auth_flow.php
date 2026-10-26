<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Test 1: Login with adminmoe and initial temporary password
$loginResult = Auth::login('adminmoe', 'pKCY%o8CeAt*gpsz');
echo "Login success: " . ($loginResult['success'] ? 'YES' : 'NO') . "\n";
echo "must_change_password flag: " . ($loginResult['user']['must_change_password'] ? 'YES' : 'NO') . "\n";

// Test 2: Check getCurrentUser()
$currentUser = Auth::getCurrentUser();
echo "getCurrentUser() username: " . $currentUser['username'] . "\n";
echo "getCurrentUser() role: " . $currentUser['role'] . "\n";
echo "getCurrentUser() must_change: " . ($currentUser['must_change_password'] ? 'YES' : 'NO') . "\n";

// Test 3: Change password
$newPass = 'NewAdminMoeSecret2026!';
$changeResult = Auth::changePassword($currentUser['id'], 'pKCY%o8CeAt*gpsz', $newPass, $newPass);
echo "Change password result: " . ($changeResult['success'] ? 'SUCCESS' : 'FAILED: ' . ($changeResult['error'] ?? '')) . "\n";

// Test 4: Verify new password
$loginResult2 = Auth::login('adminmoe', $newPass);
echo "Login with new password: " . ($loginResult2['success'] ? 'SUCCESS' : 'FAILED') . "\n";
echo "must_change_password flag after change: " . ($loginResult2['user']['must_change_password'] ? 'YES' : 'NO') . "\n";

// Reset back to temporary password so user can test with it
$pdo = Database::getConnection();
$tempHash = password_hash('pKCY%o8CeAt*gpsz', DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS);
$pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE username = 'adminmoe'")->execute([$tempHash]);
echo "Reset adminmoe back to temporary password pKCY%o8CeAt*gpsz with must_change_password=1 for user testing.\n";
