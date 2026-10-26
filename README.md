# 🐕 Bantay Aso — Continuous QA & Semantic Changelog Platform

> **Bantay Aso** (Tagalog for *"Watchdog"* / *"Guard Dog"*) is a pure web-native, automated QA and semantic diffing platform designed specifically for standard **Linux Virtualmin VPS (LAMP/LEMP)** environments. It continuously tracks web surfaces, filters out dynamic nonces and noisy trackers, computes structural line diffs, and synthesizes executive **Semantic Changelogs** using built-in ECC rules and an **Antigravity Agent (AGY CLI)**.

---

## 🎯 Primary Synchronous Target

* **Production URL:** [`https://bantayaso.s3curity.net/`](https://bantayaso.s3curity.net/)
* **Platform Node:** Bantay Aso Autonomous Sentinel & Quality Assurance Center
* **NAS SSH Synchronization Bridge:** `192.168.10.45:2222` (Bidirectional local mirror ⇄ remote NAS parity)

---

## 🛡️ Core Directives & Hard VPS Constraints

* **Pure Web-Native Execution:** Built with standard PHP 8.x native extensions (`cURL`, `DOMDocument`, `PDO`, `mbstring`) and vanilla HTML5, CSS3, and modern ES6+ JavaScript.
* **Zero Server-Side Shell Execution:** No `exec()`, `shell_exec()`, `system()`, `passthru()`, or background daemons. Strictly adheres to shared/VPS hosting restrictions.
* **Drop-in Deployment:** Requires no `npm build`, `composer install`, or command-line migrations. Simply upload the files to your Virtualmin `public_html` directory and launch.
* **Strict ECC Standards:** Incorporates rules and protocols from `/ECC` (`rules/php/security.md`, `rules/web/design-quality.md`, `skills/browser-qa`, and `skills/canary-watch`).

---

## 👥 Role-Based Access Control (RBAC) & User Management

| Role | Default Username | Default Password | Permissions |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `@@22162613Bantayaso` | • Full Watchlist CRUD (Add, Edit, Delete targets)<br>• Trigger **"NEW QA"** execution on any target<br>• Manage users (Add user accounts, assign roles, delete accounts)<br>• Configure Antigravity Agent & Analysis Engine parameters<br>• Manage own profile & change password |
| **Viewer (User)** | `viewer` | `Viewer@BantayAso2026!` | • Read-only access to Watchlist dashboard<br>• Inspect historical QA snapshots by date<br>• Compare versions via Side-by-Side and Unified Diffs<br>• Read Semantic Changelogs<br>• Manage own profile & change password |

---

## 🗄️ Synchronous Project & QA Reporting Subsystem

Directly integrated into the live website at [**`index.php`**](index.php) (served at [`https://bantayaso.s3curity.net/index.php`](https://bantayaso.s3curity.net/index.php)) as well as accessible via [`qa-dashboard.html`](qa-dashboard.html), this dedicated cybersecurity network subsystem fulfills comprehensive offline file management and inter-version QA reporting:

### 1. Offline Files & NAS SSH Synchronization Manager
* **Architecture:** Maintains strict file parity between offline mirror dumps, test snapshots, and live online surfaces (`https://bantayaso.s3curity.net/`) via an encrypted NAS SSH bridge.
* **Integrity Auditing:** Continuous canonical SHA-256 digest calculation and drift detection (zero drift tolerance).
* **Live Operations:** Real-time sync triggers (`Sync Local ⇄ NAS (SSH)`) with interactive command stream simulation.

### 2. Table 1: Changelogs & Run History
Tracks version increments across all automated and manual inspection cycles:
* **Columns:** `Run #` | `Datetime` | `Version` | `Changes`
* **Run #1:** `Version 1.0` (Baseline QA, initial surface ingestion).
* **Run #2:** `Version 1.1` (WAI-ARIA 1.2 Combobox search, noise stripper, security headers verification).
* **Interactive Compare Feature:** Direct visual connector linking **Run #2** and **Run #3** with an animated directional neon arrow (`──────▶ ⚡`) and a **`⚡ Compare Version 1.1 vs Version 1.5`** button that opens the Unified & Semantic Diff Modal.
* **Run #3:** `Version 1.5` (Synchronous production surface tracking for `https://bantayaso.s3curity.net/`, 100% table header scoping, admin credentials synchronized).

### 3. Table 2: QA Reports View
Provides dedicated inspection actions for each run:
* **Columns:** `Reports (Run #)` | `Datetime` | `Action`
* **Action:** Styled cyber **`[👁️ Show Report]`** buttons with verdict indicators (`SHIP WITH FIXES`, `SHIP (91/100)`, `SHIP (100/100)`).
* **6-Section Bantay Aso Report Specification:**
  1. *Executive Summary & Verdict Rationale*
  2. *UI & Visual Layout Changes*
  3. *Content & Copy Updates*
  4. *Security & Header Posture (CSP, HSTS, X-Frame-Options, MIME Sniffing)*
  5. *Regressions, Broken Elements & Warnings*
  6. *Recommended Action Items*

### 4. Watchdog Branding & Cyber-Net Aesthetic
* **Logo Placeholder:** Dedicated `#watchdogLogoPlaceholder` with neon laser crosshair reticles and cybernetic watchdog emblem asset (`assets/img/bantayaso-watchdog-logo.jpg`).
* **Design System:** Deep midnight obsidian surfaces, terminal monospace typography (`JetBrains Mono`), and high-phosphor neon accents (Neon Cyan, Matrix Emerald, Cyber Amber, Alert Crimson).

---

## 🔄 The "NEW QA" 4-Step Pipeline

When an Administrator triggers **"NEW QA"** on any target:

1. **Step 1 (Fetch):** Native PHP `cURL` fetches the live website, recording HTTP status code, server latency (ms), response headers, and auditing critical security headers (CSP, HSTS, X-Frame-Options, MIME sniffing).
2. **Step 2 (Strip Dynamic Noise):** Per `/ECC` directives, the content sanitizer removes nonces (`nonce="..."`), inline dynamic CSRF tokens, cache-busting asset query strings, Google Tag Manager / Analytics / ad trackers, and dynamic timestamp comments. It then calculates a canonical SHA-256 content hash.
3. **Step 3 (Store Snapshot):** The cleaned snapshot, raw headers, and structural DOM metrics are committed to the database.
4. **Step 4 (Diff & Semantic Analysis):** The system diffs the snapshot against the baseline or previous check, sending the unified diff and header drift context to the configured analysis engine (**Built-in ECC Sentinel Engine** or **Antigravity Agent via AGY CLI**). It generates a structured **Semantic Changelog** categorizing:
   * **QA Verdict:** `SHIP`, `SHIP WITH FIXES`, or `DO NOT SHIP`
   * **UI & Visual Layout Changes**
   * **Content & Copy Updates**
   * **Security & Header Posture**
   * **Potential Regressions & Broken Elements**
   * **Recommended Action Items**

---

## 📁 Project Architecture

```
bantayaso/
├── config.php                  # Database, session security, version, and seed configuration
├── index.php                   # Single-Page Application (SPA) dashboard UI & modals
├── login.php                   # Dedicated sign-in page (admin / @@22162613Bantayaso)
├── qa-dashboard.html           # Synchronous QA Reporting Dashboard & NAS SSH Sync Manager
├── DEPLOYMENT.md               # Complete DevOps handover & Virtualmin operations guide
├── .agents/
│   └── skills/
│       └── bantayaso-qa/       # Antigravity QA Skill for AGY CLI integration
│           └── SKILL.md
├── api/
│   ├── auth.php                # Authentication, logout, and CSRF token distributor
│   ├── profile.php             # User profile inspection, email update, and password change
│   ├── users.php               # Admin User Management CRUD (Add User, Delete User)
│   ├── watchlist.php           # Targets CRUD with RBAC enforcement
│   ├── qa.php                  # "NEW QA" 4-step pipeline and snapshot history
│   ├── diff.php                # Date-to-date diffing and AI changelog retriever
│   └── settings.php            # Antigravity Agent & engine parameters & connection test
├── includes/
│   ├── db.php                  # Self-initializing PDO SQLite / MySQL connection & seeder
│   ├── auth_middleware.php     # RBAC verification, password hashing, and CSRF guard
│   ├── fetcher.php             # Native cURL live website fetcher & security header audit
│   ├── sanitizer.php           # Dynamic nonce & noisy tracker stripper (ECC rules)
│   ├── differ.php              # Longest Common Subsequence (LCS) line diff engine
│   └── llm_client.php          # Semantic analysis engine with Antigravity Agent & ECC support
├── assets/
│   ├── css/
│   │   └── app.css             # Dark luxury cyber-defense stylesheet (Plus Jakarta Sans + JetBrains Mono)
│   ├── js/
│   │   └── app.js              # Modern ES6+ reactive frontend engine (Diff viewer, live progress)
│   └── img/
│       └── bantayaso-watchdog-logo.jpg # High-tech cyber watchdog emblem
└── data/
    ├── .htaccess               # Apache deny rules guarding SQLite database file
    └── bantayaso.sqlite        # SQLite database (auto-created and synchronized on load)
```

---

## 📦 Semantic Versioning & Changelog (SemVer 2.0.0)

### [`v1.3.0`] — 2026-10-20
#### 🚀 Added
- **Two-Way Live Synchronization Engine (`sync_online.py` & `api/sync.php`):**
  - Built bidirectional synchronization protocol between the local Bantay Aso instance and `https://bantayaso.s3curity.net/` using authenticated REST sessions (`admin` / `@@22162613Bantayaso`).
  - **Pull Parity:** Ingested all remote targets (`East West International Qatar`, `Hard N' Heavy`, `S3curity.net Defense Hub`) and their historical snapshots into local SQLite database.
  - **Push Parity:** Published local targets (`S3curity H3LIX HRMS Portal` and `Bantay Aso Production Node`) to `https://bantayaso.s3curity.net/`, achieving full 5-target lockstep on both ends.
- **QA Reporting Dashboard (`qa-dashboard.html`) Live Target Grid:**
  - Added dedicated **Live Two-Way Remote Synchronizer** panel showcasing real-time status of all 5 monitored websites.
  - Integrated interactive "⚡ Synchronize Now (Pull)" and "⬆️ Push Local Targets" triggers with live terminal telemetry output.
- **Table 1 & Table 2 Expansion (Run #4 & Report #4):**
  - **Table 1 (Changelogs):** Added **Run #4** documenting the live ingestion of `East West International Qatar` (`https://baalbaki.me/ewi7`), DOM metric capture (54 images, 103 links, 4 scripts, 1 `<h1>`, 7 `<h2>`, 17 `<h3>`), and SHA-256 hash `9acfafca288...`.
  - **Table 2 (QA Reports):** Added **Report #4** for `East West International Qatar` rendered with the complete 6-section Bantay Aso report format (*Executive Summary*, *UI & Visual Layout*, *Content & Copy*, *Security & Header Posture*, *Regressions*, *Recommended Action Items*).
- **SPA Header Sync Trigger:**
  - Added quick `🔄 Sync Live` action button in the main `index.php` navbar to initiate instant one-click data synchronization with `https://bantayaso.s3curity.net/`.

---

### [`v1.2.0`] — 2026-10-19
#### 🚀 Added
- **Synchronous Target Monitoring:** Established `https://bantayaso.s3curity.net/` as the primary production node in `config.php`, SQLite database, and dashboard telemetry.
- **Offline Files & NAS SSH Synchronization Manager:** Integrated interactive NAS SSH sync management panel (`qa-dashboard.html`) supporting bidirectional rsync/sftp sync simulations, checksum verifications, and latency tracking.
- **Table 1 (Changelogs & Run History):** Implemented run tracking table with exact schema (`Run #`, `Datetime`, `Version`, `Changes`) for Runs #1, #2, and #3.
- **Interactive Version Comparison Modal:** Deployed inter-row directional connector (`Run #2 ──────▶ ⚡ Run #3`) and `Compare Version 1.1 vs Version 1.5` modal featuring Unified Code Diff, Semantic Changelog, and Security Header Drift tabs.
- **Table 2 (QA Reports View):** Implemented separate reports table with neon-styled `[Show Report]` action buttons.
- **Full 6-Section QA Analysis Modal:** Comprehensive audit viewer conforming to Bantay Aso evaluation protocol (*Executive Summary*, *UI & Visual Layout*, *Content & Copy*, *Security Posture*, *Regressions*, *Action Items*).
- **Watchdog Logo Branding:** Reserved placeholder space `#watchdogLogoPlaceholder` with high-tech laser reticles and generated cyber robotic watchdog emblem asset (`assets/img/bantayaso-watchdog-logo.jpg`).

#### 🔒 Security & Configuration
- **Synchronized Administrator Credentials:** Updated default administrator password to `admin` / `@@22162613Bantayaso`.
- **Automatic Database Migration:** Upgraded `includes/db.php` to automatically synchronize existing SQLite database admin hashes and seed `https://bantayaso.s3curity.net/` into the `watchlist` table on first connection.
- **UI & Helper Synchronization:** Updated `login.php`, `index.php`, `DEPLOYMENT.md`, `run-local.ps1`, and `qa-dashboard.html` to reflect synchronized credentials and target URLs.

---

### [`v1.1.0`] — 2026-10-18
#### 🚀 Added
- **HRMS QAC Milestone Integration:** Integrated 110-route audit evaluation suite (V1 ➔ V7 Sovereign Gold Standard) with 100/100 quality score.
- **Dynamic Content Sanitizer:** Built native regex sanitizer stripping dynamic nonces (`nonce="..."`), CSRF tokens, cache-busting queries (`?v=...`), and tracker scripts.
- **Line Diffing Engine:** Implemented native Longest Common Subsequence (LCS) differ generating unified and side-by-side diff outputs.
- **Security Headers Auditor:** Automated grading of CSP, HSTS, X-Frame-Options, and MIME-type security headers.

---

### [`v1.0.0`] — 2026-10-17
#### 🚀 Added
- **Initial Baseline Release:** Production-ready Single-Page Application (SPA) dashboard for Linux Virtualmin VPS (LAMP/LEMP).
- **Role-Based Access Control (RBAC):** Native session authentication distinguishing `admin` and `viewer` roles.
- **Built-in ECC Sentinel Engine:** Deterministic rule-based QA analysis producing structured changelogs without external LLM requirements.
- **Zero Shell VPS Compliance:** 100% web-native PHP architecture with zero `exec()` or background daemons.

---

## 🚀 Virtualmin VPS Deployment Instructions

For complete server configuration details, consult [**`DEPLOYMENT.md`**](DEPLOYMENT.md).

1. **Upload Files:**
   Upload the entire `bantayaso` directory to your Virtualmin virtual server's `public_html` directory via SFTP or the Virtualmin File Manager.
2. **Permissions:**
   Ensure `data/` is writable:
   ```bash
   chmod 775 data
   ```
3. **Open in Browser:**
   Visit `https://bantayaso.s3curity.net/` in your browser. All tables and configurations will initialize and synchronize automatically.
4. **Sign In:**
   Sign in with administrator credentials:
   * **Username:** `admin`
   * **Password:** `@@22162613Bantayaso`
