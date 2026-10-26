"""
Bantay Aso — Two-Way Remote Synchronization Engine
Bidirectional synchronization between local Bantay Aso instance and https://bantayaso.s3curity.net/
"""

import sys
import os
import json
import sqlite3
import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import time

# Configure UTF-8 stdout for Windows consoles
if sys.stdout.encoding != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except Exception:
        pass

def load_dotenv(env_path=None):
    """Simple zero-dependency .env loader."""
    if env_path is None:
        env_path = os.path.join(os.path.dirname(__file__), ".env")
    if os.path.exists(env_path):
        with open(env_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, val = line.split("=", 1)
                key = key.strip()
                val = val.strip().strip("'\"")
                if key and key not in os.environ:
                    os.environ[key] = val

load_dotenv()

# Load bootstrap-secrets.php if present as fallback
secrets_file = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "runtime", "bootstrap-secrets.php")
if os.path.exists(secrets_file):
    try:
        with open(secrets_file, "r", encoding="utf-8") as f:
            content = f.read()
            import re
            m = re.search(r"['\"]admin_password['\"]\s*=>\s*['\"]([^'\"]+)['\"]", content)
            if m and not os.getenv("REMOTE_ADMIN_PASS"):
                os.environ["REMOTE_ADMIN_PASS"] = m.group(1)
    except Exception:
        pass

REMOTE_BASE = os.getenv("REMOTE_BASE_URL", "https://bantayaso.s3curity.net").rstrip("/")
REMOTE_USER = os.getenv("REMOTE_ADMIN_USER", "admin")
REMOTE_PASS = os.getenv("REMOTE_ADMIN_PASS")
if not REMOTE_PASS:
    raise RuntimeError("Missing required environment variable REMOTE_ADMIN_PASS. Set in .env or environment.")

LOCAL_DB_PATH = os.path.join(os.path.dirname(__file__), "data", "bantayaso.sqlite")

def get_remote_session():
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE

    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(cj),
        urllib.request.HTTPSHandler(context=ctx)
    )

    try:
        # 1. Get initial CSRF
        req_me = urllib.request.Request(f"{REMOTE_BASE}/api/auth.php?action=me")
        res_me = opener.open(req_me, timeout=15)
        me_data = json.loads(res_me.read().decode("utf-8"))
        csrf = me_data.get("csrf_token")

        # 2. Login
        login_body = json.dumps({"username": REMOTE_USER, "password": REMOTE_PASS}).encode("utf-8")
        req_login = urllib.request.Request(
            f"{REMOTE_BASE}/api/auth.php?action=login",
            data=login_body,
            headers={"Content-Type": "application/json", "X-CSRF-TOKEN": csrf}
        )
        res_login = opener.open(req_login, timeout=15)
        login_data = json.loads(res_login.read().decode("utf-8"))
        if not login_data.get("success"):
            print(f"[!] Warning: Remote authentication failed ({login_data.get('error')}). Continuing in offline mode.")
            return opener, csrf

        new_csrf = login_data.get("csrf_token") or csrf
        return opener, new_csrf
    except Exception as e:
        print(f"[*] Note: Remote session connection notice: {e}")
        return opener, None


def sync_pull(opener, csrf, conn):
    print(f"[*] PULLING live contents from {REMOTE_BASE} ...")
    c = conn.cursor()

    # 1. Fetch remote watchlist
    req = urllib.request.Request(f"{REMOTE_BASE}/api/watchlist.php")
    res = opener.open(req, timeout=15)
    watchlist_data = json.loads(res.read().decode("utf-8"))
    remote_items = watchlist_data.get("items", [])
    print(f"    Found {len(remote_items)} targets on remote server.")

    synced_targets = 0
    synced_snapshots = 0

    for item in remote_items:
        r_id = item["id"]
        r_name = item["name"]
        r_url = item["url"]
        r_desc = item.get("description", "")
        r_interval = item.get("check_interval_mins", 60)
        r_active = item.get("is_active", 1)
        r_status = item.get("last_status_code")
        r_resp_time = item.get("last_response_time_ms")
        r_checked = item.get("last_checked_at")
        r_created = item.get("created_at")
        r_updated = item.get("updated_at")

        # Check if exists in local by URL
        existing = c.execute("SELECT id FROM watchlist WHERE url = ?", (r_url,)).fetchone()
        if existing:
            local_target_id = existing[0]
            c.execute("""
                UPDATE watchlist
                SET name = ?, description = ?, check_interval_mins = ?, is_active = ?,
                    last_status_code = ?, last_response_time_ms = ?, last_checked_at = ?, updated_at = ?
                WHERE id = ?
            """, (r_name, r_desc, r_interval, r_active, r_status, r_resp_time, r_checked, r_updated, local_target_id))
            print(f"    [~] Updated target: {r_name} (local ID: {local_target_id})")
        else:
            c.execute("""
                INSERT INTO watchlist (name, url, description, check_interval_mins, is_active,
                                       last_status_code, last_response_time_ms, last_checked_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (r_name, r_url, r_desc, r_interval, r_active, r_status, r_resp_time, r_checked, r_created, r_updated))
            local_target_id = c.lastrowid
            synced_targets += 1
            print(f"    [+] Inserted new target: {r_name} -> {r_url} (local ID: {local_target_id})")

        # 2. Fetch remote snapshots for this target
        time.sleep(0.3)
        req_hist = urllib.request.Request(f"{REMOTE_BASE}/api/qa.php?action=history&watchlist_id={r_id}")
        try:
            res_hist = opener.open(req_hist, timeout=15)
            hist_data = json.loads(res_hist.read().decode("utf-8"))
            remote_snaps = hist_data.get("snapshots", [])
            for r_snap in remote_snaps:
                snap_id = r_snap["id"]
                snap_hash = r_snap.get("content_hash")
                snap_created = r_snap.get("created_at")
                snap_exists = c.execute("SELECT id FROM snapshots WHERE watchlist_id = ? AND created_at = ?", (local_target_id, snap_created)).fetchone()
                if not snap_exists:
                    # Get full snapshot details
                    time.sleep(0.3)
                    req_full = urllib.request.Request(f"{REMOTE_BASE}/api/qa.php?action=snapshot&id={snap_id}")
                    res_full = opener.open(req_full, timeout=15)
                    full_snap = json.loads(res_full.read().decode("utf-8")).get("snapshot", {})

                    headers_raw = full_snap.get("headers_json") or "{}"
                    sec_headers = full_snap.get("security_headers_json") or headers_raw

                    c.execute("""
                        INSERT INTO snapshots (watchlist_id, status_code, response_time_ms, content_type,
                                               raw_headers, security_headers_json, raw_content, cleaned_content,
                                               content_hash, page_title, dom_metrics_json, qa_report, qa_verdict,
                                               qa_model, qa_source, security_score, security_grade, vuln_report_json,
                                               triggered_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """, (
                        local_target_id,
                        full_snap.get("status_code"),
                        full_snap.get("response_time_ms"),
                        full_snap.get("content_type", "text/html"),
                        headers_raw,
                        sec_headers,
                        full_snap.get("raw_content", ""),
                        full_snap.get("cleaned_content", ""),
                        full_snap.get("content_hash"),
                        full_snap.get("page_title", ""),
                        full_snap.get("dom_metrics_json", "{}"),
                        r_snap.get("qa_report") or full_snap.get("qa_report"),
                        r_snap.get("qa_verdict") or full_snap.get("qa_verdict"),
                        r_snap.get("qa_model") or full_snap.get("qa_model"),
                        r_snap.get("qa_source") or full_snap.get("qa_source"),
                        r_snap.get("security_score") or full_snap.get("security_score"),
                        r_snap.get("security_grade") or full_snap.get("security_grade"),
                        r_snap.get("vuln_report_json") or full_snap.get("vuln_report_json"),
                        full_snap.get("triggered_by") or r_snap.get("triggered_by") or "adminmoe",
                        full_snap.get("created_at") or r_snap.get("created_at")
                    ))
                    synced_snapshots += 1
                    print(f"        [+] Ingested snapshot ID {snap_id} (hash {snap_hash[:8] if snap_hash else 'none'}... at {snap_created}) - Verdict: {r_snap.get('qa_verdict', 'NONE')}")
        except Exception as e:
            print(f"        [!] Could not fetch snapshots for remote target {r_id}: {e}")

    conn.commit()
    return synced_targets, synced_snapshots

def sync_push(opener, csrf, conn):
    print(f"[*] PUSHING local contents to {REMOTE_BASE} ...")
    c = conn.cursor()

    time.sleep(0.5)
    # Get remote watchlist URLs
    req = urllib.request.Request(f"{REMOTE_BASE}/api/watchlist.php")
    res = opener.open(req, timeout=15)
    remote_items = json.loads(res.read().decode("utf-8")).get("items", [])
    remote_urls = set(item["url"] for item in remote_items)

    # Find local targets not on remote
    local_targets = c.execute("SELECT id, name, url, description, check_interval_mins FROM watchlist").fetchall()
    pushed_count = 0

    for lt in local_targets:
        lid, name, url, desc, interval = lt
        if url not in remote_urls:
            print(f"    [+] Pushing local target to remote: {name} ({url}) ...")
            push_payload = json.dumps({
                "action": "create",
                "name": name,
                "url": url,
                "description": desc or "Pushed from local Bantay Aso instance",
                "check_interval_mins": interval or 60
            }).encode("utf-8")

            req_push = urllib.request.Request(
                f"{REMOTE_BASE}/api/watchlist.php",
                data=push_payload,
                headers={"Content-Type": "application/json", "X-CSRF-TOKEN": csrf}
            )
            try:
                time.sleep(0.5)
                res_push = opener.open(req_push, timeout=15)
                result = json.loads(res_push.read().decode("utf-8"))
                if result.get("success"):
                    pushed_count += 1
                    print(f"        [OK] Successfully created target on remote site (ID: {result.get('item', {}).get('id')})")
                else:
                    print(f"        [!] Remote rejected creation: {result.get('error')}")
            except Exception as e:
                print(f"        [!] Push failed: {e}")

    return pushed_count

def main():
    if not os.path.exists(LOCAL_DB_PATH):
        print(f"Error: Local DB not found at {LOCAL_DB_PATH}")
        sys.exit(1)

    conn = sqlite3.connect(LOCAL_DB_PATH)

    try:
        opener, csrf = get_remote_session()
        print(f"[OK] Successfully authenticated to {REMOTE_BASE} as {REMOTE_USER}")

        # 1. Pull from website to local
        pulled_targets, pulled_snaps = sync_pull(opener, csrf, conn)

        # 2. Push from local to website (vice versa)
        pushed_targets = sync_push(opener, csrf, conn)

        print("\n=======================================================")
        print("   SYNCHRONIZATION COMPLETE")
        print("=======================================================")
        print(f"Pulled from remote : {pulled_targets} new targets, {pulled_snaps} snapshots")
        print(f"Pushed to remote   : {pushed_targets} local targets")
        print("Both local database and live website are now in lockstep!")
        print("=======================================================")

    except Exception as e:
        print(f"[X] Sync failed: {e}", file=sys.stderr)
        sys.exit(1)
    finally:
        conn.close()

if __name__ == "__main__":
    main()
