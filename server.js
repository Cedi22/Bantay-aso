/**
 * Bantay Aso — Node.js Dev Server & Parity Hub
 * Usage: node server.js
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

const PORT = process.env.LOCAL_SERVER_PORT || 8000;
const HOST = process.env.LOCAL_SERVER_HOST || '127.0.0.1';
const BASE_DIR = __dirname;

const MIME_TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.php': 'text/plain; charset=utf-8'
};

function executePythonState(res) {
    const py = spawn('python', ['-c', `
import sqlite3, json, os
db_path = os.path.join(r"${BASE_DIR}", "data", "bantayaso.sqlite")
conn = sqlite3.connect(db_path)
conn.row_factory = sqlite3.Row
c = conn.cursor()
c.execute("""
    SELECT w.id, w.name, w.url, w.description, w.check_interval_mins, w.is_active,
           w.last_status_code, w.last_response_time_ms, w.last_checked_at,
           COUNT(s.id) as snapshot_count,
           MAX(s.created_at) as latest_snapshot_date,
           (SELECT qa_verdict FROM snapshots WHERE watchlist_id = w.id ORDER BY id DESC LIMIT 1) as latest_verdict,
           (SELECT content_hash FROM snapshots WHERE watchlist_id = w.id ORDER BY id DESC LIMIT 1) as latest_content_hash
    FROM watchlist w
    LEFT JOIN snapshots s ON s.watchlist_id = w.id
    GROUP BY w.id
    ORDER BY w.id ASC
""")
items = []
healthy = 0
total_snaps = 0
for r in c.fetchall():
    st = r["last_status_code"]
    sc = r["snapshot_count"] or 0
    total_snaps += sc
    if st and 200 <= st < 300: healthy += 1
    items.append({
        "id": r["id"], "name": r["name"], "url": r["url"], "description": r["description"] or "",
        "check_interval_mins": r["check_interval_mins"], "is_active": r["is_active"],
        "last_status_code": st, "last_response_time_ms": r["last_response_time_ms"],
        "last_checked_at": r["last_checked_at"], "snapshot_count": sc,
        "latest_snapshot_date": r["latest_snapshot_date"],
        "latest_verdict": r["latest_verdict"] or ("HEALTHY" if st == 200 else "PENDING"),
        "latest_content_hash": (r["latest_content_hash"][:16] + "...") if r["latest_content_hash"] else None
    })
print(json.dumps({
    "success": True,
    "summary": { "total_targets": len(items), "healthy_targets": healthy, "total_snapshots": total_snaps, "drift_bytes": 0 },
    "items": items
}))
    `]);

    let data = '';
    py.stdout.on('data', chunk => data += chunk);
    py.on('close', code => {
        res.writeHead(200, {
            'Content-Type': 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(data || JSON.stringify({ success: false, error: 'Failed to query state' }));
    });
}

function executeSync(res) {
    let log = '';
    const isWin = process.platform === 'win32';
    const syncCmd = isWin ? 'powershell' : 'bash';
    const syncArgs = isWin
        ? ['-ExecutionPolicy', 'Bypass', '-File', path.join(BASE_DIR, 'sync.ps1'), '-Direction', 'Both']
        : [path.join(BASE_DIR, 'sync.sh'), 'Both'];

    log += `--- [File Sync Engine: ${isWin ? 'sync.ps1' : 'sync.sh'}] ---\n`;
    let fileSyncProc;
    try {
        fileSyncProc = spawn(syncCmd, syncArgs);
    } catch (err) {
        log += `[!] Failed to spawn file sync process: ${err.message}\n`;
    }

    if (fileSyncProc) {
        fileSyncProc.stdout.on('data', chunk => log += chunk);
        fileSyncProc.stderr.on('data', chunk => log += chunk);
        fileSyncProc.on('close', fileCode => {
            if (fileCode !== 0 && fileCode !== 23 && fileCode !== 24) {
                log += `\n[!] File sync warning: exited with code ${fileCode}\n`;
            } else {
                log += `\n[OK] File sync succeeded.\n`;
            }

            runOnlineSync(res, log, fileCode);
        });
    } else {
        runOnlineSync(res, log, 1);
    }
}

function runOnlineSync(res, currentLog, fileCode) {
    let log = currentLog + '\n--- [Data Parity Engine: sync_online.py] ---\n';
    let py;
    try {
        py = spawn('python', [path.join(BASE_DIR, 'sync_online.py')]);
    } catch (err) {
        log += `[!] Failed to spawn python sync_online.py: ${err.message}\n`;
        res.writeHead(500, {
            'Content-Type': 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin': '*'
        });
        return res.end(JSON.stringify({
            success: false,
            log: log.trim(),
            exit_code: 1
        }));
    }

    py.stdout.on('data', chunk => log += chunk);
    py.stderr.on('data', chunk => log += chunk);

    py.on('close', pyCode => {
        const overallSuccess = (fileCode === 0 || fileCode === 23 || fileCode === 24) && pyCode === 0;
        res.writeHead(overallSuccess ? 200 : 500, {
            'Content-Type': 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({
            success: overallSuccess,
            log: log.trim(),
            exit_code: overallSuccess ? 0 : (fileCode !== 0 ? fileCode : pyCode)
        }));
    });
}

const UI_STATE_FILE = path.join(BASE_DIR, 'config', 'ui_state.json');

function handleUiState(req, res) {
    if (req.method === 'GET') {
        fs.readFile(UI_STATE_FILE, 'utf8', (err, data) => {
            let state = { header_title: 'Bantay Aso', last_sync_utc: null, last_sync_origin: 'local' };
            if (!err && data) {
                try { state = { ...state, ...JSON.parse(data) }; } catch(e) {}
            }
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8', 'Access-Control-Allow-Origin': '*' });
            res.end(JSON.stringify({ success: true, ...state }));
        });
        return;
    }
    if (req.method === 'POST') {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            let input = {};
            try { input = JSON.parse(body); } catch(e) {}
            fs.readFile(UI_STATE_FILE, 'utf8', (err, data) => {
                let state = { header_title: 'Bantay Aso', last_sync_utc: null, last_sync_origin: 'local' };
                if (!err && data) {
                    try { state = { ...state, ...JSON.parse(data) }; } catch(e) {}
                }
                if (input.header_title) state.header_title = input.header_title;
                state.last_sync_utc = new Date().toISOString();
                state.last_sync_origin = input.last_sync_origin || 'local';

                try { fs.mkdirSync(path.dirname(UI_STATE_FILE), { recursive: true }); } catch(e) {}
                fs.writeFile(UI_STATE_FILE, JSON.stringify(state, null, 2), () => {
                    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8', 'Access-Control-Allow-Origin': '*' });
                    res.end(JSON.stringify({ success: true, ...state }));
                });
            });
        });
        return;
    }
}

const server = http.createServer((req, res) => {
    const parsedUrl = new URL(req.url, `http://${req.headers.host}`);
    let pathname = parsedUrl.pathname;

    // CORS preflight
    if (req.method === 'OPTIONS') {
        const origin = req.headers.origin || '*';
        res.writeHead(204, {
            'Access-Control-Allow-Origin': origin,
            'Access-Control-Allow-Credentials': 'true',
            'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-CSRF-Token, X-CSRF-TOKEN, X-XSRF-Token, X-Requested-With, Origin, Accept'
        });
        return res.end();
    }

    if (pathname === '/' || pathname === '/qa') {
        pathname = '/qa-dashboard.html';
    }

    if (pathname === '/api/auth' || pathname === '/api/auth.php') {
        const origin = req.headers.origin || '*';
        res.writeHead(200, {
            'Content-Type': 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin': origin,
            'Access-Control-Allow-Credentials': 'true'
        });
        return res.end(JSON.stringify({
            success: true,
            authenticated: true,
            user: { id: 1, username: 'admin', role: 'admin', email: 'admin@bantayaso.s3curity.net' },
            csrf_token: 'local_dev_token_bantayaso'
        }));
    }

    if (pathname === '/api/ui-state' || pathname === '/api/ui-state.php') {
        return handleUiState(req, res);
    }

    if (pathname === '/api/state' || pathname === '/api/state.php') {
        return executePythonState(res);
    }

    if (pathname === '/api/sync-files' || pathname === '/api/sync.php') {
        return executeSync(res);
    }

    // Static file serving
    const safePath = path.normalize(path.join(BASE_DIR, pathname));
    if (!safePath.startsWith(BASE_DIR)) {
        res.writeHead(403);
        return res.end('Access Denied');
    }

    fs.stat(safePath, (err, stats) => {
        if (err || !stats.isFile()) {
            res.writeHead(404, { 'Content-Type': 'application/json' });
            return res.end(JSON.stringify({ error: 'File Not Found' }));
        }

        const ext = path.extname(safePath).toLowerCase();
        const contentType = MIME_TYPES[ext] || 'application/octet-stream';

        res.writeHead(200, {
            'Content-Type': contentType,
            'Access-Control-Allow-Origin': '*'
        });
        fs.createReadStream(safePath).pipe(res);
    });
});

server.listen(PORT, HOST, () => {
    console.log('========================================================');
    console.log('   Bantay Aso — Node.js Dev Server & Parity Hub');
    console.log('========================================================');
    console.log(`Local URL    : http://${HOST}:${PORT}/qa-dashboard.html`);
    console.log(`State API    : http://${HOST}:${PORT}/api/state`);
    console.log(`Sync API     : http://${HOST}:${PORT}/api/sync-files`);
    console.log('========================================================');
});
