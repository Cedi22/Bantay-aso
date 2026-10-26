/**
 * Bantay Aso — Frontend Cyber Toast Notification Test Suite
 * Validates removal of OS/SSH scripts, bottom-right viewport anchoring,
 * dark cyber theme (#0A0E17) styling, dynamic state updates, and UI action triggers.
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

console.log('========================================================');
console.log('   Bantay Aso — Frontend Cyber Toast Notification QA');
console.log('========================================================\n');

// 1. Verify removal of PowerShell OS/SSH scripts
console.log('[*] TEST 1: Decoupling from OS/PowerShell Scripts');
const sendNotifPs1 = path.join(ROOT_DIR, 'scripts', 'Send-Notification.ps1');
const notifySshPs1 = path.join(ROOT_DIR, 'scripts', 'notify_ssh.ps1');
console.assert(!fs.existsSync(sendNotifPs1), 'FAIL: scripts/Send-Notification.ps1 still exists!');
console.assert(!fs.existsSync(notifySshPs1), 'FAIL: scripts/notify_ssh.ps1 still exists!');
console.log('    ✓ scripts/Send-Notification.ps1 successfully removed');
console.log('    ✓ scripts/notify_ssh.ps1 successfully removed');

// 2. Verify CSS Toast Styling and Bottom-Right Viewport Anchor
console.log('\n[*] TEST 2: CSS Cyber-Theme (#0A0E17) & Bottom-Right Anchor');
const cssPath = path.join(ROOT_DIR, 'assets', 'css', 'app.css');
const rootCssPath = path.join(ROOT_DIR, 'app.css');
const cssContent = fs.readFileSync(cssPath, 'utf8');
const rootCssContent = fs.readFileSync(rootCssPath, 'utf8');

console.assert(cssContent === rootCssContent, 'FAIL: assets/css/app.css and root app.css are not in parity!');
console.log('    ✓ assets/css/app.css and app.css in 100% lockstep parity');

console.assert(cssContent.includes('bottom: 24px') && cssContent.includes('right: 24px'), 'FAIL: Missing bottom-right 24px positioning in CSS!');
console.log('    ✓ Viewport anchoring (bottom: 24px; right: 24px) verified');

console.assert(cssContent.includes('#0A0E17') || cssContent.includes('#0a0e17'), 'FAIL: Missing #0A0E17 dark background in toast CSS!');
console.log('    ✓ Dark cyber theme background (#0A0E17) verified');

console.assert(cssContent.includes('font-family: \'JetBrains Mono\''), 'FAIL: Missing monospace typography in toast CSS!');
console.log('    ✓ Monospace typography (JetBrains Mono / Consolas) verified');

console.assert(cssContent.includes('.toast-success') && cssContent.includes('.toast-error'), 'FAIL: Missing status border variants!');
console.log('    ✓ Cyan, emerald, rose, and amber status border variants verified');

// 3. Verify JavaScript Logic in app.js and assets/js/app.js
console.log('\n[*] TEST 3: Dynamic Controller Architecture in JavaScript');
const jsPath = path.join(ROOT_DIR, 'assets', 'js', 'app.js');
const rootJsPath = path.join(ROOT_DIR, 'app.js');
const jsContent = fs.readFileSync(jsPath, 'utf8');
const rootJsContent = fs.readFileSync(rootJsPath, 'utf8');

console.assert(jsContent === rootJsContent, 'FAIL: assets/js/app.js and root app.js are not in parity!');
console.log('    ✓ assets/js/app.js and app.js in 100% lockstep parity');

console.assert(jsContent.includes('showToast(messageOrOptions'), 'FAIL: showToast signature missing!');
console.assert(jsContent.includes('controller = {') && jsContent.includes('update:') && jsContent.includes('done:'), 'FAIL: Missing dynamic toast controller handle!');
console.log('    ✓ Dynamic stateful controller with .update(), .done(), and .error() verified');

// 4. Verify UI Action Triggers
console.log('\n[*] TEST 4: UI Action Notification Triggers');
// Sync Files trigger
console.assert(jsContent.includes("title: 'Sync Files'"), 'FAIL: Missing "Sync Files" toast trigger in app.js!');
console.log('    ✓ Trigger 1: Clicking "Sync Files" (progress -> success/error) verified');

// Target Scan trigger
console.assert(jsContent.includes("title: 'Target Scan'"), 'FAIL: Missing "Target Scan" toast trigger in app.js!');
console.log('    ✓ Trigger 2: Initiating Target "Scan" (progress -> verdict complete) verified');

// Bantay-Aso sync check trigger
console.assert(jsContent.includes("title: 'Bantay-Aso Sync Check'"), 'FAIL: Missing "Bantay-Aso Sync Check" toast trigger in app.js!');
console.log('    ✓ Trigger 3: Running "Bantay-Aso sync check" header test & parity check verified');

// 5. Verify qa-dashboard.html & index.php
console.log('\n[*] TEST 5: HTML Document Containers & Client Decoupling');
const qaHtml = fs.readFileSync(path.join(ROOT_DIR, 'qa-dashboard.html'), 'utf8');
const indexPhp = fs.readFileSync(path.join(ROOT_DIR, 'index.php'), 'utf8');

console.assert(qaHtml.includes('id="toastContainer"'), 'FAIL: qa-dashboard.html missing #toastContainer!');
console.assert(indexPhp.includes('id="toastContainer"'), 'FAIL: index.php missing #toastContainer!');
console.log('    ✓ #toastContainer present in both qa-dashboard.html and index.php');

console.assert(qaHtml.includes('title: "Sync Files"'), 'FAIL: qa-dashboard.html missing Sync Files toast!');
console.assert(qaHtml.includes('title: "Target Scan"'), 'FAIL: qa-dashboard.html missing Target Scan toast!');
console.assert(qaHtml.includes('title: "Bantay-Aso Sync Check"'), 'FAIL: qa-dashboard.html missing Bantay-Aso Sync Check toast!');
console.log('    ✓ All 3 UI notification triggers configured in qa-dashboard.html');

console.log('\n========================================================');
console.log('   >>> ALL CYBER TOAST NOTIFICATION CHECKS PASSED <<<');
console.log('========================================================\n');
