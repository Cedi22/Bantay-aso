import subprocess

php_code = """<?php
$options = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2];
$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
echo 'ADMIN_ARGON=' . password_hash('@@22162613Bantayaso', $algo, $options) . PHP_EOL;
echo 'VIEWER_ARGON=' . password_hash('Viewer@BantayAso2026!', $algo, $options) . PHP_EOL;
"""

res = subprocess.run(
    ["ssh", "-i", "~/.ssh/id_ed25519_bantayaso", "-o", "BatchMode=yes", "aso@bantayaso.s3curity.net", "php"],
    input=php_code,
    capture_output=True,
    text=True
)
print("STDOUT:", res.stdout)
print("STDERR:", res.stderr)
