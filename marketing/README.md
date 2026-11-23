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
