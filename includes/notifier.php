<?php
/**
 * Bantay Aso — Autonomous Multi-Channel Webhook Notifier
 * Supports Discord, Slack, and Generic JSON Webhook alerting.
 * Zero external dependencies (native PHP cURL & PDO).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class Notifier
{
    /**
     * Get configured webhook URL from environment or settings table.
     */
    public static function getWebhookUrl(): string
    {
        $url = getenv('WATCHDOG_WEBHOOK_URL') ?: '';
        if ($url !== '') {
            return $url;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT value_text FROM settings WHERE key_name = ? LIMIT 1');
            $stmt->execute(['watchdog_webhook_url']);
            $row = $stmt->fetch();
            if ($row && !empty($row['value_text'])) {
                return trim((string)$row['value_text']);
            }
        } catch (Throwable $e) {
            error_log('Notifier getWebhookUrl error: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * Dispatch an alert if not already active/unresolved.
     *
     * @param array $target Watchlist target array
     * @param string $incidentType 'DOWNTIME' | 'SSL_EXPIRY' | 'DEFACEMENT'
     * @param string $severity 'WARNING' | 'CRITICAL'
     * @param string $message Detailed alert description
     * @return bool True if alert was sent or logged, false if suppressed as duplicate
     */
    public static function sendAlert(array $target, string $incidentType, string $severity, string $message): bool
    {
        $targetId = (int)($target['id'] ?? 0);
        if ($targetId <= 0) {
            return false;
        }

        $pdo = Database::getConnection();

        // 1. Deduplication: Check if unresolved incident of same type already exists
        $checkStmt = $pdo->prepare('
            SELECT id FROM incidents 
            WHERE watchlist_id = ? 
              AND incident_type = ? 
              AND resolved_at IS NULL 
            LIMIT 1
        ');
        $checkStmt->execute([$targetId, $incidentType]);
        if ($checkStmt->fetch()) {
            // Already active incident; suppress alert to avoid alert fatigue/spam
            return false;
        }

        // 2. Persist new incident
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare('
            INSERT INTO incidents (watchlist_id, incident_type, severity, details, created_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        $ins->execute([$targetId, $incidentType, $severity, $message, $now]);

        // 3. Dispatch to Webhook if configured
        $webhookUrl = self::getWebhookUrl();
        if ($webhookUrl === '') {
            return true; // Incident logged in database
        }

        return self::dispatchWebhook($webhookUrl, $target, $incidentType, $severity, $message);
    }

    /**
     * Resolve any open incident of given type and optionally dispatch recovery alert.
     */
    public static function resolveIncident(int $targetId, string $incidentType, string $details = 'Target recovered and verified healthy.'): void
    {
        $pdo = Database::getConnection();
        $now = date('Y-m-d H:i:s');

        // Check if an open incident exists
        $check = $pdo->prepare('
            SELECT id FROM incidents 
            WHERE watchlist_id = ? AND incident_type = ? AND resolved_at IS NULL
        ');
        $check->execute([$targetId, $incidentType]);
        $openIncidents = $check->fetchAll();

        if (empty($openIncidents)) {
            return;
        }

        $upd = $pdo->prepare('
            UPDATE incidents 
            SET resolved_at = ? 
            WHERE watchlist_id = ? AND incident_type = ? AND resolved_at IS NULL
        ');
        $upd->execute([$now, $targetId, $incidentType]);

        // Dispatch recovery notice if webhook is configured
        $webhookUrl = self::getWebhookUrl();
        if ($webhookUrl !== '') {
            $tStmt = $pdo->prepare('SELECT * FROM watchlist WHERE id = ? LIMIT 1');
            $tStmt->execute([$targetId]);
            $target = $tStmt->fetch() ?: ['id' => $targetId, 'name' => "Target #{$targetId}", 'url' => ''];
            self::dispatchWebhook($webhookUrl, $target, $incidentType, 'RECOVERED', "✓ RECOVERY: {$details}");
        }
    }

    /**
     * Format and deliver JSON payload (Discord, Slack, or Generic Webhook).
     */
    private static function dispatchWebhook(string $webhookUrl, array $target, string $type, string $severity, string $message): bool
    {
        $targetName = $target['name'] ?? 'Unknown Target';
        $targetUrl = $target['url'] ?? '';
        $isDiscord = str_contains($webhookUrl, 'discord.com') || str_contains($webhookUrl, 'discordapp.com');
        $isSlack = str_contains($webhookUrl, 'slack.com');

        $color = ($severity === 'CRITICAL') ? 0xff0055 : (($severity === 'WARNING') ? 0xffaa00 : 0x00ff88);

        if ($isDiscord) {
            $payload = [
                'username' => 'Bantay Aso Sentinel',
                'embeds' => [
                    [
                        'title' => "[{$severity}] {$type}: {$targetName}",
                        'url' => $targetUrl,
                        'description' => $message,
                        'color' => $color,
                        'fields' => [
                            ['name' => 'Target URL', 'value' => $targetUrl, 'inline' => true],
                            ['name' => 'Incident Type', 'value' => $type, 'inline' => true],
                            ['name' => 'Timestamp', 'value' => date('Y-m-d H:i:s T'), 'inline' => false],
                        ],
                        'footer' => ['text' => 'Bantay Aso Autonomous Watchdog'],
                    ],
                ],
            ];
        } elseif ($isSlack) {
            $payload = [
                'text' => "*[{$severity}] {$type} on {$targetName}*\n>{$message}\nURL: {$targetUrl}",
            ];
        } else {
            // Generic JSON Webhook
            $payload = [
                'event' => 'bantayaso.alert',
                'incident_type' => $type,
                'severity' => $severity,
                'target_id' => $target['id'] ?? null,
                'target_name' => $targetName,
                'target_url' => $targetUrl,
                'message' => $message,
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($res !== false && $code >= 200 && $code < 300);
    }
}
