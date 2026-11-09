<?php
/** @var array $user */
/** @var bool $billingEnabled */
/** @var array|null $subscription */
/** @var array|null $latest */
/** @var int $connectedCount */
/** @var array{plan: string, billing: string, channels: int} $selected */
/** @var bool $canceled */
/** @var bool $hasCustomer */
$cfg = data('pricing');
$token = Security::csrfToken();
$allowance = Channels::allowance((int) $user['id']);
$tz = Workspace::timezone($user);
$date = static fn (?string $utc): string => $utc ? (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz)->format('M j, Y') : '';
$statusLabels = ['active' => 'Active', 'trialing' => 'Trial', 'past_due' => 'Payment due', 'canceled' => 'Canceled', 'unpaid' => 'Unpaid', 'incomplete' => 'Incomplete', 'incomplete_expired' => 'Expired', 'paused' => 'Paused'];
$jsConfig = [
    'volumeFrom' => $cfg['volume_from'],
    'volumeRate' => $cfg['volume_rate'],
    'yearlyMonths' => $cfg['yearly_months'],
    'max' => $cfg['max_channels'],
    'rates' => array_map(static fn ($p) => $p['rate'], $cfg['plans']),
];
?>
<div class="wsp set bill">
  <header class="set-head">
    <div class="wsp-title">
      <span class="ws-avatar tfa-avatar" aria-hidden="true"><?= icon('tag') ?></span>
      <div>
        <h1>Plan &amp; billing</h1>
        <p class="set-kicker">Your plan, channels, and payment details</p>
      </div>
    </div>
  </header>

  <section class="set-block" aria-labelledby="bill-current">
    <div class="set-row">
      <div>
        <h2 id="bill-current">Current plan</h2>
<?php if ($subscription): ?>
        <p><?= e($cfg['plans'][$subscription['plan']]['name'] ?? 'Paid') ?> for <?= (int) $subscription['channels'] ?> <?= (int) $subscription['channels'] === 1 ? 'channel' : 'channels' ?>, billed <?= $subscription['billing'] === 'yearly' ? 'yearly' : 'monthly' ?>.
<?php if ($subscription['current_period_end']): ?>
          <?= $subscription['cancel_at_period_end'] ? 'Ends' : 'Renews' ?> on <?= e($date($subscription['current_period_end'])) ?>.
<?php endif; ?>
        </p>
<?php if ($subscription['status'] === 'past_due'): ?>
        <p class="bill-warn"><?= icon('warning-circle') ?>Your last payment didn't go through. Update your card in Manage billing to keep your paid features.</p>
<?php endif; ?>
<?php elseif ($allowance['source'] === 'admin'): ?>
        <p>You're on a complimentary <?= e($allowance['name']) ?> plan.</p>
<?php else: ?>
        <p>Free: up to <?= (int) $cfg['free_channels'] ?> channels, <?= (int) $cfg['free_scheduled'] ?> scheduled posts per channel, and <?= (int) $cfg['free_ideas'] ?> ideas.<?php if ($latest && $latest['status'] === 'canceled'): ?> Your paid plan ended on <?= e($date($latest['current_period_end'])) ?>.<?php endif; ?></p>
<?php endif; ?>
      </div>
      <p class="set-value">
        <span class="bill-plan"><?= e($allowance['name']) ?></span>
<?php if ($subscription): ?>
        <span class="bill-status bill-status--<?= e($subscription['status']) ?>"><?= e($subscription['cancel_at_period_end'] ? 'Canceling' : ($statusLabels[$subscription['status']] ?? ucfirst($subscription['status']))) ?></span>
<?php endif; ?>
      </p>
    </div>
<?php if ($billingEnabled && $hasCustomer): ?>
    <div class="set-row">
      <div>
        <h2>Payment details</h2>
        <p>Update your card, download invoices, or cancel. This opens our payment provider, Stripe.</p>
      </div>
      <form action="/app/billing/portal" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <button type="submit" class="ws-btn"><?= icon('arrow-square-out') ?>Manage billing</button>
      </form>
    </div>
<?php endif; ?>
  </section>

  <section class="set-block" aria-labelledby="bill-choose">
    <div class="set-row set-row--stack">
      <div>
        <h2 id="bill-choose"><?= $subscription ? 'Change your plan' : 'Upgrade your plan' ?></h2>
        <p>Pay per channel. Channels 1 to <?= (int) $cfg['volume_from'] - 1 ?> are billed at the plan rate, and every channel after that is $<?= (int) $cfg['volume_rate'] ?> a month. Yearly billing costs the same as <?= (int) $cfg['yearly_months'] ?> months.</p>
      </div>
    </div>

<?php if ($canceled): ?>
    <p class="bill-note"><?= icon('info') ?>Checkout was canceled. You haven't been charged.</p>
<?php endif; ?>

<?php if (!$billingEnabled): ?>
    <p class="bill-note"><?= icon('info') ?>Paid plans are opening soon. <a href="/pricing#join">Get notified</a> and we'll email you when you can upgrade.</p>
<?php else: ?>
    <form class="bill-form" action="/app/billing/checkout" method="post" data-billing-form>
      <script type="application/json" data-billing-config><?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <input type="hidden" name="_token" value="<?= e($token) ?>">

      <fieldset class="bill-plans">
        <legend class="sr-only">Plan</legend>
<?php foreach (Billing::paidPlans() as $key): $p = $cfg['plans'][$key]; ?>
        <label class="bill-plan-card">
          <input type="radio" name="plan" value="<?= e($key) ?>"<?= $selected['plan'] === $key ? ' checked' : '' ?> data-bill-plan>
          <span class="bill-plan-body">
            <b><?= e($p['name']) ?><?php if ($p['badge']): ?> <small><?= e($p['badge']) ?></small><?php endif; ?></b>
            <span>$<?= e((string) $p['rate']) ?> per channel a month</span>
            <small><?= e(implode(' · ', array_map(static fn ($f) => $f[0], array_slice($p['included'], 0, 3)))) ?></small>
          </span>
        </label>
<?php endforeach; ?>
      </fieldset>

      <div class="bill-controls">
        <label class="bill-field">
          <span>Channels</span>
          <input class="input input--sm" type="number" name="channels" min="<?= max(1, $connectedCount) ?>" max="<?= (int) $cfg['max_channels'] ?>" value="<?= (int) $selected['channels'] ?>" inputmode="numeric" data-bill-channels>
          <small><?= (int) $connectedCount ?> connected now</small>
        </label>
        <fieldset class="bill-field">
          <legend>Billing</legend>
          <span class="seg-toggle">
<?php foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly (2 months free)'] as $value => $label): ?>
            <label class="seg-option"><input type="radio" name="billing" value="<?= $value ?>"<?= $selected['billing'] === $value ? ' checked' : '' ?> data-bill-interval><span><?= e($label) ?></span></label>
<?php endforeach; ?>
          </span>
        </fieldset>
      </div>

<?php $quote = Pricing::quote($selected['plan'], $selected['channels'], $selected['billing']); ?>
      <div class="bill-total">
        <p><span class="bill-total-amount" data-bill-total><?= e($quote['per_month']) ?></span> <span>/month</span></p>
        <p class="bill-total-note" data-bill-note><?= e($quote['note']) ?></p>
      </div>

      <div class="bill-actions">
        <button type="submit" class="ws-btn ws-btn--dark bill-submit"><?= $subscription ? 'Update plan' : 'Continue to secure checkout' ?><?= icon('arrow-right') ?></button>
        <p class="bill-fine"><?= icon('lock-simple') ?><?= $subscription ? 'Changes are prorated on your next invoice.' : 'Payments are processed by Stripe. Cancel any time.' ?></p>
      </div>
    </form>
<?php endif; ?>
  </section>
</div>
