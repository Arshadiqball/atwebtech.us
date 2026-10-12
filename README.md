# atwebtech.us

Marketing website for **atwebtech**, a social media workspace in development. Plain PHP (no framework, no build step), MySQL through PDO, one stylesheet and one vanilla JavaScript file. Runs on standard shared PHP hosting.

## Requirements

- PHP 8.1 or newer with `pdo_mysql` (or `pdo_sqlite` for local testing) and `mbstring`
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` (or any server that can route requests to `public/index.php`)

## Local setup

```bash
cp .env.example .env
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # paste into APP_KEY
# set APP_URL=http://localhost:8000 and your local DB_* values

mysql -u root -e "CREATE DATABASE atwebtec_us CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php bin/migrate.php

php -S localhost:8000 -t public public/index.php
```

For a quick test without MySQL, set `DB_DRIVER=sqlite` (the file is created at `DB_SQLITE_PATH`) and run `php bin/migrate.php`.

## Project layout

```
public/            Document root: index.php front controller, assets, favicons, .htaccess
app/               Bootstrap, config, router table (routes.php), helpers, security, forms
app/data/          Site content: menus, features, solutions, articles, FAQ, legal drafts, demo data
app/icons/         Phosphor UI icons (MIT) and Simple Icons brand glyphs (CC0)
views/             layout.php, pages/, partials/, demos/ (interactive demo markup)
database/          SQL migrations for MySQL and SQLite
bin/migrate.php    CLI migration runner (tracks applied files in schema_migrations)
storage/           logs/ and sessions/ (must be writable, never web-accessible)
```

Content lives in `app/data/*.php`, so most copy changes don't touch templates. Routes are declared in `app/routes.php`.

## Deploying to shared hosting

1. Upload the project **outside** the public web root if possible, and point the domain's document root at `public/`.
   If the host forces the project root as document root, the root `.htaccess` rewrites requests into `public/` and blocks `storage/`, `database/`, `views/`, `bin/` and dotfiles. `/app` is the workspace, so that address is routed through `public/` rather than blocked.
2. Create `.env` on the server from `.env.example`:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://atwebtech.us`
   - a fresh `APP_KEY` (64 hex characters; required in production)
   - the production database credentials
3. Migrations run by themselves the first time the site or `bin/publish.php` starts, and again whenever a new file is added under `database/migrations/`. `php bin/migrate.php` still works over SSH and prints what it applied. Do not also import those files by hand.
4. Make `storage/logs` and `storage/sessions` writable by PHP.
5. Serve over HTTPS. In production the app sends HSTS, marks the session cookie `Secure`, and only then allows indexing (`robots.txt` and the robots meta tag block indexing in every other environment).

## Configuration (`.env`)

| Key | Purpose |
|---|---|
| `APP_ENV` | `local`, `staging` or `production`. Anything except production is `noindex`. |
| `APP_KEY` | Secret for signed form timestamps and IP hashing. |
| `DB_*` | Database connection. Credentials only ever come from the environment. |
| `MAIL_ENABLED`, `MAIL_FROM`, `MAIL_NOTIFY_TO` | Optional notification email for new signups and messages, via PHP `mail()`. Submissions are saved whether or not mail works. |
| `CONTACT_EMAIL`, `CONTACT_PHONE`, `CONTACT_ADDRESS` | Public details shown on `/contact`. A blank value hides that line. |
| `ADMIN_EMAILS` | Comma-separated account emails that can open `/admin` and read contact messages. |
| `INTEGRATIONS_AVAILABLE` | Comma-separated channel slugs to mark as Available, e.g. `instagram,linkedin`. Everything else shows Planned. |
| `RATE_LIMIT_MAX`, `RATE_LIMIT_WINDOW_MINUTES` | Saved form submissions allowed per connection per window (default 5 per 10 minutes). |

## Forms

`POST /waitlist` and `POST /contact` work with and without JavaScript:

- With JavaScript, the form is submitted with `fetch` and the response is shown inline.
- Without JavaScript, the server redirects back to the form with a one-time message.

Each submission must pass a session CSRF token, a honeypot field, a signed timestamp (rejects submissions under 2 seconds old and tampered values) and a database-backed rate limit. Input is validated on both client and server. All queries use prepared statements. A success message is only shown after the row is written. Repeat waitlist signups update the existing row instead of creating duplicates.

Contact messages are saved in `enquiries` and listed at `/admin` for the accounts named in `ADMIN_EMAILS`. Anyone else gets a not-found page. Log in with one of those accounts, then open `/admin`. On the production server, add the same `ADMIN_EMAILS` value to `.env`.

Data is stored in `waitlist_signups` and `enquiries`. Signups from `/pricing` also record the chosen `plan`, `billing` and `channels` (migration `002`); these stay empty when no plan was picked.

Campaign links should use `/join`. Example: `/join?utm_source=google&utm_medium=cpc&utm_campaign=launch`. Email can use `utm_source=email`. The first link in a browser session is kept, including `gclid`, `fbclid`, and `msclkid` when an ad network adds one. A successful signup goes to `/joined`, which is the page to use as the ad conversion. The same campaign labels are stored on a new account if that person then signs up. Repeat visits do not overwrite the first campaign.

## Accounts and onboarding

`/signup` creates an account with an email and password. After signing up, `/welcome` shows a welcome screen and four questions (role, current tools, number of accounts, channels in focus). Each question can be skipped, and someone who leaves partway through resumes at the next unanswered question. When it's finished, `/app` opens the workspace.

- **Data:** migration `003` adds `users` (email, password hash, display name, theme) and `user_onboarding` (answers, the "How did you hear about us?" survey, dismissed panels). Question options live in `app/data/onboarding.php`, and workspace sections in `app/data/workspace.php`.
- **Security:**
  - Passwords are hashed with Argon2id, or bcrypt where Argon2 isn't available. They need at least 8 characters, 1 uppercase character and 1 number or symbol.
  - The session ID is regenerated on login and logout.
  - Every account form requires the session CSRF token. Sign-up also uses the honeypot, signed timestamp and rate limit.
  - Login locks for 15 minutes after 10 failed attempts, per connection and per email address.
  - Two-factor authentication is optional. From Account in the workspace menu, a person confirms their password, scans a QR code with an authenticator app, and saves 8 one-time recovery codes. After that, sign-in asks for a 6-digit code (or a recovery code) before the session starts. The shared secret is encrypted at rest, recovery codes are stored only as hashes, and the plaintext codes are shown once.
  - After login, only `/app` and `/welcome` paths are accepted as a destination.
  - Account pages are `noindex` and disallowed in `robots.txt`.
- **Workspace:** Home has the activity summary, first steps, Up Next, Comments, Templates and the survey. There is a light/dark theme (saved per user) and a collapsible sidebar. The sidebar's "+ New" menu opens the post composer or a new idea. Start pages and team invites are marked "Soon".
- **Create** (`/app/create`):
  - The ideas board saves to the database. It has Unassigned plus your own groups (To Do, In Progress and Done are created on the first visit). Ideas can be dragged between columns, edited, tagged, filtered by tag, viewed as a gallery and turned into posts.
  - Templates has a Discover library of original prompts in `app/data/templates.php`, plus Personal templates you save yourself.
  - Feeds is marked "Coming soon".
- **Publish** (`/app/publish`):
  - The Create Post composer can target several channels at once (one post is saved per channel). It has tags, an image or video upload, emoji and hashtag shortcuts, a character counter using the lowest limit among the picked channels, and YouTube settings: title, category, visibility, license, notify subscribers, allow embedding, made for kids and AI-generated. Side panels hold Templates, the AI Assistant and a live Preview. "Create Another" keeps the dialog open after saving.
  - Posts for a connected YouTube channel are published by the worker (see "Publishing" below). Posts for networks that aren't connected are saved as plans and never sent, and the composer says so.
  - The queue lists suggested times (from each channel's schedule, or `app/data/workspace.php` when nothing is connected), failed posts with their error and a Retry button, and a week calendar. Sent lists published posts with a link to the video. Approvals is marked "Coming soon".
- **Channels** (`/app/channels`): see "Connecting YouTube" below. Each channel has its own page: posts sent this week against the goal, Queue / Drafts / Approvals / Sent, a dismissible "Welcome to your Queue" card, a custom schedule editor (gear icon) and a queue that keeps loading weeks as you scroll, up to two years ahead.
- **Community and Insights:** empty states, including per-channel pages in the sidebar. They show no made-up comments or numbers.
- **Data:** migration `004` adds `idea_groups`, `ideas`, `posts`, `post_templates` and the `users.timezone` column. The time zone is detected from the browser on the first visit and can be changed on Publish. Every query is scoped to the logged-in user, and each user has limits on how many ideas, posts, groups and templates they can store.
- **Not built yet:** password reset and email verification (both need working outbound email). The login page sends people who've forgotten their password to `/contact`.

## Connecting YouTube and LinkedIn

"Connect a New Channel" (in the sidebar, the "+ New" menu and `/app/channels`) lists every platform. YouTube and a personal LinkedIn Profile work once their keys are set; Company Pages and the other networks are marked "Soon".

**The flow:**
1. Choosing YouTube opens Google sign-in. Google shows its account chooser, then its consent screen.
2. Back in atwebtech, the "Confirm your Account" page lists the user's channels. They pick which ones to add.
3. Setup asks for a posting goal (1, 3 or 5 posts a week, or a custom number from 1 to 14) and shows the suggested times. Those times fill the queue on Publish and on the channel's own page.
4. The channel appears in the sidebar and counts towards the free plan's 3 channels. It can be disconnected from the "⋯" menu on its page. Disconnecting the last channel of that network revokes atwebtech's access.

**YouTube scopes:** `youtube.readonly` (channel name and picture) and `youtube.upload` (upload videos). If someone unticks the upload permission on Google's screen, the channel still connects but shows a "Reconnect to publish" banner, and scheduling to it is refused. Channels connected before publishing existed show the same banner. It doesn't reply to comments or collect analytics.

**LinkedIn scopes:** `openid`, `profile`, `email` (name and picture) and `w_member_social` (create posts on the personal Profile). Company Pages are not requested and are not available. If someone denies sharing, the Profile still connects but shows the reconnect banner, and scheduling is refused. atwebtech does not read messages, comments or analytics, and does not write a featured section on the Profile.

**Security:**
- Google sign-in uses the authorization code flow with PKCE (an extra one-time secret on each sign-in).
- A random `state` value ties each sign-in to the session and is checked on return. Unfinished connections expire after 10 minutes.
- Starting a connection needs the CSRF token and is rate limited.
- Access and refresh tokens are encrypted with libsodium (a key derived from `APP_KEY`) before they're stored in `channels` (migration `005`). They never reach the browser.
- Channel pictures are only shown from Google's and LinkedIn's image hosts, which are allowed in the CSP's `img-src`. The CSP's `form-action` also allows `https://accounts.google.com` and `https://www.linkedin.com`, so the connect form can hand off to those sites.

**Setup (one time):**
1. In [Google Cloud Console](https://console.cloud.google.com/), create a project, then enable **YouTube Data API v3** under *APIs & Services → Library*.
2. Under *OAuth consent screen*, choose **External**. Add the app name, support email, privacy and terms URLs, and the `youtube.readonly` and `youtube.upload` scopes. While the app is in **Testing**, only the Google accounts you add as test users can connect. Moving to production needs Google's verification.
3. Under *Credentials → Create credentials → OAuth client ID*, choose **Web application**. Add this authorised redirect URI exactly, using your `APP_URL`:
   `https://atwebtech.us/app/channels/callback/youtube` (locally: `http://localhost:8000/app/channels/callback/youtube`)
4. Put the client ID and secret in `.env` as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`. Never commit them. Changing `APP_KEY` later makes stored tokens unreadable, so users would need to reconnect.

**LinkedIn setup (one time):**
1. In the [LinkedIn Developer Portal](https://www.linkedin.com/developers/), create an app and add **Sign In with LinkedIn using OpenID Connect** and **Share on LinkedIn**.
2. Add this authorised redirect URL exactly, using your `APP_URL`:
   `https://atwebtech.us/app/channels/callback/linkedin` (locally: `http://localhost:8000/app/channels/callback/linkedin`)
3. Put the client ID and secret in `.env` as `LINKEDIN_CLIENT_ID` and `LINKEDIN_CLIENT_SECRET`. Never commit them.

`GOOGLE_OAUTH_TEST_BASE` and `LINKEDIN_OAUTH_TEST_BASE` point token and API calls at a local fake server for automated tests. They're ignored when `APP_ENV=production`.

## Channel Settings

Each connected channel has `/app/channels/{id}/settings` with two tabs.

- **Posting Schedule (working):** workspace time zone, posting-goal stepper, weekly slot calendar, generate suggested slots, add a slot, and save. The same goal and `schedule` JSON used after connect.
- **General (Coming soon):** link shortening, campaign tracking, bulk CSV upload, LinkedIn profile extras, and queue actions (pause, shuffle, clean, delete all drafts, empty queue). Controls are disabled and labelled. They do not pretend to work.

## Community and Insights

- **Community** (`/app/community` and `/app/channels/{id}/community`) matches the comments / mentions layout. atwebtech does not read comments or mentions. The page says so and stays empty.
- **Insights** (`/app/insights` and `/app/channels/{id}/insights`) shows posts atwebtech actually sent in the selected date range, plus a simple posts-per-day chart. Followers, reactions, impressions and the other network metrics are labelled Coming soon and show an em dash — never a made-up number.
- **First comment:** the composer can save a first comment on a LinkedIn post. After the post publishes, `bin/publish.php` tries to post that comment. If LinkedIn refuses, the post is still `sent` and Sent shows why the comment failed.

## Publishing

`bin/publish.php` sends due posts to YouTube and to a personal LinkedIn Profile. Run it every minute from cron:

```
* * * * * /usr/bin/php /path/to/atwebtech/bin/publish.php >> /path/to/atwebtech/storage/logs/publish.log 2>&1
```

- **What a run does:**
  - Takes a lock, so overlapping runs never send twice.
  - Claims each due post (`planned` → `publishing`) and refreshes the Google or LinkedIn token if it's close to expiring.
  - **YouTube:** uploads the video with YouTube's resumable upload, along with the title, description, category, visibility, license, embedding, made-for-kids, AI-generated and notify-subscribers settings.
  - **LinkedIn:** creates a public Profile post (`ugcPosts`) with the caption, an optional image (`assets` + binary upload), and an optional first comment (`socialActions`). Videos and Company Pages are not sent.
  - Stores the video ID and link and marks the post `sent`. Network errors, HTTP 429 and 5xx are retried up to 3 times, 10 and then 20 minutes later. Anything else marks the post `failed` with a readable reason.
  - Posts stuck in `publishing` for 90 minutes go back to the queue.
  - Uploads that were never finished are deleted after a day, and files no post uses after a week.
- **Google limits to know about:**
  - Until Google audits the app, YouTube locks videos uploaded through an unverified API project to **private**, whatever visibility was chosen. The composer says so. Apply for the audit in Google Cloud once the app is verified.
  - Each upload costs 1,600 units of the default 10,000-unit daily YouTube quota, so a new project manages about 6 uploads a day. Quota errors show as "YouTube refused the upload…" on the post. Ask Google for more quota before launch.
  - YouTube decides on its own whether a video is a Short: vertical, and up to 3 minutes.
- **Uploads:**
  - Files are sent in 4 MB chunks, so the default `post_max_size` of 8 MB is enough, even for 200 MB videos.
  - Allowed: JPG, PNG, WebP and GIF images up to 10 MB; MP4, MOV and WebM videos up to 200 MB; 2 GB per user.
  - The real file type is checked with `finfo` once the upload finishes, and anything that doesn't match is deleted.
  - Files live in `storage/media/{user}/` with random names, outside `public/`. They're only served to their owner through `/app/media/{id}`, which supports range requests so videos can be scrubbed.
  - Leave room on the disk for queued videos.
- **Data:** migration `006` adds `media`, the publishing columns on `posts` (`channel_id`, `media_id`, `title`, `meta`, `tags`, `sent_at`, `external_id`, `external_url`, `error`, `attempts`) and `channels.queue_intro_closed_at`. It only adds tables and columns; nothing existing is changed.

## AI Assistant

The composer's AI Assistant writes a draft from a short brief using OpenAI's Chat Completions API. The call is made from the server, and the key never reaches the browser.

- Set `OPENAI_API_KEY` in `.env` to switch it on. With no key, the panel says the assistant is off. `OPENAI_MODEL` defaults to `gpt-4o-mini`.
- Each user gets 6 generations a minute and `AI_DAILY_LIMIT` (default 30) per 24 hours. OpenAI bills every generation to your account.
- The system prompt tells the model not to invent statistics, prices, reviews, awards or guarantees, and to leave `[placeholders]` for missing facts. The result is shown as plain text, and the user chooses whether to use it.
- Prompts and replies aren't stored or logged.
- `OPENAI_TEST_BASE` points the call at a local fake for tests and is ignored in production.

## Pricing

Plans, per-channel rates, the volume rate, the comparison table and the pricing FAQ live in `app/data/pricing.php`. Totals are calculated in `app/Pricing.php` and mirrored in `public/assets/js/app.js`, so change both if the formula changes. Without JavaScript the channel and billing controls submit as a normal `GET` form. No payment is taken: every plan button joins the waitlist with that plan selected. IP addresses are never stored; only a keyed hash is kept for rate limiting.

## Demo content

All interactive demos (calendar, idea board, inbox, analytics, AI chat) run in the browser using the fictional sample workspace in `app/data/demo.php`. Nothing from the demos is sent to the server or written to the database, and each demo is labelled on the page.

## Licenses

- Figtree and Plus Jakarta Sans fonts: SIL Open Font License (`public/assets/fonts/LICENSE-*`)
- Phosphor icons: MIT (`app/icons/ui/LICENSE-phosphor.txt`)
- Simple Icons brand glyphs: CC0 (`app/icons/brands/`). Platform names and logos belong to their owners; atwebtech does not claim affiliation.
