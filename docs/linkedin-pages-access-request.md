# LinkedIn Community Management API: access request answers

App: **AT WEB TECH US Pages** (Client ID `7710vgvxu437j6`), linked to the AT WEB TECH US company page.
Requested tier: **Development**. Use these answers when LinkedIn's access form asks for them.

**Company / legal entity:** AT WEB TECH US
**Website:** https://atwebtech.us
**Privacy policy:** https://atwebtech.us/privacy
**Business email:** info@atwebtech.us

**What does your app do? (use case)**
atwebtech is a social media scheduling workspace for small businesses. A Page admin connects the LinkedIn Company Pages they manage, writes or approves posts in atwebtech, and atwebtech publishes them to the Page at the scheduled time. It can also add the admin's first comment on that post. Nothing is posted unless the admin writes or approves it.

**Which permissions and why**
- `rw_organization_admin`: list the Pages where the signed-in member is an Administrator or Content Administrator, so they can choose which Pages to connect (organizationAcls, organizations).
- `w_organization_social`: publish the admin's scheduled text and image posts as the Page, and post the admin's first comment (ugcPosts, assets, socialActions).
- `r_organization_social`: read the Page's own posts that atwebtech published, so the admin can see they went out.

**Who uses it:** Page admins of small and mid-sized businesses who sign up at atwebtech.us. Each admin only connects Pages they already administer.

**Data handling:** Access tokens are encrypted at rest and used only to publish what the admin scheduled. atwebtech doesn't read messages, doesn't post without the admin's action, and revokes the token when the admin disconnects the Page or deletes their account.

**End-to-end flow for the reviewer**
1. Sign in at https://atwebtech.us, open Channels and choose **LinkedIn Page**.
2. Sign in with LinkedIn and allow the permissions.
3. Pick the Page(s) to connect, then **Finish Connection**.
4. Create a post, choose the Page, schedule it. It is published as the Page at that time.
