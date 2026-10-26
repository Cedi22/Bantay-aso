import urllib.request
import urllib.parse
import ssl
import re
import http.cookiejar
import json

cj = http.cookiejar.CookieJar()
ctx = ssl._create_unverified_context()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))

# 1. Fetch Login Page
req = urllib.request.Request('https://hrms-dev.s3curity.net/login', headers={'User-Agent': 'Mozilla/5.0'})
res = opener.open(req)
login_html = res.read().decode('utf-8', errors='ignore')

csrf_match = re.search(r'name="_csrf"\s+value="([^"]+)"', login_html)
csrf_token = csrf_match.group(1) if csrf_match else ''
print("CSRF Token found:", bool(csrf_token))

# 2. Authenticate
post_data = urllib.parse.urlencode({
    '_csrf': csrf_token,
    'username': 'cedi',
    'password': '@@22162613S3curity'
}).encode('utf-8')

login_post = urllib.request.Request('https://hrms-dev.s3curity.net/login', data=post_data, headers={
    'User-Agent': 'Mozilla/5.0',
    'Content-Type': 'application/x-www-form-urlencoded',
    'Referer': 'https://hrms-dev.s3curity.net/login'
})

try:
    auth_res = opener.open(login_post)
    final_url = auth_res.geturl()
    print("Authenticated! Final URL:", final_url)
    dash_html = auth_res.read().decode('utf-8', errors='ignore')
    
    title = re.search(r'<title>(.*?)</title>', dash_html)
    print("Dashboard Title:", title.group(1) if title else "N/A")
    
    stylesheets = re.findall(r'<link[^>]+rel=["\']stylesheet["\'][^>]*>', dash_html)
    print("Dashboard Stylesheets:", stylesheets)

    # 3. Download live helix.css
    css_req = urllib.request.Request('https://hrms-dev.s3curity.net/css/helix.css', headers={'User-Agent': 'Mozilla/5.0'})
    css_data = opener.open(css_req).read().decode('utf-8', errors='ignore')
    print("Live helix.css size:", len(css_data))
    
    with open('scripts/live_helix.css', 'w', encoding='utf-8') as f:
        f.write(css_data)
        
    # Also fetch fonts.css
    fonts_req = urllib.request.Request('https://hrms-dev.s3curity.net/fonts/fonts.css', headers={'User-Agent': 'Mozilla/5.0'})
    fonts_data = opener.open(fonts_req).read().decode('utf-8', errors='ignore')
    with open('scripts/live_fonts.css', 'w', encoding='utf-8') as f:
        f.write(fonts_data)
    print("Live fonts.css size:", len(fonts_data))

    # Also save dashboard sample HTML
    with open('scripts/live_dashboard_sample.html', 'w', encoding='utf-8') as f:
        f.write(dash_html)
    print("Saved live dashboard sample HTML")

except Exception as e:
    print("Error during execution:", e)
