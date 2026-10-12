<?php

declare(strict_types=1);

return [
    'features' => [
        ['label' => 'Content Planner', 'desc' => 'Collect ideas and shape them into drafts', 'href' => '/features/content-planner', 'icon' => 'lightbulb'],
        ['label' => 'Publishing', 'desc' => 'Plan posts on a clear, channel-aware calendar', 'href' => '/features/publishing', 'icon' => 'calendar-blank'],
        ['label' => 'Analytics', 'desc' => 'Compare posts and spot useful patterns', 'href' => '/features/analytics', 'icon' => 'chart-line-up'],
        ['label' => 'Community Inbox', 'desc' => 'Organize conversations in one shared view', 'href' => '/features/community', 'icon' => 'chats-circle'],
        ['label' => 'Team Collaboration', 'desc' => 'Keep feedback and approvals together', 'href' => '/features/collaboration', 'icon' => 'users-three'],
        ['label' => 'Link in Bio', 'desc' => 'Give your audience a clear next step', 'href' => '/features/link-in-bio', 'icon' => 'link-simple'],
        ['label' => 'AI Writing Assistant', 'desc' => 'Turn a starting thought into a draft', 'href' => '/features/ai-assistant', 'icon' => 'sparkle'],
    ],
    'solutions' => [
        ['label' => 'Creators', 'desc' => 'Keep a steady rhythm without the scramble', 'href' => '/solutions/creators', 'icon' => 'user'],
        ['label' => 'Small Businesses', 'desc' => 'Show up consistently with a small team', 'href' => '/solutions/businesses', 'icon' => 'storefront'],
        ['label' => 'Agencies', 'desc' => 'Organize work across several clients', 'href' => '/solutions/agencies', 'icon' => 'briefcase'],
        ['label' => 'Marketing Teams', 'desc' => 'Plan campaigns with shared visibility', 'href' => '/solutions/marketing-teams', 'icon' => 'megaphone'],
    ],
    'resources' => [
        ['label' => 'Getting started', 'desc' => 'What you can do with atwebtech today', 'href' => '/resources/getting-started', 'icon' => 'rocket-launch'],
        ['label' => 'Guides', 'desc' => 'Practical articles for everyday social work', 'href' => '/resources', 'icon' => 'book-open'],
        ['label' => 'FAQ', 'desc' => 'Answers about the product and its status', 'href' => '/faq', 'icon' => 'question'],
        ['label' => 'Contact', 'desc' => 'Send the team a question or feedback', 'href' => '/contact', 'icon' => 'envelope-simple'],
    ],
    'footer' => [
        'Product' => [
            ['Content Planner', '/features/content-planner'],
            ['Publishing', '/features/publishing'],
            ['Analytics', '/features/analytics'],
            ['Community Inbox', '/features/community'],
            ['Team Collaboration', '/features/collaboration'],
            ['Link in Bio', '/features/link-in-bio'],
            ['AI Writing Assistant', '/features/ai-assistant'],
            ['Integrations', '/integrations'],
            ['Interactive demo', '/demo'],
        ],
        'Solutions' => [
            ['Creators', '/solutions/creators'],
            ['Small Businesses', '/solutions/businesses'],
            ['Agencies', '/solutions/agencies'],
            ['Marketing Teams', '/solutions/marketing-teams'],
        ],
        'Resources' => [
            ['All guides', '/resources'],
            ['Getting started', '/resources/getting-started'],
            ['Weekly content calendar', '/resources/weekly-content-calendar'],
            ['Adapting ideas by channel', '/resources/adapting-ideas-for-each-channel'],
            ['Choosing social metrics', '/resources/choosing-social-metrics'],
            ['FAQ', '/faq'],
        ],
        'Company' => [
            ['About', '/about'],
            ['Pricing', '/pricing'],
            ['Contact', '/contact'],
            ['Privacy', '/privacy'],
            ['Terms', '/terms'],
        ],
    ],
];
