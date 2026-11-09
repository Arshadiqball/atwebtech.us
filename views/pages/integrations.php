<?php
$order = ['available' => 0, 'beta' => 1, 'planned' => 2];
$channels = integrations(true);
usort($channels, static fn (array $a, array $b): int => $order[$a['status']] <=> $order[$b['status']]);
$count = array_count_values(array_column($channels, 'status')) + ['available' => 0, 'beta' => 0, 'planned' => 0];
$notes = [
    'available' => 'Connect and publish today.',
    'beta' => 'Connect your account today. Scheduled posts are saved and go out once publishing is switched on.',
    'planned' => 'On our roadmap.',
];
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Integrations</p>
    <h1 class="h-page">Connect the channels you post to.</h1>
    <p class="lead">Plan and schedule for every network your audience uses. Each channel shows exactly what works today.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <ul class="int-summary" role="list">
      <li><span class="status status--available">Available</span><b><?= $count['available'] ?></b> connect and publish</li>
<?php if ($count['beta'] > 0): ?>
      <li><span class="status status--beta">Publishing soon</span><b><?= $count['beta'] ?></b> connect now, posting next</li>
<?php endif; ?>
      <li><span class="status status--planned">Coming soon</span><b><?= $count['planned'] ?></b> on the roadmap</li>
    </ul>
    <p class="int-legal">atwebtech is not affiliated with or endorsed by these platforms. Names and logos belong to their owners.</p>
  </div>
</section>

<section class="section int-list-sec" aria-labelledby="int-list-title">
  <div class="container">
    <h2 class="sr-only" id="int-list-title">Channel status</h2>
    <ul class="int-grid" role="list">
<?php foreach ($channels as $ch): ?>
      <li class="int-card int-card--<?= e($ch['status']) ?>" id="<?= e($ch['slug']) ?>">
        <span class="ch ch--lg" style="--c: <?= e($ch['color']) ?>" title="<?= e($ch['name']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span>
        <div>
          <h3><?= e($ch['name']) ?></h3>
          <span class="status status--<?= e($ch['status']) ?>"><?= e(integration_label($ch['status'])) ?></span>
        </div>
        <p>Formats: <?= e($ch['formats']) ?>.</p>
        <p class="int-note"><?= e($notes[$ch['status']]) ?></p>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section">
  <div class="container detail-rows">
    <div class="detail-row">
      <h2 class="h-card">How a channel becomes available</h2>
      <p>Each platform has its own developer program, permissions, and review process. A channel moves from Coming soon to Publishing soon when you can connect it, and to Available when atwebtech can post to it for you. Some platforms also review each app before it can post for the public. This page updates on its own as each step goes live.</p>
    </div>
    <div class="detail-row">
      <h2 class="h-card">Missing a channel you rely on?</h2>
      <p>Tell us which channels matter most to you. Requests help us decide what to build next. <a href="/contact">Send a request through the contact form</a>.</p>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'page']); ?>
