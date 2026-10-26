# Bantay Aso — Developer & Operations Instructions

> [!CAUTION]
> **PROTOCOL NOTICE (file:///):** Browsers block `file:///` pages from executing backend rsync/SSH scripts and CORS state APIs. Launch `run-local.bat` or run `python server.py` to connect.

---

## 1. Quick Start (Local Development)

### Preferred Method (Python Dev Server)
Double-click [`run-local.bat`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/run-local.bat) or run from PowerShell:
```powershell
python server.py
```
This starts the local parity hub on `http://127.0.0.1:8000` and automatically opens:
👉 **[http://127.0.0.1:8000/qa-dashboard.html](http://127.0.0.1:8000/qa-dashboard.html)**

### Node.js Alternative
```bash
node server.js
```

### PHP Built-in Server Alternative
```powershell
.\run-local.ps1
```

---

## 2. Environment Configuration (`.env`)

Never commit `.env` to Git. Ensure your [`.env`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.env) contains accurate credentials:

```ini
# Live Node Credentials
REMOTE_BASE_URL=https://bantayaso.s3curity.net
REMOTE_ADMIN_USER=admin
REMOTE_ADMIN_PASS=your_strong_password_here

# Virtualmin SSH Settings
VIRTUALMIN_HOST=bantayaso.s3curity.net
VIRTUALMIN_SSH_PORT=22
VIRTUALMIN_USER=aso
VIRTUALMIN_REMOTE_PATH=/project/app/
SSH_KEY_PATH=~/.ssh/id_ed25519_bantayaso

# Local Server Settings
LOCAL_SERVER_HOST=127.0.0.1
LOCAL_SERVER_PORT=8000
```

---

## 3. Two-Way File & State Synchronization

### A. One-Click UI Sync
1. Open [http://127.0.0.1:8000/qa-dashboard.html](http://127.0.0.1:8000/qa-dashboard.html).
2. Click **"⚡ Sync Files"** in the top navigation bar or **"⚡ Synchronize Now"**.
3. The dashboard calls the backend dev server, executes the rsync/SSH sync and REST SQLite data parity pass, streams live console output, and dynamically refreshes all target cards.

### B. CLI File Synchronization (`sync.ps1` & `sync.sh`)
- **Dry-Run Preview (Safe check):**
  ```powershell
  .\sync.ps1 -DryRun
  ```
- **Bidirectional Sync (Code only, preserving live database):**
  ```powershell
  .\sync.ps1 -Direction Both
  ```
- **Pull Remote Changes Down:**
  ```powershell
  .\sync.ps1 -Direction Pull
  ```
- **Push Local Changes Up:**
  ```powershell
  .\sync.ps1 -Direction Push
  ```
- **Full Sync Including SQLite Database:**
  ```powershell
  .\sync.ps1 -Direction Both -IncludeDb
  ```

---

## 4. Argon2id Security & Migration

Bantay Aso has been upgraded to OWASP-recommended **Argon2id** password hashing.
- Configuration: [`config/security.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/config/security.php)
- **Automatic Upgrade:** Any user logging in with an existing bcrypt (`$2y$`) or plaintext password is automatically rehashed to `$argon2id$v=19$` upon successful authentication.
- **Batch CLI Migration:** Run on your server or local machine to convert any legacy plaintext rows immediately:
  ```bash
  php scripts/migrate_hashes.php
  ```

---

## 5. Mitigating OneDrive File-Locking Conflicts

If your working folder is located inside `OneDrive\Documents\Desktop\bantayaso\`:
1. **WSL RAM Disk Staging:** `sync.ps1` and `sync.sh` have been configured with `--temp-dir=/tmp` and `--no-perms --no-owner --no-group` to bypass OneDrive file-rename lock errors.
2. **Move Working Tree (Optional for heavy workflows):** If OneDrive continues locking files, relocate your working tree to `C:\dev\bantayaso` outside the OneDrive directory.

---

## 6. Changelog & Project Manifest

| File | Status | Description |
|---|---|---|
| [`.env`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.env) | **Created** | Private environment variables & credentials. |
| [`.env.example`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.env.example) | **Created** | Sanitized environment template for version control. |
| [`.gitignore`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.gitignore) | **Created** | Excludes `.env`, SQLite WAL/journal files, caches, logs. |
| [`config/security.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/config/security.php) | **Created** | Defines `ARGON2ID_OPTIONS` (64MB memory, 4 time cost, 2 threads). |
| [`scripts/migrate_hashes.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/scripts/migrate_hashes.php) | **Created** | CLI batch migration script for password hashes. |
| [`includes/auth_middleware.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/includes/auth_middleware.php) | **Updated** | Adds transparent Argon2id rehash on login and timing protection. |
| [`api/state.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/api/state.php) | **Created** | Single Source of Truth state API for lockstep dashboard parity. |
| [`.htaccess`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/.htaccess) | **Created** | Virtualmin/Apache routing for `/api/state` and `.sqlite` file denial. |
| [`sync.ps1`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/sync.ps1) | **Created** | Two-way SSH rsync script with WSL bridge & OneDrive mitigations. |
| [`sync.sh`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/sync.sh) | **Created** | Bash alternative for Linux/WSL environments. |
| [`server.py`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/server.py) | **Created** | Zero-dependency local dev server powering the dashboard. |
| [`server.js`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/server.js) | **Created** | Node.js dev server alternative. |
| [`qa-dashboard.html`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/qa-dashboard.html) | **Updated** | Masked credentials, dynamic `/api/state` cards, and wired sync button. |
| [`index.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/index.php) | **Updated** | Masked plaintext credentials. |
| [`login.php`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/login.php) | **Updated** | Masked plaintext credentials. |
| [`sync_online.py`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/sync_online.py) | **Updated** | Refactored to read credentials dynamically from `.env`. |
| [`run-local.bat`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/run-local.bat) | **Updated** | Launches local dev server and opens dashboard in browser. |
| [`assets/js/app.js`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/assets/js/app.js) | **Updated** | Added interactive header toggle for sync testing with localStorage persistence. |
| [`AIProjectConvo.md`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/AIProjectConvo.md) | **Created** | Context ledger for AI continuity with zero repetitions. |
| [`Instructions.md`](file:///c:/Users/visita/OneDrive/Documents/Desktop/bantayaso/Instructions.md) | **Created** | Developer run instructions and operations guide. |
