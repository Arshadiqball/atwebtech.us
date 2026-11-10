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

## After approval

- Facebook Login for Business: add `pages_read_engagement` and `read_insights` (and `instagram_basic` for Instagram competitors) to configuration `1643682273795398`, then reconnect your Page.
- Link the AT WEB TECH US Page to your Instagram professional account in Meta Business Suite → Settings → Linked accounts. Your admin connection is then used for every customer's Instagram competitor lookups.
- Threads competitors: add `threads-discovery` to `STATS_PERMISSIONS` in the server `.env` and reconnect Threads.
