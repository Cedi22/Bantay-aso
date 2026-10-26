<?php
/**
 * Bantay Aso — Database Connection & Auto-Migrator
 * Native PDO implementation supporting SQLite (default) and MySQL.
 * Strictly adheres to ECC PHP Security rules (prepared statements, no raw string interpolation).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/security.php';

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO connection, auto-initializing tables and seeds if needed.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
            self::initSchema(self::$instance);
        }
        return self::$instance;
    }

    private static function createConnection(): PDO
    {
        try {
            if (DB_TYPE === 'sqlite') {
                $pdo = new PDO('sqlite:' . SQLITE_FILE);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                // Enable foreign keys in SQLite
                $pdo->exec('PRAGMA foreign_keys = ON;');
                $pdo->exec('PRAGMA journal_mode = WAL;');
                return $pdo;
            } elseif (DB_TYPE === 'mysql') {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    MYSQL_HOST,
                    MYSQL_PORT,
                    MYSQL_DATABASE,
                    MYSQL_CHARSET
                );
                $pdo = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return $pdo;
            } else {
                throw new RuntimeException('Unsupported DB_TYPE configured: ' . DB_TYPE);
            }
        } catch (PDOException $e) {
            // Log securely without leaking credentials
            error_log('Bantay Aso DB Connection Failure: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed. Ensure data directory is writable or check config.php.',
            ]);
            exit;
        }
    }

    /**
     * Auto-run schema migration and initial data seeding.
     */
    private static function initSchema(PDO $pdo): void
    {
        // 1. Users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY " . (DB_TYPE === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                username VARCHAR(64) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(191) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'viewer',
                must_change_password INTEGER DEFAULT 0,
                created_at DATETIME NOT NULL,
                last_login_at DATETIME NULL
            )
        ");

        // Schema migration: ensure must_change_password column exists on existing tables
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER DEFAULT 0");
        } catch (Throwable $e) {
            // Column already exists
        }

        // 2. Watchlist table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS watchlist (
                id INTEGER PRIMARY KEY " . (DB_TYPE === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                name VARCHAR(191) NOT NULL,
                url VARCHAR(512) NOT NULL,
                description TEXT NULL,
                check_interval_mins INTEGER DEFAULT 60,
                is_active INTEGER DEFAULT 1,
                last_status_code INTEGER NULL,
                last_response_time_ms INTEGER NULL,
                last_checked_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ");

        // 3. Snapshots table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS snapshots (
                id INTEGER PRIMARY KEY " . (DB_TYPE === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                watchlist_id INTEGER NOT NULL,
                status_code INTEGER NOT NULL,
                response_time_ms INTEGER NOT NULL,
                content_type VARCHAR(128) NULL,
                content_hash VARCHAR(64) NOT NULL,
                raw_headers TEXT NULL,
                security_headers_json TEXT NULL,
                cleaned_content TEXT NULL,
                raw_content TEXT NULL,
                page_title VARCHAR(255) NULL,
                meta_description TEXT NULL,
                dom_metrics_json TEXT NULL,
                triggered_by VARCHAR(64) DEFAULT 'adminmoe',
                created_at DATETIME NOT NULL,
                FOREIGN KEY (watchlist_id) REFERENCES watchlist(id) ON DELETE CASCADE
            )
        ");

        // 4. Comparisons & Semantic Changelogs table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comparisons (
                id INTEGER PRIMARY KEY " . (DB_TYPE === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                watchlist_id INTEGER NOT NULL,
                baseline_snapshot_id INTEGER NOT NULL,
                comparison_snapshot_id INTEGER NOT NULL,
                lines_added INTEGER DEFAULT 0,
                lines_removed INTEGER DEFAULT 0,
                lines_unchanged INTEGER DEFAULT 0,
                raw_diff TEXT NULL,
                semantic_changelog TEXT NULL,
                qa_verdict VARCHAR(32) DEFAULT 'INCONCLUSIVE',
                model_used VARCHAR(128) NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (watchlist_id) REFERENCES watchlist(id) ON DELETE CASCADE,
                FOREIGN KEY (baseline_snapshot_id) REFERENCES snapshots(id) ON DELETE CASCADE,
                FOREIGN KEY (comparison_snapshot_id) REFERENCES snapshots(id) ON DELETE CASCADE
            )
        ");

        // 5. System settings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key_name VARCHAR(64) PRIMARY KEY,
                value_text TEXT NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ");

        // 6. Safe idempotent watchdog schema migration & rate limit tables
        self::migrateWatchdogSchema($pdo);

        // Seed initial data if tables are empty
        self::seedInitialData($pdo);
    }

    /**
     * Idempotent migration for watchdog columns, login attempts, and incidents tables.
     */
    private static function migrateWatchdogSchema(PDO $pdo): void
    {
        // 1. Fetch existing columns on watchlist
        $existingCols = [];
        try {
            if (DB_TYPE === 'sqlite') {
                $stmt = $pdo->query('PRAGMA table_info(watchlist)');
                while ($row = $stmt->fetch()) {
                    $existingCols[strtolower($row['name'])] = true;
                }
            } else {
                $stmt = $pdo->query('SHOW COLUMNS FROM watchlist');
                while ($row = $stmt->fetch()) {
                    $existingCols[strtolower($row['Field'])] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('Bantay Aso table info inspection notice: ' . $e->getMessage());
        }

        $colsToAdd = [
            'check_interval_mins' => 'INTEGER DEFAULT 15',
            'ssl_expires_at' => 'DATETIME NULL',
            'ssl_days_left' => 'INTEGER NULL',
            'ssl_issuer' => 'VARCHAR(128) NULL',
            'ssl_status' => "VARCHAR(32) DEFAULT 'UNKNOWN'",
            'uptime_state' => "VARCHAR(20) DEFAULT 'UP'",
            'consecutive_failures' => 'INTEGER DEFAULT 0',
            'dom_skeleton_hash' => 'VARCHAR(64) NULL',
            'security_score' => 'INTEGER DEFAULT 100',
            'security_grade' => "VARCHAR(4) DEFAULT 'A'",
            'vuln_report_json' => 'TEXT NULL',
        ];

        foreach ($colsToAdd as $col => $typeDef) {
            if (!isset($existingCols[strtolower($col)])) {
                try {
                    $pdo->exec("ALTER TABLE watchlist ADD COLUMN {$col} {$typeDef}");
                } catch (Throwable $e) {
                    error_log("Schema migration notice on watchlist.{$col}: " . $e->getMessage());
                }
            }
        }

        // 1b. Fetch existing columns on snapshots
        $existingSnapCols = [];
        try {
            if (DB_TYPE === 'sqlite') {
                $stmtSnap = $pdo->query('PRAGMA table_info(snapshots)');
                while ($row = $stmtSnap->fetch()) {
                    $existingSnapCols[strtolower($row['name'])] = true;
                }
            } else {
                $stmtSnap = $pdo->query('SHOW COLUMNS FROM snapshots');
                while ($row = $stmtSnap->fetch()) {
                    $existingSnapCols[strtolower($row['Field'])] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('Bantay Aso snapshots table info inspection notice: ' . $e->getMessage());
        }

        $snapColsToAdd = [
            'security_score' => 'INTEGER DEFAULT 100',
            'security_grade' => "VARCHAR(4) DEFAULT 'A'",
            'vuln_report_json' => 'TEXT NULL',
            'triggered_by' => "VARCHAR(64) DEFAULT 'adminmoe'",
        ];

        foreach ($snapColsToAdd as $col => $typeDef) {
            if (!isset($existingSnapCols[strtolower($col)])) {
                try {
                    $pdo->exec("ALTER TABLE snapshots ADD COLUMN {$col} {$typeDef}");
                } catch (Throwable $e) {
                    error_log("Schema migration notice on snapshots.{$col}: " . $e->getMessage());
                }
            }
        }

        // 1c. Retroactive DB Fix: Rename October 26 raw title snapshot to v1.3 Security Sentinel
        try {
            $pdo->exec("
                UPDATE snapshots 
                SET page_title = 'Bantay Aso Production Node (v1.3 Security Sentinel)'
                WHERE (watchlist_id = 4 OR watchlist_id IN (SELECT id FROM watchlist WHERE url LIKE '%bantayaso%'))
                  AND (page_title LIKE '%Bantay Aso%Continuous QA%' OR page_title LIKE '%Bantay Aso — Continuous QA%')
            ");
        } catch (Throwable $e) {
            error_log('Bantay Aso snapshot version migration notice: ' . $e->getMessage());
        }

        $autoInc = (DB_TYPE === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT');

        // 2. Login attempts table for rate limiting
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY {$autoInc},
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(64) NOT NULL,
                attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        if (DB_TYPE === 'sqlite') {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts(ip_address, attempted_at)");
        }

        // 3. Incidents table for uptime outages, SSL expiries, and defacements
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS incidents (
                id INTEGER PRIMARY KEY {$autoInc},
                watchlist_id INTEGER NOT NULL,
                incident_type VARCHAR(32) NOT NULL,
                severity VARCHAR(16) NOT NULL,
                details TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                FOREIGN KEY (watchlist_id) REFERENCES watchlist(id) ON DELETE CASCADE
            )
        ");
        if (DB_TYPE === 'sqlite') {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_incidents_target_unresolved ON incidents(watchlist_id, incident_type, resolved_at)");
        }
    }

    /**
     * Generate semantic snapshot version label.
     * E.g. parses previous "Bantay Aso Production Node (v1.2 Live Sentinel)" or v1.3 and produces "Bantay Aso Production Node (v1.4 Security Sentinel)"
     *
     * @param PDO $pdo Active PDO connection
     * @param int $watchlistId Target Watchlist ID
     * @param string $targetName Target name from watchlist record
     * @param string|null $customCodename Optional custom version tag / codename
     * @return string Formatted version label
     */
    public static function generateNextSnapshotVersionLabel(PDO $pdo, int $watchlistId, string $targetName, ?string $customCodename = null): string
    {
        $stmt = $pdo->prepare('
            SELECT page_title 
            FROM snapshots 
            WHERE watchlist_id = ? 
            ORDER BY id DESC 
            LIMIT 10
        ');
        $stmt->execute([$watchlistId]);
        $titles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $lastVersion = null;
        foreach ($titles as $title) {
            $t = (string)$title;
            if (preg_match('/\(v(\d+\.\d+)[^)]*\)/i', $t, $m)) {
                $lastVersion = $m[1];
                break;
            } elseif (preg_match('/v(\d+\.\d+)/i', $t, $m)) {
                $lastVersion = $m[1];
                break;
            }
        }

        if ($lastVersion !== null) {
            $parts = explode('.', $lastVersion);
            $major = (int)$parts[0];
            $minor = (int)($parts[1] ?? 0) + 1;
            $nextVersion = "v{$major}.{$minor}";
        } else {
            $nextVersion = 'v1.0';
        }

        $codename = trim((string)($customCodename ?? ''));
        if ($codename === '') {
            $codename = 'Security Sentinel';
        }

        return "{$targetName} ({$nextVersion} {$codename})";
    }

    private static function seedInitialData(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');

        // Check if Admin exists, create or synchronize password hash
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([INITIAL_ADMIN_USERNAME]);
        $adminRow = $stmt->fetch();
        $adminHash = !empty(INITIAL_ADMIN_PASSWORD)
            ? password_hash(INITIAL_ADMIN_PASSWORD, DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS)
            : FALLBACK_ADMIN_HASH;
        if (!$adminRow) {
            $ins = $pdo->prepare('INSERT INTO users (username, password_hash, email, role, created_at) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([
                INITIAL_ADMIN_USERNAME,
                $adminHash,
                INITIAL_ADMIN_EMAIL,
                'admin',
                $now,
            ]);
        } else {
            // Synchronize password hash with configured INITIAL_ADMIN_PASSWORD if provided
            if (!empty(INITIAL_ADMIN_PASSWORD) && !password_verify(INITIAL_ADMIN_PASSWORD, $adminRow['password_hash'])) {
                $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $upd->execute([$adminHash, $adminRow['id']]);
            }
        }

        // Check if Admin user adminmoe exists, create or synchronize password hash
        if (defined('INITIAL_ADMINMOE_USERNAME')) {
            $moeStmt = $pdo->prepare('SELECT id, password_hash, must_change_password FROM users WHERE username = ?');
            $moeStmt->execute([INITIAL_ADMINMOE_USERNAME]);
            $moeRow = $moeStmt->fetch();
            $moeHash = password_hash(INITIAL_ADMINMOE_PASSWORD, DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS);
            if (!$moeRow) {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, email, role, must_change_password, created_at) VALUES (?, ?, ?, ?, 1, ?)');
                $ins->execute([
                    INITIAL_ADMINMOE_USERNAME,
                    $moeHash,
                    INITIAL_ADMINMOE_EMAIL,
                    'admin',
                    $now,
                ]);
            }
        }

        // Check if Standard Viewer user exists, create or synchronize password hash
        $vStmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $vStmt->execute([INITIAL_VIEWER_USERNAME]);
        $viewerRow = $vStmt->fetch();
        $viewerHash = !empty(INITIAL_VIEWER_PASSWORD)
            ? password_hash(INITIAL_VIEWER_PASSWORD, DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS)
            : FALLBACK_VIEWER_HASH;
        if (!$viewerRow) {
            $ins = $pdo->prepare('INSERT INTO users (username, password_hash, email, role, created_at) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([
                INITIAL_VIEWER_USERNAME,
                $viewerHash,
                INITIAL_VIEWER_EMAIL,
                'viewer',
                $now,
            ]);
        } else {
            // Synchronize viewer password hash if needed
            if (!empty(INITIAL_VIEWER_PASSWORD) && !password_verify(INITIAL_VIEWER_PASSWORD, $viewerRow['password_hash'])) {
                $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $upd->execute([$viewerHash, $viewerRow['id']]);
            }
        }

        // Check if initial watchlist item exists for https://bantayaso.s3curity.net/
        $wStmt = $pdo->prepare('SELECT COUNT(*) FROM watchlist WHERE url = ?');
        $wStmt->execute([INITIAL_WATCHLIST_URL]);
        if ((int)$wStmt->fetchColumn() === 0) {
            $wIns = $pdo->prepare('INSERT INTO watchlist (name, url, description, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)');
            $wIns->execute([
                INITIAL_WATCHLIST_NAME,
                INITIAL_WATCHLIST_URL,
                'Continuous Security QA & Surface Tracking synchronous production node.',
                $now,
                $now,
            ]);
        }

        // Seed default settings if missing
        $defaultSettings = [
            'analysis_mode' => defined('DEFAULT_ANALYSIS_MODE') ? DEFAULT_ANALYSIS_MODE : 'gemini_cli',
            'agy_endpoint' => defined('DEFAULT_AGY_ENDPOINT') ? DEFAULT_AGY_ENDPOINT : 'http://127.0.0.1:8765/agent',
            'agy_api_key' => defined('DEFAULT_AGY_API_KEY') ? DEFAULT_AGY_API_KEY : '',
            'llm_timeout' => (string)(defined('DEFAULT_ANALYSIS_TIMEOUT') ? DEFAULT_ANALYSIS_TIMEOUT : 30),
            'system_name' => APP_NAME . ' Continuous QA Monitor',
        ];

        $setCheck = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE key_name = ?');
        $setIns = $pdo->prepare('INSERT INTO settings (key_name, value_text, updated_at) VALUES (?, ?, ?)');

        foreach ($defaultSettings as $k => $v) {
            $setCheck->execute([$k]);
            if ((int)$setCheck->fetchColumn() === 0) {
                $setIns->execute([$k, $v, $now]);
            }
        }
    }
}
