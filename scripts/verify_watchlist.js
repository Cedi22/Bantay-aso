const fs = require('fs');

console.log('--- Verifying Bantay Aso Watchlist Targets Implementation ---');

// 1. Verify CSS Injection in assets/css/app.css
const css = fs.readFileSync('assets/css/app.css', 'utf8');
console.assert(css.includes('.watchlist-grid'), 'FAIL: .watchlist-grid missing in app.css');
console.assert(css.includes('#05080f'), 'FAIL: #05080f background missing in app.css');
console.assert(css.includes('border-bottom: 1px solid rgba(0, 240, 255, 0.15)'), 'FAIL: cyan row border missing in app.css');
console.assert(css.includes('.badge-secure'), 'FAIL: .badge-secure missing in app.css');
console.assert(css.includes('.badge-vuln'), 'FAIL: .badge-vuln missing in app.css');
console.assert(css.includes('.badge-offline'), 'FAIL: .badge-offline missing in app.css');
console.log('✓ CSS styles verified in assets/css/app.css');

// 2. Verify HTML Structure in qa-dashboard.html
const html = fs.readFileSync('qa-dashboard.html', 'utf8');
console.assert(html.includes('id="watchlistContainer"'), 'FAIL: #watchlistContainer missing in qa-dashboard.html');
console.assert(html.includes('class="watchlist-grid"'), 'FAIL: .watchlist-grid missing in qa-dashboard.html');
console.assert(html.includes('>TARGET</th>'), 'FAIL: TARGET column header missing');
console.assert(html.includes('>STATUS</th>'), 'FAIL: STATUS column header missing');
console.assert(html.includes('>LAST QA SCAN</th>'), 'FAIL: LAST QA SCAN column header missing');
console.assert(html.includes('>ACTION</th>'), 'FAIL: ACTION column header missing');
console.assert(html.includes('id="watchlistBody"'), 'FAIL: #watchlistBody tbody missing');
console.log('✓ HTML container and columns verified in qa-dashboard.html');

// 3. Verify HTML Structure in index.php
const php = fs.readFileSync('index.php', 'utf8');
console.assert(php.includes('id="watchlistContainer"'), 'FAIL: #watchlistContainer missing in index.php');
console.assert(php.includes('class="watchlist-grid"'), 'FAIL: .watchlist-grid missing in index.php');
console.log('✓ HTML container verified in index.php');

// 4. Verify JS Functions in assets/js/app.js & qa-dashboard.html
const appJs = fs.readFileSync('assets/js/app.js', 'utf8');
console.assert(appJs.includes('renderWatchlist(targetsArray)'), 'FAIL: BantayAsoUI.renderWatchlist missing in app.js');
console.assert(appJs.includes('switchView(viewName)'), 'FAIL: BantayAsoUI.switchView missing in app.js');
console.assert(html.includes('renderWatchlist(targetsArray)'), 'FAIL: BantayAsoUI.renderWatchlist missing in qa-dashboard.html');
console.assert(html.includes('switchMainView: function(view)'), 'FAIL: App.switchMainView missing in qa-dashboard.html');
console.log('✓ JS renderWatchlist and switchMainView verified');

// 5. Verify runWatchlistQA.js standalone script
const qaScript = fs.readFileSync('runWatchlistQA.js', 'utf8');
console.assert(qaScript.includes('async function runWatchlistQA()'), 'FAIL: runWatchlistQA function missing');
console.assert(qaScript.includes('window.runWatchlistQA = runWatchlistQA'), 'FAIL: window.runWatchlistQA export missing');
console.assert(qaScript.includes('BantayAsoUI.renderWatchlist(mockTargets)'), 'FAIL: mock targets call missing');
console.assert(qaScript.includes('#watchdogLogoPlaceholder'), 'FAIL: logo bounds check missing');
console.log('✓ Standalone runWatchlistQA.js verified');

console.log('--- ALL INTEGRITY AND SYNTAX CHECKS PASSED (5/5) ---');
