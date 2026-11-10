<?php

declare(strict_types=1);

// Status is resolved at runtime from INTEGRATIONS_AVAILABLE; anything not listed there is "planned".
// "icon" is a Simple Icons slug (app/icons/brands) or "text:<label>" / "ui:<phosphor-name>" for marks without a bundled glyph.
// "accounts" is the account type shown in the Connect a New Channel window.

return [
    ['slug' => 'instagram', 'name' => 'Instagram', 'icon' => 'instagram', 'color' => '#E4405F', 'formats' => 'Photo posts', 'accounts' => 'Business or Creator'],
    ['slug' => 'facebook', 'name' => 'Facebook', 'icon' => 'facebook', 'color' => '#0866FF', 'formats' => 'Page posts with text, photos, or video', 'accounts' => 'Page'],
    ['slug' => 'tiktok', 'name' => 'TikTok', 'icon' => 'tiktok', 'color' => '#111111', 'formats' => 'Short video', 'accounts' => 'Business or Personal'],
    ['slug' => 'linkedin', 'name' => 'LinkedIn', 'icon' => 'text:in', 'color' => '#0A66C2', 'formats' => 'Profile posts with text or a photo', 'accounts' => 'Profile'],
    ['slug' => 'youtube', 'name' => 'YouTube', 'icon' => 'youtube', 'color' => '#FF0000', 'formats' => 'Videos and Shorts', 'accounts' => 'Channel'],
    ['slug' => 'pinterest', 'name' => 'Pinterest', 'icon' => 'pinterest', 'color' => '#BD081C', 'formats' => 'Photo Pins', 'accounts' => 'Business or Profile'],
    ['slug' => 'threads', 'name' => 'Threads', 'icon' => 'threads', 'color' => '#111111', 'formats' => 'Text and photo posts', 'accounts' => 'Profile'],
    ['slug' => 'bluesky', 'name' => 'Bluesky', 'icon' => 'bluesky', 'color' => '#1185FE', 'formats' => 'Text and up to 4 photos', 'accounts' => 'Profile'],
    ['slug' => 'mastodon', 'name' => 'Mastodon', 'icon' => 'mastodon', 'color' => '#6364FF', 'formats' => 'Text, up to 4 photos, or a video', 'accounts' => 'Any server'],
    ['slug' => 'google-business-profile', 'name' => 'Google Business Profile', 'icon' => 'ui:storefront', 'color' => '#1A73E8', 'formats' => 'Updates, offers, events', 'accounts' => 'Profile'],
];
