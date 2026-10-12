<?php
/** @var array $feature */
/** @var string $slug */
$all = data('features');
$nav = array_column(data('site')['features'], null, 'href');
?>
<section class="page-hero">
  <div class="container">
    <a class="crumb" href="/features"><?= icon('arrow-left') ?>All features</a>
    <p class="eyebrow"><?= e($feature['eyebrow']) ?></p>
    <h1 class="h-page"><?= e($feature['title']) ?></h1>
    <p class="lead"><?= e($feature['intro']) ?></p>
    <div class="btn-row">
      <a class="btn btn--green" href="#join">Join the waitlist</a>
      <a class="btn btn--outline" href="/demo">View demo</a>
    </div>
  </div>
  <div class="container demo-showcase">
    <div class="showcase-card">
<?php if (!empty($feature['demo'])): ?>
      <div class="showcase-panel panel--<?= e($feature['panel']) ?>">
<?php if ($feature['demo'] === 'demos/ai-chat'): ?>
        <div class="ai-card"><div class="ai-visual"><?php partial($feature['demo']); ?></div><div class="ai-copy"><p class="eyebrow eyebrow--light">How it could work</p><ul class="ai-steps" role="list"><?php foreach ($feature['points'] as $p): ?><li><b><?= e($p['title']) ?>.</b> <?= e($p['text']) ?></li><?php endforeach; ?></ul></div></div>
<?php else: ?>
        <?php partial($feature['demo']); ?>
<?php endif; ?>
      </div>
<?php else: ?>
      <div class="showcase-panel showcase-panel--ill panel--<?= e($feature['panel']) ?>">
        <?php partial('demos/illustration', ['kind' => $feature['illustration']]); ?>
      </div>
<?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="notice" role="note">
      <?= icon('info') ?>
      <p><b>Current status</b><?= e($feature['status']) ?></p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="points">
<?php foreach ($feature['points'] as $p): ?>
      <div class="point">
        <span class="point-icon"><?= icon($p['icon']) ?></span>
        <h2 class="h-small"><?= e($p['title']) ?></h2>
        <p><?= e($p['text']) ?></p>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container detail-rows">
<?php foreach ($feature['details'] as $d): ?>
    <div class="detail-row">
      <h2 class="h-card"><?= e($d['title']) ?></h2>
      <p><?= e($d['text']) ?></p>
    </div>
<?php endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="h-section">Related features</h2></div>
    <div class="card-grid">
<?php foreach ($feature['related'] as $rel): $r = $all[$rel]; $n = $nav['/features/' . $rel] ?? null; ?>
      <a class="link-card" href="/features/<?= e($rel) ?>">
        <span class="link-card-icon panel--<?= e($r['panel']) ?>"><?= icon($n['icon'] ?? 'sparkle') ?></span>
        <h3 class="h-small"><?= e($r['label']) ?></h3>
        <p><?= e($r['intro']) ?></p>
        <span class="link-arrow">Explore <?= e(strtolower($r['label'])) ?><?= icon('arrow-right') ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'feature']); ?>
