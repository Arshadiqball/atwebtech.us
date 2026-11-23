"""Draws the images and videos for the social calendar in posts.py and writes calendar.md / calendar.csv.

    python make.py            everything
    python make.py images     only the images
    python make.py videos     only the videos

Needs Pillow (pip install pillow) and ffmpeg for the videos. Colors, fonts and the logo are the app's own.
"""
import csv, math, os, subprocess, sys, tempfile

from PIL import Image, ImageDraw, ImageFilter, ImageFont

from posts import BONUS, OFFER, POSTS

HERE = os.path.dirname(os.path.abspath(__file__))
OUT_IMG = os.path.join(HERE, 'images')
OUT_VID = os.path.join(HERE, 'videos')

# Brand colors (the same tokens as the app and the website panel)
GREEN_DEEP, GREEN, GREEN_DARK = (27, 68, 40), (20, 48, 28), (12, 30, 18)
MINT, MINT_INK, MINT_SOFT = (176, 236, 156), (37, 99, 57), (227, 245, 219)
SAND, PANEL, INK, MUTED, LINE = (241, 240, 235), (255, 255, 255), (22, 32, 30), (105, 113, 109), (228, 227, 221)
ON_DARK, BLACK = (242, 247, 241), (11, 15, 13)
BAD, BAD_BG = (214, 69, 53), (253, 236, 234)


def font(name, size):
    files = {'display': 'PlusJakartaSans-SemiBold.ttf', 'display-m': 'PlusJakartaSans-Medium.ttf', 'body': 'Figtree-Regular.ttf',
             'body-m': 'Figtree-Medium.ttf', 'body-b': 'Figtree-SemiBold.ttf'}
    return ImageFont.truetype(os.path.join(HERE, 'assets', 'fonts', files[name]), size)


def mix(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def gradient(size, top, bottom):
    w, h = size
    img = Image.new('RGB', size, top)
    d = ImageDraw.Draw(img)
    for y in range(h):
        d.line([(0, y), (w, y)], fill=mix(top, bottom, y / max(1, h - 1)))
    return img


def glow(img, center, radius, color, strength=0.35):
    """A soft colored light, like the app's splash screen."""
    mask = Image.radial_gradient('L').resize((radius * 2, radius * 2))
    # The gradient only reaches full value in the corners, so fade to nothing by the edges of the box.
    mask = mask.point(lambda v: int(max(0, 176 - v) / 176 * 255 * strength))
    layer = Image.new('RGB', (radius * 2, radius * 2), color)
    img.paste(layer, (center[0] - radius, center[1] - radius), mask)


def dark_canvas(size, black=False):
    img = gradient(size, BLACK if black else GREEN_DEEP, (5, 8, 6) if black else GREEN_DARK)
    glow(img, (int(size[0] * 0.82), int(size[1] * 0.12)), int(size[0] * 0.75), MINT, 0.20 if not black else 0.16)
    glow(img, (int(size[0] * 0.1), int(size[1] * 0.95)), int(size[0] * 0.6), (60, 130, 190), 0.10)
    return img


def words(text):
    """Splits a headline into (word, highlighted) pairs. *Stars* mark the highlighted part."""
    out, hi = [], False
    for chunk in text.split(' '):
        start = chunk.startswith('*')
        if start:
            hi = True
        out.append((chunk.strip('*'), hi))
        if chunk.endswith('*') and len(chunk) > 1 or chunk == '*':
            hi = False
    return [w for w in out if w[0]]


def layout(draw, text, fnt, max_w):
    lines, line, width = [], [], 0
    space = draw.textlength(' ', font=fnt)
    for word, hi in words(text):
        w = draw.textlength(word, font=fnt)
        if line and width + space + w > max_w:
            lines.append(line)
            line, width = [], 0
        width += (space if line else 0) + w
        line.append((word, hi, w))
    if line:
        lines.append(line)
    return lines


def rich(draw, text, xy, max_w, name, size, fill, hi_fill, leading=1.12, align='left', max_lines=6, min_size=40):
    """Draws a headline with highlighted words, shrinking the type until it fits. Returns the y below it."""
    while True:
        fnt = font(name, size)
        lines = layout(draw, text, fnt, max_w)
        widest = max((w for line in lines for _, _, w in line), default=0)  # one long word must fit too
        if (len(lines) <= max_lines and widest <= max_w) or size <= min_size:
            break
        size -= 4
    x0, y = xy
    space = draw.textlength(' ', font=fnt)
    for line in lines:
        total = sum(w for _, _, w in line) + space * (len(line) - 1)
        x = x0 + (max_w - total) / 2 if align == 'center' else x0
        for word, hi, w in line:
            draw.text((x, y), word, font=fnt, fill=hi_fill if hi else fill)
            x += w + space
        y += size * leading
    return int(y)


def para(draw, text, xy, max_w, name, size, fill, leading=1.35, align='left'):
    if not text:
        return xy[1]
    return rich(draw, text.replace('*', ''), xy, max_w, name, size, fill, fill, leading, align, max_lines=8, min_size=24)


def pill(draw, text, xy, fill, ink, size=26):
    fnt = font('body-b', size)
    w = draw.textlength(text, font=fnt)
    x, y = xy
    draw.rounded_rectangle([x, y, x + w + size * 1.5, y + size * 1.9], radius=size, fill=fill)
    draw.text((x + size * 0.75, y + size * 0.38), text, font=fnt, fill=ink)
    return int(y + size * 1.9)


def footer(img, dark=True, pad=72, lift=0, scale=1.0):
    """The logo with its wordmark on the left, the address on the right."""
    w, h = img.size
    d = ImageDraw.Draw(img)
    m = int(64 * scale)
    mark = Image.open(os.path.join(HERE, 'assets', 'logo-dark.png' if dark else 'logo.png')).convert('RGBA').resize((m, m), Image.LANCZOS)
    y = h - pad - lift - m
    img.paste(mark, (pad, y), mark)
    d.text((pad + int(82 * scale), y + int(6 * scale)), 'atwebtech', font=font('display', int(44 * scale)), fill=ON_DARK if dark else INK)
    fnt = font('body-m', int(30 * scale))
    url = 'atwebtech.us'
    d.text((w - pad - d.textlength(url, font=fnt), y + int(16 * scale)), url, font=fnt, fill=mix(ON_DARK, GREEN, 0.35) if dark else MUTED)


def phone(img, screen, top, width):
    """An app screenshot with rounded corners and a soft shadow, running off the bottom of the canvas."""
    shot = Image.open(os.path.join(HERE, 'assets', 'screens', screen + '.png')).convert('RGB')
    # The walkthrough screenshots have an empty band where the status bar sits: trim it.
    shot = shot.crop((0, int(shot.height * 0.055), shot.width, shot.height))
    height = int(shot.height * width / shot.width)
    shot = shot.resize((width, height), Image.LANCZOS)
    radius = 54
    mask = Image.new('L', (width, height), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, width - 1, height + radius], radius=radius, fill=255)
    x = (img.width - width) // 2
    shadow = Image.new('RGBA', (width + 160, height + 160), (0, 0, 0, 0))
    ImageDraw.Draw(shadow).rounded_rectangle([80, 80, width + 80, height + 80 + radius], radius=radius, fill=(0, 0, 0, 150))
    shadow = shadow.filter(ImageFilter.GaussianBlur(36))
    img.paste(shadow, (x - 80, top - 60), shadow)
    border = Image.new('RGB', (width + 16, height + 16), mix(MINT, GREEN, 0.55))
    bmask = Image.new('L', (width + 16, height + 16), 0)
    ImageDraw.Draw(bmask).rounded_rectangle([0, 0, width + 15, height + 16 + radius], radius=radius + 8, fill=255)
    img.paste(border, (x - 8, top - 8), bmask)
    img.paste(shot, (x, top), mask)


# ---- Image templates (1080 x 1350, the 4:5 size that fills the most screen in a feed) -----------------

W, H, PAD = 1080, 1350, 72


def t_hook(p):
    img = dark_canvas((W, H))
    d = ImageDraw.Draw(img)
    top = pill(d, p.get('label', 'TIP'), (PAD, 96), mix(MINT, GREEN, 0.78), MINT)
    probe = ImageDraw.Draw(Image.new('RGB', (W, H)))
    block = rich(probe, p['headline'], (PAD, 0), W - PAD * 2, 'display', 116, ON_DARK, MINT, max_lines=5, min_size=72) + 40
    block = para(probe, p.get('sub', ''), (PAD, block), W - PAD * 2 - 40, 'body', 44, ON_DARK)
    y = top + max(60, int((H - 200 - top - block) * 0.42))
    y = rich(d, p['headline'], (PAD, y), W - PAD * 2, 'display', 116, ON_DARK, MINT, max_lines=5, min_size=72) + 40
    para(d, p.get('sub', ''), (PAD, y), W - PAD * 2 - 40, 'body', 44, mix(ON_DARK, GREEN, 0.25))
    footer(img)
    return img


def t_teaser(p):
    img = dark_canvas((W, H), black=True)
    d = ImageDraw.Draw(img)
    # A big blurred "50%" behind the words: a hint, not the reveal.
    ghost = Image.new('RGB', (W, H), (0, 0, 0))
    gd = ImageDraw.Draw(ghost)
    gd.text((W / 2, H * 0.42), '50%', font=font('display', 520), fill=mix(MINT, BLACK, 0.55), anchor='mm')
    ghost = ghost.filter(ImageFilter.GaussianBlur(38))
    img = Image.blend(img, Image.composite(ghost, img, ghost.convert('L').point(lambda v: 255 if v > 8 else 0)), 0.55)
    d = ImageDraw.Draw(img)
    y = pill(d, 'BLACK FRIDAY', (PAD, 96), MINT, GREEN, 28)
    y = rich(d, p['headline'], (PAD, 640), W - PAD * 2, 'display', 108, ON_DARK, MINT, max_lines=3) + 30
    para(d, p.get('sub', ''), (PAD, y), W - PAD * 2 - 60, 'body', 42, mix(ON_DARK, BLACK, 0.3))
    footer(img)
    return img


def t_deal(p):
    img = dark_canvas((W, H), black=True)
    d = ImageDraw.Draw(img)
    pill(d, p['label'], (PAD, 96), MINT, GREEN, 30)
    note = p.get('note', '')
    if note:
        fnt = font('body-b', 30)
        d.text((W - PAD - d.textlength(note, font=fnt), 108), note, font=fnt, fill=mix(ON_DARK, BLACK, 0.2))
    size = 300
    while d.textlength(OFFER['headline'], font=font('display', size)) > W - PAD * 2:
        size -= 6
    d.text((PAD - 6, 270), OFFER['headline'], font=font('display', size), fill=MINT)
    d.text((PAD, 270 + size * 1.22), OFFER['terms'], font=font('display', 88), fill=ON_DARK)
    d.text((PAD, 270 + size * 1.22 + 118), 'of atwebtech ' + OFFER['plans'], font=font('body', 44), fill=mix(ON_DARK, BLACK, 0.3))
    # The code, on a ticket.
    box = [PAD, 850, W - PAD, 1060]
    d.rounded_rectangle(box, radius=34, fill=mix(MINT, BLACK, 0.86), outline=mix(MINT, BLACK, 0.45), width=3)
    d.text((PAD + 44, 880), 'Use code at checkout', font=font('body-m', 32), fill=mix(ON_DARK, BLACK, 0.3))
    fnt = font('body-b', 32)
    ends = OFFER['dates']
    d.text((W - PAD - 44 - d.textlength(ends, font=fnt), 880), ends, font=fnt, fill=MINT)
    d.text((PAD + 44, 936), OFFER['code'], font=font('display', 82), fill=ON_DARK)
    footer(img)
    return img


def t_checklist(p):
    img = Image.new('RGB', (W, H), SAND)
    glow(img, (W - 80, 60), 620, MINT, 0.5)
    d = ImageDraw.Draw(img)
    y = pill(d, p.get('label', 'SAVE THIS'), (PAD, 96), GREEN, MINT) + 44
    y = rich(d, p['headline'], (PAD, y), W - PAD * 2, 'display', 84, INK, MINT_INK, max_lines=3, min_size=56) + 40
    items = p['items']
    room = H - 190 - y
    row = min(170, room // len(items))
    size = 46 if row >= 150 else (40 if row >= 100 else (34 if row >= 80 else 30))
    card = [PAD, y, W - PAD, y + row * len(items) + 16]
    d.rounded_rectangle(card, radius=36, fill=PANEL, outline=LINE, width=2)
    for i, item in enumerate(items):
        cy = y + 8 + row * i + row // 2
        r = min(30, row // 3)
        d.ellipse([PAD + 36, cy - r, PAD + 36 + r * 2, cy + r], fill=MINT_SOFT)
        d.line([(PAD + 36 + r * 0.55, cy), (PAD + 36 + r * 0.9, cy + r * 0.38), (PAD + 36 + r * 1.5, cy - r * 0.4)], fill=MINT_INK, width=max(4, r // 5), joint='curve')
        d.text((PAD + 36 + r * 2 + 26, cy), item, font=font('body-m', size), fill=INK, anchor='lm')
        if i:
            d.line([(PAD + 36, y + 8 + row * i), (W - PAD - 36, y + 8 + row * i)], fill=LINE, width=2)
    footer(img, dark=False)
    return img


def t_poll(p):
    img = Image.new('RGB', (W, H), MINT)
    glow(img, (W // 2, 0), 800, (255, 255, 255), 0.35)
    d = ImageDraw.Draw(img)
    y = pill(d, 'QUICK POLL', (PAD, 96), GREEN, MINT) + 70
    y = rich(d, p['headline'], (PAD, y), W - PAD * 2, 'display', 112, GREEN, GREEN, max_lines=4, min_size=72) + 70
    for letter, text in (('A', p['a']), ('B', p['b'])):
        d.rounded_rectangle([PAD, y, W - PAD, y + 170], radius=44, fill=PANEL)
        d.ellipse([PAD + 34, y + 37, PAD + 130, y + 133], fill=GREEN)
        d.text((PAD + 82, y + 85), letter, font=font('display', 56), fill=MINT, anchor='mm')
        d.text((PAD + 164, y + 85), text, font=font('display-m', 46), fill=INK, anchor='lm')
        y += 204
    d.text((PAD, y + 6), 'Comment A or B', font=font('body-b', 38), fill=MINT_INK)
    footer(img, dark=False)
    return img


def t_versus(p):
    img = Image.new('RGB', (W, H), SAND)
    d = ImageDraw.Draw(img)
    y = rich(d, p['headline'], (PAD, 110), W - PAD * 2, 'display', 80, INK, MINT_INK, max_lines=2, min_size=56) + 50
    col = (W - PAD * 2 - 28) // 2
    bottom = H - 190
    for i, (title, rows, good) in enumerate(((p['left_title'], p['left'], False), (p['right_title'], p['right'], True))):
        x = PAD + i * (col + 28)
        d.rounded_rectangle([x, y, x + col, bottom], radius=36, fill=GREEN if good else PANEL, outline=None if good else LINE, width=2)
        d.text((x + 36, y + 40), title, font=font('display', 44), fill=MINT if good else BAD)
        ry = y + 140
        step = (bottom - ry - 30) // len(rows)
        for row in rows:
            cy = ry + 30
            if good:
                d.ellipse([x + 36, cy - 20, x + 76, cy + 20], fill=mix(MINT, GREEN, 0.7))
                d.line([(x + 46, cy), (x + 54, cy + 9), (x + 67, cy - 9)], fill=MINT, width=5, joint='curve')
            else:
                d.ellipse([x + 36, cy - 20, x + 76, cy + 20], fill=BAD_BG)
                d.line([(x + 48, cy - 8), (x + 64, cy + 8)], fill=BAD, width=5)
                d.line([(x + 64, cy - 8), (x + 48, cy + 8)], fill=BAD, width=5)
            para(d, row, (x + 94, cy - 22), col - 94 - 30, 'body-m', 36, ON_DARK if good else INK, leading=1.25)
            ry += step
    footer(img, dark=False)
    return img


def t_screen(p):
    img = dark_canvas((W, H))
    d = ImageDraw.Draw(img)
    y = rich(d, p['headline'], (PAD, 96), W - PAD * 2, 'display', 84, ON_DARK, MINT, max_lines=3, min_size=56) + 22
    y = para(d, p.get('sub', ''), (PAD, y), W - PAD * 2, 'body', 38, mix(ON_DARK, GREEN, 0.25)) + 50
    phone(img, p['screen'], y, 640)
    # The footer sits on a fade so it stays readable over the screenshot.
    fade = gradient((W, 330), GREEN_DARK, GREEN_DARK)
    mask = Image.linear_gradient('L').resize((W, 330))
    img.paste(fade, (0, H - 330), mask)
    footer(img)
    return img


TEMPLATES = {'hook': t_hook, 'teaser': t_teaser, 'deal': t_deal, 'checklist': t_checklist, 'poll': t_poll, 'versus': t_versus, 'screen': t_screen}


def slug(p):
    base = p.get('headline') or p['slides'][0][0] if p.get('template') != 'deal' else p['label']
    text = ''.join(c.lower() if c.isalnum() else '-' for c in base.replace('*', ''))
    text = '-'.join(filter(None, text.split('-')))[:38].rstrip('-')
    return ('day%02d-' % p['day'] if p['day'] else 'bonus-app-launch-') + text


def make_images():
    os.makedirs(OUT_IMG, exist_ok=True)
    for p in POSTS + [BONUS]:
        if p['kind'] != 'image':
            continue
        TEMPLATES[p['template']](p).save(os.path.join(OUT_IMG, slug(p) + '.png'), optimize=True)
        print('image', slug(p))


# ---- Videos (1080 x 1920, for Reels, Shorts and TikTok) ----------------------------------------------

VW, VH = 1080, 1920
SLIDE_SECONDS, FADE = 2.6, 0.45


def slide(index, total, head, sub, last):
    img = dark_canvas((VW, VH))
    d = ImageDraw.Draw(img)
    # Progress dashes at the top, like a story.
    gap, x = 12, PAD
    seg = (VW - PAD * 2 - gap * (total - 1)) / total
    for i in range(total):
        d.rounded_rectangle([x, 150, x + seg, 158], radius=4, fill=MINT if i <= index else mix(ON_DARK, GREEN, 0.75))
        x += seg + gap
    # Measure first so the words sit in the middle of the safe area.
    probe = ImageDraw.Draw(Image.new('RGB', (VW, VH)))
    size = 128 if index == 0 or last else 108
    bottom = rich(probe, head, (PAD, 0), VW - PAD * 2, 'display', size, ON_DARK, MINT, max_lines=5, min_size=72)
    y = max(420, (VH - bottom) // 2 - (60 if sub else 0) - 80)
    y = rich(d, head, (PAD, y), VW - PAD * 2, 'display', size, ON_DARK, MINT, max_lines=5, min_size=72) + 40
    para(d, sub, (PAD, y), VW - PAD * 2 - 40, 'body-m', 52, MINT if last else mix(ON_DARK, GREEN, 0.25))
    footer(img, lift=250, scale=1.35)  # lifted clear of the caption area the apps draw over the bottom
    return img


def make_videos():
    os.makedirs(OUT_VID, exist_ok=True)
    for p in POSTS:
        if p['kind'] != 'video':
            continue
        slides = p['slides']
        with tempfile.TemporaryDirectory() as tmp:
            files = []
            for i, (head, sub) in enumerate(slides):
                path = os.path.join(tmp, '%d.png' % i)
                slide(i, len(slides), head, sub, i == len(slides) - 1).save(path)
                files.append(path)
            frames = int(SLIDE_SECONDS * 30)
            cmd = ['ffmpeg', '-v', 'error', '-y']
            for path in files:
                cmd += ['-loop', '1', '-t', str(SLIDE_SECONDS), '-i', path]
            total = len(files) * SLIDE_SECONDS - (len(files) - 1) * FADE
            cmd += ['-f', 'lavfi', '-t', '%.2f' % total, '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']
            # Each slide drifts in slowly, then slides into the next one.
            parts = ["[%d:v]scale=2160:3840,zoompan=z='1.07-0.07*on/%d':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=%d:s=%dx%d:fps=30,format=yuv420p[v%d]"
                     % (i, frames, frames, VW, VH, i) for i in range(len(files))]
            last = 'v0'
            for i in range(1, len(files)):
                offset = i * (SLIDE_SECONDS - FADE)
                parts.append('[%s][v%d]xfade=transition=slideup:duration=%s:offset=%.2f[x%d]' % (last, i, FADE, offset, i))
                last = 'x%d' % i
            out = os.path.join(OUT_VID, slug(p) + '.mp4')
            cmd += ['-filter_complex', ';'.join(parts), '-map', '[%s]' % last, '-map', '%d:a' % len(files), '-c:v', 'libx264', '-preset', 'medium',
                    '-crf', '20', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '96k', '-shortest', '-movflags', '+faststart', out]
            subprocess.run(cmd, check=True)
            # The first slide doubles as the cover image.
            Image.open(files[0]).save(os.path.join(OUT_VID, slug(p) + '-cover.jpg'), quality=90)
            print('video', slug(p), '%.1fs' % total)


# ---- The calendar --------------------------------------------------------------------------------------

def make_calendar():
    rows = []
    md = ['# atwebtech social calendar: 21 Nov to 20 Dec 2026', '',
          'One post a day. Images are 1080 x 1350 (feed posts on Instagram, Facebook, LinkedIn, Threads, Bluesky). Videos are 1080 x 1920 with no sound, for Reels, Shorts and TikTok: add a trending sound in the app when you post, it helps reach.',
          '',
          '**Black Friday offer in these posts:** %s %s on %s, %s, code `%s`. Create this promo code in Stripe before 23 Nov or the posts promise something checkout cannot give.'
          % (OFFER['headline'].lower(), OFFER['terms'], OFFER['plans'], OFFER['dates'], OFFER['code']), '']
    for p in POSTS + [BONUS]:
        folder = 'images' if p['kind'] == 'image' else 'videos'
        name = slug(p) + ('.png' if p['kind'] == 'image' else '.mp4')
        title = ('Day %d · %s' % (p['day'], p['date'])) if p['day'] else 'Bonus · ' + p['date']
        md += ['## %s · %s · %s' % (title, p['pillar'], p['kind']), '', 'File: `%s/%s`' % (folder, name), '', p['caption'].strip(), '', p['tags'], '', '---', '']
        rows.append([p['day'] or 'bonus', p['date'], p['pillar'], p['kind'], folder + '/' + name, p['caption'].strip(), p['tags']])
    open(os.path.join(HERE, 'calendar.md'), 'w').write('\n'.join(md))
    with open(os.path.join(HERE, 'calendar.csv'), 'w', newline='') as f:
        w = csv.writer(f)
        w.writerow(['day', 'date', 'pillar', 'type', 'file', 'caption', 'hashtags'])
        w.writerows(rows)
    print('calendar', len(rows), 'posts')


if __name__ == '__main__':
    what = sys.argv[1] if len(sys.argv) > 1 else 'all'
    if what in ('all', 'images'):
        make_images()
    if what in ('all', 'videos'):
        make_videos()
    make_calendar()
