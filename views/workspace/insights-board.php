<?php
/** @var array $insights */
/** @var string $base  URL without query */
/** @var array $user */
/** @var ?array $channel */
$range = $insights['range'];
$ranges = ['7d' => '7 Days', '30d' => '30 Days', 'mtd' => 'Month to Date', 'last-month' => 'Last Month', 'custom' => 'Custom'];
$from = $insights['from'];
$to = $insights['to'];
$label = $from->format('j M Y') . ' – ' . $to->format('j M Y');
$prev = $insights['prev_from']->format('j M') . ' – ' . $insights['prev_to']->format('j M Y');
$rangeHref = static function (string $key) use ($base): string {
    if ($key === '30d') {
        return $base;
    }
    return $base . '?range=' . rawurlencode($key);
};
$delta = $insights['sent'] - $insights['prev_sent'];
$series = $insights['series'];
$max = max(1, ...array_column($series, 'n'));
$w = 640;
$h = 180;
$pad = 28;
$points = [];
$n = max(1, count($series) - 1);
foreach (array_values($series) as $i => $pt) {
    $x = $pad + ($n === 0 ? 0 : $i / $n) * ($w - $pad * 2);
    $y = $h - $pad - ($pt['n'] / $max) * ($h - $pad * 2);
    $points[] = round($x, 1) . ',' . round($y, 1);
}
$target = isset($channel) ? 'c:' . (int) $channel['id'] : '';
$compose = e(json_encode($target !== '' ? ['targets' => [$target]] : []));
$soon = [
    'Followers' => 'Follower counts from the network aren’t available.',
    'Reactions' => 'Reactions aren’t collected.',
    'Comments' => 'Comments aren’t collected.',
    'Eng. Rate' => 'Engagement rate needs reactions and impressions we don’t have.',
    'Impressions' => 'Impressions aren’t collected.',
    'Shares' => 'Shares aren’t collected.',
    'Reach' => 'Reach isn’t collected.',
    'Video views' => 'Video views aren’t collected.',
    'Watch time' => 'Watch time isn’t collected.',
    'Unique viewers' => 'Unique viewers aren’t collected.',
];
?>
  <section class="in-path" aria-labelledby="in-path-title">
    <div>
      <h2 id="in-path-title">A path to growth with weekly takeaways</h2>
      <p>The more you post, the more we can show you. atwebtech only counts posts it sent. Network analytics (followers, reactions, impressions) are not available.</p>
      <a class="ws-btn ws-btn--green" href="/app/publish/new<?= $target !== '' ? '?target=' . e($target) : '' ?>" data-compose="<?= $compose ?>">Create Post</a>
    </div>
    <ul class="in-tips" role="list">
      <li>
        <a href="/app/publish?tab=drafts">
          <?= icon('note-pencil') ?>
          <b>Use your drafts</b>
          <span><?= $insights['drafts'] === 0 ? 'You have no drafts waiting.' : ($insights['drafts'] === 1 ? 'You have 1 draft to schedule.' : 'You have ' . $insights['drafts'] . ' drafts to schedule.') ?></span>
        </a>
      </li>
      <li>
        <a href="/app/create">
          <?= icon('lightbulb') ?>
          <b>Evergreen ideas</b>
          <span>Pull something from your idea board and schedule it.</span>
        </a>
      </li>
    </ul>
  </section>

  <div class="in-toolbar">
    <div>
      <h2 class="in-all">All Insights</h2>
      <nav class="ws-seg ws-seg--range" aria-label="Date range">
<?php foreach ($ranges as $key => $name): ?>
        <a href="<?= e($rangeHref($key)) ?>"<?= $range === $key ? ' aria-current="page"' : '' ?>><?= e($name) ?><?= $key === 'custom' ? icon('caret-down') : '' ?></a>
<?php endforeach; ?>
      </nav>
<?php if ($range === 'custom'): ?>
      <form class="in-custom" method="get" action="<?= e($base) ?>">
        <input type="hidden" name="range" value="custom">
        <label>From <input class="idea-input" type="date" name="from" value="<?= e((string) ($_GET['from'] ?? $from->format('Y-m-d'))) ?>"></label>
        <label>To <input class="idea-input" type="date" name="to" value="<?= e((string) ($_GET['to'] ?? $to->format('Y-m-d'))) ?>"></label>
        <button type="submit" class="ws-btn ws-btn--sm">Apply</button>
      </form>
<?php endif; ?>
    </div>
  </div>

  <section class="in-panel" aria-labelledby="in-summary">
    <h2 id="in-summary">Summary <small><?= e($label) ?> · compared to <?= e($prev) ?></small></h2>
    <ul class="in-cards in-cards--wide" role="list">
      <li class="in-card">
        <span class="in-card-head">Posts
          <span class="tip"><button type="button" class="tip-btn" aria-describedby="in-tip-posts" aria-label="About Posts"><?= icon('info') ?></button>
          <span class="tip-body" role="tooltip" id="in-tip-posts">Posts atwebtech published in this period. Plans that were never sent are not counted.</span></span>
        </span>
        <b class="in-value"><?= (int) $insights['sent'] ?></b>
        <span class="in-trend"><?= $delta === 0 ? 'Same as the previous period' : (($delta > 0 ? '+' : '') . $delta . ' vs the previous period') ?></span>
      </li>
<?php foreach ($soon as $name => $why): ?>
      <li class="in-card is-soon">
        <span class="in-card-head"><?= e($name) ?> <span class="ws-soon">Coming soon</span>
          <span class="tip"><button type="button" class="tip-btn" aria-describedby="in-tip-<?= e(strtolower(preg_replace('/[^a-z]+/i', '-', $name))) ?>" aria-label="About <?= e($name) ?>"><?= icon('info') ?></button>
          <span class="tip-body" role="tooltip" id="in-tip-<?= e(strtolower(preg_replace('/[^a-z]+/i', '-', $name))) ?>"><?= e($why) ?></span></span>
        </span>
        <b class="in-value">—</b>
        <span class="in-trend">Not collected</span>
      </li>
<?php endforeach; ?>
    </ul>
  </section>

  <section class="in-panel" aria-labelledby="in-perf">
    <h2 id="in-perf">Performance per Post <small><?= e($label) ?></small></h2>
<?php if ($insights['posts']): ?>
    <ul class="in-posts" role="list">
<?php foreach ($insights['posts'] as $post): ?>
      <li>
        <div>
          <p><?= e(mb_strlen((string) $post['body']) > 140 ? rtrim(mb_substr((string) $post['body'], 0, 140)) . '…' : ((string) $post['body'] !== '' ? $post['body'] : ($post['title'] ?? 'Untitled'))) ?></p>
          <small>Sent <?= e($post['sent_at'] ? Workspace::local((string) $post['sent_at'], Workspace::timezone($user))->format('j M Y, g:i A') : '') ?></small>
        </div>
<?php if ($post['external_url']): ?>
        <a class="ws-btn ws-btn--sm" href="<?= e($post['external_url']) ?>" target="_blank" rel="noopener noreferrer">View</a>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php else: ?>
    <div class="in-empty">
      <?= icon('magnifying-glass') ?>
      <p>No posts found in this period. Try another date range, or schedule something from the queue.</p>
    </div>
<?php endif; ?>
  </section>

  <section class="in-panel" aria-labelledby="in-metrics">
    <h2 id="in-metrics">Metrics <small>Posts atwebtech sent. Other lines stay Coming soon.</small></h2>
    <div class="in-chart" role="img" aria-label="Posts sent per day in this period">
      <svg viewBox="0 0 <?= $w ?> <?= $h ?>" width="100%" height="200">
        <line x1="<?= $pad ?>" y1="<?= $h - $pad ?>" x2="<?= $w - $pad ?>" y2="<?= $h - $pad ?>" stroke="currentColor" opacity=".15"/>
        <polyline fill="none" stroke="#2DA641" stroke-width="2" points="<?= e(implode(' ', $points)) ?>"/>
<?php foreach (array_values($series) as $i => $pt): if ($i % max(1, (int) ceil(count($series) / 6)) !== 0 && $i !== count($series) - 1) continue; ?>
        <text x="<?= explode(',', $points[$i])[0] ?>" y="<?= $h - 8 ?>" text-anchor="middle" font-size="11" fill="currentColor" opacity=".55"><?= e($pt['day']) ?></text>
<?php endforeach; ?>
      </svg>
      <p class="wsp-muted">Green line: posts sent. Followers and engagement are not on this chart.</p>
    </div>
  </section>
