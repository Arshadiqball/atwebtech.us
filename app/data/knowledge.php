<?php

declare(strict_types=1);

// Default knowledge for Sarah, the website chatbot. Edit these lines to change what she knows.
// Product, pricing, guides, contact details and channel status are added automatically by Chatbot::knowledge().
// Only write facts that are true today; Sarah repeats them to visitors.

return [
    'company' => [
        'atwebtech is a social media workspace built for the people who plan, write, and look after social content, often alongside many other jobs: creators, small businesses, agencies, and marketing teams.',
        'atwebtech.us is operated by atwebtech, based in Tucson, Arizona, United States.',
        'Why it exists: social work tends to be scattered across notes apps, documents, spreadsheets and several social apps. atwebtech brings planning, publishing, comments and reporting into one calm workspace, so it is easy to see what is planned, in progress, and next.',
        'The product is new and still growing. Some features shown on the site are planned rather than live, and they are labelled that way.',
    ],
    'branding' => [
        'The name is always written in lowercase: atwebtech.',
        'Voice: calm, clear, practical and friendly. Plain words, short sentences, no hype.',
        'Principles: clarity first; organized by default; honest about status (demos are labelled as demos, planned features as planned); made for small teams.',
        'Never use exaggerated claims such as “guaranteed growth”, “best in the world”, or invented customer results.',
    ],
    'policies' => [
        'Privacy: atwebtech stores only what each form or feature needs (for example the email for the waitlist, or name, email and message for the contact form). Details are in the [privacy policy](/privacy).',
        'Data deletion: email the contact address or use [the contact form](/contact) with the subject “Delete my data” and the account email. Workspace account, connected channel records, posts and uploads are deleted, and the visitor is emailed when it is done. See [data deletion](/data-deletion).',
        'Disconnecting a channel inside the workspace revokes atwebtech’s access to it.',
        'The [terms of use](/terms) are still a draft.',
    ],
    // Extra questions and answers. Add your own as ['Question?', 'Answer.'].
    'faq' => [
        ['Do I need a credit card to start?', 'No. A free account needs only an email address and password, and paid plans can’t be bought online yet.'],
        ['Can I try it without signing up?', 'Yes. The [interactive demo](/demo) runs in the browser with sample data. Nothing is saved or published.'],
        ['Can I talk to a person?', 'Yes. Use [the contact form](/contact) and the team replies by email, or [book a demo](/contact?topic=demo).'],
        ['Does atwebtech take bookings, run ads, or build websites?', 'No. atwebtech is a social media workspace. It helps a business show up consistently on social media, which can support bookings and sales indirectly.'],
        ['Is my social account password stored?', 'No. Channels connect through each network’s official sign-in, and atwebtech stores only an encrypted access token, which is revoked when you disconnect.'],
    ],
];
