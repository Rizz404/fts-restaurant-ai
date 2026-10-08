"""Generates the flat vector illustrations in public/assets/restaurant/.

Self-made for FTS Restaurant AI (no third-party artwork, no license needed).
Colors follow resources/css/theme.css (docs/UI_FOUNDATION.md) plus a few
tints. The seven slide scenes and the host are simple flat placeholders;
Plan 8 replaces them with the final restaurant artwork (docs/VISUAL_ASSETS.md). Re-run after changing the drawings:

    python scripts/generate-visual-assets.py
"""

from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / 'public' / 'assets' / 'restaurant'

# Theme colors and tints.
PRIMARY = '#B4472B'
PRIMARY_DARK = '#963820'
PRIMARY_LIGHT = '#D9876E'
PRIMARY_PALE = '#F3D9CF'
NAVY = '#2B1D16'
NAVY_2 = '#3D2A20'
ACCENT = '#3F7D58'
ACCENT_PALE = '#D6E8DC'
CANVAS = '#FBF6EF'
WALL = '#F2E9DD'
WALL_DARK = '#E6D9C8'
FLOOR = '#D8C8B8'
FLOOR_DARK = '#C4B09A'
WHITE = '#FFFFFF'
MUTED = '#6B5A4E'
WOOD = '#D9C3A5'
WOOD_DARK = '#B99B76'
LEAF = '#2F8F5B'
LEAF_DARK = '#1F6B43'
POT = '#E8E1D6'
WARM = '#E9A23B'
SKIN = '#E9B48C'
SKIN_SHADE = '#D69B72'
HAIR = '#2B1D16'

W, H = 1600, 900
HORIZON = 640


def svg(width, height, body, title):
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {height}" '
        f'width="{width}" height="{height}" role="img" aria-label="{title}">\n'
        f'<title>{title}</title>\n{body}\n</svg>\n'
    )


def rect(x, y, w, h, fill, rx=0, extra=''):
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{fill}" {extra}/>'


def circle(cx, cy, r, fill, extra=''):
    return f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fill}" {extra}/>'


def path(d, fill, extra=''):
    return f'<path d="{d}" fill="{fill}" {extra}/>'


def room(wall_accent):
    """Wall, baseboard, floor with soft perspective lines, and a window."""
    parts = [
        rect(0, 0, W, HORIZON, WALL),
        rect(0, 0, W, 70, wall_accent, extra='opacity="0.9"'),
        rect(0, HORIZON - 18, W, 18, WALL_DARK),
        rect(0, HORIZON, W, H - HORIZON, FLOOR),
    ]
    for i in range(-6, 14):
        x = 800 + i * 160
        parts.append(f'<line x1="{800 + i * 60}" y1="{HORIZON}" x2="{x}" y2="{H}" stroke="{FLOOR_DARK}" stroke-width="2" opacity="0.5"/>')
    parts.append(rect(0, HORIZON + 120, W, 3, FLOOR_DARK, extra='opacity="0.5"'))
    return parts


def window(x, y, w=300, h=260):
    return [
        rect(x - 10, y - 10, w + 20, h + 20, WHITE, rx=8),
        rect(x, y, w, h, ACCENT_PALE, rx=4),
        path(f'M{x} {y + h} L{x + w * 0.35} {y + h * 0.55} L{x + w * 0.6} {y + h * 0.8} L{x + w * 0.8} {y + h * 0.45} L{x + w} {y + h * 0.7} L{x + w} {y + h} Z', PRIMARY_PALE),
        rect(x + w / 2 - 4, y, 8, h, WHITE),
        rect(x, y + h / 2 - 4, w, 8, WHITE),
        circle(x + w * 0.78, y + h * 0.25, 26, WHITE, 'opacity="0.8"'),
    ]


def plant(x, base_y, scale=1.0):
    s = scale
    return [
        path(f'M{x} {base_y - 150 * s} C{x - 70 * s} {base_y - 210 * s} {x - 90 * s} {base_y - 120 * s} {x - 20 * s} {base_y - 95 * s} Z', LEAF),
        path(f'M{x} {base_y - 150 * s} C{x + 70 * s} {base_y - 230 * s} {x + 95 * s} {base_y - 130 * s} {x + 22 * s} {base_y - 95 * s} Z', LEAF_DARK),
        path(f'M{x} {base_y - 190 * s} C{x - 30 * s} {base_y - 260 * s} {x + 40 * s} {base_y - 270 * s} {x + 5 * s} {base_y - 120 * s} Z', LEAF),
        path(f'M{x - 45 * s} {base_y - 100 * s} L{x + 45 * s} {base_y - 100 * s} L{x + 34 * s} {base_y} L{x - 34 * s} {base_y} Z', POT),
        rect(x - 48 * s, base_y - 108 * s, 96 * s, 14 * s, WOOD, rx=4),
    ]


def shadow(cx, cy, rx, ry=14):
    return f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" fill="{NAVY}" opacity="0.12"/>'


def desk(x, y, w, h=26, legs=110):
    return [
        shadow(x + w / 2, y + legs + h + 6, w / 2),
        rect(x + 20, y + h, 18, legs, WOOD_DARK),
        rect(x + w - 38, y + h, 18, legs, WOOD_DARK),
        rect(x, y, w, h, WOOD, rx=6),
    ]


def chair(x, y, color=NAVY_2):
    return [
        shadow(x + 45, y + 165, 50, 10),
        rect(x + 10, y, 70, 90, color, rx=18),
        rect(x, y + 85, 90, 24, color, rx=10),
        rect(x + 40, y + 109, 10, 45, MUTED),
        rect(x + 15, y + 150, 60, 8, MUTED, rx=4),
    ]


def monitor(x, y, w=150, h=95, screen=NAVY):
    return [
        rect(x, y, w, h, NAVY_2, rx=8),
        rect(x + 8, y + 8, w - 16, h - 16, screen, rx=4),
        rect(x + w / 2 - 8, y + h, 16, 26, MUTED),
        rect(x + w / 2 - 40, y + h + 24, 80, 8, MUTED, rx=4),
    ]


def wall_frame(x, y, w, h, inner):
    return [rect(x, y, w, h, WHITE, rx=10, extra=f'stroke="{WALL_DARK}" stroke-width="4"'), *inner]


def clock(cx, cy):
    return [
        circle(cx, cy, 46, WHITE, f'stroke="{NAVY_2}" stroke-width="8"'),
        f'<line x1="{cx}" y1="{cy}" x2="{cx}" y2="{cy - 28}" stroke="{NAVY}" stroke-width="6" stroke-linecap="round"/>',
        f'<line x1="{cx}" y1="{cy}" x2="{cx + 20}" y2="{cy + 8}" stroke="{PRIMARY}" stroke-width="6" stroke-linecap="round"/>',
        circle(cx, cy, 6, NAVY),
    ]


def logo_mark(x, y, size, bg=PRIMARY, fg=WHITE):
    """Monogram: rounded square with a speech bubble that holds a grid of dots."""
    s = size / 64
    return (
        f'<g transform="translate({x} {y}) scale({s})">'
        f'<rect width="64" height="64" rx="16" fill="{bg}"/>'
        f'<path d="M18 16h28a6 6 0 0 1 6 6v18a6 6 0 0 1-6 6H30l-9 7v-7h-3a6 6 0 0 1-6-6V22a6 6 0 0 1 6-6Z" fill="{fg}"/>'
        f'<rect x="21" y="23" width="6" height="6" rx="1.5" fill="{bg}"/><rect x="29" y="23" width="6" height="6" rx="1.5" fill="{bg}"/>'
        f'<rect x="37" y="23" width="6" height="6" rx="1.5" fill="{bg}"/><rect x="21" y="32" width="6" height="6" rx="1.5" fill="{bg}"/>'
        f'<rect x="29" y="32" width="6" height="6" rx="1.5" fill="{bg}"/><rect x="37" y="32" width="6" height="6" rx="1.5" fill="{bg}"/>'
        '</g>'
    )


def lamp(x, drop=190):
    """Pendant lamp hanging from the ceiling."""
    return [
        f'<line x1="{x}" y1="0" x2="{x}" y2="{drop}" stroke="{NAVY_2}" stroke-width="5"/>',
        path(f'M{x - 52} {drop + 54} Q{x} {drop - 24} {x + 52} {drop + 54} Z', WARM),
        circle(x, drop + 58, 14, WHITE, 'opacity="0.9"'),
    ]


def plate(cx, cy, r=46, rim=WHITE, food=PRIMARY_LIGHT):
    return [
        f'<ellipse cx="{cx}" cy="{cy}" rx="{r}" ry="{r * 0.28:.1f}" fill="{rim}" stroke="{WALL_DARK}" stroke-width="3"/>',
        f'<ellipse cx="{cx}" cy="{cy - 4}" rx="{r * 0.62:.1f}" ry="{r * 0.17:.1f}" fill="{food}"/>',
        circle(cx - r * 0.18, cy - r * 0.2, r * 0.12, LEAF),
    ]


def candle(x, y):
    return [
        rect(x - 7, y, 14, 46, WHITE, rx=3),
        path(f'M{x} {y - 22} C{x - 10} {y - 8} {x - 8} {y} {x} {y} C{x + 8} {y} {x + 10} {y - 8} {x} {y - 22} Z', WARM),
    ]


def chef_hat(cx, base_y, scale=1.0):
    s = scale
    return [
        rect(cx - 52 * s, base_y - 34 * s, 104 * s, 34 * s, WHITE, rx=6 * s),
        circle(cx - 40 * s, base_y - 62 * s, 34 * s, WHITE),
        circle(cx, base_y - 82 * s, 42 * s, WHITE),
        circle(cx + 40 * s, base_y - 62 * s, 34 * s, WHITE),
        rect(cx - 52 * s, base_y - 20 * s, 104 * s, 6 * s, WALL_DARK),
    ]


def welcome():
    p = room(PRIMARY)
    p += lamp(520) + lamp(1080)
    p += window(980, 170)
    p += [logo_mark(700, 170, 120)]
    # Host stand with a menu on top.
    p += [
        shadow(800, 830, 260, 20),
        rect(640, 560, 320, 260, PRIMARY_DARK, rx=16),
        rect(620, 540, 360, 36, WOOD, rx=10),
        rect(690, 450, 220, 92, WHITE, rx=8, extra=f'stroke="{WALL_DARK}" stroke-width="4"'),
        rect(710, 472, 140, 10, MUTED, rx=5),
        rect(710, 494, 180, 8, WALL_DARK, rx=4),
        rect(710, 512, 120, 8, WALL_DARK, rx=4),
    ]
    p += candle(1220, 560) + [rect(1160, 604, 120, 12, WOOD_DARK, rx=6)]
    p += plant(220, 820, 1.3)
    p += plant(1400, 800, 0.9)
    return svg(W, H, '\n'.join(p), 'Welcome')


def menu():
    p = room(ACCENT)
    p += lamp(380) + lamp(1220)
    # Menu board.
    p += [rect(430, 120, 740, 380, NAVY_2, rx=14, extra=f'stroke="{WOOD_DARK}" stroke-width="12"')]
    for i in range(6):
        y = 180 + i * 52
        p += [rect(480, y, 360 + (i * 41) % 120, 12, WHITE, rx=6, extra='opacity="0.85"'), rect(1040, y, 80, 12, WARM, rx=6)]
    p += [rect(480, 140, 200, 16, WARM, rx=8)]
    # Shelf with bowls.
    p += [rect(150, 330, 230, 14, WOOD_DARK, rx=6), rect(1230, 330, 230, 14, WOOD_DARK, rx=6)]
    for x in (190, 250, 310):
        p += [path(f'M{x - 26} 300 Q{x} 350 {x + 26} 300 Z', PRIMARY_LIGHT), rect(x - 26, 296, 52, 6, WHITE, rx=3)]
    for x in (1270, 1330, 1390):
        p += [path(f'M{x - 26} 300 Q{x} 350 {x + 26} 300 Z', WARM), rect(x - 26, 296, 52, 6, WHITE, rx=3)]
    # Table with dishes.
    p += desk(420, 620, 760, legs=150)
    p += plate(560, 630, 62) + plate(800, 630, 70, food=WARM) + plate(1040, 630, 62, food=LEAF_DARK)
    p += plant(160, 840, 1.1)
    p += plant(1470, 840, 1.1)
    return svg(W, H, '\n'.join(p), 'Menu')


def picks():
    p = room(WARM)
    p += lamp(800, 150)
    # Spotlight cone and a star above a covered dish.
    p += [path('M720 260 L880 260 L1010 840 L590 840 Z', WHITE, 'opacity="0.35"')]
    p += [path('M800 90 L826 150 L890 156 L842 198 L856 262 L800 230 L744 262 L758 198 L710 156 L774 150 Z', WARM)]
    p += [shadow(800, 835, 250, 20), rect(560, 700, 480, 130, WOOD, rx=14), rect(580, 680, 440, 30, WOOD_DARK, rx=10)]
    p += [
        f'<ellipse cx="800" cy="676" rx="150" ry="20" fill="{WHITE}" stroke="{WALL_DARK}" stroke-width="4"/>',
        path('M650 676 Q650 520 800 520 Q950 520 950 676 Z', PRIMARY_PALE, f'stroke="{WALL_DARK}" stroke-width="4"'),
        circle(800, 508, 16, WHITE, f'stroke="{WALL_DARK}" stroke-width="4"'),
    ]
    p += chef_hat(260, 820, 1.1)
    p += chef_hat(1360, 820, 0.9)
    p += plant(120, 840, 1.0)
    return svg(W, H, '\n'.join(p), "Chef's Picks")


def reservation():
    p = room(ACCENT)
    p += lamp(800, 170)
    # Wall calendar.
    p += [rect(180, 130, 260, 300, WHITE, rx=12, extra=f'stroke="{WALL_DARK}" stroke-width="5"'), rect(180, 130, 260, 56, PRIMARY, rx=12)]
    for r in range(4):
        for c in range(5):
            p.append(rect(204 + c * 44, 214 + r * 52, 30, 30, WARM if (r, c) == (1, 3) else CANVAS, rx=6))
    p += window(1100, 150, 320, 240)
    # Round table set for four with candle.
    p += [shadow(800, 840, 360, 22), rect(776, 640, 48, 190, WOOD_DARK), f'<ellipse cx="800" cy="640" rx="320" ry="64" fill="{WOOD}"/>']
    p += plate(600, 646, 54) + plate(1000, 646, 54) + plate(720, 676, 46) + plate(880, 676, 46)
    p += candle(800, 596)
    p += chair(380, 600, NAVY_2)
    p += chair(1130, 600, PRIMARY)
    p += plant(1480, 840, 1.0)
    return svg(W, H, '\n'.join(p), 'Reservation')


def order():
    p = room(PRIMARY)
    p += lamp(520) + lamp(1080)
    # Takeaway counter with paper bags and a bowl.
    p += [shadow(800, 840, 520, 24), rect(300, 600, 1000, 220, PRIMARY_DARK, rx=18), rect(280, 580, 1040, 40, WOOD, rx=12)]
    for x, h in ((470, 190), (660, 230), (850, 170)):
        p += [
            rect(x, 580 - h, 130, h, WOOD, rx=8, extra=f'stroke="{WOOD_DARK}" stroke-width="4"'),
            rect(x + 28, 580 - h + 36, 74, 12, WOOD_DARK, rx=6),
            path(f'M{x + 28} {580 - h} q37 -46 74 0', 'none', f'stroke="{WOOD_DARK}" stroke-width="6" stroke-linecap="round"'),
        ]
    p += [path('M1060 548 Q1130 640 1200 548 Z', WARM), rect(1052, 540, 156, 14, WHITE, rx=7)]
    p += monitor(260, 440, 150, 96, screen=PRIMARY_PALE)
    p += plant(150, 840, 1.2) + plant(1470, 840, 1.0)
    return svg(W, H, '\n'.join(p), 'Order')


def bookings():
    p = room(WARM)
    p += lamp(700, 170)
    # Reservation book board on the wall and a desk with tickets.
    p += [rect(300, 130, 760, 400, WHITE, rx=14, extra=f'stroke="{WALL_DARK}" stroke-width="6"')]
    for i in range(5):
        y = 180 + i * 64
        p += [rect(340, y, 150, 40, ACCENT_PALE, rx=8), rect(512, y + 8, 340 + (i * 37) % 120, 12, MUTED, rx=6), rect(512, y + 28, 200, 8, WALL_DARK, rx=4), circle(980, y + 20, 14, LEAF if i % 2 == 0 else WARM)]
    p += desk(1120, 620, 380, legs=140)
    p += [rect(1180, 566, 130, 54, WHITE, rx=6, extra=f'stroke="{WALL_DARK}" stroke-width="3"'), rect(1200, 580, 90, 8, MUTED, rx=4), rect(1340, 580, 90, 40, WARM, rx=6)]
    p += chair(1250, 660, PRIMARY)
    p += plant(180, 840, 1.2)
    return svg(W, H, '\n'.join(p), 'My Bookings')


def info():
    p = room(PRIMARY_LIGHT)
    p += lamp(380) + lamp(1220)
    p += clock(560, 220)
    # Opening hours board and an open sign.
    p += [rect(780, 120, 460, 340, NAVY_2, rx=14, extra=f'stroke="{WOOD_DARK}" stroke-width="10"')]
    for i in range(5):
        y = 170 + i * 54
        p += [rect(820, y, 140, 12, WHITE, rx=6), rect(1000, y, 190, 12, WARM, rx=6)]
    p += [rect(300, 330, 200, 90, PRIMARY, rx=14), rect(316, 346, 168, 58, WHITE, rx=8), rect(332, 366, 136, 16, PRIMARY, rx=8)]
    # Map pin.
    p += [path('M1380 420 C1330 360 1330 300 1380 290 C1430 300 1430 360 1380 420 Z', PRIMARY_DARK), circle(1380, 334, 18, WHITE)]
    p += desk(520, 620, 560, legs=150)
    p += [rect(600, 578, 130, 42, WHITE, rx=6), rect(780, 578, 130, 42, WARM, rx=6)]
    p += plant(160, 840, 1.2) + plant(1470, 840, 1.0)
    return svg(W, H, '\n'.join(p), 'Info')


def assistant():
    w, h = 600, 800
    p = [
        rect(0, 0, w, h, PRIMARY_PALE),
        circle(300, 330, 230, WHITE, 'opacity="0.55"'),
        circle(470, 150, 40, WHITE, 'opacity="0.6"'),
        circle(110, 560, 26, WHITE, 'opacity="0.6"'),
        # Body.
        path('M110 800 C110 600 180 520 300 520 C420 520 490 600 490 800 Z', PRIMARY),
        path('M255 520 L300 600 L345 520 Z', WHITE),
        path('M300 600 L280 640 L300 700 L320 640 Z', NAVY_2),
        path('M190 560 L255 520 L280 640 L230 800 L170 800 Z', PRIMARY_DARK, 'opacity="0.35"'),
        path('M410 560 L345 520 L320 640 L370 800 L430 800 Z', PRIMARY_DARK, 'opacity="0.35"'),
        # Name badge.
        rect(360, 640, 70, 36, WHITE, rx=6),
        rect(370, 650, 50, 6, PRIMARY, rx=3),
        rect(370, 662, 34, 5, MUTED, rx=2.5),
        # Neck and head.
        rect(268, 440, 64, 90, SKIN_SHADE, rx=24),
        circle(300, 360, 108, SKIN),
        # Hair.
        path('M192 360 C180 250 250 205 312 210 C385 214 425 270 410 360 C395 300 350 280 300 286 C250 290 215 310 192 360 Z', HAIR),
        path('M195 345 C186 400 196 440 214 460 L222 370 Z', HAIR),
        # Face.
        circle(258, 372, 10, NAVY),
        circle(342, 372, 10, NAVY),
        path('M262 418 C285 440 315 440 338 418', 'none', f'stroke="{NAVY}" stroke-width="7" stroke-linecap="round"'),
        circle(232, 405, 14, WARM, 'opacity="0.35"'),
        circle(368, 405, 14, WARM, 'opacity="0.35"'),
        # Chef hat.
        rect(214, 214, 172, 44, WHITE, rx=10),
        circle(236, 196, 42, WHITE),
        circle(300, 168, 52, WHITE),
        circle(364, 196, 42, WHITE),
        rect(214, 236, 172, 8, WALL_DARK),
        # Waving hand.
        path('M478 640 C520 600 540 520 520 470', 'none', f'stroke="{PRIMARY}" stroke-width="58" stroke-linecap="round"'),
        circle(522, 452, 34, SKIN),
        # Chat bubble.
        rect(70, 120, 150, 100, WHITE, rx=24),
        path('M150 220 L180 250 L180 218 Z', WHITE),
        circle(110, 170, 10, PRIMARY), circle(145, 170, 10, PRIMARY), circle(180, 170, 10, PRIMARY),
    ]
    return svg(w, h, '\n'.join(p), 'AI host')


def logo():
    return svg(64, 64, logo_mark(0, 0, 64), 'FTS Restaurant AI')


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    files = {
        'welcome.svg': welcome(),
        'menu.svg': menu(),
        'picks.svg': picks(),
        'reservation.svg': reservation(),
        'order.svg': order(),
        'bookings.svg': bookings(),
        'info.svg': info(),
        'assistant.svg': assistant(),
        'logo.svg': logo(),
    }
    for name, content in files.items():
        (OUT / name).write_text(content, encoding='utf-8', newline='\n')
    (OUT.parent.parent / 'favicon.svg').write_text(logo(), encoding='utf-8', newline='\n')
    print('Wrote', ', '.join(files), 'and public/favicon.svg')


if __name__ == '__main__':
    main()
