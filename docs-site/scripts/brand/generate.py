# -*- coding: utf-8 -*-
# Generuje vektorové soubory značky WireStack do docs-site/assets/brand/.
# Spuštění:  python3 docs-site/scripts/brand/generate.py
import io, os, shutil

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
BRAND = ROOT + '/docs-site/assets/brand'
GHDIR = BRAND + '/github'
for d in (GHDIR,):
    os.makedirs(d, exist_ok=True)

MEANDER = 'M52 12H20A10 10 0 0 0 20 32H44A10 10 0 0 1 44 52H12'


# Balíčky nemají vlastní ikonu — značka je pro celek jedna. Seznam tu zůstává
# jen kvůli textům v grafice (počet balíčků) a případným hlavičkám README.
PACKAGES = [
    'wire-suite', 'wire-core', 'wire-forms', 'wire-table', 'wire-sortable',
    'wire-panels', 'wire-admin', 'wire-boost', 'wire-module-auth', 'wire-module-users',
    'wire-module-settings', 'wire-module-audit', 'wire-module-notifications', 'wire-module-media',
]

# ---------- GitHub assets ----------
FONT = "Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif"
MONO = "'JetBrains Mono',ui-monospace,Menlo,monospace"

avatar = '''<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512" role="img" aria-label="WireStack">
  <rect width="512" height="512" fill="#131210"/>
  <g transform="translate(128 128) scale(4)">
    <path d="%s" fill="none" stroke="#f59e0b" stroke-width="9" stroke-linecap="round"/>
  </g>
</svg>
''' % MEANDER
io.open(GHDIR + '/avatar.svg', 'w', encoding='utf-8').write(avatar)

def wordmark(x, y, size, muted, strong):
    return ('<text x="%d" y="%d" font-family="%s" font-size="%d" letter-spacing="%.2f">'
            '<tspan font-weight="500" fill="%s">wire</tspan>'
            '<tspan font-weight="700" fill="%s">Stack</tspan></text>'
            % (x, y, FONT, size, -size * 0.035, muted, strong))

def deco(tx, ty, scale, opacity):
    return ('<g transform="translate(%d %d) scale(%s)" opacity="%s">'
            '<path d="%s" fill="none" stroke="#f59e0b" stroke-width="7" stroke-linecap="round"/></g>'
            % (tx, ty, scale, opacity, MEANDER))

def mark(tx, ty, scale, pad):
    return ('<g transform="translate(%d %d) scale(%s)">'
            '<path d="%s" fill="none" stroke="#f59e0b" stroke-width="7" stroke-linecap="round"/>'
            '<circle cx="52" cy="12" r="4.6" fill="%s"/><circle cx="12" cy="52" r="4.6" fill="%s"/></g>'
            % (tx, ty, scale, MEANDER, pad, pad))

social = '''<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="640" viewBox="0 0 1280 640" role="img" aria-label="WireStack">
  <rect width="1280" height="640" fill="#131210"/>
  %s
  %s
  %s
  <text x="220" y="378" font-family="%s" font-size="27" font-weight="500" fill="#a29a8c">Enterprise-grade Livewire components for Laravel</text>
  <path d="M96 452H1184" stroke="#2b2722" stroke-width="2"/>
  <text x="96" y="504" font-family="%s" font-size="21" fill="#7d766c">14 packages<tspan fill="#3c372f">  ·  </tspan>Laravel 12 / 13<tspan fill="#3c372f">  ·  </tspan>Livewire 4<tspan fill="#3c372f">  ·  </tspan>PHP 8.2+</text>
</svg>
''' % (deco(760, -70, '12', '.07'), mark(96, 232, '1.7', '#f4f1ea'),
       wordmark(220, 312, 84, '#a29a8c', '#f4f1ea'), FONT, MONO)
io.open(GHDIR + '/social-preview.svg', 'w', encoding='utf-8').write(social)

def banner(bg, muted, strong, rule, deco_op, pad):
    return '''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="280" viewBox="0 0 1200 280" role="img" aria-label="WireStack">
  <rect width="1200" height="280" fill="%s"/>
  <g transform="translate(880 -90) scale(7)" opacity="%s"><path d="%s" fill="none" stroke="#f59e0b" stroke-width="7" stroke-linecap="round"/></g>
  %s
  %s
  <text x="152" y="196" font-family="%s" font-size="19" fill="%s">forms  ·  tables  ·  sortable  ·  panels  ·  admin  ·  modules</text>
</svg>
''' % (bg, deco_op, MEANDER, mark(64, 96, '1.35', pad), wordmark(152, 158, 54, muted, strong), MONO, muted)

io.open(GHDIR + '/readme-banner-light.svg', 'w', encoding='utf-8').write(
    banner('#faf9f6', '#6d675c', '#1a1815', '#e7e3d9', '.10', '#18181b'))
io.open(GHDIR + '/readme-banner-dark.svg', 'w', encoding='utf-8').write(
    banner('#131210', '#a29a8c', '#f4f1ea', '#2b2722', '.10', '#f4f1ea'))


print('hotovo: značka + GitHub sada,', len(PACKAGES), 'balíčků pod jednou ikonou')
