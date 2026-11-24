"""Worker: captures frames [a, b) at 30 fps.

    python render.py 0 1800          promo.html    -> frames/
    python render.py 0 1800 v        promo-v.html  -> frames-v/     (vertical)
    python render.py 0 1800 2        promo2.html   -> frames2/      (second video; add v for vertical)
"""
import os, sys
from playwright.sync_api import sync_playwright
D = os.path.dirname(os.path.abspath(__file__)); a, b = int(sys.argv[1]), int(sys.argv[2])
V = 'v' in sys.argv[3:]; N = '2' if '2' in sys.argv[3:] else ''
FR = D + '/frames' + N + ('-v' if V else ''); os.makedirs(FR, exist_ok=True)
with sync_playwright() as p:
    br = p.chromium.launch(channel='chrome', headless=True)
    pg = br.new_page(viewport={'width': 1080, 'height': 1920} if V else {'width': 1920, 'height': 1080})
    pg.goto('file://' + D + '/promo' + N + ('-v' if V else '') + '.html', wait_until='load'); pg.wait_for_timeout(1500)
    for i in range(a, b):
        f = '%s/%05d.jpg' % (FR, i)
        if os.path.exists(f): continue
        pg.evaluate('seek(%f)' % (i / 30)); pg.screenshot(path=f + '.tmp', type='jpeg', quality=96); os.rename(f + '.tmp', f)
    br.close()
print('done', a, b)
