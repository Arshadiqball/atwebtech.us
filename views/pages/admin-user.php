<?php
/** @var array $user */
/** @var array $account */
/** @var list<array> $channels */
/** @var array $posts from AdminAccounts::posts() */
$notice = flash('notice');
$self = '/admin/users/' . (int) $account['id'];
$tz = AdminAccounts::zone($account);
$isAdmin = AdminAccounts::isAdminEmail((string) $account['email']);
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
      <div class="crm-actions">
<?php if ($account['disabled_at'] !== null): ?>
        <span class="crm-status crm-status--lost">Disabled</span>
<?php endif; ?>
        <a class="crm-btn" href="/admin/posts?user=<?= (int) $account['id'] ?>">All posts</a>
<?php if (!$isAdmin): ?>
        <form class="crm-inline" method="post" action="/admin/users/bulk">
          <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
          <input type="hidden" name="back" value="<?= e($self) ?>">
          <input type="hidden" name="ids[]" value="<?= (int) $account['id'] ?>">
<?php if ($account['disabled_at'] === null): ?>
          <button class="crm-btn" type="submit" name="action" value="disable" data-confirm="Disable this account? It is signed out everywhere, can't sign in, and its channels are paused.">Disable</button>
<?php else: ?>
          <button class="crm-btn" type="submit" name="action" value="enable">Enable</button>
<?php endif; ?>
          <button class="crm-btn crm-btn--danger" type="submit" name="action" value="delete" data-confirm="Delete this account and everything in it (channels, posts, media, client workspaces)? Any paid plan is cancelled. This cannot be undone.">Delete account</button>
        </form>
<?php endif; ?>
      </div>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
    <h2>Channels (<?= count($channels) ?>)</h2>
<?php if ($channels): ?>
    <?php partial('partials/admin-bulk-bar', [
        'id' => 'bulk-channels', 'action' => '/admin/channels/bulk', 'back' => $self, 'total' => count($channels), 'noun' => 'channels',
        'filters' => ['f_user' => (string) $account['id']],
        'actions' => [
        ['pause', 'Pause', 'Pause the selected channels? Nothing is published to them until they are resumed.'],
        ['resume', 'Resume', 'Resume the selected channels? Their due posts go out on the next publishing run.'],
        ['delete', 'Delete', 'Delete the selected channels? Their owners will have to connect them again. Posts are kept without a channel.'],
    ],
    ]); ?>
    <?php partial('partials/admin-channel-table', ['channels' => $channels, 'back' => $self, 'showOwner' => false, 'bulk' => 'bulk-channels']); ?>
<?php else: ?>
    <p class="crm-empty">No channels connected.</p>
<?php endif; ?>
    <h2>Posts (<?= number_format($posts['total']) ?>)</h2>
<?php if ($posts['rows']): ?>
    <?php partial('partials/admin-bulk-bar', [
        'id' => 'bulk-posts', 'action' => '/admin/posts/bulk', 'back' => $self, 'total' => $posts['total'], 'noun' => 'posts',
        'filters' => ['f_user' => (string) $account['id']],
        'actions' => [
        ['disable', 'Disable', 'Move the selected posts back to drafts? They will not be published until scheduled again.'],
        ['schedule', 'Schedule', 'Schedule the selected posts at their saved times? Posts whose time has passed go out on the next publishing run.'],
        ['delete', 'Delete', 'Delete the selected posts? This cannot be undone. Posts already published stay on the networks.'],
    ],
    ]); ?>
    <?php partial('partials/admin-post-table', ['posts' => $posts['rows'], 'back' => $self, 'showOwner' => false, 'bulk' => 'bulk-posts']); ?>
<?php if ($posts['pages'] > 1): ?>
    <p><a class="crm-link" href="/admin/posts?user=<?= (int) $account['id'] ?>">See all <?= number_format($posts['total']) ?> posts</a></p>
<?php endif; ?>
<?php else: ?>
    <p class="crm-empty">No posts yet.</p>
<?php endif; ?>
  </div>
</div>
