<?php
$demo = data('demo');
$posts = $demo['posts'];
$byDay = [];
foreach ($posts as $p) {
    $byDay[$p['day']][] = $p;
}
$first = $posts[0];
$month = $demo['month'];
$img = static fn (?string $name, int $size = 480): string => $name ? "/assets/img/posts/{$name}-{$size}.webp" : '';
?>
<div class="demo demo-cal app-window" data-calendar>
  <div class="demo-bar">
    <div class="demo-bar-start">
      <?php partial('partials/logo-mark', ['class' => 'app-logo']); ?>
      <div class="seg" role="group" aria-label="Calendar view">
        <button type="button" aria-pressed="true" data-view="week">Week</button>
        <button type="button" aria-pressed="false" data-view="month">Month</button>
      </div>
      <strong class="cal-range" data-range data-week-label="<?= e($demo['week']['label']) ?>" data-month-label="<?= e($month['label']) ?>"><?= e($demo['week']['label']) ?></strong>
    </div>
    <?php partial('partials/demo-label'); ?>
  </div>

  <div class="chip-row" role="group" aria-label="Filter posts by channel">
<?php foreach ($demo['channels'] as $slug => $ch): ?>
    <button type="button" class="chip" aria-pressed="true" data-filter="<?= e($slug) ?>"><?= channel_icon($slug, 'ch--xs') ?><?= e($ch['name']) ?></button>
<?php endforeach; ?>
    <span class="chip-legend"><span class="legend legend--scheduled">Scheduled</span><span class="legend legend--draft">Draft</span></span>
  </div>

  <div class="cal-layout">
    <div class="cal-views">
      <div class="cal-week" data-panel="week">
<?php foreach ($demo['week']['days'] as $i => [$dayName, $num]): ?>
        <div class="cal-day<?= $i === 0 ? ' is-today' : '' ?>">
          <div class="cal-day-head"><span><?= e($dayName) ?></span><b><?= e($num) ?></b></div>
          <ul class="cal-posts" role="list">
<?php foreach ($byDay[$i] ?? [] as $p): ?>
            <li data-channel="<?= e($p['channel']) ?>">
              <button type="button" class="post-card post-card--<?= e($p['channel']) ?> is-<?= e($p['status']) ?>"
                aria-pressed="<?= $p === $first ? 'true' : 'false' ?>"
                data-post
                data-title="<?= e($p['title']) ?>"
                data-text="<?= e($p['text']) ?>"
                data-when="<?= e($dayName . ' ' . $num . ', ' . $p['time']) ?>"
                data-channel="<?= e($p['channel']) ?>"
                data-channel-name="<?= e($demo['channels'][$p['channel']]['name']) ?>"
                data-status="<?= e($p['status']) ?>"
                data-image="<?= e($img($p['image'])) ?>">
                <span class="post-card-top"><?= channel_icon($p['channel'], 'ch--xs') ?><span class="post-time"><?= e($p['time']) ?></span></span>
<?php if ($p['image']): ?>
                <img src="<?= e($img($p['image'], 240)) ?>" alt="" width="240" height="240" loading="lazy" decoding="async">
<?php endif; ?>
                <span class="post-title"><?= e($p['title']) ?></span>
                <span class="state state--<?= e($p['status']) ?>"><?= $p['status'] === 'draft' ? 'Draft' : 'Scheduled' ?></span>
              </button>
            </li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endforeach; ?>
      </div>

      <div class="cal-month" data-panel="month" hidden>
        <div class="month-grid" role="grid" aria-label="<?= e($month['label']) ?> sample month">
          <div class="month-row month-row--head" role="row">
<?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
            <span role="columnheader"><?= e($d) ?></span>
<?php endforeach; ?>
          </div>
<?php
$cells = array_merge(array_fill(0, $month['first_weekday'], null), range(1, $month['days']));
while (count($cells) % 7) {
    $cells[] = null;
}
foreach (array_chunk($cells, 7) as $week): ?>
          <div class="month-row" role="row">
<?php foreach ($week as $day): ?>
            <div class="month-cell<?= $day === null ? ' is-empty' : '' ?><?= $day === $month['today'] ? ' is-today' : '' ?>" role="gridcell">
<?php if ($day !== null): ?>
              <span class="month-num"><?= $day ?></span>
              <span class="month-dots">
<?php foreach ($month['posts'][$day] ?? [] as $slug): ?>
                <span class="dot dot--<?= e($slug) ?>" data-channel="<?= e($slug) ?>" title="<?= e($demo['channels'][$slug]['name']) ?> post"></span>
<?php endforeach; ?>
              </span>
              <span class="sr-only"><?= count($month['posts'][$day] ?? []) ?> posts</span>
<?php endif; ?>
            </div>
<?php endforeach; ?>
          </div>
<?php endforeach; ?>
        </div>
      </div>
    </div>

    <aside class="cal-preview" aria-label="Post preview" data-preview>
      <p class="preview-label">Post preview</p>
      <div class="preview-card" aria-live="polite">
        <div class="preview-head">
          <span class="pv-avatar">MC</span>
          <span class="preview-who"><b>Marigold Ceramics</b><small data-pv-channel><?= e($demo['channels'][$first['channel']]['name']) ?></small></span>
          <span class="state state--<?= e($first['status']) ?>" data-pv-status><?= $first['status'] === 'draft' ? 'Draft' : 'Scheduled' ?></span>
        </div>
        <img class="preview-img" src="<?= e($img($first['image'])) ?>" alt="" width="480" height="480" loading="lazy" decoding="async" data-pv-image<?= $first['image'] ? '' : ' hidden' ?>>
        <p class="preview-title" data-pv-title><?= e($first['title']) ?></p>
        <p class="preview-text" data-pv-text><?= e($first['text']) ?></p>
        <p class="preview-when"><?= icon('clock') ?><span data-pv-when><?= e($demo['week']['days'][$first['day']][0] . ' ' . $demo['week']['days'][$first['day']][1] . ', ' . $first['time']) ?></span></p>
      </div>
      <div class="preview-actions">
        <button type="button" class="btn btn--outline btn--xs" disabled>Edit</button>
        <button type="button" class="btn btn--green btn--xs" disabled>Schedule</button>
        <span class="preview-note">Publishing is disabled in this demo.</span>
      </div>
    </aside>
  </div>
</div>
