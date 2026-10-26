import subprocess
import json

php_code = """<?php
$db = new PDO('sqlite:/project/data/bantayaso.sqlite');
$s = $db->query('SELECT watchlist_id, count(*), min(created_at), max(created_at) FROM snapshots GROUP BY watchlist_id');
print_r($s->fetchAll(PDO::FETCH_ASSOC));
$w = $db->query('SELECT id, name, url FROM watchlist');
print_r($w->fetchAll(PDO::FETCH_ASSOC));
"""

res = subprocess.run(
    ["ssh", "-i", "~/.ssh/id_ed25519_bantayaso", "-o", "BatchMode=yes", "aso@bantayaso.s3curity.net", "php"],
    input=php_code,
    capture_output=True,
    text=True
)
print("STDOUT:", res.stdout)
print("STDERR:", res.stderr)
