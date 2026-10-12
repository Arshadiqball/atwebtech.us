<?php

declare(strict_types=1);

// Onboarding questions shown after sign-up. Keys are stored in user_onboarding.
return [
    'steps' => [
        1 => [
            'field' => 'describe_as',
            'title' => 'How would you describe yourself?',
            'multiple' => false,
            'options' => [
                'solo-creator' => ['Solo creator', '👋', 'lavender'],
                'small-business' => ['Small business owner', '💪', 'orange'],
                'marketing-team' => ['Part of a company marketing team', '👩‍💻', 'green'],
                'freelancer' => ['Freelancer/consultant', '⭐', 'pink'],
                'agency' => ['Marketing agency', '🎯', 'teal'],
                'nonprofit' => ['Non-profit organization', '🏆', 'coral'],
                'other' => ['Other', '🪄', 'blue'],
            ],
        ],
        2 => [
            'field' => 'tools',
            'title' => 'What tools do you use to manage your social media?',
            'multiple' => true,
            'options' => [
                'none' => ['None - I post to social platforms directly', '💻', 'lavender'],
                'meta-business-suite' => ['Meta Business Suite', '🔵', 'coral'],
                'smm-tool' => ['An existing social media management tool', '⚒️', 'green', 'e.g. Hootsuite, Sprout Social, Later'],
                'platform-tools' => ['Tools that focus on specific social platform(s)', '🧁', 'pink', 'e.g. Typefully, Hypefury'],
                'ai' => ['AI platforms', '🍲', 'teal', 'ChatGPT/Claude/etc.'],
                'other' => ['Other', '🌈', 'orange'],
            ],
        ],
        3 => [
            'field' => 'accounts_range',
            'title' => 'How many social accounts do you currently manage?',
            'multiple' => false,
            'options' => [
                '1-3' => ['1-3'],
                '4-6' => ['4-6'],
                '7-10' => ['7-10'],
                '11-20' => ['11-20'],
                '21-50' => ['21-50'],
                '50+' => ['50+'],
            ],
        ],
        4 => [
            'field' => 'focus_channels',
            'title' => 'What social channel(s) are in focus?',
            'multiple' => true,
            'channels' => true,
        ],
    ],

    'referral' => [
        'team' => 'Member of the atwebtech team',
        'social' => 'On a social network',
        'friend' => 'Friend, coworker or family member',
        'search' => 'Search engine',
        'waitlist' => 'I joined the atwebtech waitlist',
        'resources' => 'The atwebtech resources and guides',
        'course' => 'Took a course, class or training',
        'ai' => 'AI search or chatbot (ChatGPT, Gemini, Claude, Perplexity, etc.)',
        'other' => 'Other (please specify)',
    ],
];
