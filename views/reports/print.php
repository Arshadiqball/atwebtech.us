<?php
/** @var array $user */
/** @var array $in  Workspace::insights() */
/** @var DateTimeZone $tz */
/** @var array $names */
/** @var array $accounts */
/** @var string $file */
/** @var array $campaigns */
$byNetwork = [];
foreach ($in['posts'] as $p) {
    $byNetwork[$names[$p['channel']] ?? $p['channel']] = ($byNetwork[$names[$p['channel']] ?? $p['channel']] ?? 0) + 1;
}
arsort($byNetwork);
$max = max(1, ...array_values(array_map(static fn ($d) => $d['n'], $in['series'])));
$change = $in['prev_sent'] > 0 ? round(($in['sent'] - $in['prev_sent']) / $in['prev_sent'] * 100) : null;
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
    body { margin: 0; background: #F3F3F0; color: #141A19; font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .bar { position: sticky; top: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 24px; background: #0B1514; color: #fff; }
    .bar a, .bar button { font: inherit; color: #0B1514; background: #B0EC9C; border: 0; border-radius: 999px; padding: 8px 16px; cursor: pointer; text-decoration: none; font-weight: 600; }
    .bar a.ghost { background: transparent; color: #fff; border: 1px solid rgb(255 255 255 / .4); }
    .page { max-width: 860px; margin: 24px auto; padding: 40px 44px; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px -20px rgb(0 0 0 / .3); }
    h1 { margin: 0; font-size: 26px; letter-spacing: -0.02em; }
    h2 { margin: 30px 0 12px; font-size: 16px; }
    .sub { color: #5E6461; margin: 4px 0 0; }
    .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 24px; }
    .kpi { padding: 14px 16px; border: 1px solid #E5E3DD; border-radius: 10px; }
    .kpi b { display: block; font-size: 26px; }
    .kpi span { color: #5E6461; font-size: 12.5px; }
    .chart { display: flex; align-items: flex-end; gap: 2px; height: 120px; border-bottom: 1px solid #E5E3DD; }
    .chart i { flex: 1; background: #16A34A; border-radius: 3px 3px 0 0; min-height: 1px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; color: #5E6461; font-weight: 600; font-size: 12px; padding: 0 8px 8px; border-bottom: 1px solid #E5E3DD; }
    td { padding: 8px; border-bottom: 1px solid #F0EEE8; vertical-align: top; }
    td a { color: #1A4FB5; }
    .foot { margin-top: 30px; color: #8A8F8C; font-size: 12px; }
    @media print { body { background: #fff; } .bar { display: none; } .page { margin: 0; box-shadow: none; padding: 0; max-width: none; } }
  </style>
</head>
<body>
  <div class="bar"><span>Report preview. Use Print and choose "Save as PDF".</span><span><a class="ghost" href="/app/insights?range=<?= e($in['range']) ?>">Back to Insights</a> <button type="button" data-print>Print / Save as PDF</button></span></div>
  <main class="page">
    <h1>Social media report</h1>
    <p class="sub"><?= e($user['display_name']) ?> · <?= e($in['from']->format('M j, Y')) ?> – <?= e($in['to']->format('M j, Y')) ?> (<?= e($tz->getName()) ?>)</p>
    <div class="kpis">
      <div class="kpi"><b><?= (int) $in['sent'] ?></b><span>Posts published<?= $change !== null ? ' · ' . ($change >= 0 ? '+' : '') . $change . '% vs previous period' : '' ?></span></div>
      <div class="kpi"><b><?= count($byNetwork) ?></b><span>Networks used</span></div>
      <div class="kpi"><b><?= count($campaigns) ?></b><span>Campaigns (post tags)</span></div>
    </div>
    <h2>Posts published per day</h2>
    <div class="chart"><?php foreach ($in['series'] as $d): ?><i style="height: <?= round($d['n'] / $max * 100) ?>%" title="<?= e($d['day']) ?>: <?= (int) $d['n'] ?>"></i><?php endforeach; ?></div>
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
    <table><thead><tr><th>Date</th><th>Network</th><th>Post</th></tr></thead><tbody>
<?php foreach ($in['posts'] as $p): ?>
      <tr><td><?= e(Workspace::local((string) $p['sent_at'], $tz)->format('M j, g:i A')) ?></td><td><?= e($names[$p['channel']] ?? $p['channel']) ?><br><small><?= e($accounts[(int) $p['channel_id']]['name'] ?? '') ?></small></td><td><?= e(mb_strimwidth(trim(($p['title'] ? $p['title'] . ' — ' : '') . $p['body']), 0, 180, '…')) ?><?php if ($p['external_url']): ?><br><a href="<?= e($p['external_url']) ?>">View post</a><?php endif; ?></td></tr>
<?php endforeach; ?>
    </tbody></table>
<?php else: ?>
    <p class="sub">No posts were published in this period.</p>
<?php endif; ?>
    <p class="foot">Generated by atwebtech on <?= e((new DateTimeImmutable('now', $tz))->format('M j, Y g:i A')) ?>. Follower and engagement numbers from each network will appear here as those integrations go live.</p>
  </main>
  <script src="<?= e(asset('js/report.js')) ?>" defer></script>
</body>
</html>
