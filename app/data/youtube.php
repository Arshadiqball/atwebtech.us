<?php

declare(strict_types=1);

// YouTube upload options. Category ids are YouTube's assignable videoCategories (they are the same in most regions).
return [
    'default_category' => '22',
    'categories' => [
        '1' => 'Film & Animation',
        '2' => 'Autos & Vehicles',
        '10' => 'Music',
        '15' => 'Pets & Animals',
        '17' => 'Sports',
        '19' => 'Travel & Events',
        '20' => 'Gaming',
        '22' => 'People & Blogs',
        '23' => 'Comedy',
        '24' => 'Entertainment',
        '25' => 'News & Politics',
        '26' => 'Howto & Style',
        '27' => 'Education',
        '28' => 'Science & Technology',
        '29' => 'Nonprofits & Activism',
    ],
    'visibility' => ['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private'],
    'licenses' => ['youtube' => 'Standard YouTube License', 'creativeCommon' => 'Creative Commons – Attribution'],
    'title_max' => 100,
];
