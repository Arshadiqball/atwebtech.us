<?php
$state = View::formState('admin-login');
$old = $state['old'] ?? [];
?>
<div class="auth">
  <main class="auth-main" id="main" tabindex="-1">
    <div class="auth-box">
      <a class="auth-logo" href="/" aria-label="atwebtech home"><?php partial('partials/logo-mark'); ?></a>
      <h1 class="auth-title">Admin panel</h1>
      <p class="auth-switch">Sign in to read messages and manage waitlist plans.</p>
      <form class="auth-form" id="admin-login" action="/admin/login" method="post" novalidate>
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
<?php if ($state && $state['status'] === 'error'): ?>
        <div class="form-status is-error" role="alert"><?= icon('warning-circle') ?><span><?= e($state['message']) ?></span></div>
<?php endif; ?>
        <div class="auth-field">
          <label for="admin-username">Username</label>
          <input class="auth-input" type="text" id="admin-username" name="username" required maxlength="80" autocomplete="username" spellcheck="false" value="<?= e($old['username'] ?? '') ?>">
        </div>
        <div class="auth-field">
          <label for="admin-password">Password</label>
          <input class="auth-input" type="password" id="admin-password" name="password" required maxlength="200" autocomplete="current-password">
        </div>
        <button class="auth-submit" type="submit">Log in</button>
      </form>
    </div>
  </main>
</div>
