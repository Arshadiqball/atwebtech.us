<?php
/** @var array $user */
/** @var int $range  days */
/** @var array $rows */
/** @var int $limit */
$uid = (int) $user['id'];
$tz = Workspace::timezone($user);
$token = Security::csrfToken();
$count = count($rows);
$paid = Channels::allowance($uid)['paid'];
$num = static function ($v, int $dec = 0): string {
    if ($v === null) {
        return '—';
    }
    $v = (float) $v;
    $abs = abs($v);
    $s = $abs >= 1e9 ? round($v / 1e9, 2) . 'B' : ($abs >= 1e6 ? round($v / 1e6, 2) . 'M' : ($abs >= 1e4 ? round($v / 1e3, 1) . 'K' : number_format($v, $abs < 100 ? $dec : 0)));
    return $s;
};
$signed = static fn ($v): string => $v === null ? '—' : (($v > 0 ? '+' : ($v < 0 ? '−' : '')) . $num(abs($v)));
$ranges = ['7' => '7 Days', '30' => '30 Days', '90' => '90 Days'];
$networks = Competitors::NETWORKS;

ob_start(); ?>
      <nav class="ws-seg ws-seg--range" aria-label="Date range">
<?php foreach ($ranges as $key => $label): ?>
        <a href="/app/competitors<?= $key === '30' ? '' : '?range=' . $key ?>"<?= (string) $range === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
      </nav>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'chart-line-up', 'title' => 'Competitors', 'actions' => $actions]); ?>

  <section class="co-add" aria-labelledby="co-add-title">
    <div>
      <h2 id="co-add-title">Follow a competitor</h2>
      <p>Compare followers, posting rhythm and engagement with accounts in your niche. Stats update twice a day from each network’s public data.</p>
    </div>
    <form action="/app/competitors" method="post" class="co-form">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="range" value="<?= (int) $range ?>">
      <label class="sr-only" for="co-network">Network</label>
      <select class="idea-input" id="co-network" name="network">
<?php foreach ($networks as $slug => $n): ?>
        <option value="<?= e($slug) ?>"><?= e($n['name']) ?></option>
<?php endforeach; ?>
      </select>
      <label class="sr-only" for="co-handle">Handle or profile link</label>
      <input class="idea-input" id="co-handle" name="handle" maxlength="200" placeholder="@handle or profile link" required autocomplete="off">
      <button type="submit" class="ws-btn ws-btn--dark"<?= $count >= $limit ? ' disabled' : '' ?>><?= icon('plus') ?>Add competitor</button>
    </form>
    <p class="co-meta"><?= $count ?> of <?= $limit ?> competitors<?= !$paid ? ' on the Free plan. <a href="/app/billing">Upgrade</a> to follow up to ' . Competitors::PAID_LIMIT . '.' : '.' ?>
      <span class="tip"><button type="button" class="tip-btn" aria-describedby="co-tip-nets" aria-label="Which networks work"><?= icon('info') ?></button>
      <span class="tip-body" role="tooltip" id="co-tip-nets">YouTube and Bluesky work for any public account. Instagram needs a connected Facebook Page linked to your Instagram professional account, and works for other professional accounts. X isn’t included because its API charges for every read.</span></span>
    </p>
  </section>

<?php if (!$rows): ?>
  <div class="in-empty co-empty">
    <?= icon('chart-line-up') ?>
    <p><b>No competitors yet.</b> Add 3 to 5 accounts your audience also follows, such as a direct competitor, a brand you admire and a creator in your niche. You’ll see who grows fastest, how often they post and which posts get the most engagement.</p>
  </div>
<?php else: ?>
  <ul class="co-list" role="list">
<?php foreach ($rows as $c): $net = $networks[$c['network']] ?? ['name' => $c['network'], 'followers' => 'Followers']; ?>
    <li class="co-card">
      <div class="co-top">
        <a class="co-who" href="<?= e($c['url']) ?>" target="_blank" rel="noopener noreferrer">
<?php if ($c['avatar_url']): ?>
          <img src="<?= e($c['avatar_url']) ?>" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer">
<?php else: ?>
          <span class="co-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $c['name'], 0, 1))) ?></span>
<?php endif; ?>
          <span><b><?= e($c['name']) ?></b><small><?= brand_icon($c['network'] === 'bluesky' ? 'bluesky' : $c['network'], 'co-net') ?><?= e($net['name']) ?> · <?= e($c['handle']) ?></small></span>
        </a>
        <div class="co-acts">
          <form action="/app/competitors" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="range" value="<?= (int) $range ?>">
            <button type="submit" class="ws-icon-btn" name="action" value="refresh" title="Update now"><?= icon('arrows-clockwise') ?><span class="sr-only">Update <?= e($c['name']) ?> now</span></button>
            <button type="submit" class="ws-icon-btn" name="action" value="remove" title="Remove" data-confirm="Stop following <?= e($c['name']) ?>? Its history is deleted."><?= icon('trash') ?><span class="sr-only">Remove <?= e($c['name']) ?></span></button>
          </form>
        </div>
      </div>
      <dl class="co-stats">
        <div><dt><?= e($net['followers']) ?></dt><dd><?= e($num($c['followers'])) ?></dd></div>
        <div><dt>Growth</dt><dd class="<?= ($c['growth'] ?? 0) > 0 ? 'is-up' : (($c['growth'] ?? 0) < 0 ? 'is-down' : '') ?>"><?= e($c['growth_since'] ? $signed($c['growth']) : 'Tracking') ?></dd></div>
        <div><dt>Posts</dt><dd><?= (int) $c['posts'] ?> <small><?= e((string) $c['per_week']) ?>/wk</small></dd></div>
        <div><dt>Avg likes</dt><dd><?= e($num($c['avg_likes'])) ?></dd></div>
        <div><dt>Avg comments</dt><dd><?= e($num($c['avg_comments'], 1)) ?></dd></div>
        <div><dt>Avg views</dt><dd><?= e($num($c['avg_views'])) ?></dd></div>
        <div><dt>Engagement</dt><dd><?= $c['engagement'] !== null ? e(rtrim(rtrim(number_format($c['engagement'], 2), '0'), '.')) . '%' : '—' ?></dd></div>
      </dl>
<?php if ($c['error']): ?>
      <p class="co-error"><?= icon('warning-circle') ?><?= e($c['error']) ?></p>
<?php endif; ?>
      <details class="co-more">
        <summary><?= icon('chart-bar') ?>More stats</summary>
<?php
$tl = $c['timeline'];
if ($tl):
    $vals = array_map(static fn (array $p): int => (int) $p['likes'] + (int) $p['comments'] + (int) $p['shares'], $tl);
    $cw = (int) min(600, max(160, count($vals) * 28));
    $bars = Charts::bars($vals, $cw, 120, 3);
?>
        <figure class="co-chart">
          <figcaption>Engagement per post (likes, comments and shares), oldest to newest</figcaption>
          <svg viewBox="0 0 <?= $cw ?> 140" width="100%" height="150" style="max-width: <?= $cw ?>px" role="img" aria-label="Engagement per post for <?= e($c['name']) ?>">
<?php foreach ($bars as $i => $b): $p = $tl[$i]; ?>
            <rect x="<?= $b['x'] ?>" y="<?= $b['y'] ?>" width="<?= max(1, $b['w']) ?>" height="<?= $b['h'] ?>" rx="2" fill="#2DA641"><title><?= e(Workspace::local((string) $p['published_at'], $tz)->format('j M')) ?>: <?= e($num($b['v'])) ?> interactions</title></rect>
<?php endforeach; ?>
            <text x="0" y="136" font-size="11" fill="currentColor" opacity=".6"><?= e(Workspace::local((string) $tl[0]['published_at'], $tz)->format('j M')) ?></text>
            <text x="<?= $cw ?>" y="136" font-size="11" fill="currentColor" opacity=".6" text-anchor="end"><?= e(Workspace::local((string) end($tl)['published_at'], $tz)->format('j M')) ?></text>
          </svg>
        </figure>
<?php else: ?>
        <p class="wsp-muted">No posts in the last <?= (int) $range ?> days.</p>
<?php endif; ?>
<?php if (count($c['series']) > 1): $f = Charts::line(array_map(static fn (array $r): int => (int) $r['followers'], $c['series']), 600, 80); ?>
        <figure class="co-chart">
          <figcaption><?= e($net['followers']) ?> since you started following</figcaption>
          <svg viewBox="0 0 600 80" width="100%" height="90" role="img" aria-label="<?= e($net['followers']) ?> over time"><path d="<?= e($f['line']) ?>" fill="none" stroke="#2867B2" stroke-width="2"/></svg>
        </figure>
<?php endif; ?>
<?php if ($c['top']): ?>
        <h3 class="co-sub">Top posts</h3>
        <ol class="co-top-posts">
<?php foreach ($c['top'] as $p): ?>
          <li>
            <a href="<?= e($p['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($p['text'] !== null ? (mb_strlen($p['text']) > 120 ? rtrim(mb_substr($p['text'], 0, 120)) . '…' : $p['text']) : 'View post') ?></a>
            <small><?= e(Workspace::local((string) $p['published_at'], $tz)->format('j M Y')) ?><?= $p['likes'] !== null ? ' · ' . e($num($p['likes'])) . ' likes' : '' ?><?= $p['comments'] !== null ? ' · ' . e($num($p['comments'])) . ' comments' : '' ?><?= $p['shares'] !== null ? ' · ' . e($num($p['shares'])) . ' shares' : '' ?><?= $p['views'] !== null ? ' · ' . e($num($p['views'])) . ' views' : '' ?></small>
          </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
        <p class="co-updated">Updated <?= $c['last_checked_at'] ? e(Workspace::local((string) $c['last_checked_at'], $tz)->format('j M, g:i A')) : 'soon' ?>. Following since <?= e(Workspace::local((string) $c['created_at'], $tz)->format('j M Y')) ?>.</p>
      </details>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
</div>
