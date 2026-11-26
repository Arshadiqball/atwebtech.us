<?php
/**
 * Bulk actions for an admin listing. Row checkboxes join this form with form="<id>".
 * @var string $id      form id
 * @var string $action  POST URL
 * @var string $back    page to return to
 * @var int    $total   rows matching the current filter
 * @var string $noun    plural noun, e.g. "posts"
 * @var array<string,string> $filters  current filter as f_* fields, used for "all matching"
 * @var list<array{0:string,1:string,2:string}> $actions  [value, label, confirm text]
 */
$filters ??= [];
?>
<form class="crm-bulk" id="<?= e($id) ?>" method="post" action="<?= e($action) ?>" data-bulk>
  <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
  <input type="hidden" name="back" value="<?= e($back) ?>">
<?php foreach ($filters as $k => $v): if ($v !== ''): ?>
  <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
<?php endif; endforeach; ?>
  <label class="crm-bulk-all"><input type="checkbox" data-bulk-toggle aria-label="Select every <?= e($noun) ?> on this page"> <span data-bulk-count>None selected</span></label>
<?php if ($total > 0): ?>
  <label class="crm-bulk-scope"><input type="checkbox" name="scope" value="all" data-bulk-scope> All <?= number_format($total) ?> <?= e($noun) ?> matching this view</label>
<?php endif; ?>
  <div class="crm-actions">
<?php foreach ($actions as [$value, $label, $confirm]): ?>
    <button class="crm-btn crm-btn--sm<?= $value === 'delete' ? ' crm-btn--danger' : '' ?>" type="submit" name="action" value="<?= e($value) ?>" data-bulk-confirm="<?= e($confirm) ?>" disabled><?= e($label) ?></button>
<?php endforeach; ?>
  </div>
</form>
