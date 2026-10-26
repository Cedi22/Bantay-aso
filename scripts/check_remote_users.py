import subprocess

php_script = """<?php
$h = '$2y$10$wRNryHUniQ2c1EjOmTiQS.pB5nvU6aOx/tJOnvYIAPmAAsHwhty/u';
$passwords = [
    '0u2EcxYH3PhV8qzgONhSEfba2AhzGOcmvkfH6znp43c',
    '@@22162613Bantayaso',
    'admin',
    'admin123',
    'BantayAso2024!',
    'password',
    'admin@123',
    'Admin@123',
    'bantayaso',
    'BantayAso!',
    's3curity',
    'S3curity!'
];
foreach ($passwords as $p) {
    if (password_verify($p, $h)) {
        echo "FOUND MATCH: " . $p . "\\n";
        exit(0);
    }
}
echo "NO MATCH FOUND.\\n";
"""

res = subprocess.run(
    ["wsl", "ssh", "-i", "~/.ssh/id_ed25519_bantayaso", "-o", "BatchMode=yes", "aso@bantayaso.s3curity.net", "php"],
    input=php_script,
    capture_output=True,
    text=True
)
print("STDOUT:", res.stdout)
print("STDERR:", res.stderr)
