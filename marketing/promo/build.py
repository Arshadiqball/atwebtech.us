"""Builds promo.html: a 60 second, 1920x1080 animated page. seek(t) puts every animation at time t, so it can be captured frame by frame."""
import os, re, sys

V = len(sys.argv) > 1 and sys.argv[1] == 'v'  # vertical 1080 x 1920 for Reels, TikTok and Shorts
W, H = (1080, 1920) if V else (1920, 1080)

SP = os.path.dirname(os.path.abspath(__file__))
ROOT = '/Users/mac/Documents/atwebtech/atwebtechus'
F = 'file://' + ROOT + '/public/assets/fonts/'
IMG = 'file://' + ROOT


def glyph(slug):
    s = open(f'{ROOT}/app/icons/brands/{slug}.svg').read()
    s = re.sub(r'<title>.*?</title>', '', s)
    return s.replace('<svg ', '<svg fill="#fff" ', 1)


NET = {
    'instagram': ('radial-gradient(circle at 30% 107%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285AEB 90%)', glyph('instagram')),
    'facebook': ('#1877F2', glyph('facebook')),
    'linkedin': ('#0A66C2', '<b>in</b>'),
    'youtube': ('#FF0000', glyph('youtube')),
    'pinterest': ('#E60023', glyph('pinterest')),
    'threads': ('#000', glyph('threads')),
    'bluesky': ('#1185FE', glyph('bluesky')),
}


def net(slug, cls='', attrs=''):
    bg, g = NET[slug]
    return f'<span class="net {cls}" style="background:{bg}" {attrs}>{g}</span>'


def words(text, start, step, kind='slam', cls=''):
    out = []
    for i, w in enumerate(text.split(' ')):
        out.append(f'<span class="w {cls}" data-in="{start + i * step:.2f} {kind} .55">{w}</span>')
    return ' '.join(out)


def feature(n, label, head, sub, s):
    return f'''<div class="copy">
  <span class="chip" data-in="{s + .15:.2f} left .6"><i>{n}</i>{label}</span>
  <h2>{words(head, s + .35, .09, 'up')}</h2>
  <p data-in="{s + 1.2:.2f} up .7">{sub}</p>
</div>'''



ST = ['left:60px;top:430px;transform:rotate(-7deg)', 'right:60px;top:540px;transform:rotate(6deg)', 'left:70px;bottom:560px;transform:rotate(5deg)', 'right:70px;bottom:450px;transform:rotate(-5deg)'] if V else \
     ['left:150px;top:150px;transform:rotate(-7deg)', 'right:170px;top:190px;transform:rotate(6deg)', 'left:210px;bottom:170px;transform:rotate(5deg)', 'right:210px;bottom:150px;transform:rotate(-5deg)']
CUR = (1000, 800, 975, 700, 949, 640) if V else (1000, 900, 975, 830, 944, 768)
# Vertical: copy on top, the screen below, and nothing important in the top 250 px or bottom 320 px (covered by the apps' own buttons).
VCSS = """
h1 { font-size: 150px; } h1.mid { font-size: 118px; }
.center { padding: 0 60px; }
.sticker { font-size: 34px; padding: 16px 26px; }
.big3 { gap: 70px; }
.logo { font-size: 124px !important; gap: 26px; } .logo img { width: 140px !important; height: 140px !important; }
.tag { font-size: 52px; }
.copy { left: 70px; width: 940px; top: 270px; bottom: auto; display: block; }
.copy h2 { font-size: 100px; }
.copy p { font-size: 42px; }
.stage { left: 0; right: 0; top: 800px; bottom: 320px; }
.browser { width: 980px; } .view { height: 612px; }
.composer, .inbox, .stats { width: 960px; }
.post { left: 70px; top: 960px; width: 430px; }
.note { right: 0; left: 0; text-align: center; bottom: 340px; }
.ticks { flex-direction: column; gap: 30px; align-items: flex-start; font-size: 48px; }
.cta { font-size: 62px; padding: 36px 64px; }
.orb { width: 92px; height: 92px; border-radius: 23px; margin: -46px 0 0 -46px; } .orb b { font-size: 50px; }
"""

ring = ['instagram', 'facebook', 'linkedin', 'youtube', 'pinterest', 'threads', 'bluesky']
import math
ring_html, line_html = '', ''
for i, slug in enumerate(ring):
    a = -math.pi / 2 + (i - 3) * (2 * math.pi / 9.2) + math.pi  # spread on the right-hand arc
    a = math.radians([-66, -44, -22, 0, 22, 44, 66][i])
    x, y = (660 + 320 * math.cos(a), 1250 + 330 * math.sin(a)) if V else (1450 + 290 * math.cos(a), 540 + 440 * math.sin(a))
    t = 29.5 + i * 0.5
    ring_html += net(slug, 'orb', f'data-in="{t:.2f} pop .5" style2="left:{x:.0f}px;top:{y:.0f}px"') .replace('style2="', 'data-pos="')
    ring_html += f'<span class="ok" data-pos="left:{x + 34:.0f}px;top:{y - 56:.0f}px" data-in="{t + .9:.2f} pop .4">✓</span>'
    line_html += f'<line x1="{500 if V else 1310}" y1="{1250 if V else 540}" x2="{x:.0f}" y2="{y:.0f}" data-draw="{t - .2:.2f}"/>'

bars = ''.join(f'<i style="--h:{h}%" data-in="{44.0 + i * .12:.2f} grow .7"></i>' for i, h in enumerate([28, 36, 33, 48, 44, 60, 57, 72, 80, 96]))

html = f'''<!doctype html><html><head><meta charset="utf-8"><style>
@font-face {{ font-family: Figtree; src: url("{F}figtree-latin-400-normal.woff2"); font-weight: 400; }}
@font-face {{ font-family: Figtree; src: url("{F}figtree-latin-500-normal.woff2"); font-weight: 500; }}
@font-face {{ font-family: Figtree; src: url("{F}figtree-latin-600-normal.woff2"); font-weight: 600 800; }}
@font-face {{ font-family: Jakarta; src: url("{F}plus-jakarta-sans-latin-600-normal.woff2"); font-weight: 500 800; }}
* {{ box-sizing: border-box; margin: 0; padding: 0; }}
html, body {{ width: {W}px; height: {H}px; overflow: hidden; background: #101C19; }}
body {{ font-family: Figtree, sans-serif; color: #213130; -webkit-font-smoothing: antialiased; }}
section {{ position: absolute; inset: 0; overflow: hidden; visibility: hidden; }}
.dark {{ background: radial-gradient(1200px 800px at 50% 40%, #1F4D36 0%, #14251F 55%, #0E1815 100%); color: #F4F8F1; }}
.light {{ background: #FEFDFB; }}
.sand {{ background: #F0EEE7; }}
.blob {{ position: absolute; border-radius: 50%; opacity: .55; -webkit-mask: radial-gradient(closest-side, #000 0%, rgba(0,0,0,.6) 40%, transparent 100%); }}
.grid {{ position: absolute; inset: -40px; background-image: linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px); background-size: 80px 80px; }}
.light .grid, .sand .grid {{ background-image: linear-gradient(rgba(33,49,48,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(33,49,48,.05) 1px, transparent 1px); }}
.center {{ position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }}
h1 {{ font: 600 190px/1.02 Jakarta, sans-serif; letter-spacing: -.045em; }}
h1.mid {{ font-size: 150px; }}
.w {{ display: inline-block; will-change: transform; }}
.g {{ color: #B0EC9C; }}
.gi {{ color: #2F6A43; }}
.sticker {{ position: absolute; padding: 18px 30px; border-radius: 22px; font: 600 38px/1 Figtree; background: #fff; color: #213130; box-shadow: 0 24px 50px -18px rgba(0,0,0,.55); white-space: nowrap; }}
.net {{ display: inline-flex; align-items: center; justify-content: center; width: 124px; height: 124px; border-radius: 30px; box-shadow: 0 22px 44px -18px rgba(0,0,0,.5); }}
.net svg {{ width: 58%; height: 58%; }}
.net b {{ font: 800 66px/1 Arial, sans-serif; color: #fff; letter-spacing: -.04em; }}
.net.sm {{ width: 44px; height: 44px; border-radius: 12px; box-shadow: none; }}
.net.sm b {{ font-size: 24px; }}
.big3 {{ display: flex; gap: 90px; align-items: baseline; }}
.big3 div {{ font: 600 210px/1 Jakarta; letter-spacing: -.05em; }}
.big3 small {{ display: block; font: 600 54px/1 Figtree; letter-spacing: 0; opacity: .7; margin-top: 14px; }}
.logo {{ display: flex; align-items: center; gap: 34px; font: 600 170px/1 Jakarta; letter-spacing: -.05em; }}
.logo img {{ width: 190px; height: 190px; }}
.tag {{ font: 500 62px/1.2 Figtree; margin-top: 44px; color: #3B4847; }}
.dark .tag {{ color: #CFE3D2; }}
.copy {{ position: absolute; left: 110px; top: 0; bottom: 0; width: 640px; display: flex; flex-direction: column; justify-content: center; }}
.chip {{ align-self: flex-start; display: inline-flex; align-items: center; gap: 14px; padding: 12px 26px 12px 12px; border-radius: 99px; background: #E4F6DC; color: #2F6A43; font: 600 32px/1 Figtree; }}
.chip i {{ font-style: normal; display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; border-radius: 50%; background: #B0EC9C; color: #14301C; font-size: 26px; }}
.dark .chip {{ background: rgba(176,236,156,.14); color: #B0EC9C; }}
.copy h2 {{ font: 600 96px/1.04 Jakarta; letter-spacing: -.04em; margin: 34px 0 30px; }}
.copy p {{ font: 500 38px/1.35 Figtree; color: #56605C; }}
.dark .copy p {{ color: #BFD3C4; }}
.stage {{ position: absolute; left: 800px; right: 70px; top: 0; bottom: 0; display: flex; align-items: center; justify-content: center; perspective: 2200px; }}
.browser {{ width: 1040px; border-radius: 22px; background: #fff; overflow: hidden; box-shadow: 0 60px 120px -40px rgba(33,49,48,.55), 0 0 0 1px rgba(33,49,48,.08); }}
.bar {{ height: 52px; background: #F0EEE7; display: flex; align-items: center; gap: 10px; padding: 0 20px; }}
.bar u {{ width: 14px; height: 14px; border-radius: 50%; background: #D6D2C9; }}
.bar span {{ margin-left: 18px; flex: 1; height: 30px; border-radius: 99px; background: #fff; font: 500 16px/30px Figtree; color: #64665F; padding: 0 16px; }}
.view {{ height: 650px; overflow: hidden; }}
.view img {{ width: 100%; display: block; transform-origin: 46% 22%; }}
.card {{ color: #213130; background: #fff; border-radius: 28px; box-shadow: 0 60px 120px -40px rgba(33,49,48,.5), 0 0 0 1px rgba(33,49,48,.07); }}
.composer {{ width: 980px; padding: 34px 38px; }}
.composer header {{ display: flex; align-items: center; justify-content: space-between; font: 600 34px/1 Jakarta; letter-spacing: -.02em; }}
.pill {{ display: inline-flex; align-items: center; gap: 10px; padding: 12px 22px; border-radius: 99px; border: 2px solid #D6D2C9; font: 600 24px/1 Figtree; }}
.pill.ai {{ border-color: #D4C2FF; background: #F5F0FF; color: #4B2FA8; }}
.avs {{ display: flex; gap: 16px; margin: 28px 0 22px; }}
.av {{ position: relative; width: 70px; height: 70px; border-radius: 50%; background: #FFD88A; font: 600 30px/70px Jakarta; text-align: center; outline: 4px solid #B0EC9C; outline-offset: 3px; }}
.av .net {{ position: absolute; right: -8px; bottom: -8px; width: 32px; height: 32px; border-radius: 9px; }}
.av .net b {{ font-size: 17px; }}
.field {{ min-height: 250px; border: 2px solid #9DE485; border-radius: 18px; padding: 26px 28px; font: 500 36px/1.42 Figtree; box-shadow: 0 0 0 6px rgba(176,236,156,.35); }}
.field em {{ font-style: normal; background: linear-gradient(90deg, #EFE7FF, #E4F6DC); border-radius: 8px; }}
.caret {{ display: inline-block; width: 4px; height: 40px; background: #2F6A43; vertical-align: -7px; margin-left: 2px; }}
.composer footer {{ display: flex; align-items: center; gap: 16px; margin-top: 26px; }}
.btn {{ display: inline-flex; align-items: center; gap: 12px; padding: 20px 34px; border-radius: 99px; background: #B0EC9C; color: #14301C; font: 600 28px/1 Figtree; }}
.btn.done {{ background: #213130; color: #B0EC9C; }}
.cursor {{ position: absolute; width: 54px; height: 54px; z-index: 9; }}
.post {{ position: absolute; left: 870px; top: 250px; width: 440px; padding: 22px; }}
.post img {{ width: 100%; height: 300px; object-fit: cover; border-radius: 16px; display: block; }}
.post .who {{ display: flex; align-items: center; gap: 14px; font: 600 26px/1 Figtree; margin-bottom: 18px; }}
.post .who i {{ width: 52px; height: 52px; border-radius: 50%; background: #FFD88A; font: 600 24px/52px Jakarta; text-align: center; font-style: normal; }}
.post p {{ font: 500 25px/1.35 Figtree; margin-top: 18px; }}
.post .when {{ margin-top: 16px; display: inline-flex; padding: 9px 16px; border-radius: 99px; background: #E4F6DC; color: #2F6A43; font: 600 21px/1 Figtree; }}
.orb {{ position: absolute; width: 108px; height: 108px; border-radius: 27px; margin: -54px 0 0 -54px; }}
.orb b {{ font-size: 58px; }}
.ok {{ position: absolute; width: 46px; height: 46px; margin: -8px 0 0 -8px; border-radius: 50%; background: #B0EC9C; color: #14301C; font: 800 28px/46px Figtree; text-align: center; box-shadow: 0 0 0 5px #14251F; }}
svg.lines {{ position: absolute; inset: 0; width: {W}px; height: {H}px; }}
svg.lines line {{ stroke: #B0EC9C; stroke-width: 3; stroke-dasharray: 2 12; stroke-linecap: round; opacity: .7; }}
.inbox {{ width: 980px; display: flex; flex-direction: column; gap: 22px; }}
.msg {{ padding: 28px 32px; }}
.msg .who {{ display: flex; align-items: center; gap: 16px; font: 600 28px/1 Figtree; }}
.msg .who i {{ width: 58px; height: 58px; border-radius: 50%; font: 600 26px/58px Jakarta; text-align: center; font-style: normal; }}
.msg .who small {{ font: 500 22px/1 Figtree; color: #64665F; margin-left: 6px; }}
.msg .who .net {{ margin-left: auto; }}
.msg > p {{ font: 500 32px/1.35 Figtree; margin-top: 18px; }}
.reply {{ margin-top: 20px; display: flex; align-items: center; gap: 16px; }}
.reply div {{ flex: 1; min-height: 76px; border: 2px solid #9DE485; border-radius: 16px; padding: 16px 22px; font: 500 28px/1.4 Figtree; }}
.stats {{ width: 1000px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }}
.stat {{ padding: 30px 32px; }}
.stat small {{ font: 600 24px/1 Figtree; color: #64665F; }}
.stat b {{ display: block; font: 600 78px/1 Jakarta; letter-spacing: -.04em; margin: 16px 0 12px; font-variant-numeric: tabular-nums; }}
.stat span {{ font: 600 22px/1 Figtree; color: #2F6A43; }}
.chart {{ grid-column: 1 / -1; padding: 30px 32px; }}
.chart .bars {{ height: 250px; display: flex; align-items: flex-end; gap: 18px; margin-top: 22px; }}
.chart .bars i {{ flex: 1; height: var(--h); border-radius: 12px 12px 4px 4px; background: linear-gradient(#B0EC9C, #60BE6E); transform-origin: bottom; }}
.note {{ position: absolute; right: 80px; bottom: 44px; font: 500 20px/1 Figtree; color: #8A877C; }}
.cta {{ margin-top: 60px; padding: 34px 70px; border-radius: 99px; background: #B0EC9C; color: #14301C; font: 600 60px/1 Figtree; box-shadow: 0 30px 70px -20px rgba(176,236,156,.55); }}
.url {{ margin-top: 40px; font: 600 50px/1 Figtree; letter-spacing: .01em; color: #CFE3D2; }}
.ticks {{ display: flex; gap: 46px; margin-top: 54px; font: 600 42px/1 Figtree; color: #CFE3D2; }}
.ticks span::before {{ content: "✓"; display: inline-block; width: 50px; height: 50px; margin-right: 14px; border-radius: 50%; background: #B0EC9C; color: #14301C; font: 800 30px/50px Figtree; text-align: center; vertical-align: 2px; }}
#fade {{ position: absolute; inset: 0; background: #0E1815; opacity: 0; z-index: 99; pointer-events: none; }}
{VCSS if V else ''}
</style></head><body>

<!-- 1. Hook -->
<section class="dark" data-s="0" data-e="4">
  <div class="grid" data-drift></div>
  <div class="center"><h1>{words('Still posting', .15, .28)}<br><span class="g">{words('last minute?', .85, .32)}</span></h1></div>
  <span class="sticker" style="{ST[0]}" data-in="1.9 pop .5">⏰ 11:58 PM</span>
  <span class="sticker" style="{ST[1]}" data-in="2.2 pop .5">Caption?? 😩</span>
  <span class="sticker" style="{ST[2]}" data-in="2.5 pop .5">“Did we post today?”</span>
  <span class="sticker" style="{ST[3]}" data-in="2.8 pop .5">Which app was it… 🤯</span>
</section>

<!-- 2. Problem -->
<section class="dark" data-s="4" data-e="8">
  <div class="grid" data-drift></div>
  <div class="center">
    <div class="big3">
      <div data-in="4.1 slam .5">6<small>apps</small></div>
      <div data-in="5.0 slam .5">6<small>tabs</small></div>
      <div class="g" data-in="6.0 slam .5">0<small>plan</small></div>
    </div>
    <p class="tag" data-in="6.8 up .6">There is an easier way.</p>
  </div>
</section>

<!-- 3. Reveal -->
<section class="light" data-s="8" data-e="12">
  <div class="blob" style="left:-200px;top:-200px;width:900px;height:900px;background:#B0EC9C" data-float="40"></div>
  <div class="blob" style="right:-250px;bottom:-300px;width:900px;height:900px;background:#D4C2FF" data-float="-40"></div>
  <div class="blob" style="right:300px;top:-300px;width:600px;height:600px;background:#FFD88A" data-float="30"></div>
  <div class="center">
    <div class="logo"><img src="{IMG}/public/assets/img/logo-mark-512.png" data-in="8.05 pop .7"><span data-in="8.4 left .7">atwebtech</span></div>
    <p class="tag">{words('All your social media. One calm place.', 9.4, .11, 'up')}</p>
  </div>
</section>

<!-- 4. Plan -->
<section class="sand" data-s="12" data-e="20">
  <div class="grid"></div>
  {feature(1, 'Plan', 'Plan the whole week in one sitting.', 'A visual calendar for every channel. Drag, drop, done.', 12)}
  <div class="stage"><div class="browser" id="cal" data-in="12.5 tilt 1.1">
    <div class="bar"><u></u><u></u><u></u><span>atwebtech.us/app/publish</span></div>
    <div class="view"><img id="calimg" src="file://{SP}/shots/calendar.jpg"></div>
  </div></div>
</section>

<!-- 5. Create -->
<section class="light" data-s="20" data-e="28">
  <div class="blob" style="right:-200px;top:-250px;width:800px;height:800px;background:#D4C2FF;opacity:.45" data-float="30"></div>
  <div class="blob" style="right:500px;bottom:-350px;width:700px;height:700px;background:#B0EC9C;opacity:.45" data-float="-30"></div>
  {feature(2, 'Create', 'Write once. Let AI help with the draft.', 'Start with a rough thought. Review it, then schedule.', 20)}
  <div class="stage"><div class="card composer" data-in="20.5 tilt 1">
    <header><span>Create Post</span><span class="pill ai" id="aipill">✨ AI Assistant</span></header>
    <div class="avs">
      <div class="av">S{net('instagram', 'sm')}</div><div class="av" style="background:#ADDAFF">S{net('linkedin', 'sm')}</div>
      <div class="av" style="background:#FFB2A8">S{net('youtube', 'sm')}</div><div class="av" style="background:#D4C2FF">S{net('threads', 'sm')}</div>
      <div class="av" style="background:#9FEAE2">S{net('bluesky', 'sm')}</div>
    </div>
    <div class="field"><span id="typed"></span><em id="ai"></em><span class="caret" id="caret"></span></div>
    <footer><span class="pill">📅 Tue, Nov 24</span><span class="pill">🕘 9:00 AM</span><span style="flex:1"></span><span class="btn" id="sched">Schedule Post</span></footer>
  </div>
  <svg class="cursor" id="cur" viewBox="0 0 22 22"><path d="M3 2l15 8-6.5 1.8L9 18z" fill="#14301C" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>
  </div>
</section>

<!-- 6. Publish -->
<section class="dark" data-s="28" data-e="34">
  <div class="grid" data-drift></div>
  {feature(3, 'Publish', 'Posts itself. Everywhere. On time.', 'Schedule it once and close the laptop.', 28)}
  <svg class="lines">{line_html}</svg>
  <div class="card post" data-in="28.5 pop .8">
    <div class="who"><i>S</i>Sunrise Bakery</div>
    <img src="{IMG}/public/assets/img/posts/bread-480.webp">
    <p>Saturday special: cinnamon rolls, warm from 8 am.</p>
    <span class="when">Scheduled · Tue 9:00 AM</span>
  </div>
  {ring_html}
</section>

<!-- 7. Engage -->
<section class="sand" data-s="34" data-e="42">
  <div class="grid"></div>
  {feature(4, 'Engage', 'Every comment. One inbox.', 'Reply to everyone without opening a single app.', 34)}
  <div class="stage"><div class="inbox">
    <div class="card msg" data-in="34.6 right .8">
      <div class="who"><i style="background:#F3BDFF">E</i>@emma.eats<small>2h ago</small>{net('threads', 'sm')}</div>
      <p>These look amazing! Do you have gluten-free bread too?</p>
      <div class="reply"><div><span id="rep"></span><span class="caret" id="caret2"></span></div><span class="btn" id="send">Send reply</span></div>
    </div>
    <div class="card msg" data-in="35.1 right .8">
      <div class="who"><i style="background:#ADDAFF">M</i>@mike_r<small>3h ago</small>{net('instagram', 'sm')}</div>
      <p>What is the price for a breakfast box for 12 people?</p>
    </div>
    <div class="card msg" data-in="35.6 right .8">
      <div class="who"><i style="background:#9FEAE2">L</i>@lena.bsky.social<small>3h ago</small>{net('bluesky', 'sm')}</div>
      <p>Welcome! Looking forward to the bread photos.</p>
    </div>
  </div></div>
</section>

<!-- 8. Grow -->
<section class="light" data-s="42" data-e="49">
  <div class="blob" style="right:-200px;top:-250px;width:800px;height:800px;background:#ADDAFF;opacity:.45" data-float="30"></div>
  <div class="blob" style="right:600px;bottom:-350px;width:700px;height:700px;background:#B0EC9C;opacity:.45" data-float="-30"></div>
  {feature(5, 'Grow', 'See what works. Do more of it.', 'Followers, reach and best times to post, in plain numbers.', 42)}
  <div class="stage"><div class="stats">
    <div class="card stat" data-in="42.6 up .7"><small>Followers</small><b data-count="7305" data-from="43">0</b><span>▲ 685 this month</span></div>
    <div class="card stat" data-in="42.8 up .7"><small>Reactions</small><b data-count="741" data-from="43.2">0</b><span>across 5 channels</span></div>
    <div class="card stat" data-in="43.0 up .7"><small>Engagement rate</small><b data-count="5.94" data-dec="2" data-suffix="%" data-from="43.4">0</b><span>last 30 days</span></div>
    <div class="card chart" data-in="43.3 up .7"><small style="font:600 24px/1 Figtree;color:#64665F">Reach by week</small><div class="bars">{bars}</div></div>
  </div></div>
  <span class="note">Screens show a sample workspace.</span>
</section>

<!-- 9. Offer -->
<section class="dark" data-s="49" data-e="54">
  <div class="grid" data-drift></div>
  <div class="center">
    <h1 class="mid">{words('Start free.', 49.2, .25)}<br><span class="g">{words('Stay consistent.', 50.1, .3)}</span></h1>
    <div class="ticks"><span data-in="51.3 up .5">Free for 3 channels</span><span data-in="51.7 up .5">No credit card</span><span data-in="52.1 up .5">Set up in minutes</span></div>
  </div>
</section>

<!-- 10. End card -->
<section class="dark" data-s="54" data-e="60.5">
  <div class="blob" style="left:-200px;top:-300px;width:900px;height:900px;background:#2F6A43;opacity:.6" data-float="40"></div>
  <div class="blob" style="right:-250px;bottom:-350px;width:900px;height:900px;background:#4B2FA8;opacity:.35" data-float="-40"></div>
  <div class="center">
    <div class="logo" style="font-size:150px"><img src="file://{ROOT}/marketing/assets/logo-dark.png" style="width:170px;height:170px" data-in="54.1 pop .7"><span data-in="54.4 left .7">atwebtech</span></div>
    <p class="tag" data-in="55.2 up .6">Plan, schedule and publish your social media in one place.</p>
    <div class="cta" id="cta" data-in="56.0 pop .7">Start free today →</div>
    <div class="url" data-in="56.7 up .6">atwebtech.us</div>
  </div>
</section>
<div id="fade"></div>

<script>
const OUT = 'cubic-bezier(.16,1,.3,1)', BACK = 'cubic-bezier(.34,1.56,.64,1)';
const K = {{
  up: [{{opacity: 0, transform: 'translateY(70px)'}}, {{opacity: 1, transform: 'translateY(0)'}}],
  left: [{{opacity: 0, transform: 'translateX(-90px)'}}, {{opacity: 1, transform: 'translateX(0)'}}],
  right: [{{opacity: 0, transform: 'translateX(140px)'}}, {{opacity: 1, transform: 'translateX(0)'}}],
  pop: [{{opacity: 0, scale: '.4'}}, {{opacity: 1, scale: '1'}}],
  slam: [{{opacity: 0, scale: '2.2', filter: 'blur(14px)'}}, {{opacity: 1, scale: '1', filter: 'blur(0px)'}}],
  tilt: [{{opacity: 0, transform: 'translateY(160px) rotateX(18deg) rotateY(-14deg) scale(.86)'}}, {{opacity: 1, transform: 'translateY(0) rotateX(0deg) rotateY(-5deg) scale(1)'}}],
  grow: [{{transform: 'scaleY(0)'}}, {{transform: 'scaleY(1)'}}],
}};
document.querySelectorAll('[data-pos]').forEach(el => el.style.cssText += ';' + el.dataset.pos);
document.querySelectorAll('[data-in]').forEach(el => {{
  const [t, k, d] = el.dataset.in.split(' ');
  el.animate(K[k], {{delay: t * 1000, duration: (d || .7) * 1000, fill: 'both', easing: (k === 'pop' || k === 'slam') ? BACK : OUT}});
}});
const scenes = [...document.querySelectorAll('section')];
scenes.forEach((s, i) => {{
  s.style.zIndex = i + 1;
  if (i) s.animate([{{opacity: 0, transform: 'scale(1.08)'}}, {{opacity: 1, transform: 'scale(1)'}}], {{delay: s.dataset.s * 1000, duration: 380, fill: 'both', easing: OUT}});
  // a slow push-in keeps every scene moving
  const inner = [...s.children].filter(c => !c.matches('.grid,.blob,.note'));
  inner.forEach(c => c.animate([{{translate: '0 0'}}, {{translate: '0 -14px'}}], {{delay: s.dataset.s * 1000, duration: (s.dataset.e - s.dataset.s) * 1000, fill: 'both', composite: 'add'}}));
}});
document.querySelectorAll('[data-drift]').forEach(el => el.animate([{{transform: 'translate(0,0)'}}, {{transform: 'translate(-80px,-80px)'}}], {{duration: 8000, iterations: Infinity}}));
document.querySelectorAll('[data-float]').forEach(el => el.animate([{{transform: 'translate(0,0) scale(1)'}}, {{transform: `translate(${{el.dataset.float}}px, ${{-el.dataset.float}}px) scale(1.15)`}}], {{duration: 5000, iterations: Infinity, direction: 'alternate', easing: 'ease-in-out'}}));
document.querySelectorAll('[data-draw]').forEach(el => el.animate([{{opacity: 0}}, {{opacity: .7}}], {{delay: el.dataset.draw * 1000, duration: 400, fill: 'both'}}));
// calendar: push in on the week
document.getElementById('calimg').animate([{{transform: 'scale(1)'}}, {{transform: 'scale(1)', offset: .25}}, {{transform: 'scale(1.75)'}}], {{delay: 13200, duration: 6000, fill: 'both', easing: 'cubic-bezier(.45,0,.2,1)'}});
document.getElementById('cal').animate([{{rotate: 'y 0deg'}}, {{rotate: 'y 5deg'}}], {{delay: 13600, duration: 6000, fill: 'both', composite: 'add'}});
// AI pill pulse and buttons
document.getElementById('aipill').animate([{{boxShadow: '0 0 0 0 rgba(140,100,255,.0)', scale: '1'}}, {{boxShadow: '0 0 0 18px rgba(140,100,255,.28)', scale: '1.12'}}, {{boxShadow: '0 0 0 0 rgba(140,100,255,0)', scale: '1'}}], {{delay: 23400, duration: 700, fill: 'both'}});
const cur = document.getElementById('cur');
cur.animate([{{left: '{CUR[0]}px', top: '{CUR[1]}px', opacity: 0}}, {{left: '{CUR[2]}px', top: '{CUR[3]}px', opacity: 1, offset: .2}}, {{left: '{CUR[4]}px', top: '{CUR[5]}px', opacity: 1}}], {{delay: 25500, duration: 900, fill: 'both', easing: OUT}});
cur.animate([{{scale: '1'}}, {{scale: '.8'}}, {{scale: '1'}}], {{delay: 26400, duration: 250, fill: 'both'}});
document.getElementById('cta').animate([{{scale: '1'}}, {{scale: '1.06'}}, {{scale: '1'}}], {{delay: 57000, duration: 1000, iterations: 3, fill: 'both', easing: 'ease-in-out', composite: 'add'}});
document.getElementById('fade').animate([{{opacity: 0}}, {{opacity: 1}}], {{delay: 59400, duration: 600, fill: 'both'}});

const T1 = 'Saturday special: cinnamon rolls, warm from 8 am.', T2 = ' First come, first served. Bring a friend! #bakery #weekend';
const R = 'Yes! Fresh gluten-free loaves every Friday. 🍞';
const clamp = (x) => Math.max(0, Math.min(1, x));
const fx = [
  (t) => {{
    typed.textContent = T1.slice(0, Math.round(T1.length * clamp((t - 21.3) / 2.0)));
    ai.textContent = T2.slice(0, Math.round(T2.length * clamp((t - 24.0) / 1.1)));
    caret.style.opacity = t > 26.4 ? 0 : (Math.floor(t * 2.5) % 2 ? 1 : .15);
    const done = t >= 26.5;
    sched.textContent = done ? '✓ Scheduled' : 'Schedule Post'; sched.className = done ? 'btn done' : 'btn';
  }},
  (t) => {{
    rep.textContent = [...R].slice(0, Math.round([...R].length * clamp((t - 36.6) / 2.2))).join('');
    caret2.style.opacity = t > 39.4 ? 0 : (Math.floor(t * 2.5) % 2 ? 1 : .15);
    const done = t >= 39.5;
    send.textContent = done ? '✓ Replied' : 'Send reply'; send.className = done ? 'btn done' : 'btn';
  }},
  (t) => document.querySelectorAll('[data-count]').forEach(el => {{
    const p = 1 - Math.pow(1 - clamp((t - el.dataset.from) / 1.6), 3), v = el.dataset.count * p, d = +(el.dataset.dec || 0);
    el.textContent = v.toLocaleString('en-US', {{minimumFractionDigits: d, maximumFractionDigits: d}}) + (el.dataset.suffix || '');
  }}),
];
const all = document.getAnimations();
all.forEach(a => a.pause());
window.seek = (t) => {{
  all.forEach(a => a.currentTime = t * 1000);
  scenes.forEach(s => s.style.visibility = (t >= +s.dataset.s && t < +s.dataset.e + .4) ? 'visible' : 'hidden');
  fx.forEach(f => f(t));
}};
seek(0);
</script></body></html>'''
open(SP + ('/promo-v.html' if V else '/promo.html'), 'w').write(html)
print('ok', len(html))
