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
$m = $insights['metrics'];
$names = array_column(integrations(), 'name', 'slug');
$networkList = static function (array $slugs) use ($names): string {
    $list = array_map(static fn (string $p): string => $names[$p] ?? ucfirst($p), $slugs);
    return count($list) > 1 ? implode(', ', array_slice($list, 0, -1)) . ' and ' . end($list) : (string) ($list[0] ?? '');
};
$cards = [
    'followers' => ['Followers', 'Followers across your connected channels today. The change is since the start of this period.'],
    'reactions' => ['Reactions', 'Likes and reactions on posts atwebtech sent in this period.'],
    'comments' => ['Comments', 'Comments and replies on posts atwebtech sent in this period.'],
    'eng_rate' => ['Eng. Rate', 'Reactions, comments, shares and saves divided by impressions (or video views).'],
    'impressions' => ['Impressions', 'How many times your posts were seen, as each network reports views.'],
    'shares' => ['Shares', 'Shares, reposts and quotes of your posts.'],
    'reach' => ['Reach', 'Unique accounts that saw your posts.'],
    'video_views' => ['Video views', 'Views of your videos.'],
    'watch_time' => ['Watch time', 'Total time people spent watching your videos.'],
    'unique_viewers' => ['Unique viewers', 'Unique people who watched your videos.'],
];
$fmt = static function (string $key, $v): string {
    if ($v === null) {
        return '—';
    }
    if ($key === 'eng_rate') {
        return rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%';
    }
    if ($key === 'watch_time') {
        $s = (int) $v;
        return $s >= 3600 ? floor($s / 3600) . 'h ' . floor($s % 3600 / 60) . 'm' : ($s >= 60 ? floor($s / 60) . 'm ' . $s % 60 . 's' : $s . 's');
    }
    $v = (int) $v;
    return $v >= 1000000 ? round($v / 1000000, 1) . 'M' : ($v >= 10000 ? round($v / 1000, 1) . 'K' : number_format($v));
};
$m['now']['watch_time'] = $m['now']['watch_seconds'];
$m['prev']['watch_time'] = $m['prev']['watch_seconds'];

?>
  <section class="in-path" aria-labelledby="in-path-title">
    <div>
      <h2 id="in-path-title">A path to growth with weekly takeaways</h2>
      <p>The more you post, the more we can show you. Followers and post stats come from each network and update every few hours<?= $m['checked'] ? ' (last checked ' . e(Workspace::local((string) $m['checked'], Workspace::timezone($user))->format('j M, g:i A')) . ')' : '' ?>. Stats cover posts atwebtech sent.</p>
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
<?php foreach ($cards as $key => [$name, $about]):
    $metricKey = $key === 'watch_time' ? 'watch_seconds' : ($key === 'eng_rate' ? 'impressions' : $key);
    $reporters = Metrics::reporters($metricKey, $m['networks']);
    $value = $m['now'][$key] ?? null;
    $before = $m['prev'][$key] ?? null;
    $tipId = 'in-tip-' . str_replace('_', '-', $key);
    if ($value !== null) {
        $diff = $before !== null ? $value - $before : null;
        $trend = $diff === null || $diff == 0 ? ($diff === null ? 'No earlier data to compare' : 'Same as the previous period')
            : (($diff > 0 ? '+' : '−') . ($key === 'eng_rate' ? rtrim(rtrim(number_format(abs($diff), 2), '0'), '.') . ' pts' : $fmt($key, abs($diff))) . ' vs the previous period');
    } elseif (!$m['networks']) {
        $trend = 'Connect a channel to see this';
    } elseif (!$reporters) {
        $trend = $networkList($m['networks']) . (count($m['networks']) > 1 ? ' don’t' : ' doesn’t') . ' report this';
    } elseif (array_filter($m['issues'], static fn (array $i): bool => in_array($i['channel']['provider'], $reporters, true))) {
        $trend = 'Needs a reconnect, see below';
    } elseif (in_array($key, ['video_views', 'watch_time', 'unique_viewers'], true)) {
        $trend = 'No video stats in this period';
    } else {
        $trend = 'No data for this period yet';
    }
    $about .= $reporters ? ' Reported by ' . $networkList($reporters) . '.' : '';
?>
      <li class="in-card<?= $value === null ? ' is-empty' : '' ?>">
        <span class="in-card-head"><?= e($name) ?>
          <span class="tip"><button type="button" class="tip-btn" aria-describedby="<?= e($tipId) ?>" aria-label="About <?= e($name) ?>"><?= icon('info') ?></button>
          <span class="tip-body" role="tooltip" id="<?= e($tipId) ?>"><?= e($about) ?></span></span>
        </span>
        <b class="in-value"><?= e($fmt($key, $value)) ?></b>
        <span class="in-trend"><?= e($trend) ?></span>
      </li>
<?php endforeach; ?>
    </ul>
<?php if ($m['issues']): ?>
    <ul class="in-issues" role="list">
<?php foreach ($m['issues'] as $issue): $ch = $issue['channel']; ?>
      <li>
        <?= icon('warning-circle') ?>
        <span><b><?= e($ch['name']) ?></b> (<?= e($names[$ch['provider']] ?? $ch['provider']) ?>): <?= e($issue['message']) ?></span>
<?php if (Channels::connectable($ch['provider'])): ?>
        <form action="/app/channels/connect/<?= e($ch['provider']) ?>" method="post">
          <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
          <input type="hidden" name="reconnect" value="1">
          <button type="submit" class="ws-btn ws-btn--sm"><?= icon('arrows-clockwise') ?>Reconnect</button>
        </form>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>

<?php $best = $insights['best']; $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$hourLabel = static fn (int $h): string => (new DateTimeImmutable('2000-01-01 ' . sprintf('%02d:00', $h)))->format('g A'); ?>
  <section class="in-panel" aria-labelledby="in-best">
    <h2 id="in-best">Best Times to Post <small>Last 90 days · <?= e(Workspace::timezoneLabel(Workspace::timezone($user))) ?></small></h2>
<?php if (!$best['ready']): ?>
    <div class="in-empty">
      <?= icon('clock') ?>
      <p>atwebtech needs stats from at least <?= Metrics::BEST_MIN_POSTS ?> posts<?= $best['posts'] > 0 ? ' (it has ' . (int) $best['posts'] . ' so far)' : '' ?>, with a few sent at the same hour, to find your best times. Keep posting at different times; stats update every few hours.</p>
    </div>
<?php else: ?>
    <ol class="bt-top" role="list">
<?php foreach ($best['top'] as $t): ?>
      <li><b><?= e($dayNames[$t['day']] . ' ' . $hourLabel($t['hour'])) ?></b><span><?= e(number_format($t['lift'], 1)) ?>× your usual engagement · <?= (int) $t['n'] ?> posts</span></li>
<?php endforeach; ?>
    </ol>
    <div class="bt-heat" role="table" aria-label="Average engagement by day and time">
      <div role="row" class="bt-row bt-row--head"><span role="columnheader"></span>
<?php foreach (['12–4 AM', '4–8 AM', '8 AM–12', '12–4 PM', '4–8 PM', '8 PM–12'] as $b): ?>
        <span role="columnheader"><?= e($b) ?></span>
<?php endforeach; ?>
      </div>
<?php foreach ($dayNames as $d => $dn): ?>
      <div role="row" class="bt-row"><span role="rowheader"><?= e($dn) ?></span>
<?php for ($b = 0; $b < 6; $b++): $cell = $best['heat'][$d][$b] ?? null; $v = $cell && $best['max'] > 0 ? min(1, $cell['score'] / $best['max']) : 0; ?>
        <span role="cell" class="bt-cell<?= $cell ? '' : ' is-empty' ?>" style="--v: <?= e(number_format($v, 2, '.', '')) ?>" title="<?= e($cell ? sprintf('%s %s: %s× usual engagement, %d %s', $dn, ['12–4 AM', '4–8 AM', '8 AM–12 PM', '12–4 PM', '4–8 PM', '8 PM–12 AM'][$b], number_format($cell['score'], 1), $cell['n'], $cell['n'] === 1 ? 'post' : 'posts') : 'No posts sent at this time') ?>"><span class="sr-only"><?= e($cell ? number_format($cell['score'], 1) . '×' : 'none') ?></span></span>
<?php endfor; ?>
      </div>
<?php endforeach; ?>
    </div>
    <p class="wsp-muted">Darker means more reactions, comments, shares and saves than your usual post on that channel.</p>
<?php if (isset($channel)): ?>
    <form class="bt-apply" action="/app/channels/<?= (int) $channel['id'] ?>/schedule" method="post">
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
<?php foreach ($best['top'] as $t): ?>
      <input type="hidden" name="times[<?= (int) $t['day'] ?>][]" value="<?= e(sprintf('%02d:00', $t['hour'])) ?>">
<?php endforeach; ?>
      <button type="submit" class="ws-btn ws-btn--sm"><?= icon('clock') ?>Use these times for the posting schedule</button>
      <small>Replaces this channel’s current posting times.</small>
    </form>
<?php else: ?>
    <p class="wsp-muted">To use these times for a channel’s posting schedule, open that channel’s Insights.</p>
<?php endif; ?>
<?php endif; ?>
  </section>

  <section class="in-panel" aria-labelledby="in-perf">
    <h2 id="in-perf">Performance per Post <small><?= e($label) ?></small></h2>
<?php if ($insights['posts']): ?>
    <ul class="in-posts" role="list">
<?php foreach ($insights['posts'] as $post): ?>
      <li>
        <div>
          <p><?= e(mb_strlen((string) $post['body']) > 140 ? rtrim(mb_substr((string) $post['body'], 0, 140)) . '…' : ((string) $post['body'] !== '' ? $post['body'] : ($post['title'] ?? 'Untitled'))) ?></p>
          <small>Sent <?= e($post['sent_at'] ? Workspace::local((string) $post['sent_at'], Workspace::timezone($user))->format('j M Y, g:i A') : '') ?> · <?= e($names[$post['channel']] ?? $post['channel']) ?></small>
<?php $pm = $insights['post_metrics'][$post['id']] ?? null; $chips = [];
if ($pm) {
    foreach (['impressions' => 'views', 'video_views' => 'video views', 'reach' => 'reached', 'reactions' => 'reactions', 'comments' => 'comments', 'shares' => 'shares', 'saves' => 'saves'] as $f => $word) {
        if ($pm[$f] !== null && !($f === 'video_views' && $pm['impressions'] !== null && $pm['impressions'] == $pm['video_views'])) {
            $chips[] = $fmt($f, $pm[$f]) . ' ' . $word;
        }
    }
} ?>
<?php if ($chips): ?>
          <span class="in-post-stats"><?= e(implode(' · ', $chips)) ?></span>
<?php elseif ($pm && $pm['error']): ?>
          <span class="in-post-stats"><?= e($pm['error']) ?></span>
<?php endif; ?>
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
    <h2 id="in-metrics">Metrics <small>Posts atwebtech sent per day</small></h2>
    <div class="in-chart" role="img" aria-label="Posts sent per day in this period">
      <svg viewBox="0 0 <?= $w ?> <?= $h ?>" width="100%" height="200">
        <line x1="<?= $pad ?>" y1="<?= $h - $pad ?>" x2="<?= $w - $pad ?>" y2="<?= $h - $pad ?>" stroke="currentColor" opacity=".15"/>
        <polyline fill="none" stroke="#2DA641" stroke-width="2" points="<?= e(implode(' ', $points)) ?>"/>
<?php foreach (array_values($series) as $i => $pt): if ($i % max(1, (int) ceil(count($series) / 6)) !== 0 && $i !== count($series) - 1) continue; ?>
        <text x="<?= explode(',', $points[$i])[0] ?>" y="<?= $h - 8 ?>" text-anchor="middle" font-size="11" fill="currentColor" opacity=".55"><?= e($pt['day']) ?></text>
<?php endforeach; ?>
      </svg>
      <p class="wsp-muted">Green line: posts sent. Follower and engagement totals are in the Summary above.</p>
    </div>
  </section>
