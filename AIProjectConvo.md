# Bantay Aso — AI Project Context & Conversation Ledger
> **Purpose:** This document maintains complete architectural and conversational context for Antigravity AI, Google Gemini, and Google Argon agents to prevent redundant questions, avoid duplicate work, and ensure consistency across sessions.

---

## 1. Project Identity & Architecture

- **Project Name:** Bantay Aso (Autonomous QA & Semantic Sentinel Surface Monitor)
- **Primary Function:** Continuous website inspection, noise-filtered DOM/text diffing, security header auditing (CSP, HSTS, X-Frame-Options), and automated QA changelog generation.
- **Local Environment:**
  - **OS:** Windows 11
  - **Workspace Path:** `C:\Cedi\bantayaso\` (Migrated out of OneDrive to prevent NTFS locks)
  - **WSL Subsystem:** Ubuntu (Default) with `/usr/bin/rsync` and OpenSSH
  - **Local Binaries Available:** Python 3.14 (`C:\Python314\python.exe`), Node.js (`C:\Program Files\nodejs\node.exe`), XAMPP PHP (`C:\xampp\php\php.exe`), OpenSSH (`C:\Windows\System32\OpenSSH\ssh.exe`), Git.
- **Remote Production Server:**
  - **URL:** `https://bantayaso.s3curity.net`
  - **Hosting Platform:** Virtualmin VPS (LAMP / LEMP stack)
  - **SSH Host:** `bantayaso.s3curity.net` (Port 22)
  - **SSH User:** `s3curity` (or configured domain user)
  - **Remote Web Root:** `/home/s3curity/domains/bantayaso.s3curity.net/public_html/` (or `/home/<user>/public_html/`)
  - **Database Mode:** SQLite3 (`data/bantayaso.sqlite`) with optional MySQL fallback.

---

## 2. Solved & Implemented Milestones (DO NOT RE-ASK / RE-IMPLEMENT)

### A. Security & Environment Isolation
- [x] **Plaintext Password Removal:** All hardcoded passwords (`@@22162613Bantayaso`) have been permanently removed from client-side UI (`qa-dashboard.html`, `index.php`, `login.php`). Masked representations (`admin / [Secured in .env]`) are shown instead.
- [x] **`.env` Configuration:** Sensitive variables (`REMOTE_BASE_URL`, `REMOTE_ADMIN_USER`, `REMOTE_ADMIN_PASS`, `VIRTUALMIN_HOST`, `VIRTUALMIN_USER`, `VIRTUALMIN_REMOTE_PATH`, `SSH_KEY_PATH`, `LOCAL_SERVER_PORT`) are isolated in [`.env`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.env).
- [x] **Template & Version Control:** Clean [`.env.example`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.env.example) and [`.gitignore`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.gitignore) are created.
- [x] **Argon2id Upgrade:**
  - Defined in [`config/security.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/config/security.php) (`memory_cost: 65536`, `time_cost: 4`, `threads: 2`).
  - Implemented one-time CLI migration script: [`scripts/migrate_hashes.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/scripts/migrate_hashes.php).
  - Integrated transparent on-login rehash in [`includes/auth_middleware.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/includes/auth_middleware.php), upgrading legacy bcrypt (`$2y$`) hashes to `$argon2id$v=19$` automatically upon user authentication.

### B. Two-Way File Sync Scripting & OneDrive Mitigation
- [x] **PowerShell Script ([`sync.ps1`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/sync.ps1)):**
  - Bidirectional sync using `rsync -avz --update --human-readable`.
  - Automatically bridges into WSL Ubuntu `/usr/bin/rsync` and handles Windows path translation (`C:\Users\...` to `/mnt/c/Users/...`).
  - Supports `-Direction Both|Pull|Push`, `-DryRun`, and `-IncludeDb`.
  - **OneDrive Lock Mitigation:** Staged temporary files via `--temp-dir=/tmp` (in WSL RAM disk) and added `--no-perms --no-owner --no-group` to avoid NTFS/9P oplock collisions.
- [x] **Bash Script ([`sync.sh`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/sync.sh)):**
  - Native Linux/WSL/Git Bash sync engine with matching excludes and `--temp-dir=/tmp`.
- [x] **Decoupling Code from State:** `data/*.sqlite*` is excluded from standard code syncs to prevent overwriting active cron snapshots recorded on the live server while the local workstation is offline.

### C. Unified State API & 100% Lockstep Parity
- [x] **Endpoint [`/api/state`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/api/state.php):**
  - Returns real-time JSON metrics: total targets (5), healthy count (2), snapshot counts, response time ms, latest verdicts, and hashes.
  - Enabled full CORS (`Access-Control-Allow-Origin: *`).
- [x] **Apache Rewrite Rules ([`.htaccess`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.htaccess)):**
  - Rewrites `/api/state` to `api/state.php` and protects SQLite files from public download.
- [x] **Dynamic Frontend (`qa-dashboard.html`):**
  - Replaced static mocked HTML cards with `App.loadState()`. The 5 monitored targets (`Bantay Aso Production Node`, `S3curity H3LIX HRMS Portal`, `East West International Qatar`, `Hard N' Heavy`, `S3curity.net Defense Hub`) now render dynamic telemetry and verdicts directly from the database.

### D. Wired "Sync Files" Button & Local Dev Server
- [x] **Local Dev Server ([`server.py`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/server.py)):**
  - Zero-dependency Python server running on `http://127.0.0.1:8000/qa-dashboard.html`.
  - Serves static assets, directly queries SQLite for `/api/state`, and handles `POST /api/sync-files`.
- [x] **Node Server Alternative ([`server.js`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/server.js)):**
  - Native Node.js dev server for developers preferring `node server.js`.
- [x] **Protocol Warning Banner:**
  - Displays sticky warning if opened via `file:///`:
    `PROTOCOL NOTICE (file:///): Browsers block file:/// pages from executing backend rsync/SSH scripts and CORS state APIs. Launch run-local.bat or run python server.py to connect.`
- [x] **Wired Trigger:**
  - Clicking **"⚡ Sync Files"**, **"🔄 Sync Local ⇄ NAS"**, or **"⚡ Synchronize Now"** executes the backend script, streams log output to the terminal bar, and dynamically refreshes the UI state without page reload.
- [x] **Synchronization Header Test Button:**
  - Added interactive toggle button (`🧪 Header: Bantay-aso`) in the header of both `index.php` and `qa-dashboard.html`.
  - Toggles the h1 header between `Bantay Aso` and `Bantay-aso` with localStorage persistence for easy visual verification of sync operations.
- [x] **Watchlist Targets Interface & Cyber Data Grid:**
  - High-density cyber-security data grid styles in [`assets/css/app.css`](file:///c:/Cedi/bantayaso/assets/css/app.css) with `#05080f` background, monospace typography (13px), subtle cyan row borders (`rgba(0, 240, 255, 0.15)`), and left border glow on hover.
  - Utility classes for status badges: `.badge-secure` (emerald, neon glow), `.badge-vuln` (rose/red, pulsing animation), `.badge-offline` (amber/gray).
  - HTML structure `<div id="watchlistContainer">` with columns: `TARGET`, `STATUS`, `LAST QA SCAN`, and `ACTION`.
  - Dynamic JavaScript render function: `BantayAsoUI.renderWatchlist(targetsArray)` injecting into `#watchlistContainer`.
  - View switcher: `App.switchMainView()` correctly toggles `display: none` between `#watchlistContainer` and the SSH Sync Terminal wrapper (`#sectionOfflineSync`).
  - Standalone automated QA simulation suite: [`runWatchlistQA.js`](file:///c:/Cedi/bantayaso/runWatchlistQA.js) validating view toggling, 3-row payload injection, status badges, and 48x48px `#watchdogLogoPlaceholder` layout regression check.

### E. S3curity H3LIX Design System Integration (from `hrms-dev.s3curity.net`)
- [x] **Live Stylesheet Extraction:** Authenticated to `https://hrms-dev.s3curity.net/` using user credentials (`cedi`), analyzed live design system v2 (2026-10-12 redesign), and extracted `/css/helix.css` (39.7 KB), `/fonts/fonts.css`, and SVG branding assets.
- [x] **Local Assets Deployment:** Saved authentic [`assets/css/helix.css`](file:///c:/Cedi/bantayaso/assets/css/helix.css), [`assets/css/helix-fonts.css`](file:///c:/Cedi/bantayaso/assets/css/helix-fonts.css), and SVGs ([`assets/img/h3lix-emblem.svg`](file:///c:/Cedi/bantayaso/assets/img/h3lix-emblem.svg), [`assets/img/s3curity-favicon.svg`](file:///c:/Cedi/bantayaso/assets/img/s3curity-favicon.svg)).
- [x] **Design Token Harmonization:** Updated [`assets/css/app.css`](file:///c:/Cedi/bantayaso/assets/css/app.css) to import H3LIX fonts/styles and aligned all surfaces (`#04070d`, `#070c15`, `#0a1220`, `#0d1626`), neon cyan accents (`#00e5ff`), mint (`#39ffb4`), and angular clip geometry (`polygon(8px 0, 100% 0, ...)`).
- [x] **Preserved Internal Logic & Tests:** Preserved 100% of internal PHP/JS logic, CSRF handlers, endpoints, event listeners, and verified that [`scripts/verify_watchlist.js`](file:///c:/Cedi/bantayaso/scripts/verify_watchlist.js) passes all 5/5 assertions.
- [x] **Cyber Auth Upgrade:** Styled [`login.php`](file:///c:/Cedi/bantayaso/login.php) with the authentic H3LIX `.hx-auth` HUD panel and cyan corner brackets while preserving existing authentication flow.

### F. Watchlist Table Alignment, Live Site Telemetry, and Two-Way Sync Engine Fixes
- [x] **Local Watchlist Table Alignment:**
  - Enforced `table-layout: fixed !important;` on `.watchlist-grid` / `table.watchlist-grid` in [`assets/css/app.css`](file:///c:/Cedi/bantayaso/assets/css/app.css).
  - Explicit column width percentages: Col 1 (`35%`, `text-align: left`), Col 2 (`20%`, `text-align: left`), Col 3 (`25%`, `text-align: left`), Col 4 (`20%`, `text-align: right`).
  - Added text truncation (`ellipsis`) to `.target-name` and `.target-url` to prevent wide URLs from expanding cells and distorting column headers.
  - Aligned ACTION buttons with `justify-content: flex-end` matching `text-align: right` on the header.
- [x] **Live Site Telemetry & Watchlist Rows Fix:**
  - **Root Cause 1:** `api/watchlist.php` had strict `Auth::requireLogin()` blocking unauthenticated visitors with 401 Unauthorized, aborting frontend `loadWatchlist()` on `bantayaso.s3curity.net`.
  - **Fix 1:** Updated [`api/watchlist.php`](file:///c:/Cedi/bantayaso/api/watchlist.php) to permit public `GET` queries for active targets (matching `/api/state.php`), keeping write methods (`POST`/`PUT`/`DELETE`) strictly protected by `Auth::requireAdmin()` and CSRF.
  - **Root Cause 2:** In [`assets/js/app.js`](file:///c:/Cedi/bantayaso/assets/js/app.js), `document.getElementById('watchlistGrid')` threw `TypeError` on pages without the card grid, aborting KPI card updates.
  - **Fix 2:** Added null-checks, unified `loadWatchlist()` to read `/api/state` or `api/watchlist.php`, and invoked `BantayAsoUI.renderWatchlist()`.
  - **Pre-rendering:** Added server-side SQLite pre-rendering in [`index.php`](file:///c:/Cedi/bantayaso/index.php) so initial HTML serves actual numbers (`5`, `2`, `3`) and pre-rendered rows without relying on client JS hydration.
- [x] **Two-Way Sync Engine (Virtualmin-to-Local Pull & Push):**
  - Updated both [`sync.ps1`](file:///c:/Cedi/bantayaso/sync.ps1) and [`sync.sh`](file:///c:/Cedi/bantayaso/sync.sh) with `--no-perms --no-owner --no-group` on pulls to eliminate NTFS/DrvFs/9P permission attribute errors.
  - Fixed `.env` parsing in `sync.sh` to prevent `\r` and empty comment parsing failures.
  - Handled rsync exit code 24 ("vanished source files") cleanly as success.
  - Validated both `sync.ps1 -Direction Pull` and `sync.sh pull` with live verification.
- [x] **H3LIX HUD Styling:**
  - Added dark slate panels (`#0a1220`), cyan corner brackets (`::before`/`::after` with `#00e5ff`), sharp cyan buttons (`border-radius: 3px; font-family: monospace; text-transform: uppercase`), and horizontal bordered KPI strips.

### G. Frontend Cyber Toast Notification System (Decoupled from OS/SSH)
- [x] **Decoupled from PowerShell & OS-Level Alerts:** Permanently removed `Send-Notification.ps1` and `notify_ssh.ps1`. All notifications are now 100% native client-side JavaScript and CSS.
- [x] **Bottom-Right Viewport Anchor:** `#toastContainer` / `.toast-container` is anchored fixed at `bottom: 24px; right: 24px; z-index: 99999;` with smooth slide/fade micro-animations.
- [x] **Dark Cyber Styling:** Styled with authentic `#0A0E17` background, monospace typography (`JetBrains Mono` / `Consolas`), and glowing status borders (Cyan for info/progress, Emerald for success, Rose for errors, Amber for warnings).
- [x] **Dynamic Stateful Controller:** `App.showToast()` returns an active controller handle with `.update()`, `.done()`, and `.error()`.
- [x] **Action-Driven Triggers:**
  - **"Sync Files":** Triggers on `#btnSyncOnline` starting with `"Syncing in progress... [initiating rsync & state synchronization]"`, dynamically updating in-place to `"✓ Bidirectional sync completed in lockstep!"` or detailed error messages.
  - **Target "Scan":** Triggers when initiating `"NEW QA"` or card scan, updating dynamically to `"✓ Scan complete! Verdict: [HEALTHY/etc.]"` or error details.
  - **"Bantay-Aso sync check":** Triggers when toggling the header test button (`btnToggleSyncHeader`), or verifying single offline file SHA-256 digests against master.
- [x] **Automated QA Suite:** Verified via [`scripts/test_toast_notifications.js`](file:///c:/Cedi/bantayaso/scripts/test_toast_notifications.js) passing all assertions.

---

## 3. Current Live Target Parity Baseline (Snapshot)

| ID | Target Name | Live URL | Last Status | Latency | Snapshots | Verdict |
|---|---|---|---|---|---|---|
| **1** | S3curity.net Defense Hub | `https://s3curity.net` | Pending | `--` | 0 | `PENDING` |
| **2** | Hard N' Heavy | `https://hardnheavyqatar.com/` | 200 OK | 139 ms | 3 | `HEALTHY` |
| **3** | S3curity H3LIX HRMS Portal | `https://hrms-dev.s3curity.net/` | Pending | `--` | 7 | `PENDING` |
| **4** | Bantay Aso Production Node | `https://bantayaso.s3curity.net/` | Pending | `--` | 0 | `PENDING` |
| **5** | East West International Qatar | `https://baalbaki.me/ewi7` | 200 OK | 147 ms | 1 | `SHIP WITH FIXES` |

---

## 4. Pending Remote VPS Setup Checklist (For User / Google Argon)

1. **SSH Key Deployment:**
   - Generate key locally: `ssh-keygen -t ed25519 -C "bantayaso-deploy" -f ~/.ssh/id_ed25519_bantayaso -N ""`
   - Add public key to VPS: `~/.ssh/authorized_keys` (permissions: `700 ~/.ssh`, `600 ~/.ssh/authorized_keys`).
2. **Git Bare Repo with `post-receive` Hook (Optional Git Workflow):**
   - Location: `~/repos/bantayaso.git`
   - Target Directory: `/home/<user>/public_html/` (or sub-server path)
   - Excludes `/data/*.sqlite*` during checkout/rsync.
3. **Run CLI Hash Migration on Live Server:**
   - Execute once on Virtualmin: `php scripts/migrate_hashes.php` to immediately convert any plaintext users to Argon2id.
