---
name: bantayaso-qa
description: Autonomous QA analysis and semantic diff evaluation engine for Bantay Aso. Analyzes website diffs, security header drift, and produces structured semantic changelogs.
metadata:
  origin: BantayAso-AGY
---

# Bantay Aso QA & Semantic Surface Sentinel

Autonomous agent skill for evaluating continuous web surface changes, noise-filtered code/text diffs, and security posture alterations.

## When to Activate

- Analyzing diffs generated between two website snapshots
- Categorizing web surface modifications into UI, Content, Security, and Regression sections
- Determining official QA verdicts: `SHIP`, `SHIP WITH FIXES`, or `DO NOT SHIP`
- Running post-deploy canary and regression audits

## Core Evaluation Protocol

When receiving a diff payload from the Bantay Aso platform:

1. **Noise Check:** Ensure all dynamic nonces (`nonce="..."`), CSRF tokens, cache-busting queries (`?v=...`), and timestamp comments were stripped.
2. **Security Headers Audit:**
   - Verify `Content-Security-Policy` (CSP)
   - Verify `Strict-Transport-Security` (HSTS)
   - Verify `X-Frame-Options` and `X-Content-Type-Options`
   - Flag any removed or weakened headers as a **CRITICAL BLOCKER** (`DO NOT SHIP`).
3. **HTTP Status & Latency:**
   - HTTP 200 = Healthy
   - HTTP 4xx/5xx = Immediate Blocker (`DO NOT SHIP`)
   - Latency spike > 2x baseline = Flag as warning.
4. **Content & Structural Diff:**
   - Differentiate between intentional copy updates and accidental content truncation.
   - Detect layout shifts, broken elements, or missing script tags.

## Standard Output Format

Output must strictly conform to the following markdown structure:

```markdown
## QA Report & Verdict
**Verdict:** [SHIP | SHIP WITH FIXES | DO NOT SHIP]

### 1. Executive Summary
[Concise 2-3 sentence overview of changes, health, and verdict rationale.]

### 2. UI & Visual Layout Changes
- [Specific additions, removals, or layout modifications]

### 3. Content & Copy Updates
- [Copy updates, messaging, or SEO metadata changes]

### 4. Security & Header Posture
- [CSP, HSTS, and framing header status]

### 5. Regressions, Broken Elements & Warnings
- [Blockers or warnings identified]

### 6. Recommended Action Items
- [Concrete engineering or QA team next steps]
```
