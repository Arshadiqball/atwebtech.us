<?php

declare(strict_types=1);

// Answers must reflect what is actually implemented. Update them as the product changes.

return [
    [
        'q' => 'What is atwebtech?',
        'a' => '<p>atwebtech is a social media management workspace for creators and small teams. Plan ideas, write posts for several channels, and schedule them on a calendar with a posting schedule for each channel, all in one place.</p>',
    ],
    [
        'q' => 'Who is it designed for?',
        'a' => '<p>Creators, small businesses, agencies, and marketing teams: the people who plan, write, and look after social content day to day. See the <a href="/solutions">solutions pages</a> for how it fits each group.</p>',
    ],
    [
        'q' => 'Is there a free plan?',
        'a' => '<p>Yes. <a href="/signup">Create a free account</a> and connect up to 3 channels, with up to 10 scheduled posts per channel and 100 ideas. No credit card required. ' . (Billing::enabled() ? 'Upgrade to Essentials or Team any time for unlimited posts and ideas and more channels.' : 'Paid plans with higher limits are coming soon.') . '</p>',
    ],
    [
        'q' => 'Can I try it before signing up?',
        'a' => '<p>Yes. The <a href="/demo">interactive demo</a> lets you try the calendar, idea board, inbox, analytics views, and an AI workflow using sample data. It runs entirely in your browser. Nothing is saved, published, or sent.</p>',
    ],
    [
        'q' => 'Which integrations are available?',
        'a' => '<p>You can publish to LinkedIn personal profiles and YouTube today. Instagram, Facebook, TikTok, Pinterest, Threads, X, and Google Business Profile are coming soon. The <a href="/integrations">integrations page</a> shows the current status of each one.</p>',
    ],
    [
        'q' => 'How do I hear about new features?',
        'a' => '<p><a href="/join">Sign up for product updates</a> to get news about new channels and features by email. The <a href="/integrations">integrations page</a> always shows which channels are available.</p>',
    ],
    [
        'q' => 'How can I contact the team?',
        'a' => '<p>Use the <a href="/contact">contact form</a>. Your message is saved securely and the team will reply by email.</p>',
    ],
];
