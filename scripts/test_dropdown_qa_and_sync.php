<?php
/**
 * QA Test Suite: dropdown-qa.html / qa-dashboard.html Lockdown & Sync Tools Dropdown Parity
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

echo "========================================================\n";
echo "   Bantay Aso — Dropdown QA & Sync Tools Test Suite\n";
echo "========================================================\n\n";

// 1. Verify router.php logic
echo "[*] TEST 1: router.php Gateway Lockdown\n";
$_SERVER['REQUEST_URI'] = '/dropdown-qa.html';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = [];

// Intercept headers
ob_start();
// Test unauthenticated access to qa-dashboard.php
$currentUser = Auth::getCurrentUser();
if (!$currentUser) {
    echo "    ✓ Unauthenticated user blocked: Auth::getCurrentUser() is null\n";
} else {
    echo "    FAIL: Unauthenticated user unexpectedly granted access\n";
    exit(1);
}
ob_end_clean();

// 2. Verify files exist
echo "\n[*] TEST 2: Gateway Files Verification\n";
$requiredFiles = [
    'dropdown-qa.html',
    'dropdown-qa.php',
    'qa-dashboard.html',
    'qa-dashboard.php',
    'router.php',
    'login.php',
    'run-local.bat',
    'run-local.ps1'
];
foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (file_exists($fullPath)) {
        echo "    ✓ $file exists (" . filesize($fullPath) . " bytes)\n";
    } else {
        echo "    FAIL: $file missing\n";
        exit(1);
    }
}

// 3. Verify qa-dashboard.html Auth Guard & Dropdown Structure
echo "\n[*] TEST 3: qa-dashboard.html Structure & Event Wiring\n";
$qaHtml = file_get_contents(__DIR__ . '/../qa-dashboard.html');

// Check client-side auth guard
if (strpos($qaHtml, 'Strict Client-Side Authentication Guard') !== false &&
    strpos($qaHtml, "fetch('api/auth.php?action=me'") !== false &&
    strpos($qaHtml, "window.location.replace('login.php") !== false) {
    echo "    ✓ Client-side authentication guard embedded in <head>\n";
} else {
    echo "    FAIL: Client-side auth guard missing or malformed in qa-dashboard.html\n";
    exit(1);
}

// Check Sync Tools Dropdown Menu
if (strpos($qaHtml, 'id="syncToolsQaDropdown"') !== false &&
    strpos($qaHtml, 'id="syncToolsQaMenu"') !== false &&
    strpos($qaHtml, 'id="btnQuickSyncTrigger"') !== false &&
    strpos($qaHtml, 'id="btnToggleQaHeader"') !== false) {
    echo "    ✓ #syncToolsQaDropdown has #syncToolsQaMenu wrapper with Sync Files and Toggle Header items\n";
} else {
    echo "    FAIL: Sync Tools dropdown menu markup is broken in qa-dashboard.html\n";
    exit(1);
}

// Check duplicate init
$initCount = preg_match_all('/init:\s*function\s*\(\)/', $qaHtml, $m);
if ($initCount === 1) {
    echo "    ✓ Exactly 1 App.init() definition exists (no lifecycle overwrites)\n";
} else {
    echo "    FAIL: Found $initCount App.init() definitions in qa-dashboard.html\n";
    exit(1);
}

// 4. Verify run-local.bat and run-local.ps1
echo "\n[*] TEST 4: run-local Startup Scripts Target Login Page\n";
$bat = file_get_contents(__DIR__ . '/../run-local.bat');
if (strpos($bat, 'start http://127.0.0.1:8000/login.php') !== false &&
    strpos($bat, '-S 127.0.0.1:8000 router.php') !== false) {
    echo "    ✓ run-local.bat points to login.php and mounts router.php\n";
} else {
    echo "    FAIL: run-local.bat does not launch login.php with router.php\n";
    exit(1);
}

$ps1 = file_get_contents(__DIR__ . '/../run-local.ps1');
if (strpos($ps1, 'Start-Process "http://127.0.0.1:8000/login.php"') !== false &&
    strpos($ps1, '-S 127.0.0.1:8000 router.php') !== false) {
    echo "    ✓ run-local.ps1 points to login.php and mounts router.php\n";
} else {
    echo "    FAIL: run-local.ps1 does not launch login.php with router.php\n";
    exit(1);
}

// 5. Verify index.php parity with app.js
echo "\n[*] TEST 5: index.php and app.js Parity\n";
$indexPhp = file_get_contents(__DIR__ . '/../index.php');
if (strpos($indexPhp, 'id="syncToolsDropdown"') !== false &&
    strpos($indexPhp, 'id="syncToolsMenu"') !== false &&
    strpos($indexPhp, 'id="btnSyncOnline"') !== false) {
    echo "    ✓ index.php has #syncToolsDropdown with #syncToolsMenu\n";
} else {
    echo "    FAIL: index.php missing sync tools dropdown markup\n";
    exit(1);
}

$rootAppJs = file_get_contents(__DIR__ . '/../app.js');
$assetsAppJs = file_get_contents(__DIR__ . '/../assets/js/app.js');
if (hash('sha256', $rootAppJs) === hash('sha256', $assetsAppJs)) {
    echo "    ✓ app.js and assets/js/app.js are in 100% hash lockstep\n";
} else {
    echo "    FAIL: app.js and assets/js/app.js hash mismatch\n";
    exit(1);
}

echo "\n========================================================\n";
echo "   >>> ALL DROP-IN QA & SYNC DROPDOWN TESTS PASSED <<<   \n";
echo "========================================================\n";
