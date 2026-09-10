# -*- coding: utf-8 -*-
# Vyrenderuje PNG rastry ze značkových SVG přes headless Chrome (kvůli webfontům).
# Spuštění:  python3 docs-site/scripts/brand/raster.py
import io, os, subprocess
B = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), 'assets', 'brand')
T = os.path.dirname(os.path.abspath(__file__))
CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
FONTS = ('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
         'family=Archivo:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">')

def render(svg_path, out_path, w, h, scale=2, transparent=False):
    svg = io.open(svg_path, encoding='utf-8').read()
    svg = svg.replace('width="%d"' % w, 'width="%d"' % w, 1)
    html = ('%s<style>html,body{margin:0;padding:0}svg{display:block;width:%dpx;height:%dpx}</style>\n%s'
            % (FONTS, w, h, svg))
    tmp = T + '/_r.html'
    io.open(tmp, 'w', encoding='utf-8').write(html)
    cmd = [CHROME, '--headless', '--disable-gpu', '--hide-scrollbars',
           '--window-size=%d,%d' % (w, h), '--force-device-scale-factor=%d' % scale,
           '--virtual-time-budget=5000', '--screenshot=' + out_path, tmp]
    if transparent:
        cmd.insert(4, '--default-background-color=00000000')
    subprocess.run(cmd, capture_output=True)
    return os.path.getsize(out_path) if os.path.exists(out_path) else 0

jobs = [
    (B + '/github/social-preview.svg',      B + '/github/social-preview.png',      1280, 640, 2, False),
    (B + '/github/readme-banner-light.svg', B + '/github/readme-banner-light.png', 1200, 280, 2, False),
    (B + '/github/readme-banner-dark.svg',  B + '/github/readme-banner-dark.png',  1200, 280, 2, False),
    (B + '/github/avatar.svg',              B + '/github/avatar.png',               512, 512, 2, False),
    (B + '/github/avatar.svg',              B + '/apple-touch-icon.png',            180, 180, 1, False),
    (B + '/favicon.svg',                    B + '/favicon-32.png',                   32,  32, 1, True),
    (B + '/favicon.svg',                    B + '/favicon-180.png',                 180, 180, 1, True),
]
for src, out, w, h, s, tr in jobs:
    print('%-34s %8d B  %dx%d' % (os.path.basename(out), render(src, out, w, h, s, tr), w * s, h * s))
