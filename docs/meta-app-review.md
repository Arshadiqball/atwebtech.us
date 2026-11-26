# Meta App Review: atwebtech (app 1437806015169707)

Why: today only people with a role on the Meta app (you) can connect Facebook Pages, Instagram and Threads, and competitor lookups for Instagram, Facebook Pages and Threads are blocked. Approval gives every customer the same access.

Business verification: **done** (A2Z Airport Parking Ltd., verified). Previous approval (Oct 31, 2026): `instagram_business_basic`, `public_profile`.

## Already in the draft submission (App Review → Submissions)

| Request | What atwebtech uses it for |
|---|---|
| `pages_manage_posts` | Publish the posts a customer schedules to Facebook Pages they manage. |
| `pages_read_engagement` | Read the Page's followers, reactions, comments and shares for the customer's Insights. |
| `read_insights` | Read views and reach of the customer's Page posts for Insights. |
| `instagram_business_content_publish` | Publish photos the customer schedules to their Instagram professional account. |
| `instagram_business_manage_insights` | Read views, reach, shares and saves of the customer's Instagram posts for Insights. |
| `instagram_basic` | Read the Instagram professional account linked to the customer's Page; needed for Business Discovery. |
| Instagram Public Content Access | Business Discovery: show public follower counts and recent posts of competitor professional accounts the customer chooses to follow (Competitors page). |

## Still to add (your decision; these widen access across use cases)

- `pages_show_list`: list the Pages a customer manages so they can pick which to connect. **Required** for customers to connect Pages.
- `business_management`: needed by Facebook Login for Business for Pages owned in a Business portfolio.
- Threads use case: `threads_content_publish`, `threads_manage_insights`, `threads_profile_discovery` (competitor lookups on Threads).
- Community inbox (read and reply to comments): `instagram_business_manage_comments`, `pages_read_user_content`, `pages_manage_engagement`, `threads_read_replies`, `threads_manage_replies`. Use: "Customers read comments on their own posts in atwebtech's Community inbox and reply to them." Screencast: Community → a comment → Send reply → the reply on the network. After approval set `COMMUNITY_PERMISSIONS=instagram,threads` and reconnect.

Add them from **Use cases → Customize → Permissions and features → Actions → Add to App Review**, then click **Request** in the dialog.

## For each request Meta asks for

1. **How the app uses it**: copy the text from the table above and add: "atwebtech is a social media scheduling and analytics tool at https://atwebtech.us. Customers sign in, connect their own accounts with Facebook Login for Business or Instagram Login, and choose what to publish. We only read data for accounts the customer connects, and public data of competitors the customer chooses to follow."
2. **Screen recording** (one per permission, or one covering several), showing:
   - Sign in at https://atwebtech.us/login with the reviewer test account.
   - Channels → Connect → Facebook (or Instagram / Threads) → the Meta permission dialog → Allow.
   - `pages_manage_posts` / `instagram_business_content_publish`: New Post → pick the channel → write text, attach a photo → Schedule (or set the time a minute ahead) → show the post live on the Page / Instagram.
   - `pages_read_engagement`, `read_insights`, `instagram_business_manage_insights`: open Insights and the per-post stats.
   - Instagram Public Content Access / `instagram_basic`: Competitors → paste `instagram.com/nike` → Add → the competitor card with followers and top posts.
3. **Test credentials**: create a reviewer login on atwebtech (Sign up with an email you control, e.g. `arshadiqbal.d+metareview@gmail.com`) and put the email and password in the "Testing instructions" field. Add the same steps as the recording.
4. **Data handling questions**: atwebtech stores access tokens encrypted at rest (libsodium XSalsa20-Poly1305), doesn't sell or share data, deletes data on request at https://atwebtech.us/data-deletion, and the privacy policy is https://atwebtech.us/privacy.

## Status of the draft submission (2026-11-25)

Saved in the draft (App Review → Submissions → Next → Allowed usage): the usage description for all seven requests. Business verification is done.

Still missing before Meta lets it be submitted:

- A screen recording uploaded for each of the seven requests.
- Test API calls. Meta shows "0 of 1 API call(s) required" for `pages_manage_posts`, `pages_read_engagement`, `read_insights` and `instagram_basic`. Each needs one successful call made through the app by someone with a role on it; Meta can take up to 24 hours to count it. `instagram_business_content_publish` is already "Completed".
- `pages_show_list` added to the request (Use cases → Manage everything on your Page → Customize → Add to App Review).
- The "I agree" box on each request, the certification boxes on the Renewal tab (`instagram_business_basic`, `public_profile`), the Data handling questions, and Reviewer instructions with a test login.

## Ready-to-paste answers (drafted 2026-11-25)

Why this matters now: the public sees "It looks like this app isn't available. This app needs at least one supported permission" when they click Connect Facebook, because no Page permission has been approved. People with a role on the Meta app are not affected.

Before submitting, add `pages_show_list` to the request. The app lists a customer's Pages with `me/accounts`, which needs it, and Meta will not grant `pages_manage_posts` without `pages_show_list` and `pages_read_engagement` alongside it.

Paste the matching text into each permission's "Tell us how you're using this permission" box. Each answer is written to stand alone, because reviewers read them one at a time.

### pages_show_list

> atwebtech (https://atwebtech.us) is a social media scheduling tool for small businesses, creators and agencies. When a customer clicks Connect on Facebook in Channels, we use pages_show_list to list the Facebook Pages that person manages, so they can choose which Pages to connect to their atwebtech workspace. We store the Page ID, name and profile picture of the Pages they connect and show them in the customer's channel list. We do not read or store Pages the customer does not select in the Facebook dialog. Without this permission a customer cannot connect a Page at all.

### pages_manage_posts

> atwebtech is a social media scheduling tool. Customers write a post in our composer, pick one or more connected Facebook Pages and either publish now or schedule it for a later time. We use pages_manage_posts to publish that post to the Page on the customer's behalf at the chosen time: text posts, link posts, photo posts (one or several photos) and video posts. Every post is written or approved by the customer before it goes out; nothing is published automatically without their action. Customers can see scheduled and published posts in the Publish section, with a link to the live post on Facebook. This saves a small business from logging in to Facebook at the exact time each post should appear.

### pages_read_engagement

> atwebtech shows each customer how their own Facebook Page is doing. We use pages_read_engagement to read the Page's follower count and, for the posts the customer published, the number of reactions, comments and shares. These numbers appear in the Insights section and next to each published post, so the customer can see which posts worked and what times are best to post. We only read this for Pages the customer has connected, we show it only to that customer's workspace, and we do not share or sell it. This permission is also required to publish to the Page with pages_manage_posts.

### read_insights

> atwebtech's Insights section shows a customer the reach of their own Facebook Page posts. We use read_insights to read the views and unique viewers of posts published on the Pages the customer has connected (the post insights metrics for media views and impressions). The customer sees these as totals, per-post figures and a chart over time, and can export them as a report for their own records or their client. We read insights only for connected Pages and show them only inside that customer's workspace.

### instagram_business_content_publish

> atwebtech is a social media scheduling tool. A customer connects their Instagram professional account with Instagram Login, then writes a post in our composer, attaches one or several photos, and publishes now or schedules it. We use instagram_business_content_publish to publish that photo post or carousel to the customer's own Instagram account at the time they chose. The customer writes or approves every post; nothing is published without their action. Published posts appear in the Publish section with a link to the post on Instagram.

### instagram_business_manage_insights

> atwebtech's Insights section shows a customer how their own Instagram posts performed. We use instagram_business_manage_insights to read views, reach, shares and saves for the posts on the Instagram professional account the customer has connected, plus the account's follower count. The customer sees these as totals, per-post figures and best times to post. We read this only for the connected account and show it only inside that customer's workspace.

### instagram_basic and Instagram Public Content Access

> atwebtech has a Competitors section where a customer enters the public Instagram professional accounts they want to compare themselves with. We use Instagram Public Content Access (Business Discovery), with instagram_basic to read the Instagram professional account linked to our own Facebook Page, to show the public follower count and recent public posts of the accounts the customer added. We show this only to that customer, we do not contact those accounts, and we do not collect private data.

### Testing instructions (same text for every permission)

> 1. Go to https://atwebtech.us/login and sign in with the test account below.
> 2. Open Channels and click Connect next to Facebook. Approve the Facebook dialog and select a Page you manage. The Page now appears in the channel list (pages_show_list).
> 3. Open Publish, click New post, choose the Facebook Page, write a short text, attach a photo and click Publish now. Open the link on the published post to see it live on the Page (pages_manage_posts).
> 4. Open Insights. The Page's followers and the post's reactions, comments, shares and views are shown (pages_read_engagement, read_insights).
> 5. Repeat steps 2 to 4 with Instagram: Connect Instagram, publish a photo post, open Insights (instagram_business_content_publish, instagram_business_manage_insights).
> 6. Open Competitors, paste a public Instagram professional account address and click Add. Its follower count and recent posts are shown (Instagram Public Content Access).
>
> Test account: [email] / [password]

Fill in the test account yourself; do not write the password in this file.

### Screen recordings

Meta rejects most requests on the recording, so each one must show the full path with the permission dialog visible:

1. **Connect and publish to a Page** (covers `pages_show_list`, `pages_manage_posts`): log in → Channels → Connect Facebook → the Facebook dialog with the Page picker → the Page in the channel list → New post → publish → the post open on facebook.com.
2. **Page insights** (covers `pages_read_engagement`, `read_insights`): Insights with the Facebook Page selected → the per-post numbers → the same post on Facebook showing matching reactions and comments.
3. **Instagram publish and insights** (covers both `instagram_business_*` requests): Connect Instagram → the Instagram dialog → New post with a photo → the post on instagram.com → Insights.
4. **Competitors** (covers `instagram_basic`, Public Content Access): Competitors → add a public account → the card with followers and posts.

Record in English, at 1080p or higher, with the mouse visible and no cuts between the permission dialog and the feature. Use a login that has a role on the Meta app, since public logins are blocked until approval.

## After approval

- Facebook Login for Business: add `pages_read_engagement` and `read_insights` (and `instagram_basic` for Instagram competitors) to configuration `1643682273795398`, then reconnect your Page.
- Link the AT WEB TECH US Page to your Instagram professional account in Meta Business Suite → Settings → Linked accounts. Your admin connection is then used for every customer's Instagram competitor lookups.
- Threads competitors: add `threads-discovery` to `STATS_PERMISSIONS` in the server `.env` and reconnect Threads.
