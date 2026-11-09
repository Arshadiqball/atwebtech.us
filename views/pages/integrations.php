<?php
$channels = integrations();
$availableCount = count(array_filter($channels, static fn ($c) => $c['status'] === 'available'));
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Integrations</p>
    <h1 class="h-page">Connect the channels you post to.</h1>
    <p class="lead">Every channel is labeled honestly. Available means you can connect it and publish from atwebtech today. Coming soon means we are working on it.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="notice" role="note">
      <?= icon('info') ?>
<?php if ($availableCount === 0): ?>
      <p><b>New channels are coming soon</b>You can still plan ideas, draft posts, and build your calendar in a <a href="/signup">free account</a> today. atwebtech is not affiliated with or endorsed by these platforms; names and logos belong to their owners.</p>
<?php else: ?>
      <p><b><?= $availableCount ?> of <?= count($channels) ?> channels available now</b>Connect available channels from your <a href="/signup">free account</a>; the rest are coming soon. atwebtech is not affiliated with or endorsed by these platforms; names and logos belong to their owners.</p>
<?php endif; ?>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="int-list-title">
  <div class="container">
    <h2 class="sr-only" id="int-list-title">Channel status</h2>
    <ul class="int-grid" role="list">
<?php foreach ($channels as $ch): ?>
      <li class="int-card" id="<?= e($ch['slug']) ?>">
        <?= channel_icon($ch['slug'], 'ch--lg') ?>
        <div>
          <h3><?= e($ch['name']) ?></h3>
          <span class="status status--<?= e($ch['status']) ?>"><?= $ch['status'] === 'available' ? 'Available' : 'Coming soon' ?></span>
        </div>
        <p>Formats: <?= e($ch['formats']) ?>.</p>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section">
  <div class="container detail-rows">
    <div class="detail-row">
      <h2 class="h-card">How a channel becomes available</h2>
      <p>Each platform has its own developer program, permissions, and review process. A channel moves from Coming soon to Available once the connection is tested with real accounts and approved by the platform where approval is required. This page updates as each new channel goes live.</p>
    </div>
    <div class="detail-row">
      <h2 class="h-card">Missing a channel you rely on?</h2>
      <p>Tell us which channels matter most to you. Requests help us decide what to build next. <a href="/contact">Send a request through the contact form</a>.</p>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'page']); ?>
