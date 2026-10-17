<?php

declare(strict_types=1);

function not_found(): never
{
    View::render('404', [], [
        'title' => 'Page not found | atwebtech',
        'description' => 'The page you were looking for could not be found.',
        'noindex' => true,
    ], 404);
}

function page(string $view, string $title, string $description, array $vars = [], array $meta = []): Closure
{
    return static fn () => View::render($view, $vars, ['title' => $title, 'description' => $description] + $meta);
}

$org = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'atwebtech',
    'url' => absolute_url('/'),
    'logo' => absolute_url('/assets/img/logo-mark-512.png'),
];

return [
    ['GET', '/', page('home', 'atwebtech | Plan your social content in one workspace', 'Meet atwebtech: a simpler way to plan social content, organize ideas, and build a more consistent online presence. Join the waitlist.', [], [
        'schema' => [$org, [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'atwebtech',
            'url' => absolute_url('/'),
        ]],
    ])],

    ['GET', '/demo', page('demo', 'Interactive product demo | atwebtech', 'Explore interactive demonstrations of the atwebtech calendar, idea board, inbox, analytics, and AI workflow using sample data.')],

    ['GET', '/features', page('features', 'Features | atwebtech', 'An overview of the atwebtech workspace: content planning, publishing, analytics, community inbox, collaboration, link in bio, and AI writing assistance.')],

    ['GET', '/features/{slug}', static function (string $slug): void {
        $feature = data('features')[$slug] ?? null;
        if (!$feature) {
            not_found();
        }
        View::render('feature', ['feature' => $feature, 'slug' => $slug], [
            'title' => $feature['meta_title'],
            'description' => $feature['meta_description'],
        ]);
    }],

    ['GET', '/integrations', page('integrations', 'Integrations and channel status | atwebtech', 'See which social channels atwebtech plans to support, and the current status of each integration.')],

    ['GET', '/solutions', page('solutions', 'Solutions | atwebtech', 'How atwebtech is designed for creators, small businesses, agencies, and marketing teams.')],

    ['GET', '/solutions/{slug}', static function (string $slug): void {
        $solution = data('solutions')[$slug] ?? null;
        if (!$solution) {
            not_found();
        }
        View::render('solution', ['solution' => $solution, 'slug' => $slug], [
            'title' => $solution['meta_title'],
            'description' => $solution['meta_description'],
        ]);
    }],

    ['GET', '/pricing', page('pricing', 'Pricing | atwebtech', 'atwebtech launch pricing: a Free plan for up to 3 channels, and Essentials and Team plans priced per channel, billed monthly or yearly.')],

    ['GET', '/resources', page('resources', 'Resources and guides | atwebtech', 'Practical, introductory guides on content calendars, adapting ideas for each channel, and choosing social metrics.')],

    ['GET', '/resources/{slug}', static function (string $slug) use ($org): void {
        $article = data('articles')[$slug] ?? null;
        if (!$article) {
            not_found();
        }
        View::render('article', ['article' => $article, 'slug' => $slug], [
            'title' => $article['title'] . ' | atwebtech',
            'description' => $article['description'],
            'type' => 'article',
            'schema' => [[
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $article['title'],
                'description' => $article['description'],
                'mainEntityOfPage' => absolute_url('/resources/' . $slug),
                'author' => ['@type' => 'Organization', 'name' => 'atwebtech', 'url' => absolute_url('/')],
                'publisher' => $org,
            ]],
        ]);
    }],

    ['GET', '/faq', page('faq', 'Frequently asked questions | atwebtech', 'Answers about what atwebtech is, who it is for, the demo, integration status, launch updates, and how to contact the team.')],
    ['GET', '/about', page('about', 'About | atwebtech', 'atwebtech is built to make everyday social work simpler, with a focus on clarity, organization, and honest product status.')],
    ['GET', '/contact', page('contact', 'Contact | atwebtech', 'Send the atwebtech team a question, feedback, or partnership enquiry.')],
    ['GET', '/join', page('join', 'Join the waitlist | atwebtech', 'Join the atwebtech waitlist for product updates and early-access news.')],
    ['GET', '/joined', page('joined', 'You are on the list | atwebtech', 'Your atwebtech waitlist signup is confirmed.', [], ['noindex' => true])],
    ['GET', '/privacy', page('legal', 'Privacy policy | atwebtech', 'How atwebtech collects, uses, stores, and deletes information, including YouTube and other Google user data.', ['doc' => 'privacy'])],
    ['GET', '/data-deletion', page('legal', 'Data deletion | atwebtech', 'How to ask atwebtech to delete your account and data received from connected channels.', ['doc' => 'deletion'])],
    ['GET', '/terms', page('legal', 'Terms of use (draft) | atwebtech', 'Draft terms of use for atwebtech.us, pending owner and legal review.', ['doc' => 'terms'])],

    ['POST', '/waitlist', [FormHandler::class, 'waitlist']],
    ['POST', '/contact', [FormHandler::class, 'contact']],

    ['GET', '/signup', static function (): void {
        if (Auth::check()) {
            redirect('/app');
        }
        View::render('auth', ['mode' => 'signup'], ['title' => 'Create your account | atwebtech', 'description' => 'Create an atwebtech account.', 'layout' => 'app', 'noindex' => true]);
    }],
    ['POST', '/signup', [AccountHandler::class, 'signup']],
    ['GET', '/login', static function (): void {
        $next = Auth::safeNext($_GET['next'] ?? null);
        if (Auth::check()) {
            redirect($next ?? '/app');
        }
        TwoFactor::clearLogin();
        View::render('auth', ['mode' => 'login', 'next' => $next], ['title' => 'Log in | atwebtech', 'description' => 'Log in to your atwebtech account.', 'layout' => 'app', 'noindex' => true]);
    }],
    ['POST', '/login', [AccountHandler::class, 'login']],
    ['GET', '/login/two-factor', static function (): void {
        if (Auth::check()) {
            redirect('/app');
        }
        $pending = TwoFactor::pendingLogin();
        if ($pending === null) {
            redirect('/login');
        }
        View::render('two-factor', ['email' => $pending['email']], [
            'title' => 'Authentication code | atwebtech',
            'description' => 'Enter the code from your authenticator app.',
            'layout' => 'app',
            'noindex' => true,
        ]);
    }],
    ['POST', '/login/two-factor', [AccountHandler::class, 'twoFactorLogin']],
    ['POST', '/logout', [AccountHandler::class, 'logout']],

    ['GET', '/welcome', static function (): void {
        $user = Auth::require();
        $onboarding = Auth::onboarding((int) $user['id']);
        if ($onboarding['completed_at'] !== null) {
            redirect('/app');
        }
        $steps = data('onboarding')['steps'];
        $step = (int) ($_GET['step'] ?? 0);
        if ($step !== 0 && !isset($steps[$step])) {
            redirect('/welcome');
        }
        View::render('welcome', ['user' => $user, 'onboarding' => $onboarding, 'step' => $step, 'steps' => $steps], [
            'title' => ($step === 0 ? 'Welcome' : $steps[$step]['title']) . ' | atwebtech',
            'description' => 'Set up your atwebtech workspace.',
            'layout' => 'app',
            'noindex' => true,
        ]);
    }],
    ['POST', '/welcome', [AccountHandler::class, 'onboardingStep']],

    ['GET', '/app', static function (): void {
        $user = Auth::require();
        $onboarding = Auth::onboarding((int) $user['id']);
        if ($onboarding['completed_at'] === null) {
            redirect('/welcome' . ((int) $onboarding['step'] > 0 ? '?step=' . ((int) $onboarding['step'] + 1) : ''));
        }
        View::render('app-home', ['user' => $user, 'onboarding' => $onboarding, 'section' => 'home'], [
            'title' => 'Home | atwebtech', 'description' => 'Your atwebtech workspace.', 'layout' => 'app', 'noindex' => true,
        ]);
    }],
    ['GET', '/app/create', static function (): void {
        $tab = Workspace::pick('tab', ['ideas', 'templates', 'feeds']);
        Workspace::render('create', 'workspace/create', 'Create', static function (array $user) use ($tab): array {
            $uid = (int) $user['id'];
            return [
                'tab' => $tab,
                'view' => Workspace::pick('view', ['board', 'gallery']),
                'tag' => mb_substr(trim((string) ($_GET['tag'] ?? '')), 0, 24),
                'set' => Workspace::pick('set', ['discover', 'personal']),
                'category' => (string) ($_GET['category'] ?? ''),
                'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
                'board' => $tab === 'ideas' ? Workspace::board($uid) : null,
                'personal' => Workspace::personalTemplates($uid),
            ];
        });
    }],
    ['GET', '/app/create/idea', static function (): void {
        Workspace::render('create', 'workspace/idea-page', 'Idea', static function (array $user): array {
            $idea = isset($_GET['id']) ? Workspace::idea((int) $user['id'], (int) $_GET['id']) : null;
            if (isset($_GET['id']) && $idea === null) {
                not_found();
            }
            return ['idea' => $idea, 'group' => (int) ($_GET['group'] ?? 0)];
        });
    }],
    ['GET', '/app/publish', static function (): void {
        Workspace::render('publish', 'workspace/publish', 'Publish', static function (array $user, array $onboarding): array {
            $tab = Workspace::pick('tab', ['queue', 'drafts', 'approvals', 'sent']);
            $view = Workspace::pick('view', ['list', 'calendar']);
            $uid = (int) $user['id'];
            return [
                'tab' => $tab,
                'view' => $view,
                'channel' => (string) ($_GET['channel'] ?? ''),
                'queue' => $tab === 'queue' && $view === 'list' ? Workspace::queue($user, $onboarding, max(2, min(14, (int) ($_GET['days'] ?? 2)))) : null,
                'week' => $tab === 'queue' && $view === 'calendar' ? Workspace::week($user, max(-52, min(52, (int) ($_GET['week'] ?? 0)))) : null,
                'drafts' => $tab === 'drafts' ? Workspace::drafts($uid) : ($tab === 'sent' ? Workspace::sent($uid) : []),
                'counts' => Workspace::counts($uid),
            ];
        });
    }],
    ['GET', '/app/publish/new', static function (): void {
        Workspace::render('publish', 'workspace/compose-page', 'New post', static function (array $user): array {
            $uid = (int) $user['id'];
            $target = (string) ($_GET['target'] ?? (isset($_GET['channel']) ? 'n:' . $_GET['channel'] : ''));
            $prefill = ['id' => 0, 'targets' => $target !== '' ? [$target] : [], 'body' => '', 'date' => (string) ($_GET['date'] ?? ''), 'time' => (string) ($_GET['time'] ?? ''), 'idea' => 0];
            if (isset($_GET['id'])) {
                $post = Workspace::post($uid, (int) $_GET['id']);
                if ($post === null) {
                    not_found();
                }
                $prefill = Workspace::composeData($post, Workspace::timezone($user), $uid) + ['idea' => (int) $post['idea_id']];
            } elseif (isset($_GET['idea']) && ($idea = Workspace::idea($uid, (int) $_GET['idea']))) {
                $prefill['body'] = trim($idea['title'] . "\n\n" . $idea['body']);
                $prefill['idea'] = (int) $idea['id'];
            } elseif (isset($_GET['template']) && ($template = WorkspaceHandler::findTemplate($uid, (string) $_GET['template']))) {
                $prefill['body'] = $template['prompt'];
            }
            return ['prefill' => $prefill];
        });
    }],
    ['GET', '/app/community', static function (): void {
        Workspace::render('community', 'workspace/community', 'Community', static fn (): array => [
            'tab' => Workspace::pick('tab', ['comments', 'mentions']),
            'view' => Workspace::pick('view', ['post', 'list']),
        ]);
    }],
    ['GET', '/app/insights', static function (): void {
        Workspace::render('insights', 'workspace/insights', 'Insights', static function (array $user): array {
            $range = Workspace::pick('range', ['30d', '7d', 'mtd', 'last-month', 'custom']);
            return ['insights' => Workspace::insights($user, $range)];
        });
    }],
    ['POST', '/app/ideas', [WorkspaceHandler::class, 'idea']],
    ['POST', '/app/ideas/move', [WorkspaceHandler::class, 'moveIdeas']],
    ['POST', '/app/groups', [WorkspaceHandler::class, 'group']],
    ['POST', '/app/posts', [WorkspaceHandler::class, 'post']],
    ['POST', '/app/posts/move', [WorkspaceHandler::class, 'movePost']],
    ['POST', '/app/templates', [WorkspaceHandler::class, 'template']],
    ['POST', '/account/timezone', [WorkspaceHandler::class, 'timezone']],
    ['GET', '/app/account', static function (): void {
        Workspace::render('account', 'workspace/account', 'Account', static function (array $user): array {
            return ['twoFactor' => TwoFactor::pageState((int) $user['id'])];
        });
    }],
    ['POST', '/app/account/two-factor/start', [AccountHandler::class, 'twoFactorStart']],
    ['POST', '/app/account/two-factor/cancel', [AccountHandler::class, 'twoFactorCancel']],
    ['POST', '/app/account/two-factor/confirm', [AccountHandler::class, 'twoFactorConfirm']],
    ['POST', '/app/account/two-factor/disable', [AccountHandler::class, 'twoFactorDisable']],
    ['POST', '/app/account/two-factor/recovery', [AccountHandler::class, 'twoFactorRecovery']],
    ['POST', '/app/account/two-factor/recovery/done', [AccountHandler::class, 'twoFactorRecoveryDone']],
    ['GET', '/app/channels', static function (): void {
        Workspace::render('channels', 'workspace/channels', 'Channels', static fn (array $user): array => ['connected' => Channels::forUser((int) $user['id'])]);
    }],
    ['POST', '/app/channels/connect/{provider}', [ChannelHandler::class, 'start']],
    ['GET', '/app/channels/callback/{provider}', [ChannelHandler::class, 'callback']],
    ['GET', '/app/channels/confirm', static function (): void {
        $user = Auth::require();
        $pending = ChannelHandler::pending();
        if ($pending === null) {
            redirect('/app/channels');
        }
        View::render('channel-confirm', ['user' => $user, 'pending' => $pending, 'connected' => Channels::forUser((int) $user['id'])], [
            'title' => 'Confirm your account | atwebtech', 'description' => 'Confirm the channel to connect.', 'layout' => 'app', 'noindex' => true,
        ]);
    }],
    ['POST', '/app/channels/confirm', [ChannelHandler::class, 'finish']],
    ['GET', '/app/channels/{id}', static function (string $id): void {
        if (!ctype_digit($id)) {
            not_found();
        }
        Workspace::render('channels', 'workspace/channel', 'Channel', static function (array $user, array $onboarding) use ($id): array {
            $uid = (int) $user['id'];
            $channel = Channels::find($uid, (int) $id);
            if ($channel === null) {
                not_found();
            }
            if (($_GET['setup'] ?? '') === 'schedule') {
                redirect('/app/channels/' . (int) $channel['id'] . '/settings');
            }
            $cid = (int) $channel['id'];
            $tab = Workspace::pick('tab', ['queue', 'drafts', 'approvals', 'sent']);
            $view = Workspace::pick('view', ['list', 'calendar']);
            $days = max(28, min(ChannelHandler::QUEUE_DAYS, (int) ($_GET['days'] ?? 28)));
            return [
                'channel' => $channel,
                'setup' => Workspace::pick('setup', ['', 'goal', 'times', 'schedule']),
                'tab' => $tab,
                'view' => $view,
                'days' => $days,
                'queue' => $tab === 'queue' && $view === 'list' ? Workspace::queue($user, $onboarding, $days, $cid) : null,
                'week' => $tab === 'queue' && $view === 'calendar' ? Workspace::week($user, max(-52, min(52, (int) ($_GET['week'] ?? 0))), $cid) : null,
                'posts' => match ($tab) { 'drafts' => Workspace::drafts($uid, $cid), 'sent' => Workspace::sent($uid, $cid), default => [] },
                'counts' => Workspace::counts($uid, $cid),
            ];
        });
    }],
    ['GET', '/app/channels/{id}/settings', [ChannelHandler::class, 'settings']],
    ['GET', '/app/channels/{id}/queue', [ChannelHandler::class, 'queueFragment']],
    ['GET', '/app/channels/{id}/community', static function (string $id): void {
        ChannelHandler::subPage($id, 'community');
    }],
    ['GET', '/app/channels/{id}/insights', static function (string $id): void {
        ChannelHandler::subPage($id, 'insights');
    }],
    ['POST', '/app/channels/{id}/goal', [ChannelHandler::class, 'goal']],
    ['POST', '/app/channels/{id}/schedule', [ChannelHandler::class, 'schedule']],
    ['POST', '/app/channels/{id}/intro', [ChannelHandler::class, 'closeIntro']],
    ['POST', '/app/channels/{id}/disconnect', [ChannelHandler::class, 'disconnect']],
    ['POST', '/app/media', [Media::class, 'start']],
    ['POST', '/app/media/{id}/chunk', [Media::class, 'chunk']],
    ['POST', '/app/media/{id}/complete', [Media::class, 'complete']],
    ['GET', '/app/media/{id}', [Media::class, 'serve']],
    ['POST', '/app/ai/generate', [Assistant::class, 'generate']],
    ['GET', '/app/{slug}', static function (string $slug): void {
        $sections = data('workspace')['sections'];
        if (!isset($sections[$slug]['title'])) {
            not_found();
        }
        Workspace::render($slug, 'workspace/section', $sections[$slug]['label'], static fn (): array => ['info' => $sections[$slug]]);
    }],
    ['POST', '/app/survey', [AccountHandler::class, 'survey']],
    ['POST', '/app/checklist/close', [AccountHandler::class, 'closeChecklist']],
    ['POST', '/account/theme', [AccountHandler::class, 'theme']],

    ['GET', '/admin/login', static function (): void {
        if (Auth::staff() || Auth::isAdmin()) {
            redirect('/admin');
        }
        View::render('admin-login', [], [
            'title' => 'Admin login | atwebtech',
            'description' => 'Sign in to the atwebtech admin panel.',
            'layout' => 'app',
            'noindex' => true,
        ]);
    }],
    ['POST', '/admin/login', [Admin::class, 'login']],
    ['GET', '/admin', static function (): void {
        $user = Auth::requireAdmin();
        View::render('admin', [
            'user' => $user,
            'messages' => Admin::messages(),
            'selected' => null,
        ], [
            'title' => 'Messages | atwebtech',
            'description' => 'Contact form messages.',
            'layout' => 'app',
            'noindex' => true,
            'admin' => true,
        ]);
    }],
    ['GET', '/admin/messages/{id}', static function (string $id): void {
        if (!ctype_digit($id)) {
            not_found();
        }
        $user = Auth::requireAdmin();
        $selected = Admin::message((int) $id);
        if ($selected === null) {
            not_found();
        }
        View::render('admin', [
            'user' => $user,
            'messages' => Admin::messages(),
            'selected' => $selected,
        ], [
            'title' => $selected['name'] . ' | Messages | atwebtech',
            'description' => 'Contact form message.',
            'layout' => 'app',
            'noindex' => true,
            'admin' => true,
        ]);
    }],

    ['GET', '/admin/subscribers', static function (): void {
        $user = Auth::requireAdmin();
        View::render('admin-subscribers', [
            'user' => $user,
            'subscribers' => Admin::subscribers(),
            'selected' => null,
        ], [
            'title' => 'Subscribers | atwebtech',
            'description' => 'Waitlist emails for future updates.',
            'layout' => 'app',
            'noindex' => true,
            'admin' => true,
        ]);
    }],
    ['GET', '/admin/subscribers/{id}', static function (string $id): void {
        if (!ctype_digit($id)) {
            not_found();
        }
        $user = Auth::requireAdmin();
        $selected = Admin::subscriber((int) $id);
        if ($selected === null) {
            not_found();
        }
        View::render('admin-subscribers', [
            'user' => $user,
            'subscribers' => Admin::subscribers(),
            'selected' => $selected,
        ], [
            'title' => $selected['email'] . ' | Subscribers | atwebtech',
            'description' => 'Waitlist signup.',
            'layout' => 'app',
            'noindex' => true,
            'admin' => true,
        ]);
    }],
    ['POST', '/admin/subscribers/{id}/plan', static function (string $id): void {
        if (!ctype_digit($id)) {
            not_found();
        }
        Auth::requireAdmin();
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            flash('notice', 'Your session expired. Refresh the page and try again.');
            redirect('/admin/subscribers/' . $id);
        }
        $plan = (string) ($_POST['plan'] ?? '');
        $billing = (string) ($_POST['billing'] ?? 'monthly');
        if (!Admin::setPlan((int) $id, $plan, $billing)) {
            flash('notice', 'That plan could not be saved.');
            redirect('/admin/subscribers/' . $id);
        }
        flash('notice', 'Plan updated.');
        redirect('/admin/subscribers/' . $id);
    }],

    ['GET', '/email/preview/confirm', static function (): void {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
        echo EmailTemplate::confirm(absolute_url('/signup'))['html'];
    }],

    ['GET', '/sitemap.xml', static function (): void {
        $paths = ['/', '/join', '/demo', '/features', '/integrations', '/solutions', '/pricing', '/resources', '/faq', '/about', '/contact', '/privacy', '/data-deletion', '/terms'];
        foreach (array_keys(data('features')) as $slug) {
            $paths[] = '/features/' . $slug;
        }
        foreach (array_keys(data('solutions')) as $slug) {
            $paths[] = '/solutions/' . $slug;
        }
        foreach (array_keys(data('articles')) as $slug) {
            $paths[] = '/resources/' . $slug;
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
        foreach ($paths as $p) {
            echo '  <url><loc>', e(absolute_url($p)), '</loc></url>', "\n";
        }
        echo '</urlset>', "\n";
    }],

    ['GET', '/robots.txt', static function (): void {
        header('Content-Type: text/plain; charset=utf-8');
        if (is_public_site()) {
            echo "User-agent: *\nAllow: /\nDisallow: /app\nDisallow: /admin\nDisallow: /welcome\nDisallow: /login\nDisallow: /signup\nDisallow: /email\n\nSitemap: ", absolute_url('/sitemap.xml'), "\n";
        } else {
            echo "User-agent: *\nDisallow: /\n";
        }
    }],
];
