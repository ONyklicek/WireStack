# -*- coding: utf-8 -*-
# Generuje soubory značky WireStack: vektory do docs-site/assets/brand/ a inline
# značku do docs-site/templates/partials/brand-mark.php.
# Spuštění:  python3 docs-site/scripts/brand/generate.py
# Rastry (PNG) potom:  python3 docs-site/scripts/brand/raster.py
#
# Pravidla značky (varianty, ochranná zóna, minimální velikost, písmo) jsou v
# docs-site/assets/brand/README.md. Tenhle soubor je jediný zdroj geometrie —
# šablona ani SVG se ručně neupravují.
import io, math, os

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
BRAND = ROOT + '/docs-site/assets/brand'
GHDIR = BRAND + '/github'
PARTIAL = ROOT + '/docs-site/templates/partials/brand-mark.php'
os.makedirs(GHDIR, exist_ok=True)

# ---------- Barvy značky ----------
# Teplá rampa žlutá → oranžová → červená, aby značka ladila s docs-site: jeho
# `--primary` je amber-500 #F59E0B a gradient webu (--brand-grad v site.css) jde
# stejnými tóny. Tvar a stínování jsou z grafické předlohy značky (brand sheet
# WireStack, mimo repozitář); její modro-fialové barvy se nepoužívají.
#
# Jedna rampa přes celou značku, shora dolů s mírným sklonem (vpravo nahoře
# nejsvětlejší), ne barva po pásech — přechod teče bez skoku mezi pásy. Opěrné
# body jsou odstíny Tailwindu (ADR 0005) a jejich středy.
RAMP_AXIS = (46, 4, 34, 60)          # x1 y1 x2 y2 v rámečku 64; šikmější osa barví levý konec
                                     # horního pásu už do indiga (zkoušeno: 52,5→24,60)
RAMP = [(0.0, '#FBBF24'),   # amber-400
        (0.25, '#F8AD17'),
        (0.42, '#F59E0B'),  # amber-500 = --primary docs-site
        (0.58, '#F7850F'),
        (0.72, '#F97316'),  # orange-500
        (0.87, '#F35A2C'),
        (1.0, '#EF4444')]   # red-500
# Vrstvy musí zůstat čitelné i bez skoku v barvě: klesající pásy (pod stoupajícími)
# jsou na rampě o kus dál a mírně ztmavené.
# (posun po rampě, ztmavení k RAMP_SHADE) pro pásy 1–5
BAND_TONE = [(0.0, 0.0), (0.05, 0.10), (0.0, 0.0), (0.05, 0.10), (0.0, 0.0)]
RAMP_SHADE = '#7C2D12'      # orange-900


def _hex(c):
    return [int(c[i:i + 2], 16) for i in (1, 3, 5)]


def _mix(c1, c2, t):
    return '#%02X%02X%02X' % tuple(round(a + (b - a) * t) for a, b in zip(_hex(c1), _hex(c2)))


def ramp_at(t):
    t = max(0.0, min(1.0, t))
    for (t1, c1), (t2, c2) in zip(RAMP, RAMP[1:]):
        if t <= t2:
            return _mix(c1, c2, (t - t1) / (t2 - t1))
    return RAMP[-1][1]


def band_stops(k):
    """Stopy gradientu pásu k na společné ose: rampa s posunem a ztmavením pásu."""
    shift, shade = BAND_TONE[k]
    return [(i / 10, _mix(ramp_at(i / 10 + shift), RAMP_SHADE, shade)) for i in range(11)]


# Stín, který pás nad ohybem vrhá na klesající pás pod sebou: nejtmavší přímo u
# hrany, do ~3.6 jednotky zmizí (změřeno na předloze). (pás, který stín přijímá, barva)
FOLD_SHADOWS = [(1, '#D35A0A'), (3, '#C2380F')]
SHADOW_BLUR, SHADOW_DROP = 1.8, 3.2
# Horní pás je mírně průsvitný: tam, kde pod ním leží 1. klesající pás, je o
# stupeň tmavší (klín mezi horní hranou klesajícího a spodní hranou horního pásu).
# Klín plynule nastupuje mezi x=30 a 34.5 — levý konec pásu je v předloze čistý
# a ostrá svislá hrana by tam byla vidět. (pás nahoře, pás pod ním, od x, do x, barva, krytí)
SHOW_THROUGH = [(0, 1, 30.0, 34.5, '#D35A0A', '.12')]

# GitHub sada: teplé neutrály (jako dosavadní sada), akcenty z rampy.
AMBER_LIGHT, AMBER, ORANGE, RED = '#FBBF24', '#F59E0B', '#F97316', '#EF4444'
INK = '#131210'        # tmavý podklad
INK_TEXT = '#1A1815'   # wordmark na světlém
PAPER = '#FAF9F6'      # světlý podklad
MUTED_LIGHT, MUTED_DARK = '#6D675C', '#A29A8C'

TAGLINE = 'BUILD · CONNECT · SIMPLIFY'
FONT = "Inter,'Helvetica Neue',Helvetica,Arial,sans-serif"
MONO = "'JetBrains Mono',ui-monospace,Menlo,monospace"

# ---------- Geometrie značky ----------
# Jedna stuha přeložená do cik-caku: pět pásů (stoupá, klesá, stoupá, klesá,
# stoupá). Každý pás je čtyřúhelník ohraničený svými dvěma hranami a na konci v
# ohybu *vnější hranou sousedního pásu* — tak vznikne v ohybu jeden společný
# zaoblený hrot. Horní konec je svisle useknutý, spodní je půlkruh kolmý na pás.
# Pořadí vrstev a stíny: viz mark_body().
#
# MĚŘENÍ. Každá hrana je samostatná přímka y = a + m·x nafitovaná z bodů, kde
# hrana v předloze přechází z bílé do kresby (výřez 150×180 px kolem velké
# značky: x 80–230, y 45–225 původního obrázku). Pásy NEMAJÍ jednotnou tloušťku
# ani sklon — shora 48, 40, 44, 44 a 36 px (zužují se jako v perspektivě); sklony
# 0.39–0.42. Zprůměrování to rozbilo (mělčí zářezy, posunuté ohyby), proto tu
# zůstávají naměřené hodnoty tak, jak jsou. Nakonec se vše převede do 64×64.
EDGES = {  # (a, m) v px výřezu; první odhad z bodů hran, pak doladěno optimalizací (viz níže)
    'top_top': (56.261, -0.405),  # horní hrana horního pásu
    'top_bot': (104.04, -0.41),   # spodní hrana horního pásu (pravý zářez 1, horní strana)
    'f1_top': (20.32, 0.42),      # pravý zářez 1, dolní strana
    'f1_bot': (62.59, 0.412),     # levý ohyb 1, spodní obrys
    # Za zářezem (vrchol 52.5, 83.85) se viditelná spodní hrana pásu 2 nad pásem 3
    # lomí: v předloze leží o 2.5–3 px níž, než by pokračovala hrana ohybu —
    # body (60,90) (72,95) (84,99.5) (96,~105). Uvnitř kresby, obrys nemění.
    'f1_bot_inner': (59.2, 0.47),
    'mid_top': (105.48, -0.405),  # levý ohyb 2, horní obrys
    'mid_bot': (149.87, -0.417),  # pravý ohyb 1, spodní obrys
    'f2_top': (65.661, 0.417),    # pravý zářez 2, dolní strana
    'f2_bot': (111.84, 0.401),    # levý ohyb 2, spodní obrys
    'bot_top': (159.96, -0.42),   # skrytá; určuje tloušťku (35.8) a tím poloměr půlkruhu
    'bot_bot': (195.76, -0.42),   # spodní hrana spodního pásu
}
X_TOP_END = 134.75                # svislý řez horního konce
X_BOTTOM_TIP = 45.0               # nejlevější bod spodního konce
# Poloměry rohů v px výřezu: hroty levých ohybů (1, 2), pravých ohybů (1, 2),
# vnitřní rohy (skryté pod sousedním pásem), horní konec (ostrý / tupý roh).
R_TIP_LEFT, R_TIP_RIGHT, R_INNER = (13.275, 12.9), (12.5, 12.688), 2.8
R_END_ACUTE, R_END_OBTUSE = 15.781, 9.203
# Doladění: coordinate descent nad všemi čísly výše, kritérium = počet pixelů,
# kde se silueta liší od předlohy (práh jasu 200 / 128). Z 387 px na 84 px
# z 27 000, tj. ~0.1 px průměrné odchylky obrysu — pod rozlišením předlohy.
# Převod výřez → rámeček 64×64: střed kresby (72, 88) → (32, 32).
SCALE, ORIGIN = 0.359, (72.0, 88.0)


def _line(name):
    a, m = EDGES[name]
    return ('l', a, m)


def _vert(x):
    return ('v', x)


def _round_end_cut():
    """Řez kolmý na spodní pás; oba rohy se zaoblením r = půl tloušťky dají půlkruh.

    Řez je tečnou půlkruhu v jeho vrcholu, takže střed kružnice leží o r dál
    uvnitř pásu — o r napravo od nejlevějšího bodu X_BOTTOM_TIP.
    """
    (a_top, m), (a_bot, _) = EDGES['bot_top'], EDGES['bot_bot']
    angle = math.atan(-m)
    r = (a_bot - a_top) * math.cos(angle) / 2
    cx = X_BOTTOM_TIP + r
    cy = (a_top + a_bot) / 2 + m * cx
    tx, ty = cx - r * math.cos(angle), cy + r * math.sin(angle)
    k = -1 / m
    return ('l', ty - k * tx, k), r


def _to_frame(pts):
    return [((x - ORIGIN[0]) * SCALE + 32, (y - ORIGIN[1]) * SCALE + 32) for x, y in pts]


def _meet(a, b):
    if a[0] == 'v':
        a, b = b, a
    if b[0] == 'v':
        return (b[1], a[1] + a[2] * b[1])
    x = (b[1] - a[1]) / (a[2] - b[2])
    return (x, a[1] + a[2] * x)


def _rounded(pts, radii):
    """Uzavřená cesta mnohoúhelníku se zaoblenými rohy (oblouk tečný k oběma hranám)."""
    corners = []
    n = len(pts)
    for i in range(n):
        p, a, b, r = pts[i], pts[i - 1], pts[(i + 1) % n], radii[i]
        v1 = (a[0] - p[0], a[1] - p[1])
        v2 = (b[0] - p[0], b[1] - p[1])
        l1, l2 = math.hypot(*v1), math.hypot(*v2)
        u1 = (v1[0] / l1, v1[1] / l1)
        u2 = (v2[0] / l2, v2[1] / l2)
        angle = math.acos(max(-1.0, min(1.0, u1[0] * u2[0] + u1[1] * u2[1])))
        t = r / math.tan(angle / 2)
        start = (p[0] + u1[0] * t, p[1] + u1[1] * t)
        end = (p[0] + u2[0] * t, p[1] + u2[1] * t)
        sweep = 1 if (u1[0] * u2[1] - u1[1] * u2[0]) < 0 else 0
        corners.append((start, end, r, sweep))
    d = 'M%.2f %.2f' % corners[0][0]
    for i, (start, end, r, sweep) in enumerate(corners):
        if i:
            d += 'L%.2f %.2f' % start
        d += 'A%.2f %.2f 0 0 %d %.2f %.2f' % (r, r, sweep, end[0], end[1])
    return d + 'Z'


def bands():
    L = _line
    m, i = _meet, R_INNER
    (l1, l2), (r1, r2) = R_TIP_LEFT, R_TIP_RIGHT
    cut, r_end = _round_end_cut()
    shapes = [
        # 1 stoupá: vpravo svislý konec, vlevo řez spodní hranou pásu 2
        ([m(L('top_top'), _vert(X_TOP_END)), m(L('top_bot'), _vert(X_TOP_END)),
          m(L('top_bot'), L('f1_bot')), m(L('top_top'), L('f1_bot'))], [R_END_ACUTE, R_END_OBTUSE, i, l1]),
        # 2 klesá: vlevo řez horní hranou pásu 1, vpravo spodní hranou pásu 3
        ([m(L('f1_top'), L('top_top')), m(L('f1_top'), L('mid_bot')), m(L('f1_bot_inner'), L('mid_bot')),
          m(L('f1_bot_inner'), L('f1_bot')), m(L('f1_bot'), L('top_top'))], [i, r1, i, 0.01, l1]),
        # 3 stoupá: vpravo řez horní hranou pásu 2, vlevo spodní hranou pásu 4
        ([m(L('mid_top'), L('f1_top')), m(L('mid_bot'), L('f1_top')),
          m(L('mid_bot'), L('f2_bot')), m(L('mid_top'), L('f2_bot'))], [i, r1, i, l2]),
        # 4 klesá: vlevo řez horní hranou pásu 3, vpravo spodní hranou pásu 5
        ([m(L('f2_top'), L('mid_top')), m(L('f2_top'), L('bot_bot')),
          m(L('f2_bot'), L('bot_bot')), m(L('f2_bot'), L('mid_top'))], [i, r2, i, l2]),
        # 5 stoupá: vpravo řez horní hranou pásu 4, vlevo půlkruh
        ([m(L('bot_top'), L('f2_top')), m(L('bot_bot'), L('f2_top')),
          m(L('bot_bot'), cut), m(L('bot_top'), cut)], [i, r2, r_end - 0.01, r_end - 0.01]),
    ]
    return [_rounded(_to_frame(pts), [r * SCALE for r in radii]) for pts, radii in shapes]


BANDS = bands()


# Pásy leží jako přeložený papír: každý další segment je POD předchozím, takže se
# kreslí odspodu (pás 5) nahoru (pás 1). Stín pásu k dopadá na pás k+1 a kreslí
# se hned po něm, před pásem k, který ho pak shora ohraničí ostrou hranou.
MONO_GAP = 1.4  # šířka mezery mezi vrstvami v mono variantě (jednotky rámečku)


def mark_defs(prefix, mono=False, shade=True):
    if mono:
        # Jedna barva nemá stíny, kterými by vrstvy oddělila — černá na černé
        # zmizí. Vrstvy proto dělí úzká mezera: maska kreslí pásy odspodu a každý
        # nejdřív obtáhne černě (vyřízne kolem sebe mezeru v pásech pod ním), pak
        # se vyplní bíle.
        layers = ''.join('<path d="%s" fill="#fff" stroke="#000" stroke-width="%s" paint-order="stroke"/>'
                         % (BANDS[k], MONO_GAP * 2) for k in range(len(BANDS) - 1, -1, -1))
        return '<mask id="%s-m" maskUnits="userSpaceOnUse" x="0" y="0" width="64" height="64">%s</mask>' % (prefix, layers)
    out = []
    x1, y1, x2, y2 = RAMP_AXIS
    for k in range(len(BANDS)):
        out.append('<linearGradient id="%s-g%d" gradientUnits="userSpaceOnUse" x1="%s" y1="%s" x2="%s" y2="%s">%s</linearGradient>'
                   % (prefix, k, x1, y1, x2, y2, ''.join('<stop offset="%.1f" stop-color="%s"/>' % st
                                                         for st in band_stops(k))))
    if shade:
        out.append('<filter id="%s-sh" x="-20%%" y="-20%%" width="140%%" height="140%%">'
                   '<feGaussianBlur stdDeviation="%s"/></filter>' % (prefix, SHADOW_BLUR))
        clipped = sorted({k for k, _ in FOLD_SHADOWS} | {t[0] for t in SHOW_THROUGH})
        out += ['<clipPath id="%s-c%d"><path d="%s"/></clipPath>' % (prefix, k, BANDS[k]) for k in clipped]
        out += ['<linearGradient id="%s-x%d" gradientUnits="userSpaceOnUse" x1="%s" y1="0" x2="%s" y2="0">'
                '<stop offset="0" stop-color="%s" stop-opacity="0"/><stop offset="1" stop-color="%s" stop-opacity="%s"/>'
                '</linearGradient>' % (prefix, n, t[2], t[3], t[4], t[4], t[5]) for n, t in enumerate(SHOW_THROUGH)]
    return ''.join(out)


def mark_body(prefix, mono=False, shade=True):
    if mono:
        return '<rect width="64" height="64" fill="currentColor" mask="url(#%s-m)"/>' % prefix
    shadows = dict(FOLD_SHADOWS)
    out = []
    for k in range(len(BANDS) - 1, -1, -1):
        out.append('<path d="%s" fill="url(#%s-g%d)"/>' % (BANDS[k], prefix, k))
        if shade and k in shadows:
            out.append('<g clip-path="url(#%s-c%d)"><path d="%s" transform="translate(0 %s)" fill="%s" '
                       'filter="url(#%s-sh)"/></g>' % (prefix, k, BANDS[k - 1], SHADOW_DROP, shadows[k], prefix))
        for n, (top, under, *_rest) in enumerate(SHOW_THROUGH if shade else []):
            if top == k:
                out.append('<path d="%s" clip-path="url(#%s-c%d)" fill="url(#%s-x%d)"/>'
                           % (BANDS[under], prefix, top, prefix, n))
    return ''.join(out)


def mark_svg(mono=False, shade=True, size=64):
    return ('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 64 64" role="img" '
            'aria-label="WireStack"><defs>%s</defs>%s</svg>\n'
            % (size, size, mark_defs('ws', mono, shade), mark_body('ws', mono, shade)))


def write(path, text):
    io.open(path, 'w', encoding='utf-8').write(text)


# Plná — výchozí, na světlém i tmavém podkladu (gradient je středně tmavý).
write(BRAND + '/wirestack-mark.svg', mark_svg())
# Tmavá — na tmavém podkladu stejná kresba; soubor zůstává kvůli odkazům.
write(BRAND + '/wirestack-mark-dark.svg', mark_svg())
# Mono — jedna barva přes currentColor; vrstvy dělí úzká mezera (viz mark_defs).
write(BRAND + '/wirestack-mark-mono.svg', mark_svg(mono=True))
# Micro — 16–24 px: bez stínů, rozmazání by se v pár pixelech slilo do šmouhy.
write(BRAND + '/wirestack-mark-micro.svg', mark_svg(shade=False, size=16))
write(BRAND + '/favicon.svg', mark_svg())

# ---------- Inline značka v hlavičce dokumentace ----------
write(PARTIAL, '''<?php
/**
 * The WireStack mark in the sidebar header.
 *
 * GENERATED by `docs-site/scripts/brand/generate.py` — edit the geometry there,
 * never here. Inline rather than an `<img>` so it arrives with the page and costs
 * no request; the ids are fixed because the partial is included once per page.
 *
 * Variants, clear space and minimum size: `docs-site/assets/brand/README.md`.
 */
?>
<span class="brand-mark" aria-hidden="true">
    <svg viewBox="0 0 64 64"><defs>%s</defs>%s</svg>
</span>
''' % (mark_defs('ws-mark'), mark_body('ws-mark')))


# Balíčky nemají vlastní ikonu — značka je pro celek jedna.
PACKAGES = [
    'wire-suite', 'wire-core', 'wire-forms', 'wire-table', 'wire-sortable',
    'wire-panels', 'wire-admin', 'wire-boost', 'wire-module-auth', 'wire-module-users',
    'wire-module-settings', 'wire-module-audit', 'wire-module-notifications', 'wire-module-media',
]


# ---------- GitHub sada ----------
def placed_mark(tx, ty, scale):
    return '<g transform="translate(%s %s) scale(%s)">%s</g>' % (tx, ty, scale, mark_body('ws'))


def wordmark(x, y, size, fill):
    return ('<text x="%d" y="%d" font-family="%s" font-size="%d" font-weight="700" '
            'letter-spacing="%.2f" fill="%s">WireStack</text>' % (x, y, FONT, size, -size * 0.03, fill))


def tagline(x, y, size, fill):
    return ('<text x="%d" y="%d" font-family="%s" font-size="%d" font-weight="500" '
            'letter-spacing="%.2f" fill="%s">%s</text>' % (x, y, FONT, size, size * 0.2, fill, TAGLINE))


def glows(w, h, strength):
    return ('<radialGradient id="glowA" cx="%d" cy="0" r="%d" gradientUnits="userSpaceOnUse">'
            '<stop offset="0" stop-color="%s" stop-opacity="%s"/><stop offset="1" stop-color="%s" stop-opacity="0"/></radialGradient>'
            '<radialGradient id="glowB" cx="%d" cy="%d" r="%d" gradientUnits="userSpaceOnUse">'
            '<stop offset="0" stop-color="%s" stop-opacity="%s"/><stop offset="1" stop-color="%s" stop-opacity="0"/></radialGradient>'
            % (w * 0.82, h * 0.9, AMBER, strength, AMBER, w * 0.98, h, h * 0.9, RED, strength, RED))


BAR = ('<linearGradient id="bar" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="%s"/>'
       '<stop offset=".45" stop-color="%s"/><stop offset=".75" stop-color="%s"/><stop offset="1" stop-color="%s"/></linearGradient>'
       % (AMBER_LIGHT, AMBER, ORANGE, RED))

avatar = '''<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512" role="img" aria-label="WireStack">
  <defs>%s</defs>
  <rect width="512" height="512" fill="%s"/>
  %s
</svg>
''' % (mark_defs('ws'), INK, placed_mark(96, 96, 5))
write(GHDIR + '/avatar.svg', avatar)

social = '''<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="640" viewBox="0 0 1280 640" role="img" aria-label="WireStack">
  <defs>%s%s%s</defs>
  <rect width="1280" height="640" fill="%s"/>
  <rect width="1280" height="640" fill="url(#glowA)"/>
  <rect width="1280" height="640" fill="url(#glowB)"/>
  %s
  %s
  %s
  <text x="96" y="420" font-family="%s" font-size="28" font-weight="400" fill="#C9C2B4">Enterprise-grade Livewire components for Laravel</text>
  <rect x="96" y="470" width="1088" height="6" rx="3" fill="url(#bar)"/>
  <text x="96" y="530" font-family="%s" font-size="21" fill="#7D766C">14 packages<tspan fill="#3C372F">  ·  </tspan>Laravel 12 / 13<tspan fill="#3C372F">  ·  </tspan>Livewire 4<tspan fill="#3C372F">  ·  </tspan>PHP 8.2+</text>
</svg>
''' % (mark_defs('ws'), glows(1280, 640, '.28'), BAR, INK,
       placed_mark(84, 150, '2.6'),
       wordmark(272, 268, 112, '#F4F1EA'), tagline(280, 330, 26, MUTED_DARK), FONT, MONO)
write(GHDIR + '/social-preview.svg', social)


def banner(bg, word, tag, line, glow):
    return '''<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="280" viewBox="0 0 1200 280" role="img" aria-label="WireStack">
  <defs>%s%s%s</defs>
  <rect width="1200" height="280" fill="%s"/>
  <rect width="1200" height="280" fill="url(#glowA)"/>
  <rect width="1200" height="280" fill="url(#glowB)"/>
  %s
  %s
  %s
  <text x="196" y="214" font-family="%s" font-size="18" fill="%s">forms  ·  tables  ·  sortable  ·  panels  ·  admin  ·  modules</text>
  <rect x="0" y="274" width="1200" height="6" fill="url(#bar)"/>
</svg>
''' % (mark_defs('ws'), glows(1200, 280, glow), BAR, bg,
       placed_mark(48, 52, '2.1'),
       wordmark(192, 132, 76, word), tagline(198, 174, 19, tag), MONO, line)


write(GHDIR + '/readme-banner-light.svg', banner(PAPER, INK_TEXT, MUTED_LIGHT, '#A39C90', '.12'))
write(GHDIR + '/readme-banner-dark.svg', banner(INK, '#F4F1EA', MUTED_DARK, '#7D766C', '.20'))

print('hotovo: značka (4 varianty + favicon + inline partial) + GitHub sada,', len(PACKAGES), 'balíčků pod jednou ikonou')
