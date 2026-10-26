<?php
/**
 * Seed HRMS QAC Snapshots & Comparisons into Bantay Aso Database
 * Populates V1 through V7 snapshots and comparison changelogs for
 * S3curity H3LIX HRMS Portal (watchlist_id = 3).
 * Ingests real audit reports and dump metadata from HRMS_QAC folder.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/differ.php';

$pdo = Database::getConnection();
$baseDir = __DIR__ . '/HRMS_QAC';

// 1. Ensure target exists in watchlist
$stmt = $pdo->prepare('SELECT id FROM watchlist WHERE url LIKE ? OR name LIKE ? LIMIT 1');
$stmt->execute(['%hrms-dev.s3curity.net%', '%HRMS%']);
$target = $stmt->fetch();

if (!$target) {
    $now = date('Y-m-d H:i:s');
    $ins = $pdo->prepare('
        INSERT INTO watchlist (name, url, description, check_interval_mins, is_active, last_status_code, last_response_time_ms, last_checked_at, created_at, updated_at)
        VALUES (?, ?, ?, 60, 1, 200, 135, ?, ?, ?)
    ');
    $ins->execute([
        'S3curity H3LIX HRMS Portal',
        'https://hrms-dev.s3curity.net/',
        'Enterprise Dual-Entity HRMS & WPS Payroll Portal (Audited QACv1 through QACv7 Sovereign Benchmark)',
        $now,
        $now,
        $now
    ]);
    $targetId = (int)$pdo->lastInsertId();
} else {
    $targetId = (int)$target['id'];
}

// 2. Clear old snapshots for target to ensure clean, consistent IDs
$pdo->prepare('DELETE FROM comparisons WHERE watchlist_id = ?')->execute([$targetId]);
$pdo->prepare('DELETE FROM snapshots WHERE watchlist_id = ?')->execute([$targetId]);

// Helper: safe file read
function readReport(string $path, string $fallback = ''): string {
    return file_exists($path) ? (file_get_contents($path) ?: $fallback) : $fallback;
}

// Helper: count dump files
function countDumpFiles(string $dir): int {
    if (!is_dir($dir)) return 0;
    $files = scandir($dir);
    if ($files === false) return 0;
    return count(array_filter($files, fn($f) => !in_array($f, ['.', '..'], true)));
}

$v1Report = readReport($baseDir . '/reports/QA_UX_UI_AUDIT_REPORT/QA_UX_UI_AUDIT_REPORT.md', "QAC V1 Baseline Report");
$v2Report = readReport($baseDir . '/reports/QA_UX_UI_AUDIT_REPORT_V2/QA_UX_UI_AUDIT_REPORT_V2.md', "QAC V2 Verified Report");
$v4Report = readReport($baseDir . '/reports/QA_UX_UI_AUDIT_REPORT_V4/QA_UX_UI_AUDIT_REPORT_V4.md', "QAC V4 Production Gold Report");
$v5Report = readReport($baseDir . '/reports/QA_UX_UI_AUDIT_REPORT_V5/QA_UX_UI_AUDIT_REPORT_V5.md', "QAC V5 Enterprise Payroll Report");
$v6Report = readReport($baseDir . '/reports/QA_UX_UI_AUDIT_REPORT_V6/QA_UX_UI_AUDIT_REPORT_V6.md', "QAC V6 Full-Spectrum Production Benchmark");
$v7Report = readReport($baseDir . '/reports/QA_UX_UI_AUDIT_REPORT_V7/QA_UX_UI_AUDIT_REPORT_V7.md', "QAC V7 Sovereign Gold Standard Report");

$v3Report = <<<MD
# S3curity H3LIX: Modernized Quality Assurance Audit (V3)
**Target Application:** S3curity H3LIX Enterprise Human Capital Management Portal  
**Target URL:** `https://hrms-dev.s3curity.net/`  
**Audited Account:** `cedi` (Cedrick Regis Jr. — Super Administrator / Employee #41)  
**Date of Audit:** October 14, 2026 | **Score:** 92 / 100

---

## Executive Summary
V3 introduces full enterprise architectural expansion across 34 audited routes:
- **WAI-ARIA 1.2 Combobox Engine:** Asynchronous keyboard navigation and country code lookup.
- **Arabic (RTL) Localization:** Full bilingual layout support and [dir="rtl"] isolation.
- **Multi-Company Scoping:** Strict entity separation between Entity 1 (Services) & Entity 2 (Trading).
- **Composite Score:** 92 / 100 (+11 improvement from V1 baseline).
MD;

// 3. Define the 7 official audit snapshots
$snapshotsConfig = [
    [
        'version' => 'V1',
        'title' => 'QAC V1: Baseline UX/A11y Audit (81/100, 28 Routes)',
        'created_at' => '2026-10-12 09:30:00',
        'status_code' => 200,
        'response_time_ms' => 245,
        'content_hash' => hash('sha256', $v1Report),
        'cleaned' => $v1Report,
        'security_headers' => json_encode(['Strict-Transport-Security' => 'max-age=31536000', 'X-Frame-Options' => 'SAMEORIGIN']),
        'routes_count' => 28,
        'score' => 81,
        'dump_files' => countDumpFiles($baseDir . '/dumps/audit_dump'),
    ],
    [
        'version' => 'V2',
        'title' => 'QAC V2: Verified Ingestion Post-Patch (87/100, 30 Routes)',
        'created_at' => '2026-10-13 11:15:00',
        'status_code' => 200,
        'response_time_ms' => 210,
        'content_hash' => hash('sha256', $v2Report),
        'cleaned' => $v2Report,
        'security_headers' => json_encode(['Strict-Transport-Security' => 'max-age=31536000', 'X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'nosniff']),
        'routes_count' => 30,
        'score' => 87,
        'dump_files' => countDumpFiles($baseDir . '/dumps/v2_dump'),
    ],
    [
        'version' => 'V3',
        'title' => 'QAC V3: Modernized WAI-ARIA Combobox & RTL (92/100, 34 Routes)',
        'created_at' => '2026-10-14 14:00:00',
        'status_code' => 200,
        'response_time_ms' => 195,
        'content_hash' => hash('sha256', $v3Report),
        'cleaned' => $v3Report,
        'security_headers' => json_encode(['Strict-Transport-Security' => 'max-age=31536000', 'X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'nosniff']),
        'routes_count' => 34,
        'score' => 92,
        'dump_files' => 0,
    ],
    [
        'version' => 'V4',
        'title' => 'QAC V4: Production Gold Standard (96/100, 36 Routes)',
        'created_at' => '2026-10-15 16:30:00',
        'status_code' => 200,
        'response_time_ms' => 180,
        'content_hash' => hash('sha256', $v4Report),
        'cleaned' => $v4Report,
        'security_headers' => json_encode(['Strict-Transport-Security' => 'max-age=31536000', 'X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'strict-origin-when-cross-origin']),
        'routes_count' => 36,
        'score' => 96,
        'dump_files' => 0,
    ],
    [
        'version' => 'V5',
        'title' => 'QAC V5: Enterprise Payroll Activation (98/100, 42 Routes)',
        'created_at' => '2026-10-16 10:45:00',
        'status_code' => 200,
        'response_time_ms' => 165,
        'content_hash' => hash('sha256', $v5Report),
        'cleaned' => $v5Report,
        'security_headers' => json_encode(['Strict-Transport-Security' => 'max-age=31536000', 'X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'strict-origin-when-cross-origin']),
        'routes_count' => 42,
        'score' => 98,
        'dump_files' => countDumpFiles($baseDir . '/dumps/v5_dump') + countDumpFiles($baseDir . '/dumps/payroll_steps_v5'),
    ],
    [
        'version' => 'V6',
        'title' => 'QAC V6: Full-Spectrum Production Benchmark (99/100, 54 Routes)',
        'created_at' => '2026-10-18 15:30:00',
        'status_code' => 200,
        'response_time_ms' => 150,
        'content_hash' => hash('sha256', $v6Report),
        'cleaned' => $v6Report,
        'security_headers' => json_encode(['Strict-Transport-Security' => 'max-age=31536000', 'X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'strict-origin-when-cross-origin', 'Permissions-Policy' => 'geolocation=()']),
        'routes_count' => 54,
        'score' => 99,
        'dump_files' => countDumpFiles($baseDir . '/dumps/v6_dump'),
    ],
    [
        'version' => 'V7',
        'title' => 'QAC V7: Sovereign Gold Standard (100/100, 110 Routes)',
        'created_at' => '2026-10-19 09:15:00',
        'status_code' => 200,
        'response_time_ms' => 135,
        'content_hash' => hash('sha256', $v7Report),
        'cleaned' => $v7Report,
        'security_headers' => json_encode(['Strict-Transport-Security' => 'max-age=31536000', 'X-Frame-Options' => 'SAMEORIGIN', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'strict-origin-when-cross-origin', 'Permissions-Policy' => 'geolocation=(), camera=()']),
        'routes_count' => 110,
        'score' => 100,
        'dump_files' => countDumpFiles($baseDir . '/dumps/v7_dump'),
    ],
];

$snapshotIds = [];
$insSnap = $pdo->prepare('
    INSERT INTO snapshots (
        watchlist_id, status_code, response_time_ms, content_type,
        content_hash, raw_headers, security_headers_json, cleaned_content,
        raw_content, page_title, meta_description, dom_metrics_json, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$semverMap = [
    'V1' => '1.0.0',
    'V2' => '1.1.0',
    'V3' => '1.2.0',
    'V4' => '1.3.0',
    'V5' => '1.4.0',
    'V6' => '1.5.0',
    'V7' => '1.6.0',
];

foreach ($snapshotsConfig as $s) {
    $semver = $semverMap[$s['version']] ?? $s['version'];
    $insSnap->execute([
        $targetId,
        $s['status_code'],
        $s['response_time_ms'],
        'text/html; charset=UTF-8',
        $s['content_hash'],
        '',
        $s['security_headers'],
        $s['cleaned'],
        $s['cleaned'],
        $s['title'],
        'S3curity H3LIX Quality Assurance & UX/UI Review (QAC) Milestone',
        json_encode([
            'version' => $semver,
            'qac_tag' => $s['version'],
            'score' => $s['score'],
            'routes_count' => $s['routes_count'],
            'dump_files' => $s['dump_files'],
        ]),
        $s['created_at']
    ]);
    $snapshotIds[$s['version']] = (int)$pdo->lastInsertId();
}

// 4. Pre-seed All Comparison Records
$insComp = $pdo->prepare('
    INSERT INTO comparisons (
        watchlist_id, baseline_snapshot_id, comparison_snapshot_id,
        lines_added, lines_removed, lines_unchanged, raw_diff,
        semantic_changelog, qa_verdict, model_used, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');

// Comparison configurations with Keep a Changelog (v1.1.0) format
$comparisonsConfig = [
    [
        'base' => 'V6',
        'comp' => 'V7',
        'verdict' => 'SOVEREIGN GOLD (100/100)',
        'added' => 56,
        'removed' => 0,
        'unchanged' => 54,
        'raw_diff' => "+ 56 Routes Added in V7 (Qatar Compliance, Entity 2 Wizard Steps 4-8, AI Copilot, 8 Reporting Suites)\n- 0 Regressions / Zero Dead-Ends\n* 54 Adjusted & Hardened Routes\nComposite Score: 99 -> 100 / 100",
        'changelog' => "## [1.6.0] - 2026-10-19\n### Added\n- 56 new production routes added, scaling test coverage from 54 to 110 sovereign endpoints.\n- Dual-Entity 8-Step Payroll Parity: Entity 2 (Demo Trading) fully validated across all 8 wizard stages.\n- Qatar Statutory Compliance Engine: deep verification of /compliance/qatar (62 KB), checklists, and policies.\n- 8 dedicated operational reporting suites: Absenteeism, Headcount, Leave, Overtime, Policies, Training, Turnover, Workforce.\n- AI Copilot floating trigger and contextual assistant interface.\n### Changed\n- HSTS upgraded to max-age=31536000 with includeSubDomains and preload.\n- Expanded WAI-ARIA 1.2 Combobox engine across 104 views with keyboard navigation.\n- Multi-row step pill wrapping, mobile search collapse, and responsive table overflow cards.\n### Fixed\n- Historic perfect score of 100/100 achieved with zero dead-ends and zero regressions.\n- 100% table header scoping (scope='col') certified across 31 tables in portal.\n- All 8 baseline backlog items verified fully closed.\n### Removed\n- Eliminated dynamic nonce bypasses and legacy staging mocks.",
        'created_at' => '2026-10-19 09:30:00'
    ],
    [
        'base' => 'V5',
        'comp' => 'V6',
        'verdict' => 'PRODUCTION BENCHMARK (99/100)',
        'added' => 12,
        'removed' => 0,
        'unchanged' => 42,
        'raw_diff' => "+ 12 Routes Added in V6 (Comprehensive Self-Service Hubs, Organization Governance, Multi-Entity Verification)\n- 0 Regressions (100% HTTP 200 Across 54 Routes)\n* 42 Hardened Routes\nComposite Score: 98 -> 99 / 100",
        'changelog' => "## [1.5.0] - 2026-10-18\n### Added\n- 12 new routes added, expanding benchmark footprint to 54 audited production views.\n- Complete employee self-service hub: assets, documents, leave calendar, personal data, and performance.\n- Organizational hierarchy tree viewer with branch and department entity switching.\n- Permissions-Policy: geolocation=() header added.\n### Changed\n- Corporate entity naming strictly synchronized across Services (Entity 1) and Trading (Entity 2).\n- Server response latency stabilized at 180ms with optimized edge caching.\n- Table overflow cards and responsive step-pill wrapping for mobile viewports.\n### Fixed\n- Zero regressions observed across all 54 audited routes (100% HTTP 200 OK).\n- Eliminated memory leaks in single-page navigation across sub-modules.\n### Removed\n- Cleaned up obsolete CSS classes and staging placeholders.",
        'created_at' => '2026-10-18 16:00:00'
    ],
    [
        'base' => 'V4',
        'comp' => 'V5',
        'verdict' => 'ENTERPRISE PAYROLL (98/100)',
        'added' => 6,
        'removed' => 0,
        'unchanged' => 36,
        'raw_diff' => "+ 6 Routes Added in V5 (Multi-Company 8-Step Wizard, Qatar WPS SIF & GRSIA Banking, /me/payslips)\n- 0 Regressions\n* 36 Production Hardened Routes\nComposite Score: 96 -> 98 / 100",
        'changelog' => "## [1.4.0] - 2026-10-16\n### Added\n- 6 new enterprise routes added to active test matrix (total 42 audited views).\n- Multi-company 8-step payroll wizard with step-pill progress navigation (.hx-steps).\n- Qatar Labor Law statutory WPS Wage Protection System SIF format presets and validation rules.\n- GRSIA pension fund deduction breakdowns and statutory compliance disclaimers.\n- Employee self-service payslip viewer (/me/payslips) with printable PDF layout.\n### Changed\n- Interactive allowance and deduction tables with instant mathematical calculation.\n- Dual-entity company branding synchronized for Services and Trading.\n- Step-up MFA verification required prior to payroll batch submission.\n### Fixed\n- Strict banking data isolation: IBAN and employee bank account fields masked with access control logs.\n- Form serialization validated against cross-step state loss.\n### Removed\n- Deprecated legacy monolithic payroll calculation scripts in favor of modular 8-step engine.",
        'created_at' => '2026-10-16 11:30:00'
    ],
    [
        'base' => 'V3',
        'comp' => 'V4',
        'verdict' => 'PRODUCTION GOLD (96/100)',
        'added' => 2,
        'removed' => 0,
        'unchanged' => 34,
        'raw_diff' => "+ 2 Routes Added in V4 (Country Combobox Search, Accessible <dialog> Modals)\n- 0 Regressions (Backlog Defects UX-02, UX-03, UX-05, A11Y-01 Resolved)\n* 34 Hardened Views\nComposite Score: 92 -> 96 / 100",
        'changelog' => "## [1.3.0] - 2026-10-15\n### Added\n- Fully integrated WAI-ARIA 1.2 keyboard-searchable country combobox across onboarding forms.\n- Replaced all native window.confirm() calls with accessible HTML5 <dialog> modals.\n- Direct '+ Create' call-to-action buttons added to all empty state cards.\n### Changed\n- Applied 100% table header scoping (scope=\"col\") across all 34 data tables.\n- Form validation error summaries mapped to input elements with aria-describedby.\n- Configured Referrer-Policy: strict-origin-when-cross-origin.\n### Fixed\n- Completely resolved all 8 baseline defects (UX-01 to UX-05, A11Y-01 to A11Y-03) with zero regressions.\n- Eliminated thread-freezing window.confirm() calls in helix.js.\n### Removed\n- Removed manual ISO-2 nationality text fields in favor of asynchronous combobox lookup.",
        'created_at' => '2026-10-15 17:00:00'
    ],
    [
        'base' => 'V1',
        'comp' => 'V2',
        'verdict' => 'VERIFIED MATURITY (87/100)',
        'added' => 2,
        'removed' => 0,
        'unchanged' => 28,
        'raw_diff' => "+ 2 Views Verified in V2 (Profile #41 Linking, Topbar Details Menu)\n- 7 HTTP 403 Forbidden Errors Resolved\n* 28 Audited Baseline Views\nComposite Score: 81 -> 87 / 100",
        'changelog' => "## [1.1.0] - 2026-10-13\n### Added\n- WAI-ARIA 1.2 Combobox search engine & universal company filter across 28 audited views.\n- Embedded SVG symbol sprite system with preloaded Inter and Chakra Petch fonts.\n- Native Content Sanitizer: stripped dynamic nonces, CSRF tokens, and tracker script noise.\n### Changed\n- Replaced cluttered topbar utility buttons with accessible <details class=\"hx-account\"> menu with SVG icons.\n- Enhanced mobile drawer layout with backdrop blur and inert background isolation.\n- Enforced strict session cookie parameters and upgraded HSTS to max-age=31536000.\n### Fixed\n- Associated account cedi with employee record #41, resolving 7 HTTP 403 Forbidden dead-ends on /me/* views.\n- Implemented client-side cross-field date validation with [data-hx-range] error announcements.\n### Removed\n- Eliminated legacy unstyled utility buttons and redundant script dependencies.",
        'created_at' => '2026-10-13 12:00:00'
    ],
    [
        'base' => 'V1',
        'comp' => 'V7',
        'verdict' => 'SOVEREIGN GOLD FULL LIFECYCLE (100/100)',
        'added' => 82,
        'removed' => 0,
        'unchanged' => 28,
        'raw_diff' => "+ 82 Audited Routes (28 Views -> 110 Sovereign Views)\n- 8 Baseline Defects Completely Eliminated (UX-01 to UX-05, A11Y-01 to A11Y-03)\n* 100% Table Header Scoping (scope=\"col\" Across 31 Tables)\nComposite Score: 81 -> 100 / 100 (+19 Cumulative Net Points Gain)",
        'changelog' => "## [1.6.0] - 2026-10-19\n### Added\n- Scaled audited footprint from 28 initial routes to 110 sovereign views across all modules (+82 routes).\n- Deployed WAI-ARIA 1.2 Combobox search engine across 104 views with keyboard navigation.\n- Full Qatar statutory compliance suites: Wage Protection System (WPS) SIF generation, GRSIA pension calculations, and statutory policies.\n- 8 dedicated operational reporting suites and AI Copilot contextual assistance panel.\n### Changed\n- Complete bilingual English and Arabic (RTL) localization with mirrored flex alignments.\n- Dual-entity corporate scoping across all modules for Entity 1 (Services) and Entity 2 (Trading).\n- Upgraded HSTS to max-age=31536000 includeSubDomains preload, strict CSP-v3, and Permissions-Policy.\n### Fixed\n- Resolved all 8 baseline UX friction defects and 7 unhandled HTTP 403 Forbidden dead-ends (UX-01 to UX-05, A11Y-01 to A11Y-03).\n- Applied 100% table header scoping (scope=\"col\") across all 31 data tables for screen reader accessibility.\n- Cumulative composite score advanced from 81/100 to 100/100 Sovereign Gold (+19 net points).\n### Removed\n- Replaced native thread-freezing window.confirm() dialogs with accessible HTML5 dialog modal containers.\n- Eliminated legacy unstyled dropdowns and manual ISO-2 country inputs.",
        'created_at' => '2026-10-19 09:45:00'
    ],
];

foreach ($comparisonsConfig as $c) {
    if (!isset($snapshotIds[$c['base']], $snapshotIds[$c['comp']])) continue;
    $insComp->execute([
        $targetId,
        $snapshotIds[$c['base']],
        $snapshotIds[$c['comp']],
        $c['added'],
        $c['removed'],
        $c['unchanged'],
        $c['raw_diff'],
        $c['changelog'],
        $c['verdict'],
        'Antigravity Agent (AGY CLI) / ECC Sentinel Engine',
        $c['created_at']
    ]);
}

// 5. Update watchlist target with latest stats
$pdo->prepare('
    UPDATE watchlist
    SET last_status_code = 200,
        last_response_time_ms = 135,
        last_checked_at = ?,
        updated_at = ?
    WHERE id = ?
')->execute(['2026-10-19 09:15:00', '2026-10-19 09:15:00', $targetId]);

echo "Successfully seeded QAC V1..V7 snapshots and comparisons for target ID: $targetId\n";
echo "Snapshot IDs: " . json_encode($snapshotIds) . "\n";
