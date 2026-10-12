<?php $solutions = data('solutions'); ?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Solutions</p>
    <h1 class="h-page">Designed for the people behind the posts.</h1>
    <p class="lead">Whether you post for yourself, your business, your clients, or your company, atwebtech is being built around the same goal: a clear plan you can keep.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="card-grid card-grid--2">
<?php foreach ($solutions as $slug => $s): ?>
      <a class="link-card" href="/solutions/<?= e($slug) ?>">
        <span class="link-card-icon panel--<?= e($s['panel']) ?>"><?= icon($s['icon']) ?></span>
        <h2 class="h-card"><?= e($s['label']) ?></h2>
        <p><?= e($s['intro']) ?></p>
        <span class="link-arrow">atwebtech for <?= e(strtolower($s['label'])) ?><?= icon('arrow-right') ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'solution']); ?>
