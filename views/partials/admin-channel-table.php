<?php
/** @var list<array> $channels */
/** @var string $back the page to return to after an action */
/** @var bool $showOwner */
/** @var ?string $bulk bulk form id; adds a checkbox column */
$showOwner ??= true;
$bulk ??= null;
?>
<div class="crm-table-wrap crm-card">
  <table class="crm-table">
    <thead><tr><?php if ($bulk): ?><th class="crm-check"><span class="sr-only">Select</span></th><?php endif; ?><th>Channel</th><?php if ($showOwner): ?><th>Owner</th><?php endif; ?><th>Scheduled</th><th>Sent</th><th>Health</th><th>Publishing</th></tr></thead>
    <tbody>
<?php foreach ($channels as $c): ?>
      <tr>
<?php if ($bulk): ?>
        <td class="crm-check"><input type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" form="<?= e($bulk) ?>" data-bulk-item aria-label="Select <?= e((string) $c['name']) ?>"></td>
<?php endif; ?>
        <td><a class="crm-who" href="/admin/posts?channel=<?= (int) $c['id'] ?>"><b><?= e((string) $c['name']) ?></b><small><?= e(ucfirst((string) $c['provider'])) ?><?= $c['kind'] ? ' · ' . e((string) $c['kind']) : '' ?> · #<?= (int) $c['id'] ?></small></a></td>
<?php if ($showOwner): ?>
        <td><a href="/admin/users/<?= (int) $c['user_id'] ?>"><?= e((string) $c['email']) ?></a></td>
<?php endif; ?>
        <td><?= (int) $c['scheduled'] ?></td>
        <td><?= (int) $c['sent'] ?></td>
        <td><?php $err = trim((string) ($c['stats_error'] ?: $c['inbox_error'])); ?><?= $err !== '' ? '<small title="' . e($err) . '">' . e(mb_strimwidth($err, 0, 60, '…')) . '</small>' : '<small>OK</small>' ?></td>
        <td>
          <form class="crm-inline" method="post" action="/admin/channels/<?= (int) $c['id'] ?>/pause">
            <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
            <input type="hidden" name="back" value="<?= e($back) ?>">
<?php if ($c['paused_at'] !== null): ?>
            <span class="crm-status crm-status--lost">Paused</span>
            <input type="hidden" name="pause" value="0">
            <button class="crm-btn crm-btn--sm" type="submit">Resume</button>
<?php else: ?>
            <span class="crm-status crm-status--won">Active</span>
            <input type="hidden" name="pause" value="1">
            <button class="crm-btn crm-btn--sm" type="submit" data-confirm="Pause <?= e((string) $c['name']) ?>? Nothing will be published to it until it is resumed.">Pause</button>
<?php endif; ?>
          </form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
