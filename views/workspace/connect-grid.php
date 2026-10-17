<?php
/** @var int $connectedCount */
$token = Security::csrfToken();
$allowance = Channels::allowance();
$full = $connectedCount >= $allowance['limit'];
$linked = array_column(Channels::forUser((int) Auth::member()['id']), 'provider');
?>
<?php if ($full): ?>
<p class="cg-note"><?= icon('info') ?><span>The <?= e($allowance['name']) ?> plan includes <?= (int) $allowance['limit'] ?> channels, and you've connected all of them. Disconnect one to add another.</span></p>
<?php endif; ?>
<ul class="cg" role="list">
<?php foreach (integrations() as $ch): $live = Channels::connectable($ch['slug']); ?>
  <li id="connect-<?= e($ch['slug']) ?>">
<?php if ($live && !$full): ?>
    <form action="/app/channels/connect/<?= e($ch['slug']) ?>" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <button type="submit" class="cg-card">
<?php if (in_array($ch['slug'], $linked, true)): ?><span class="ws-chip cg-chip cg-chip--on">Connected</span><?php endif; ?>
        <span class="cg-icon" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span>
        <b><?= e($ch['name']) ?></b><small><?= e($ch['accounts']) ?></small>
      </button>
    </form>
<?php else: ?>
    <div class="cg-card is-disabled" aria-disabled="true">
      <span class="ws-chip cg-chip"><?= $live ? 'Limit reached' : 'Soon' ?></span>
      <span class="cg-icon" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span>
      <b><?= e($ch['name']) ?></b><small><?= e($ch['accounts']) ?></small>
    </div>
<?php endif; ?>
  </li>
<?php endforeach; ?>
  <li>
    <div class="cg-card is-disabled" aria-disabled="true">
      <span class="ws-chip cg-chip">Soon</span>
      <span class="cg-icon" style="--c: #3E8E5C"><?= icon('link-simple', 'brand-glyph') ?></span>
      <b>Start Page</b><small>Simple link-in-bio page</small>
    </div>
  </li>
  <li>
    <a class="cg-card cg-card--request" href="/contact">
      <span class="cg-icon cg-icon--plain"><?= icon('plus', 'brand-glyph') ?></span>
      <b>Can't find it?</b><small>Request a channel</small>
    </a>
  </li>
</ul>
