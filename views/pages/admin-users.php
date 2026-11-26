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
    <?php partial('partials/admin-bulk-bar', [
        'id' => 'bulk-users', 'action' => '/admin/users/bulk', 'back' => '/admin/users' . ($q !== '' ? '?q=' . rawurlencode($q) : ''), 'total' => count($rows), 'noun' => 'users',
        'filters' => ['f_q' => $q],
        'actions' => [
            ['disable', 'Disable', 'Disable the selected accounts? They are signed out everywhere, can\'t sign in, and their channels are paused. Admin accounts are skipped.'],
            ['enable', 'Enable', 'Enable the selected accounts? Channels paused by disabling them are resumed.'],
            ['delete', 'Delete', 'Delete the selected accounts and everything in them (channels, posts, media, client workspaces)? Paid plans are cancelled. Admin accounts are skipped. This cannot be undone.'],
        ],
    ]); ?>
    <div class="crm-table-wrap crm-card">
      <table class="crm-table">
        <thead><tr><th class="crm-check"><span class="sr-only">Select</span></th><th>User</th><th>Channels</th><th>Posts</th><th>Scheduled</th><th>Failed</th><th>Joined</th><th>Last sign-in</th></tr></thead>
        <tbody>
<?php foreach ($rows as $r): ?>
          <tr>
            <td class="crm-check"><?php if (!AdminAccounts::isAdminEmail((string) $r['email'])): ?><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="bulk-users" data-bulk-item aria-label="Select <?= e((string) $r['email']) ?>"><?php endif; ?></td>
            <td><a class="crm-who" href="/admin/users/<?= (int) $r['id'] ?>"><b><?= e((string) ($r['display_name'] ?: $r['email'])) ?></b><small><?= e((string) $r['email']) ?><?= $r['parent_id'] ? ' · client workspace' : '' ?><?= AdminAccounts::isAdminEmail((string) $r['email']) ? ' · admin' : '' ?></small></a><?php if ($r['disabled_at'] !== null): ?> <span class="crm-status crm-status--lost">Disabled</span><?php endif; ?></td>
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
