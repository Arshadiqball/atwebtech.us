<?php

declare(strict_types=1);

// Workspace navigation and settings.
return [
    // Caption limits used by the post composer, in characters.
    'limits' => [
        'instagram' => 2200, 'facebook' => 5000, 'tiktok' => 2200, 'linkedin' => 3000, 'youtube' => 5000,
        'pinterest' => 500, 'threads' => 500, 'x' => 280, 'google-business-profile' => 1500,
    ],
    'post_max' => 5000,
    // Suggested posting times for the queue (24h, in the user's time zone).
    'queue_times' => ['09:00', '13:00', '17:00'],

    'sections' => [
        'home' => ['label' => 'Home', 'icon' => 'house', 'href' => '/app'],
        'create' => ['label' => 'Create', 'icon' => 'lightbulb', 'href' => '/app/create'],
        'publish' => ['label' => 'Publish', 'icon' => 'calendar-blank', 'href' => '/app/publish', 'count' => true],
        'community' => ['label' => 'Community', 'icon' => 'chats-circle', 'href' => '/app/community'],
        'insights' => ['label' => 'Insights', 'icon' => 'chart-bar', 'href' => '/app/insights'],
        'channels' => [
            'label' => 'Channels', 'icon' => 'plus', 'href' => '/app/channels', 'nav' => false,
            'title' => 'Connect a channel',
            'text' => 'YouTube and a personal LinkedIn Profile can be connected and published to. Company Pages and the other networks are not available yet.',
            'demo' => null, 'feature' => '/integrations',
        ],
    ],
];
