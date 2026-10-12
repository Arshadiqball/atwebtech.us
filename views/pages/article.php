<?php
/** @var array $article */
/** @var string $slug */
$others = array_filter(data('articles'), static fn ($k) => $k !== $slug, ARRAY_FILTER_USE_KEY);
?>
<article class="section">
  <div class="container article-wrap">
    <a class="crumb" href="/resources"><?= icon('arrow-left') ?>All resources</a>
    <h1 class="h-page"><?= e($article['title']) ?></h1>
    <p class="lead" style="margin-top: 18px"><?= e($article['description']) ?></p>
    <div class="article-meta">
      <span class="tag"><?= e($article['category']) ?></span>
      <span><?= e($article['read']) ?></span>
      <span aria-hidden="true">·</span>
      <span>By the atwebtech team</span>
    </div>
    <div class="article-cover panel--<?= e($article['panel']) ?>" aria-hidden="true"></div>
    <div class="prose">
      <?= $article['body'] ?>
    </div>
  </div>
</article>

<section class="section">
  <div class="container">
    <div class="section-head"><h2 class="h-section">Keep reading</h2></div>
    <div class="card-grid">
<?php foreach ($others as $s => $a): ?>
      <a class="link-card" href="/resources/<?= e($s) ?>">
        <span class="link-card-icon panel--<?= e($a['panel']) ?>"><?= icon($a['icon']) ?></span>
        <h3 class="h-small"><?= e($a['title']) ?></h3>
        <p><?= e($a['summary']) ?></p>
        <span class="link-arrow">Read guide<?= icon('arrow-right') ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'resources']); ?>
