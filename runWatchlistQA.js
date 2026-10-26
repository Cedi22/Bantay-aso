/**
 * Bantay Aso - Client-Side QA Simulation & Layout Regression Script
 * 
 * Target Component: Watchlist Data Grid (#watchlistContainer) & Header Bounds (#watchdogLogoPlaceholder)
 * Purpose: Simulates target telemetry states through BantayAsoUI.renderWatchlist(),
 *          validating high-density cyber table layout, status badges (secure, vuln, offline),
 *          view switching between Watchlist and SSH Sync Terminal, and header 48x48px lock.
 */

async function runWatchlistQA() {
    console.log('%c[QA E2E] Starting Watchlist Targets Interface & Layout Regression QA Simulation...', 'color: #00f0ff; font-weight: bold;');

    const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    // 0. Pre-flight Element & Module Verification
    const watchlistContainer = document.getElementById('watchlistContainer');
    const logo = document.getElementById('watchdogLogoPlaceholder');
    const syncTerminal = document.getElementById('terminalSyncCmd');

    if (!window.BantayAsoUI || typeof window.BantayAsoUI.renderWatchlist !== 'function') {
        console.error('[QA FAILED] BantayAsoUI.renderWatchlist is not available on window.');
        return false;
    }

    if (!watchlistContainer) {
        console.error('[QA FAILED] Element #watchlistContainer was not found in the DOM.');
        return false;
    }

    if (!logo) {
        console.error('[QA FAILED] Element #watchdogLogoPlaceholder was not found in the DOM.');
        return false;
    }

    // 1. State Simulation: View Switching
    console.log('%c[QA Progress] Activating Watchlist Targets view via App.switchMainView("watchlist")...', 'color: #fbbf24;');
    if (window.App && typeof window.App.switchMainView === 'function') {
        window.App.switchMainView('watchlist');
    } else if (window.BantayAsoUI && typeof window.BantayAsoUI.switchView === 'function') {
        window.BantayAsoUI.switchView('watchlist');
    }
    await delay(500);

    // 2. State Simulation: Mock Payload of 3 Targets (1 Secure, 1 Vulnerable, 1 Offline)
    console.log('%c[QA Progress] Injecting mock targets payload into BantayAsoUI.renderWatchlist()...', 'color: #fbbf24;');
    const mockTargets = [
        {
            id: 1,
            name: "S3curity H3LIX HRMS Portal",
            url: "https://hrms-dev.s3curity.net/",
            ip: "104.21.48.112",
            status: "SECURE",
            last_scan: "2026-10-20 22:15:30 UTC"
        },
        {
            id: 2,
            name: "East West International Qatar",
            url: "https://baalbaki.me/ewi7",
            ip: "192.168.1.10",
            status: "VULNERABLE",
            last_scan: "2026-10-20 22:30:15 UTC"
        },
        {
            id: 3,
            name: "Hard N' Heavy",
            url: "https://hardnheavyqatar.com/",
            ip: "185.199.108.153",
            status: "OFFLINE",
            last_scan: "2026-10-19 18:30:11 UTC"
        }
    ];

    window.BantayAsoUI.renderWatchlist(mockTargets);
    await delay(600);

    console.log('%c[QA Progress] Running Automated Assertions...', 'color: #00f0ff; font-weight: bold;');

    // Assertion 1: #watchlistContainer is visible (display: block or flex) and Sync Terminal is hidden
    const containerComputedDisplay = window.getComputedStyle(watchlistContainer).display;
    const isWatchlistVisible = containerComputedDisplay === 'block' || containerComputedDisplay === 'flex';

    const syncSection = document.getElementById('sectionOfflineSync');
    const isTerminalHidden = (
        !syncSection ||
        (syncSection && window.getComputedStyle(syncSection).display === 'none') ||
        (syncTerminal && (syncTerminal.offsetParent === null || window.getComputedStyle(syncTerminal).display === 'none'))
    );

    console.assert(
        isWatchlistVisible,
        `[ASSERTION FAILED] #watchlistContainer must be visible (display: block or flex), but computed style is "${containerComputedDisplay}".`
    );

    console.assert(
        isTerminalHidden,
        `[ASSERTION FAILED] Sync Terminal must be hidden when Watchlist view is active.`
    );

    // Assertion 2: Exactly 3 child rows spawned in the grid
    const spawnedRows = watchlistContainer.querySelectorAll('tbody tr.watchlist-row');
    const hasThreeRows = spawnedRows.length === 3;

    console.assert(
        hasThreeRows,
        `[ASSERTION FAILED] Expected exactly 3 child rows in watchlist grid, but found ${spawnedRows.length}.`
    );

    // Verify Presence of Semantic Cyber Status Badges
    const secureBadge = watchlistContainer.querySelector('.badge-secure');
    const vulnBadge = watchlistContainer.querySelector('.badge-vuln');
    const offlineBadge = watchlistContainer.querySelector('.badge-offline');

    const hasAllBadges = (secureBadge !== null) && (vulnBadge !== null) && (offlineBadge !== null);
    console.assert(
        hasAllBadges,
        `[ASSERTION FAILED] Status badges missing in DOM. Secure: ${!!secureBadge}, Vuln: ${!!vulnBadge}, Offline: ${!!offlineBadge}`
    );

    // Assertion 3: Regression Check: #watchdogLogoPlaceholder remains exactly 48x48px
    const logoRect = logo.getBoundingClientRect();
    const isLogoWidthExact = Math.round(logoRect.width) === 48;
    const isLogoHeightExact = Math.round(logoRect.height) === 48;

    console.assert(
        isLogoWidthExact && isLogoHeightExact,
        `[REGRESSION ASSERTION FAILED] #watchdogLogoPlaceholder bounds violated. Expected 48x48px, got ${logoRect.width}x${logoRect.height}px.`
    );

    // Summary Telemetry Report
    const allPassed = isWatchlistVisible && isTerminalHidden && hasThreeRows && hasAllBadges && isLogoWidthExact && isLogoHeightExact;

    if (allPassed) {
        console.log(
            '%c[QA E2E COMPLETE] All checks passed successfully! Watchlist grid rendered with 3 targets (Secure, Vulnerable, Offline), view toggled correctly, and logo bounds locked at 48x48px.',
            'color: #00ff66; font-weight: bold; font-size: 13px;'
        );
    } else {
        console.warn(
            '%c[QA E2E COMPLETE] Some Watchlist assertions failed. Inspect the console.assert messages above for details.',
            'color: #ff0055; font-weight: bold; font-size: 13px;'
        );
    }

    return allPassed;
}

// Make accessible to console and global scope
window.runWatchlistQA = runWatchlistQA;
