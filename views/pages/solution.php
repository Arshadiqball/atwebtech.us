<?php
/** @var array $solution */
$features = data('features');
$nav = array_column(data('site')['features'], null, 'href');
$others = array_filter(data('site')['solutions'], static fn ($s) => $s['href'] !== '/solutions/' . $slug);
?>
<section class="page-hero">
  <div class="container">
    <a class="crumb" href="/solutions"><?= icon('arrow-left') ?>All solutions</a>
    <p class="eyebrow">For <?= e(strtolower($solution['label'])) ?></p>
    <h1 class="h-page"><?= e($solution['title']) ?></h1>
    <p class="lead"><?= e($solution['intro']) ?></p>
    <div class="btn-row">
      <a class="btn btn--green" href="#join">Join the waitlist</a>
      <a class="btn btn--outline" href="/demo">View demo</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="h-section">Where atwebtech is designed to help</h2></div>
    <div class="points">
<?php foreach ($solution['challenges'] as $i => $c): ?>
      <div class="point">
        <span class="point-icon panel--<?= e($solution['panel']) ?>"><?= icon(['warning-circle', 'clock', 'chats-circle'][$i] ?? 'check') ?></span>
        <h3 class="h-small"><?= e($c['title']) ?></h3>
        <p><?= e($c['text']) ?></p>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="h-section">Features that fit</h2><p>Each feature below includes an interactive demonstration or illustration of how it is designed to work.</p></div>
    <div class="card-grid card-grid--4">
<?php foreach ($solution['features'] as $fs): $f = $features[$fs]; ?>
      <a class="link-card" href="/features/<?= e($fs) ?>">
        <span class="link-card-icon panel--<?= e($f['panel']) ?>"><?= icon($nav['/features/' . $fs]['icon'] ?? 'sparkle') ?></span>
        <h3 class="h-small"><?= e($f['label']) ?></h3>
        <p><?= e($f['intro']) ?></p>
        <span class="link-arrow">Learn more<?= icon('arrow-right') ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="notice" role="note">
      <?= icon('info') ?>
      <p><b>Current status</b>atwebtech is in development. You can explore the <a href="/demo">interactive demo</a> and join the waitlist today; connecting social accounts and publishing are not available yet.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="h-section">Also designed for</h2></div>
    <div class="card-grid">
<?php foreach ($others as $o): ?>
      <a class="link-card" href="<?= e($o['href']) ?>">
        <span class="link-card-icon" style="background: var(--sand)"><?= icon($o['icon']) ?></span>
        <h3 class="h-small"><?= e($o['label']) ?></h3>
        <p><?= e($o['desc']) ?></p>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'solution']); ?>
