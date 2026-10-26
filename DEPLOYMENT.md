# 🚀 DevOps Deployment & Operations Guide
## "Bantay Aso" — Continuous QA & Semantic Changelog Platform

> **Target Environment:** Standard Linux Virtualmin VPS (LAMP/LEMP) with Apache or Nginx, PHP 8.x, and SQLite (default) or MySQL/MariaDB.  
> **Key Constraint:** Zero server-side shell dependencies. No `npm build`, `composer install`, Python daemons, or terminal commands are required on the production web server.

---

## 1. Prerequisites & PHP Extensions

Verify that the target Virtualmin virtual server has PHP 8.1+ enabled with the following standard extensions:

```bash
# Check loaded extensions
php -m | grep -iE 'curl|pdo|sqlite3|mbstring|dom|openssl|session'
```

Required packages on Ubuntu/Debian Virtualmin nodes:
```bash
sudo apt-get install -y php8.x-curl php8.x-sqlite3 php8.x-mbstring php8.x-xml
```

---

## 2. Drop-in Deployment Steps

1. **Upload Codebase:**
   Copy the entire contents of the `bantayaso` directory into your virtual server's document root (typically `/home/<username>/public_html/`).

2. **Set Ownership & Permissions:**
   Ensure the web server user (`www-data` or the virtual server domain owner) owns the directory and has write access to `data/`:
   ```bash
   chown -R <virtual_server_user>:<virtual_server_group> /home/<virtual_server>/public_html
   chmod -R 755 /home/<virtual_server>/public_html
   chmod -R 775 /home/<virtual_server>/public_html/data
   ```

3. **Verify Apache / Nginx Configuration:**
   * **Apache:** Ensure `AllowOverride All` is set so `data/.htaccess` prevents direct browser downloads of the SQLite database.
   * **Nginx:** If using Nginx as the primary web server instead of Apache, add the following block inside the `server { ... }` configuration:
     ```nginx
     # Block direct access to database and sensitive directories
     location ^~ /data/ {
         deny all;
         return 404;
     }
     location ^~ /includes/ {
         deny all;
         return 404;
     }
     ```

4. **Initialize Platform:**
   Navigate to `https://your-domain.com/` in your browser. The SQLite database schema, initial seed accounts, and watchlist entries will auto-initialize on the very first HTTP request.

---

## 3. Database Configuration (`config.php`)

By default, the platform runs in zero-config **SQLite** mode (`data/bantayaso.sqlite`).

If your team prefers **Virtualmin MySQL / MariaDB**:
1. Create a MySQL database and user in Virtualmin (*Virtualmin ➔ Edit Databases ➔ Create Database*).
2. Edit `config.php`:
   ```php
   define('DB_TYPE', 'mysql');
   define('MYSQL_HOST', '127.0.0.1');
   define('MYSQL_PORT', 3306);
   define('MYSQL_DATABASE', 'your_database_name');
   define('MYSQL_USERNAME', 'your_database_user');
   define('MYSQL_PASSWORD', 'your_strong_password');
   ```

---

## 4. Default Credentials & Handover

| Role | Username | Default Password | Permissions |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin` | `@@22162613Bantayaso` | Watchlist CRUD, "NEW QA" triggers, User CRUD, Engine configuration |
| **Viewer** | `viewer` | `Viewer@BantayAso2026!` | Read-only inspection of targets, snapshots, diffs, and changelogs |

> [!IMPORTANT]
> **Production Target & Credentials:** The primary synchronous production node is configured at `https://bantayaso.s3curity.net/` with initial admin credentials: `admin` / `@@22162613Bantayaso`. Sign in and verify your access.

---

## 5. Antigravity Agent (AGY CLI) Configuration

Bantay Aso is decoupled from local LM Studio instances and defaults to its built-in, zero-infrastructure **ECC Sentinel QA Engine**.

Once deployed, your team can connect an **Antigravity Agent** via the **AGY CLI**:

1. **Antigravity QA Skill Included:**
   The repository includes a ready-to-run Antigravity Skill located at:
   [`.agents/skills/bantayaso-qa/SKILL.md`](.agents/skills/bantayaso-qa/SKILL.md)

2. **Configure via UI:**
   * Sign in as **Admin** ➔ click **⚙ Engine** in the top navigation.
   * Under **Active QA Analysis Engine**, select **Antigravity Agent (AGY CLI Sidecar / Webhook)**.
   * Enter the Antigravity Agent endpoint (e.g., `http://127.0.0.1:8765/agent` or your internal agent gateway).
   * Click **Test Active Engine Connection** and click **Save Settings**.

---

## 6. Pre-Flight Verification Checklist

- [ ] `https://your-domain.com/` loads dashboard and hero banner.
- [ ] Admin login functions and distributes CSRF token.
- [ ] Direct HTTP request to `https://your-domain.com/data/bantayaso.sqlite` returns `403 Forbidden` or `404 Not Found`.
- [ ] Clicking **"NEW QA"** on any watchlist target successfully runs all 4 pipeline steps and logs a clean snapshot.
- [ ] Date comparison selectors populate and render the side-by-side diff.
- [ ] User profile modal allows changing password.
- [ ] User management modal allows creating and deleting user accounts.

---

## 7. Autonomous Watchdog Worker (Cron Job & Webhooks)

To enable scheduled 24/7 background monitoring, SSL certificate expiration tracking, and autonomous defacement detection:

1. **Webhook Notifications (Optional):**
   Configure your Discord, Slack, or generic JSON webhook URL in `.env`:
   ```bash
   WATCHDOG_WEBHOOK_URL="https://discord.com/api/webhooks/..."
   ```

2. **Crontab Configuration:**
   Add a standard 1-minute cron job in Virtualmin (*Webmin ➔ System ➔ Scheduled Cron Jobs*) or via `crontab -e`:
   ```bash
   # Autonomous Watchdog Worker (handles locking, interval scheduling, SSL inspection & defacement sentinel)
   * * * * * /usr/bin/php /home/<virtual_server>/public_html/scripts/watchdog_worker.php >> /home/<virtual_server>/public_html/data/watchdog_cron.log 2>&1
   ```

3. **CLI Worker Testing & Options:**
   Test worker execution from the command line:
   ```bash
   # Dry-run inspection pass without persisting snapshots
   php scripts/watchdog_worker.php --dry-run

   # Force immediate check on all targets regardless of last check interval
   php scripts/watchdog_worker.php --force

   # Limit inspection batch to N targets
   php scripts/watchdog_worker.php --limit=5
   ```

---

## 8. Enterprise Hardening & PHP2Shell Defense (CVE-2026-109618 / CVE-2026-109619)

Bantay Aso incorporates layered defense-in-depth mitigations against Remote Code Execution (RCE), PHP-to-Shell escalation, Local File Inclusion (LFI), and parser memory-corruption exploits.

### 8.1 Patched PHP Runtime Versions
Ensure the server runs a patched PHP minor release or administrative limit:
- **PHP 8.5:** Version **8.5.12** or later
- **PHP 8.4:** Version **8.4.27** or later
- **PHP 8.3:** Version **8.3.36** or later
- **PHP 8.2:** Version **8.2.35** or later

### 8.2 Hardened `php.ini` Configuration
A drop-in configuration snippet is provided at [`config/php-hardened.ini`](config/php-hardened.ini). In your environment's PHP configuration (`/etc/php/8.x/fpm/php.ini` or `/etc/php/8.x/apache2/php.ini`):
```ini
; Neutralize controllable OOM exploits
memory_limit = -1
post_max_size = 16M
max_input_nesting_level = 32
max_multipart_body_parts = 100

; Neutralize stale global state & engine corruption vectors
session.upload_progress.enabled = 0
fatal_error_backtraces = 0

; Disable dangerous system execution functions
disable_functions = exec,shell_exec,system,passthru,popen,proc_open,pcntl_exec,eval,dl,show_source,phpinfo,posix_getpwuid,posix_kill,syslog

; Disable unneeded file upload processing
file_uploads = Off
allow_url_include = Off
allow_url_fopen = Off
```

### 8.3 Web Server Hardening & Multipart Blocking
- **Apache (.htaccess):** The included [`.htaccess`](.htaccess) automatically blocks any incoming request with `Content-Type: multipart/form-data`, denies access to internal subdirectories (`data/`, `dumps/`, `config/`, `includes/`, `scripts/`, `storage/`), and disables PHP execution engines in static/data directories. Reference: [`config/apache-hardened.conf`](config/apache-hardened.conf).
- **Nginx:** Deploy the complete production server block configuration from [`config/nginx-hardened.conf`](config/nginx-hardened.conf), which blocks multipart payloads with HTTP 415 and prevents PHP FastCGI execution in non-script locations.

### 8.4 Secure Upload Handling (`SecureUploader`)
If file uploads are ever enabled for custom plugins or avatars, use the hardened [`includes/uploader.php`](includes/uploader.php) module. It enforces:
1. Strict `finfo` magic-byte MIME whitelisting (PNG, JPEG, WebP, PDF, TXT, JSON).
2. Deep inspection rejecting embedded PHP tags (`<?php`, `<?=`).
3. Collision-free cryptographic hash renaming (`bin2hex(random_bytes(16))`).
4. Isolation entirely outside the web root (`storage/uploads/`) with script execution denied.

### 8.5 Diagnostic Telemetry Verification
Sign in as an administrator and query `GET /api/doctor` to verify your server's security posture:
```json
{
  "cve_2026_109618_mitigated": true,
  "cve_2026_109619_mitigated": true,
  "php_patched": true,
  "memory_limit_hardened": true,
  "session_upload_progress_disabled": true,
  "fatal_error_backtraces_disabled": true,
  "multipart_shield_active": true
}
```

