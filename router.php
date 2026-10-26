<?php
/**
 * Bantay Aso — Built-in PHP Development Server Router
 * Intercepts unauthenticated direct requests to qa-dashboard.html / dropdown-qa.html
 * and routes them through strict authentication checks.
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Normalize /dropdown-qa and /qa-dashboard requests to protected qa-dashboard.php wrapper
if ($uri === '/dropdown-qa.html' || $uri === '/dropdown-qa.php' || $uri === '/dropdown-qa' ||
    $uri === '/qa-dashboard.html' || $uri === '/qa-dashboard.php' || $uri === '/qa-dashboard') {
    require __DIR__ . '/qa-dashboard.php';
    return true;
}

$filePath = __DIR__ . $uri;

// If request points directly to a real file
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    // If it is a PHP script, let PHP evaluate it
    if (str_ends_with($filePath, '.php')) {
        require $filePath;
        return true;
    }
    // Static assets (CSS, JS, images, fonts) served directly by PHP built-in server
    return false;
}

// Default root request routes to index.php (which enforces Auth::getCurrentUser())
require __DIR__ . '/index.php';
return true;
