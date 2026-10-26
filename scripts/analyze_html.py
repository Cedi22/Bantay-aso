import re

def analyze(filename):
    print(f"=== {filename} ===")
    lines = open(filename, 'r', encoding='utf-8').readlines()
    for i, l in enumerate(lines):
        line = l.strip()
        if re.search(r'<(section|table|header|div\s+class=".*modal|select)', line, re.I):
            id_match = re.search(r'id=["\']([^"\']+)["\']', line)
            class_match = re.search(r'class=["\']([^"\']+)["\']', line)
            id_str = f" id={id_match.group(1)}" if id_match else ""
            cls_str = f" class={class_match.group(1)}" if class_match else ""
            tag = line.split()[0].replace('<', '').replace('>', '')
            print(f"L{i+1}: <{tag}{id_str}{cls_str}>")

analyze("qa-dashboard.html")
print("\n")
analyze("index.php")
