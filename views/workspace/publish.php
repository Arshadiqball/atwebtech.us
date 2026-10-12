<?php
/** @var array $user */
/** @var array $onboarding */
/** @var string $tab */
/** @var string $view */
/** @var string $channel */
/** @var ?array $queue */
/** @var ?array $week */
/** @var array $drafts */
/** @var array $counts */
$token = Security::csrfToken();
$all = array_column(integrations(), null, 'slug');
$mine = Workspace::channels($onboarding);
$channel = isset($all[$channel]) ? $channel : '';
$tz = Workspace::timezone($user);
$url = static fn (array $params): string => '/app/publish?' . http_build_query(array_filter($params + ['tab' => $tab, 'view' => $view, 'channel' => $channel], static fn ($v) => $v !== '' && $v !== null && $v !== 'queue' && $v !== 'list'));
$mark = static function (string $slug) use ($all): string {
    $ch = $all[$slug] ?? null;
    return $ch ? '<span class="ws-ch ws-ch--sm" style="--c: ' . e($ch['color']) . '">' . brand_mark($ch['icon'], $ch['name']) . '</span>' : '';
};
$shown = static fn (string $slug): bool => $channel === '' || $slug === $channel;
$publishing = array_filter(Channels::forUser((int) $user['id']), static fn (array $c): bool => $c['can_publish']);

ob_start(); ?>
    <nav class="ws-seg" aria-label="Layout">
      <a href="<?= e($url(['tab' => 'queue', 'view' => 'list'])) ?>"<?= $tab === 'queue' && $view === 'list' ? ' aria-current="page"' : '' ?>><?= icon('rows') ?>List</a>
      <a href="<?= e($url(['tab' => 'queue', 'view' => 'calendar'])) ?>"<?= $tab === 'queue' && $view === 'calendar' ? ' aria-current="page"' : '' ?>><?= icon('calendar-blank') ?>Calendar</a>
    </nav>
    <a class="ws-btn ws-btn--dark" href="/app/publish/new" data-compose="{}"><?= icon('plus') ?>New Post</a>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'calendar-blank', 'title' => 'Publish', 'actions' => $actions]); ?>

  <div class="wsp-bar">
    <nav class="wsp-tabs" aria-label="Publish">
      <a href="/app/publish<?= $view === 'calendar' ? '?view=calendar' : '' ?>"<?= $tab === 'queue' ? ' aria-current="page"' : '' ?>>Queue <span class="wsp-count"><?= $counts['queue'] ?></span></a>
      <a href="/app/publish?tab=drafts"<?= $tab === 'drafts' ? ' aria-current="page"' : '' ?>>Drafts <span class="wsp-count"><?= $counts['drafts'] ?></span></a>
      <a href="/app/publish?tab=approvals"<?= $tab === 'approvals' ? ' aria-current="page"' : '' ?>>Approvals <span class="wsp-bolt" aria-hidden="true"><?= icon('lightning') ?></span></a>
      <a href="/app/publish?tab=sent"<?= $tab === 'sent' ? ' aria-current="page"' : '' ?>>Sent <span class="wsp-count"><?= $counts['sent'] ?></span></a>
    </nav>
    <div class="wsp-filters">
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn<?= $channel !== '' ? ' is-active' : '' ?>" aria-expanded="false" aria-controls="channel-menu" data-menu-toggle><?= e($channel !== '' ? $all[$channel]['name'] : 'Channels') ?><?= icon('caret-down') ?></button>
        <div class="ws-menu" id="channel-menu" hidden data-menu-panel>
          <a class="ws-menu-item" href="<?= e($url(['channel' => ''])) ?>"<?= $channel === '' ? ' aria-current="true"' : '' ?>>All channels</a>
<?php foreach ($all as $slug => $ch): ?>
          <a class="ws-menu-item" href="<?= e($url(['channel' => $slug])) ?>"<?= $slug === $channel ? ' aria-current="true"' : '' ?>><?= $mark($slug) ?><?= e($ch['name']) ?></a>
<?php endforeach; ?>
        </div>
      </div>
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn" aria-expanded="false" aria-controls="tz-menu" data-menu-toggle><?= icon('globe') ?><?= e(Workspace::timezoneLabel($tz)) ?><?= icon('caret-down') ?></button>
        <form class="ws-menu ws-menu--end ws-menu--form ws-menu-form" id="tz-menu" action="/account/timezone" method="post" hidden data-menu-panel>
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="return" value="<?= e($url([])) ?>">
          <label for="tz-select">Time zone</label>
          <select class="idea-input" id="tz-select" name="timezone">
<?php foreach (DateTimeZone::listIdentifiers() as $name): ?>
            <option<?= $name === $tz->getName() ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
          </select>
          <button type="submit" class="ws-btn ws-btn--sm ws-btn--green">Save</button>
        </form>
      </div>
    </div>
  </div>

<?php if ($tab === 'queue'): ?>
  <p class="wsp-note"><?= icon('info') ?><span><?= $publishing ? 'Posts for a connected YouTube channel or LinkedIn Profile publish automatically at their time. Posts for other networks are plans only and are never sent.' : 'Only connected YouTube channels and LinkedIn Profiles publish automatically. Everything else in your queue is a plan and is never sent.' ?></span></p>
<?php endif; ?>

<?php if ($tab === 'queue' && $view === 'list'): ?>
<?php $days = max(2, min(14, (int) ($_GET['days'] ?? 2))); ?>
<?php partial('workspace/queue-list', ['user' => $user, 'queue' => $queue, 'filter' => $channel, 'more' => $days < 14 ? $url(['days' => min(14, $days + 5)]) : null]); ?>


<?php elseif ($tab === 'queue'): ?>
<?php partial('workspace/calendar', ['user' => $user, 'week' => $week, 'url' => $url, 'target' => $channel !== '' ? 'n:' . $channel : '', 'filter' => $channel]); ?>

<?php elseif ($tab === 'drafts' || $tab === 'sent'): ?>
<?php partial('workspace/post-list', ['user' => $user, 'posts' => array_filter($drafts, static fn (array $p): bool => $shown($p['channel'])), 'kind' => $tab]); ?>

<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon wsp-empty-icon--purple"><?= icon('lightning') ?></span>
    <h2>Approvals <span class="ws-soon">Coming soon</span></h2>
    <p>Approvals will let teammates review posts before they go out. Teams aren't available yet.</p>
  </div>
<?php endif; ?>
</div>
