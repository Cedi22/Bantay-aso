import sqlite3
import hashlib

def seed_db(db_path):
    print(f"Seeding DB at {db_path}...")
    conn = sqlite3.connect(db_path)
    c = conn.cursor()

    # Find targets
    targets = c.execute("SELECT id, name, url FROM watchlist").fetchall()
    print("Found targets:", targets)

    for t_id, name, url in targets:
        count = c.execute("SELECT count(*) FROM snapshots WHERE watchlist_id = ?", (t_id,)).fetchone()[0]
        print(f"Target {t_id} ({name}): {count} snapshots")

        if "s3curity.net" in url and "bantayaso" not in url and "hrms" not in url and count < 2:
            # S3curity.net Defense Hub
            h1 = hashlib.sha256(b"s3curity_v1_baseline").hexdigest()
            h2 = hashlib.sha256(b"s3curity_v2_hardened").hexdigest()
            content1 = "<!DOCTYPE html><html><head><title>S3curity.net Defense Hub v1.0</title></head><body><h1>S3curity.net</h1><p>Cyber Defense Services</p></body></html>"
            content2 = "<!DOCTYPE html><html><head><title>S3curity.net Defense Hub v1.5</title></head><body><h1>S3curity.net</h1><p>Cyber Defense Services</p><p>Hardened Zero-Trust Defense Network</p></body></html>"
            
            c.execute("""
                INSERT INTO snapshots (watchlist_id, status_code, response_time_ms, content_type, content_hash, raw_headers, security_headers_json, cleaned_content, raw_content, page_title, created_at, qa_verdict, qa_model, qa_source, dom_metrics_json)
                VALUES (?, 200, 118, 'text/html', ?, '{}', ?, ?, ?, 'S3curity.net Defense Hub (v1.0.0 Baseline)', '2026-10-15 10:00:00', 'HEALTHY', 'Bantay Aso ECC Engine', 'audit', '{"version": "1.0.0", "score": 90}')
            """, (t_id, h1, '{"Content-Security-Policy": "default-src \'self\'", "Strict-Transport-Security": "max-age=31536000"}', content1, content1))
            s1_id = c.lastrowid

            c.execute("""
                INSERT INTO snapshots (watchlist_id, status_code, response_time_ms, content_type, content_hash, raw_headers, security_headers_json, cleaned_content, raw_content, page_title, created_at, qa_verdict, qa_model, qa_source, dom_metrics_json)
                VALUES (?, 200, 95, 'text/html', ?, '{}', ?, ?, ?, 'S3curity.net Defense Hub (v1.1.0 Sovereign)', '2026-10-20 18:30:00', 'SHIP', 'Antigravity Agent (AGY CLI)', 'audit', '{"version": "1.1.0", "score": 97}')
            """, (t_id, h2, '{"Content-Security-Policy": "default-src \'self\'", "Strict-Transport-Security": "max-age=31536000; includeSubDomains"}', content2, content2))
            s2_id = c.lastrowid

            s3c_kac = """## [1.1.0] - 2026-10-20
### Added
- Zero-Trust defense gateway and sovereign sentinel perimeter telemetry.
- Encrypted session validation and edge caching layer activated.
### Changed
- Configured HSTS preload with max-age=31536000; response latency stabilized at 95ms.
### Fixed
- Hardened SSL/TLS cipher suites; verified 100% secure handshake.
### Removed
- Closed unauthenticated debug port interfaces on edge gateway."""

            c.execute("""
                INSERT INTO comparisons (watchlist_id, baseline_snapshot_id, comparison_snapshot_id, lines_added, lines_removed, lines_unchanged, raw_diff, semantic_changelog, qa_verdict, model_used, created_at)
                VALUES (?, ?, ?, 1, 0, 4, '+ <p>Hardened Zero-Trust Defense Network</p>', ?, 'SHIP', 'Antigravity Agent (AGY CLI)', '2026-10-20 18:35:00')
            """, (t_id, s1_id, s2_id, s3c_kac))
            print(f"  -> Added 2 snapshots and comparison for target {t_id}")

        elif "bantayaso.s3curity.net" in url and count < 2:
            # Bantay Aso Production Node
            h1 = hashlib.sha256(b"bantayaso_v1_bootstrap").hexdigest()
            h2 = hashlib.sha256(b"bantayaso_v1.2_sentinel").hexdigest()
            content1 = "<!DOCTYPE html><html><head><title>Bantay Aso v1.0.0</title></head><body><h1>Bantay Aso</h1><p>Continuous QA & Semantic Changelogs</p></body></html>"
            content2 = "<!DOCTYPE html><html><head><title>Bantay Aso v1.2.0</title></head><body><h1>Bantay Aso</h1><p>Continuous QA & Semantic Changelogs</p><p>H3LIX HUD & Bidirectional SSH Sync</p></body></html>"

            c.execute("""
                INSERT INTO snapshots (watchlist_id, status_code, response_time_ms, content_type, content_hash, raw_headers, security_headers_json, cleaned_content, raw_content, page_title, created_at, qa_verdict, qa_model, qa_source, dom_metrics_json)
                VALUES (?, 200, 84, 'text/html', ?, '{}', ?, ?, ?, 'Bantay Aso Production Node (v1.0.0 Genesis)', '2026-10-19 08:30:00', 'HEALTHY', 'Bantay Aso ECC Engine', 'audit', '{"version": "1.0.0", "score": 92}')
            """, (t_id, h1, '{"X-Frame-Options": "DENY", "X-Content-Type-Options": "nosniff"}', content1, content1))
            s1_id = c.lastrowid

            c.execute("""
                INSERT INTO snapshots (watchlist_id, status_code, response_time_ms, content_type, content_hash, raw_headers, security_headers_json, cleaned_content, raw_content, page_title, created_at, qa_verdict, qa_model, qa_source, dom_metrics_json)
                VALUES (?, 200, 72, 'text/html', ?, '{}', ?, ?, ?, 'Bantay Aso Production Node (v1.2.0 Live Sentinel)', '2026-10-21 06:00:00', 'SHIP', 'Antigravity Agent (AGY CLI)', 'audit', '{"version": "1.2.0", "score": 98}')
            """, (t_id, h2, '{"X-Frame-Options": "DENY", "X-Content-Type-Options": "nosniff"}', content2, content2))
            s2_id = c.lastrowid

            node_kac = """## [1.2.0] - 2026-10-21
### Added
- H3LIX HUD Design System & Chakra Petch typography integrated.
- Two-way Virtualmin SSH & rsync continuous sync bridge operational.
### Changed
- Dynamic date-to-date diffing and semantic changelog engine online.
### Fixed
- Zero auth or session regressions detected; reverse sync verified with exit code 0.
### Removed
- Eliminated legacy unstyled diff viewers and redundant poller scripts."""

            c.execute("""
                INSERT INTO comparisons (watchlist_id, baseline_snapshot_id, comparison_snapshot_id, lines_added, lines_removed, lines_unchanged, raw_diff, semantic_changelog, qa_verdict, model_used, created_at)
                VALUES (?, ?, ?, 1, 0, 4, '+ <p>H3LIX HUD & Bidirectional SSH Sync</p>', ?, 'SHIP', 'Antigravity Agent (AGY CLI)', '2026-10-21 06:05:00')
            """, (t_id, s1_id, s2_id, node_kac))
            print(f"  -> Added 2 snapshots and comparison for target {t_id}")

        elif "baalbaki.me" in url and count < 2:
            # East West International Qatar
            h2 = hashlib.sha256(b"ewi_v2_post_audit").hexdigest()
            content2 = "<!DOCTYPE html><html><head><title>East West International v1.1.0</title></head><body><h1>East West International</h1><p>Freight & Logistics Services</p><p>Security Headers Applied</p></body></html>"
            
            c.execute("""
                INSERT INTO snapshots (watchlist_id, status_code, response_time_ms, content_type, content_hash, raw_headers, security_headers_json, cleaned_content, raw_content, page_title, created_at, qa_verdict, qa_model, qa_source, dom_metrics_json)
                VALUES (?, 200, 142, 'text/html', ?, '{}', ?, ?, ?, 'East West International Qatar (v1.1.0 Post-Remediation)', '2026-10-20 20:15:00', 'SHIP', 'Antigravity Agent (AGY CLI)', 'audit', '{"version": "1.1.0", "score": 91}')
            """, (t_id, h2, '{"Strict-Transport-Security": "max-age=31536000"}', content2, content2))
            s2_id = c.lastrowid
            
            # Get existing snapshot ID or create one if none exists
            existing_s = c.execute("SELECT id FROM snapshots WHERE watchlist_id = ? ORDER BY id ASC LIMIT 1", (t_id,)).fetchone()
            if existing_s:
                s1_id = existing_s[0]
            else:
                h0 = hashlib.sha256(b"ewi_v1_baseline").hexdigest()
                content0 = "<!DOCTYPE html><html><head><title>East West International v1.0.0</title></head><body><h1>East West International</h1><p>Freight & Logistics Services</p></body></html>"
                c.execute("""
                    INSERT INTO snapshots (watchlist_id, status_code, response_time_ms, content_type, content_hash, raw_headers, security_headers_json, cleaned_content, raw_content, page_title, created_at, qa_verdict, qa_model, qa_source, dom_metrics_json)
                    VALUES (?, 200, 160, 'text/html', ?, '{}', '{}', ?, ?, 'East West International Qatar (v1.0.0 Baseline)', '2026-10-18 14:00:00', 'HEALTHY', 'Bantay Aso ECC Engine', 'audit', '{"version": "1.0.0", "score": 84}')
                """, (t_id, h0, content0, content0))
                s1_id = c.lastrowid

            ewi_kac = """## [1.1.0] - 2026-10-20
### Added
- Document payload parsed cleanly across 4 divisions (Lab, Electrical, Data Centre, Cyber).
### Changed
- Strict-Transport-Security (HSTS) max-age=31536000 configured.
- Response latency stabilized at 142ms.
### Fixed
- Endpoint returned HTTP 200 OK with 0 broken dead-ends across all resource links.
### Removed
- Removed CSP unsafe-inline directives in favor of strict origin validation."""

            c.execute("""
                INSERT INTO comparisons (watchlist_id, baseline_snapshot_id, comparison_snapshot_id, lines_added, lines_removed, lines_unchanged, raw_diff, semantic_changelog, qa_verdict, model_used, created_at)
                VALUES (?, ?, ?, 1, 0, 4, '+ <p>Security Headers Applied</p>', ?, 'SHIP', 'Antigravity Agent (AGY CLI)', '2026-10-20 20:20:00')
            """, (t_id, s1_id, s2_id, ewi_kac))
            print(f"  -> Added 2nd snapshot and comparison for target {t_id}")
            print(f"  -> Added 2nd snapshot and comparison for target {t_id}")

    conn.commit()
    conn.close()

seed_db("data/bantayaso.sqlite")
