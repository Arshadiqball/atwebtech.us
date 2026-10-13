<?php
/** @var array $connected */
$all = array_column(integrations(), null, 'slug');
?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'plus', 'title' => 'Channels', 'actions' => '']); ?>

<?php if ($connected): ?>
  <section class="chl" aria-labelledby="chl-title">
    <h2 id="chl-title">Connected <small><?php $plan = Channels::allowance(); ?><?= $plan['paid'] ? count($connected) . ' on the ' . e($plan['name']) . ' plan' : count($connected) . '/' . (int) $plan['limit'] . ' on the free plan' ?></small></h2>
    <ul class="chl-list" role="list">
<?php foreach ($connected as $c): ?>
      <li class="chl-row">
        <?= channel_avatar($c, 'md') ?>
        <span class="chl-text">
          <a href="/app/channels/<?= (int) $c['id'] ?>"><b><?= e($c['name']) ?></b></a>
          <small><?= e($all[$c['provider']]['name'] ?? $c['provider']) ?> <?= e($c['kind']) ?> · <?= $c['posting_goal'] ? e((int) $c['posting_goal'] . ' ' . ((int) $c['posting_goal'] === 1 ? 'post' : 'posts') . ' a week') : 'No posting goal yet' ?></small>
        </span>
        <a class="ws-btn ws-btn--sm" href="/app/channels/<?= (int) $c['id'] ?>">Open</a>
      </li>
<?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

  <section class="chl" id="connect" aria-labelledby="connect-page-title">
    <h2 id="connect-page-title">Connect a New Channel</h2>
    <p class="wsp-muted">Connecting lets atwebtech read your channel's name and picture. Publishing, comments and analytics aren't built yet, so nothing is posted or collected.</p>
<?php partial('workspace/connect-grid', ['connectedCount' => count($connected)]); ?>
  </section>
</div>
