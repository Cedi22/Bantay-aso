import urllib.request
import re

url = "http://127.0.0.1:8000/qa-dashboard.html"
html = urllib.request.urlopen(url).read().decode("utf-8")

print(f"Loaded HTML: {len(html)} bytes")
print("kac-changelog count:", html.count("kac-changelog"))
print("kac-version-badge count:", html.count("kac-version-badge"))
print("kac-section-title count:", html.count("kac-section-title"))

# Check Table 1
t1 = re.search(r'<tbody id="changelogsTableBody">(.*?)</tbody>', html, re.DOTALL)
if t1:
    print("Table 1 (changelogsTableBody) found! Number of rows:", len(re.findall(r'<tr', t1.group(1))))
    print("Table 1 kac-changelog instances:", t1.group(1).count("kac-changelog"))
    versions = re.findall(r'## \[(\d+\.\d+\.\d+)\]', t1.group(1))
    print("Table 1 Keep a Changelog SemVer tags:", sorted(set(versions)))
else:
    print("Table 1 not found")

# Check Table 2
t2 = re.search(r'<tbody id="(?:qaReportsTableBody|reportsTableBody)">(.*?)</tbody>', html, re.DOTALL)
if not t2:
    t2 = re.search(r'<table id="tableQaReports"[^>]*>.*?<tbody[^>]*>(.*?)</tbody>', html, re.DOTALL)
if t2:
    print("Table 2 found! Number of rows:", len(re.findall(r'<tr', t2.group(1))))
    versions = re.findall(r'(\d+\.\d+\.\d+)', t2.group(1))
    print("Table 2 SemVer versions detected:", sorted(set(versions)))
else:
    print("Table 2 not found")

# Check Modal Comparison
m = re.search(r'<div id="modalComparison".*?>(.*?)</div>\s*</div>\s*</div>', html, re.DOTALL)
if m:
    print("Modal comparison found! Has kac-changelog:", "kac-changelog" in m.group(1))
    modal_vers = re.findall(r'(\d+\.\d+\.\d+)', m.group(1))
    print("Modal SemVer versions detected:", sorted(set(modal_vers)))
