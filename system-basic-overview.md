# Bantay Aso — Plain English System Overview

> **Audience:** Project Managers, Product Owners, Business Stakeholders, and Anyone Curious About How Bantay Aso Works.  
> **Platform Name:** Bantay Aso (*"Watchdog"* or *"Guard Dog"* in Tagalog)  
> **Version:** 1.2.0  

---

## 1. What is Bantay Aso in 30 Seconds?

Imagine you own several busy physical storefronts or buildings. You can't personally stand outside every store 24/7 to make sure the front door lock works, the signs haven't been vandalized, or the painters didn't accidentally paint over an emergency exit.

**Bantay Aso is your 24/7 automated digital guard dog.** 

Whenever your development team updates a website or changes a single line of code, Bantay Aso automatically visits the site, takes an exact snapshot of how it looks and behaves, compares it to how it looked yesterday, checks the locks and security alarms, and writes a neat, plain-English summary report telling you:
- **What changed** (new buttons, moved banners, updated prices).
- **If anything broke** (missing images, broken links, slow loading speeds).
- **If the site is safe to launch** (a clear **"SHIP"** or **"DO NOT SHIP"** verdict badge).

---

## 2. The 3 Big Problems It Solves

```
  Traditional Manual QA                 Bantay Aso Automated Watchdog
┌───────────────────────────────┐     ┌────────────────────────────────┐
│ 😫 Humans clicking 50 pages   │     │ ⚡ Inspects in seconds         │
│ 🙈 Easy to miss tiny breaks   │ ──► │ 🎯 Catches 1-pixel changes     │
│ ❓ "Did someone break this?"   │     │ 📜 Plain-English change log    │
│ 🔓 Security headers forgotten │     │ 🛡️ Automatic security check    │
└───────────────────────────────┘     └────────────────────────────────┘
```

1. **Silent Mistakes:** Developers frequently fix a bug on Page A, but it accidentally breaks a form or navigation bar on Page B without anyone noticing until real customers complain. Bantay Aso catches this instantly.
2. **"Spot-the-Difference" Fatigue:** Human testers get tired after staring at dozens of pages. Bantay Aso compares thousands of lines of website structure in less than a second.
3. **Hidden Security Gaps:** Even if a website *looks* fine on screen, technical security headers (the invisible locks that protect visitors against identity theft and hackers) can silently disappear during routine updates. Bantay Aso sounds the alarm right away.

---

## 3. The Core Parts Made Simple ("What Stuff Does What")

Think of Bantay Aso as an organized team working inside a digital security room:

| Team Member (Feature) | What It Is Called | What It Actually Does |
| :--- | :--- | :--- |
| **🖥️ The Control Desk** | **Dashboard (`index.php`)** | The main screen where you log in. It lists all monitored websites, their current health score, recent test history, and alert badges. |
| **🕵️ The Scout** | **Fetcher (`fetcher.php`)** | Visits the target website like a regular web visitor, measures how fast the server answers (latency in milliseconds), and collects the raw web page. |
| **🧹 The Noise Filter** | **Sanitizer (`sanitizer.php`)** | Websites are full of moving parts like random ad banners, clock timestamps, and temporary tracking codes. The sanitizer sweeps away this "junk noise" so we only focus on **real, meaningful changes**. |
| **🔍 The Spot-the-Difference Engine** | **Differ (`differ.php`)** | Places yesterday's clean website structure side-by-side with today's. It highlights every added line in **bright green** and every deleted line in **crimson red**. |
| **🤖 The Senior Inspector** | **Semantic AI (`llm_client.php`)** | Reads the highlighted differences, reviews the security checklist, and types up an executive 6-section report in plain English explaining what the changes mean for the business. |
| **🛡️ The Building Inspector** | **Security Hub** | Focuses exclusively on safety: validates the SSL/TLS padlock certificate, checks defense against fake website clones, and calculates a 0–100 Security Grade. |
| **🚚 The Courier** | **Sync Tools (`api/sync.php`)** | Keeps your local computer copy and the live cloud server copy synchronized with zero missing files. |

---

## 4. A Day in the Life of a Quality Check ("What Goes Where")

Here is what happens behind the scenes from the moment you click **"Run Live QA"** on any website:

```
 Step 1              Step 2              Step 3               Step 4              Step 5
┌─────────┐         ┌─────────┐         ┌─────────┐          ┌─────────┐         ┌─────────┐
│ 📸 TAKE  │   ──►   │ 🧹 WASH  │   ──►   │ 🔍 SPOT  │   ──►    │ 🤖 WRITE │   ──►   │ 🏆 SHOW │
│ PICTURE │         │  NOISE  │         │  DIFFS  │          │ REPORT  │         │ VERDICT │
└─────────┘         └─────────┘         └─────────┘          └─────────┘         └─────────┘
  The Scout           The Filter          The Diff             The AI              The Green
  fetches the         cleans away         engine spots         evaluates           or Red badge
  live page           random ads          additions &          risk and            appears on
  and headers.        & timestamps.       deletions.           impact.             your screen.
```

1. **Step 1: Taking the Snapshot (Ingestion)**  
   The system visits your website URL, downloads the exact page, verifies the SSL padlock certificate, and notes how quickly the server loaded.
2. **Step 2: Washing Away the Noise (Sanitization)**  
   Dynamic ad IDs, tracking nonces, and current time stamps are filtered out. What remains is the clean, stable foundation of the website. A unique digital fingerprint (SHA-256 hash) is calculated and saved.
3. **Step 3: Spotting the Differences (Diffing)**  
   The system compares this new fingerprint against the previous version. If nothing changed, it immediately confirms **"Zero Drift / 100% Intact"**. If code changed, it generates an easy-to-read comparison table.
4. **Step 4: The AI Review (Semantic Analysis)**  
   Rather than handing you hundreds of lines of confusing code, our AI reads through the changes and writes an executive summary covering:
   - What visual layout elements moved or changed
   - What text or content was updated
   - Whether any security headers were dropped
   - Potential warnings or broken links
5. **Step 5: The Final Verdict Badge**  
   The report is stamped with a clear verdict:
   - 🟢 **SHIP:** Perfect condition. Safe to deploy to live customers.
   - 🟡 **SHIP WITH FIXES:** Good to go, but minor issues need attention soon.
   - 🔴 **HOLD / DO NOT SHIP:** Critical bugs, missing security headers, or broken layouts found. Do not release yet.

---

## 5. Who Uses Bantay Aso? (Roles & Permissions)

| User Role | Everyday Example | What They Can Do in Bantay Aso |
| :--- | :--- | :--- |
| **👑 Administrator (`admin`)** | Lead Developer, DevOps, Engineering Manager | • Add or delete monitored websites<br>• Click **"NEW QA"** to trigger live tests anytime<br>• Create and manage team accounts<br>• Trigger server synchronization tools<br>• Adjust AI engine and security settings |
| **👀 Viewer (`viewer`)** | QA Tester, Product Manager, Executive | • View all website health scores and status cards<br>• Browse historical check runs and inspection dates<br>• Read side-by-side diffs and AI changelog reports<br>• Cannot accidentally delete targets or trigger heavy server operations |

---

## 6. Glossary of Everyday Terms

- **Snapshot:** An exact copy of a website's structure and security headers saved at a specific point in time.
- **Diff:** Short for "difference". A visual view showing what was added (+), removed (-), or stayed the same between two snapshots.
- **Nonce / Dynamic Noise:** Tiny, random pieces of code that change every single time a page loads (like security counters or ad trackers) that do not represent real changes to the website's design or functionality.
- **Semantic Changelog:** A meaningful, plain-English summary of what changes actually mean to users, rather than raw code jargon.
- **Security Headers:** Invisible instructions sent by the web server to your browser telling it to block hackers, prevent dangerous clickjacking, and enforce encrypted connections.
- **Lockstep Parity:** When two computer servers (like your local testing machine and the live public website) are 100% identical with zero missing files or discrepancies.
