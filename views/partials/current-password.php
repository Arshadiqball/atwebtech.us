<?php
/** @var string $id */
/** @var string|null $error */
?>
<div>
  <label for="<?= e($id) ?>">Password</label>
  <div class="auth-password">
    <input class="auth-input" type="password" id="<?= e($id) ?>" name="password" required autocomplete="current-password"<?= $error ? ' aria-invalid="true"' : '' ?> aria-describedby="<?= e($id) ?>-error">
    <button type="button" class="auth-reveal" aria-pressed="false" aria-controls="<?= e($id) ?>" data-password-toggle>
      <span class="reveal-show"><?= icon('eye') ?></span><span class="reveal-hide"><?= icon('eye-slash') ?></span><span class="sr-only">Show password</span>
    </button>
  </div>
  <p class="field-error" id="<?= e($id) ?>-error"<?= $error ? '' : ' hidden' ?>><?= e($error ?? '') ?></p>
</div>
