import os
import subprocess

admin_pass = os.environ.get("REMOTE_ADMIN_PASS")
viewer_pass = os.environ.get("VIEWER_PASS")

if not admin_pass or not viewer_pass:
    raise RuntimeError("Missing REMOTE_ADMIN_PASS or VIEWER_PASS environment variables.")

remote_php = f"""<?php
$admin_hash = password_hash('{admin_pass}', PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
$viewer_hash = password_hash('{viewer_pass}', PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);

$db = new PDO('sqlite:/project/data/bantayaso.sqlite');
$stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
$stmt->execute([$admin_hash, 'admin']);
$stmt->execute([$viewer_hash, 'viewer']);

// Update runtime bootstrap-secrets.php
$secrets = "<?php\\nreturn ['admin_password' => '{admin_pass}', 'viewer_password' => '{viewer_pass}'];\\n";
@file_put_contents('/project/runtime/bootstrap-secrets.php', $secrets);

$env_content = "REMOTE_BASE_URL=https://bantayaso.s3curity.net\\nREMOTE_ADMIN_USER=admin\\nREMOTE_ADMIN_PASS={admin_pass}\\nVIEWER_USER=viewer\\nVIEWER_PASS={viewer_pass}\\n";
@file_put_contents('/project/.env', $env_content);
@file_put_contents('/project/app/.env', $env_content);

$check = $db->query('SELECT id, username, email, role, password_hash FROM users')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($check, JSON_PRETTY_PRINT);
"""

res = subprocess.run(
    ["ssh", "-i", "~/.ssh/id_ed25519_bantayaso", "-o", "BatchMode=yes", "aso@bantayaso.s3curity.net", "php"],
    input=remote_php,
    capture_output=True,
    text=True
)
print("STDOUT:", res.stdout)
print("STDERR:", res.stderr)
