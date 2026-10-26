<?php
declare(strict_types=1);

// Test unauthenticated access by simulating request to api/state.php, api/watchlist.php, etc.

$endpoints = [
    'api/state.php',
    'api/watchlist.php',
    'api/diff.php',
    'api/qa.php',
    'api/hrms_qac.php',
    'api/sync.php',
    'api/ui-state.php',
];

echo "Testing API lock for unsigned users...\n";

foreach ($endpoints as $ep) {
    $cmd = 'C:\xampp\php\php.exe -d display_errors=0 ' . __DIR__ . '/../' . $ep;
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    $text = implode("\n", $output);
    $isLocked = (strpos($text, 'Authentication required') !== false || strpos($text, '"success":false') !== false);
    echo "  Endpoint $ep: " . ($isLocked ? "LOCKED (401 Auth Required)" : "WARNING: $text") . "\n";
}
