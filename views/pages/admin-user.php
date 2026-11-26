<?php
/** @var array $user */
/** @var array $account */
/** @var list<array> $channels */
/** @var array $posts from AdminAccounts::posts() */
$notice = flash('notice');
$self = '/admin/users/' . (int) $account['id'];
$tz = AdminAccounts::zone($account);
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'users']); ?>
  <div class="crm-wrap">
    <a class="crm-link crm-backlink" href="/admin/users"><?= icon('arrow-left') ?>All users</a>
    <header class="crm-head">
      <div>
        <h1><?= e((string) ($account['display_name'] ?: $account['email'])) ?></h1>
        <p><?= e((string) $account['email']) ?> · <?= e(Workspace::timezoneLabel($tz)) ?> · joined <?= e(Admin::when((string) $account['created_at'])->format('j M Y')) ?></p>
      </div>
      <a class="crm-btn" href="/admin/posts?user=<?= (int) $account['id'] ?>">All posts</a>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
    <h2>Channels (<?= count($channels) ?>)</h2>
<?php if ($channels): ?>
    <?php partial('partials/admin-channel-table', ['channels' => $channels, 'back' => $self, 'showOwner' => false]); ?>
<?php else: ?>
    <p class="crm-empty">No channels connected.</p>
<?php endif; ?>
    <h2>Posts (<?= number_format($posts['total']) ?>)</h2>
<?php if ($posts['rows']): ?>
    <?php partial('partials/admin-post-table', ['posts' => $posts['rows'], 'back' => $self, 'showOwner' => false]); ?>
<?php if ($posts['pages'] > 1): ?>
    <p><a class="crm-link" href="/admin/posts?user=<?= (int) $account['id'] ?>">See all <?= number_format($posts['total']) ?> posts</a></p>
<?php endif; ?>
<?php else: ?>
    <p class="crm-empty">No posts yet.</p>
<?php endif; ?>
  </div>
</div>
