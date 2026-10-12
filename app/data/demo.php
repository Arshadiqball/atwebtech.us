<?php

declare(strict_types=1);

// Illustrative sample data for product demonstrations only.
// Lives in code, is never written to the database, and is not tied to any real account.

$series = static function (int $count, float $start, float $end, int $seed, float $wobble): array {
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $t = $count > 1 ? $i / ($count - 1) : 0;
        $base = $start + ($end - $start) * (0.15 * $t + 0.85 * $t * $t * (3 - 2 * $t));
        $noise = sin(($i + 1) * 1.7 + $seed) * $wobble + cos(($i + 3) * 0.6 + $seed * 2) * $wobble * 0.6;
        $out[] = (int) round($base + $noise);
    }
    $out[0] = (int) $start;
    $out[$count - 1] = (int) $end;
    return $out;
};

$bars = static function (int $count, float $min, float $max, int $seed): array {
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $wave = (sin($i * 0.9 + $seed) + 1) / 2 * 0.55 + (cos($i * 2.3 + $seed) + 1) / 2 * 0.3 + ($i / max(1, $count - 1)) * 0.15;
        $out[] = (int) round($min + ($max - $min) * $wave);
    }
    return $out;
};

return [
    'workspace' => 'Marigold Ceramics',
    'channels' => [
        'instagram' => ['name' => 'Instagram', 'short' => 'IG'],
        'tiktok' => ['name' => 'TikTok', 'short' => 'TT'],
        'linkedin' => ['name' => 'LinkedIn', 'short' => 'in'],
        'facebook' => ['name' => 'Facebook', 'short' => 'FB'],
        'pinterest' => ['name' => 'Pinterest', 'short' => 'P'],
        'threads' => ['name' => 'Threads', 'short' => '@'],
    ],
    'week' => [
        'label' => 'Oct 12 to 18',
        'days' => [['Mon', 12], ['Tue', 13], ['Wed', 14], ['Thu', 15], ['Fri', 16], ['Sat', 17], ['Sun', 18]],
    ],
    'posts' => [
        ['id' => 'p1', 'day' => 0, 'time' => '9:00 AM', 'channel' => 'instagram', 'status' => 'scheduled', 'title' => 'New speckled mug colors', 'text' => 'Three new speckled glazes, made for slow autumn mornings. Sage, oat, or terracotta: which one is yours?', 'image' => 'mugs'],
        ['id' => 'p2', 'day' => 0, 'time' => '12:30 PM', 'channel' => 'linkedin', 'status' => 'draft', 'title' => 'Lessons from our first pop-up', 'text' => 'We spent a weekend selling at a neighborhood market. Here are four things we will do differently next time.', 'image' => null],
        ['id' => 'p3', 'day' => 1, 'time' => '8:15 AM', 'channel' => 'tiktok', 'status' => 'scheduled', 'title' => 'Throwing a mug in 30 seconds', 'text' => 'From a lump of clay to a finished mug shape, sped up. Sound on for the wheel.', 'image' => 'wheel'],
        ['id' => 'p4', 'day' => 1, 'time' => '5:00 PM', 'channel' => 'facebook', 'status' => 'scheduled', 'title' => 'Thursday open studio', 'text' => 'Our doors are open Thursday evening. Come see the kilns, meet the makers, and try the wheel.', 'image' => null],
        ['id' => 'p5', 'day' => 2, 'time' => '10:00 AM', 'channel' => 'pinterest', 'status' => 'draft', 'title' => 'Autumn glaze palette', 'text' => 'A saved board of the colors inspiring this season: moss, oat, rust, and smoke.', 'image' => null],
        ['id' => 'p6', 'day' => 2, 'time' => '1:00 PM', 'channel' => 'threads', 'status' => 'scheduled', 'title' => 'Matte or glossy?', 'text' => 'Settling a studio debate. Matte or glossy glaze on a morning mug? Reply with your pick.', 'image' => null],
        ['id' => 'p7', 'day' => 3, 'time' => '9:30 AM', 'channel' => 'instagram', 'status' => 'scheduled', 'title' => 'Behind the wheel', 'text' => 'Every piece starts here. A quiet look at shaping this week\'s batch of bowls.', 'image' => 'wheel'],
        ['id' => 'p8', 'day' => 3, 'time' => '4:00 PM', 'channel' => 'linkedin', 'status' => 'scheduled', 'title' => 'Wholesale for cafés', 'text' => 'We now take small wholesale orders for independent cafés. Here is how the process works.', 'image' => 'latte'],
        ['id' => 'p9', 'day' => 4, 'time' => '11:00 AM', 'channel' => 'facebook', 'status' => 'draft', 'title' => 'Weekend market reminder', 'text' => 'Find us at the Saturday market from 9 until 2. Seconds table included.', 'image' => null],
        ['id' => 'p10', 'day' => 4, 'time' => '6:00 PM', 'channel' => 'instagram', 'status' => 'scheduled', 'title' => 'Planter restock', 'text' => 'The speckled planters are back. Pictured with a very happy monstera.', 'image' => 'plants'],
        ['id' => 'p11', 'day' => 5, 'time' => '10:00 AM', 'channel' => 'tiktok', 'status' => 'draft', 'title' => 'Packing an order', 'text' => 'How we wrap a set of four mugs so they arrive in one piece.', 'image' => null],
        ['id' => 'p12', 'day' => 6, 'time' => '7:00 PM', 'channel' => 'threads', 'status' => 'draft', 'title' => 'Week in the studio', 'text' => 'Two kiln loads, one cracked bowl, and a new glaze we cannot stop looking at.', 'image' => null],
    ],
    // Day of month => channels with posts, for the October month view.
    'month' => [
        'label' => 'October',
        'first_weekday' => 3, // 0 = Monday; October 1 falls on a Thursday in this sample
        'days' => 31,
        'today' => 12,
        'posts' => [
            1 => ['instagram'], 2 => ['facebook', 'threads'], 5 => ['instagram', 'linkedin'], 6 => ['tiktok'], 7 => ['pinterest'],
            8 => ['instagram', 'facebook'], 9 => ['threads'], 12 => ['instagram', 'linkedin'], 13 => ['tiktok', 'facebook'],
            14 => ['pinterest', 'threads'], 15 => ['instagram', 'linkedin'], 16 => ['facebook', 'instagram'], 17 => ['tiktok'],
            18 => ['threads'], 20 => ['instagram'], 21 => ['linkedin', 'tiktok'], 22 => ['facebook'], 23 => ['instagram', 'pinterest'],
            26 => ['instagram', 'threads'], 27 => ['tiktok'], 28 => ['linkedin'], 29 => ['instagram', 'facebook'], 30 => ['pinterest'],
        ],
    ],
    'ideas' => [
        'ideas' => [
            ['title' => 'Morning set collab with Hollow Oak Bakery', 'note' => 'Mug and sourdough bundle. Shoot at the bakery counter.', 'tags' => ['Collab'], 'image' => 'bread'],
            ['title' => 'Café spotlight: who uses our cups', 'note' => 'Ask three wholesale cafés for a photo and a quote.', 'tags' => ['Series'], 'image' => 'latte'],
            ['title' => 'Glaze test results', 'note' => 'Carousel of six test tiles with the recipe names.', 'tags' => ['Carousel'], 'image' => null],
        ],
        'drafting' => [
            ['title' => 'Studio tour reel', 'note' => 'Shot list: door, shelves, wheel, kiln, packing table.', 'tags' => ['Reel'], 'image' => 'wheel'],
            ['title' => 'How to care for stoneware', 'note' => 'Dishwasher, microwave, and oven notes in plain language.', 'tags' => ['Guide'], 'image' => null],
        ],
        'review' => [
            ['title' => 'Autumn collection launch', 'note' => 'Caption v3 ready. Needs final photo choice.', 'tags' => ['Launch'], 'image' => 'mugs'],
            ['title' => 'Workshop sign-ups', 'note' => 'Two dates in November, eight seats each.', 'tags' => ['Event'], 'image' => null],
        ],
    ],
    'conversations' => [
        ['id' => 'c1', 'name' => 'Priya Raman', 'initials' => 'PR', 'channel' => 'instagram', 'unread' => true, 'tag' => 'Question', 'time' => '9:42 AM', 'post' => 'New speckled mug colors',
            'messages' => [['from' => 'them', 'text' => 'These are lovely. Is the sage one dishwasher safe?']]],
        ['id' => 'c2', 'name' => 'Tomasz Wójcik', 'initials' => 'TW', 'channel' => 'facebook', 'unread' => true, 'tag' => 'Question', 'time' => '9:10 AM', 'post' => 'Thursday open studio',
            'messages' => [['from' => 'them', 'text' => 'Do I need to book for Thursday, or can we just turn up with the kids?']]],
        ['id' => 'c3', 'name' => 'Lucía Herrera', 'initials' => 'LH', 'channel' => 'instagram', 'unread' => true, 'tag' => 'Feedback', 'time' => 'Yesterday', 'post' => 'Planter restock',
            'messages' => [['from' => 'them', 'text' => 'My planter arrived and it is perfect. Any chance of a bigger size for a fiddle leaf fig?']]],
        ['id' => 'c4', 'name' => 'Daniel Kim', 'initials' => 'DK', 'channel' => 'tiktok', 'unread' => false, 'tag' => 'Question', 'time' => 'Yesterday', 'post' => 'Throwing a mug in 30 seconds',
            'messages' => [
                ['from' => 'them', 'text' => 'How long does one piece take from the wheel to the kiln?'],
                ['from' => 'us', 'text' => 'About two weeks. It needs to dry slowly before the first firing.'],
                ['from' => 'them', 'text' => 'That is so much longer than I expected. Thanks!'],
            ]],
        ['id' => 'c5', 'name' => 'Ama Owusu', 'initials' => 'AO', 'channel' => 'threads', 'unread' => false, 'tag' => 'Reply', 'time' => 'Mon', 'post' => 'Matte or glossy?',
            'messages' => [['from' => 'them', 'text' => 'Matte, always. The texture is the best part of holding a mug.']]],
        ['id' => 'c6', 'name' => 'Hollow Oak Bakery', 'initials' => 'HO', 'channel' => 'linkedin', 'unread' => false, 'tag' => 'Partner', 'time' => 'Mon', 'post' => 'Wholesale for cafés',
            'messages' => [
                ['from' => 'them', 'text' => 'Happy to plan the morning set collaboration for next month. Shall we pick a shoot date?'],
                ['from' => 'us', 'text' => 'Yes please. Would the first week of November work for you?'],
            ]],
    ],
    'analytics' => [
        '30' => [
            'label' => 'Last 30 days',
            'kpis' => [
                ['label' => 'Followers', 'value' => '4,386', 'delta' => '+284'],
                ['label' => 'Engagement rate', 'value' => '4.6%', 'delta' => '+0.4 pts'],
                ['label' => 'Posts', 'value' => '38', 'delta' => '+6'],
                ['label' => 'Link clicks', 'value' => '612', 'delta' => '+71'],
            ],
            'growth' => $series(30, 4102, 4386, 3, 9),
            'growth_axis' => ['Sep 13', 'Sep 27', 'Oct 12'],
            'engagement' => $bars(15, 140, 410, 2),
            'engagement_axis' => ['Sep 13', 'Sep 27', 'Oct 12'],
            'top_posts' => [
                ['title' => 'Throwing a mug in 30 seconds', 'channel' => 'tiktok', 'rate' => 7.9],
                ['title' => 'New speckled mug colors', 'channel' => 'instagram', 'rate' => 6.2],
                ['title' => 'Matte or glossy?', 'channel' => 'threads', 'rate' => 5.4],
                ['title' => 'Wholesale for cafés', 'channel' => 'linkedin', 'rate' => 4.1],
                ['title' => 'Thursday open studio', 'channel' => 'facebook', 'rate' => 2.8],
            ],
            'channels' => [
                ['channel' => 'tiktok', 'rate' => 6.1, 'posts' => 7],
                ['channel' => 'instagram', 'rate' => 4.8, 'posts' => 12],
                ['channel' => 'threads', 'rate' => 3.9, 'posts' => 6],
                ['channel' => 'linkedin', 'rate' => 3.4, 'posts' => 5],
                ['channel' => 'facebook', 'rate' => 2.3, 'posts' => 5],
                ['channel' => 'pinterest', 'rate' => 1.9, 'posts' => 3],
            ],
        ],
        '90' => [
            'label' => 'Last 90 days',
            'kpis' => [
                ['label' => 'Followers', 'value' => '4,386', 'delta' => '+733'],
                ['label' => 'Engagement rate', 'value' => '4.3%', 'delta' => '+0.9 pts'],
                ['label' => 'Posts', 'value' => '104', 'delta' => '+19'],
                ['label' => 'Link clicks', 'value' => '1,694', 'delta' => '+248'],
            ],
            'growth' => $series(30, 3653, 4386, 7, 14),
            'growth_axis' => ['Jul 14', 'Aug 28', 'Oct 12'],
            'engagement' => $bars(13, 820, 2140, 5),
            'engagement_axis' => ['Jul 14', 'Aug 28', 'Oct 12'],
            'top_posts' => [
                ['title' => 'Throwing a mug in 30 seconds', 'channel' => 'tiktok', 'rate' => 7.9],
                ['title' => 'Summer kiln opening', 'channel' => 'instagram', 'rate' => 6.6],
                ['title' => 'New speckled mug colors', 'channel' => 'instagram', 'rate' => 6.2],
                ['title' => 'Matte or glossy?', 'channel' => 'threads', 'rate' => 5.4],
                ['title' => 'Our first wholesale café', 'channel' => 'linkedin', 'rate' => 4.4],
            ],
            'channels' => [
                ['channel' => 'tiktok', 'rate' => 5.7, 'posts' => 19],
                ['channel' => 'instagram', 'rate' => 4.6, 'posts' => 33],
                ['channel' => 'threads', 'rate' => 3.6, 'posts' => 17],
                ['channel' => 'linkedin', 'rate' => 3.2, 'posts' => 14],
                ['channel' => 'facebook', 'rate' => 2.2, 'posts' => 13],
                ['channel' => 'pinterest', 'rate' => 1.8, 'posts' => 8],
            ],
        ],
    ],
    'ai_flows' => [
        'ideas' => [
            'label' => 'Suggest content ideas',
            'prompt' => 'Suggest three post ideas for our autumn mug collection.',
            'reply' => [
                'Here are three directions that fit a small ceramics studio:',
                '1. Glaze reveal: a short video opening the kiln, ending on the three new colors side by side.',
                '2. Morning ritual: customers share the drink they make first thing, in the mug they own.',
                '3. Maker notes: one carousel slide per glaze, with the name, the mood, and what inspired it.',
                'Want me to draft a caption for one of these?',
            ],
        ],
        'refine' => [
            'label' => 'Refine a draft',
            'prompt' => 'Make this warmer and shorter: "Our new autumn mugs are now available in three colors. Order now on our website."',
            'reply' => [
                'Here is a warmer, shorter version:',
                '"Three new speckled glazes, made for slow autumn mornings. Which one is yours? Link in bio."',
                'What changed: the colors become a feeling, the question invites replies, and the call to action fits Instagram.',
            ],
        ],
        'plan' => [
            'label' => 'Organize a posting plan',
            'prompt' => 'Spread these three ideas across next week.',
            'reply' => [
                'Here is a simple plan that leaves room between posts:',
                'Monday, 9:00 AM on Instagram: glaze reveal video.',
                'Wednesday, 1:00 PM on Threads: ask followers about their morning drink.',
                'Friday, 6:00 PM on Instagram: maker notes carousel.',
                'I can add these to the calendar as drafts for review.',
            ],
        ],
    ],
];
