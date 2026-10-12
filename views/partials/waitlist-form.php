<?php
/**
 * @var string $id      Unique form id (also the anchor used after a non-JS submit)
 * @var string $source  One of FormHandler::SOURCES
 * @var string $variant hero | cta | stack
 */
$variant = $variant ?? 'hero';
$button = $button ?? 'Join the waitlist';
$showRole = $showRole ?? false;
$state = View::formState($id);
$old = $state['old'] ?? [];
$errors = $state['errors'] ?? [];
$emailError = $errors['email'] ?? '';
$roleError = $errors['role'] ?? '';
/** @var array{selected: string, billing: string, channels: int}|null $plan */
$plan = $plan ?? null;
$planError = $errors['plan'] ?? '';
?>
<form class="wl wl--<?= e($variant) ?>" id="<?= e($id) ?>" action="/waitlist" method="post" novalidate data-ajax-form>
  <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
  <input type="hidden" name="_ts" value="<?= e(Security::freshFormTimestamp(isset($old['_ts']) ? (string) $old['_ts'] : null)) ?>">
  <input type="hidden" name="form_id" value="<?= e($id) ?>">
  <input type="hidden" name="source" value="<?= e($source) ?>">
  <input type="hidden" name="return" value="<?= e(current_path()) ?>">
  <?php partial('partials/honeypot', ['id' => $id]); ?>

<?php if ($showRole): ?>
  <div class="field">
    <label class="field-label" for="<?= e($id) ?>-role">I'm joining as <span class="optional">(optional)</span></label>
    <select class="input" id="<?= e($id) ?>-role" name="role"<?= $roleError ? ' aria-invalid="true"' : '' ?> aria-describedby="<?= e($id) ?>-role-error">
      <option value="">Choose one</option>
<?php foreach (FormHandler::WAITLIST_ROLES as $value => $label): ?>
      <option value="<?= e($value) ?>"<?= ($old['role'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
    </select>
    <p class="field-error" id="<?= e($id) ?>-role-error"<?= $roleError ? '' : ' hidden' ?>><?= e($roleError) ?></p>
  </div>
<?php endif; ?>

<?php if ($plan !== null): $selectedPlan = $old['plan'] ?? $plan['selected']; ?>
  <div class="field wl-plan">
    <label class="field-label" for="<?= e($id) ?>-plan">Plan I'm interested in <span class="optional">(optional)</span></label>
    <select class="input" id="<?= e($id) ?>-plan" name="plan" data-plan-select<?= $planError ? ' aria-invalid="true"' : '' ?> aria-describedby="<?= e($id) ?>-plan-error">
      <option value="">Not sure yet</option>
<?php foreach (data('pricing')['plans'] as $value => $p): ?>
      <option value="<?= e($value) ?>"<?= $selectedPlan === $value ? ' selected' : '' ?>><?= e($p['name']) ?></option>
<?php endforeach; ?>
    </select>
    <p class="field-error" id="<?= e($id) ?>-plan-error"<?= $planError ? '' : ' hidden' ?>><?= e($planError) ?></p>
    <input type="hidden" name="billing" value="<?= e($plan['billing']) ?>" data-plan-billing>
    <input type="hidden" name="channels" value="<?= e((string) $plan['channels']) ?>" data-plan-channels>
  </div>
<?php endif; ?>

  <div class="field">
    <label class="<?= $variant === 'stack' ? 'field-label' : 'sr-only' ?>" for="<?= e($id) ?>-email">Email address</label>
    <div class="wl-pill">
      <input class="wl-input" type="email" id="<?= e($id) ?>-email" name="email" required maxlength="254"
        autocomplete="email" inputmode="email" spellcheck="false" placeholder="Enter your email..."
        value="<?= e($old['email'] ?? '') ?>"<?= $emailError ? ' aria-invalid="true"' : '' ?>
        aria-describedby="<?= e($id) ?>-email-error <?= e($id) ?>-note">
      <button type="submit" class="btn <?= $variant === 'cta' ? 'btn--dark' : 'btn--green' ?> wl-submit">
        <span class="btn-label"><?= e($button) ?></span><?= icon('arrow-right') ?>
      </button>
    </div>
    <p class="field-error" id="<?= e($id) ?>-email-error"<?= $emailError ? '' : ' hidden' ?>><?= e($emailError) ?></p>
  </div>

  <div class="form-status<?= $state ? ' is-' . e($state['status']) : '' ?>" role="status" aria-live="polite" data-form-status>
<?php if ($state): ?>
    <?= icon($state['status'] === 'success' ? 'check-circle' : 'warning-circle') ?><span><?= e($state['message']) ?></span>
<?php endif; ?>
  </div>
  <p class="wl-note" id="<?= e($id) ?>-note">By joining, you agree to receive atwebtech launch emails. See our <a href="/privacy">privacy policy</a>.</p>
</form>
