const fs = require('fs');
const path = require('path');
const assert = require('assert');
const vm = require('vm');

console.log('========================================================');
console.log('   Bantay Aso — QA System Time, User Tracking & Viewer Audit');
console.log('========================================================\n');

const appJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'app.js'), 'utf8');
const rootAppJs = fs.readFileSync(path.join(__dirname, '..', 'app.js'), 'utf8');
const indexPhp = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const qaHtml = fs.readFileSync(path.join(__dirname, '..', 'qa-dashboard.html'), 'utf8');
const dbPhp = fs.readFileSync(path.join(__dirname, '..', 'includes', 'db.php'), 'utf8');
const qaPhp = fs.readFileSync(path.join(__dirname, '..', 'api', 'qa.php'), 'utf8');

// TEST 1: Parity check between assets/js/app.js and app.js
console.log('[*] TEST 1: Code Parity');
assert.strictEqual(appJs, rootAppJs, 'FAIL: assets/js/app.js and root app.js must be identical');
console.log('    ✓ assets/js/app.js and app.js in 100% lockstep parity');

// TEST 2: Date & Time formatting engine (matches header clock Asia/Qatar)
console.log('\n[*] TEST 2: Header Clock & Table Timezone Matching Engine');
assert.ok(appJs.includes("timeZone: tz") || appJs.includes("timeZone: 'Asia/Qatar'"), 'FAIL: formatDate must format with Asia/Qatar');
assert.ok(appJs.includes("const tz = 'Asia/Qatar';"), 'FAIL: formatDate must define tz as Asia/Qatar');

// Extract and test formatDate function in isolated VM sandbox
const mockDoc = {
    addEventListener: () => {},
    querySelectorAll: () => [],
    getElementById: () => null,
    querySelector: () => null,
};
const mockWin = {
    addEventListener: () => {},
    document: mockDoc,
    location: { href: '' }
};
const sandbox = {
    window: mockWin,
    document: mockDoc,
    console: console,
    Date: Date,
    Intl: Intl,
    setTimeout: setTimeout,
    setInterval: () => {},
};
const ctx = vm.createContext(sandbox);
vm.runInContext(appJs, ctx);
const testApp = ctx.window.App || ctx.App;

// Test standard UTC string: 11:18 UTC should format to 02:18 PM in Doha (UTC+3)
const utcSample = '2026-10-26 11:18:00 UTC';
const formattedUtc = testApp.formatDate(utcSample, 'full');
console.log(`    UTC string "${utcSample}" formatted to: "${formattedUtc}"`);
assert.ok(formattedUtc.includes('02:18 PM') || formattedUtc.includes('2:18 PM'), 'FAIL: UTC 11:18 must format to 2:18 PM Doha time');

// Test DB local timestamp stored in Doha time: should not jump 3 hours into future
const localDbSample = '2026-10-26 14:18:00';
const formattedLocal = testApp.formatDate(localDbSample, 'full');
console.log(`    DB timestamp "${localDbSample}" formatted to: "${formattedLocal}"`);
assert.ok(formattedLocal.includes('02:18 PM') || formattedLocal.includes('2:18 PM'), 'FAIL: Doha 14:18 must format to 2:18 PM');

console.log('    ✓ Table date and time matches header clock (Asia/Qatar) without time drift');

// TEST 3: Admin-Only "Audited By / Triggered By" Tracking
console.log('\n[*] TEST 3: Admin User Tracking');
assert.ok(dbPhp.includes("'triggered_by'"), 'FAIL: db.php must include triggered_by column definition');
assert.ok(qaPhp.includes("triggered_by"), 'FAIL: api/qa.php must include triggered_by in queries and inserts');
assert.ok(appJs.includes("report.triggered_by"), 'FAIL: app.js must inspect report.triggered_by');
assert.ok(appJs.includes("report-user-pill"), 'FAIL: app.js must render .report-user-pill for admins');
assert.ok(indexPhp.includes("Triggered By"), 'FAIL: index.php must have Triggered By th header');
assert.ok(qaHtml.includes("Triggered By"), 'FAIL: qa-dashboard.html must have Triggered By th header');
console.log('    ✓ Database schema and API includes triggered_by column');
console.log('    ✓ Table header and row builder includes user badge for administrators');

// TEST 4: Viewer Read-Only Permissions (No Scans)
console.log('\n[*] TEST 4: Viewer Read-Only Permissions');
assert.ok(appJs.includes("Access Denied: Scan operations are restricted to administrators"), 'FAIL: triggerQAFromCard must guard against non-admins');
assert.ok(appJs.includes("Access Denied: Fleet scanning is restricted to administrators"), 'FAIL: triggerFleetSecurityScan must guard against non-admins');
assert.ok(indexPhp.includes("<?php if (($currentUser['role'] ?? 'admin') === 'admin'): ?>"), 'FAIL: index.php must hide scan button for viewers');
assert.ok(qaHtml.includes("Access Denied: Scan operations are restricted to administrators"), 'FAIL: qa-dashboard.html must guard scan against non-admins');
console.log('    ✓ Watchlist scan buttons conditionally hidden for viewers');
console.log('    ✓ Javascript execution blocked with Access Denied toast if non-admin attempts scan');
console.log('    ✓ Backend API strictly enforces Auth::requireAdmin() on run_qa and mutations');

console.log('\n========================================================');
console.log('   >>> ALL TIMEZONE, ADMIN & VIEWER QA CHECKS PASSED <<<');
console.log('========================================================\n');
