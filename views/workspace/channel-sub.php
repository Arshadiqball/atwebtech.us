<?php
/** @var array $channel */
/** @var string $sub  'community' | 'insights' */
$provider = array_column(integrations(), null, 'slug')[$channel['provider']] ?? ['name' => $channel['provider']];
$copy = $sub === 'community'
    ? ['chat-circle-text', 'Comments for ' . $channel['name'], 'Replying to ' . $provider['name'] . ' comments from atwebtech isn’t built yet. atwebtech only asks YouTube for permission to read your channel and upload videos, so it doesn’t see any comments.']
    : ['chart-bar', 'Insights for ' . $channel['name'], 'Analytics aren’t built yet. When they are, views and engagement for the posts atwebtech published will appear here.'];
?>
<div class="wsp">
  <header class="wsp-head">
    <div class="wsp-title">
      <?= channel_avatar($channel, 'md') ?>
      <div>
        <h1><?= e($channel['name']) ?></h1>
        <p class="ch-sub"><?= $sub === 'community' ? 'Community' : 'Insights' ?></p>
      </div>
    </div>
  </header>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon($copy[0]) ?></span>
    <h2><?= e($copy[1]) ?> <span class="ws-soon">Coming soon</span></h2>
    <p><?= e($copy[2]) ?></p>
    <a class="ws-btn" href="/app/channels/<?= (int) $channel['id'] ?>">Back to the queue</a>
  </div>
</div>
