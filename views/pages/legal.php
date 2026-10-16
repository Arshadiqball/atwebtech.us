<?php
/** @var string $doc */
$legal = data('legal')[$doc];
$draft = !empty($legal['draft']);
?>
<article class="section">
  <div class="container article-wrap">
<?php if ($draft): ?>
    <div class="draft-banner" role="note">
      <?= icon('warning-circle') ?>
      <p><b>Draft for review.</b> This document has not yet been reviewed or approved. Bracketed items such as [Legal entity name] must be completed by the site owner, and the full text reviewed by qualified counsel, before launch.</p>
    </div>
<?php endif; ?>
    <h1 class="h-page"><?= e($legal['title']) ?></h1>
    <p class="article-meta"><?= $draft ? 'Status: draft · Last updated: [Date of approval]' : 'Last updated: ' . e((string) ($legal['updated'] ?? '')) ?></p>
    <div class="prose" style="margin-top: 32px">
      <?= $legal['body'] ?>
    </div>
  </div>
</article>

<?php partial('partials/cta-final', ['source' => 'page']); ?>
