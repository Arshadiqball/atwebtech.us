# App Review notes (paste into App Store Connect, App Review Information, Notes)

The text between the lines is what goes into the Notes field (under 4,000 characters). Attach the screen recording in the same section, or put its link on the first line.

---

SCREEN RECORDING: attached to this submission (recorded on a physical iPhone, latest iOS). It shows: launch, account registration, login, the main flow, reporting and blocking a comment, the plan screen, and account deletion.

1) PURPOSE AND AUDIENCE
atwebtech is a social media scheduling tool for small businesses, creators and agencies. Problem: posting by hand to several networks takes time and posts get missed. Value: the user writes a post once, schedules it, and atwebtech publishes it on time to the social accounts the user has connected. It also gathers comments from those accounts into one inbox and shows basic statistics.

2) HOW TO USE IT
- Log in with the demo account in the Sign-In fields (or tap "Create an account" to register a new one with any email).
- Home: what needs attention and this week's numbers. "New post" opens the composer.
- Publish: Queue, Drafts, Approvals, Failed, Sent. Tap "New post", pick a channel, write text, optionally add a photo, pick a time, tap Schedule or Save draft.
- Community: comments from the connected accounts. Reply, Mark done. The "..." menu on each comment has "Report comment" and "Block [person]". A "Blocked" chip lists blocked people with Unblock.
- Insights: statistics for the connected accounts.
- More: Channels, Media Library, Automations, Account, Your plan.
- Account deletion: More > Account > "Delete my account" (asks for the password and the word DELETE). It deletes the account and all its data at once.
- Connecting a new social account opens that network's own sign-in page in a browser (OAuth). The demo account already has accounts connected, so this is not needed to review.

3) USER-GENERATED CONTENT
The only content from other people is comments left on the user's own connected social accounts, fetched through each network's official API. Every comment can be reported (it is removed from the inbox immediately and sent to our team, who review reports within 24 hours) and its author can be blocked (their comments no longer appear). Contact: info@atwebtech.us.

4) PAID FEATURES
The iPhone/iPad app has no purchases and no links or buttons to buy anything. Everything shown in the recording is free (the Free plan covers up to 3 channels). Paid plans exist for our web customers and are bought on our website outside the app; the app only shows which plan an account already has ("Your plan"). This follows guideline 3.1.3(b) (multiplatform services).

5) EXTERNAL SERVICES
- Our own server (atwebtech.us): accounts and login (email + password, optional two-factor), all app data.
- Social networks' official APIs, with the account owner's authorization: Instagram, Facebook and Threads (Meta), LinkedIn, YouTube (Google), Pinterest, Bluesky, Mastodon. Used to publish the user's posts and read comments and statistics of their own accounts.
- OpenAI API: optional AI writing help (draft a post, suggest a reply). The user starts it and edits the result.
- Firebase Cloud Messaging / APNs: push notifications (a post failed, an approval is waiting).
- Stripe: payments on the website only, never in the app.

6) REGIONS
The app works the same in every region where it is available. It is not offered in China mainland.

7) REGULATED CONTENT
The app is not in a regulated industry. It contains no third-party media. Comments and statistics come from the user's own social accounts through the networks' official APIs under their developer terms, after the user authorizes access.

---

## Recording checklist (for you)

Record on a real iPhone with the TestFlight build, starting before you tap the app icon:

1. Launch the app (splash, then the login screen).
2. Tap "Create an account", register a new test address, and show the empty Home.
3. More > Account > "Delete my account": enter the password, type DELETE, confirm. The app returns to the login screen.
4. Log in with the demo account.
5. Home, then "New post": pick a channel, type a line, Save draft. Show Publish > Drafts.
6. Community: open the "..." menu on a comment, show "Report comment" and "Block". (Tap Cancel or pick one on a comment you don't need.)
7. Insights.
8. More > Your plan.
