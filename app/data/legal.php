<?php

declare(strict_types=1);

// DRAFT legal content. Must be reviewed and completed by the site owner and qualified counsel before launch.
// Bracketed placeholders are intentionally left unfilled; do not invent entity details.

return [
    'privacy' => [
        'title' => 'Privacy policy',
        'body' => <<<'HTML'
<p>This policy explains what information atwebtech.us collects, why, and how it is handled. It applies to this marketing website, its interactive demo, the waitlist, the contact form, and a workspace account, including a YouTube channel you choose to connect.</p>
<p><strong>Who we are:</strong> atwebtech.us is operated by [Legal entity name], [Registered address]. You can reach us through the <a href="/contact">contact form</a>.</p>

<h2>Information we collect</h2>
<h3>Waitlist</h3>
<p>When you join the waitlist we store your email address, the optional role you select (for example, creator or agency), which form you used, and the date and time. If you arrived from an ad or an email, we also store the campaign name and source from that link, and the click id when the ad network adds one.</p>
<h3>Contact form</h3>
<p>When you send a message we store your name, email address, chosen topic, your message, and the date and time.</p>
<h3>Technical information</h3>
<p>To protect the forms from abuse we store a one-way keyed hash derived from your IP address (not the address itself) and your browser's user-agent string. We also record form attempts for a short period to apply rate limits.</p>
<h3>Cookies</h3>
<p>This site sets one essential session cookie, used to protect forms against cross-site request forgery, to show the result of a form submission, and to remember the campaign link you arrived from until you close the browser. The site does not use advertising or analytics cookies. [Update this section if analytics are added.]</p>
<h3>Interactive demo</h3>
<p>Demo interactions, such as moving idea cards or typing a reply, run entirely in your browser. They are not sent to or stored on our servers.</p>
<h3>Workspace account</h3>
<p>When you create a workspace account we store your name, email address, and a password that is stored only as a hash. We do not store the password itself.</p>
<h3>YouTube and other Google user data</h3>
<p>If you connect a YouTube channel, you sign in with Google and choose the channel. Google then gives atwebtech that channel’s name and picture, and permission to upload videos you schedule. We store an encrypted token so the upload can run at the time you chose, using the title and description you wrote. We do not delete your videos, reply to comments, read YouTube analytics, or use YouTube data for advertising.</p>
<p>We use this Google user data only to connect the channel you selected and to upload the videos you scheduled. We do not sell it, and we do not share it with anyone except the company that hosts this website, which stores it so the service can run. Use and transfer of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
<p>You can disconnect the channel in your workspace at any time. Disconnecting revokes atwebtech’s access. You can also ask us to delete the stored channel name, picture, and token through the <a href="/contact">contact form</a>.</p>

<h2>How we use information</h2>
<ul>
  <li>To send atwebtech product updates and early-access news to waitlist members.</li>
  <li>To read and reply to messages sent through the contact form.</li>
  <li>To keep the website secure and prevent spam.</li>
  <li>To show a YouTube channel you connected, and to upload videos you scheduled to that channel.</li>
</ul>
<p>We do not sell personal information. [Confirm any service providers, such as hosting or email delivery, that process data on our behalf.]</p>

<h2>Legal basis and retention</h2>
<p>[Owner and counsel to confirm the legal basis for processing in each relevant jurisdiction, and how long waitlist and enquiry records are kept.]</p>

<h2>Your choices and rights</h2>
<p>You can ask us to access, correct, or delete the information we hold about you, or to remove you from the waitlist, by using the <a href="/contact">contact form</a>. [Add jurisdiction-specific rights, such as those under GDPR or US state privacy laws, as applicable.]</p>

<h2>Changes to this policy</h2>
<p>We will update this page if our practices change. [Add effective date on publication.]</p>
HTML,
    ],
    'terms' => [
        'title' => 'Terms of use',
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
