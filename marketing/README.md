# Social media content: 21 Nov to 20 Dec 2026

One post a day for 30 days, with the Black Friday campaign in the first 11 days, plus one bonus post for the day the iPhone app is approved.

- `calendar.md`: every day's caption and hashtags, with the file to post. `calendar.csv` is the same thing as a spreadsheet.
- `images/`: 24 feed images, 1080 x 1350.
- `videos/`: 7 short videos, 1080 x 1920, 9 to 13 seconds, each with a `-cover.jpg`. They have no sound on purpose: add a trending sound in Instagram, TikTok or YouTube when you post, which helps reach.
- `posts.py`: all the words. `make.py`: draws everything from them.

## Before you start

1. **Create the promo code in Stripe** before 23 Nov: code `BLACKFRIDAY50`, 50% off, repeating for 3 months, valid 23 Nov to 1 Dec, for Essentials and Team. Checkout already accepts promo codes. Without it, the Black Friday posts promise something checkout cannot give.
2. **Do not post the bonus "atwebtech is on the iPhone" image** until Apple approves the app.

## The mix

| Kind | Days | Why |
|---|---|---|
| Black Friday | 2, 3, 5, 7, 8, 10, 11 | Teaser, launch, the day itself, Cyber Monday, last day |
| How-to (save-worthy) | 4, 9, 14, 16, 19, 22, 27, 29 | Checklists and tips people save and share |
| Relatable | 1, 12, 15, 20, 25 | Short truths people send to a friend |
| Engagement | 6, 18, 26 | A or B polls that are easy to comment on |
| Product | 13, 17, 21, 23, 24, 28, 30 | What atwebtech does, shown with real screens |

## Changing something

Edit the text in `posts.py`, then run:

```bash
pip install pillow
python make.py
```

ffmpeg is needed for the videos.

## The 60-second promo video

`promo/atwebtech-promo-60s.mp4` is a 1920 x 1080, 60 second video with a soundtrack: hook, problem, the five things atwebtech does (plan, create, publish, engage, grow), the free plan, and the call to action.

It is an animated web page captured frame by frame, so every word can be changed:

```bash
pip install playwright pillow numpy
python promo/build.py                 # writes promo/promo.html from the text in build.py
python promo/render.py 0 1800         # captures 1800 frames into promo/frames/ (slow: run a few ranges at once)
python promo/music.py                 # synthesizes promo/music.wav (no samples, nothing to license)
ffmpeg -framerate 30 -i promo/frames/%05d.jpg -i promo/music.wav -c:v libx264 -crf 17 -pix_fmt yuv420p -c:a aac -b:a 192k -shortest -movflags +faststart promo/atwebtech-promo-60s.mp4
```

For the vertical version (`promo/atwebtech-promo-60s-9x16.mp4`, 1080 x 1920, for Reels, TikTok and Shorts) add `v`: `python promo/build.py v`, `python promo/render.py 0 1800 v`, then the same ffmpeg command with `promo/frames-v/%05d.jpg`. It keeps the top 250 px and bottom 320 px clear of text, where the apps draw their own buttons.

The calendar in the "Plan" scene is a real screenshot of a sample workspace (`promo/shots/calendar.jpg`); the other screens are drawn to match the product. Needs Google Chrome and ffmpeg.

### Second promo: "From trend to post"

`promo/atwebtech-trend-to-post-60s.mp4` (1920 x 1080) and `promo/atwebtech-trend-to-post-60s-9x16.mp4` (1080 x 1920) follow one workflow: pick a trend, let the AI Assistant write the draft, preview it on each network, schedule it at the best time. It has its own look (colour blocks, thick outlines, wipes) and its own 96 BPM soundtrack.

Built the same way, from `promo/build2.py` and `promo/music2.py`: `python promo/build2.py` (add `v` for vertical), `python promo/render.py 0 1800 2` (add `v`), `python promo/music2.py`, then ffmpeg with `promo/frames2/%05d.jpg` (or `frames2-v`) and `promo/music2.wav`. The trends and the best-times grid are samples, and say so on screen.
