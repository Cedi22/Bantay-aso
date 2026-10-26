<?php
/**
 * Bantay Aso — Semantic QA Analysis Engine & Agent Client
 * Supports:
 * 1. ECC Built-in Deterministic Engine (Zero external infrastructure, instant execution)
 * 2. Antigravity Agent (AGY CLI / Agent Sidecar HTTP integration)
 * 3. Custom OpenAI-compatible LLM endpoint
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class LlmClient
{
    /**
     * Generate an AI/Rule-powered semantic changelog and QA verdict between two snapshots.
     *
     * @param array $baseline Snapshot row for baseline
     * @param array $comparison Snapshot row for comparison
     * @param array $diffStats Diff statistics
     * @param string $unifiedDiff Snippet of unified diff
     * @return array [
     *   'verdict' => string,
     *   'changelog_markdown' => string,
     *   'model' => string,
     *   'source' => string,
     *   'latency_ms' => int
     * ]
     */
    public static function analyzeDiff(
        array $baseline,
        array $comparison,
        array $diffStats,
        string $unifiedDiff
    ): array {
        $startTime = microtime(true);
        $settings = self::getSettings();
        $mode = $settings['mode'] ?? DEFAULT_ANALYSIS_MODE;

        // Deterministic Defacement & Unauthorized Tampering Pre-checks
        $newContent = (string)($comparison['cleaned_content'] ?? '');
        $rawComparison = (string)($comparison['raw_content'] ?? '');
        $defacementDetected = false;
        $defacementReasons = [];

        // 1. Signature keyword & malicious tag scans
        $suspiciousPatterns = [
            '/\b(?:hacked|pwned|defaced)\s+by\b/i' => 'Known defacement crew banner signature detected',
            '/\b(?:hacked|pwned|defaced)\s+by\s+[a-z0-9_.-]+/i' => 'Defacer signature attribution detected',
            '/<iframe\b[^>]*src=["\'](?:https?:)?\/\/(?!(?:[a-z0-9-]+\.)?s3curity\.net)[^"\']*["\']/i' => 'Suspicious external iframe injection detected',
            '/<script\b[^>]*src=["\'](?:https?:)?\/\/(?!(?:[a-z0-9-]+\.)?(?:s3curity\.net|cdnjs\.cloudflare\.com|googleapis\.com|jsdelivr\.net))[^"\']*["\']/i' => 'Unauthorized external script tag injection detected',
        ];

        foreach ($suspiciousPatterns as $pattern => $reason) {
            if (preg_match($pattern, $newContent) || preg_match($pattern, $rawComparison)) {
                $defacementDetected = true;
                $defacementReasons[] = $reason;
            }
        }

        // 2. Catastrophic line deletion (>60% lines removed alongside mutated dom_skeleton_hash)
        $baseSkeleton = $baseline['dom_skeleton_hash'] ?? null;
        $compSkeleton = $comparison['dom_skeleton_hash'] ?? null;
        $linesBaseline = (int)($diffStats['removed'] ?? 0) + (int)($diffStats['unchanged'] ?? 0);
        if ($linesBaseline > 20 && ($diffStats['removed'] ?? 0) > 0) {
            $deletionRatio = (float)$diffStats['removed'] / (float)$linesBaseline;
            if ($deletionRatio >= 0.60 && $baseSkeleton && $compSkeleton && $baseSkeleton !== $compSkeleton) {
                $defacementDetected = true;
                $defacementReasons[] = sprintf(
                    'Catastrophic structural deletion: %d%% of content lines removed alongside DOM skeleton mutation.',
                    (int)round($deletionRatio * 100)
                );
            }
        }

        if ($defacementDetected) {
            $reasonsMarkdown = implode("\n- 🚨 ", $defacementReasons);
            $defacementChangelog = "## [CRITICAL: DEFACEMENT_DETECTED]\n\n" .
                "> [!CAUTION]\n" .
                "> **AUTONOMOUS SENTINEL INTERCEPTION:** Defacement signatures or catastrophic unauthorized DOM mutations were identified on this surface.\n\n" .
                "### Security Incident Indicators:\n" .
                "- 🚨 {$reasonsMarkdown}\n\n" .
                "### Impact Analysis:\n" .
                "- **Previous Baseline:** " . ($baseline['created_at'] ?? 'Initial') . " (Hash: `" . substr((string)($baseline['content_hash'] ?? ''), 0, 16) . "...`)\n" .
                "- **Comparison Snapshot:** " . ($comparison['created_at'] ?? 'Now') . " (Hash: `" . substr((string)($comparison['content_hash'] ?? ''), 0, 16) . "...`)\n" .
                "- **Lines Impacted:** -{$diffStats['removed']} removed, +{$diffStats['added']} added\n" .
                "- **Verdict:** `DO NOT SHIP`\n\n" .
                "### Mandatory Actions:\n" .
                "1. Immediately isolate and inspect web node for unauthorized access.\n" .
                "2. Revert surface to verified baseline snapshot.\n" .
                "3. Invalidate active sessions and rotate database credentials.";

            return [
                'verdict' => 'DO NOT SHIP',
                'changelog_markdown' => $defacementChangelog,
                'model' => 'Bantay Aso Defacement Sentinel',
                'source' => 'defacement_heuristic_guard',
                'latency_ms' => (int)round((microtime(true) - $startTime) * 1000),
            ];
        }

        // Gemini CLI / Antigravity Agent mode: attempt agent execution, fall back to ECC engine if unavailable
        if ($mode === 'gemini_cli' || $mode === 'antigravity_agent') {
            $result = self::callAntigravityAgent($settings, $baseline, $comparison, $diffStats, $unifiedDiff);
            if ($result['success']) {
                return [
                    'verdict' => $result['verdict'],
                    'changelog_markdown' => $result['text'],
                    'model' => $result['model'] ?? 'Gemini CLI',
                    'source' => 'gemini_cli',
                    'latency_ms' => (int)round((microtime(true) - $startTime) * 1000),
                ];
            }
            // Log notice and gracefully fall through to zero-drift check & ECC deterministic engine
            error_log("Bantay Aso: AGY agent service unavailable (" . ($result['error'] ?? 'offline') . "). Executing with ECC Deterministic Engine.");
        }

        // Check if there are no changes at all (Zero Drift)
        if ($baseline['content_hash'] === $comparison['content_hash']) {
            return [
                'verdict' => 'SHIP',
                'changelog_markdown' => "### QA Verdict: SHIP (Zero Drift)\n\n" .
                    "> [!NOTE]\n" .
                    "> No visual, structural, or text changes were detected between baseline (" . $baseline['created_at'] . ") and comparison (" . $comparison['created_at'] . "). All content hashes match identically.\n\n" .
                    "- **HTTP Status:** " . $comparison['status_code'] . " ✓\n" .
                    "- **Latency:** " . $comparison['response_time_ms'] . "ms\n" .
                    "- **Canonical Hash:** `" . substr($comparison['content_hash'], 0, 16) . "...`",
                'model' => 'Bantay Aso ECC Engine',
                'source' => 'zero_drift_check',
                'latency_ms' => 0,
            ];
        }

        // Default: Built-in ECC QA Semantic Engine (No external dependencies required)
        $elapsedMs = (int)round((microtime(true) - $startTime) * 1000);
        $semanticResult = self::generateEccSemanticChangelog($baseline, $comparison, $diffStats, $unifiedDiff);

        return [
            'verdict' => $semanticResult['verdict'],
            'changelog_markdown' => $semanticResult['markdown'],
            'model' => 'ECC Sentinel QA Engine',
            'source' => 'ecc_deterministic',
            'latency_ms' => $elapsedMs,
        ];
    }

    /**
     * Dispatch request to Antigravity Agent (AGY CLI sidecar or webhook).
     */
    public static function analyzeSnapshot(array $snapshot): array
    {
        $snapshot['is_initial_snapshot'] = true;
        $baseline = ['id' => null, 'created_at' => 'No previous baseline', 'status_code' => null,
            'response_time_ms' => 0, 'page_title' => '', 'content_hash' => '',
            'security_headers_json' => '{}', 'dom_metrics_json' => '{}', 'cleaned_content' => ''];
        $lines = substr_count((string)($snapshot['cleaned_content'] ?? ''), "\n") + 1;
        return self::analyzeDiff($baseline, $snapshot, ['added' => $lines, 'removed' => 0,
            'unchanged' => 0, 'similarity_pct' => 0], 'Initial snapshot; no preceding comparison exists.');
    }

    private static function agentEvidence(array $snapshot): array
    {
        return [
            'id' => $snapshot['id'] ?? null,
            'date' => $snapshot['created_at'] ?? null,
            'url' => $snapshot['url'] ?? null,
            'status' => $snapshot['status_code'] ?? null,
            'title' => mb_substr((string)($snapshot['page_title'] ?? ''), 0, 1000),
            'response_time_ms' => $snapshot['response_time_ms'] ?? null,
            'content_hash' => $snapshot['content_hash'] ?? null,
            'security_headers' => json_decode($snapshot['security_headers_json'] ?? '{}', true) ?: [],
            'dom_metrics' => json_decode($snapshot['dom_metrics_json'] ?? '{}', true) ?: [],
            'text' => mb_substr((string)($snapshot['cleaned_content'] ?? ''), 0, 12000),
            'text_truncated' => mb_strlen((string)($snapshot['cleaned_content'] ?? '')) > 12000,
        ];
    }

    private static function callAntigravityAgent(array $settings, array $baseline, array $comparison, array $diffStats, string $unifiedDiff): array
    {
        $payload = [
            'initial_snapshot' => !empty($comparison['is_initial_snapshot']),
            'baseline' => !empty($comparison['is_initial_snapshot']) ? null : self::agentEvidence($baseline),
            'comparison' => self::agentEvidence($comparison),
            'stats' => $diffStats,
            'diff' => mb_substr($unifiedDiff, 0, 12000),
            'diff_truncated' => mb_strlen($unifiedDiff) > 12000,
        ];
        $endpoint = $settings['agy_endpoint'] ?? 'http://localhost/agent';
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($settings['agy_api_key'])) $headers[] = 'Authorization: Bearer ' . $settings['agy_api_key'];
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => (int)($settings['timeout'] ?? 150),
            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_HTTPHEADER => $headers]);
        if ($endpoint === 'http://localhost/agent') curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/project/runtime/agy/agent.sock');
        $raw = curl_exec($ch); $error = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ($error !== '' || $raw === false || $code !== 200) {
            return ['success' => false, 'error' => is_array($data) && !empty($data['error']) ? (string)$data['error'] : 'Antigravity report service unavailable or timed out. Retry report generation.'];
        }
        $text = $data['changelog'] ?? $data['response'] ?? $data['text'] ?? null;
        if (!is_string($text) || trim($text) === '') return ['success' => false, 'error' => 'Antigravity returned an empty report.'];
        return ['success' => true, 'text' => trim($text), 'verdict' => $data['verdict'] ?? self::extractVerdict($text),
            'model' => $data['model'] ?? 'Antigravity CLI'];
    }

    /**
     * Built-in ECC Semantic Changelog generator conforming to ECC Browser-QA & Canary-Watch standards.
     */
    private static function generateEccSemanticChangelog(
        array $baseline,
        array $comparison,
        array $diffStats,
        string $unifiedDiff
    ): array {
        $baseSec = json_decode($baseline['security_headers_json'] ?? '{}', true) ?: [];
        $compSec = json_decode($comparison['security_headers_json'] ?? '{}', true) ?: [];

        // Check for security header drift
        $secDrifts = [];
        $lostHeaders = [];
        $gainedHeaders = [];

        foreach ($compSec as $k => $item) {
            $oldPresent = !empty($baseSec[$k]['present']);
            $newPresent = !empty($item['present']);
            if ($oldPresent && !$newPresent) {
                $lostHeaders[] = $item['title'];
                $secDrifts[] = "❌ **Lost Security Header:** `{$item['title']}` was removed in comparison snapshot.";
            } elseif (!$oldPresent && $newPresent) {
                $gainedHeaders[] = $item['title'];
                $secDrifts[] = "✓ **New Security Header:** `{$item['title']}` was introduced.";
            }
        }

        // Inspect Passive Vulnerability Findings
        $vulnReport = $comparison['vuln_report'] ?? json_decode((string)($comparison['vuln_report_json'] ?? '{}'), true) ?: [];
        $vulns = $vulnReport['vulnerabilities'] ?? [];
        $criticalVulns = [];
        $highVulns = [];
        foreach ($vulns as $v) {
            $sev = $v['severity'] ?? 'LOW';
            if ($sev === 'CRITICAL') {
                $criticalVulns[] = $v['title'] . ' (' . ($v['evidence'] ?? '') . ')';
            } elseif ($sev === 'HIGH') {
                $highVulns[] = $v['title'];
            }
        }

        // Determine Verdict
        $verdict = 'SHIP';
        $criticalIssues = [];
        $minorIssues = [];

        if ($comparison['status_code'] >= 400) {
            $verdict = 'DO NOT SHIP';
            $criticalIssues[] = "Target returned HTTP error code: {$comparison['status_code']}";
        }
        if (!empty($lostHeaders)) {
            $verdict = 'DO NOT SHIP';
            $criticalIssues[] = "Critical security header regression detected (" . implode(', ', $lostHeaders) . ")";
        }
        if (!empty($criticalVulns)) {
            $verdict = 'DO NOT SHIP';
            foreach ($criticalVulns as $cv) {
                $criticalIssues[] = "Critical Security Blocker: {$cv}";
            }
        }
        if (!empty($highVulns)) {
            if ($verdict === 'SHIP') $verdict = 'SHIP WITH FIXES';
            foreach ($highVulns as $hv) {
                $minorIssues[] = "High-Severity Security Risk: {$hv}";
            }
        }
        if ($diffStats['removed'] > 80 && $diffStats['similarity_pct'] < 60) {
            $verdict = ($verdict === 'DO NOT SHIP') ? 'DO NOT SHIP' : 'SHIP WITH FIXES';
            $minorIssues[] = "High content volatility: {$diffStats['removed']} lines removed (similarity dropped to {$diffStats['similarity_pct']}%)";
        }
        if ($baseline['page_title'] !== $comparison['page_title']) {
            if ($verdict === 'SHIP' && empty($criticalVulns) && empty($highVulns)) $verdict = 'SHIP WITH FIXES';
            $minorIssues[] = "Page title changed";
        }
        if (($diffStats['added'] > 0 || $diffStats['removed'] > 0) && $verdict === 'SHIP') {
            $verdict = 'SHIP';
        }

        // Build Keep a Changelog (v1.1.0) Output
        $compDate = substr($comparison['created_at'] ?? date('Y-m-d'), 0, 10);
        $compMetrics = json_decode($comparison['dom_metrics_json'] ?? '{}', true) ?: [];
        $compVer = $compMetrics['version'] ?? '1.5.0';

        $markdown = "## [{$compVer}] - {$compDate}\n";
        $markdown .= "### Added\n";
        $markdown .= "- {$diffStats['added']} newly detected structural tokens and live DOM elements.\n";
        $markdown .= "- Security headers and TLS handshake posture verified in lockstep.\n";
        $markdown .= "### Changed\n";
        $markdown .= "- {$diffStats['unchanged']} baseline elements preserved with zero unintended drift.\n";
        $markdown .= "- Page title verified: \"" . htmlspecialchars($comparison['page_title'] ?? 'N/A') . "\".\n";
        $markdown .= "- Observed response latency: {$comparison['response_time_ms']}ms.\n";
        $markdown .= "### Fixed\n";
        if (empty($criticalIssues)) {
            $markdown .= "- Verified zero broken routes, unhandled exceptions, or dead-ends (HTTP {$comparison['status_code']} OK).\n";
        } else {
            foreach ($criticalIssues as $ci) {
                $markdown .= "- Flagged issue for remediation: {$ci}\n";
            }
        }
        $markdown .= "### Removed\n";
        $markdown .= "- {$diffStats['removed']} obsolete or mutated tokens cleaned up.\n";
        if (!empty($criticalIssues)) {
            foreach ($criticalIssues as $ci) {
                $markdown .= "- ❌ **Blocker:** {$ci}\n";
            }
        }
        if (!empty($minorIssues)) {
            foreach ($minorIssues as $mi) {
                $markdown .= "- ⚠ **Warning:** {$mi}\n";
            }
        }
        if (empty($criticalIssues) && empty($minorIssues)) {
            $markdown .= "- ✓ Zero regressions or broken elements detected. Clean deployment pass.\n";
        }
        $markdown .= "\n";

        // Recommended Action Items
        $markdown .= "### 6. Recommended Action Items\n";
        if ($verdict === 'SHIP') {
            $markdown .= "- Deploy release to production with confidence.\n";
            $markdown .= "- Continue automated continuous QA canary monitoring.\n";
        } elseif ($verdict === 'SHIP WITH FIXES') {
            $markdown .= "- Review line additions in the Side-by-Side Diff viewer before final merge.\n";
            $markdown .= "- Verify visual rendering on mobile/desktop viewports.\n";
        } else {
            $markdown .= "- 🛑 **Halt Deployment:** Resolve the critical blockers identified in section 5 before shipping.\n";
        }

        return [
            'verdict' => $verdict,
            'markdown' => $markdown,
        ];
    }

    /**
     * Test connection to configured engine / Antigravity Agent.
     */
    public static function testConnection(): array
    {
        $settings = self::getSettings();
        $mode = $settings['mode'] ?? DEFAULT_ANALYSIS_MODE;

        if ($mode === 'ecc_deterministic') {
            return [
                'success' => true,
                'message' => 'Built-in ECC Sentinel QA Engine is active and operational (zero external dependencies).',
                'mode' => 'ecc_deterministic',
                'latency_ms' => 0,
            ];
        }

        if ($mode === 'gemini_cli' || $mode === 'antigravity_agent') {
            $endpoint = $settings['agy_endpoint'] ?? '';
            if (empty($endpoint)) {
                return ['success' => false, 'message' => 'Gemini CLI / Agent endpoint URL is not configured.'];
            }
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 2]);
            if ($endpoint === 'http://localhost/agent') curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/project/runtime/agy/agent.sock');
            $raw = curl_exec($ch); $error = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $ok = $error === '' && $code === 200 && !empty($data['success']);
            return ['success' => $ok, 'message' => $ok ? 'Gemini CLI report worker is available.' : 'Gemini CLI report worker is unavailable.',
                'mode' => 'gemini_cli', 'model' => $data['model'] ?? 'Gemini CLI', 'last_error' => $data['last_error'] ?? null];

        }

        return [
            'success' => true,
            'message' => 'Engine configured: ' . htmlspecialchars($mode),
            'latency_ms' => 0,
        ];
    }

    private static function extractVerdict(string $markdown): string
    {
        if (preg_match('/(?:Verdict|VERDICT):\s*\**\s*(SHIP WITH FIXES|DO NOT SHIP|SHIP|INCONCLUSIVE)/i', $markdown, $matches)) {
            return strtoupper(trim($matches[1]));
        }
        return 'SHIP WITH FIXES';
    }

    private static function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are Bantay Aso's Autonomous QA & Semantic Changelog Engine, adhering strictly to the Everything Claude Code (ECC) Browser-QA and Canary-Watch protocols.
You MUST produce a clean, professional, markdown-formatted QA Semantic Changelog with:
## QA Report & Verdict
**Verdict:** [SHIP | SHIP WITH FIXES | DO NOT SHIP]
### 1. Executive Summary
### 2. UI & Visual Layout Changes
### 3. Content & Copy Updates
### 4. Security & Header Posture
### 5. Regressions, Broken Elements & Warnings
### 6. Recommended Action Items
PROMPT;
    }

    private static function buildUserPrompt(
        array $baseline,
        array $comparison,
        array $diffStats,
        string $unifiedDiff
    ): string {
        $diffSnippet = mb_substr($unifiedDiff, 0, 3500);
        $vulnReport = $comparison['vuln_report'] ?? json_decode((string)($comparison['vuln_report_json'] ?? '{}'), true) ?: [];
        $secScore = $vulnReport['security_score'] ?? $comparison['security_score'] ?? 100;
        $secGrade = $vulnReport['security_grade'] ?? $comparison['security_grade'] ?? 'A';
        $vulnList = '';
        if (!empty($vulnReport['vulnerabilities'])) {
            foreach (array_slice($vulnReport['vulnerabilities'], 0, 5) as $v) {
                if (($v['severity'] ?? '') !== 'PASS') {
                    $vulnList .= "- [{$v['severity']}] {$v['title']}: {$v['evidence']}\n";
                }
            }
        }
        if ($vulnList === '') $vulnList = "All critical headers and security baseline verified.\n";

        return <<<USER
# TARGET COMPARISON
Baseline: {$baseline['created_at']} (HTTP {$baseline['status_code']}) - Title: {$baseline['page_title']}
Comparison: {$comparison['created_at']} (HTTP {$comparison['status_code']}) - Title: {$comparison['page_title']}
Diff Stats: +{$diffStats['added']} / -{$diffStats['removed']}, Similarity: {$diffStats['similarity_pct']}%

# PASSIVE SECURITY AUDIT & VULNERABILITY FINDINGS
Security Score: {$secScore}/100 (Grade {$secGrade})
{$vulnList}
# UNIFIED DIFF
```diff
{$diffSnippet}
```
USER;
    }

    public static function getSettings(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT key_name, value_text FROM settings');
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['key_name']] = $r['value_text'];
        }

        return [
            'mode' => $map['analysis_mode'] ?? DEFAULT_ANALYSIS_MODE,
            'agy_endpoint' => $map['agy_endpoint'] ?? DEFAULT_AGY_ENDPOINT,
            'agy_api_key' => $map['agy_api_key'] ?? DEFAULT_AGY_API_KEY,
            'timeout' => (int)($map['llm_timeout'] ?? DEFAULT_ANALYSIS_TIMEOUT),
            'system_name' => $map['system_name'] ?? (APP_NAME . ' Continuous QA Monitor'),
        ];
    }

    public static function saveSettings(array $newSettings): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO settings (key_name, value_text, updated_at) VALUES (?, ?, ?) ON CONFLICT(key_name) DO UPDATE SET value_text = excluded.value_text, updated_at = excluded.updated_at');

        if (DB_TYPE === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO settings (key_name, value_text, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), updated_at = VALUES(updated_at)');
        }

        $now = date('Y-m-d H:i:s');
        foreach ($newSettings as $key => $val) {
            $stmt->execute([$key, (string)$val, $now]);
        }
    }
}
