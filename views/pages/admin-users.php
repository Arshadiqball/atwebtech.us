<?php
/** @var array $user */
/** @var list<array> $rows */
/** @var string $q */
$notice = flash('notice');
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'users']); ?>
  <div class="crm-wrap">
    <header class="crm-head">
      <div>
        <h1>Users</h1>
        <p><?= number_format(count($rows)) ?> <?= count($rows) === 1 ? 'account' : 'accounts' ?><?= $q !== '' ? ' match “' . e($q) . '”' : '' ?></p>
      </div>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
    <form class="crm-filters" method="get" action="/admin/users">
      <input class="crm-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search email or name" aria-label="Search">
      <button class="crm-btn crm-btn--dark" type="submit">Search</button>
<?php if ($q !== ''): ?><a class="crm-link" href="/admin/users">Clear</a><?php endif; ?>
    </form>
<?php if ($rows): ?>
    <div class="crm-table-wrap crm-card">
      <table class="crm-table">
        <thead><tr><th>User</th><th>Channels</th><th>Posts</th><th>Scheduled</th><th>Failed</th><th>Joined</th><th>Last sign-in</th></tr></thead>
        <tbody>
<?php foreach ($rows as $r): ?>
          <tr>
            <td><a class="crm-who" href="/admin/users/<?= (int) $r['id'] ?>"><b><?= e((string) ($r['display_name'] ?: $r['email'])) ?></b><small><?= e((string) $r['email']) ?><?= $r['parent_id'] ? ' · team member' : '' ?></small></a></td>
            <td><?= (int) $r['channels'] ?><?= (int) $r['paused'] > 0 ? '<small>' . (int) $r['paused'] . ' paused</small>' : '' ?></td>
            <td><?= (int) $r['posts'] ?></td>
            <td><?= (int) $r['scheduled'] ?></td>
            <td><?= (int) $r['failed'] > 0 ? '<a href="/admin/posts?user=' . (int) $r['id'] . '&amp;status=failed">' . (int) $r['failed'] . '</a>' : '0' ?></td>
            <td><?= e(Admin::when((string) $r['created_at'])->format('j M Y')) ?></td>
            <td><?= $r['last_login_at'] ? e(Admin::when((string) $r['last_login_at'])->format('j M Y')) : '<small>Never</small>' ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php else: ?>
    <p class="crm-empty">No users found.</p>
<?php endif; ?>
  </div>
</div>
