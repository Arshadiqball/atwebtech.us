<?php
/** @var array $guide */
/** @var int $number */
/** @var array|null $previous */
/** @var array|null $next */
$base = 'guides/' . $guide['slug'];
ob_start(); ?>
    <a class="ws-btn ws-btn--sm" href="<?= e(asset($base . '.pdf')) ?>" download><?= icon('file-text') ?>Download PDF</a>
    <a class="ws-btn ws-btn--sm" href="/app/guide"><?= icon('arrow-left') ?>All guides</a>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'book-open', 'title' => 'Guide', 'actions' => $actions]); ?>
  <article class="gd gd--one">
    <span class="gd-num">Guide <?= $number ?> · <?= e($guide['level']) ?></span>
    <h2 class="gd-title"><?= e($guide['title']) ?></h2>
    <p class="gd-lead"><?= e($guide['summary']) ?></p>

<?php partial('partials/guide-steps', ['guide' => $guide, 'previous' => $previous, 'next' => $next, 'home' => '/app/guide']); ?>
  </article>
</div>
