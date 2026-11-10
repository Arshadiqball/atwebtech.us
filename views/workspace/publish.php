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
/** @var array $approvals */
/** @var ?array $reviewLink */
/** @var array $failed */
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
      <a href="/app/publish?tab=approvals"<?= $tab === 'approvals' ? ' aria-current="page"' : '' ?>>Approvals <span class="wsp-count<?= $counts['review'] > 0 ? ' wsp-count--warn' : '' ?>"><?= $counts['review'] ?></span></a>
      <a href="/app/publish?tab=failed"<?= $tab === 'failed' ? ' aria-current="page"' : '' ?>>Failed <span class="wsp-count<?= $counts['failed'] > 0 ? ' wsp-count--bad' : '' ?>"><?= $counts['failed'] ?></span></a>
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

<?php if ($tab === 'queue' && $counts['failed'] > 0): ?>
  <a class="wsp-alert" href="/app/publish?tab=failed"><?= icon('warning-circle') ?><span><b><?= $counts['failed'] ?> <?= $counts['failed'] === 1 ? 'post' : 'posts' ?> failed to publish.</b> Fix and retry them in the Failed tab.</span><?= icon('arrow-right') ?></a>
<?php endif; ?>
<?php if ($tab === 'queue' && $counts['review'] > 0): ?>
  <a class="wsp-alert wsp-alert--info" href="/app/publish?tab=approvals"><?= icon('check-circle') ?><span><b><?= $counts['review'] ?> <?= $counts['review'] === 1 ? 'post is' : 'posts are' ?> waiting for approval.</b> They publish only after they are approved.</span><?= icon('arrow-right') ?></a>
<?php endif; ?>
<?php if ($tab === 'queue'): ?>
  <p class="wsp-note"><?= icon('info') ?><span><?= $publishing ? 'Posts for connected channels publish automatically at their time. Posts for networks you haven’t connected, and for TikTok, are plans only and are never sent.' : 'Connect a channel to publish automatically. Until then, everything in your queue is a plan and is never sent.' ?></span></p>
<?php endif; ?>

<?php if ($tab === 'queue' && $view === 'list'): ?>
<?php $days = max(2, min(14, (int) ($_GET['days'] ?? 2))); ?>
<?php partial('workspace/queue-list', ['user' => $user, 'queue' => $queue, 'filter' => $channel, 'more' => $days < 14 ? $url(['days' => min(14, $days + 5)]) : null]); ?>


<?php elseif ($tab === 'queue'): ?>
<?php partial('workspace/calendar', ['user' => $user, 'week' => $week, 'url' => $url, 'target' => $channel !== '' ? 'n:' . $channel : '', 'filter' => $channel]); ?>

<?php elseif ($tab === 'drafts' || $tab === 'sent'): $listed = array_filter($drafts, static fn (array $p): bool => $shown($p['channel'])); ?>
<?php if ($tab === 'drafts' && $listed): ?>
  <div class="ap-head">
    <div>
      <h2><?= count($listed) ?> <?= count($listed) === 1 ? 'draft' : 'drafts' ?></h2>
      <p class="ap-sub">Open a draft to finish and schedule it, or clear out the ones you no longer need.</p>
    </div>
    <form action="/app/posts" method="post">
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
      <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '/app/publish?tab=drafts') ?>">
      <input type="hidden" name="status" value="draft">
<?php foreach ($listed as $p): ?>
      <input type="hidden" name="ids[]" value="<?= (int) $p['id'] ?>">
<?php endforeach; ?>
      <button type="submit" class="ws-btn ws-btn--danger" name="action" value="delete_all" data-confirm="Delete all <?= count($listed) ?> drafts shown here? This can’t be undone."><?= icon('trash') ?>Delete all</button>
    </form>
  </div>
<?php endif; ?>
<?php partial('workspace/post-list', ['user' => $user, 'posts' => $listed, 'kind' => $tab]); ?>

<?php elseif ($tab === 'approvals'): ?>
<?php partial('workspace/approvals', ['user' => $user, 'posts' => array_filter($approvals, static fn (array $p): bool => $shown($p['channel'])), 'reviewLink' => $reviewLink]); ?>

<?php else: ?>
<?php partial('workspace/failed', ['user' => $user, 'posts' => array_filter($failed, static fn (array $p): bool => $shown($p['channel']))]); ?>
<?php endif; ?>
</div>
