<?php
/** @var array $user */
/** @var array $groups */
/** @var int $limit */
$token = Security::csrfToken();
$form = View::formState('hashtags');
$old = $form['old'] ?? [];
$paid = Channels::allowance((int) $user['id'])['paid'];
?>
<section class="tk" aria-labelledby="hg-title">
  <header class="tk-head">
    <div>
      <h2 id="hg-title">Hashtag groups</h2>
      <p>Save the hashtags you use often, then insert a whole group from the <b>#</b> button in the composer.</p>
    </div>
    <span class="tk-count"><?= count($groups) ?> / <?= $paid ? 'unlimited' : $limit ?></span>
  </header>

<?php if ($form && $form['status'] === 'error'): ?>
  <p class="bill-warn"><?= icon('warning-circle') ?><?= e($form['message']) ?><?php if (!$paid && count($groups) >= $limit): ?> <a href="/app/billing">See plans</a><?php endif; ?></p>
<?php endif; ?>

<?php if ($paid || count($groups) < $limit): ?>
  <form class="tk-form" action="/app/hashtags" method="post">
    <input type="hidden" name="_token" value="<?= e($token) ?>">
    <label class="bio-field"><span>Group name</span><input class="idea-input" name="name" maxlength="60" required placeholder="e.g. Small business" value="<?= e((string) ($old['name'] ?? '')) ?>"></label>
    <label class="bio-field tk-grow"><span>Hashtags</span><input class="idea-input" name="tags" maxlength="1000" required placeholder="#smallbusiness #marketingtips #shoplocal" value="<?= e((string) ($old['tags'] ?? '')) ?>"></label>
    <button type="submit" class="ws-btn ws-btn--dark"><?= icon('plus') ?>Save group</button>
  </form>
<?php else: ?>
  <p class="bill-note"><?= icon('info') ?>You've saved <?= $limit ?> groups, the most on the Free plan. <a href="/app/billing">Upgrade</a> for unlimited groups, or edit one below.</p>
<?php endif; ?>

<?php if ($groups): ?>
  <ul class="tk-list" role="list">
<?php foreach ($groups as $g): ?>
    <li class="tk-item">
      <form class="tk-form tk-form--row" action="/app/hashtags" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
        <input class="idea-input" name="name" maxlength="60" required value="<?= e($g['name']) ?>" aria-label="Group name">
        <input class="idea-input tk-grow" name="tags" maxlength="1000" required value="<?= e($g['tags']) ?>" aria-label="Hashtags">
        <span class="tk-meta"><?= count(preg_split('/\s+/', trim((string) $g['tags'])) ?: []) ?> tags</span>
        <button type="submit" class="ws-btn ws-btn--sm">Save</button>
        <button type="submit" class="ws-icon-btn" name="action" value="delete" formnovalidate data-confirm="Delete this hashtag group?"><?= icon('trash') ?><span class="sr-only">Delete</span></button>
      </form>
    </li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('hash') ?></span>
    <h2>No hashtag groups yet</h2>
    <p>Add your first group above. Tip: keep 3 to 5 relevant hashtags per group for Instagram and LinkedIn.</p>
  </div>
<?php endif; ?>
</section>
