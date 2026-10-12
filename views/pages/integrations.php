<?php
$channels = integrations();
$availableCount = count(array_filter($channels, static fn ($c) => $c['status'] === 'available'));
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Integrations</p>
    <h1 class="h-page">The channels we plan to support.</h1>
    <p class="lead">Each channel is listed with its current status. A channel is marked Available only once its integration has been built, tested, and approved where the platform requires it.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="notice" role="note">
      <?= icon('info') ?>
<?php if ($availableCount === 0): ?>
      <p><b>No channels are connected yet</b>atwebtech is in development, so every channel below is planned. atwebtech is not affiliated with or endorsed by these platforms; names and logos belong to their owners.</p>
<?php else: ?>
      <p><b><?= $availableCount ?> of <?= count($channels) ?> channels available</b>Planned channels are still in development. atwebtech is not affiliated with or endorsed by these platforms; names and logos belong to their owners.</p>
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
          <span class="status status--<?= e($ch['status']) ?>"><?= $ch['status'] === 'available' ? 'Available' : 'Planned' ?></span>
        </div>
        <p>Intended formats: <?= e($ch['formats']) ?>.</p>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section">
  <div class="container detail-rows">
    <div class="detail-row">
      <h2 class="h-card">How a channel becomes available</h2>
      <p>Each platform has its own developer program, permissions, and review process. A channel moves from Planned to Available after the connection is built, tested with real accounts, and approved by the platform where approval is required. Waitlist members will hear about new channels first.</p>
    </div>
    <div class="detail-row">
      <h2 class="h-card">Missing a channel you rely on?</h2>
      <p>Tell us which channels matter most to you. Requests help us decide what to build next. <a href="/contact">Send a request through the contact form</a>.</p>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'page']); ?>
