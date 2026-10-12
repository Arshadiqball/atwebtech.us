<?php
/** @var array $user */
/** @var array $twoFactor */
$token = Security::csrfToken();
$initial = mb_strtoupper(mb_substr($user['display_name'], 0, 1));
$start = View::formState('tfa-start');
$confirm = View::formState('tfa-confirm');
$disable = View::formState('tfa-disable');
$regen = View::formState('tfa-recovery');
$enabled = (bool) $twoFactor['enabled'];
$setup = $twoFactor['setup'];
$recovery = $twoFactor['recovery'];
$since = null;
if ($twoFactor['confirmedAt']) {
    $since = (new DateTimeImmutable($twoFactor['confirmedAt'], new DateTimeZone('UTC')))
        ->setTimezone(Workspace::timezone($user))
        ->format('M j, Y');
}
$startErrors = $start['errors'] ?? [];
$confirmErrors = $confirm['errors'] ?? [];
$disableErrors = $disable['errors'] ?? [];
$regenErrors = $regen['errors'] ?? [];
?>
<div class="wsp set">
  <header class="set-head">
    <div class="wsp-title">
      <span class="ws-avatar tfa-avatar" aria-hidden="true"><?= e($initial) ?></span>
      <div>
        <h1>Account</h1>
        <p class="set-kicker">Your sign-in and security</p>
      </div>
    </div>
  </header>

  <section class="set-block">
    <div class="set-row">
      <div>
        <h2>Name</h2>
        <p>Shown on your workspace.</p>
      </div>
      <p class="set-value"><?= e($user['display_name']) ?></p>
    </div>
    <div class="set-row">
      <div>
        <h2>Email</h2>
        <p>The address you use to log in.</p>
      </div>
      <p class="set-value"><?= e($user['email']) ?></p>
    </div>
  </section>

  <section class="set-block" aria-labelledby="tfa-title">
    <div class="set-row set-row--stack">
      <div>
        <h2 id="tfa-title">Two-factor authentication</h2>
        <p>Protect sign-in with a one-time code from an authenticator app, such as Google Authenticator, 1Password, or Authy. Each code works for 30 seconds.</p>
      </div>
      <p class="tfa-status<?= $enabled ? ' is-on' : '' ?>"><?= $enabled ? 'On' : 'Off' ?><?php if ($since): ?> <span>since <?= e($since) ?></span><?php endif; ?></p>
    </div>

<?php if ($recovery): ?>
    <div class="tfa">
      <h3>Save your recovery codes</h3>
      <p>If you lose your phone, these codes can sign you in. Each one works once. Store them somewhere you can open without this account. They won’t be shown again.</p>
      <ul class="tfa-codes" id="tfa-codes" data-copy-text="<?= e(implode("\n", $recovery)) ?>">
<?php foreach ($recovery as $code): ?>
        <li><?= e($code) ?></li>
<?php endforeach; ?>
      </ul>
      <div class="tfa-actions">
        <button type="button" class="ws-btn" data-copy="tfa-codes"><span aria-live="polite">Copy codes<span class="sr-only"> to the clipboard</span></span></button>
        <form action="/app/account/two-factor/recovery/done" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <button type="submit" class="ws-btn ws-btn--green">I’ve saved these codes</button>
        </form>
      </div>
    </div>
<?php elseif ($setup): ?>
    <div class="tfa">
      <h3>Scan this code</h3>
      <p>In your authenticator app, add an account and scan this code. The account name is atwebtech.</p>
<?php if ($setup['svg'] !== ''): ?>
      <div class="tfa-qr"><?= $setup['svg'] ?></div>
<?php else: ?>
      <p class="composer-error" role="alert">The QR code couldn’t be drawn. Enter the key below instead.</p>
<?php endif; ?>
      <p class="tfa-manual">Can’t scan? Enter this key by hand.</p>
      <p class="tfa-key" id="tfa-key" data-copy-text="<?= e($setup['secret']) ?>"><?= e($setup['grouped']) ?></p>
      <button type="button" class="ws-btn" data-copy="tfa-key"><span aria-live="polite">Copy key<span class="sr-only"> to the clipboard</span></span></button>

<?php if ($confirm && $confirm['status'] === 'error'): ?>
      <p class="composer-error" role="alert"><?= e($confirm['message']) ?></p>
<?php endif; ?>
      <form class="tfa-form" action="/app/account/two-factor/confirm" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <div>
          <label for="tfa-code">6-digit code</label>
          <input class="idea-input" id="tfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" spellcheck="false" maxlength="12" required autofocus<?= isset($confirmErrors['code']) ? ' aria-invalid="true"' : '' ?> aria-describedby="tfa-code-error">
          <p class="field-error" id="tfa-code-error"<?= isset($confirmErrors['code']) ? '' : ' hidden' ?>><?= e($confirmErrors['code'] ?? '') ?></p>
        </div>
        <div class="tfa-actions">
          <button type="submit" class="ws-btn ws-btn--green">Turn on two-factor authentication</button>
        </div>
      </form>
      <form action="/app/account/two-factor/cancel" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <button type="submit" class="ws-btn ws-btn--ghost">Cancel</button>
      </form>
    </div>
<?php elseif (!$enabled): ?>
    <div class="tfa">
<?php if ($start && $start['status'] === 'error'): ?>
      <p class="composer-error" role="alert"><?= e($start['message']) ?></p>
<?php endif; ?>
      <form class="tfa-form" action="/app/account/two-factor/start" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <div>
          <label for="tfa-password">Password</label>
          <p class="tfa-hint" id="tfa-password-hint">Confirm it’s you before the QR code is created.</p>
          <div class="auth-password">
            <input class="auth-input" type="password" id="tfa-password" name="password" required autocomplete="current-password"<?= isset($startErrors['password']) ? ' aria-invalid="true"' : '' ?> aria-describedby="tfa-password-hint tfa-password-error">
            <button type="button" class="auth-reveal" aria-pressed="false" aria-controls="tfa-password" data-password-toggle>
              <span class="reveal-show"><?= icon('eye') ?></span><span class="reveal-hide"><?= icon('eye-slash') ?></span><span class="sr-only">Show password</span>
            </button>
          </div>
          <p class="field-error" id="tfa-password-error"<?= isset($startErrors['password']) ? '' : ' hidden' ?>><?= e($startErrors['password'] ?? '') ?></p>
        </div>
        <button type="submit" class="ws-btn ws-btn--green">Set up authenticator</button>
      </form>
    </div>
<?php else: ?>
    <div class="tfa tfa-on">
      <p>Sign-in now asks for a code from your authenticator app after your password. Keep your recovery codes somewhere safe.</p>
      <details class="tfa-fold"<?= $regen ? ' open' : '' ?>>
        <summary>Get new recovery codes</summary>
        <p>This replaces the codes you have now. The old ones stop working.</p>
<?php if ($regen && $regen['status'] === 'error'): ?>
        <p class="composer-error" role="alert"><?= e($regen['message']) ?></p>
<?php endif; ?>
        <form class="tfa-form" action="/app/account/two-factor/recovery" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <?php partial('partials/current-password', ['id' => 'tfa-new-password', 'error' => $regenErrors['password'] ?? null]); ?>
          <div>
            <label for="tfa-new-code">Current code</label>
            <input class="idea-input" id="tfa-new-code" name="code" inputmode="text" autocomplete="one-time-code" spellcheck="false" maxlength="32" required<?= isset($regenErrors['code']) ? ' aria-invalid="true"' : '' ?> aria-describedby="tfa-new-code-error">
            <p class="field-error" id="tfa-new-code-error"<?= isset($regenErrors['code']) ? '' : ' hidden' ?>><?= e($regenErrors['code'] ?? '') ?></p>
          </div>
          <button type="submit" class="ws-btn">Generate new codes</button>
        </form>
      </details>
      <details class="tfa-fold"<?= $disable ? ' open' : '' ?>>
        <summary>Turn off two-factor authentication</summary>
        <p>Your password will be the only thing required at sign-in.</p>
<?php if ($disable && $disable['status'] === 'error'): ?>
        <p class="composer-error" role="alert"><?= e($disable['message']) ?></p>
<?php endif; ?>
        <form class="tfa-form" action="/app/account/two-factor/disable" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <?php partial('partials/current-password', ['id' => 'tfa-off-password', 'error' => $disableErrors['password'] ?? null]); ?>
          <div>
            <label for="tfa-off-code">Current code</label>
            <input class="idea-input" id="tfa-off-code" name="code" inputmode="text" autocomplete="one-time-code" spellcheck="false" maxlength="32" required<?= isset($disableErrors['code']) ? ' aria-invalid="true"' : '' ?> aria-describedby="tfa-off-code-error">
            <p class="field-error" id="tfa-off-code-error"<?= isset($disableErrors['code']) ? '' : ' hidden' ?>><?= e($disableErrors['code'] ?? '') ?></p>
          </div>
          <button type="submit" class="ws-btn ws-btn--danger">Turn off</button>
        </form>
      </details>
    </div>
<?php endif; ?>
  </section>
</div>
