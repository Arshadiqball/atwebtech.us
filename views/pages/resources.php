<?php $articles = data('articles'); ?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Resources</p>
    <h1 class="h-page">Practical guides for everyday social work.</h1>
    <p class="lead">Straightforward advice on planning, writing, and measuring social content, whichever tools you use.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php partial('partials/resource-bento', ['articles' => $articles]); ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="h-section">All guides</h2></div>
    <div class="card-grid card-grid--2">
<?php foreach ($articles as $slug => $a): ?>
      <a class="link-card" href="/resources/<?= e($slug) ?>">
        <span class="link-card-icon panel--<?= e($a['panel']) ?>"><?= icon($a['icon']) ?></span>
        <h3 class="h-small"><?= e($a['title']) ?></h3>
        <p><?= e($a['description']) ?></p>
        <span class="link-arrow"><?= e($a['category']) ?> · <?= e($a['read']) ?><?= icon('arrow-right') ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'resources']); ?>
