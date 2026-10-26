import os
import sys
import json
import sqlite3
import difflib

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, "data", "bantayaso.sqlite")

def test_qa_history(watchlist_id=3):
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    c = conn.cursor()
    c.execute("""
        SELECT id, watchlist_id, status_code, response_time_ms, content_type,
               content_hash, page_title, dom_metrics_json, created_at, qa_report, qa_verdict, qa_model, qa_source
        FROM snapshots
        WHERE watchlist_id = ?
        ORDER BY created_at DESC
    """, (watchlist_id,))
    rows = [dict(r) for r in c.fetchall()]
    conn.close()
    return {"success": True, "snapshots": rows}

def test_diff_compare(baseline_id=23, comparison_id=29):
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    c = conn.cursor()
    c.execute("SELECT * FROM snapshots WHERE id IN (?, ?)", (baseline_id, comparison_id))
    snaps = {r["id"]: dict(r) for r in c.fetchall()}
    
    if baseline_id not in snaps or comparison_id not in snaps:
        conn.close()
        return {"success": False, "error": "Snapshots not found"}

    base = snaps[baseline_id]
    comp = snaps[comparison_id]

    c.execute("""
        SELECT * FROM comparisons 
        WHERE (baseline_snapshot_id = ? AND comparison_snapshot_id = ?)
           OR (baseline_snapshot_id = ? AND comparison_snapshot_id = ?)
        ORDER BY id DESC LIMIT 1
    """, (baseline_id, comparison_id, comparison_id, baseline_id))
    cached = c.fetchone()
    cached = dict(cached) if cached else None
    conn.close()

    old_lines = (base.get("cleaned_content") or "").splitlines()
    new_lines = (comp.get("cleaned_content") or "").splitlines()
    matcher = difflib.SequenceMatcher(None, old_lines, new_lines)

    side_by_side = []
    unified = []
    old_num = 1
    new_num = 1
    added = 0
    removed = 0
    unchanged = 0

    for tag, i1, i2, j1, j2 in matcher.get_opcodes():
        if tag == 'equal':
            for i in range(i1, i2):
                txt = old_lines[i]
                unchanged += 1
                unified.append(' ' + txt)
                side_by_side.append({
                    'left': {'num': old_num, 'type': 'unchanged', 'text': txt},
                    'right': {'num': new_num, 'type': 'unchanged', 'text': txt}
                })
                old_num += 1
                new_num += 1
        elif tag == 'replace':
            for i in range(i1, i2):
                txt = old_lines[i]
                removed += 1
                unified.append('-' + txt)
                side_by_side.append({
                    'left': {'num': old_num, 'type': 'removed', 'text': txt},
                    'right': {'num': None, 'type': 'empty', 'text': ''}
                })
                old_num += 1
            for j in range(j1, j2):
                txt = new_lines[j]
                added += 1
                unified.append('+' + txt)
                side_by_side.append({
                    'left': {'num': None, 'type': 'empty', 'text': ''},
                    'right': {'num': new_num, 'type': 'added', 'text': txt}
                })
                new_num += 1
        elif tag == 'delete':
            for i in range(i1, i2):
                txt = old_lines[i]
                removed += 1
                unified.append('-' + txt)
                side_by_side.append({
                    'left': {'num': old_num, 'type': 'removed', 'text': txt},
                    'right': {'num': None, 'type': 'empty', 'text': ''}
                })
                old_num += 1
        elif tag == 'insert':
            for j in range(j1, j2):
                txt = new_lines[j]
                added += 1
                unified.append('+' + txt)
                side_by_side.append({
                    'left': {'num': None, 'type': 'empty', 'text': ''},
                    'right': {'num': new_num, 'type': 'added', 'text': txt}
                })
                new_num += 1

    total = added + removed + unchanged
    sim = round((unchanged / total * 100), 1) if total > 0 else 100.0

    changelog = cached.get("semantic_changelog") if cached else "No changes detected."
    verdict = cached.get("qa_verdict") if cached else ("SHIP" if added > 0 or unchanged > 0 else "HOLD")
    model = cached.get("model_used") if cached else "Antigravity Agent (AGY CLI)"

    return {
        "success": True,
        "baseline": {
            "id": base["id"],
            "created_at": base["created_at"],
            "status_code": base["status_code"],
            "response_time_ms": base["response_time_ms"],
            "page_title": base["page_title"],
            "content_hash": base["content_hash"],
            "security_headers": json.loads(base["security_headers_json"] or "{}") if isinstance(base.get("security_headers_json"), str) else {}
        },
        "comparison": {
            "id": comp["id"],
            "created_at": comp["created_at"],
            "status_code": comp["status_code"],
            "response_time_ms": comp["response_time_ms"],
            "page_title": comp["page_title"],
            "content_hash": comp["content_hash"],
            "security_headers": json.loads(comp["security_headers_json"] or "{}") if isinstance(comp.get("security_headers_json"), str) else {}
        },
        "stats": {
            "added": added,
            "removed": removed,
            "unchanged": unchanged,
            "total": total,
            "similarity_pct": sim
        },
        "side_by_side": side_by_side,
        "unified": "\n".join(unified),
        "semantic_changelog": changelog,
        "verdict": verdict,
        "model": model,
        "source": "audit"
    }

print("Testing QA History:")
h = test_qa_history(3)
print(f"Snapshots found: {len(h['snapshots'])}")

print("Testing Diff Compare (42 vs 43):")
d = test_diff_compare(42, 43)
print(f"Diff success: {d['success']}, Verdict: {d.get('verdict')}, Lines: +{d['stats']['added']}, -{d['stats']['removed']}")
