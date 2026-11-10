<?php
/** @var string $mode  forgot | reset | expired */
/** @var string $token */
$state = View::formState($mode === 'reset' ? 'reset' : 'forgot');
$old = $state['old'] ?? [];
?>
<div class="auth">
  <main class="auth-main" id="main" tabindex="-1">
    <div class="auth-box">
      <a class="auth-logo" href="/" aria-label="atwebtech home"><?php partial('partials/logo-mark'); ?></a>
<?php if ($mode === 'expired'): ?>
      <h1 class="auth-title">This reset link isn’t valid</h1>
      <p class="auth-switch">It expired, or was already used. Reset links work once, for 60 minutes.</p>
      <a class="auth-submit tm-join-btn" href="/forgot">Send a new link</a>
<?php elseif ($mode === 'reset'): ?>
      <h1 class="auth-title">Choose a new password</h1>
      <p class="auth-switch">You’ll be signed out everywhere else.</p>
      <form class="auth-form" id="reset" action="/reset/<?= e($token) ?>" method="post" novalidate data-auth-form>
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
<?php if ($state && $state['status'] === 'error'): ?>
        <div class="form-status is-error" role="alert"><?= icon('warning-circle') ?><span><?= e($state['message']) ?></span></div>
<?php endif; ?>
        <div class="auth-field">
          <label for="reset-password">New password</label>
          <div class="auth-password">
            <input class="auth-input" type="password" id="reset-password" name="password" required maxlength="72" autocomplete="new-password" placeholder="Your new password" data-password-rules aria-describedby="reset-rules-status">
            <button type="button" class="auth-reveal" aria-pressed="false" aria-controls="reset-password" data-password-toggle>
              <span class="reveal-show"><?= icon('eye') ?></span><span class="reveal-hide"><?= icon('eye-slash') ?></span><span class="sr-only">Show password</span>
            </button>
          </div>
        </div>
        <div class="pw-rules" data-rules>
          <p class="sr-only">Password requirements</p>
          <ul role="list">
            <li data-rule="length"><span class="pw-dot" aria-hidden="true"><?= icon('check') ?></span>At least 8 characters long<span class="sr-only" data-rule-state>, not met</span></li>
            <li data-rule="upper"><span class="pw-dot" aria-hidden="true"><?= icon('check') ?></span>Contains 1 uppercase character<span class="sr-only" data-rule-state>, not met</span></li>
            <li data-rule="symbol"><span class="pw-dot" aria-hidden="true"><?= icon('check') ?></span>Contains 1 number or symbol<span class="sr-only" data-rule-state>, not met</span></li>
          </ul>
          <p class="sr-only" id="reset-rules-status" aria-live="polite" data-rules-status></p>
        </div>
        <button type="submit" class="auth-submit">Save new password</button>
      </form>
<?php else: ?>
      <h1 class="auth-title">Reset your password</h1>
      <p class="auth-switch">Enter your email and we’ll send a link to choose a new one. <a href="/login">Back to log in</a></p>
      <form class="auth-form" id="forgot" action="/forgot" method="post" novalidate>
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
<?php if ($state): ?>
        <div class="form-status is-<?= $state['status'] === 'success' ? 'success' : 'error' ?>" role="<?= $state['status'] === 'success' ? 'status' : 'alert' ?>"><?= icon($state['status'] === 'success' ? 'check-circle' : 'warning-circle') ?><span><?= e($state['message']) ?></span></div>
<?php endif; ?>
        <div class="auth-field">
          <label for="forgot-email">Email</label>
          <input class="auth-input" type="email" id="forgot-email" name="email" required maxlength="254" autocomplete="username" inputmode="email" spellcheck="false" placeholder="Your email address" value="<?= e($old['email'] ?? '') ?>">
        </div>
        <button type="submit" class="auth-submit">Send reset link</button>
      </form>
<?php endif; ?>
    </div>
  </main>
</div>
