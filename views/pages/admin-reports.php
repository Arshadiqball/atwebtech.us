<?php
/** @var array $user */
/** @var list<array> $rows */
$notice = flash('notice');
?>
<div class="adm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'reports']); ?>
  <div class="adm-kb adm-push">
    <header class="adm-push-head">
      <h1>Reported comments</h1>
      <p>Comments people reported from their inbox, in the app or on the website. A reported comment is removed from that person’s inbox straight away. Look at each one within 24 hours: if it breaks a network’s rules, report it on that network too, and block the account if needed.</p>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="adm-push-note is-ok" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if (!$rows): ?>
    <p class="adm-push-empty">No reports.</p>
<?php else: ?>
    <ul class="adm-push-history" role="list">
<?php foreach ($rows as $row): ?>
      <li>
        <b><?= e(Community::REPORT_REASONS[$row['reason']] ?? (string) $row['reason']) ?></b>
        <p><?= e(mb_strimwidth((string) $row['body'], 0, 600, '…')) ?></p>
        <small><?= e(Admin::when((string) $row['created_at'])->format('j M Y, g:i A')) ?> · <?= e(Community::NETWORKS[$row['network']] ?? ucfirst((string) $row['network'])) ?> · from <?= e((string) $row['author_name']) ?> · reported by <?= e((string) ($row['workspace_email'] ?? 'a deleted account')) ?><?php if ($row['item_url']): ?> · <a href="<?= e((string) $row['item_url']) ?>" rel="noopener noreferrer" target="_blank">open the post</a><?php endif; ?></small>
<?php if ($row['handled_at'] === null): ?>
        <form class="adm-report-act" action="/admin/reports" method="post">
          <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
          <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
          <button type="submit">Mark as handled</button>
        </form>
<?php else: ?>
        <small class="adm-report-done">Handled <?= e(Admin::when((string) $row['handled_at'])->format('j M Y')) ?></small>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </div>
</div>
