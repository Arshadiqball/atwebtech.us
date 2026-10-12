<?php
/** @var string $icon */
/** @var string $title */
/** @var string $actions  Pre-rendered HTML for the right side */
?>
<header class="wsp-head">
  <div class="wsp-title">
    <span class="wsp-tile" aria-hidden="true"><?= icon($icon) ?></span>
    <h1><?= e($title) ?></h1>
  </div>
  <div class="wsp-actions">
    <a class="ws-icon-btn wsp-feedback" href="/contact" title="Share feedback"><?= icon('megaphone') ?><span class="sr-only">Share feedback</span></a>
    <?= $actions ?>
  </div>
</header>
