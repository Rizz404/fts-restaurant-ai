"""Generates the flat vector artwork of the guest stage in public/assets/stage/.

    scenes/{welcome,menu,picks,reservation,order,bookings,info}.svg   1920x1080, drawn with object-fit: cover in mind
    character/host-{greeting,menu,notepad}.svg                        800x1200 cut-outs, flat at the bottom edge
    character/host-avatar.svg                                         the round face used beside narration and chat

Self-made for FTS Restaurant AI (no third-party artwork, no license needed). Illustrations may use literal
fills because the stylesheet is not inherited by an <img>. Re-run after changing the drawings:

    python scripts/generate-stage-assets.py
"""

from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / 'public' / 'assets' / 'stage'

# Dining room at night.
WALL = '#3b2417'
WALL_DEEP = '#2a170f'
WAINSCOT = '#4b2d1d'
WAINSCOT_LIGHT = '#5d3924'
FLOOR = '#5a3823'
FLOOR_DARK = '#46291a'
BRASS = '#d9a441'
BRASS_DARK = '#a8761f'
CREAM = '#f4e4c4'
CREAM_DARK = '#dcc59a'
TERRACOTTA = '#b4472b'
WINE = '#7a2e3b'
HERB = '#5fa777'
HERB_DARK = '#2f6b45'
NIGHT = '#16223a'
NIGHT_LIGHT = '#27406b'
GLOW = '#ffcf7a'
CHALK = '#f1ead8'
BLACKBOARD = '#1d2420'
STEEL = '#aeb5b8'
STEEL_DARK = '#80888c'
COPPER = '#c0743a'
SKIN = '#e9b48c'
SKIN_SHADE = '#d69b72'
HAIR = '#241610'
WHITE = '#fbf7ee'
VEST = '#3a1f2a'
INK = '#150e0a'

W, H = 1920, 1080
HORIZON = 800


def svg(width, height, body, title, defs=''):
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {height}" '
        f'width="{width}" height="{height}" role="img" aria-label="{title}">\n'
        f'<title>{title}</title>\n<defs>{defs}</defs>\n{body}\n</svg>\n'
    )


def rect(x, y, w, h, fill, rx=0, extra=''):
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{fill}" {extra}/>'


def circle(cx, cy, r, fill, extra=''):
    return f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fill}" {extra}/>'


def ellipse(cx, cy, rx, ry, fill, extra=''):
    return f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" fill="{fill}" {extra}/>'


def path(d, fill, extra=''):
    return f'<path d="{d}" fill="{fill}" {extra}/>'


def line(x1, y1, x2, y2, stroke, width=2, extra=''):
    return f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{stroke}" stroke-width="{width}" stroke-linecap="round" {extra}/>'


DEFS = (
    f'<linearGradient id="wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{WALL_DEEP}"/><stop offset="1" stop-color="{WALL}"/></linearGradient>'
    f'<linearGradient id="floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{FLOOR}"/><stop offset="1" stop-color="{FLOOR_DARK}"/></linearGradient>'
    f'<linearGradient id="night" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{NIGHT}"/><stop offset="1" stop-color="{NIGHT_LIGHT}"/></linearGradient>'
    f'<radialGradient id="glow" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="{GLOW}" stop-opacity="0.55"/><stop offset="1" stop-color="{GLOW}" stop-opacity="0"/></radialGradient>'
    f'<radialGradient id="vignette" cx="0.5" cy="0.45" r="0.75"><stop offset="0.55" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.45"/></radialGradient>'
)


def room(wainscot=True):
    """Back wall, wainscot and a wooden floor with perspective planks."""
    parts = [
        rect(0, 0, W, HORIZON, 'url(#wall)'),
        rect(0, HORIZON, W, H - HORIZON, 'url(#floor)'),
    ]
    if wainscot:
        parts += [
            rect(0, 560, W, HORIZON - 560, WAINSCOT),
            rect(0, 552, W, 12, BRASS_DARK),
        ]
        for x in range(40, W, 160):
            parts.append(rect(x, 590, 120, 180, WAINSCOT_LIGHT, 8, 'opacity="0.55"'))
    parts.append(rect(0, HORIZON - 8, W, 14, FLOOR_DARK))
    for i in range(-6, 14):
        parts.append(line(i * 170 + 120, HORIZON + 6, i * 300 - 360, H, FLOOR_DARK, 2, 'opacity="0.55"'))
    return ''.join(parts)


def finish():
    """Warm vignette over everything so the text and the host stand out."""
    return rect(0, 0, W, H, 'url(#vignette)')


def pendant(x, drop=210, size=1.0):
    r = 54 * size
    return (
        line(x, 0, x, drop - 36, INK, 3)
        + path(f'M{x - r} {drop} Q{x - r} {drop - 62 * size} {x} {drop - 62 * size} Q{x + r} {drop - 62 * size} {x + r} {drop} Z', BRASS)
        + path(f'M{x - r} {drop} L{x + r} {drop} L{x + r - 8} {drop + 10} L{x - r + 8} {drop + 10} Z', BRASS_DARK)
        + ellipse(x, drop + 14, r * 0.55, 10, GLOW)
        + ellipse(x, drop + 150 * size, 190 * size, 150 * size, 'url(#glow)')
    )


def arch_window(x, y, w, h, stars=True):
    r = w / 2
    frame = f'M{x} {y + h} L{x} {y + r} A{r} {r} 0 0 1 {x + w} {y + r} L{x + w} {y + h} Z'
    inner = f'M{x + 14} {y + h} L{x + 14} {y + r} A{r - 14} {r - 14} 0 0 1 {x + w - 14} {y + r} L{x + w - 14} {y + h} Z'
    parts = [path(frame, BRASS_DARK), path(inner, 'url(#night)')]
    # city skyline
    base = y + h
    for i, (bx, bw, bh) in enumerate([(24, 54, 120), (84, 40, 170), (130, 62, 100), (198, 46, 150), (250, 58, 118), (314, 40, 170)]):
        if bx + bw < w - 14:
            parts.append(rect(x + bx, base - bh, bw, bh, '#0f1830', extra='opacity="0.9"'))
            for wy in range(base - bh + 14, base - 14, 26):
                parts.append(rect(x + bx + 10, wy, 8, 10, GLOW, 1, 'opacity="0.8"'))
    if stars:
        for sx, sy in [(60, 90), (150, 60), (240, 110), (300, 70), (110, 150)]:
            if sx < w - 20:
                parts.append(circle(x + sx, y + sy, 2.2, CREAM, 'opacity="0.9"'))
    parts.append(circle(x + w - 90, y + 110, 20, CREAM, 'opacity="0.95"'))
    parts.append(rect(x + w / 2 - 3, y + r - 40, 6, h - r + 40, BRASS_DARK))
    parts.append(rect(x + 14, y + h * 0.62, w - 28, 6, BRASS_DARK))
    parts.append(rect(x - 12, y + h, w + 24, 14, BRASS_DARK, 4))
    return ''.join(parts)


def table(cx, y, w=300, cloth=CREAM, candle_on=True):
    top = ellipse(cx, y, w / 2, 34, cloth)
    drape = path(f'M{cx - w / 2} {y} Q{cx - w / 2} {y + 120} {cx - w / 2 + 30} {y + 150} L{cx + w / 2 - 30} {y + 150} Q{cx + w / 2} {y + 120} {cx + w / 2} {y} Z', cloth, 'opacity="0.96"')
    fold = ''.join(line(cx + k, y + 20, cx + k * 1.05, y + 146, CREAM_DARK, 3, 'opacity="0.6"') for k in (-90, -30, 30, 90))
    shadow = ellipse(cx, y + 156, w / 2 + 20, 18, '#000', 'opacity="0.35"')
    items = ''
    if candle_on:
        items += candle(cx, y - 6)
    return shadow + drape + fold + top + items


def candle(x, y):
    return (
        rect(x - 7, y - 44, 14, 44, CREAM)
        + path(f'M{x} {y - 78} C{x - 12} {y - 62} {x - 8} {y - 50} {x} {y - 46} C{x + 8} {y - 50} {x + 12} {y - 62} {x} {y - 78} Z', GLOW)
        + ellipse(x, y - 60, 60, 54, 'url(#glow)')
    )


def plate(cx, cy, r=46, food=TERRACOTTA):
    return ellipse(cx, cy, r, r * 0.34, CREAM) + ellipse(cx, cy - 2, r * 0.66, r * 0.22, CREAM_DARK) + ellipse(cx, cy - 6, r * 0.38, r * 0.14, food)


def glass(x, y, h=64):
    return (
        path(f'M{x - 14} {y - h} L{x + 14} {y - h} Q{x + 12} {y - 20} {x} {y - 14} Q{x - 12} {y - 20} {x - 14} {y - h} Z', '#cfe6f3', 'opacity="0.55"')
        + rect(x - 2, y - 16, 4, 16, '#cfe6f3', extra='opacity="0.7"')
        + ellipse(x, y, 14, 4, '#cfe6f3', 'opacity="0.7"')
    )


def bottle(x, y, h=96, color=HERB_DARK):
    return rect(x - 15, y - h, 30, h, color, 8) + rect(x - 6, y - h - 28, 12, 32, color, 3) + rect(x - 15, y - h * 0.6, 30, h * 0.28, CREAM, 2, 'opacity="0.85"')


def plant(x, base_y, scale=1.0):
    s = scale
    parts = [
        path(f'M{x - 40 * s} {base_y - 70 * s} L{x + 40 * s} {base_y - 70 * s} L{x + 30 * s} {base_y} L{x - 30 * s} {base_y} Z', TERRACOTTA),
        rect(x - 46 * s, base_y - 82 * s, 92 * s, 16 * s, '#cd5c3c', 5),
    ]
    for ang, ln in [(-50, 150), (-25, 190), (0, 210), (25, 190), (50, 150), (-72, 100), (72, 100)]:
        import math

        a = math.radians(ang - 90)
        ex = x + math.cos(a) * ln * s
        ey = base_y - 82 * s + math.sin(a) * ln * s
        mx = x + math.cos(a) * ln * s * 0.5 + (14 * s if ang >= 0 else -14 * s)
        my = base_y - 82 * s + math.sin(a) * ln * s * 0.5
        parts.append(path(f'M{x} {base_y - 82 * s} Q{mx} {my - 20 * s} {ex} {ey} Q{mx + 18 * s} {my + 10 * s} {x} {base_y - 82 * s} Z', HERB, 'opacity="0.95"'))
    return ''.join(parts)


def frame(x, y, w, h, inner):
    return rect(x, y, w, h, BRASS_DARK, 6) + rect(x + 10, y + 10, w - 20, h - 20, WALL_DEEP, 3) + inner


def clock(cx, cy, r=44):
    return (
        circle(cx, cy, r, BRASS_DARK)
        + circle(cx, cy, r - 7, CREAM)
        + line(cx, cy, cx, cy - r * 0.55, INK, 4)
        + line(cx, cy, cx + r * 0.38, cy + r * 0.12, INK, 3)
        + circle(cx, cy, 4, INK)
    )


def shelf(x, y, w, items):
    return rect(x, y, w, 14, WAINSCOT_LIGHT, 3) + rect(x, y + 14, w, 6, INK, 0, 'opacity="0.45"') + items


def chair(x, y, color=WINE):
    return (
        rect(x - 38, y - 150, 76, 100, color, 14)
        + rect(x - 44, y - 56, 88, 26, color, 10)
        + rect(x - 36, y - 30, 8, 70, INK)
        + rect(x + 28, y - 30, 8, 70, INK)
    )


def neon(x, y, text, color=GLOW, size=64):
    return (
        f'<text x="{x}" y="{y}" font-family="Georgia, serif" font-size="{size}" font-weight="700" letter-spacing="8" '
        f'text-anchor="middle" fill="none" stroke="{color}" stroke-width="3">{text}</text>'
        f'<text x="{x}" y="{y}" font-family="Georgia, serif" font-size="{size}" font-weight="700" letter-spacing="8" '
        f'text-anchor="middle" fill="{color}" opacity="0.28" filter="url(#blur)">{text}</text>'
    )


BLUR = '<filter id="blur" x="-20%" y="-50%" width="140%" height="200%"><feGaussianBlur stdDeviation="8"/></filter>'


# ------------------------------------------------------------------ scenes


def welcome():
    p = [room()]
    p.append(arch_window(220, 120, 360, 520))
    p.append(arch_window(660, 120, 360, 520, stars=False))
    p += [pendant(260, 190), pendant(700, 230, 1.1), pendant(1140, 190), pendant(1580, 230, 1.1)]
    # shelf with bottles on the right wall
    bottles = ''.join(bottle(1270 + i * 62, 410, 80 + (i % 3) * 22, [HERB_DARK, WINE, BRASS_DARK, HERB_DARK][i % 4]) for i in range(6))
    p.append(shelf(1240, 410, 420, bottles))
    p.append(shelf(1240, 300, 420, ''.join(plate(1320 + i * 120, 296, 38, [TERRACOTTA, HERB, BRASS][i % 3]) for i in range(3))))
    p.append(frame(1720, 120, 130, 170, rect(1736, 140, 98, 130, TERRACOTTA, 2) + circle(1785, 205, 28, CREAM)))
    p.append(clock(1790, 380, 40))
    # set tables in the mid ground
    p.append(table(430, 800, 330))
    p.append(chair(300, 880) + chair(560, 880, TERRACOTTA))
    p.append(plate(380, 786, 40) + plate(490, 786, 40, HERB) + glass(430, 776))
    p.append(table(930, 830, 300))
    p.append(plate(880, 816, 38, BRASS) + plate(985, 816, 38) + glass(930, 806))
    p.append(plant(70, 800, 1.1))
    p.append(finish())
    return svg(W, H, ''.join(p), 'Dining room', DEFS)


def menu():
    p = [room()]
    # big chalkboard menu
    p.append(rect(380, 70, 900, 520, BRASS_DARK, 12))
    p.append(rect(398, 88, 864, 484, BLACKBOARD, 6))
    p.append(line(430, 150, 830, 150, CHALK, 5, 'opacity="0.85"'))
    for i, (w1, w2) in enumerate([(380, 90), (330, 90), (420, 90), (300, 90), (360, 90), (260, 90)]):
        y = 220 + i * 56
        p.append(line(430, y, 430 + w1, y, CHALK, 4, 'opacity="0.55"'))
        p.append(line(1160 - w2, y, 1160, y, CHALK, 4, 'opacity="0.55"'))
    p.append(circle(1100, 150, 28, 'none', f'stroke="{CHALK}" stroke-width="4" opacity="0.7"'))
    p.append(path('M1085 150 l10 12 l22 -26', 'none', f'stroke="{CHALK}" stroke-width="5" stroke-linecap="round" opacity="0.8"'))
    # pass-through window with warm light
    p.append(rect(1360, 150, 420, 300, BRASS_DARK, 10))
    p.append(rect(1376, 166, 388, 268, '#f0b45a', 6))
    p.append(ellipse(1570, 300, 220, 140, 'url(#glow)'))
    for i in range(5):
        x = 1410 + i * 74
        p.append(line(x, 166, x, 230, STEEL_DARK, 3))
        p.append(path(f'M{x - 26} 230 h52 l-8 36 h-36 Z', COPPER))
    p.append(rect(1340, 450, 460, 28, WAINSCOT_LIGHT, 4))
    p.append(plate(1470, 440, 44) + plate(1570, 440, 44, HERB) + plate(1670, 440, 44, BRASS))
    # hanging pans and pendants
    for i, x in enumerate([180, 250, 320]):
        p.append(line(x, 0, x, 110 + i * 24, INK, 3))
        p.append(circle(x, 150 + i * 24, 42, COPPER) + circle(x, 150 + i * 24, 30, BRASS_DARK))
    p += [pendant(700, 180), pendant(1240, 160, 0.9), pendant(1700, 180)]
    p.append(table(560, 840, 340, WINE, candle_on=False))
    p.append(plate(480, 826, 40) + plate(600, 826, 40, HERB) + bottle(700, 826, 70, WINE))
    p.append(plant(1860, 800, 1.0))
    p.append(finish())
    return svg(W, H, ''.join(p), 'Menu', DEFS)


def picks():
    p = [room()]
    # long kitchen pass with heat lamps
    p.append(rect(0, 520, W, 40, STEEL))
    p.append(rect(0, 560, W, 240, STEEL_DARK))
    p.append(rect(0, 560, W, 14, '#000', extra='opacity="0.25"'))
    for x in range(60, W, 240):
        p.append(rect(x, 600, 190, 150, STEEL, 10, 'opacity="0.55"'))
    for i, x in enumerate([260, 620, 980, 1340]):
        p.append(line(x, 0, x, 300, INK, 4))
        p.append(path(f'M{x - 70} 330 Q{x - 70} 270 {x} 270 Q{x + 70} 270 {x + 70} 330 Z', COPPER))
        p.append(ellipse(x, 340, 52, 8, GLOW))
        p.append(ellipse(x, 480, 130, 150, 'url(#glow)'))
    # cloches on the pass
    for x, scale in [(300, 1.0), (680, 1.15), (1060, 1.0), (1400, 1.1)]:
        p.append(ellipse(x, 524, 90 * scale, 12, STEEL_DARK))
        p.append(path(f'M{x - 80 * scale} 520 Q{x - 80 * scale} {430 - 40 * scale} {x} {430 - 40 * scale} Q{x + 80 * scale} {430 - 40 * scale} {x + 80 * scale} 520 Z', STEEL))
        p.append(circle(x, 430 - 48 * scale, 10, STEEL_DARK))
        p.append(path(f'M{x - 52 * scale} 500 Q{x - 52 * scale} {460 - 20 * scale} {x - 12 * scale} {450 - 20 * scale}', 'none', 'stroke="#fff" stroke-width="5" stroke-linecap="round" opacity="0.45"'))
    # star sign
    p.append(path('M1600 90 l22 48 l52 6 l-38 36 l10 52 l-46 -26 l-46 26 l10 -52 l-38 -36 l52 -6 Z', BRASS))
    p.append(ellipse(1630, 170, 150, 120, 'url(#glow)'))
    p.append(rect(150, 70, 360, 220, BRASS_DARK, 8) + rect(164, 84, 332, 192, BLACKBOARD, 4))
    for i in range(4):
        p.append(line(190, 130 + i * 38, 190 + 220 - i * 24, 130 + i * 38, CHALK, 4, 'opacity="0.55"'))
    p.append(floor_shadow())
    p.append(finish())
    return svg(W, H, ''.join(p), "Chef's picks", DEFS)


def floor_shadow():
    return rect(0, HORIZON, W, 120, '#000', extra='opacity="0.18"')


def reservation():
    p = [room()]
    p.append(arch_window(100, 110, 380, 540))
    p += [pendant(700, 200), pendant(1160, 230, 1.1), pendant(1620, 200)]
    for cx, y, c in [(700, 820, CREAM), (1150, 850, CREAM), (1600, 820, CREAM)]:
        p.append(table(cx, y, 330, c))
        p.append(plate(cx - 70, y - 14, 38) + plate(cx + 70, y - 14, 38, HERB) + glass(cx, y - 24))
        # reserved tent card
        p.append(path(f'M{cx - 40} {y - 8} l40 -48 l40 48 Z', WHITE) + rect(cx - 20, y - 36, 40, 5, TERRACOTTA, 2) + rect(cx - 14, y - 26, 28, 4, CREAM_DARK, 2))
    p.append(chair(540, 900, TERRACOTTA) + chair(860, 900))
    p.append(chair(990, 930) + chair(1310, 930, TERRACOTTA))
    p.append(frame(560, 130, 150, 190, rect(578, 150, 114, 150, WINE, 2) + circle(635, 225, 34, CREAM)))
    p.append(frame(1260, 150, 220, 150, rect(1278, 168, 184, 114, HERB_DARK, 2) + circle(1370, 225, 30, BRASS)))
    p.append(plant(1860, 800, 1.15))
    p.append(finish())
    return svg(W, H, ''.join(p), 'Reservations', DEFS)


def order():
    p = [room()]
    # takeaway counter
    p.append(rect(0, 640, W, 40, WAINSCOT_LIGHT))
    p.append(rect(0, 680, W, 120, WAINSCOT))
    p.append(rect(0, 676, W, 8, BRASS))
    # menu boards
    for i, x in enumerate([260, 560, 860]):
        p.append(rect(x, 140, 240, 300, BRASS_DARK, 8) + rect(x + 12, 152, 216, 276, BLACKBOARD, 4))
        for r in range(6):
            p.append(line(x + 34, 200 + r * 36, x + 34 + 140 - (r % 3) * 24, 200 + r * 36, CHALK, 4, 'opacity="0.55"'))
    # neon open sign
    p.append(rect(1280, 120, 360, 150, INK, 20, 'opacity="0.6"'))
    p.append(neon(1460, 218, 'OPEN', GLOW, 78))
    p.append(ellipse(1460, 200, 260, 130, 'url(#glow)'))
    # paper bags and bell on the counter
    for x, h, c in [(1180, 130, CREAM_DARK), (1280, 160, CREAM), (1380, 120, CREAM_DARK)]:
        p.append(path(f'M{x - 44} 640 L{x - 38} {640 - h} L{x + 38} {640 - h} L{x + 44} 640 Z', c))
        p.append(path(f'M{x - 38} {640 - h} l38 22 l38 -22', CREAM_DARK, 'opacity="0.8"'))
        p.append(rect(x - 18, 640 - h * 0.58, 36, 26, TERRACOTTA, 4))
    p.append(ellipse(1560, 636, 44, 12, BRASS_DARK) + path('M1520 636 Q1520 586 1560 586 Q1600 586 1600 636 Z', BRASS) + circle(1560, 580, 8, BRASS_DARK))
    # stools
    for x in [200, 520, 840]:
        p.append(ellipse(x, 880, 62, 16, WINE) + rect(x - 6, 880, 12, 120, INK) + ellipse(x, 1004, 54, 10, INK, 'opacity="0.6"'))
    p += [pendant(380, 230, 0.9), pendant(1000, 200, 0.9), pendant(1620, 200, 0.9)]
    p.append(plant(60, 800, 1.1))
    p.append(finish())
    return svg(W, H, ''.join(p), 'Order', DEFS + BLUR)


def bookings():
    p = [room()]
    # key rack and reservation board
    p.append(rect(180, 120, 460, 320, BRASS_DARK, 10) + rect(194, 134, 432, 292, '#6d3a24', 6))
    for r in range(3):
        for c in range(5):
            x, y = 238 + c * 82, 190 + r * 80
            p.append(circle(x, y, 8, BRASS) + path(f'M{x} {y + 8} v30', 'none', f'stroke="{BRASS}" stroke-width="4"') + circle(x, y + 46, 12, BRASS_DARK))
    p.append(clock(900, 220, 64))
    p.append(frame(1060, 120, 300, 200, rect(1080, 140, 260, 160, CREAM, 3) + line(1100, 190, 1320, 190, WALL, 5) + line(1100, 230, 1260, 230, WALL, 5) + line(1100, 270, 1200, 270, WALL, 5)))
    p += [pendant(500, 190), pendant(1100, 200, 1.1), pendant(1620, 190)]
    # host stand with open ledger
    p.append(path('M330 700 L1100 700 L1060 1010 L370 1010 Z', WAINSCOT))
    p.append(rect(310, 660, 810, 46, WAINSCOT_LIGHT, 8) + rect(310, 656, 810, 8, BRASS, 3))
    p.append(path('M520 650 L700 622 L880 650 L860 664 L700 640 L540 664 Z', CREAM) + path('M700 622 V640', 'none', f'stroke="{CREAM_DARK}" stroke-width="3"'))
    for i in range(4):
        p.append(line(560 + i * 8, 646 - i * 4, 680, 636 - i * 3, CREAM_DARK, 2, 'opacity="0.8"'))
    # desk lamp
    p.append(rect(960, 640, 70, 14, BRASS_DARK, 4) + line(995, 640, 1030, 540, BRASS_DARK, 8) + path('M1000 530 L1076 560 L1048 590 Z', BRASS) + ellipse(1050, 640, 140, 70, 'url(#glow)'))
    p.append(plant(1260, 800, 1.0))
    p.append(finish())
    return svg(W, H, ''.join(p), 'My bookings', DEFS)


def info():
    p = [room()]
    # big storefront window with street and awning
    p.append(rect(120, 110, 1000, 560, BRASS_DARK, 10))
    p.append(rect(140, 130, 960, 520, 'url(#night)'))
    for bx, bw, bh in [(160, 110, 280), (290, 90, 360), (400, 130, 240), (550, 100, 320), (670, 120, 270), (810, 90, 350), (920, 140, 250)]:
        p.append(rect(bx, 650 - bh, bw, bh, '#0f1830', extra='opacity="0.92"'))
        for wy in range(650 - bh + 18, 636, 32):
            for wx in range(bx + 14, bx + bw - 20, 30):
                p.append(rect(wx, wy, 10, 14, GLOW, 1, 'opacity="0.7"'))
    p.append(rect(630, 130, 8, 520, BRASS_DARK) + rect(140, 380, 960, 8, BRASS_DARK))
    p.append(path('M100 110 L1140 110 L1100 40 L140 40 Z', TERRACOTTA))
    for i in range(10):
        p.append(path(f'M{140 + i * 96} 40 h48 l-12 70 h-24 Z', CREAM, 'opacity="0.9"'))
    p.append(ellipse(620, 600, 520, 200, 'url(#glow)'))
    # opening hours plaque and sign
    p.append(rect(1280, 150, 380, 260, BRASS_DARK, 10) + rect(1294, 164, 352, 232, BLACKBOARD, 5))
    for r in range(5):
        p.append(line(1330, 210 + r * 38, 1330 + 120, 210 + r * 38, CHALK, 4, 'opacity="0.55"'))
        p.append(line(1500, 210 + r * 38, 1500 + 100, 210 + r * 38, CHALK, 4, 'opacity="0.55"'))
    p.append(circle(1740, 200, 60, BRASS_DARK) + circle(1740, 200, 48, CREAM) + path('M1740 200 L1740 170 M1740 200 L1766 214', 'none', f'stroke="{INK}" stroke-width="5" stroke-linecap="round"'))
    p += [pendant(1300, 200, 0.9), pendant(1620, 230, 0.9)]
    p.append(table(1400, 850, 320, WINE, candle_on=False))
    p.append(bottle(1330, 836, 90, HERB_DARK) + glass(1430, 836) + plate(1500, 836, 36, BRASS))
    p.append(plant(60, 800, 1.2))
    p.append(finish())
    return svg(W, H, ''.join(p), 'Information', DEFS)


# ------------------------------------------------------------------ the host


def head():
    """Front-facing head, centred on x=400. Neutral, friendly, short parted hair."""
    return ''.join(
        [
            path('M360 450 h80 v110 q-40 34 -80 0 Z', SKIN_SHADE),
            ellipse(292, 372, 17, 28, SKIN_SHADE),
            ellipse(508, 372, 17, 28, SKIN_SHADE),
            ellipse(400, 352, 108, 126, SKIN),
            path('M292 330 C288 214 380 176 440 188 C516 200 520 292 508 332 C490 282 440 262 392 270 C340 278 304 294 292 330 Z', HAIR),
            path('M296 328 C284 372 294 398 306 412 L312 340 Z', HAIR),
            path('M504 328 C516 372 506 398 494 412 L488 340 Z', HAIR),
            path('M338 232 C384 206 448 214 490 250', 'none', 'stroke="#4a3026" stroke-width="7" stroke-linecap="round" opacity="0.7"'),
            path('M334 322 q24 -14 48 -2', 'none', f'stroke="{HAIR}" stroke-width="7" stroke-linecap="round"'),
            path('M418 320 q24 -12 48 2', 'none', f'stroke="{HAIR}" stroke-width="7" stroke-linecap="round"'),
            ellipse(358, 362, 12, 15, INK),
            ellipse(442, 362, 12, 15, INK),
            circle(362, 357, 4, '#fff'),
            circle(446, 357, 4, '#fff'),
            ellipse(336, 410, 20, 11, '#e58f78', 'opacity="0.45"'),
            ellipse(464, 410, 20, 11, '#e58f78', 'opacity="0.45"'),
            path('M368 420 q32 30 64 0', 'none', 'stroke="#8a4a3c" stroke-width="6" stroke-linecap="round"'),
        ]
    )


def torso():
    """Shirt, vest, bow tie and the long waist apron. Everything is cut flat at y=1200."""
    return ''.join(
        [
            # trousers visible under the apron
            path('M270 1000 L530 1000 L536 1200 L264 1200 Z', INK),
            # white shirt
            path('M236 612 C236 560 312 526 400 526 C488 526 564 560 564 612 L590 1010 L210 1010 Z', WHITE),
            # vest
            path('M290 566 L400 700 L510 566 L534 1010 L266 1010 Z', VEST),
            path('M400 700 L400 1010', 'none', f'stroke="{BRASS}" stroke-width="3"'),
            *[circle(400, 740 + i * 70, 7, BRASS) for i in range(4)],
            # collar and bow tie
            path('M352 540 L400 612 L372 622 L336 560 Z', WHITE, 'stroke="#d8cfbd" stroke-width="2"'),
            path('M448 540 L400 612 L428 622 L464 560 Z', WHITE, 'stroke="#d8cfbd" stroke-width="2"'),
            path('M400 612 L360 592 L360 636 Z', TERRACOTTA),
            path('M400 612 L440 592 L440 636 Z', TERRACOTTA),
            circle(400, 614, 11, '#8e3620'),
            # apron
            path('M268 880 L532 880 L552 1200 L248 1200 Z', CREAM),
            path('M268 880 L532 880 L536 912 L264 912 Z', CREAM_DARK),
            rect(366, 960, 68, 56, CREAM_DARK, 8),
            path('M268 880 Q400 836 532 880', 'none', f'stroke="{BRASS_DARK}" stroke-width="5"'),
        ]
    )


def arm_down(side):
    """Relaxed arm: sleeve plus forearm and hand."""
    sx = 270 if side == 'left' else 530
    ex = 236 if side == 'left' else 564
    return (
        f'<path d="M{sx} 600 Q{ex - (20 if side == "left" else -20)} 740 {ex} 880" fill="none" stroke="{WHITE}" stroke-width="74" stroke-linecap="round"/>'
        + f'<path d="M{ex} 836 L{ex} 950" fill="none" stroke="{SKIN}" stroke-width="52" stroke-linecap="round"/>'
        + circle(ex, 968, 34, SKIN)
    )


def pose_greeting():
    waving = (
        f'<path d="M530 604 Q612 640 640 560" fill="none" stroke="{WHITE}" stroke-width="76" stroke-linecap="round"/>'
        + f'<path d="M640 568 L652 456" fill="none" stroke="{SKIN}" stroke-width="54" stroke-linecap="round"/>'
        + circle(654, 428, 42, SKIN)
        + ''.join(
            f'<path d="M{654 + dx} {428 - 26} l{dx * 0.25} -{h}" stroke="{SKIN}" stroke-width="16" stroke-linecap="round"/>'
            for dx, h in [(-24, 30), (-8, 42), (10, 44), (26, 30)]
        )
        + path('M624 440 q-30 10 -26 36', 'none', f'stroke="{SKIN}" stroke-width="18" stroke-linecap="round"')
    )
    return arm_down('left') + torso() + waving + head()


def pose_menu():
    menu_card = (
        f'<g transform="rotate(-8 330 760)">'
        + rect(214, 640, 250, 330, '#5b1f2c', 14)
        + rect(228, 654, 222, 302, '#7a2e3b', 10)
        + rect(256, 690, 166, 6, BRASS, 3)
        + rect(256, 724, 120, 5, CREAM_DARK, 2)
        + rect(256, 750, 150, 5, CREAM_DARK, 2)
        + rect(256, 776, 100, 5, CREAM_DARK, 2)
        + circle(339, 880, 22, 'none', f'stroke="{BRASS}" stroke-width="5"')
        + '</g>'
    )
    left_arm = (
        f'<path d="M270 604 Q210 740 300 800" fill="none" stroke="{WHITE}" stroke-width="74" stroke-linecap="round"/>'
        + f'<path d="M284 790 L330 840" fill="none" stroke="{SKIN}" stroke-width="50" stroke-linecap="round"/>'
        + circle(346, 850, 34, SKIN)
    )
    pointing = (
        f'<path d="M530 604 Q620 660 590 760" fill="none" stroke="{WHITE}" stroke-width="76" stroke-linecap="round"/>'
        + f'<path d="M590 740 L560 830" fill="none" stroke="{SKIN}" stroke-width="52" stroke-linecap="round"/>'
        + circle(552, 852, 32, SKIN)
        + f'<path d="M530 836 L452 800" stroke="{SKIN}" stroke-width="18" stroke-linecap="round"/>'
    )
    return torso() + left_arm + pointing + menu_card + head()


def pose_notepad():
    pad = (
        '<g transform="rotate(-6 340 760)">'
        + rect(262, 690, 170, 220, WHITE, 10)
        + rect(262, 690, 170, 34, TERRACOTTA, 10)
        + ''.join(rect(284, 748 + i * 30, 126 - (i % 2) * 28, 4, CREAM_DARK, 2) for i in range(5))
        + '</g>'
    )
    left_arm = (
        f'<path d="M270 604 Q214 744 304 800" fill="none" stroke="{WHITE}" stroke-width="74" stroke-linecap="round"/>'
        + f'<path d="M290 790 L330 830" fill="none" stroke="{SKIN}" stroke-width="50" stroke-linecap="round"/>'
        + circle(346, 842, 32, SKIN)
    )
    right_arm = (
        f'<path d="M530 604 Q600 700 500 782" fill="none" stroke="{WHITE}" stroke-width="76" stroke-linecap="round"/>'
        + f'<path d="M508 770 L430 800" fill="none" stroke="{SKIN}" stroke-width="50" stroke-linecap="round"/>'
        + circle(418, 806, 30, SKIN)
        + f'<path d="M418 806 L390 746" stroke="{BRASS_DARK}" stroke-width="10" stroke-linecap="round"/>'
    )
    return torso() + left_arm + right_arm + pad + head()


def character(body, title):
    return svg(800, 1200, body, title)


def avatar():
    return (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="210 170 380 380" width="256" height="256" role="img" aria-label="AI host">\n'
        '<title>AI host</title>\n'
        f'<defs><clipPath id="face"><circle cx="400" cy="360" r="188"/></clipPath></defs>\n'
        f'<g clip-path="url(#face)">{circle(400, 360, 190, "#f1d8a5")}{head()}'
        f'{path("M250 500 Q400 420 550 500 L560 560 L240 560 Z", WHITE)}'
        f'{path("M400 520 L362 500 L362 544 Z", TERRACOTTA)}{path("M400 520 L438 500 L438 544 Z", TERRACOTTA)}{circle(400, 522, 11, "#8e3620")}</g>\n'
        '</svg>\n'
    )


def write(folder, name, content):
    target = OUT / folder
    target.mkdir(parents=True, exist_ok=True)
    (target / name).write_text(content, encoding='utf-8', newline='\n')


def main():
    for name, factory in [
        ('welcome', welcome),
        ('menu', menu),
        ('picks', picks),
        ('reservation', reservation),
        ('order', order),
        ('bookings', bookings),
        ('info', info),
    ]:
        write('scenes', f'{name}.svg', factory())

    write('character', 'host-greeting.svg', character(pose_greeting(), 'AI host greeting'))
    write('character', 'host-menu.svg', character(pose_menu(), 'AI host with the menu'))
    write('character', 'host-notepad.svg', character(pose_notepad(), 'AI host taking notes'))
    write('character', 'host-avatar.svg', avatar())
    print(f'Wrote artwork to {OUT}')


if __name__ == '__main__':
    main()
