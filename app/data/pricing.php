<?php

declare(strict_types=1);

// Launch pricing. Paid plans are billed per connected channel:
// channels 1 to `volume_from - 1` at the plan rate, every channel after that at `volume_rate`.
// Yearly billing charges `yearly_months` months for a full year.

return [
    'currency' => '$',
    'volume_from' => 11,
    'volume_rate' => 4,
    'yearly_months' => 10,
    'max_channels' => 100,
    'free_channels' => 3,
    'free_ideas' => 100,
    'free_scheduled' => 10,

    'plans' => [
        'free' => [
            'name' => 'Free',
            'rate' => 0,
            'badge' => null,
            'included' => [
                ['Up to 3 channels', 'Connect up to 3 social profiles, Facebook Pages, or Pinterest accounts. Each one counts as a channel.'],
                ['10 scheduled posts per channel, refilled as posts go out', 'Each channel can hold up to 10 scheduled posts at a time. When one is published, the slot opens again.'],
                ['100 ideas', 'Save up to 100 ideas in the content planner.'],
                ['1 user account', 'One person can sign in and manage the workspace.'],
                ['AI writing assistance', 'Help turning a starting thought into a draft, with your review before anything is used.'],
                ['Insights', 'Core analytics with a 30-day history.'],
                ['Community inbox', 'Read and reply to comments in one place.'],
                ['Link in bio page', 'A simple page with links to your content. It counts as one channel.'],
            ],
        ],
        'essentials' => [
            'name' => 'Essentials',
            'rate' => 6,
            'badge' => 'Recommended',
            'included' => [
                ['Unlimited channels and Pages', 'Connect as many profiles, Facebook Pages, and boards as you manage. You pay per channel, and every channel after the tenth costs less.'],
                ['Unlimited scheduled posts per channel', 'No cap on how many posts each channel can have scheduled.'],
                ['Unlimited ideas', 'Keep every idea in the planner.'],
                ['1 user account', 'One person can sign in and manage the workspace.'],
                ['AI writing assistance', 'Help turning a starting thought into a draft, with your review before anything is used.'],
                ['Advanced analytics', 'Unlimited history, performance overviews, and exportable reports.'],
                ['Community inbox', 'Read and reply to comments in one place, with unlimited saved replies.'],
                ['Hashtag manager', 'Save groups of hashtags and reuse them.'],
                ['First comment scheduling', 'Schedule a first comment to go out with a post.'],
            ],
        ],
        'team' => [
            'name' => 'Team',
            'rate' => 12,
            'badge' => null,
            'included' => [
                ['Unlimited channels and Pages', 'Connect as many profiles, Facebook Pages, and boards as your team manages. You pay per channel, and every channel after the tenth costs less.'],
                ['Unlimited scheduled posts per channel', 'No cap on how many posts each channel can have scheduled.'],
                ['Unlimited ideas', 'Keep every idea in the planner.'],
                ['Unlimited team members', 'Invite as many people as you need at no extra cost.'],
                ['AI writing assistance', 'Help turning a starting thought into a draft, with your review before anything is used.'],
                ['Advanced analytics', 'Unlimited history, performance overviews, and exportable reports.'],
                ['Community inbox', 'Read and reply to comments in one place, with unlimited saved replies.'],
                ['Hashtag manager', 'Save groups of hashtags and reuse them.'],
                ['First comment scheduling', 'Schedule a first comment to go out with a post.'],
                ['Access levels', 'Choose which channels each team member can use.'],
                ['Content approval workflows', 'Decide who can draft and who can approve before a post is scheduled.'],
            ],
        ],
    ],

    // Each row: [feature, description, free, essentials, team]. true = included, false = not included, string = limit.
    'compare' => [
        'Content planning' => [
            ['Ideas', 'Capture and store content ideas whenever they come to you.', '100 ideas', 'Unlimited', 'Unlimited'],
            ['Tags', 'Organize content by campaign, format, or topic.', '3 tags', '250 tags', '250 tags'],
            ['Drafts', 'Keep work in progress until it is ready.', 'Unlimited', 'Unlimited', 'Unlimited'],
            ['Board view', 'Move ideas through Ideas, Drafting, and Ready for review columns.', true, true, true],
            ['AI writing assistance', 'Suggest ideas, refine drafts, and organize a plan.', true, true, true],
        ],
        'Publishing' => [
            ['Connected channels', 'Each social profile, Facebook Page, or Pinterest account counts as one channel.', 'Up to 3', 'Unlimited, priced per channel', 'Unlimited, priced per channel'],
            ['Networks you can publish to', 'Instagram, Facebook Pages, LinkedIn, YouTube, Pinterest, and Threads.', '6', '6', '6'],
            ['Trending ideas', 'This week’s trending videos, stories, and searches, inside the composer.', true, true, true],
            ['Scheduled posts per channel', 'How many posts each channel can have scheduled at one time.', '10', 'Unlimited', 'Unlimited'],
            ['Visual content calendar', 'See your schedule across weeks or months.', true, true, true],
            ['Queue', 'A running list of upcoming posts you can reorder.', true, true, true],
            ['Link in bio page', 'A simple landing page with links to your content. Counts as one channel.', true, true, true],
            ['Duplicate posts', 'Copy a post and adapt it for another channel or date.', true, true, true],
            ['First comment scheduling', 'Schedule a first comment to go out with a post.', false, true, true],
            ['Channel groups', 'Group channels for quick selection when scheduling.', false, true, true],
            ['Hashtag manager', 'Save groups of hashtags and insert them from the composer.', '3 groups', 'Unlimited', 'Unlimited'],
            ['RSS feeds', 'Follow blogs and news sites and turn new articles into posts.', '3 feeds', '25 feeds', '25 feeds'],
            ['Bulk scheduling', 'Upload a CSV file to schedule many posts at once.', true, true, true],
        ],
        'Community' => [
            ['Community inbox', 'Read and reply to comments from connected channels in one place.', true, true, true],
            ['Saved replies', 'Reuse answers to common questions.', '1 saved reply', 'Unlimited', 'Unlimited'],
            ['AI reply suggestions', 'Draft replies in your tone for you to review and send.', '5 per week', 'Unlimited', 'Unlimited'],
        ],
        'Analytics' => [
            ['Insights', 'Follower growth, engagement, and post performance.', '30-day history', 'Unlimited', 'Unlimited'],
            ['Performance overview', 'A summary of results for any date range, compared with the previous period.', false, true, true],
            ['Reports', 'Export your data as CSV or PDF.', false, true, true],
            ['Custom UTM parameters', 'Add tracking parameters to the links you share.', false, true, true],
            ['Branded reports', 'Add your logo and a cover page to exported reports.', false, false, true],
        ],
        'Collaboration' => [
            ['Included users', 'How many people can sign in to the workspace.', '1', '1', 'Unlimited'],
            ['Two-factor authentication', 'Protect sign-in with a one-time code.', true, true, true],
            ['Internal notes', 'Leave comments on posts that only your team can see.', false, true, true],
            ['Approval workflows', 'Decide who can draft and who can approve posts.', false, false, true],
            ['Access levels', 'Choose which channels each team member can use.', false, false, true],
        ],
        'Support' => [
            ['Help guides', 'Step-by-step articles for getting set up and using each feature.', true, true, true],
            ['Email support', 'Reach the atwebtech team by email.', true, true, true],
        ],
    ],

    'faq' => [
        ['Can I sign up today?', Billing::enabled()
            ? '<p>Yes. Create a free account and start planning and scheduling right away, with no credit card. When you need more, upgrade to Essentials or Team from Plan &amp; billing in your workspace. Payments are processed securely by Stripe.</p>'
            : '<p>Yes. Create a free account and start planning and scheduling right away, with no credit card. Paid plans are opening soon. Use "Get notified" under Essentials or Team and we will email you as soon as you can upgrade.</p>'],
        ['What counts as a channel?', '<p>A channel is one social account you connect, such as a single Instagram profile, Facebook page, or LinkedIn page. A link in bio page also counts as one channel. If you manage three brands with an Instagram and a Facebook account each, that is six channels.</p>'],
        ['How does per-channel pricing work?', '<p>Paid plans are priced per channel, so you only pay for the accounts you manage. Channels 1 to 10 are billed at the plan rate ($6 a month on Essentials, $12 a month on Team). Every channel after the tenth is $4 a month on either plan, so your average cost per channel goes down as you grow.</p>'],
        ['What are the billing options?', '<p>Paid plans can be billed monthly or yearly. Yearly billing costs the same as 10 months, so you save the equivalent of two months.</p>'],
        ['Is there a free plan?', '<p>Yes. The Free plan includes up to 3 channels, 10 scheduled posts per channel at a time, 100 ideas, and core analytics, with no time limit.</p>'],
        ['What is the scheduled post limit for each plan?', '<p>On the Free plan each channel can hold 10 scheduled posts at a time. When a post is published, that slot becomes available again. Paid plans have no limit on scheduled posts per channel.</p>'],
        ['Which channels can I connect?', '<p>atwebtech is built for Instagram, Facebook, TikTok, LinkedIn, YouTube, Pinterest, Threads, and Google Business Profile. Each network is switched on once its connection is approved by the platform. The <a href="/integrations">integrations page</a> shows which ones are live today.</p>'],
        ['Can I cancel or change plans?', '<p>Yes. You can stay on Free as long as you like. On a paid plan you can switch plans, add or remove channels, or cancel from Plan &amp; billing in your workspace. Changes are prorated, and if you cancel you keep your paid features until the end of the period you paid for.</p>'],
        ['Which payment methods do you accept?', '<p>All major credit and debit cards, processed by Stripe. Card details are never stored on atwebtech servers. Invoices and receipts are available from Plan &amp; billing.</p>'],
    ],
];
