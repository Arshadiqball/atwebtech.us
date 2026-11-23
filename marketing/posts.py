"""The 30-day social calendar for atwebtech (21 Nov to 20 Dec 2026), plus one bonus post for the day the
iPhone app is approved. Edit the text here, then run make.py to redraw the images, videos and calendar.

Each post: day, date, kind (image or video), template, the words on the graphic, and the caption.
In headlines, *stars* mark the words drawn in the accent color.
"""

OFFER = {
    'headline': '50% OFF',
    'terms': 'your first 3 months',
    'plans': 'Essentials or Team',
    'dates': '23 Nov to 1 Dec',
    'code': 'BLACKFRIDAY50',
}

TAGS_CORE = '#socialmediamarketing #smallbusiness #contentcreator #socialmediatips #marketingtips'
TAGS_BF = '#blackfriday #blackfridaydeals #cybermonday #smallbusiness #socialmediamarketing #marketingtools'

POSTS = [
    # ---- Teasers -------------------------------------------------------------------------------
    dict(day=1, date='Sat 21 Nov', kind='video', pillar='Relatable',
         slides=[
             ('You don\'t need to post *every day.*', ''),
             ('You need a *queue.*', 'Write once. It goes out on time.'),
             ('Sunday: write the week.', 'Monday to Friday: live your life.'),
             ('Plan it. Schedule it. *Done.*', 'atwebtech.us'),
         ],
         caption="""You don't need to post every day. You need a queue.

The people who look "always online" usually aren't. They sit down once, write the week, schedule it, and get on with their work.

Try it this Sunday: 30 minutes, five posts, all scheduled.

Save this for Sunday. 📌""",
         tags=TAGS_CORE + ' #contentplanning #batchcontent'),

    dict(day=2, date='Sun 22 Nov', kind='image', pillar='Black Friday', template='teaser',
         headline='Something is coming *tomorrow.*', sub='Our biggest offer of the year. Turn on notifications.',
         caption="""Tomorrow. 👀

If you've been meaning to get your social media under control before the new year, this is the week.

Turn on post notifications so you don't miss it.""",
         tags=TAGS_BF),

    # ---- Black Friday week ---------------------------------------------------------------------
    dict(day=3, date='Mon 23 Nov', kind='image', pillar='Black Friday', template='deal',
         label='BLACK FRIDAY', note='Starts today',
         caption="""Black Friday is open. 🖤

50% off your first 3 months of atwebtech, on Essentials or Team.

What you get:
✅ One queue for Instagram, Facebook, LinkedIn, YouTube, Pinterest, Threads, Bluesky and Mastodon
✅ Write a post once, publish it everywhere
✅ An AI assistant for captions when you're stuck
✅ Every comment in one inbox

Use code BLACKFRIDAY50 at checkout. Ends 1 December.

👉 atwebtech.us""",
         tags=TAGS_BF),

    dict(day=4, date='Tue 24 Nov', kind='image', pillar='How-to', template='checklist',
         label='SAVE THIS', headline='Plan a week of posts in *20 minutes*',
         items=['Pick 5 topics your customers ask about', 'Write one short post per topic', 'Add a photo you already have',
                'Schedule one per day', 'Close the app. You are done.'],
         caption="""A week of posts in 20 minutes. Here's the routine:

1. Pick 5 topics your customers ask about
2. Write one short post per topic (3 or 4 lines is enough)
3. Add a photo you already have on your phone
4. Schedule one per day
5. Close the app

That's it. No daily scramble.

Save this, and try it before Monday. 📌""",
         tags=TAGS_CORE + ' #contentplanning #contentcalendar'),

    dict(day=5, date='Wed 25 Nov', kind='video', pillar='Black Friday',
         slides=[
             ('*Half price.* Three months.', 'Black Friday at atwebtech'),
             ('One queue for *every* network.', 'Instagram, Facebook, LinkedIn, YouTube and more'),
             ('Write once. *Publish everywhere.*', 'With AI help for captions'),
             ('Code *BLACKFRIDAY50*', 'Ends 1 December · atwebtech.us'),
         ],
         caption="""Half price for three months. 🖤

One place to plan, schedule and publish to every network you use. Write once, publish everywhere, and answer every comment from one inbox.

Code BLACKFRIDAY50 · Essentials or Team · ends 1 December

👉 atwebtech.us""",
         tags=TAGS_BF),

    dict(day=6, date='Thu 26 Nov', kind='image', pillar='Engagement', template='poll',
         headline='How do you post?', a='I batch it on Sunday', b='I wing it every day',
         caption="""Be honest. 😅

A: I batch my posts once a week
B: I open the app every morning and hope for an idea

Comment A or B. (No judgement. We've all been B.)""",
         tags=TAGS_CORE + ' #contentcreation'),

    dict(day=7, date='Fri 27 Nov', kind='image', pillar='Black Friday', template='deal',
         label='BLACK FRIDAY', note='Today',
         caption="""It's Black Friday. 🖤

50% off your first 3 months of atwebtech.

If "be more consistent on social" is on your list for 2027, this is the cheapest it will be to start.

Code: BLACKFRIDAY50
Plans: Essentials or Team
Ends: 1 December

👉 atwebtech.us""",
         tags=TAGS_BF),

    dict(day=8, date='Sat 28 Nov', kind='image', pillar='Product', template='screen', screen='07-queue',
         headline='Your whole week. *Already scheduled.*', sub='This is what a calm Monday looks like.',
         caption="""This is what a calm Monday looks like.

Every post for the week, lined up and waiting. No "what do I post today?" at 8am.

Black Friday: 50% off your first 3 months with code BLACKFRIDAY50. Ends 1 December.

👉 atwebtech.us""",
         tags=TAGS_BF + ' #contentcalendar'),

    dict(day=9, date='Sun 29 Nov', kind='video', pillar='How-to',
         slides=[
             ('3 posts *any* small business can write *today*', ''),
             ('1. Answer the question you get most', 'Your FAQ is your content.'),
             ('2. Show how it\'s made', 'Behind the scenes always works.'),
             ('3. Introduce a real person', 'People follow people.'),
             ('Write all three. *Schedule them.*', 'atwebtech.us'),
         ],
         caption="""Out of ideas? Here are 3 posts any small business can write today:

1. Answer the question customers ask you most
2. Show how something is made, packed or prepared
3. Introduce a real person on your team (or yourself)

Write all three now, schedule them across the week, and you're covered.

Which one are you posting first? 👇""",
         tags=TAGS_CORE + ' #contentideas #postideas'),

    dict(day=10, date='Mon 30 Nov', kind='image', pillar='Black Friday', template='deal',
         label='CYBER MONDAY', note='2 days left',
         caption="""Cyber Monday. Two days left. ⏳

50% off your first 3 months of atwebtech, on Essentials or Team.

Plan December now and walk into the holidays with your posts already scheduled.

Code: BLACKFRIDAY50 · Ends tomorrow night

👉 atwebtech.us""",
         tags=TAGS_BF),

    dict(day=11, date='Tue 1 Dec', kind='image', pillar='Black Friday', template='deal',
         label='LAST DAY', note='Ends tonight',
         caption="""Last day. ⏰

50% off your first 3 months of atwebtech ends tonight.

Code: BLACKFRIDAY50
Plans: Essentials or Team

After today it's back to full price.

👉 atwebtech.us""",
         tags=TAGS_BF),

    # ---- Evergreen growth ----------------------------------------------------------------------
    dict(day=12, date='Wed 2 Dec', kind='image', pillar='Relatable', template='versus',
         headline='Posting on social media', left_title='Without a plan', right_title='With a queue',
         left=['"What do I post today?"', 'Posting at midnight', 'Gone quiet for 2 weeks', 'Missed comments'],
         right=['Written on Sunday', 'Goes out on time', 'Shows up every week', 'One inbox for replies'],
         caption="""Same business. Same hours in the day. Very different week.

The difference isn't talent or time. It's deciding once, in advance, instead of deciding every morning.

Which side are you on right now? 👇""",
         tags=TAGS_CORE + ' #contentplanning'),

    dict(day=13, date='Thu 3 Dec', kind='image', pillar='Product', template='screen', screen='13-inbox',
         headline='Every comment. *One inbox.*', sub='Reply to all your networks without opening five apps.',
         caption="""A comment you never answered is a customer you never met.

atwebtech pulls the comments from your connected accounts into one inbox. Reply, mark as done, move on. No more opening five apps to check.

👉 atwebtech.us""",
         tags=TAGS_CORE + ' #communitymanagement #customerservice'),

    dict(day=14, date='Fri 4 Dec', kind='video', pillar='How-to',
         slides=[
             ('Stop doing *this* on social media', ''),
             ('Posting only when you have something to *sell*', 'Give first. Sell sometimes.'),
             ('Ignoring your *comments*', 'Replies are where trust is built.'),
             ('Disappearing for *weeks*', 'Small and regular beats big and rare.'),
             ('Fix all three with a *queue.*', 'atwebtech.us'),
         ],
         caption="""3 things quietly hurting your social media:

1. Only posting when you have something to sell
2. Leaving comments unanswered
3. Disappearing for weeks, then posting five times in a day

The fix for all three is boring and it works: plan ahead, show up regularly, reply to people.

Which one are you guilty of? 😅""",
         tags=TAGS_CORE + ' #socialmediamistakes'),

    dict(day=15, date='Sat 5 Dec', kind='image', pillar='Relatable', template='hook',
         label='REMINDER', headline='Consistency beats *going viral.*',
         sub='One post that pops is luck. Showing up every week is a business.',
         caption="""Going viral once is luck.

Showing up every week is how people come to know you, trust you and buy from you.

You don't need a hit. You need a habit.

Send this to someone who needs to hear it. 💚""",
         tags=TAGS_CORE + ' #consistency #entrepreneurmindset'),

    dict(day=16, date='Sun 6 Dec', kind='image', pillar='How-to', template='checklist',
         label='SUNDAY RESET', headline='The *30-minute* content reset',
         items=['Look at last week: what got replies?', 'Write 5 posts for the week ahead', 'Reuse one old post that did well',
                'Schedule everything', 'Answer every open comment'],
         caption="""The Sunday content reset. 30 minutes, once a week:

☑️ Look at last week. What got replies?
☑️ Write 5 posts for the week ahead
☑️ Reuse one older post that did well
☑️ Schedule everything
☑️ Answer every open comment

Do this four Sundays in a row and you'll never go back.

Save for Sunday. 📌""",
         tags=TAGS_CORE + ' #sundayreset #contentplanning'),

    dict(day=17, date='Mon 7 Dec', kind='image', pillar='Product', template='screen', screen='08-composer',
         headline='Stuck on the caption? *Start with a brief.*', sub='Tell the AI assistant what the post is about. Edit what it writes.',
         caption="""Staring at an empty caption box is the worst part of posting.

In atwebtech you write one line about the post ("our new autumn menu, friendly, ask a question") and the AI assistant drafts it. You edit, you schedule, you're done.

It's a starting point, not a robot voice. You always have the last word.

👉 atwebtech.us""",
         tags=TAGS_CORE + ' #aitools #captionideas'),

    dict(day=18, date='Tue 8 Dec', kind='image', pillar='Engagement', template='poll',
         headline='Which one is harder?', a='Coming up with ideas', b='Posting consistently',
         caption="""Quick question for everyone who runs their own social media:

A: Coming up with ideas
B: Actually posting consistently

Comment A or B. We'll make a post that helps with whichever wins. 👇""",
         tags=TAGS_CORE),

    dict(day=19, date='Wed 9 Dec', kind='video', pillar='How-to',
         slides=[
             ('Turn *one idea* into *5 posts*', ''),
             ('1. The tip', 'Say it in two lines.'),
             ('2. The story behind it', 'When did you learn it?'),
             ('3. The mistake people make', 'What goes wrong without it?'),
             ('4. A question for your audience', '5. A before and after'),
             ('One idea. A full week.', 'atwebtech.us'),
         ],
         caption="""You don't need 5 ideas a week. You need 1 idea, told 5 ways:

1. The tip itself
2. The story of how you learned it
3. The mistake people make without it
4. A question about it for your audience
5. A before and after

One idea. A full week of posts.

Save this for the next time you're stuck. 📌""",
         tags=TAGS_CORE + ' #contentrepurposing #contentideas'),

    dict(day=20, date='Thu 10 Dec', kind='image', pillar='Relatable', template='hook',
         label='MYTH', headline='"You have to be on *every* platform."',
         sub='No. Be good on one or two. Use a scheduler to cover the rest without extra work.',
         caption="""Myth: you have to be everywhere.

Truth: pick the one or two places your customers actually are, and do those well.

If you want to be on the others too, write the post once and let a scheduler publish it there. Same effort, more reach.

Where do your customers hang out? 👇""",
         tags=TAGS_CORE + ' #marketingmyths'),

    dict(day=21, date='Fri 11 Dec', kind='image', pillar='Product', template='screen', screen='15-insights',
         headline='Know what worked. *Do more of it.*', sub='Followers, views, comments and your best posts in one place.',
         caption="""Guessing is expensive.

atwebtech shows you what happened after you posted: followers, views, reactions, comments, and which posts did best. So next week you can do more of what worked and less of what didn't.

👉 atwebtech.us""",
         tags=TAGS_CORE + ' #socialmediaanalytics'),

    dict(day=22, date='Sat 12 Dec', kind='image', pillar='How-to', template='checklist',
         label='SAVE THIS', headline='*10 post ideas* for December',
         items=['Your year in 3 photos', 'A customer question, answered', 'Holiday opening hours', 'A gift idea from your shop',
                'Thank your customers', 'One thing you learned this year', 'A behind-the-scenes moment', 'Your plan for next year',
                'Your most popular product', 'A "see you in January" post'],
         caption="""December post ideas, ready to steal:

1. Your year in 3 photos
2. A customer question, answered
3. Your holiday opening hours
4. A gift idea from your shop
5. A thank-you to your customers
6. One thing you learned this year
7. A behind-the-scenes moment
8. Your plan for next year
9. Your most popular product of the year
10. A "see you in January" post

Schedule them now and enjoy your holidays. 🎄

Save this. 📌""",
         tags=TAGS_CORE + ' #decembercontent #postideas #holidaymarketing'),

    dict(day=23, date='Sun 13 Dec', kind='video', pillar='Product',
         slides=[
             ('Run your socials from *one place*', ''),
             ('Plan the week', 'One queue for every network'),
             ('Reply to comments', 'One inbox'),
             ('See what worked', 'Simple stats'),
             ('*Free* for up to 3 channels', 'atwebtech.us'),
         ],
         caption="""Everything you need to run your social media, in one place:

📅 Plan and schedule your week
💬 Reply to comments from one inbox
📈 See what worked

Free for up to 3 channels. No credit card.

👉 atwebtech.us""",
         tags=TAGS_CORE + ' #socialmediatools'),

    dict(day=24, date='Mon 14 Dec', kind='image', pillar='Product', template='hook',
         label='HOW IT WORKS', headline='Post once. *Publish everywhere.*',
         sub='Instagram · Facebook · LinkedIn · YouTube · Pinterest · Threads · Bluesky · Mastodon',
         caption="""Copy. Paste. Resize. Repeat. Five times.

Or: write it once, pick your channels, press schedule.

atwebtech publishes to Instagram, Facebook, LinkedIn, YouTube, Pinterest, Threads, Bluesky and Mastodon from one composer.

👉 atwebtech.us""",
         tags=TAGS_CORE + ' #socialmediascheduler'),

    dict(day=25, date='Tue 15 Dec', kind='image', pillar='Relatable', template='versus',
         headline='Where your posting time goes', left_title='By hand', right_title='Scheduled',
         left=['Log in to each app', 'Retype the same post', 'Remember to do it daily', 'Forget on busy days'],
         right=['One login', 'Write it once', 'Do it once a week', 'It posts on busy days too'],
         caption="""Posting isn't hard. Remembering to post, every day, on every app, is.

That's the part worth handing off.

How long does posting take you each week? 👇""",
         tags=TAGS_CORE + ' #productivitytips #timemanagement'),

    dict(day=26, date='Wed 16 Dec', kind='image', pillar='Engagement', template='poll',
         headline='When do you write your captions?', a='Calmly, in advance', b='In a panic, at midnight',
         caption="""No wrong answers. (There is one wrong answer.) 😄

A: Calmly, in advance
B: In a panic, at midnight

Comment A or B. 👇""",
         tags=TAGS_CORE),

    dict(day=27, date='Thu 17 Dec', kind='video', pillar='How-to',
         slides=[
             ('*4 hooks* that stop the scroll', ''),
             ('"Nobody tells you this about…"', 'Curiosity'),
             ('"I was wrong about…"', 'Honesty'),
             ('"Do this before you…"', 'A clear next step'),
             ('"The mistake I see every week…"', 'Experience'),
             ('Save these. *Use one today.*', 'atwebtech.us'),
         ],
         caption="""The first line decides if anyone reads the second.

4 opening lines that earn attention:

1. "Nobody tells you this about ___"
2. "I was wrong about ___"
3. "Do this before you ___"
4. "The mistake I see every week: ___"

Fill in the blank with something true about your business.

Save this and use one today. 📌""",
         tags=TAGS_CORE + ' #hooks #copywritingtips #contentcreation'),

    dict(day=28, date='Fri 18 Dec', kind='image', pillar='Product', template='screen', screen='11-approvals',
         headline='Nothing goes out *until it\'s approved.*', sub='Teammates draft. You approve or ask for changes.',
         caption="""For teams and agencies: a post shouldn't go live because someone hit the wrong button.

In atwebtech, teammates write and send for approval. You approve it, or send it back with a note. Clients can have their own workspace too.

👉 atwebtech.us""",
         tags=TAGS_CORE + ' #socialmediamanager #agencylife #teamwork'),

    dict(day=29, date='Sat 19 Dec', kind='image', pillar='How-to', template='checklist',
         label='HOLIDAY PLAN', headline='Schedule now. *Log off* for the holidays.',
         items=['Write your holiday hours post', 'Schedule a thank-you post', 'Queue 3 posts for the quiet week',
                'Plan your first post of January', 'Turn on notifications for comments only'],
         caption="""You deserve a real break. Your social media can keep going without you.

☑️ Write your holiday hours post
☑️ Schedule a thank-you to your customers
☑️ Queue 3 posts for the quiet week
☑️ Plan your first post of January
☑️ Keep notifications on for comments only

An hour today buys you two weeks off.

Save this. 📌""",
         tags=TAGS_CORE + ' #holidaymarketing #worklifebalance'),

    dict(day=30, date='Sun 20 Dec', kind='image', pillar='Product', template='hook',
         label='START FREE', headline='Free for *3 channels.* Start your queue today.',
         sub='No credit card. Plan, schedule and publish from one place.',
         caption="""If you've read this far into our month of posts, here's the short version:

Plan your posts once a week. Schedule them. Reply to people. Repeat.

atwebtech is free for up to 3 channels, with no credit card.

Start your queue today. 👉 atwebtech.us""",
         tags=TAGS_CORE + ' #socialmediascheduler'),
]

# Post this one only on the day Apple approves the app. Do not post it before.
BONUS = dict(day=0, date='When the iPhone app is approved', kind='image', pillar='Launch', template='screen', screen='05-home',
             headline='atwebtech is on the *iPhone.*', sub='Plan, schedule and reply from your pocket.',
             caption="""It's here. 📱

atwebtech is now on the App Store for iPhone and iPad.

Plan your week, publish to every network, and answer comments from your phone.

Search "atwebtech" on the App Store. Free for up to 3 channels.""",
             tags=TAGS_CORE + ' #newapp #iosapp #appstore')
