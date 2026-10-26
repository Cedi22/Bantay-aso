<?php
/**
 * Bantay Aso — Settings & Engine Configuration API
 * Allows Administrators to configure Semantic QA engines (ECC Deterministic, Antigravity Agent, or Custom LLM).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/llm_client.php';

header('Content-Type: application/json; charset=utf-8');

// Require Admin authentication for all settings operations
Auth::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? $input['action'] ?? '';

if ($method === 'GET') {
    $settings = LlmClient::getSettings();

    Auth::jsonResponse([
        'success' => true,
        'settings' => [
            'analysis_mode' => $settings['mode'],
            'agy_endpoint' => $settings['agy_endpoint'],
            'agy_api_key' => $settings['agy_api_key'],
            'llm_timeout' => $settings['timeout'],
            'system_name' => $settings['system_name'],
            'app_version' => APP_VERSION,
            'db_type' => DB_TYPE,
        ],
    ]);
}

// POST actions require CSRF validation
Auth::requireCsrf();

if ($method === 'POST') {
    switch ($action) {
        case 'test_llm':
        case 'test_engine':
        case 'test':
            $testResult = LlmClient::testConnection();
            Auth::jsonResponse($testResult);
            break;

        case 'save':
            $mode = trim((string)($input['analysis_mode'] ?? 'gemini_cli'));
            if (!in_array($mode, ['gemini_cli', 'antigravity_agent', 'ecc_deterministic'], true)) {
                $mode = 'gemini_cli';
            }
            $agyEndpoint = trim((string)($input['agy_endpoint'] ?? ''));
            $agyApiKey = trim((string)($input['agy_api_key'] ?? ''));
            $timeout = max(5, min(240, (int)($input['llm_timeout'] ?? 30)));

            LlmClient::saveSettings([
                'analysis_mode' => $mode,
                'agy_endpoint' => $agyEndpoint,
                'agy_api_key' => $agyApiKey,
                'llm_timeout' => $timeout,
            ]);

            Auth::jsonResponse([
                'success' => true,
                'message' => 'Analysis engine settings saved successfully.',
            ]);
            break;

        default:
            Auth::jsonResponse(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)], 400);
    }
}
