<?php

declare(strict_types=1);

return [
    'creators' => [
        'label' => 'Creators',
        'icon' => 'user',
        'panel' => 'lavender',
        'title' => 'A steadier rhythm for the work you share.',
        'intro' => 'When you are the writer, photographer, editor, and community manager, a simple plan matters more than a complicated tool.',
        'challenges' => [
            ['title' => 'Ideas arrive at awkward times', 'text' => 'Capture them on the idea board the moment they appear, then shape them when you have time to sit down.'],
            ['title' => 'Consistency is hard to keep up', 'text' => 'A weekly calendar shows quiet days early, so you can batch content instead of posting in bursts.'],
            ['title' => 'Comments pile up', 'text' => 'A single inbox view makes it easier to find questions from your audience and answer them thoughtfully.'],
        ],
        'features' => ['content-planner', 'publishing', 'link-in-bio', 'ai-assistant'],
        'meta_title' => 'atwebtech for creators',
        'meta_description' => 'Plan content, capture ideas, and keep a steady posting rhythm as a creator with atwebtech.',
    ],
    'businesses' => [
        'label' => 'Small Businesses',
        'icon' => 'storefront',
        'panel' => 'yellow',
        'title' => 'Show up consistently, even with a small team.',
        'intro' => 'Social media is one job among many. atwebtech is designed to make it a manageable weekly routine rather than a daily scramble.',
        'challenges' => [
            ['title' => 'Little time to plan', 'text' => 'Set aside one session a week to plan posts across channels, then let the calendar keep you on track.'],
            ['title' => 'Different channels, different audiences', 'text' => 'Adapt one announcement for each channel without rewriting it from scratch every time.'],
            ['title' => 'Customer questions in public', 'text' => 'Keep comments from customers in one place so questions about hours, products, and orders get answered.'],
        ],
        'features' => ['publishing', 'community', 'analytics', 'link-in-bio'],
        'meta_title' => 'atwebtech for small businesses',
        'meta_description' => 'A manageable weekly routine for small business social media: plan posts, adapt them by channel, and keep up with customer comments.',
    ],
    'agencies' => [
        'label' => 'Agencies',
        'icon' => 'briefcase',
        'panel' => 'teal',
        'title' => 'Keep every client plan clear and organized.',
        'intro' => 'Managing several brands means several calendars, voices, and approval processes. atwebtech is designed to keep them separate and easy to review.',
        'challenges' => [
            ['title' => 'Many calendars to juggle', 'text' => 'Plan each client in its own workspace with its own channels, so posts never land in the wrong place.'],
            ['title' => 'Approvals slow things down', 'text' => 'Keep client feedback and sign-off next to the post, instead of across emails and chat threads.'],
            ['title' => 'Reporting takes too long', 'text' => 'Clear reporting views are designed to make monthly client summaries quicker to prepare.'],
        ],
        'features' => ['collaboration', 'publishing', 'analytics', 'community'],
        'meta_title' => 'atwebtech for agencies',
        'meta_description' => 'Plan, review, and report on social content for several clients with clear workspaces and approvals.',
    ],
    'marketing-teams' => [
        'label' => 'Marketing Teams',
        'icon' => 'megaphone',
        'panel' => 'coral',
        'title' => 'Plan campaigns with shared visibility.',
        'intro' => 'When several people contribute to social content, everyone needs to see the same plan, the same drafts, and the same status.',
        'challenges' => [
            ['title' => 'Campaigns span many channels', 'text' => 'Group posts by campaign in the calendar and adapt each one for the channel it is going to.'],
            ['title' => 'Work is spread across people', 'text' => 'A shared idea board shows who is drafting what, and what is waiting for review.'],
            ['title' => 'Results need to be shared', 'text' => 'Simple charts and comparisons make it easier to explain what worked to the wider team.'],
        ],
        'features' => ['publishing', 'collaboration', 'content-planner', 'analytics'],
        'meta_title' => 'atwebtech for marketing teams',
        'meta_description' => 'Plan social campaigns across channels with shared calendars, drafts, approvals, and reporting views.',
    ],
];
