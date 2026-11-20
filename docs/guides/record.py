"""Records the Guide videos by really using the panel, and writes the step-by-step guides from the same run.

Each guide below is a list of steps. A step has the words for the written guide and the actions to perform.
While a guide runs, a browser is recorded doing those actions (with a visible pointer and a caption), and a
screenshot is taken after each step. The results:

    public/assets/guides/<slug>/video.mp4, poster.jpg, step-01.jpg ...
    public/assets/guides/<slug>.pdf  and  public/assets/guides/atwebtech-user-guide.pdf
    app/data/guides.php   (what the Guide pages in the panel read)

Run it against a LOCAL server with a seeded demo account, never the live site:

    python docs/guides/record.py            all guides
    python docs/guides/record.py first-post one guide (by its slug)
    python docs/guides/record.py pdf        only rebuild the data file and the PDFs

Needs: pip install playwright pillow, Google Chrome, ffmpeg.
"""
import datetime, html, json, os, re, shutil, subprocess, sys, tempfile

from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
OUT = os.path.join(ROOT, 'public', 'assets', 'guides')
BASE = os.environ.get('GUIDE_BASE', 'http://localhost:8000')
DEMO = (os.environ.get('GUIDE_EMAIL', 'demo@example.test'), os.environ.get('GUIDE_PASSWORD', 'DemoPass#123'))
NEW = ('sara@example.test', 'GuidePass#123')
W, H = 1280, 720

# The pointer, the highlight ring and the caption drawn over the page while recording.
OVERLAY = r"""
(() => {
  if (window.__guide) return;
  const make = (css) => { const e = document.createElement('div'); e.style.cssText = css; return e; };
  const cursor = make('position:fixed;left:0;top:0;width:22px;height:22px;margin:-3px 0 0 -3px;z-index:2147483647;pointer-events:none;transition:transform .08s;' +
    'background:no-repeat url("data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22"><path d="M3 2l15 8-6.5 1.8L9 18z" fill="#14301C" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>') + '")');
  const ring = make('position:fixed;z-index:2147483646;pointer-events:none;border:3px solid #60BE6E;border-radius:12px;box-shadow:0 0 0 6px rgba(96,190,110,.28);opacity:0;transition:all .25s ease');
  const cap = make('position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:2147483645;pointer-events:none;max-width:760px;padding:11px 20px;border-radius:14px;' +
    'background:rgba(20,48,28,.94);color:#F2F7F1;font:600 19px/1.35 Figtree,system-ui,sans-serif;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.28);opacity:0;transition:opacity .2s');
  const card = make('position:fixed;inset:0;z-index:2147483644;display:none;align-items:center;justify-content:center;flex-direction:column;gap:14px;background:radial-gradient(circle at 50% 45%,#1F4A2D,#14301C 55%,#0C1E12);color:#F2F7F1;font-family:"Plus Jakarta Sans",Figtree,system-ui,sans-serif;text-align:center;padding:60px');
  // Everything sits in a popover, so it also shows above an open dialog (re-shown on every call to stay on top).
  const layer = make('position:fixed;inset:0;width:100vw;height:100vh;max-width:none;max-height:none;margin:0;padding:0;border:0;background:transparent;overflow:visible;pointer-events:none');
  layer.setAttribute('popover', 'manual');
  layer.append(card, cap, ring, cursor);
  const top = () => { try { if (layer.matches(':popover-open')) layer.hidePopover(); layer.showPopover(); } catch (e) {} };
  const add = () => { document.documentElement.appendChild(layer); top(); };
  if (document.documentElement) add(); else document.addEventListener('DOMContentLoaded', add);
  document.addEventListener('mousemove', (e) => { cursor.style.left = e.clientX + 'px'; cursor.style.top = e.clientY + 'px'; }, true);
  document.addEventListener('mousedown', () => { cursor.style.transform = 'scale(.8)'; }, true);
  document.addEventListener('mouseup', () => { cursor.style.transform = 'scale(1)'; }, true);
  const g = window.__guide = {
    caption(text, up) { top(); cap.textContent = text; cap.style.opacity = text ? 1 : 0; cap.style.bottom = up ? 'auto' : '22px'; cap.style.top = up ? '18px' : 'auto'; try { sessionStorage.setItem('__cap', JSON.stringify([text, !!up])); } catch (e) {} },
    ring(x, y, w, h) { top(); if (w) { ring.style.left = (x - 6) + 'px'; ring.style.top = (y - 6) + 'px'; ring.style.width = (w + 6) + 'px'; ring.style.height = (h + 6) + 'px'; ring.style.opacity = 1; } else ring.style.opacity = 0; },
    clean(on) { cap.style.visibility = on ? 'hidden' : 'visible'; },
    card(title, sub) { if (!title) { card.style.display = 'none'; return; } card.innerHTML = ''; const t = document.createElement('div'); t.textContent = title; t.style.cssText = 'font-size:52px;font-weight:600;letter-spacing:-1px;line-height:1.1;max-width:900px'; const s = document.createElement('div'); s.textContent = sub || ''; s.style.cssText = 'font:500 22px Figtree,system-ui,sans-serif;color:#B0EC9C'; card.append(t, s); card.style.display = 'flex'; },
    at(x, y) { top(); cursor.style.left = x + 'px'; cursor.style.top = y + 'px'; },
  };
  try { const saved = JSON.parse(sessionStorage.getItem('__cap') || 'null'); if (saved) g.caption(saved[0], saved[1]); } catch (e) {}
})();
"""


class Recorder:
    def __init__(self, browser, guide, state):
        self.guide, self.steps, self.mouse = guide, [], (W // 2, H // 2)
        self.dir = os.path.join(OUT, guide['slug'])
        shutil.rmtree(self.dir, ignore_errors=True)
        os.makedirs(self.dir)
        self.tmp = tempfile.mkdtemp()
        self.ctx = browser.new_context(viewport={'width': W, 'height': H}, record_video_dir=self.tmp, record_video_size={'width': W, 'height': H},
                                       storage_state=state, bypass_csp=True, color_scheme='light')
        self.ctx.add_init_script(OVERLAY)
        self.page = self.ctx.new_page()
        self.page.set_default_timeout(15000)

    # ---- What a step can do ----------------------------------------------------------------------
    def js(self, call):
        try:
            self.page.evaluate('window.__guide && window.__guide.' + call)
        except Exception:
            pass

    def wait(self, seconds):
        self.page.wait_for_timeout(int(seconds * 1000))

    def goto(self, path):
        self.page.goto(BASE + path)
        self.page.wait_for_load_state('networkidle')
        self.js('at(%d, %d)' % self.mouse)

    def loc(self, target):
        return self.page.locator(target).first if isinstance(target, str) else target.first

    def point(self, target, hold=0.7):
        """Moves the pointer to something and rings it."""
        el = self.loc(target)
        el.scroll_into_view_if_needed()
        self.wait(0.25)
        box = el.bounding_box()
        if not box:
            return el
        x, y = box['x'] + min(box['width'] / 2, 180), box['y'] + box['height'] / 2
        self.page.mouse.move(x, y, steps=22)
        self.mouse = (int(x), int(y))
        self.js('ring(%f, %f, %f, %f)' % (box['x'], box['y'], box['width'], box['height']))
        self.wait(hold)
        return el

    def click(self, target, hold=0.6, after=0.9):
        el = self.point(target, hold)
        self.js('ring(0)')
        el.click()
        try:
            self.page.wait_for_load_state('networkidle', timeout=8000)
        except Exception:
            pass
        self.js('at(%d, %d)' % self.mouse)
        self.wait(after)

    def type(self, target, text, delay=38):
        el = self.point(target, 0.4)
        el.click()
        self.js('ring(0)')
        el.press_sequentially(text, delay=delay)
        self.wait(0.5)

    def set(self, target, value):
        """Date, time and select fields: set directly (their pickers are the browser's own windows)."""
        el = self.point(target, 0.5)
        if el.evaluate('e => e.tagName') == 'SELECT':
            el.select_option(label=value)
        else:
            el.fill(value)
        self.js('ring(0)')
        self.wait(0.6)

    def scroll(self, pixels):
        self.page.mouse.wheel(0, pixels)
        self.wait(0.8)

    # ---- Steps -----------------------------------------------------------------------------------
    def step(self, title, text, action=None, caption_top=False, read=1.6):
        self.js('caption(%s, %s)' % (json.dumps(title), 'true' if caption_top else 'false'))
        self.wait(0.9)
        if action:
            action(self)
        self.js('caption(%s, %s)' % (json.dumps(title), 'true' if caption_top else 'false'))  # a new page starts without it
        self.wait(read)
        name = 'step-%02d.jpg' % (len(self.steps) + 1)
        self.js('clean(true)')
        self.page.screenshot(path=os.path.join(self.dir, name), type='jpeg', quality=84)
        self.js('clean(false)')
        self.steps.append({'title': title, 'text': text, 'image': name})
        self.js('ring(0)')

    def title_card(self, title, sub, seconds=2.2):
        self.js('caption("")')
        self.js('card(%s, %s)' % (json.dumps(title), json.dumps(sub)))
        self.wait(seconds)
        self.js('card("")')

    def finish(self):
        self.title_card('That\'s it.', 'More guides: atwebtech.us, then Guide in the menu', 2.4)
        video = self.page.video
        self.ctx.close()
        src = video.path()
        out = os.path.join(self.dir, 'video.mp4')
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', src, '-vf', 'fps=24,format=yuv420p', '-c:v', 'libx264', '-preset', 'slow', '-crf', '27',
                        '-movflags', '+faststart', '-an', out], check=True)
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-ss', '1', '-i', out, '-frames:v', '1', '-q:v', '3', os.path.join(self.dir, 'poster.jpg')], check=True)
        seconds = float(subprocess.run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', out], capture_output=True, text=True).stdout.strip() or 0)
        shutil.rmtree(self.tmp, ignore_errors=True)
        return {'seconds': round(seconds), 'size': os.path.getsize(out)}


# ---- The guides ----------------------------------------------------------------------------------------

def g_start(r):
    r.goto('/signup')
    r.title_card('Create your account', 'Guide 1 · Getting started')
    r.step('Open atwebtech.us and choose Sign up', 'Go to atwebtech.us and click **Start free** or **Sign up**. You only need an email address and a password. No credit card is asked for.',
           lambda r: (r.type('#signup-email', NEW[0]), r.type('#signup-password', NEW[1])))
    r.step('Press Sign Up', 'Use a password with at least 8 characters, one uppercase letter and one number or symbol. Then press **Sign Up**.',
           lambda r: (r.click(r.page.get_by_role('button', name='Sign Up')), r.page.wait_for_url('**/welcome**')))
    r.step('Answer four quick questions, or skip them', 'atwebtech asks what kind of work you do and which networks you care about, so it can suggest the right setup. Pick an answer, or press **Skip**.',
           lambda r: (r.click(r.page.get_by_role('link', name="Let's start")), r.click('label:has(input[value="small-business"])', after=0.4)))
    r.step('Press Continue after each answer', 'There are four questions in total. After the last one you land in your new workspace.',
           lambda r: (r.click(r.page.get_by_role('button', name='Continue')),
                      r.click('label:has(input[value="none"])', after=0.4), r.click(r.page.get_by_role('button', name='Continue')),
                      r.click('label:has(input[value="1-3"])', after=0.4), r.click(r.page.get_by_role('button', name='Continue')),
                      r.click('label:has(input[value="instagram"])', after=0.3), r.click('label:has(input[value="facebook"])', after=0.4),
                      r.click(r.page.get_by_role('button', name='Continue')), r.page.wait_for_url('**/app**')))
    r.step('This is Home', 'Home shows what needs you today, your numbers for the week, and the first steps for a new account. A one-question survey may appear the first time: answer it or close it.',
           lambda r: (r.click(r.page.get_by_role('button', name='Close survey'), after=0.8), r.wait(0.8)))
    r.step('The menu on the left takes you everywhere', '**Publish** is your queue of posts. **Community** is your comment inbox. **Insights** shows your results. **Guide** opens these instructions at any time.',
           lambda r: (r.point(r.page.get_by_role('link', name=re.compile(r'^Publish'))), r.point(r.page.get_by_role('link', name='Community', exact=True)),
                      r.point(r.page.get_by_role('link', name='Insights', exact=True)), r.point(r.page.locator('a[href="/app/guide"]').first)))
    r.step('Next: connect your first channel', 'Nothing can be published until a social account is connected. Click **Connect Channel** to do that now. The next guide shows how.',
           lambda r: r.point(r.page.get_by_role('link', name='Connect Channel')))


def g_channels(r):
    r.goto('/app')
    r.title_card('Connect your social accounts', 'Guide 2 · Channels')
    r.step('Open Channels', 'In the menu on the left, find **Channels** at the bottom and click the **+** button, or click **Connect a channel** on Home.',
           lambda r: r.click(r.page.locator('a[href="/app/channels"]').first))
    r.step('Your connected accounts are listed at the top', 'Each connected account shows its network and how many posts a week you plan for it. **Open** changes its posting times. **Disconnect** removes it.',
           lambda r: r.point(r.page.get_by_role('link', name='Open').first))
    r.step('Pick the network you want to add', 'Scroll to **Connect a New Channel** and click a network: Instagram, Facebook Page, LinkedIn, YouTube, Pinterest, Threads, Bluesky or Mastodon.',
           lambda r: (r.scroll(420), r.point(r.page.get_by_role('button', name=re.compile('Facebook'))), r.point(r.page.get_by_role('button', name=re.compile('Pinterest')))))
    r.step('Sign in on the network\'s own page', 'atwebtech sends you to that network to sign in and allow access. You never type your social password into atwebtech. Mastodon first asks which server you use.',
           lambda r: (r.click(r.page.get_by_role('button', name=re.compile('Mastodon'))), r.type('input[name=instance]', 'mastodon.social')))
    r.step('Come back and the channel is ready', 'After you allow access, the network returns you to atwebtech and the account appears in your list. Repeat for each account you want to post to.',
           lambda r: (r.click(r.page.get_by_role('link', name='Back to channels')), r.wait(0.6)))
    r.step('Set when each channel posts', 'Click **Open** on a channel to choose its posting days and times. New posts added to the queue take the next free time.',
           lambda r: (r.click(r.page.get_by_role('link', name='Open').first), r.wait(0.8)))


def g_first_post(r):
    r.goto('/app/publish')
    r.title_card('Write and schedule your first post', 'Guide 3 · Publish')
    r.step('Click New Post', 'Open **Publish** in the menu and click **New Post** in the top right corner. The green **New** button in the menu does the same from any page.',
           lambda r: r.click(r.page.get_by_role('link', name='New Post')))
    r.step('Choose where it goes', 'Tick every channel this post should go to. One post can go to several networks at once.',
           lambda r: [r.click('label.cp-target:visible:has(input[data-provider="%s"])' % p, hold=0.4, after=0.4) for p in ('linkedin',)])
    r.step('Write your post', 'Type your text. The counter shows how many characters are left for the channels you picked. **AI Assistant** can write a first draft from a short brief, and **Templates** gives you ideas.',
           lambda r: r.type('textarea[name=body]:visible', 'Fresh sourdough comes out of the oven every morning at 7. Which loaf is your favourite?', delay=24))
    r.step('Add a photo or video', 'Click **Add image or video** and pick a file from your computer. Instagram always needs a photo.',
           lambda r: (r.point(r.page.get_by_role('button', name='Add image or video')), r.page.locator('input[data-cp-file]').first.set_input_files(os.path.join(ROOT, 'docs', 'guides', 'sample-post.jpg')), r.wait(2.5)),
           caption_top=True)
    when = datetime.date.today() + datetime.timedelta(days=3)
    r.step('Pick the day and time', 'At the bottom, set the date and the time the post should go out. Times are in your own time zone.',
           lambda r: (r.set('input[name=date]:visible', when.isoformat()), r.set('input[name=time]:visible', '09:30')), caption_top=True)
    r.step('Press Schedule Post', '**Schedule Post** puts it in the queue. **Save Draft** keeps it for later without a time. **Send for approval** asks a teammate to check it first.',
           lambda r: r.click(r.page.get_by_role('button', name='Schedule Post'), after=1.4), caption_top=True)
    r.step('It is now in your queue', 'The post waits in **Queue** and atwebtech publishes it at the time you chose. You can close the page: it goes out without you.', lambda r: r.wait(1.0))


def g_publish(r):
    r.goto('/app/publish')
    r.title_card('Manage your queue', 'Guide 4 · Publish')
    r.step('Queue: everything that is scheduled', 'Posts are listed in the order they will go out. Click a post to change its text, photo or time.', lambda r: r.point(r.page.locator('main a[href*="/app/publish/new?id="]').first))
    r.step('Calendar: see your week', 'Switch to **Calendar** for a week view. Click an empty day to write a post for that day.',
           lambda r: (r.click(r.page.get_by_role('link', name='Calendar')), r.wait(0.8)))
    r.step('Drafts: posts you have not finished', 'Back in **List**, open **Drafts**. A draft has no time yet. Open one, finish it and schedule it.',
           lambda r: (r.click(r.page.get_by_role('link', name='List')), r.click(r.page.get_by_role('link', name=re.compile(r'^Drafts')))))
    r.step('Failed: fix it and retry', 'If a network refuses a post, it lands in **Failed** with the reason in plain words. Fix what it asks for, then press **Retry**.',
           lambda r: (r.click(r.page.get_by_role('link', name=re.compile(r'^Failed'))), r.click(r.page.get_by_role('button', name='Retry', exact=True).first, after=1.2)))
    r.step('Approvals: check a teammate\'s post', 'Posts sent for approval wait in **Approvals**. **Approve** schedules the post. **Request changes** sends it back to Drafts with your note.',
           lambda r: (r.click(r.page.get_by_role('link', name=re.compile(r'^Approvals'))), r.click(r.page.get_by_role('button', name='Approve', exact=True).first, after=1.2)))
    r.step('Sent: what has been published', '**Sent** keeps every published post with its numbers. **Duplicate** copies a good post into a new draft.',
           lambda r: r.click(r.page.get_by_role('link', name=re.compile(r'^Sent'))))


def g_community(r):
    r.goto('/app')
    r.title_card('Reply to comments from one inbox', 'Guide 5 · Community')
    r.step('Open Community', 'Click **Community** in the menu. Comments from your connected accounts arrive here every 30 minutes. **Refresh** checks right now.',
           lambda r: r.click(r.page.locator('a[href="/app/community"]')))
    r.step('Unanswered comments come first', 'Each comment shows who wrote it, on which network and under which post. The tabs sort them into **Unanswered**, **Answered** and **Done**.',
           lambda r: r.point(r.page.locator('textarea[name=reply]').first))
    r.step('Write a reply, or let AI suggest one', 'Type your answer in the box. **Suggest a reply** writes a draft for you to edit.',
           lambda r: r.click(r.page.get_by_role('button', name='Suggest a reply').first, after=2.2))
    r.step('Use a saved reply for common questions', 'Pick a saved reply from the list to fill the box in one click. Add your own under **Saved replies** at the top.',
           lambda r: r.set(r.page.locator('select[data-cm-saved]').first, 'Pricing'))
    r.step('Press Send reply', 'The reply is posted on the network under your name, and the comment moves to **Answered**.',
           lambda r: r.click(r.page.get_by_role('button', name='Send reply').first, after=1.6))
    r.step('Mark done when no answer is needed', '**Mark done** clears a comment from Unanswered without replying, for example a simple thank-you.',
           lambda r: r.click(r.page.get_by_role('button', name='Mark done').first, after=1.4))


def g_insights(r):
    r.goto('/app')
    r.title_card('See what is working', 'Guide 6 · Insights')
    r.step('Open Insights', 'Click **Insights** in the menu to see how your posts performed.', lambda r: r.click(r.page.get_by_role('link', name='Insights', exact=True)))
    r.step('Choose the period', 'Switch between **7 Days**, **30 Days**, **Month to Date** and **Last Month**. Each number shows how it changed against the period before.',
           lambda r: (r.click(r.page.get_by_role('link', name='7 Days')), r.click(r.page.get_by_role('link', name='30 Days'))))
    r.step('Read the totals', 'Posts, followers, reactions, comments, shares and views for all your channels together. The small **i** next to a number explains what it counts.',
           lambda r: r.point(r.page.get_by_role('button', name='About Followers')))
    r.step('Find your best times and hashtags', 'Scroll down for the hours when your posts did best and how each hashtag performed. **Use these times** applies them to a channel\'s schedule.',
           lambda r: (r.scroll(520), r.scroll(520)))
    r.step('Learn from your top posts', 'The posts with the most engagement are listed at the bottom. **View** opens the post on its network.', lambda r: r.scroll(700))
    r.step('Export a report', 'Back at the top, **Export** downloads the numbers as a file you can open in Excel or Google Sheets.',
           lambda r: (r.page.mouse.wheel(0, -4000), r.wait(0.8), r.point(r.page.get_by_role('button', name='Export'))))


def g_automations(r):
    r.goto('/app')
    r.title_card('Let atwebtech do the weekly work', 'Guide 7 · Automations')
    r.step('Open Automations', 'Click **Automations** in the menu. There are three kinds: blog to social, a weekly AI plan, and automatic replies.',
           lambda r: r.click(r.page.get_by_role('link', name='Automations', exact=True)))
    r.step('Weekly plan: describe your business', 'Open **Set up** on the weekly plan. Tell the AI Assistant what your business is and what customers ask about.',
           lambda r: (r.click(r.page.locator('summary', has_text='Set up').nth(1), after=0.6), r.type('input[name=topic]', 'A family bakery in Austin'),
                      r.type('textarea[name=details]', 'Sourdough, pastries, open 7 to 3, catering on request', delay=22)))
    r.step('Choose channels and how many posts', 'Tick the channels to write for, how many posts a week, and the tone. **Draft** means nothing is published until you have read it.',
           lambda r: r.point(r.page.locator('select[name=per_week]')), caption_top=True)
    r.step('Your automations are listed below', 'Every automation shows what it made so far. **Run now** starts it immediately, **Pause** stops it, **Delete** removes it.',
           lambda r: (r.scroll(900), r.point(r.page.get_by_role('button', name='Run now').first)))
    r.step('Blog to social and automatic replies', 'The first box turns new articles from your blog into posts. The third answers common questions, like prices, with a reply you write once.',
           lambda r: (r.page.mouse.wheel(0, -4000), r.wait(0.7), r.point(r.page.locator('summary', has_text='Set up').first), r.point(r.page.locator('summary', has_text='Set up').nth(2))))


def g_account(r):
    r.goto('/app/account')
    r.title_card('Your account and security', 'Guide 8 · Account')
    r.step('Open Account', 'Click your name at the bottom of the menu, then **Account**. Here you change your name, password and email alerts.',
           lambda r: r.point('#acc-name'))
    r.step('Change your name', 'Type the name teammates should see and press **Save**.',
           lambda r: (r.page.locator('#acc-name').fill(''), r.type('#acc-name', 'Sara Khan'), r.click(r.page.get_by_role('button', name='Save', exact=True), after=1.0)))
    r.step('Change your password', 'Enter your current password and a new one. Changing it signs you out on every other device, including the phone app.',
           lambda r: r.point(r.page.locator('input[name=password]').first))
    r.step('Choose which emails you get', 'Tick what you want to hear about: a failed post, a channel that needs reconnecting, approvals, an empty queue, and a Monday summary.',
           lambda r: (r.scroll(380), r.click(r.page.locator('label:has(input[value="queue_low"])').first, after=0.5), r.point(r.page.get_by_role('button', name='Save email preferences'))))
    r.step('Turn on two-factor authentication', 'For extra safety, **Set up authenticator** asks for a code from an app such as Google Authenticator every time you log in.',
           lambda r: (r.scroll(420), r.point(r.page.get_by_role('button', name='Set up authenticator'))))
    r.step('Delete your account', 'At the very bottom, **Delete my account** removes your account and everything in it for good. It asks for your password and the word DELETE first.',
           lambda r: (r.scroll(900), r.point(r.page.get_by_role('button', name='Delete my account'))))


GUIDES = [
    dict(slug='getting-started', title='Create your account', summary='Sign up, answer the welcome questions and find your way around.', level='Start here', run=g_start, user='new'),
    dict(slug='connect-channels', title='Connect your social accounts', summary='Add Instagram, Facebook, LinkedIn and the rest, and set when each one posts.', level='Start here', run=g_channels, user='demo'),
    dict(slug='first-post', title='Write and schedule your first post', summary='Pick channels, write, add a photo, choose a time and schedule.', level='Start here', run=g_first_post, user='demo'),
    dict(slug='manage-queue', title='Manage your queue', summary='Queue, Calendar, Drafts, Failed, Approvals and Sent, and what to do in each.', level='Every day', run=g_publish, user='demo'),
    dict(slug='reply-comments', title='Reply to comments from one inbox', summary='Answer, use saved replies, get an AI suggestion and mark comments done.', level='Every day', run=g_community, user='demo'),
    dict(slug='insights', title='See what is working', summary='Totals, best times, hashtags, top posts and reports.', level='Grow', run=g_insights, user='demo'),
    dict(slug='automations', title='Let atwebtech do the weekly work', summary='A weekly AI plan, blog to social and automatic replies.', level='Grow', run=g_automations, user='demo'),
    dict(slug='account', title='Your account and security', summary='Name, password, email alerts, two-factor and deleting your account.', level='Settings', run=g_account, user='demo'),
]


def write_data(guides):
    """app/data/guides.php: what the Guide pages read."""
    body = json.dumps(guides, indent=1, ensure_ascii=False)
    with open(os.path.join(ROOT, 'app', 'data', 'guides.php'), 'w') as f:
        f.write("<?php\n\ndeclare(strict_types=1);\n\n// The guides shown under Guide in the panel. Written by docs/guides/record.py together with the videos,\n"
                "// screenshots and PDFs in public/assets/guides. Change the words there and run it again.\nreturn json_decode(<<<'JSON'\n" + body + "\nJSON, true);\n")


def rich(text):
    return re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', html.escape(text))


PRINT_CSS = """
@font-face { font-family: Figtree; font-weight: 400; src: url('file://%(fonts)s/figtree-latin-400-normal.woff2'); }
@font-face { font-family: Figtree; font-weight: 600; src: url('file://%(fonts)s/figtree-latin-600-normal.woff2'); }
@font-face { font-family: Jakarta; font-weight: 600; src: url('file://%(fonts)s/plus-jakarta-sans-latin-600-normal.woff2'); }
@page { size: A4; margin: 16mm 15mm 18mm; }
* { box-sizing: border-box; }
body { margin: 0; font: 400 11.5pt/1.5 Figtree, sans-serif; color: #14301C; }
.cover { height: 258mm; display: flex; flex-direction: column; justify-content: center; padding: 0 8mm; background: #14301C; color: #F2F7F1; border-radius: 6mm; page-break-after: always; }
.cover img { width: 20mm; margin-bottom: 10mm; }
.cover h1 { font: 600 34pt/1.1 Jakarta, sans-serif; letter-spacing: -.5pt; margin: 0 0 5mm; }
.cover p { font-size: 14pt; color: #B0EC9C; margin: 0 0 3mm; max-width: 130mm; }
.cover ol { margin: 10mm 0 0; padding-left: 6mm; font-size: 12pt; line-height: 1.9; }
.guide { page-break-before: always; }
.guide:first-of-type { page-break-before: auto; }
.kicker { font: 600 9pt Figtree; letter-spacing: 1pt; text-transform: uppercase; color: #3C8C46; }
h2 { font: 600 22pt/1.15 Jakarta, sans-serif; letter-spacing: -.3pt; margin: 1mm 0 2mm; }
.lead { color: #47604D; margin: 0 0 2mm; }
.video { font-size: 10pt; color: #47604D; margin: 0 0 6mm; padding-bottom: 4mm; border-bottom: .3mm solid #D9E5D8; }
.video b { color: #14301C; }
.step { display: grid; grid-template-columns: 9mm 1fr; column-gap: 4mm; margin-bottom: 7mm; break-inside: avoid; }
.n { width: 9mm; height: 9mm; border-radius: 50%%; background: #B0EC9C; font: 600 11pt/9mm Figtree; text-align: center; }
h3 { font: 600 13pt/1.3 Figtree; margin: 1mm 0 1mm; }
.step p { margin: 0 0 3mm; }
.step img { width: 84%%; border: .3mm solid #D9E5D8; border-radius: 2.5mm; display: block; }
"""


def guide_html(g, number):
    steps = ''.join('<div class="step"><div class="n">%d</div><div><h3>%s</h3><p>%s</p><img src="file://%s"></div></div>' %
                    (i + 1, html.escape(st['title']), rich(st['text']), os.path.join(OUT, g['slug'], st['image'])) for i, st in enumerate(g['steps']))
    return ('<section class="guide"><div class="kicker">Guide %d · %s</div><h2>%s</h2><p class="lead">%s</p>'
            '<p class="video">Video (%d seconds): <b>atwebtech.us/app/guide/%s</b></p>%s</section>') % (
        number, html.escape(g['level']), html.escape(g['title']), html.escape(g['summary']), g['seconds'], g['slug'], steps)


def write_pdfs(browser, guides):
    """One PDF per guide and one with all of them."""
    css = PRINT_CSS % {'fonts': os.path.join(ROOT, 'public', 'assets', 'fonts')}
    logo = os.path.join(ROOT, 'public', 'assets', 'img', 'logo-mark-512.png')
    cover = ('<div class="cover"><img src="file://%s"><h1>atwebtech user guide</h1><p>Everything a new user needs, step by step, with a short video for each part.</p>'
             '<ol>%s</ol></div>') % (logo, ''.join('<li>%s</li>' % html.escape(g['title']) for g in guides))
    jobs = [(g['slug'] + '.pdf', guide_html(g, i + 1)) for i, g in enumerate(guides)]
    jobs.append(('atwebtech-user-guide.pdf', cover + ''.join(guide_html(g, i + 1) for i, g in enumerate(guides))))
    page = browser.new_page()
    footer = '<div style="width:100%;font:8px sans-serif;color:#7A8F7E;padding:0 15mm;display:flex;justify-content:space-between"><span>atwebtech.us</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>'
    for name, body in jobs:
        src = os.path.join(tempfile.gettempdir(), 'guide-print.html')
        open(src, 'w').write('<!doctype html><meta charset="utf-8"><style>%s</style>%s' % (css, body))
        page.goto('file://' + src)
        page.wait_for_load_state('networkidle')
        page.pdf(path=os.path.join(OUT, name), format='A4', print_background=True, display_header_footer=True, header_template='<span></span>', footer_template=footer)
        print('%-28s %.1f MB' % (name, os.path.getsize(os.path.join(OUT, name)) / 1048576))
    page.close()


def login(browser, email, password):
    ctx = browser.new_context(viewport={'width': W, 'height': H})
    page = ctx.new_page()
    page.goto(BASE + '/login')
    page.fill('input[type=email]', email)
    page.fill('input[type=password]', password)
    page.keyboard.press('Enter')
    page.wait_for_url('**/app**', timeout=20000)
    state = ctx.storage_state()
    ctx.close()
    return state


def main():
    only = sys.argv[1:]
    os.makedirs(OUT, exist_ok=True)
    meta_path = os.path.join(ROOT, 'docs', 'guides', 'guides.json')  # what each finished recording produced
    done = json.load(open(meta_path)) if os.path.exists(meta_path) else {}
    with sync_playwright() as p:
        browser = p.chromium.launch(channel='chrome', headless=True)
        state = login(browser, *DEMO)
        for g in GUIDES:
            if only == ['pdf'] or (only and g['slug'] not in only):
                continue
            r = Recorder(browser, g, None if g['user'] == 'new' else state)
            failed = None
            try:
                g['run'](r)
            except Exception as e:  # keep what was recorded and say where it stopped
                failed = str(e).split('\n')[0]
            info = r.finish()
            if failed:
                print('%-18s STOPPED after step %d: %s' % (g['slug'], len(r.steps), failed))
                continue
            done[g['slug']] = {'steps': r.steps, **info}
            print('%-18s %d steps, %ds, %.1f MB' % (g['slug'], len(r.steps), info['seconds'], info['size'] / 1048576))
        json.dump(done, open(meta_path, 'w'), indent=1, ensure_ascii=False)
        guides = [dict(slug=g['slug'], title=g['title'], summary=g['summary'], level=g['level'], **done[g['slug']]) for g in GUIDES if g['slug'] in done]
        write_data(guides)
        write_pdfs(browser, guides)
        browser.close()


if __name__ == '__main__':
    main()
