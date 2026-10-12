<?php
$features = data('features');
$nav = data('site')['features'];
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Features</p>
    <h1 class="h-page">One workspace for the whole content routine.</h1>
    <p class="lead">From the first idea to the conversation after a post goes live, atwebtech is designed to keep each step clear and connected.</p>
    <div class="btn-row">
      <a class="btn btn--green" href="#join">Join the waitlist</a>
      <a class="btn btn--outline" href="/demo">View demo</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="card-grid">
<?php foreach ($nav as $item): $slug = basename($item['href']); $f = $features[$slug]; ?>
      <a class="link-card" href="<?= e($item['href']) ?>">
        <span class="link-card-icon panel--<?= e($f['panel']) ?>"><?= icon($item['icon']) ?></span>
        <h2 class="h-small"><?= e($item['label']) ?></h2>
        <p><?= e($f['intro']) ?></p>
        <span class="link-arrow">Learn more<?= icon('arrow-right') ?></span>
      </a>
<?php endforeach; ?>
      <a class="link-card" href="/integrations">
        <span class="link-card-icon" style="background: var(--sand)"><?= icon('squares-four') ?></span>
        <h2 class="h-small">Integrations</h2>
        <p>See which social channels are planned and the current status of each connection.</p>
        <span class="link-arrow">See integration status<?= icon('arrow-right') ?></span>
      </a>
      <a class="link-card" href="/demo">
        <span class="link-card-icon" style="background: var(--ink); color: var(--bg)"><?= icon('eye') ?></span>
        <h2 class="h-small">Interactive demo</h2>
        <p>Try the calendar, board, inbox, analytics, and AI workflow with sample data.</p>
        <span class="link-arrow">Open the demo<?= icon('arrow-right') ?></span>
      </a>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'feature']); ?>
