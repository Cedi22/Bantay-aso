import urllib.request
import re
import ssl

ctx = ssl._create_unverified_context()
req = urllib.request.Request('https://s3curity.net/', headers={'User-Agent': 'Mozilla/5.0'})
html = urllib.request.urlopen(req, context=ctx).read().decode('utf-8', errors='ignore')
styles = re.findall(r'<link[^>]+rel=[\'"]stylesheet[\'"][^>]*>', html)
print('Stylesheets on s3curity.net:', styles)

buttons = re.findall(r'<button[^>]*>.*?</button>', html, re.DOTALL)
print('Found buttons:', len(buttons))
for b in buttons[:5]:
    print('Button:', b[:100])

links = re.findall(r'<a[^>]+class=[\'"][^\'"]*btn[^\'"]*[\'"][^>]*>.*?</a>', html, re.DOTALL)
print('Found btn links:', len(links))
for l in links[:5]:
    print('Btn link:', l[:100])
