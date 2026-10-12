<?php
$demo = data('demo');
$posts = $demo['posts'];
$growth = $demo['analytics']['30']['growth'];
$spark = Charts::line($growth, 220, 56, 3);
$byDay = [];
foreach ($posts as $p) {
    $byDay[$p['day']][] = $p;
}
$heroChannels = ['instagram', 'tiktok', 'linkedin', 'facebook', 'threads'];
$queue = [$posts[0], $posts[2], $posts[3]];
?>
<div class="hero-stage" data-hero-demo>
  <div class="app-window hero-app">
    <div class="app-top">
      <div class="app-top-left">
        <?php partial('partials/logo-mark', ['class' => 'app-logo']); ?>
        <span class="ws-name"><span class="ws-avatar" aria-hidden="true">MC</span><?= e($demo['workspace']) ?></span>
        <?php partial('partials/demo-label'); ?>
      </div>
      <div class="app-tabs" aria-hidden="true">
        <span class="is-active"><?= icon('calendar-blank') ?>Calendar</span>
        <span><?= icon('tray') ?>Queue</span>
        <span><?= icon('lightbulb') ?>Ideas</span>
      </div>
    </div>
    <div class="app-body">
      <aside class="app-side">
        <p class="side-title" id="hero-channels">Channels</p>
        <ul class="platforms" role="list" aria-labelledby="hero-channels">
<?php foreach ($heroChannels as $slug): ?>
          <li>
            <button type="button" class="platform" aria-pressed="true" data-platform="<?= e($slug) ?>">
              <?= channel_icon($slug) ?>
              <span class="platform-name"><?= e($demo['channels'][$slug]['name']) ?></span>
              <span class="platform-check"><?= icon('check') ?></span>
            </button>
          </li>
<?php endforeach; ?>
        </ul>
        <p class="side-hint">Select channels to show or hide their posts.</p>
      </aside>
      <div class="app-main">
        <div class="mini-cal-head">
          <strong><?= e($demo['week']['label']) ?></strong>
          <span class="mini-cal-ctrl" aria-hidden="true"><?= icon('caret-left') ?><?= icon('caret-right') ?></span>
          <span class="seg seg--static" aria-hidden="true"><span class="is-on">Week</span><span>Month</span></span>
        </div>
        <div class="mini-week">
<?php foreach ($demo['week']['days'] as $i => [$dayName, $num]): ?>
          <div class="mini-day<?= $i === 0 ? ' is-today' : '' ?>">
            <div class="mini-day-head"><span><?= e($dayName) ?></span><b><?= e($num) ?></b></div>
<?php foreach ($byDay[$i] ?? [] as $p): ?>
            <div class="mini-post mini-post--<?= e($p['channel']) ?><?= $p['status'] === 'draft' ? ' is-draft' : '' ?>" data-channel="<?= e($p['channel']) ?>">
<?php if ($p['image']): ?>
              <img src="/assets/img/posts/<?= e($p['image']) ?>-240.webp" alt="" width="240" height="240" loading="lazy" decoding="async">
<?php endif; ?>
              <span class="mini-post-meta"><?= channel_icon($p['channel'], 'ch--xs') ?><?= e($p['time']) ?></span>
              <span class="mini-post-title"><?= e($p['title']) ?></span>
            </div>
<?php endforeach; ?>
          </div>
<?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="float float--preview" aria-hidden="true">
    <div class="pv-head"><span class="pv-avatar">MC</span><span><b>marigold.ceramics</b><small>Scheduled for Mon, 9:00 AM</small></span><?= channel_icon('instagram', 'ch--sm') ?></div>
    <img class="pv-img" src="/assets/img/posts/mugs-480.webp" alt="" width="480" height="480" decoding="async">
    <div class="pv-actions"><?= icon('heart') ?><?= icon('chat-circle-text') ?><?= icon('paper-plane-tilt') ?></div>
    <p class="pv-caption"><b>marigold.ceramics</b> Three new speckled glazes, made for slow autumn mornings. Which one is yours?</p>
  </div>

  <div class="float float--stats" aria-hidden="true">
    <div class="st-head"><span>Followers</span><small>Last 30 days</small></div>
    <div class="st-value">4,386 <span class="delta">+284</span></div>
    <svg class="spark" viewBox="0 0 220 56" preserveAspectRatio="none" aria-hidden="true">
      <path d="<?= e($spark['area']) ?>" class="spark-area"/>
      <path d="<?= e($spark['line']) ?>" class="spark-line"/>
    </svg>
  </div>

  <div class="float float--queue" aria-hidden="true">
    <div class="q-head"><b>Up next</b><span>3 posts</span></div>
<?php foreach ($queue as $p): ?>
    <div class="q-item">
      <?= channel_icon($p['channel'], 'ch--sm') ?>
      <span class="q-text"><b><?= e($p['title']) ?></b><small><?= e($demo['week']['days'][$p['day']][0]) ?>, <?= e($p['time']) ?></small></span>
    </div>
<?php endforeach; ?>
  </div>
</div>
