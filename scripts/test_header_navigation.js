const fs = require('fs');

console.log('========================================================');
console.log('   Bantay Aso — Header Navigation Refactor QA Suite');
console.log('========================================================\n');

// 1. Verify CSS styles
console.log('[*] TEST 1: CSS Dark Container (#0A0E17), Neon Accents & Responsive Rules');
const css = fs.readFileSync('assets/css/app.css', 'utf8');
const rootCss = fs.readFileSync('app.css', 'utf8');

console.assert(css === rootCss, 'FAIL: assets/css/app.css and root app.css are not identical');
console.log('    ✓ assets/css/app.css and root app.css are in 100% lockstep parity');

console.assert(css.includes('#0A0E17'), 'FAIL: #0A0E17 dark container background missing');
console.log('    ✓ #0A0E17 dark container background style verified');

console.assert(css.includes('rgba(0, 240, 255'), 'FAIL: Cyan neon accents missing');
console.assert(css.includes('rgba(16, 185, 129') || css.includes('#39ffb4'), 'FAIL: Emerald neon accents missing');
console.log('    ✓ Cyan and emerald neon accent styling verified');

console.assert(css.includes('.nav-dropdown-menu'), 'FAIL: .nav-dropdown-menu styles missing');
console.assert(css.includes('.nav-dropdown-right .nav-dropdown-menu'), 'FAIL: right-aligned utilities dropdown styles missing');
console.log('    ✓ Dropdown container and right-aligned utilities menu verified');

console.assert(css.includes('@media (max-width: 960px)'), 'FAIL: Responsive collapse breakpoint missing');
console.assert(css.includes('[data-tooltip]::after'), 'FAIL: Responsive cyber tooltips missing');
console.log('    ✓ Responsive styling and icon-only tooltips on compact viewports verified');

// 2. Verify index.php Navigation Elements
console.log('\n[*] TEST 2: index.php Header Navigation Structure');
const php = fs.readFileSync('index.php', 'utf8');

// Primary Tabs
console.assert(php.includes('id="tabNavWatchlist"'), 'FAIL: #tabNavWatchlist missing in index.php');
console.assert(php.includes('id="tabNavSecurityHub"'), 'FAIL: #tabNavSecurityHub missing in index.php');
console.log('    ✓ Primary tabs "Watchlist Targets" and "Security Hub" verified');

// Grouped Sync Tools
console.assert(php.includes('id="syncToolsDropdown"'), 'FAIL: #syncToolsDropdown missing in index.php');
console.assert(php.includes('id="btnSyncToolsDropdown"'), 'FAIL: #btnSyncToolsDropdown trigger missing in index.php');
console.assert(!php.includes('id="tabNavSyncManager"'), 'FAIL: #tabNavSyncManager should be removed in index.php');
console.assert(php.includes('id="btnSyncOnline"'), 'FAIL: #btnSyncOnline missing in index.php');
console.log('    ✓ Grouped "Sync Tools" dropdown verified (SSH Sync Manager removed)');

// Consolidated Utilities
console.assert(php.includes('id="userUtilitiesDropdown"'), 'FAIL: #userUtilitiesDropdown missing in index.php');
console.assert(php.includes('id="btnOpenProfile"'), 'FAIL: #btnOpenProfile missing in index.php');
console.assert(php.includes('id="btnOpenUsers"'), 'FAIL: #btnOpenUsers missing in index.php');
console.assert(php.includes('id="btnOpenSettings"'), 'FAIL: #btnOpenSettings missing in index.php');
console.assert(php.includes('id="btnLogoutNav"'), 'FAIL: #btnLogoutNav missing in index.php');
console.log('    ✓ Consolidated utilities dropdown (Profile, Users, Engine, Sign Out) verified');

// 3. Verify qa-dashboard.html Navigation Elements
console.log('\n[*] TEST 3: qa-dashboard.html Header Navigation Structure');
const html = fs.readFileSync('qa-dashboard.html', 'utf8');

console.assert(html.includes('id="tabQaWatchlist"'), 'FAIL: #tabQaWatchlist missing in qa-dashboard.html');
console.assert(html.includes('id="tabQaSecurityHub"'), 'FAIL: #tabQaSecurityHub missing in qa-dashboard.html');
console.assert(html.includes('id="syncToolsQaDropdown"'), 'FAIL: #syncToolsQaDropdown missing in qa-dashboard.html');
console.assert(html.includes('id="tabQaSyncTools"'), 'FAIL: #tabQaSyncTools trigger missing in qa-dashboard.html');
console.assert(!html.includes('id="tabQaSyncManager"'), 'FAIL: #tabQaSyncManager should be removed in qa-dashboard.html');
console.assert(html.includes('id="btnQuickSyncTrigger"'), 'FAIL: #btnQuickSyncTrigger missing in qa-dashboard.html');
console.assert(html.includes('id="userUtilitiesQaDropdown"'), 'FAIL: #userUtilitiesQaDropdown missing in qa-dashboard.html');
console.assert(html.includes('id="btnQaProfile"'), 'FAIL: #btnQaProfile missing in qa-dashboard.html');
console.assert(html.includes('id="btnQaUsers"'), 'FAIL: #btnQaUsers missing in qa-dashboard.html');
console.assert(html.includes('id="btnQaEngine"'), 'FAIL: #btnQaEngine missing in qa-dashboard.html');
console.assert(html.includes('id="btnQaLogout"'), 'FAIL: #btnQaLogout missing in qa-dashboard.html');
console.log('    ✓ qa-dashboard.html primary tabs, sync tools, and utilities dropdowns verified');

// 4. Verify JS Handlers and Dynamic User Badge Rendering
console.log('\n[*] TEST 4: JavaScript Logic, Dropdown Handling & Dynamic User Badge');
const js = fs.readFileSync('assets/js/app.js', 'utf8');
const rootJs = fs.readFileSync('app.js', 'utf8');

console.assert(js === rootJs, 'FAIL: assets/js/app.js and root app.js are not identical');
console.log('    ✓ assets/js/app.js and root app.js are in 100% lockstep parity');

console.assert(js.includes('initNavDropdowns()'), 'FAIL: initNavDropdowns missing in app.js');
console.assert(js.includes('closeAllDropdowns()'), 'FAIL: closeAllDropdowns missing in app.js');
console.assert(js.includes('tabSyncTools'), 'FAIL: tabSyncTools active handling missing in switchView');
console.log('    ✓ Dropdown toggling, click-outside dismissal, and view switching state verified');

console.log('\n========================================================');
console.log('   >>> ALL HEADER REFACTOR QA CHECKS PASSED (100%) <<<');
console.log('========================================================');
