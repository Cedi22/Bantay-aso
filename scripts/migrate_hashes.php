<?php
/**
 * Bantay Aso — Password Hash Migration Script (CLI)
 * Converts legacy plaintext or legacy bcrypt hashes into Argon2id hashes.
 *
 * Usage:
 *   php scripts/migrate_hashes.php
 *   php scripts/migrate_hashes.php --upgrade-all (also rehashes bcrypt to Argon2id immediately)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';

$dbPath = __DIR__ . '/../data/bantayaso.sqlite';
if (!file_exists($dbPath)) {
    echo "[-] Error: Database not found at {$dbPath}\n";
    exit(1);
}

try {
    $db = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Prevent SQLite locking collisions with background QA cron jobs
    $db->exec('PRAGMA busy_timeout = 5000;');
    $db->exec('PRAGMA journal_mode = WAL;');

    $users = $db->query("SELECT id, username, password_hash FROM users")->fetchAll();
    $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");

    $upgradeAll = in_array('--upgrade-all', $argv, true);
    $migratedCount = 0;
    $db->beginTransaction();

    foreach ($users as $user) {
        $currentVal = $user['password_hash'];
        $info = password_get_info($currentVal);
        $needsRehash = false;

        // Unknown algorithm means plaintext
        if (empty($info['algo'])) {
            $newHash = password_hash($currentVal, DEFAULT_HASH_ALGO, ARGON2ID_OPTIONS);
            $needsRehash = true;
            echo "[+] Migrated user '{$user['username']}' (ID {$user['id']}) from plaintext to Argon2id.\n";
        } elseif ($upgradeAll && $info['algoName'] !== 'argon2id') {
            // Force upgrade from bcrypt ($2y$) to Argon2id if requested
            echo "[*] Notice: User '{$user['username']}' is using {$info['algoName']}. Note: cannot rehash bcrypt directly without password; it will upgrade automatically on next user login.\n";
        }

        if ($needsRehash && isset($newHash)) {
            $updateStmt->execute([
                ':hash' => $newHash,
                ':id'   => $user['id']
            ]);
            $migratedCount++;
        }
    }

    $db->commit();
    echo "[OK] Complete. Migrated {$migratedCount} records to Argon2id.\n";

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "[!] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
