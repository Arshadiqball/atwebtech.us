<?php
/** @var string $email */
$state = View::formState('two-factor');
$errors = $state['errors'] ?? [];
?>
<div class="auth">
  <main class="auth-main" id="main" tabindex="-1">
    <div class="auth-box">
      <a class="auth-logo" href="/" aria-label="atwebtech home"><?php partial('partials/logo-mark'); ?></a>
      <h1 class="auth-title">Enter your authentication code</h1>
      <p class="auth-switch">Signing in as <?= e($email) ?>. Open your authenticator app, or use a recovery code.</p>

      <form class="auth-form" id="two-factor" action="/login/two-factor" method="post" novalidate>
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
<?php if ($state && $state['status'] === 'error' && !$errors): ?>
        <div class="form-status is-error" role="alert"><?= icon('warning-circle') ?><span><?= e($state['message']) ?></span></div>
<?php endif; ?>
        <div class="auth-field">
          <label for="two-factor-code">Authentication code</label>
          <input class="auth-input" type="text" id="two-factor-code" name="code" required inputmode="text" autocomplete="one-time-code" spellcheck="false" autocapitalize="characters" maxlength="32" autofocus placeholder="6-digit code or recovery code"<?= isset($errors['code']) ? ' aria-invalid="true"' : '' ?> aria-describedby="two-factor-code-error">
          <p class="field-error" id="two-factor-code-error"<?= isset($errors['code']) ? '' : ' hidden' ?>><?= e($errors['code'] ?? '') ?></p>
        </div>
        <button type="submit" class="auth-submit">Continue</button>
        <p class="auth-fine"><a href="/login">Use a different account</a></p>
        <p class="auth-fine">Lost your phone and your recovery codes? <a href="/contact">Contact the atwebtech team</a>.</p>
      </form>
    </div>
    <nav class="auth-legal" aria-label="Legal">
      <a href="/terms">Terms of use</a><span aria-hidden="true">·</span><a href="/privacy">Privacy policy</a><span aria-hidden="true">·</span><a href="/contact">Contact</a>
    </nav>
  </main>
  <aside class="auth-aside" aria-label="About atwebtech">
    <span class="auth-badge">Early access</span>
    <h2 class="auth-aside-title">A second step at sign-in</h2>
    <p class="auth-aside-sub">The code changes every 30 seconds. A recovery code works once, if you can’t reach the app.</p>
  </aside>
</div>
