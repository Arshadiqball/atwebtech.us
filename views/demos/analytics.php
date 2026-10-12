<?php
$demo = data('demo');
$ranges = $demo['analytics'];
?>
<div class="demo demo-analytics app-window" data-analytics>
  <div class="demo-bar">
    <div class="demo-bar-start">
      <?php partial('partials/logo-mark', ['class' => 'app-logo']); ?>
      <strong>Analytics</strong>
      <div class="seg" role="group" aria-label="Date range">
<?php foreach ($ranges as $key => $range): ?>
        <button type="button" aria-pressed="<?= (string) $key === '30' ? 'true' : 'false' ?>" data-range-btn="<?= e($key) ?>"><?= e($key) ?> days</button>
<?php endforeach; ?>
      </div>
    </div>
    <?php partial('partials/demo-label', ['text' => 'Illustrative data']); ?>
  </div>

<?php foreach ($ranges as $key => $r):
    $growth = Charts::line($r['growth'], 400, 140, 6);
    $bars = Charts::bars($r['engagement'], 400, 130, (string) $key === '30' ? 6 : 8);
    $maxRate = max(array_column($r['channels'], 'rate'));
    $maxPost = max(array_column($r['top_posts'], 'rate'));
    $last = end($growth['points']);
?>
  <div class="an-panel" data-range-panel="<?= e($key) ?>"<?= (string) $key === '30' ? '' : ' hidden' ?>>
    <dl class="kpis">
<?php foreach ($r['kpis'] as $kpi): ?>
      <div class="kpi">
        <dt><?= e($kpi['label']) ?></dt>
        <dd><span class="kpi-value"><?= e($kpi['value']) ?></span><span class="delta"><?= icon('trend-up') ?><?= e($kpi['delta']) ?></span></dd>
      </div>
<?php endforeach; ?>
    </dl>
    <div class="charts">
      <figure class="chart">
        <figcaption><b>Audience growth</b><small>Total followers, all channels</small></figcaption>
        <svg viewBox="0 0 400 140" class="chart-svg" role="img" aria-label="Followers grew from <?= e(number_format($r['growth'][0])) ?> to <?= e(number_format(end($r['growth']))) ?> over the <?= e(strtolower($r['label'])) ?>.">
          <line x1="0" x2="400" y1="35" y2="35" class="grid"/><line x1="0" x2="400" y1="70" y2="70" class="grid"/><line x1="0" x2="400" y1="105" y2="105" class="grid"/>
          <path d="<?= e($growth['area']) ?>" class="area"/>
          <path d="<?= e($growth['line']) ?>" class="line"/>
          <circle cx="<?= e($last[0]) ?>" cy="<?= e($last[1]) ?>" r="4.5" class="line-dot"/>
        </svg>
        <div class="axis"><?php foreach ($r['growth_axis'] as $a): ?><span><?= e($a) ?></span><?php endforeach; ?></div>
      </figure>

      <figure class="chart">
        <figcaption><b>Engagement over time</b><small><?= (string) $key === '30' ? 'Engagements per 2 days' : 'Engagements per week' ?></small></figcaption>
        <svg viewBox="0 0 400 130" class="chart-svg" role="img" aria-label="Engagement ranged from <?= e(min($r['engagement'])) ?> to <?= e(max($r['engagement'])) ?> per period.">
<?php foreach ($bars as $i => $b): ?>
          <rect x="<?= e($b['x']) ?>" y="<?= e($b['y']) ?>" width="<?= e($b['w']) ?>" height="<?= e($b['h']) ?>" rx="3" class="bar<?= $i === count($bars) - 1 ? ' bar--hi' : '' ?>"><title><?= e($b['v']) ?> engagements</title></rect>
<?php endforeach; ?>
        </svg>
        <div class="axis"><?php foreach ($r['engagement_axis'] as $a): ?><span><?= e($a) ?></span><?php endforeach; ?></div>
      </figure>

      <figure class="chart">
        <figcaption><b>Content performance</b><small>Top posts by engagement rate</small></figcaption>
        <ol class="perf-list">
<?php foreach ($r['top_posts'] as $p): ?>
          <li>
            <?= channel_icon($p['channel'], 'ch--xs') ?>
            <span class="perf-title"><?= e($p['title']) ?></span>
            <span class="perf-bar" style="--w: <?= e(round($p['rate'] / $maxPost * 100)) ?>%"></span>
            <span class="perf-val"><?= e(number_format($p['rate'], 1)) ?>%</span>
          </li>
<?php endforeach; ?>
        </ol>
      </figure>

      <figure class="chart">
        <figcaption><b>Channel comparison</b><small>Engagement rate and posts per channel</small></figcaption>
        <ul class="chan-list" role="list">
<?php foreach ($r['channels'] as $c): ?>
          <li>
            <span class="chan-name"><?= channel_icon($c['channel'], 'ch--xs') ?><?= e($demo['channels'][$c['channel']]['name']) ?></span>
            <span class="chan-bar chan-bar--<?= e($c['channel']) ?>" style="--w: <?= e(round($c['rate'] / $maxRate * 100)) ?>%"></span>
            <span class="chan-val"><?= e(number_format($c['rate'], 1)) ?>% <small><?= e($c['posts']) ?> posts</small></span>
          </li>
<?php endforeach; ?>
        </ul>
      </figure>
    </div>
  </div>
<?php endforeach; ?>
</div>
