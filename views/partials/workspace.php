<?php
/** @var array $user */
/** @var array $onboarding */
/** @var string $section */
/** @var string $body  View rendered inside the main panel */
$sections = data('workspace')['sections'];
$channels = array_column(integrations(), null, 'slug');
$connected = Channels::forUser((int) $user['id']);
$connectedProviders = array_column($connected, 'provider');
$focus = array_values(array_filter(explode(',', (string) ($onboarding['focus_channels'] ?? '')), static fn ($s) => isset($channels[$s])));
$suggested = array_slice(array_values(array_diff($focus ?: ['linkedin', 'facebook', 'tiktok'], $connectedProviders)), 0, 3);
$currentChannel = isset($channel['id']) && $section === 'channels' ? (int) $channel['id'] : 0;
$currentSub = $sub ?? 'publish';
$allowance = Channels::allowance((int) $user['id']);
$channelLimit = $allowance['limit'];
$initial = mb_strtoupper(mb_substr($user['display_name'], 0, 1));
$queueCount = Workspace::counts((int) $user['id'])['queue'];
$notice = flash('notice');
?>
<div class="ws" data-workspace data-token="<?= e(Security::csrfToken()) ?>"<?= empty($user['timezone']) ? ' data-tz-unset' : '' ?>>
  <div class="ws-topbar">
    <button type="button" class="ws-icon-btn" aria-expanded="false" aria-controls="ws-side" data-ws-menu><?= icon('list') ?><span class="sr-only">Open navigation</span></button>
    <a class="ws-logo" href="/app" aria-label="atwebtech workspace home"><?php partial('partials/logo'); ?></a>
  </div>

  <aside class="ws-side" id="ws-side" aria-label="Workspace">
    <div class="ws-side-head">
      <a class="ws-logo" href="/app" aria-label="atwebtech workspace home"><?php partial('partials/logo'); ?></a>
      <button type="button" class="ws-icon-btn ws-side-close" data-ws-close><?= icon('x') ?><span class="sr-only">Close navigation</span></button>
    </div>

    <div class="ws-new-wrap" data-ws-dd>
      <button type="button" class="ws-new" aria-expanded="false" aria-controls="ws-new-menu" data-menu-toggle><?= icon('plus') ?><span class="ws-label">New</span></button>
      <div class="ws-menu ws-new-menu" id="ws-new-menu" hidden data-menu-panel>
        <a class="ws-menu-item ws-menu-item--rich" href="/app/publish/new" data-compose="{}">
          <span class="ws-menu-icon ws-menu-icon--blue"><?= icon('file-text') ?></span>
          <span><b>Post</b><small>Write a post for a channel</small></span>
        </a>
        <a class="ws-menu-item ws-menu-item--rich" href="/app/create/idea" data-idea-open="{}">
          <span class="ws-menu-icon ws-menu-icon--green"><?= icon('lightbulb') ?></span>
          <span><b>Idea</b><small>Save an idea for later</small></span>
        </a>
        <hr>
        <a class="ws-menu-item" href="/app/channels#connect" data-connect-open><?= icon('plus') ?>Connect a New Channel</a>
        <span class="ws-menu-item is-disabled" aria-disabled="true"><?= icon('arrow-square-out') ?>Create a Start Page<span class="ws-chip">Soon</span></span>
        <span class="ws-menu-item is-disabled" aria-disabled="true"><?= icon('lightning') ?>Invite a Team Member<span class="ws-chip">Soon</span></span>
      </div>
    </div>

    <nav aria-label="Workspace sections">
      <ul class="ws-nav" role="list">
<?php foreach ($sections as $key => $s): if (($s['nav'] ?? true) === false) continue; ?>
        <li>
          <a href="<?= e($s['href']) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?> title="<?= e($s['label']) ?>">
            <?= icon($s['icon']) ?><span class="ws-label"><?= e($s['label']) ?></span>
<?php if (isset($s['count'])): ?>
            <span class="ws-count" aria-label="<?= $queueCount ?> in queue"><?= $queueCount ?></span>
<?php endif; ?>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
    </nav>

<?php if ($connected): ?>
    <nav class="ws-channels-nav" aria-label="Connected channels">
      <p class="ws-connect-title ws-label">Channels</p>
      <ul class="ws-nav" role="list">
<?php foreach ($connected as $c): ?>
        <li>
          <a href="/app/channels/<?= (int) $c['id'] ?>"<?= $currentChannel === (int) $c['id'] ? ' class="is-open"' : '' ?> title="<?= e($c['name']) ?>">
            <?= channel_avatar($c, 'xs') ?><span class="ws-label ws-channel-name"><?= e($c['name']) ?></span>
          </a>
          <ul class="ws-subnav ws-label" role="list">
<?php foreach (['publish' => ['calendar-blank', 'Publish', ''], 'community' => ['chat-circle-text', 'Community', '/community'], 'insights' => ['chart-bar', 'Insights', '/insights']] as $key => [$ico, $label, $path]): ?>
            <li><a href="/app/channels/<?= (int) $c['id'] . $path ?>"<?= $currentChannel === (int) $c['id'] && $currentSub === $key ? ' aria-current="page"' : '' ?>><?= icon($ico) ?><?= e($label) ?></a></li>
<?php endforeach; ?>
          </ul>
        </li>
<?php endforeach; ?>
      </ul>
    </nav>
<?php endif; ?>

    <div class="ws-connect">
      <p class="ws-connect-title ws-label"><?= $connected ? 'Connect more channels' : 'Connect channels' ?></p>
      <ul class="ws-connect-list" role="list">
<?php foreach ($suggested as $slug): $ch = $channels[$slug]; ?>
        <li><a class="ws-ch" href="/app/channels#connect-<?= e($slug) ?>" style="--c: <?= e($ch['color']) ?>" title="Connect <?= e($ch['name']) ?>" data-connect-open><?= brand_mark($ch['icon'], 'Connect ' . $ch['name']) ?></a></li>
<?php endforeach; ?>
        <li><a class="ws-ch ws-ch--more" href="/app/channels#connect" title="Connect a new channel" data-connect-open><?= icon('plus', 'icon', 'Connect a new channel') ?></a></li>
      </ul>
    </div>

    <div class="ws-side-foot">
<?php if ($onboarding['checklist_closed_at'] === null): ?>
      <div class="ws-progress" data-dismissible>
        <a class="ws-progress-link" href="/app/channels"><?= $allowance['paid'] ? count($connected) . ' channels connected' : count($connected) . '/' . $channelLimit . ' channels connected' ?></a>
<?php if (!$allowance['paid']): ?>
        <span class="ws-progress-bar" aria-hidden="true"><?php for ($i = 0; $i < $channelLimit; $i++): ?><i<?= $i < count($connected) ? ' class="is-on"' : '' ?>></i><?php endfor; ?></span>
<?php endif; ?>
        <form action="/app/checklist/close" method="post" data-quiet-form>
          <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
          <button type="submit" class="ws-progress-close"><?= icon('x') ?><span class="sr-only">Hide channel progress</span></button>
        </form>
      </div>
<?php endif; ?>
      <div class="ws-account">
        <button type="button" class="ws-org" aria-expanded="false" aria-controls="ws-account-menu" data-account-toggle>
          <span class="ws-avatar" aria-hidden="true"><?= e($initial) ?></span>
          <span class="ws-org-text ws-label"><b>My workspace</b><small><?= e($allowance['name']) ?> plan</small></span>
        </button>
        <button type="button" class="ws-icon-btn ws-collapse" aria-pressed="false" data-ws-collapse><?= icon('sidebar-simple') ?><span class="sr-only">Collapse sidebar</span></button>
        <div class="ws-account-menu" id="ws-account-menu" hidden>
          <p class="ws-account-email"><?= e($user['email']) ?></p>
          <a href="/app/account"<?= aria_current('/app/account') ?>><?= icon('user') ?>Account</a>
<?php if (Auth::isAdmin()): ?>
          <a href="/admin"><?= icon('tray') ?>Messages</a>
          <a href="/admin/subscribers"><?= icon('envelope-simple') ?>Subscribers</a>
<?php endif; ?>
          <div class="ws-account-theme"><span>Theme</span><?php partial('partials/theme-toggle'); ?></div>
          <a href="/">atwebtech.us</a>
          <form action="/logout" method="post">
            <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
            <button type="submit"><?= icon('sign-out') ?>Log out</button>
          </form>
        </div>
      </div>
    </div>
  </aside>
  <div class="ws-scrim" hidden data-ws-scrim></div>

  <main class="ws-main" id="main" tabindex="-1">
<?php partial($body, get_defined_vars()); ?>
  </main>

  <a class="ws-help" href="/faq" title="Help and FAQ"><?= icon('question') ?><span class="sr-only">Help and FAQ</span></a>
  <p class="ws-toast" role="status" aria-live="polite" data-toast<?= $notice ? '' : ' hidden' ?>><?= e((string) $notice) ?></p>

  <dialog class="ws-dialog ws-dialog--cp" id="compose-dialog" aria-labelledby="dc-title" data-dialog="compose">
<?php partial('workspace/composer', ['user' => $user, 'onboarding' => $onboarding, 'prefill' => [], 'dialog' => true]); ?>
  </dialog>
  <dialog class="ws-dialog ws-dialog--wide" id="connect-dialog" aria-labelledby="connect-title" data-dialog="connect">
    <header class="composer-head cg-head">
      <h2 id="connect-title">Connect a New Channel</h2>
      <button type="button" class="ws-icon-btn" data-dialog-close><?= icon('x') ?><span class="sr-only">Close</span></button>
    </header>
<?php partial('workspace/connect-grid', ['connectedCount' => count($connected)]); ?>
  </dialog>
  <dialog class="ws-dialog ws-dialog--sm" id="idea-dialog" aria-labelledby="idea-title" data-dialog="idea">
<?php partial('workspace/idea-form', ['user' => $user, 'idea' => null, 'group' => 0, 'dialog' => true]); ?>
  </dialog>
</div>
