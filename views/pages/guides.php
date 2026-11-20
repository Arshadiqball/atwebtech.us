<?php /** @var array $guides */ ?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Video guides</p>
    <h1 class="h-page">Learn atwebtech in short videos.</h1>
    <p class="lead">Eight narrated videos, each with written steps and a PDF. Follow the first three in order and your first post is scheduled in about ten minutes.</p>
    <div class="btn-row">
      <a class="btn btn--green btn--lg" href="/signup">Start free<?= icon('arrow-right') ?></a>
      <a class="btn btn--lg" href="<?= e(asset('guides/atwebtech-user-guide.pdf')) ?>" download><?= icon('file-text') ?>Download all as PDF</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="gd gd--public">
<?php partial('partials/guide-list', ['guides' => $guides, 'base' => '/guide']); ?>
      <p class="gd-help"><?= icon('lifebuoy') ?>Still stuck? <a href="/contact">Write to us</a> and a person will answer.</p>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'guide']); ?>
