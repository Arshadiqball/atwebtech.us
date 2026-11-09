<?php
/** @var array $user */
/** @var int $days */
/** @var array $dash */
$k = $dash['kpi'];
$money = static fn (float $v): string => '$' . number_format($v, $v >= 1000 ? 0 : 2);
$delta = static function (array $pair): string {
    [$now, $before] = $pair;
    if ($before === null) {
        return '';
    }
    if ($before === 0) {
        return $now > 0 ? '<span class="crm-delta is-up">new</span>' : '';
    }
    $pct = (int) round(($now - $before) / $before * 100);
    return '<span class="crm-delta ' . ($pct >= 0 ? 'is-up' : 'is-down') . '">' . ($pct >= 0 ? '+' : '') . $pct . '%</span>';
};
$rate = static fn (int $a, int $b): string => $b > 0 ? round($a / $b * 100) . '%' : '—';
$funnelTop = max(1, $dash['funnel'][0][1]);
$maxDay = max(1, ...array_values(array_map(static fn ($d) => max($d['leads'], $d['signups']), $dash['daily'])));
$notice = flash('notice');
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'crm']); ?>
  <div class="crm-wrap">
    <header class="crm-head">
      <div>
        <h1>Growth dashboard</h1>
        <p><?= number_format($dash['total']) ?> contacts in total · leads from ads, the waitlist, the contact form, and sign-ups</p>
      </div>
      <nav class="crm-range" aria-label="Date range">
<?php foreach ([7, 30, 90] as $d): ?>
        <a href="/admin?days=<?= $d ?>"<?= $d === $days ? ' aria-current="page"' : '' ?>><?= $d ?> days</a>
<?php endforeach; ?>
      </nav>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>

    <section class="crm-kpis" aria-label="Key numbers">
      <a class="crm-kpi" href="/admin/crm/contacts?stage=lead"><span>New leads</span><b><?= number_format($k['leads'][0]) ?></b><?= $delta($k['leads']) ?></a>
      <a class="crm-kpi" href="/admin/crm/contacts?stage=signed_up"><span>Sign-ups</span><b><?= number_format($k['signups'][0]) ?></b><?= $delta($k['signups']) ?></a>
      <a class="crm-kpi" href="/admin/crm/contacts?stage=activated"><span>Activated</span><b><?= number_format($k['activated'][0]) ?></b><?= $delta($k['activated']) ?><small>connected a channel</small></a>
      <a class="crm-kpi" href="/admin/crm/contacts?stage=active"><span>Active users</span><b><?= number_format($k['active'][0]) ?></b><small>last 7 days</small></a>
      <a class="crm-kpi" href="/admin/crm/contacts?stage=customer"><span>Paying customers</span><b><?= number_format($k['customers'][0]) ?></b></a>
      <div class="crm-kpi crm-kpi--money"><span>Monthly recurring revenue</span><b><?= e($money((float) $k['mrr'][0])) ?></b><small>from live Stripe plans</small></div>
    </section>

    <div class="crm-grid">
      <section class="crm-card" aria-labelledby="crm-funnel">
        <h2 id="crm-funnel">Conversion funnel <small>people first seen in the last <?= $days ?> days</small></h2>
        <ol class="crm-funnel" role="list">
<?php foreach ($dash['funnel'] as $i => [$label, $n]): $prev = $i > 0 ? $dash['funnel'][$i - 1][1] : null; ?>
          <li>
            <span class="crm-funnel-label"><?= e($label) ?></span>
            <span class="crm-funnel-bar"><i style="width: <?= max(2, round($n / $funnelTop * 100)) ?>%"></i></span>
            <b><?= number_format($n) ?></b>
            <small><?= $prev !== null ? e($rate($n, $prev)) . ' of previous' : '' ?></small>
          </li>
<?php endforeach; ?>
        </ol>
      </section>

      <section class="crm-card" aria-labelledby="crm-daily">
        <h2 id="crm-daily">Leads and sign-ups per day</h2>
        <div class="crm-chart" role="img" aria-label="Daily leads and sign-ups for the last <?= $days ?> days">
<?php foreach ($dash['daily'] as $day => $v): ?>
          <span class="crm-col" title="<?= e(date('j M', strtotime($day))) ?>: <?= $v['leads'] ?> leads, <?= $v['signups'] ?> sign-ups">
            <i class="crm-col-lead" style="height: <?= round($v['leads'] / $maxDay * 100) ?>%"></i>
            <i class="crm-col-sign" style="height: <?= round($v['signups'] / $maxDay * 100) ?>%"></i>
          </span>
<?php endforeach; ?>
        </div>
        <p class="crm-legend"><span class="crm-key crm-key--lead"></span>Leads <span class="crm-key crm-key--sign"></span>Sign-ups</p>
      </section>
    </div>

    <section class="crm-card" aria-labelledby="crm-sources">
      <h2 id="crm-sources">Where your leads come from <small>last <?= $days ?> days · Meta ads are detected from Facebook click IDs and paid UTM tags</small></h2>
<?php if ($dash['sources']): ?>
      <div class="crm-table-wrap">
        <table class="crm-table">
          <thead><tr><th>Source</th><th>Leads</th><th>Sign-ups</th><th>Activated</th><th>Customers</th><th>Lead → customer</th><th>Top campaign</th></tr></thead>
          <tbody>
<?php foreach ($dash['sources'] as $name => $s): arsort($s['campaigns']); ?>
            <tr>
              <td><a href="/admin/crm/contacts?source=<?= e(rawurlencode($name)) ?>"><?php if ($name === 'Meta ads'): ?><span class="crm-meta-dot" aria-hidden="true"></span><?php endif; ?><?= e($name) ?></a></td>
              <td><?= $s['leads'] ?></td>
              <td><?= $s['signups'] ?> <small><?= e($rate($s['signups'], $s['leads'])) ?></small></td>
              <td><?= $s['activated'] ?></td>
              <td><?= $s['customers'] ?></td>
              <td><b><?= e($rate($s['customers'], $s['leads'])) ?></b></td>
              <td><?= $s['campaigns'] ? e((string) array_key_first($s['campaigns'])) . ' <small>(' . reset($s['campaigns']) . ')</small>' : '<small>—</small>' ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php else: ?>
      <p class="crm-empty">No new leads in this period yet. Ads that link to atwebtech.us with UTM tags (for example <code>?utm_source=facebook&amp;utm_medium=paid&amp;utm_campaign=launch</code>) are tracked automatically.</p>
<?php endif; ?>
    </section>

    <div class="crm-grid">
      <section class="crm-card" aria-labelledby="crm-due">
        <h2 id="crm-due">Follow-ups due <a class="crm-more" href="/admin/crm/followups">All follow-ups</a></h2>
<?php if ($dash['due']): ?>
        <ul class="crm-mini" role="list">
<?php foreach ($dash['due'] as $f): $overdue = $f['due_at'] < gmdate('Y-m-d H:i:s'); ?>
          <li>
            <a href="/admin/crm/contacts/<?= (int) $f['contact_id'] ?>"><b><?= e($f['title']) ?></b><small><?= e($f['name'] ?: $f['email']) ?></small></a>
            <span class="crm-due<?= $overdue ? ' is-late' : '' ?>"><?= $overdue ? 'Overdue · ' : 'Today · ' ?><?= e(Crm::local($f['due_at'], 'j M, g:i A')) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
<?php else: ?>
        <p class="crm-empty">Nothing due. Schedule follow-ups from any contact's page.</p>
<?php endif; ?>
      </section>

      <section class="crm-card" aria-labelledby="crm-recent">
        <h2 id="crm-recent">Latest activity <a class="crm-more" href="/admin/crm/contacts">All contacts</a></h2>
        <ul class="crm-mini" role="list">
<?php foreach ($dash['recent'] as $c): ?>
          <li>
            <a href="/admin/crm/contacts/<?= (int) $c['id'] ?>"><b><?= e($c['name'] ?: $c['email']) ?></b><small><?= e($c['source']) ?><?= $c['campaign'] !== '' ? ' · ' . e($c['campaign']) : '' ?></small></a>
            <span class="crm-stage crm-stage--<?= e($c['stage']) ?>"><?= e(Crm::STAGES[$c['stage']]) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
      </section>
    </div>

    <section class="crm-card crm-stages" aria-labelledby="crm-stage-title">
      <h2 id="crm-stage-title">All contacts by stage</h2>
      <div class="crm-stage-row">
<?php foreach ($dash['stages'] as $stage => $n): ?>
        <a href="/admin/crm/contacts?stage=<?= e($stage) ?>"><span class="crm-stage crm-stage--<?= e($stage) ?>"><?= e(Crm::STAGES[$stage]) ?></span><b><?= number_format($n) ?></b></a>
<?php endforeach; ?>
      </div>
    </section>
  </div>
</div>
