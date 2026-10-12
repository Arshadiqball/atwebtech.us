<?php
/** @var string $section */
/** @var array $info */
?>
<div class="ws-page">
  <header class="ws-section-head">
    <h1><?= e($info['label']) ?></h1>
    <span class="ws-soon">Coming soon</span>
  </header>

<?php if ($section === 'channels'): ?>
  <p class="ws-lead"><?= e($info['text']) ?></p>
  <ul class="ws-channels" role="list">
<?php foreach (integrations() as $ch): ?>
    <li class="ws-channel" id="<?= e($ch['slug']) ?>">
      <span class="ws-ch ws-ch--lg" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span>
      <span class="ws-channel-text"><b><?= e($ch['name']) ?></b><small><?= e($ch['formats']) ?></small></span>
      <span class="status status--<?= e($ch['status']) ?>"><?= $ch['status'] === 'available' ? 'Available' : 'Planned' ?></span>
    </li>
<?php endforeach; ?>
  </ul>
  <p class="ws-fine">Connecting a channel isn't possible yet. <a href="/integrations">Read about integration status</a>.</p>
<?php else: ?>
  <div class="ws-empty ws-empty--feature">
    <span class="ws-empty-icon"><?= icon(data('workspace')['sections'][$section]['icon']) ?></span>
    <h2><?= e($info['title']) ?></h2>
    <p><?= e($info['text']) ?></p>
    <div class="ws-empty-actions">
<?php if ($info['demo']): ?>
      <a class="ws-btn ws-btn--dark" href="<?= e($info['demo']) ?>">Try it in the demo<?= icon('arrow-up-right') ?></a>
<?php endif; ?>
      <a class="ws-btn" href="<?= e($info['feature']) ?>">About this feature</a>
    </div>
  </div>
<?php endif; ?>
</div>
