<?php
/** @var list<array> $posts */
/** @var string $back the page to return to after an action */
/** @var bool $showOwner */
$showOwner ??= true;
?>
<div class="crm-table-wrap crm-card">
  <table class="crm-table">
    <thead><tr><th>Post</th><th>Channel</th><?php if ($showOwner): ?><th>Owner</th><?php endif; ?><th>Status</th><th>Time (owner's zone)</th><th></th></tr></thead>
    <tbody>
<?php foreach ($posts as $p):
    $tz = AdminAccounts::zone($p);
    $at = AdminAccounts::local($p['status'] === 'sent' ? ($p['sent_at'] ?: $p['planned_at']) : $p['planned_at'], $tz);
    $text = trim((string) ($p['title'] ?: $p['body']));
?>
      <tr>
        <td><a class="crm-who" href="/admin/posts/<?= (int) $p['id'] ?>"><b><?= e($text !== '' ? mb_strimwidth($text, 0, 90, '…') : '(no text)') ?></b><small>#<?= (int) $p['id'] ?><?= $p['error'] ? ' · ' . e(mb_strimwidth((string) $p['error'], 0, 80, '…')) : '' ?></small></a></td>
        <td><?php if ($p['channel_id']): ?><?= e((string) $p['channel_name']) ?><small><?= e(ucfirst((string) $p['provider'])) ?><?= $p['paused_at'] !== null ? ' · paused' : '' ?></small><?php else: ?><small><?= e(ucfirst((string) ($p['channel'] ?: 'none'))) ?> (plan only)</small><?php endif; ?></td>
<?php if ($showOwner): ?>
        <td><a href="/admin/users/<?= (int) $p['user_id'] ?>"><?= e((string) $p['email']) ?></a></td>
<?php endif; ?>
        <td><span class="crm-status crm-status--<?= $p['status'] === 'failed' ? 'lost' : ($p['status'] === 'sent' ? 'won' : 'contacted') ?>"><?= e(AdminAccounts::STATUSES[$p['status']] ?? (string) $p['status']) ?></span></td>
        <td><?= $at ? e($at->format('j M Y, g:i A')) . '<small>' . e(Workspace::timezoneLabel($tz)) . '</small>' : '<small>No time</small>' ?></td>
        <td>
          <div class="crm-actions">
<?php if ($p['external_url']): ?>
            <a class="crm-btn crm-btn--sm" href="<?= e((string) $p['external_url']) ?>" target="_blank" rel="noopener noreferrer">View</a>
<?php endif; ?>
<?php if (!in_array($p['status'], ['sent', 'publishing'], true)): ?>
            <a class="crm-btn crm-btn--sm" href="/admin/posts/<?= (int) $p['id'] ?>">Edit</a>
<?php endif; ?>
<?php if ($p['status'] !== 'publishing'): ?>
            <form class="crm-inline" method="post" action="/admin/posts/<?= (int) $p['id'] ?>/delete">
              <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
              <input type="hidden" name="back" value="<?= e($back) ?>">
              <button class="crm-btn crm-btn--sm" type="submit" data-confirm="<?= $p['status'] === 'sent' ? 'Delete this post from atwebtech? It stays on the network; only atwebtech’s copy is removed.' : 'Delete this post? This cannot be undone.' ?>">Delete</button>
            </form>
<?php endif; ?>
          </div>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
