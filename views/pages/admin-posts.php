<?php
/** @var array $user */
/** @var array $result from AdminAccounts::posts() */
/** @var array $filter */
/** @var ?array $owner */
/** @var ?array $channel */
$notice = flash('notice');
$val = static fn (string $k): string => (string) ($filter[$k] ?? '');
$query = static fn (array $extra = []): string => http_build_query(array_filter(array_merge(
    ['status' => $val('status'), 'q' => $val('q'), 'user' => $val('user'), 'channel' => $val('channel')], $extra
)));
$pageUrl = static fn (int $p): string => '/admin/posts?' . $query(['page' => $p > 1 ? $p : null]);
$self = $pageUrl($result['page']);
$self = rtrim($self, '?');
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'posts']); ?>
  <div class="crm-wrap">
    <header class="crm-head">
      <div>
        <h1>Posts</h1>
        <p><?= number_format($result['total']) ?> <?= $result['total'] === 1 ? 'post' : 'posts' ?><?= $owner ? ' by ' . e((string) $owner['email']) : '' ?><?= $channel ? ' on ' . e((string) $channel['name']) : '' ?>. Failed and sending posts are listed first.</p>
      </div>
    </header>
<?php $beat = AdminAccounts::heartbeat(); ?>
<?php if ($beat['minutes'] === null || $beat['minutes'] > 5): ?>
    <p class="crm-notice crm-notice--warn" role="alert"><b>Scheduled posts aren’t going out.</b> The publisher <?= $beat['at'] ? 'last ran ' . e(Admin::when($beat['at']->format('Y-m-d H:i:s'))->format('j M, g:i A')) . ' UTC' : 'has not run yet' ?>. Check the every-minute cron job in DirectAdmin (it runs bin/publish.php with PHP 8.2).</p>
<?php else: ?>
    <p class="crm-fine">Publisher last ran <?= $beat['minutes'] === 0 ? 'less than a minute' : (int) $beat['minutes'] . ' min' ?> ago (PHP <?= e($beat['php']) ?>).</p>
<?php endif; ?>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
    <form class="crm-filters" method="get" action="/admin/posts">
      <input class="crm-input" type="search" name="q" value="<?= e($val('q')) ?>" placeholder="Search post text or owner email" aria-label="Search">
      <select class="crm-input" name="status" aria-label="Status">
        <option value="">Any status</option>
<?php foreach (AdminAccounts::STATUSES as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $val('status') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
      </select>
<?php if ($val('user') !== ''): ?><input type="hidden" name="user" value="<?= e($val('user')) ?>"><?php endif; ?>
<?php if ($val('channel') !== ''): ?><input type="hidden" name="channel" value="<?= e($val('channel')) ?>"><?php endif; ?>
      <button class="crm-btn crm-btn--dark" type="submit">Filter</button>
<?php if (array_filter([$val('status'), $val('q'), $val('user'), $val('channel')])): ?>
      <a class="crm-link" href="/admin/posts">Clear</a>
<?php endif; ?>
    </form>
<?php if ($result['rows']): ?>
    <?php partial('partials/admin-bulk-bar', [
        'id' => 'bulk-posts', 'action' => '/admin/posts/bulk', 'back' => $self, 'total' => $result['total'], 'noun' => 'posts',
        'filters' => ['f_status' => $val('status'), 'f_q' => $val('q'), 'f_user' => $val('user'), 'f_channel' => $val('channel')],
        'actions' => [
        ['disable', 'Disable', 'Move the selected posts back to drafts? They will not be published until scheduled again.'],
        ['schedule', 'Schedule', 'Schedule the selected posts at their saved times? Posts whose time has passed go out on the next publishing run.'],
        ['delete', 'Delete', 'Delete the selected posts? This cannot be undone. Posts already published stay on the networks.'],
    ],
    ]); ?>
    <?php partial('partials/admin-post-table', ['posts' => $result['rows'], 'back' => $self, 'bulk' => 'bulk-posts']); ?>
<?php if ($result['pages'] > 1): ?>
    <nav class="crm-pages" aria-label="Pages">
<?php if ($result['page'] > 1): ?><a class="crm-btn" href="<?= e($pageUrl($result['page'] - 1)) ?>">Previous</a><?php endif; ?>
      <span>Page <?= $result['page'] ?> of <?= $result['pages'] ?></span>
<?php if ($result['page'] < $result['pages']): ?><a class="crm-btn" href="<?= e($pageUrl($result['page'] + 1)) ?>">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?php else: ?>
    <p class="crm-empty">No posts found.</p>
<?php endif; ?>
  </div>
</div>
