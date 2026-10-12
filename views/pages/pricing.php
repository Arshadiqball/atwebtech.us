<?php
$cfg = data('pricing');
$plans = $cfg['plans'];
$channelsCount = Pricing::clampChannels($_GET['channels'] ?? 1);
$billing = Pricing::billing($_GET['billing'] ?? 'yearly');
$selectedPlan = isset($plans[$_GET['plan'] ?? '']) ? $_GET['plan'] : '';
$channelList = integrations();
$jsConfig = [
    'currency' => $cfg['currency'],
    'volumeFrom' => $cfg['volume_from'],
    'volumeRate' => $cfg['volume_rate'],
    'yearlyMonths' => $cfg['yearly_months'],
    'max' => $cfg['max_channels'],
    'rates' => array_map(static fn ($p) => $p['rate'], $plans),
    'names' => array_map(static fn ($p) => $p['name'], $plans),
];
$planHref = static fn (string $key): string => '/pricing?' . http_build_query(['plan' => $key, 'channels' => $channelsCount, 'billing' => $billing]) . '#join';
$cell = static function (bool|string $value): string {
    if ($value === true) {
        return icon('check', 'icon cmp-check') . '<span class="sr-only">Included</span>';
    }
    if ($value === false) {
        return '<span class="cmp-none" aria-hidden="true"></span><span class="sr-only">Not included</span>';
    }
    return '<span class="cmp-text">' . e($value) . '</span>';
};
$planKeys = array_keys($plans);
?>
<div class="pricing" data-pricing>
<script type="application/json" data-pricing-config><?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<section class="pr-hero" aria-labelledby="pricing-title">
  <div class="container">
    <h1 class="pr-title" id="pricing-title">Flexible pricing for every workspace</h1>

    <h2 class="sr-only">Plans</h2>
    <form class="pr-controls" action="/pricing" method="get" data-pricing-controls>
      <div class="pr-control">
        <div class="pr-control-text">
          <label class="pr-control-label" for="pr-channels">Channels</label>
          <span class="pr-control-hint" id="pr-channels-hint">Select how many channels you want to connect</span>
        </div>
        <div class="stepper">
          <button type="button" class="stepper-btn" data-step="-1" aria-label="Decrease channels" aria-controls="pr-channels"<?= $channelsCount <= 1 ? ' disabled' : '' ?>><?= icon('minus') ?></button>
          <input class="stepper-input" type="number" id="pr-channels" name="channels" value="<?= $channelsCount ?>" min="1" max="<?= (int) $cfg['max_channels'] ?>" inputmode="numeric" aria-describedby="pr-channels-hint" data-channels>
          <button type="button" class="stepper-btn" data-step="1" aria-label="Increase channels" aria-controls="pr-channels"<?= $channelsCount >= $cfg['max_channels'] ? ' disabled' : '' ?>><?= icon('plus') ?></button>
        </div>
      </div>
      <fieldset class="pr-control">
        <div class="pr-control-text">
          <legend class="pr-control-label">Billing</legend>
          <span class="pr-control-hint">Select your preferred billing schedule</span>
        </div>
        <div class="seg-toggle">
<?php foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label): ?>
          <label class="seg-option">
            <input type="radio" name="billing" value="<?= $value ?>"<?= $billing === $value ? ' checked' : '' ?> data-billing>
            <span><?= $label ?></span>
          </label>
<?php endforeach; ?>
        </div>
      </fieldset>
      <noscript><button type="submit" class="btn btn--dark btn--sm">Update prices</button></noscript>
    </form>

    <div class="plan-grid">
<?php foreach ($plans as $key => $p): $quote = Pricing::quote($key, $channelsCount, $billing); ?>
      <article class="plan-card<?= $p['badge'] ? ' plan-card--featured' : '' ?>" aria-labelledby="plan-<?= e($key) ?>">
        <p class="plan-badge"<?= $p['badge'] ? '' : ' aria-hidden="true"' ?>><?= $p['badge'] ? '<span>' . e($p['badge']) . '</span>' : '' ?></p>
        <h3 class="plan-name" id="plan-<?= e($key) ?>"><?= e($p['name']) ?></h3>
        <p class="plan-price">
<?php if ($p['rate'] === 0): ?>
          <span class="plan-amount">Free</span> <span class="plan-unit">forever</span>
<?php else: ?>
          <span class="plan-amount" data-plan-price="<?= e($key) ?>"><?= e($quote['per_month']) ?></span> <span class="plan-unit">/month</span>
<?php endif; ?>
        </p>
        <p class="plan-note"<?= $p['rate'] === 0 ? '' : ' data-plan-note="' . e($key) . '"' ?>><?= $p['rate'] === 0 ? 'Connect up to ' . (int) $cfg['free_channels'] . ' channels' : e($quote['note']) ?></p>
        <a class="btn btn--dark plan-cta" href="<?= e($planHref($key)) ?>" data-plan-cta="<?= e($key) ?>">Join the waitlist<?= icon('arrow-right') ?><span class="sr-only"> for <?= e($p['name']) ?></span></a>
        <h4 class="plan-included">What's included</h4>
        <ul class="plan-features" role="list">
<?php foreach ($p['included'] as $j => [$feature, $desc]): $tipId = 'tip-' . $key . '-' . $j; ?>
          <li>
            <span><?= e($feature) ?></span>
            <span class="tip">
              <button type="button" class="tip-btn" aria-describedby="<?= e($tipId) ?>" aria-label="About <?= e($feature) ?>"><?= icon('info') ?></button>
              <span class="tip-body" role="tooltip" id="<?= e($tipId) ?>"><?= e($desc) ?></span>
            </span>
          </li>
<?php endforeach; ?>
        </ul>
      </article>
<?php endforeach; ?>
    </div>
    <p class="sr-only" aria-live="polite" data-pricing-live></p>

    <div class="pr-after">
      <p class="pr-disclaimer">Planned launch pricing in US dollars. atwebtech is in development, so choosing a plan adds you to the waitlist. No payment is taken.</p>
      <a class="btn btn--dark btn--sm" href="#compare">Compare plans<?= icon('arrow-down') ?></a>
    </div>
  </div>
</section>

<section class="section pr-channels" aria-labelledby="pr-channels-title">
  <div class="container">
    <div class="channels-strip pr-channels-strip">
      <div class="channels-copy">
        <h2 class="h-strip" id="pr-channels-title">Channels we plan to support</h2>
        <a class="link-arrow" href="/integrations">See integration status<?= icon('arrow-right') ?></a>
      </div>
      <ul class="pr-channel-icons" role="list">
<?php foreach ($channelList as $ch): ?>
        <li>
          <a class="pr-channel" href="/integrations#<?= e($ch['slug']) ?>">
            <span class="brand-tile" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span>
            <span class="pr-channel-label"><?= e($ch['name']) ?><?= icon('arrow-right') ?></span>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="pr-statement-title">
  <div class="container">
    <div class="pr-statement">
      <h2 class="pr-statement-title" id="pr-statement-title">Pay for the channels you manage. Nothing more.</h2>
      <p>Add a channel when you take on a new account and remove it when you no longer need it. From the <?= (int) $cfg['volume_from'] ?>th channel on, each one is <?= e(Pricing::money((float) $cfg['volume_rate'])) ?> a month on any paid plan, so your average cost per channel goes down as you grow.</p>
    </div>
  </div>
</section>

<section class="section cmp-sec" id="compare" aria-labelledby="compare-title">
  <div class="container">
    <table class="cmp" data-compare>
      <caption class="sr-only">Feature comparison of the Free, Essentials, and Team plans</caption>
      <thead>
        <tr class="cmp-head-top">
          <td class="cmp-head-title" rowspan="2"><h2 id="compare-title">Feature comparison</h2></td>
          <td class="cmp-head-spacer" colspan="3"></td>
        </tr>
        <tr class="cmp-head">
<?php foreach ($plans as $key => $p): ?>
          <th scope="col" class="cmp-plan<?= $p['badge'] ? ' cmp-col--featured' : '' ?>">
            <?php if ($p['badge']): ?><span class="plan-badge-pill"><?= e($p['badge']) ?></span><?php endif; ?>
            <span class="cmp-plan-name"><?= e($p['name']) ?></span>
            <a class="btn btn--outline btn--xs" href="<?= e($planHref($key)) ?>" data-plan-cta="<?= e($key) ?>">Join<span class="sr-only"> the waitlist for <?= e($p['name']) ?></span><?= icon('arrow-right') ?></a>
          </th>
<?php endforeach; ?>
        </tr>
      </thead>
<?php $g = 0; foreach ($cfg['compare'] as $group => $rows): $gid = 'cmp-g' . $g++; ?>
      <tbody class="cmp-group" data-cmp-group>
        <tr class="cmp-group-row">
          <th colspan="4" scope="colgroup">
            <button type="button" class="cmp-toggle" aria-expanded="true" aria-controls="<?= $gid ?>" data-cmp-toggle><?= e($group) ?><?= icon('caret-down', 'icon cmp-caret') ?></button>
          </th>
        </tr>
      </tbody>
      <tbody class="cmp-rows" id="<?= $gid ?>">
<?php foreach ($rows as [$feature, $desc, $free, $ess, $team]): ?>
        <tr>
          <th scope="row"><span class="cmp-feature"><?= e($feature) ?></span><span class="cmp-desc"><?= e($desc) ?></span></th>
<?php foreach ([$free, $ess, $team] as $i => $value): ?>
          <td class="<?= $planKeys[$i] === 'essentials' ? 'cmp-col--featured' : '' ?>" data-plan-label="<?= e($plans[$planKeys[$i]]['name']) ?>"><?= $cell($value) ?></td>
<?php endforeach; ?>
        </tr>
<?php endforeach; ?>
      </tbody>
<?php endforeach; ?>
    </table>
    <p class="cmp-foot">All features are planned for launch and may change before release. See each <a href="/features">feature page</a> for its current status.</p>
  </div>
</section>

<section class="section faq-sec" aria-labelledby="pr-faq-title">
  <div class="container faq-grid pr-faq">
    <div><h2 class="pr-faq-title" id="pr-faq-title">FAQs</h2></div>
    <?php partial('partials/faq-list', ['items' => array_map(static fn ($f) => ['q' => $f[0], 'a' => $f[1]], $cfg['faq']), 'icon' => 'caret-down']); ?>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'pricing', 'plan' => ['selected' => $selectedPlan, 'billing' => $billing, 'channels' => $channelsCount]]); ?>
</div>
