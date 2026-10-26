<?php
/**
 * Bantay Aso — Security & Password Hashing Configuration
 * Configures military-grade Argon2id parameters as per OWASP and Google Argon guidelines.
 */

declare(strict_types=1);

if (!defined('ARGON2ID_OPTIONS')) {
    define('ARGON2ID_OPTIONS', [
        'memory_cost' => 65536, // 64 MB
        'time_cost'   => 4,     // 4 iterations
        'threads'     => 2      // 2 parallelism lanes
    ]);
}

// Fallback algorithm constant in case sodium / argon2 is missing on certain PHP environments
if (!defined('DEFAULT_HASH_ALGO')) {
    define('DEFAULT_HASH_ALGO', defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}
