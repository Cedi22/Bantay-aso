"""
Synchronize index.php Table 1, Table 2, and Comparison Modal with Keep a Changelog (v1.1.0) and SemVer 2.0.0.
"""

import re

with open('qa-dashboard.html', 'r', encoding='utf-8') as f:
    qa_html = f.read()

with open('index.php', 'r', encoding='utf-8') as f:
    php_html = f.read()

# 1. Extract Table 1 from qa-dashboard.html and replace in index.php
t1_match = re.search(r'(<section class="changelogs-section" id="sectionChangelogs"[\s\S]*?</section>)', qa_html)
if t1_match:
    t1_content = t1_match.group(1)
    php_html = re.sub(r'<section class="changelogs-section" id="sectionChangelogs"[\s\S]*?</section>', t1_content, php_html)
    print("Table 1 replaced successfully.")
else:
    print("WARNING: Table 1 not found in qa-dashboard.html")

# 2. Update Table 2 Version Pills in index.php
# reportRow1 through reportRow9
php_html = re.sub(r'(<tr id="reportRow1"[\s\S]*?<span class="version-pill [^"]*">)1\.0(</span>)', r'\g<1>1.0.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow2"[\s\S]*?<span class="version-pill [^"]*">)1\.1(</span>)', r'\g<1>1.1.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow3"[\s\S]*?<span class="version-pill [^"]*">)1\.4(</span>)', r'\g<1>1.2.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow4"[\s\S]*?<span class="version-pill [^"]*">)1\.5(</span>)', r'\g<1>1.3.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow5"[\s\S]*?<span class="version-pill [^"]*">)1\.6(</span>)', r'\g<1>1.4.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow6"[\s\S]*?<span class="version-pill [^"]*">)1\.7(</span>)', r'\g<1>1.5.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow7"[\s\S]*?<span class="version-pill [^"]*">)1\.0(</span>)', r'\g<1>1.0.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow8"[\s\S]*?<span class="version-pill [^"]*">)1\.5(</span>)', r'\g<1>1.1.0\g<2>', php_html)
php_html = re.sub(r'(<tr id="reportRow9"[\s\S]*?<span class="version-pill [^"]*">)1\.2(</span>)', r'\g<1>1.2.0\g<2>', php_html)

# 3. Update Modal comparison in index.php
php_html = php_html.replace('Version Comparison: Version 1.1 ➔ Version 1.5', 'Version Comparison: Version 1.1.0 ➔ Version 1.5.0')
php_html = php_html.replace('(v1.1 vs v1.5)', '(v1.1.0 vs v1.5.0)')

# 4. diffPaneSemantic in index.php
kac_semantic_match = re.search(r'(<!-- Tab Pane 2: Semantic Changelog Summary[\s\S]*?</div>\s*</div>\s*</div>)', qa_html)
if kac_semantic_match:
    php_html = re.sub(
        r'<!-- Tab Pane 2: Semantic Changelog Summary[\s\S]*?</div>\s*</div>',
        kac_semantic_match.group(1),
        php_html
    )
    print("diffPaneSemantic replaced successfully.")

# 5. qaData object in index.php
php_html = re.sub(r'(1:\s*\{[\s\S]*?version:\s*)"1\.0"', r'\g<1>"1.0.0"', php_html)
php_html = re.sub(r'(2:\s*\{[\s\S]*?version:\s*)"1\.1"', r'\g<1>"1.1.0"', php_html)
php_html = re.sub(r'(3:\s*\{[\s\S]*?version:\s*)"1\.5"', r'\g<1>"1.5.0"', php_html)

with open('index.php', 'w', encoding='utf-8') as f:
    f.write(php_html)

print("index.php updated successfully!")
