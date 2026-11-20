<?php
/** @var array $guides  From app/data/guides.php */
ob_start(); ?>
    <a class="ws-btn ws-btn--sm ws-btn--green" href="<?= e(asset('guides/atwebtech-user-guide.pdf')) ?>" download><?= icon('file-text') ?>Download all as PDF</a>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'book-open', 'title' => 'Guide', 'actions' => $actions]); ?>
  <div class="gd">
    <p class="gd-lead">Short videos and step-by-step instructions for everything in atwebtech. New here? Follow the first three in order and your first post will be scheduled in about ten minutes.</p>
<?php partial('partials/guide-list', ['guides' => $guides, 'base' => '/app/guide']); ?>
    <p class="gd-help"><?= icon('lifebuoy') ?>Still stuck? <a href="/contact">Write to us</a> and a person will answer.</p>
  </div>
</div>
