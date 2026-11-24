"""Builds the second promo ("From trend to post"): promo2.html, or promo2-v.html with the argument v.

Same method as build.py (seek(t) puts every animation at time t), but a different look: colour blocks,
thick outlines, hard shadows and wipes. 96 BPM, so a bar is 2.5 s and every cut lands on a bar.
"""
import os, re, sys

V = len(sys.argv) > 1 and sys.argv[1] == 'v'
W, H = (1080, 1920) if V else (1920, 1080)
SP = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(SP))
F = 'file://' + ROOT + '/public/assets/fonts/'

DRAFT = 'Pumpkin spice season is officially here 🍂 Our pumpkin loaf is back this Saturday, baked fresh every morning. Who is coming first? #pumpkinspice #bakery'
N = len(DRAFT)


def glyph(slug):
    s = re.sub(r'<title>.*?</title>', '', open(f'{ROOT}/app/icons/brands/{slug}.svg').read())
    return s.replace('<svg ', '<svg fill="#fff" ', 1)


NET = {
    'instagram': ('radial-gradient(circle at 30% 107%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285AEB 90%)', glyph('instagram')),
    'linkedin': ('#0A66C2', '<b>in</b>'),
    'threads': ('#000', glyph('threads')),
    'youtube': ('#FF0000', glyph('youtube')),
    'google': ('#4285F4', glyph('google')),
    'reddit': ('#FF4500', glyph('reddit')),
    'hn': ('#FF6600', '<b>Y</b>'),
}


def net(slug, cls=''):
    bg, g = NET[slug]
    return f'<span class="net {cls}" style="background:{bg}">{g}</span>'


def words(text, start, step, kind='slam'):
    return ' '.join(f'<span class="w" data-in="{start + i * step:.2f} {kind} .5">{w}</span>' for i, w in enumerate(text.split(' ')))


def copy(head, sub, s):
    return f'<div class="copy"><h2>{words(head, s + .35, .1, "up")}</h2><p data-in="{s + 1.2:.2f} up .6">{sub}</p></div>'


def trend(src, label, metric, title, emoji, color, t, pick=False):
    return f'''<div class="trend{' pick' if pick else ''}" data-in="{t:.2f} right .6">
  <span class="thumb" style="background:{color}">{emoji}</span>
  <div><small>{net(src, 'xs')}{label}<i>{metric}</i></small><b>{title}</b></div>
  <span class="use"{' id="use"' if pick else ''}>Use{'<svg class="cursor" id="cur1" viewBox="0 0 22 22"><path d="M3 2l15 8-6.5 1.8L9 18z" fill="#213130" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>' if pick else ''}</span>
</div>'''


def preview(slug, name, limit, t, rot, img=False):
    pic = f'<img src="file://{ROOT}/public/assets/img/posts/bread-480.webp">' if img else ''
    return f'''<div class="nb pv" style="--r:{rot}deg" data-in="{t:.2f} drop .7">
  <header><i>S</i><span>Sunrise Bakery<small>{name}</small></span>{net(slug, 'sm')}</header>{pic}
  <p>{DRAFT}</p><footer><b>{N}</b> / {limit}<span>✓ fits</span></footer>
</div>'''


heat = [[1, 2, 2, 1], [2, 3, 2, 2], [1, 2, 3, 2], [2, 2, 2, 3], [2, 3, 3, 4], [5, 4, 3, 3], [3, 3, 2, 2]]  # sample: Mon..Sun x 9 AM, 12 PM, 3 PM, 6 PM
cells = ''
for r, hour in enumerate(['9 AM', '12 PM', '3 PM', '6 PM']):
    cells += f'<span class="hl">{hour}</span>'
    for d in range(7):
        v = heat[d][r]
        cells += f'<span class="hc h{v}{" best" if v == 5 else ""}" data-in="{41.0 + (d + r) * .06:.2f} pop .4"></span>'
days = '<span></span>' + ''.join(f'<span class="hd">{d}</span>' for d in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])

VCSS = """
h1 { font-size: 150px; }
.center { padding: 0 60px; }
.tracker { top: 250px; gap: 10px; } .tracker span { font-size: 24px; padding: 12px 18px; }
.copy { left: 70px; width: 940px; top: 370px; bottom: auto; display: block; }
.copy h2 { font-size: 104px; } .copy p { font-size: 42px; }
.stage { left: 0; right: 0; top: 860px; bottom: 300px; }
.panel, .composer, .sched { width: 960px; }
.pvs { gap: 20px; } .pv { width: 318px; padding: 14px; box-shadow: 8px 8px 0 var(--ink); } .pv p { font-size: 19px; }
.stage.wide { transform: scale(.94); }
.srcs { flex-direction: column; align-items: center; }
.logo { font-size: 124px; gap: 26px; } .logo img { width: 140px; height: 140px; }
.tag { font-size: 54px; }
.note { right: 0; left: 0; text-align: center; bottom: 330px; }
.strip { bottom: 420px; }
.three div { font-size: 190px; }
"""

html = f'''<!doctype html><html><head><meta charset="utf-8"><style>
@font-face {{ font-family: Figtree; src: url("{F}figtree-latin-400-normal.woff2"); font-weight: 400; }}
@font-face {{ font-family: Figtree; src: url("{F}figtree-latin-500-normal.woff2"); font-weight: 500; }}
@font-face {{ font-family: Figtree; src: url("{F}figtree-latin-600-normal.woff2"); font-weight: 600 800; }}
@font-face {{ font-family: Jakarta; src: url("{F}plus-jakarta-sans-latin-600-normal.woff2"); font-weight: 500 800; }}
* {{ box-sizing: border-box; margin: 0; padding: 0; }}
:root {{ --ink: #213130; --cream: #FEFDFB; --green: #B0EC9C; --yellow: #FFD88A; --pink: #F3BDFF; --blue: #ADDAFF; --lav: #D4C2FF; --coral: #FFB2A8; }}
html, body {{ width: {W}px; height: {H}px; overflow: hidden; background: var(--ink); }}
body {{ font-family: Figtree, sans-serif; color: var(--ink); -webkit-font-smoothing: antialiased; }}
section {{ position: absolute; inset: 0; overflow: hidden; visibility: hidden; }}
.dots {{ position: absolute; inset: -60px; background-image: radial-gradient(rgba(33,49,48,.16) 3px, transparent 3.5px); background-size: 44px 44px; }}
.inkbg .dots {{ background-image: radial-gradient(rgba(254,253,251,.13) 3px, transparent 3.5px); }}
.inkbg {{ background: var(--ink); color: var(--cream); }}
.center {{ position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }}
h1 {{ font: 600 200px/.98 Jakarta, sans-serif; letter-spacing: -.05em; text-transform: uppercase; }}
.w {{ display: inline-block; }}
.hl1 {{ display: inline-block; background: var(--ink); color: var(--yellow); padding: 0 .12em .04em; border-radius: .12em; transform: rotate(-2deg); }}
.out {{ -webkit-text-stroke: 5px var(--cream); color: transparent; }}
.nb {{ background: #fff; color: var(--ink); border: 5px solid var(--ink); border-radius: 26px; box-shadow: 14px 14px 0 var(--ink); }}
.inkbg .nb {{ box-shadow: 14px 14px 0 var(--green); }}
.strip {{ position: absolute; left: -10%; width: 120%; bottom: 110px; transform: rotate(-4deg); background: var(--ink); color: var(--yellow); font: 600 54px/1 Jakarta; letter-spacing: .02em; text-transform: uppercase; padding: 22px 0; white-space: nowrap; overflow: hidden; }}
.strip div {{ display: inline-block; }}
.blank {{ margin-top: 54px; width: 720px; max-width: 86%; padding: 30px 34px; text-align: left; font: 500 40px/1.3 Figtree; color: #8A877C; }}
.caret {{ display: inline-block; width: 5px; height: 44px; background: var(--ink); vertical-align: -8px; margin-right: 8px; }}
.net {{ display: inline-flex; align-items: center; justify-content: center; width: 96px; height: 96px; border-radius: 24px; flex: none; }}
.net svg {{ width: 58%; height: 58%; }}
.net b {{ font: 800 52px/1 Arial, sans-serif; color: #fff; letter-spacing: -.04em; }}
.net.sm {{ width: 46px; height: 46px; border-radius: 13px; }} .net.sm b {{ font-size: 25px; }}
.net.xs {{ width: 30px; height: 30px; border-radius: 9px; }} .net.xs b {{ font-size: 17px; }}
.srcs {{ display: flex; gap: 22px; margin-top: 60px; flex-wrap: wrap; justify-content: center; }}
.src {{ display: inline-flex; align-items: center; gap: 18px; padding: 16px 30px 16px 16px; border-radius: 99px; background: var(--cream); color: var(--ink); font: 600 40px/1 Figtree; border: 5px solid var(--cream); box-shadow: 8px 8px 0 var(--green); }}
.src .net {{ width: 62px; height: 62px; border-radius: 50%; }} .src .net b {{ font-size: 34px; }}
.tag {{ font: 500 60px/1.2 Figtree; margin-top: 40px; }}
.tracker {{ position: absolute; z-index: 50; left: 0; right: 0; top: 56px; display: flex; justify-content: center; gap: 14px; }}
.tracker span {{ display: inline-flex; align-items: center; gap: 12px; padding: 14px 24px; border-radius: 99px; border: 4px solid var(--ink); background: var(--cream); font: 600 28px/1 Figtree; text-transform: uppercase; letter-spacing: .04em; opacity: .55; }}
.tracker span.on {{ background: var(--ink); color: var(--green); opacity: 1; box-shadow: 6px 6px 0 rgba(33,49,48,.25); }}
.tracker span.did {{ opacity: 1; }}
.tracker i {{ font-style: normal; }}
.copy {{ position: absolute; left: 110px; top: 0; bottom: 0; width: 640px; display: flex; flex-direction: column; justify-content: center; }}
.copy h2 {{ font: 600 104px/1 Jakarta; letter-spacing: -.045em; margin-bottom: 30px; }}
.copy p {{ font: 500 40px/1.32 Figtree; }}
.stage {{ position: absolute; left: 800px; right: 70px; top: 60px; bottom: 0; display: flex; align-items: center; justify-content: center; }}
.panel {{ width: 1000px; padding: 30px 34px 34px; }}
.panel > header {{ display: flex; align-items: baseline; gap: 16px; font: 600 38px/1 Jakarta; letter-spacing: -.02em; }}
.panel > header small {{ font: 500 24px/1 Figtree; color: #64665F; }}
.chips {{ display: flex; gap: 12px; margin: 24px 0 22px; flex-wrap: wrap; }}
.chips span {{ padding: 11px 20px; border-radius: 99px; border: 3px solid var(--ink); font: 600 23px/1 Figtree; }}
.chips span:first-child {{ background: var(--ink); color: var(--cream); }}
.trend {{ display: flex; align-items: center; gap: 22px; padding: 18px; border: 3px solid #D6D2C9; border-radius: 20px; margin-top: 16px; }}
.trend.did {{ border-color: var(--ink); background: #E4F6DC; }}
.thumb {{ flex: none; width: 150px; height: 100px; border-radius: 14px; border: 3px solid var(--ink); font-size: 56px; line-height: 94px; text-align: center; }}
.trend div {{ flex: 1; min-width: 0; }}
.trend small {{ display: flex; align-items: center; gap: 10px; font: 600 22px/1 Figtree; color: #56605C; }}
.trend small i {{ font-style: normal; font-weight: 500; }}
.trend b {{ display: block; font: 600 31px/1.2 Figtree; margin-top: 12px; }}
.use {{ position: relative; flex: none; padding: 16px 30px; border-radius: 99px; border: 4px solid var(--ink); background: var(--green); font: 600 26px/1 Figtree; box-shadow: 5px 5px 0 var(--ink); }}
.use.did {{ background: var(--ink); color: var(--green); box-shadow: none; }}
.cursor {{ position: absolute; right: -6px; bottom: -34px; width: 56px; height: 56px; z-index: 9; }}
.composer {{ width: 1000px; padding: 32px 36px; }}
.composer > header {{ display: flex; align-items: center; justify-content: space-between; font: 600 36px/1 Jakarta; letter-spacing: -.02em; }}
.pill {{ display: inline-flex; align-items: center; gap: 10px; padding: 13px 22px; border-radius: 99px; border: 4px solid var(--ink); font: 600 25px/1 Figtree; background: #fff; }}
.pill.ai {{ background: var(--lav); }}
.from {{ display: inline-flex; align-items: center; gap: 12px; margin: 24px 0 18px; padding: 12px 20px; border-radius: 14px; background: var(--yellow); border: 3px solid var(--ink); font: 600 25px/1.2 Figtree; }}
.field {{ min-height: 270px; border: 4px solid var(--ink); border-radius: 18px; padding: 24px 26px; font: 500 35px/1.42 Figtree; background: var(--cream); }}
.composer > footer {{ display: flex; align-items: center; gap: 14px; margin-top: 22px; font: 600 24px/1 Figtree; color: #56605C; }}
.pvs {{ display: flex; gap: 26px; align-items: flex-start; }}
.pv {{ width: 318px; padding: 18px; border-radius: 22px; box-shadow: 10px 10px 0 var(--ink); rotate: var(--r); }}
.pv header {{ display: flex; align-items: center; gap: 10px; font: 600 20px/1.15 Figtree; }}
.pv header i {{ flex: none; width: 46px; height: 46px; border-radius: 50%; background: var(--yellow); border: 3px solid var(--ink); font: 600 21px/40px Jakarta; text-align: center; font-style: normal; }}
.pv header > span:not(.net) {{ flex: 1; }} .pv header small {{ display: block; font: 500 16px/1.2 Figtree; color: #64665F; }}
.pv img {{ display: block; width: 100%; height: 170px; object-fit: cover; border-radius: 12px; border: 3px solid var(--ink); margin-top: 14px; }}
.pv p {{ font: 500 20px/1.38 Figtree; margin-top: 14px; }}
.pv footer {{ display: flex; align-items: center; gap: 6px; margin-top: 14px; padding-top: 12px; border-top: 3px solid #E7E4DD; font: 500 19px/1 Figtree; color: #56605C; }}
.pv footer b {{ color: var(--ink); }}
.pv footer span {{ margin-left: auto; padding: 7px 12px; border-radius: 99px; background: var(--green); border: 3px solid var(--ink); font: 600 17px/1 Figtree; color: var(--ink); }}
.sched {{ width: 980px; padding: 30px 34px; }}
.sched > header {{ display: flex; align-items: center; gap: 14px; font: 600 34px/1 Jakarta; letter-spacing: -.02em; }}
.heat {{ display: grid; grid-template-columns: 84px repeat(7, 1fr); gap: 10px; margin-top: 24px; align-items: center; }}
.hd {{ font: 600 22px/1 Figtree; text-align: center; color: #56605C; }}
.hl {{ font: 600 21px/1 Figtree; color: #56605C; }}
.hc {{ height: 62px; border-radius: 12px; border: 3px solid var(--ink); }}
.h1 {{ background: #fff; }} .h2 {{ background: #E4F6DC; }} .h3 {{ background: #B0EC9C; }} .h4 {{ background: #60BE6E; }} .h5 {{ background: var(--ink); }}
.hc.best {{ position: relative; }}
.hc.best::after {{ content: "★"; position: absolute; inset: 0; color: var(--yellow); font: 600 34px/56px Figtree; text-align: center; }}
.sched > footer {{ display: flex; align-items: center; gap: 14px; margin-top: 26px; }}
.btn {{ position: relative; display: inline-flex; padding: 20px 34px; border-radius: 99px; border: 4px solid var(--ink); background: var(--green); font: 600 28px/1 Figtree; box-shadow: 6px 6px 0 var(--ink); }}
.btn.did {{ background: var(--ink); color: var(--green); box-shadow: none; }}
.note {{ position: absolute; right: 80px; bottom: 40px; font: 500 20px/1 Figtree; opacity: .6; }}
.three {{ display: flex; flex-direction: column; align-items: center; gap: 6px; }}
.three div {{ font: 600 230px/.95 Jakarta; letter-spacing: -.05em; text-transform: uppercase; }}
.logo {{ display: flex; align-items: center; gap: 34px; font: 600 160px/1 Jakarta; letter-spacing: -.05em; }}
.logo img {{ width: 180px; height: 180px; padding: 22px; background: #fff; border: 5px solid var(--ink); border-radius: 28%; box-shadow: 10px 10px 0 var(--ink); }}
.cta {{ margin-top: 54px; padding: 34px 70px; border-radius: 99px; background: var(--ink); color: var(--green); font: 600 60px/1 Figtree; box-shadow: 12px 12px 0 rgba(33,49,48,.28); }}
.url {{ margin-top: 44px; font: 600 48px/1 Figtree; }}
.small {{ margin-top: 26px; font: 500 34px/1.3 Figtree; opacity: .8; }}
#fade {{ position: absolute; inset: 0; background: var(--ink); opacity: 0; z-index: 99; }}
{VCSS if V else ''}
</style></head><body>

<section style="background:var(--yellow)" data-s="0" data-e="5" data-wipe="up">
  <div class="dots" data-drift></div>
  <div class="center">
    <h1>{words('What do I', .15, .22)}<br><span class="hl1">{words('post today?', .95, .3)}</span></h1>
    <div class="nb blank" data-in="2.0 up .6"><span class="caret" id="c0"></span>Start writing…</div>
  </div>
  <div class="strip"><div data-marquee="-900">No ideas ✦ Blank page ✦ Posting nothing ✦ No ideas ✦ Blank page ✦ Posting nothing ✦ No ideas ✦ Blank page ✦ Posting nothing ✦ No ideas ✦ Blank page ✦</div></div>
</section>

<section class="inkbg" data-s="5" data-e="10" data-wipe="left">
  <div class="dots" data-drift></div>
  <div class="center">
    <h1 style="font-size:{118 if V else 150}px">{words('Start from', 5.25, .2)}<br><span style="color:var(--green)">{words("what's trending.", 5.9, .28)}</span></h1>
    <div class="srcs">
      <span class="src" data-in="7.1 pop .45">{net('youtube')}YouTube</span><span class="src" data-in="7.4 pop .45">{net('google')}Google Search</span>
      <span class="src" data-in="7.7 pop .45">{net('reddit')}Reddit</span><span class="src" data-in="8.0 pop .45">{net('hn')}Hacker News</span>
    </div>
  </div>
</section>

<section style="background:var(--cream)" data-s="10" data-e="20" data-wipe="right">
  <div class="dots"></div>
  {copy('Pick a trend.', 'What people are watching, reading and searching this week.', 10)}
  <div class="stage"><div class="nb panel" data-in="10.5 rise .8">
    <header>Trending<small>updated every week</small></header>
    <div class="chips"><span>All</span><span>YouTube</span><span>Hacker News</span><span>Reddit</span><span>Google Search</span></div>
    {trend('youtube', 'YouTube', '1.2M views', '5 breakfast ideas you can make in 10 minutes', '🥞', 'var(--coral)', 11.2)}
    {trend('google', 'Google Search', '200K+ searches', 'pumpkin spice is back', '🎃', 'var(--yellow)', 11.5, True)}
    {trend('reddit', 'Reddit', '4.1K upvotes', 'What is the best bread you have ever had?', '🍞', 'var(--blue)', 11.8)}
  </div></div>
  <span class="note">Sample trends.</span>
</section>

<section style="background:var(--lav)" data-s="20" data-e="30" data-wipe="up">
  <div class="dots"></div>
  {copy('AI writes the first draft.', 'You stay the editor. Nothing goes out without you.', 20)}
  <div class="stage"><div class="nb composer" data-in="20.5 rise .8">
    <header><span>Create Post</span><span class="pill ai" id="aipill">✨ AI Assistant</span></header>
    <span class="from" data-in="21.3 pop .5">🎃 Trending on Google: “pumpkin spice is back”</span>
    <div class="field"><span id="typed"></span><span class="caret" id="c1" style="margin:0 0 0 3px"></span></div>
    <footer><span class="pill">📷 Add image</span><span style="flex:1"></span><span id="count">0</span>&nbsp;characters</footer>
  </div></div>
</section>

<section style="background:var(--blue)" data-s="30" data-e="40" data-wipe="left">
  <div class="dots"></div>
  {copy('One post. Every channel.', 'Preview each network before it goes out.', 30)}
  <div class="stage wide"><div class="pvs">
    {preview('instagram', 'Instagram', '2,200', 30.7, -4, True)}
    {preview('linkedin', 'LinkedIn', '3,000', 31.3, 2)}
    {preview('threads', 'Threads', '500', 31.9, 5)}
  </div></div>
</section>

<section style="background:var(--pink)" data-s="40" data-e="47.5" data-wipe="right">
  <div class="dots"></div>
  {copy('Posted at your best time.', 'Insights shows when your posts do best.', 40)}
  <div class="stage"><div class="nb sched" data-in="40.5 rise .8">
    <header>{net('instagram', 'sm')}Best times<small style="font:500 24px/1 Figtree;color:#64665F;margin-left:auto">Sunrise Bakery</small></header>
    <div class="heat">{days}{cells}</div>
    <footer><span class="pill">📅 Sat, Nov 28</span><span class="pill">🕘 9:00 AM</span><span style="flex:1"></span>
      <span class="btn" id="sched">Schedule Post<svg class="cursor" id="cur2" viewBox="0 0 22 22"><path d="M3 2l15 8-6.5 1.8L9 18z" fill="#213130" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg></span></footer>
  </div></div>
  <span class="note">Sample data.</span>
</section>

<section class="inkbg" data-s="47.5" data-e="52.5" data-wipe="up">
  <div class="dots" data-drift></div>
  <div class="center"><div class="three">
    <div style="color:var(--yellow)" data-in="47.6 slam .45">Trend.</div>
    <div style="color:var(--lav)" data-in="48.85 slam .45">Draft.</div>
    <div style="color:var(--green)" data-in="50.1 slam .45">Done.</div>
  </div></div>
</section>

<section style="background:var(--green)" data-s="52.5" data-e="60.5" data-wipe="left">
  <div class="dots" data-drift></div>
  <div class="center">
    <div class="logo"><img src="file://{ROOT}/public/assets/img/logo-mark-512.png" data-in="52.7 pop .6"><span data-in="53.0 left .6">atwebtech</span></div>
    <p class="tag">{words('Never run out of things to post.', 53.8, .1, 'up')}</p>
    <div class="cta" id="cta" data-in="55.0 pop .6">Start free →</div>
    <div class="url" data-in="55.6 up .5">atwebtech.us</div>
    <p class="small" data-in="56.1 up .5">Free for 3 channels · No credit card</p>
  </div>
</section>

<div class="tracker" id="tracker"><span><i>1</i>Trend</span><span><i>2</i>Write</span><span><i>3</i>Preview</span><span><i>4</i>Schedule</span></div>
<div id="fade"></div>

<script>
const OUT = 'cubic-bezier(.16,1,.3,1)', BACK = 'cubic-bezier(.34,1.7,.64,1)';
const K = {{
  up: [{{opacity: 0, transform: 'translateY(70px)'}}, {{opacity: 1, transform: 'translateY(0)'}}],
  left: [{{opacity: 0, transform: 'translateX(-90px)'}}, {{opacity: 1, transform: 'translateX(0)'}}],
  right: [{{opacity: 0, transform: 'translateX(120px)'}}, {{opacity: 1, transform: 'translateX(0)'}}],
  pop: [{{opacity: 0, scale: '.3'}}, {{opacity: 1, scale: '1'}}],
  slam: [{{opacity: 0, scale: '2.4'}}, {{opacity: 1, scale: '1'}}],
  rise: [{{opacity: 0, transform: 'translateY(220px) rotate(4deg)'}}, {{opacity: 1, transform: 'translateY(0) rotate(-1.2deg)'}}],
  drop: [{{opacity: 0, transform: 'translateY(-260px) scale(.9)'}}, {{opacity: 1, transform: 'translateY(0) scale(1)'}}],
}};
document.querySelectorAll('[data-in]').forEach(el => {{
  const [t, k, d] = el.dataset.in.split(' ');
  el.animate(K[k], {{delay: t * 1000, duration: (d || .6) * 1000, fill: 'both', easing: (k === 'pop' || k === 'slam' || k === 'drop') ? BACK : OUT}});
}});
const WIPE = {{up: 'inset(100% 0 0 0)', left: 'inset(0 0 0 100%)', right: 'inset(0 100% 0 0)'}};
const scenes = [...document.querySelectorAll('section')];
scenes.forEach((s, i) => {{
  s.style.zIndex = i + 1;
  if (i) s.animate([{{clipPath: WIPE[s.dataset.wipe]}}, {{clipPath: 'inset(0 0 0 0)'}}], {{delay: s.dataset.s * 1000, duration: 520, fill: 'both', easing: 'cubic-bezier(.7,0,.2,1)'}});
  [...s.children].filter(c => c.matches('.center,.copy,.stage')).forEach(c => c.animate([{{scale: '1'}}, {{scale: '1.035'}}], {{delay: s.dataset.s * 1000, duration: (s.dataset.e - s.dataset.s) * 1000, fill: 'both'}}));
}});
document.querySelectorAll('[data-drift]').forEach(el => el.animate([{{transform: 'translate(0,0)'}}, {{transform: 'translate(-44px,-44px)'}}], {{duration: 5000, iterations: Infinity}}));
document.querySelectorAll('[data-marquee]').forEach(el => el.animate([{{transform: 'translateX(0)'}}, {{transform: `translateX(${{el.dataset.marquee}}px)`}}], {{duration: 5600, fill: 'both'}}));
const click = (id, at) => {{
  const c = document.getElementById(id);
  c.animate([{{translate: '150px 190px', opacity: 0}}, {{translate: '90px 110px', opacity: 1, offset: .25}}, {{translate: '0 0', opacity: 1}}], {{delay: (at - 1.1) * 1000, duration: 1000, fill: 'both', easing: OUT}});
  c.animate([{{scale: '1'}}, {{scale: '.75'}}, {{scale: '1'}}], {{delay: (at - .08) * 1000, duration: 260, fill: 'both'}});
  c.animate([{{opacity: 1}}, {{opacity: 0}}], {{delay: (at + .9) * 1000, duration: 300, fill: 'forwards'}});
}};
click('cur1', 16.25); click('cur2', 45.0);
document.getElementById('aipill').animate([{{scale: '1', boxShadow: '0 0 0 0 rgba(33,49,48,0)'}}, {{scale: '1.14', boxShadow: '0 0 0 16px rgba(33,49,48,.18)'}}, {{scale: '1', boxShadow: '0 0 0 0 rgba(33,49,48,0)'}}], {{delay: 22300, duration: 650, fill: 'both'}});
document.querySelectorAll('.hc.best').forEach(el => el.animate([{{scale: '1'}}, {{scale: '1.22'}}, {{scale: '1'}}], {{delay: 42500, duration: 625, iterations: 3, fill: 'both', easing: 'ease-in-out', composite: 'add'}}));
document.getElementById('cta').animate([{{scale: '1'}}, {{scale: '1.07'}}, {{scale: '1'}}], {{delay: 56250, duration: 1250, iterations: 2, fill: 'both', easing: 'ease-in-out', composite: 'add'}});
document.getElementById('tracker').animate([{{opacity: 0, translate: '0 -60px'}}, {{opacity: 1, translate: '0 0'}}], {{delay: 10300, duration: 500, fill: 'both', easing: OUT}});
document.getElementById('tracker').animate([{{opacity: 1}}, {{opacity: 0}}], {{delay: 47300, duration: 200, fill: 'forwards'}});
document.getElementById('fade').animate([{{opacity: 0}}, {{opacity: 1}}], {{delay: 59400, duration: 600, fill: 'both'}});

const D = [...{DRAFT!r}];
const clamp = (x) => Math.max(0, Math.min(1, x));
const blink = (t) => Math.floor(t * 3.2) % 2 ? 1 : .1;
const pills = [...document.querySelectorAll('#tracker span')], trend = document.querySelector('.trend.pick');
const fx = [
  (t) => {{ c0.style.opacity = blink(t); }},
  (t) => {{
    const did = t >= 16.25; trend.classList.toggle('did', did); use.classList.toggle('did', did);
    use.firstChild.textContent = did ? '✓ Added' : 'Use';
  }},
  (t) => {{
    const n = Math.round(D.length * clamp((t - 22.9) / 3.4));
    typed.textContent = D.slice(0, n).join(''); count.textContent = n;
    c1.style.opacity = t > 27 ? 0 : blink(t);
  }},
  (t) => {{ const did = t >= 45.0; sched.classList.toggle('did', did); sched.firstChild.textContent = did ? '✓ Scheduled' : 'Schedule Post'; }},
  (t) => {{ const step = t < 20 ? 0 : t < 30 ? 1 : t < 40 ? 2 : 3; pills.forEach((p, i) => {{ p.classList.toggle('on', i === step); p.classList.toggle('did', i < step); p.firstElementChild.textContent = i < step ? '✓' : i + 1; }}); }},
];
const all = document.getAnimations();
all.forEach(a => a.pause());
window.seek = (t) => {{
  all.forEach(a => a.currentTime = t * 1000);
  scenes.forEach(s => s.style.visibility = (t >= +s.dataset.s && t < +s.dataset.e + .6) ? 'visible' : 'hidden');
  fx.forEach(f => f(t));
}};
seek(0);
</script></body></html>'''
open(SP + ('/promo2-v.html' if V else '/promo2.html'), 'w').write(html)
print('ok', N, 'characters in the draft')
