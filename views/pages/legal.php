<?php
/** @var string $doc */
$legal = data('legal')[$doc];
?>
<article class="section">
  <div class="container article-wrap">
    <div class="draft-banner" role="note">
      <?= icon('warning-circle') ?>
      <p><b>Draft for review.</b> This document has not yet been reviewed or approved. Bracketed items such as [Legal entity name] must be completed by the site owner, and the full text reviewed by qualified counsel, before launch.</p>
    </div>
    <h1 class="h-page"><?= e($legal['title']) ?></h1>
    <p class="article-meta">Status: draft · Last updated: [Date of approval]</p>
    <div class="prose" style="margin-top: 32px">
      <?= $legal['body'] ?>
    </div>
  </div>
</article>

<?php partial('partials/cta-final', ['source' => 'page']); ?>
