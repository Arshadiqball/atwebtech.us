<?php
/** @var array $guide */
/** @var int $number */
/** @var array|null $previous */
/** @var array|null $next */
?>
<section class="section">
  <div class="container">
    <article class="gd gd--public gd--one">
      <p class="gd-crumb"><a href="/guide"><?= icon('arrow-left') ?>All video guides</a></p>
      <span class="gd-num">Guide <?= $number ?> · <?= e($guide['level']) ?></span>
      <h1 class="gd-title"><?= e($guide['title']) ?></h1>
      <p class="gd-lead"><?= e($guide['summary']) ?></p>
      <p class="gd-acts"><a class="btn" href="<?= e(asset('guides/' . $guide['slug'] . '.pdf')) ?>" download><?= icon('file-text') ?>Download PDF</a></p>
<?php partial('partials/guide-steps', ['guide' => $guide, 'previous' => $previous, 'next' => $next, 'home' => '/guide']); ?>
    </article>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'guide']); ?>
