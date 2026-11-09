<?php
/** @var array $user */
/** @var list<array> $due */
/** @var list<array> $upcoming */
/** @var list<array> $done */
$token = Security::csrfToken();
$notice = flash('notice');
$row = static function (array $f, bool $open) use ($token): void { $late = $open && $f['due_at'] < gmdate('Y-m-d H:i:s'); ?>
          <li class="crm-fu-row<?= $late ? ' is-late' : '' ?>">
            <a href="/admin/crm/contacts/<?= (int) $f['contact_id'] ?>"><b><?= e($f['title']) ?></b><small><?= e($f['name'] ?: $f['email']) ?></small></a>
            <span class="crm-due<?= $late ? ' is-late' : '' ?>"><?= $open ? ($late ? 'Overdue · ' : '') . e(Crm::local($f['due_at'], 'D j M, g:i A')) : 'Done ' . e(Crm::local((string) $f['done_at'], 'j M')) ?></span>
<?php if ($open): ?>
            <form method="post" action="/admin/crm/action">
              <input type="hidden" name="_token" value="<?= e($token) ?>">
              <input type="hidden" name="return" value="/admin/crm/followups">
              <input type="hidden" name="followup" value="<?= (int) $f['id'] ?>">
              <button class="crm-btn crm-btn--sm" name="action" value="done"><?= icon('check') ?>Done</button>
            </form>
<?php endif; ?>
          </li>
<?php };
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'followups']); ?>
  <div class="crm-wrap crm-wrap--narrow">
    <header class="crm-head">
      <div>
        <h1>Follow-ups</h1>
        <p>Schedule follow-ups from a contact's page. Overdue ones are shown first.</p>
      </div>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
    <section class="crm-card" aria-labelledby="fu-due">
      <h2 id="fu-due">Due now <small><?= count($due) ?></small></h2>
<?php if ($due): ?>
      <ul class="crm-fu-list" role="list"><?php foreach ($due as $f) { $row($f, true); } ?></ul>
<?php else: ?>
      <p class="crm-empty">You're all caught up.</p>
<?php endif; ?>
    </section>
    <section class="crm-card" aria-labelledby="fu-up">
      <h2 id="fu-up">Upcoming <small><?= count($upcoming) ?></small></h2>
<?php if ($upcoming): ?>
      <ul class="crm-fu-list" role="list"><?php foreach ($upcoming as $f) { $row($f, true); } ?></ul>
<?php else: ?>
      <p class="crm-empty">Nothing scheduled. Open a contact and choose a date to follow up.</p>
<?php endif; ?>
    </section>
<?php if ($done): ?>
    <section class="crm-card" aria-labelledby="fu-done">
      <h2 id="fu-done">Recently done</h2>
      <ul class="crm-fu-list crm-fu-list--done" role="list"><?php foreach ($done as $f) { $row($f, false); } ?></ul>
    </section>
<?php endif; ?>
  </div>
</div>
