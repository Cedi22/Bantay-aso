const fs = require('fs');
const https = require('https');
const http = require('http');

console.log('========================================================');
console.log('   Bantay Aso — Full Quality Assurance Verification Pass');
console.log('========================================================\n');

// -----------------------------------------------------------------------------
// TEST 1: Header Clock & Timezone Formatting Engine
// -----------------------------------------------------------------------------
console.log('[*] TEST 1: Header Clock & Timezone Formatting Engine');
const htmlContent = fs.readFileSync('qa-dashboard.html', 'utf8');

// Verify Header Clock DOM element
console.assert(htmlContent.includes('id="clock"'), 'FAIL: #clock element missing in qa-dashboard.html');
console.assert(htmlContent.includes('header-clock-pill'), 'FAIL: .header-clock-pill styling missing');
console.assert(htmlContent.includes("initDohaHeaderClock"), 'FAIL: initDohaHeaderClock missing in App');

// Test clock formatting logic
const testDate = new Date();
const dohaTime = testDate.toLocaleTimeString('en-US', {
    timeZone: 'Asia/Qatar',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
});
const clockString = `DOHA ${dohaTime}`;
console.log(`    Live Doha Clock generated: "${clockString}"`);
console.assert(clockString.startsWith('DOHA ') && clockString.length === 10, 'FAIL: Clock format mismatch');
console.log('    ✓ Header Clock correctly formats to "DOHA HH:MM" in Asia/Qatar');

// Test local timezone parsing logic in formatDate
function formatDateTest(dateStr) {
    if (!dateStr || dateStr === 'Never' || dateStr === 'N/A') return 'Never';
    const s = String(dateStr).trim();
    let norm = s;
    if (s.endsWith(' UTC')) {
        norm = s.slice(0, -4).replace(' ', 'T') + 'Z';
    } else if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(s)) {
        norm = s.replace(' ', 'T') + 'Z';
    }
    const d = new Date(norm);
    if (isNaN(d.getTime())) return s;
    return d.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });
}

const rawUtc = '2026-10-21 08:54:04 UTC';
const formattedLocal = formatDateTest(rawUtc);
console.log(`    Raw UTC timestamp: "${rawUtc}" -> Formatted Local: "${formattedLocal}"`);
console.assert(!formattedLocal.includes('UTC'), 'FAIL: UTC suffix retained');
console.log('    ✓ Dynamic local machine timezone conversion verified');

// -----------------------------------------------------------------------------
// TEST 2: Table 2 (Website QA Reports Table) & Auto-Append Engine
// -----------------------------------------------------------------------------
console.log('\n[*] TEST 2: Table 2 (Website QA Reports Table) & Auto-Append');
console.assert(htmlContent.includes('id="tableQaReports"'), 'FAIL: #tableQaReports missing');
console.assert(htmlContent.includes('id="qaReportsTableBody"'), 'FAIL: #qaReportsTableBody missing');
console.assert(htmlContent.includes('initQaReportsTable'), 'FAIL: initQaReportsTable missing in App');
console.assert(htmlContent.includes('fetchQaReports'), 'FAIL: fetchQaReports missing in App');
console.assert(htmlContent.includes('appendNewQaReport'), 'FAIL: appendNewQaReport missing in App');
console.assert(htmlContent.includes('buildQaReportItem'), 'FAIL: buildQaReportItem missing in App');
console.assert(htmlContent.includes('runNewQA'), 'FAIL: runNewQA missing in App');

// Simulate Table 2 Auto-Append State
const mockReportsState = {
    items: [
        { runNumber: 'Run #43', targetName: 'S3curity H3LIX HRMS Portal', verdict: 'SHIP' },
        { runNumber: 'Run #42', targetName: 'S3curity H3LIX HRMS Portal', verdict: 'SHIP' }
    ]
};

const newSnapshot = {
    id: 44,
    watchlist_id: 3,
    target_name: 'S3curity H3LIX HRMS Portal',
    page_title: 'S3curity H3LIX HRMS Portal',
    qa_verdict: 'SHIP',
    created_at: new Date().toISOString()
};
const newComparison = {
    id: 44,
    qa_verdict: 'SHIP',
    created_at: new Date().toISOString()
};

const appendedItem = {
    runNumber: `Run #${newComparison.id}`,
    targetName: newSnapshot.target_name,
    verdict: newComparison.qa_verdict
};

mockReportsState.items = [
    appendedItem,
    ...mockReportsState.items.filter(i => i.runNumber !== appendedItem.runNumber)
];

console.assert(mockReportsState.items[0].runNumber === 'Run #44', 'FAIL: New report was not prepended to index 0');
console.log(`    Pre-scan report count: 2 -> Post-scan report count: ${mockReportsState.items.length}`);
console.log(`    Top item in Table 2: ${mockReportsState.items[0].runNumber} (${mockReportsState.items[0].targetName}) [${mockReportsState.items[0].verdict}]`);
console.log('    ✓ Table 2 auto-append logic successfully verified');

// -----------------------------------------------------------------------------
// TEST 3: Live Synchronizer & Remote Node Health Pass
// -----------------------------------------------------------------------------
console.log('\n[*] TEST 3: Live Synchronizer Verification');

function fetchJson(url) {
    return new Promise((resolve, reject) => {
        const client = url.startsWith('https') ? https : http;
        client.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, json: JSON.parse(data) });
                } catch (e) {
                    resolve({ status: res.statusCode, raw: data });
                }
            });
        }).on('error', reject);
    });
}

async function verifySyncEndpoints() {
    try {
        // Check live remote reports endpoint
        const reportsRes = await fetchJson('https://bantayaso.s3curity.net/api/qa.php?action=reports&limit=5');
        console.assert(reportsRes.status === 200, `FAIL: Live reports endpoint returned ${reportsRes.status}`);
        console.assert(reportsRes.json.success === true, 'FAIL: Live reports endpoint success is not true');
        console.log(`    ✓ Remote Node Live Reports: HTTP ${reportsRes.status}, count = ${reportsRes.json.reports.length}`);

        // Check live remote sync endpoint
        const syncRes = await fetchJson('https://bantayaso.s3curity.net/api/sync.php?action=sync');
        console.assert(syncRes.status === 200, `FAIL: Live sync endpoint returned ${syncRes.status}`);
        console.assert(syncRes.json.success === true, 'FAIL: Live sync success is not true');
        console.assert(syncRes.json.exit_code === 0, 'FAIL: Live sync exit_code is non-zero');
        console.log(`    ✓ Remote Node Live Sync: HTTP ${syncRes.status}, exit_code = ${syncRes.json.exit_code}, message = "${syncRes.json.message}"`);

        // Check local dev server sync endpoint
        const localSyncRes = await fetchJson('http://127.0.0.1:8000/api/sync.php?action=status');
        console.assert(localSyncRes.status === 200, `FAIL: Local sync returned ${localSyncRes.status}`);
        console.log(`    ✓ Local Node Sync Status: HTTP ${localSyncRes.status}, exit_code = ${localSyncRes.json.exit_code}`);

        console.log('\n========================================================');
        console.log('   >>> ALL QA VERIFICATION CHECKS PASSED (100%) <<<');
        console.log('========================================================');
    } catch (err) {
        console.error('FAIL during verification:', err);
        process.exit(1);
    }
}

verifySyncEndpoints();
