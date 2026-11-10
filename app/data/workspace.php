<?php

declare(strict_types=1);

// Workspace navigation and settings.
return [
    // Caption limits used by the post composer, in characters.
    'limits' => [
        'instagram' => 2200, 'facebook' => 5000, 'tiktok' => 2200, 'linkedin' => 3000, 'youtube' => 5000,
        'pinterest' => 500, 'threads' => 500, 'bluesky' => 300, 'mastodon' => 500, 'google-business-profile' => 1500,
    ],
    'post_max' => 5000,
    // Suggested posting times for the queue (24h, in the user's time zone).
    'queue_times' => ['09:00', '13:00', '17:00'],

    'sections' => [
        'home' => ['label' => 'Home', 'icon' => 'house', 'href' => '/app'],
        'notifications' => ['label' => 'Notifications', 'icon' => 'bell', 'href' => '/app/notifications'],
        'create' => ['label' => 'Create', 'icon' => 'lightbulb', 'href' => '/app/create', 'group' => 'Content'],
        'publish' => ['label' => 'Publish', 'icon' => 'calendar-blank', 'href' => '/app/publish', 'count' => true],
        'library' => ['label' => 'Media Library', 'icon' => 'image', 'href' => '/app/library'],
        'community' => ['label' => 'Community', 'icon' => 'chats-circle', 'href' => '/app/community', 'group' => 'Engage'],
        'bio' => ['label' => 'Link in Bio', 'icon' => 'link-simple', 'href' => '/app/link-in-bio'],
        'insights' => ['label' => 'Insights', 'icon' => 'chart-bar', 'href' => '/app/insights', 'group' => 'Grow'],
        'competitors' => ['label' => 'Competitors', 'icon' => 'chart-line-up', 'href' => '/app/competitors'],
        'automations' => ['label' => 'Automations', 'icon' => 'lightning', 'href' => '/app/automations'],
        'team' => ['label' => 'Team', 'icon' => 'user-plus', 'href' => '/app/team', 'nav' => false],
        'channels' => [
            'label' => 'Channels', 'icon' => 'plus', 'href' => '/app/channels', 'nav' => false,
            'title' => 'Connect a channel',
            'text' => 'Instagram, Facebook Pages, LinkedIn, YouTube, Pinterest, Threads, Bluesky, and Mastodon can be connected and published to. TikTok connects now, with publishing coming soon.',
            'demo' => null, 'feature' => '/integrations',
        ],
    ],
];
