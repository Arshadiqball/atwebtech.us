<?php
/** @var array $user */
/** @var array $in  Workspace::insights() */
/** @var DateTimeZone $tz */
/** @var array $names */
/** @var array $accounts */
/** @var string $file */
/** @var array $campaigns */
/** @var array $brand  ClientReports::brand() */
/** @var bool $shared  true on the client link (/r/{token}) */
$color = $brand['color'];
$title = $brand['name'] ?? (string) ($user['display_name'] ?? '');
$byNetwork = [];
foreach ($in['posts'] as $p) {
    $byNetwork[$names[$p['channel']] ?? $p['channel']] = ($byNetwork[$names[$p['channel']] ?? $p['channel']] ?? 0) + 1;
}
arsort($byNetwork);
$max = max(1, ...array_values(array_map(static fn ($d) => $d['n'], $in['series'])));
$change = $in['prev_sent'] > 0 ? round(($in['sent'] - $in['prev_sent']) / $in['prev_sent'] * 100) : null;
$m = $in['metrics']['now'];
$prev = $in['metrics']['prev'];
$num = static fn ($v): string => $v === null ? '—' : number_format((int) $v);
$delta = static function ($now, $before, bool $pts = false): string {
    if ($now === null || $before === null) {
        return '';
    }
    if ($pts) {
        $d = round((float) $now - (float) $before, 2);
        return ($d >= 0 ? '+' : '') . $d . ' pts';
    }
    if ((float) $before === 0.0) {
        return '';
    }
    $d = round(((float) $now - (float) $before) / (float) $before * 100);
    return ($d >= 0 ? '+' : '') . $d . '%';
};
$views = $m['impressions'] ?? $m['video_views'];
$kpis = [
    ['Posts published', (string) $in['sent'], $change !== null ? ($change >= 0 ? '+' : '') . $change . '%' : ''],
    ['Followers', $num($m['followers']), $m['followers'] !== null && $prev['followers'] !== null ? (($d = (int) $m['followers'] - (int) $prev['followers']) >= 0 ? '+' : '') . number_format($d) : ''],
    ['Views', $num($views), $delta($views, $prev['impressions'] ?? $prev['video_views'])],
    ['Reactions', $num($m['reactions']), $delta($m['reactions'], $prev['reactions'])],
    ['Comments', $num($m['comments']), $delta($m['comments'], $prev['comments'])],
    ['Engagement rate', $m['eng_rate'] !== null ? rtrim(rtrim(number_format((float) $m['eng_rate'], 2), '0'), '.') . '%' : '—', $delta($m['eng_rate'], $prev['eng_rate'], true)],
];
$score = static fn (array $pm): int => (int) ($pm['reactions'] ?? 0) + (int) ($pm['comments'] ?? 0) + (int) ($pm['shares'] ?? 0) + (int) ($pm['saves'] ?? 0);
$top = array_values(array_filter($in['posts'], static fn (array $p): bool => isset($in['post_metrics'][$p['id']])));
usort($top, static fn (array $a, array $b): int => $score($in['post_metrics'][$b['id']]) <=> $score($in['post_metrics'][$a['id']]));
$top = array_slice($top, 0, 5);
$best = $in['best'];
$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$ranges = ['7d' => 'Last 7 days', '30d' => 'Last 30 days', 'mtd' => 'Month to date', 'last-month' => 'Last month'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($file) ?></title>
  <style>
    * { box-sizing: border-box; }
    :root { --brand: <?= e($color) ?>; }
    body { margin: 0; background: #F3F3F0; color: #141A19; font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .bar { position: sticky; top: 0; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 24px; background: #0B1514; color: #fff; }
    .bar a, .bar button { font: inherit; color: #0B1514; background: #fff; border: 0; border-radius: 999px; padding: 8px 16px; cursor: pointer; text-decoration: none; font-weight: 600; }
    .bar a.ghost { background: transparent; color: #fff; border: 1px solid rgb(255 255 255 / .4); }
    .bar a.ghost[aria-current] { background: rgb(255 255 255 / .15); }
    .bar nav { display: flex; flex-wrap: wrap; gap: 6px; }
    .page { max-width: 860px; margin: 24px auto; padding: 40px 44px; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px -20px rgb(0 0 0 / .3); border-top: 6px solid var(--brand); }
    .head { display: flex; align-items: center; gap: 16px; }
    .head img { max-height: 56px; max-width: 180px; object-fit: contain; }
    h1 { margin: 0; font-size: 26px; letter-spacing: -0.02em; }
    h2 { margin: 30px 0 12px; font-size: 16px; }
    .sub { color: #5E6461; margin: 4px 0 0; }
    .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 24px; }
    .kpi { padding: 14px 16px; border: 1px solid #E5E3DD; border-left: 4px solid var(--brand); border-radius: 10px; }
    .kpi b { display: block; font-size: 26px; }
    .kpi span { color: #5E6461; font-size: 12.5px; }
    .kpi em { font-style: normal; font-size: 12px; color: #2F6A43; margin-left: 6px; }
    .kpi em.down { color: #B42318; }
    .chart { display: flex; align-items: flex-end; gap: 2px; height: 120px; border-bottom: 1px solid #E5E3DD; }
    .chart i { flex: 1; background: var(--brand); border-radius: 3px 3px 0 0; min-height: 1px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; color: #5E6461; font-weight: 600; font-size: 12px; padding: 0 8px 8px; border-bottom: 1px solid #E5E3DD; }
    td { padding: 8px; border-bottom: 1px solid #F0EEE8; vertical-align: top; }
    td a { color: #1A4FB5; }
    .best { display: flex; flex-wrap: wrap; gap: 8px; }
    .best span { padding: 8px 12px; border: 1px solid #E5E3DD; border-radius: 8px; }
    .foot { margin-top: 30px; color: #8A8F8C; font-size: 12px; }
    @media (max-width: 640px) { .page { padding: 24px 18px; margin: 12px; } .kpis { grid-template-columns: repeat(2, 1fr); } }
    @media print { body { background: #fff; } .bar { display: none; } .page { margin: 0; box-shadow: none; padding: 0; max-width: none; border-top: 0; } }
  </style>
</head>
<body>
  <div class="bar">
<?php if ($shared): ?>
    <nav aria-label="Period">
<?php foreach ($ranges as $key => $label): ?>
      <a class="ghost" href="?range=<?= e($key) ?>"<?= $in['range'] === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
    </nav>
    <button type="button" data-print>Save as PDF</button>
<?php else: ?>
    <span>Report preview. Use Print and choose "Save as PDF".</span>
    <span><a class="ghost" href="/app/settings#reports">Branding</a> <a class="ghost" href="/app/insights?range=<?= e($in['range']) ?>">Back to Insights</a> <button type="button" data-print>Print / Save as PDF</button></span>
<?php endif; ?>
  </div>
  <main class="page">
    <div class="head">
<?php if ($brand['logo']): ?>
      <img src="<?= e($brand['logo']) ?>" alt="<?= e($title) ?>">
<?php endif; ?>
      <div>
        <h1><?= e($brand['name'] ? $brand['name'] . ' · Social media report' : 'Social media report') ?></h1>
        <p class="sub"><?= $brand['name'] ? '' : e($title) . ' · ' ?><?= e($in['from']->format('M j, Y')) ?> – <?= e($in['to']->format('M j, Y')) ?> (<?= e($tz->getName()) ?>)</p>
      </div>
    </div>
    <div class="kpis">
<?php foreach ($kpis as [$label, $value, $diff]): ?>
      <div class="kpi"><b><?= e($value) ?></b><span><?= e($label) ?><?php if ($diff !== ''): ?><em<?= str_starts_with($diff, '-') ? ' class="down"' : '' ?>><?= e($diff) ?> vs previous</em><?php endif; ?></span></div>
<?php endforeach; ?>
    </div>
    <h2>Posts published per day</h2>
    <div class="chart"><?php foreach ($in['series'] as $d): ?><i style="height: <?= round($d['n'] / $max * 100) ?>%" title="<?= e($d['day']) ?>: <?= (int) $d['n'] ?>"></i><?php endforeach; ?></div>
<?php if ($top): ?>
    <h2>Top posts</h2>
    <table><thead><tr><th>Post</th><th>Network</th><th>Reactions</th><th>Comments</th><th>Shares</th><th>Views</th></tr></thead><tbody>
<?php foreach ($top as $p): $pm = $in['post_metrics'][$p['id']]; ?>
      <tr><td><?= e(mb_strimwidth(trim(($p['title'] ? $p['title'] . ' — ' : '') . $p['body']), 0, 110, '…')) ?><?php if ($p['external_url']): ?><br><a href="<?= e($p['external_url']) ?>">View post</a><?php endif; ?></td><td><?= e($names[$p['channel']] ?? $p['channel']) ?></td><td><?= $num($pm['reactions']) ?></td><td><?= $num($pm['comments']) ?></td><td><?= $num($pm['shares']) ?></td><td><?= $num($pm['impressions'] ?? $pm['video_views']) ?></td></tr>
<?php endforeach; ?>
    </tbody></table>
<?php endif; ?>
<?php if ($best['ready']): ?>
    <h2>Best times to post</h2>
    <div class="best">
<?php foreach ($best['top'] as $t): ?>
      <span><b><?= e($dayNames[$t['day']]) ?> <?= e((new DateTimeImmutable(sprintf('2000-01-01 %02d:00', $t['hour'])))->format('g A')) ?></b> · <?= e(number_format($t['lift'], 1)) ?>× usual engagement</span>
<?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($byNetwork): ?>
    <h2>By network</h2>
    <table><thead><tr><th>Network</th><th>Posts</th></tr></thead><tbody>
<?php foreach ($byNetwork as $n => $c): ?><tr><td><?= e($n) ?></td><td><?= (int) $c ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php endif; ?>
<?php if ($campaigns): ?>
    <h2>Campaigns</h2>
    <table><thead><tr><th>Campaign (tag)</th><th>Published</th><th>Scheduled</th><th>Failed</th></tr></thead><tbody>
<?php foreach ($campaigns as $c): ?><tr><td><?= e($c['name']) ?></td><td><?= (int) $c['sent'] ?></td><td><?= (int) $c['scheduled'] ?></td><td><?= (int) $c['failed'] ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php endif; ?>
    <h2>Published posts</h2>
<?php if ($in['posts']): ?>
    <table><thead><tr><th>Date</th><th>Network</th><th>Post</th><th>Engagement</th></tr></thead><tbody>
<?php foreach ($in['posts'] as $p): $pm = $in['post_metrics'][$p['id']] ?? null; ?>
      <tr><td><?= e(Workspace::local((string) $p['sent_at'], $tz)->format('M j, g:i A')) ?></td><td><?= e($names[$p['channel']] ?? $p['channel']) ?><br><small><?= e($accounts[(int) $p['channel_id']]['name'] ?? '') ?></small></td><td><?= e(mb_strimwidth(trim(($p['title'] ? $p['title'] . ' — ' : '') . $p['body']), 0, 180, '…')) ?><?php if ($p['external_url']): ?><br><a href="<?= e($p['external_url']) ?>">View post</a><?php endif; ?></td><td><?= $pm ? number_format($score($pm)) : '—' ?></td></tr>
<?php endforeach; ?>
    </tbody></table>
<?php else: ?>
    <p class="sub">No posts were published in this period.</p>
<?php endif; ?>
    <p class="foot">Generated <?= e((new DateTimeImmutable('now', $tz))->format('M j, Y g:i A')) ?><?= $brand['white_label'] ? '' : ' by atwebtech' ?>. Stats come from each network and update every few hours; a dash means the network didn’t report that number.</p>
  </main>
  <script src="<?= e(asset('js/report.js')) ?>" defer></script>
</body>
</html>
