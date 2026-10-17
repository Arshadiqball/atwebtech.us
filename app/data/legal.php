<?php

declare(strict_types=1);

// DRAFT legal content. Must be reviewed and completed by the site owner and qualified counsel before launch.
// Bracketed placeholders are intentionally left unfilled; do not invent entity details.

return [
    'privacy' => [
        'title' => 'Privacy policy',
        'updated' => '16 October 2026',
        'body' => <<<'HTML'
<p>This policy explains what information atwebtech collects, why it is collected, how it is used, how long it is kept, and how you can delete it. It covers the website at https://atwebtech.us/, the waitlist, the contact form, a workspace account, and a YouTube channel you choose to connect.</p>
<p><strong>Who we are:</strong> atwebtech.us is operated by atwebtech, 3672 S 16th Ave, Tucson, AZ 85713, US. You can email <a href="mailto:info@atwebtech.us">info@atwebtech.us</a> or use the <a href="/contact">contact form</a>.</p>

<h2>Information we collect</h2>
<h3>Waitlist</h3>
<p>When you join the waitlist we store your email address, the optional role you select (for example, creator or agency), which form you used, and the date and time. If you arrived from an ad or an email, we also store the campaign name and source from that link, and the click id when the ad network adds one.</p>
<h3>Contact form</h3>
<p>When you send a message we store your name, email address, chosen topic, your message, and the date and time.</p>
<h3>Workspace account</h3>
<p>When you create a workspace account we store your name, email address, and a password that is stored only as a hash. We do not store the password itself.</p>
<h3>YouTube and other Google user data</h3>
<p>If you connect a YouTube channel, you sign in on Google’s own screen and choose the channel. atwebtech then receives:</p>
<ul>
  <li>The channel’s name, picture, and channel id, so we can show that channel in your workspace.</li>
  <li>Permission to upload a video you scheduled, using the video file, title, and description you provided, at the time you chose.</li>
  <li>An access token and a refresh token from Google. These are encrypted before they are stored, and they are used only to keep that connection working.</li>
</ul>
<p>atwebtech does not read your Gmail, Google Drive, contacts, or YouTube analytics. It does not delete your videos, reply to comments, or change your channel settings. It does not use Google user data for advertising, and it does not sell Google user data.</p>
<p>atwebtech's use and transfer to any other app of information received from Google APIs will adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
<h3>Technical information</h3>
<p>To protect the forms from abuse we store a one-way keyed hash derived from your IP address (not the address itself) and your browser's user-agent string. We also record form attempts for a short period to apply rate limits.</p>
<h3>Cookies</h3>
<p>This site sets one essential session cookie. It protects forms against cross-site request forgery, shows the result of a form submission, and remembers the campaign link you arrived from until you close the browser. The site does not use advertising or analytics cookies.</p>
<h3>Interactive demo</h3>
<p>Demo interactions, such as moving idea cards or typing a reply, run entirely in your browser. They are not sent to or stored on our servers.</p>

<h2>How we use information</h2>
<ul>
  <li>To send atwebtech product updates and early-access news to waitlist members.</li>
  <li>To read and reply to messages sent through the contact form.</li>
  <li>To run your workspace account and show a YouTube channel you connected.</li>
  <li>To upload videos you scheduled to that YouTube channel.</li>
  <li>To keep the website secure and prevent spam.</li>
</ul>
<p>We do not sell personal information. The website host stores this information so the site can run, and the email provider delivers messages you asked us to send. They process it only for those purposes.</p>

<h2>How long we keep it</h2>
<p>Waitlist and contact records are kept until you ask us to delete them, or until we no longer need them to reply to you or to send the updates you requested. A connected YouTube channel, its name and picture, and its encrypted tokens are kept until you disconnect the channel or ask us to delete them. A video file you attached to a scheduled post is kept until the post is sent or you delete the post. Security logs are kept only for a short period.</p>

<h2>Your choices and rights</h2>
<p>You can disconnect a connected channel in your workspace at any time. Disconnecting revokes atwebtech’s access to that channel. You can ask us to access, correct, or delete the information we hold about you, including your account, waitlist entry, messages, channel name, picture, and tokens, by emailing <a href="mailto:info@atwebtech.us">info@atwebtech.us</a> or using the <a href="/contact">contact form</a>. The steps are also on the <a href="/data-deletion">data deletion</a> page.</p>

<h2>Changes to this policy</h2>
<p>We will update this page if our practices change. The date at the top of this page is the date of the latest change.</p>
HTML,
    ],
    'deletion' => [
        'title' => 'Data deletion',
        'updated' => '17 October 2026',
        'body' => <<<'HTML'
<p>You can ask atwebtech to delete the personal information we hold about you. This page explains how. It applies to your workspace account, the waitlist, contact messages, and information received when you connect a channel, including data from Google, YouTube, Facebook, Instagram, and Threads.</p>
<p>atwebtech.us is operated by atwebtech, 3672 S 16th Ave, Tucson, AZ 85713, US.</p>

<h2>How to ask</h2>
<p>Email <a href="mailto:info@atwebtech.us">info@atwebtech.us</a> or use the <a href="/contact">contact form</a>. Use the subject “Delete my data” and include the email address on your atwebtech account. If you connected a channel, include the account or Page name so we can find the right record.</p>

<h2>What we delete</h2>
<ul>
  <li>Your workspace account, including your name, email address, and password hash.</li>
  <li>Connected channel names, pictures, and the encrypted access tokens stored for those channels.</li>
  <li>Posts, drafts, and uploaded files saved in your workspace.</li>
  <li>Your waitlist entry and contact messages, when you ask for those to be removed.</li>
</ul>
<p>Disconnecting a channel in the workspace revokes atwebtech’s access to that channel. A deletion request removes the stored record as well.</p>

<h2>After the request</h2>
<p>We delete the information described above and email you when it is done. We may keep a short security log that does not include the content of your account, and we may keep a record of the deletion request itself.</p>
<p>More detail is in the <a href="/privacy">privacy policy</a>.</p>
HTML,
    ],
    'terms' => [
        'title' => 'Terms of use',
        'draft' => true,
        'body' => <<<'HTML'
<p>These terms govern your use of the atwebtech.us website. By using the site you agree to them.</p>
<p><strong>Operator:</strong> [Legal entity name], [Registered address].</p>

<h2>About this website</h2>
<p>atwebtech is a product in development. This website describes planned features and includes interactive demonstrations that use sample data. Demonstrations do not connect to social accounts, publish content, send messages, or produce live AI results.</p>
<p>Descriptions of features, integrations, and timelines reflect current plans and may change. Nothing on this site is an offer to sell or a commitment to deliver a particular feature.</p>

<h2>Waitlist</h2>
<p>Joining the waitlist does not create an account, a purchase, or a guarantee of access. [Owner to confirm any early-access terms.]</p>

<h2>Acceptable use</h2>
<ul>
  <li>Do not attempt to disrupt, overload, or gain unauthorised access to the site.</li>
  <li>Do not submit unlawful, abusive, or misleading content through forms.</li>
  <li>Do not send passwords, payment details, or other sensitive information through the contact form.</li>
</ul>

<h2>Content and trademarks</h2>
<p>Site content, design, and the atwebtech name and logo belong to [Legal entity name] unless stated otherwise. Social platform names and logos belong to their respective owners and are used only to identify planned integrations; their use does not imply partnership or endorsement.</p>

<h2>Guides and resources</h2>
<p>Articles are general information, not professional advice.</p>

<h2>Disclaimers and liability</h2>
<p>[Owner and counsel to provide disclaimer, limitation of liability, and governing law clauses appropriate to the operating jurisdiction.]</p>

<h2>Changes</h2>
<p>We may update these terms. [Add effective date on publication.]</p>
HTML,
    ],
];
