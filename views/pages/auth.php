<?php
/** @var string $mode signup | login */
$signup = $mode === 'signup';
$state = View::formState($mode);
$old = $state['old'] ?? [];
$errors = $state['errors'] ?? [];
$next = $next ?? null;
$id = $mode;
?>
<div class="auth">
  <main class="auth-main" id="main" tabindex="-1">
    <div class="auth-box">
      <a class="auth-logo" href="/" aria-label="atwebtech home"><?php partial('partials/logo-mark'); ?></a>
      <h1 class="auth-title"><?= $signup ? 'Create an atwebtech account' : 'Log in to atwebtech' ?></h1>
      <p class="auth-switch">
<?php if ($signup): ?>
        Already have an account? <a href="/login">Log in instead</a>
<?php else: ?>
        New to atwebtech? <a href="/signup">Create an account</a>
<?php endif; ?>
      </p>

      <form class="auth-form" id="<?= e($id) ?>" action="/<?= e($mode) ?>" method="post" novalidate data-auth-form>
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
<?php if ($signup): ?>
        <input type="hidden" name="_ts" value="<?= e(Security::freshFormTimestamp(isset($old['_ts']) ? (string) $old['_ts'] : null)) ?>">
        <?php partial('partials/honeypot', ['id' => $id]); ?>
<?php elseif ($next): ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
<?php endif; ?>

<?php if ($state && $state['status'] === 'error' && !$errors): ?>
        <div class="form-status is-error" role="alert"><?= icon('warning-circle') ?><span><?= e($state['message']) ?></span></div>
<?php endif; ?>

        <div class="auth-field">
          <label for="<?= e($id) ?>-email">Email</label>
          <input class="auth-input" type="email" id="<?= e($id) ?>-email" name="email" required maxlength="254"
            autocomplete="<?= $signup ? 'email' : 'username' ?>" inputmode="email" spellcheck="false" placeholder="Your email address"
            value="<?= e($old['email'] ?? '') ?>"<?= isset($errors['email']) ? ' aria-invalid="true"' : '' ?> aria-describedby="<?= e($id) ?>-email-error">
          <p class="field-error" id="<?= e($id) ?>-email-error"<?= isset($errors['email']) ? '' : ' hidden' ?>><?= e($errors['email'] ?? '') ?></p>
        </div>

        <div class="auth-field">
          <label for="<?= e($id) ?>-password"><?= $signup ? 'Create Password' : 'Password' ?></label>
          <div class="auth-password">
            <input class="auth-input" type="password" id="<?= e($id) ?>-password" name="password" required
              autocomplete="<?= $signup ? 'new-password' : 'current-password' ?>" placeholder="Your password"
              <?= $signup ? 'maxlength="72" data-password-rules' : '' ?><?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>
              aria-describedby="<?= e($id) ?>-password-error<?= $signup ? ' ' . e($id) . '-rules-status' : '' ?>">
            <button type="button" class="auth-reveal" aria-pressed="false" aria-controls="<?= e($id) ?>-password" data-password-toggle>
              <span class="reveal-show"><?= icon('eye') ?></span><span class="reveal-hide"><?= icon('eye-slash') ?></span><span class="sr-only">Show password</span>
            </button>
          </div>
          <p class="field-error" id="<?= e($id) ?>-password-error"<?= isset($errors['password']) ? '' : ' hidden' ?>><?= e($errors['password'] ?? '') ?></p>
        </div>

<?php if ($signup): ?>
        <div class="pw-rules" data-rules>
          <p class="sr-only">Password requirements</p>
          <ul role="list">
            <li data-rule="length"><span class="pw-dot" aria-hidden="true"><?= icon('check') ?></span>At least 8 characters long<span class="sr-only" data-rule-state>, not met</span></li>
            <li data-rule="upper"><span class="pw-dot" aria-hidden="true"><?= icon('check') ?></span>Contains 1 uppercase character<span class="sr-only" data-rule-state>, not met</span></li>
            <li data-rule="symbol"><span class="pw-dot" aria-hidden="true"><?= icon('check') ?></span>Contains 1 number or symbol<span class="sr-only" data-rule-state>, not met</span></li>
          </ul>
          <p class="sr-only" id="<?= e($id) ?>-rules-status" aria-live="polite" data-rules-status></p>
        </div>
<?php endif; ?>

        <button type="submit" class="auth-submit"><?= $signup ? 'Sign Up' : 'Continue' ?></button>
<?php if ($signup): ?>
        <p class="auth-fine">By signing up, you agree to the <a href="/terms">terms of use</a> and <a href="/privacy">privacy policy</a>.</p>
<?php else: ?>
        <p class="auth-fine">Forgot your password? <a href="/contact">Contact the atwebtech team</a> and we'll help you get back in.</p>
<?php endif; ?>
      </form>
    </div>

    <nav class="auth-legal" aria-label="Legal">
      <a href="/terms">Terms of use</a><span aria-hidden="true">·</span><a href="/privacy">Privacy policy</a><span aria-hidden="true">·</span><a href="/contact">Contact</a>
    </nav>
  </main>

  <aside class="auth-aside" aria-label="About atwebtech">
    <span class="auth-badge">Early access</span>
    <h2 class="auth-aside-title">Plan your content in one workspace</h2>
    <p class="auth-aside-sub">Your account is ready today. Publishing, the inbox, and insights are being built, and each one is marked until it's live.</p>
    <div class="auth-preview" aria-hidden="true">
      <div class="ap-side">
        <span class="ap-logo"><?php partial('partials/logo-mark'); ?></span>
        <span class="ap-new"><?= icon('plus') ?></span>
        <?php foreach (['lightbulb', 'calendar-blank', 'chats-circle', 'chart-bar'] as $i): ?><span class="ap-nav"><?= icon($i) ?></span><?php endforeach; ?>
      </div>
      <div class="ap-main">
        <div class="ap-head"><span class="ap-title">Ideas</span><span class="ap-chip">This week</span></div>
        <div class="ap-cols">
<?php foreach ([['Ideas', ['Behind the scenes clip', 'Customer question roundup']], ['Drafting', ['Launch checklist carousel', 'Weekly tips thread']], ['Ready', ['Studio tour reel']]] as [$col, $cards]): ?>
          <div class="ap-col">
            <span class="ap-col-title"><?= e($col) ?> <b><?= count($cards) ?></b></span>
<?php foreach ($cards as $c): ?>
            <span class="ap-card"><?= e($c) ?><i></i></span>
<?php endforeach; ?>
          </div>
<?php endforeach; ?>
        </div>
      </div>
    </div>
  </aside>
</div>
