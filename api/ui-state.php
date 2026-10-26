<?php
/**
 * Bantay Aso — UI State API Endpoint
 * Handles GET and POST for config/ui_state.json
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// Entire ui-state endpoint strictly requires authentication
Auth::requireLogin();

$configFile = __DIR__ . '/../config/ui_state.json';

function getUiState(string $file): array {
    $default = [
        'header_title' => 'Bantay Aso',
        'last_sync_utc' => null,
        'last_sync_origin' => 'remote'
    ];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return array_merge($default, $data);
        }
    }
    return $default;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $state = getUiState($configFile);
    echo json_encode(array_merge(['success' => true], $state), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $state = getUiState($configFile);

    if (isset($input['header_title'])) {
        $state['header_title'] = trim((string)$input['header_title']);
    }
    $state['last_sync_utc'] = gmdate('Y-m-d\TH:i:s\Z');
    $state['last_sync_origin'] = $input['last_sync_origin'] ?? 'remote';

    if (!is_dir(dirname($configFile))) {
        @mkdir(dirname($configFile), 0775, true);
    }
    file_put_contents($configFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    echo json_encode(array_merge(['success' => true], $state), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
