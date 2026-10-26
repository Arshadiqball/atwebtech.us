<?php
$id = 'contact-form';
$state = View::formState($id);
$old = $state['old'] ?? [];
$errors = $state['errors'] ?? [];
$contactEmail = (string) config('contact.email');
$contactPhone = (string) config('contact.phone');
$contactAddress = (string) config('contact.address');
$phoneHref = preg_replace('/[^\d+]/', '', $contactPhone) ?? '';
$fieldAttrs = static function (string $name) use ($id, $errors): string {
    return ' id="' . e($id . '-' . $name) . '" name="' . e($name) . '" aria-describedby="' . e($id . '-' . $name . '-error') . '"'
        . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
};
$errorEl = static function (string $name) use ($id, $errors): string {
    $msg = $errors[$name] ?? '';
    return '<p class="field-error" id="' . e($id . '-' . $name . '-error') . '"' . ($msg ? '' : ' hidden') . '>' . e($msg) . '</p>';
};
?>
<section class="page-hero page-hero--left">
  <div class="container">
    <p class="eyebrow">Contact</p>
    <h1 class="h-page">Talk to the atwebtech team.</h1>
  </div>
</section>

<section class="section">
  <div class="container contact-grid">
    <div class="contact-aside">
      <p class="lead">Questions, feedback, partnership ideas, or a channel you would like us to support: send a message and we will reply by email.</p>
      <dl class="contact-details">
<?php if ($contactEmail !== ''): ?>
        <div>
          <dt>Email</dt>
          <dd><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></dd>
        </div>
<?php endif; ?>
<?php if ($contactPhone !== ''): ?>
        <div>
          <dt>Office</dt>
          <dd><a href="tel:<?= e($phoneHref) ?>"><?= e($contactPhone) ?></a></dd>
        </div>
<?php endif; ?>
<?php if ($contactAddress !== ''): ?>
        <div>
          <dt>Address</dt>
          <dd><a href="https://maps.google.com/?q=<?= rawurlencode($contactAddress) ?>"><?= e($contactAddress) ?></a></dd>
        </div>
<?php endif; ?>
      </dl>
      <div class="notice" role="note">
        <?= icon('info') ?>
        <p><b>Looking for a quick answer?</b>The <a href="/faq">FAQ</a> covers the product status, the demo, and integrations. New to atwebtech? Read <a href="/resources/getting-started">getting started</a>.</p>
      </div>
    </div>

    <form class="contact-form" id="<?= e($id) ?>" action="/contact" method="post" novalidate data-ajax-form>
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
      <input type="hidden" name="_ts" value="<?= e(Security::freshFormTimestamp(isset($old['_ts']) ? (string) $old['_ts'] : null)) ?>">
      <input type="hidden" name="form_id" value="<?= e($id) ?>">
      <input type="hidden" name="return" value="/contact">
      <?php partial('partials/honeypot', ['id' => $id]); ?>

      <div class="field-row">
        <div class="field">
          <label class="field-label" for="<?= e($id) ?>-name">Name</label>
          <input class="input" type="text"<?= $fieldAttrs('name') ?> required minlength="2" maxlength="100" autocomplete="name" data-label="Name" value="<?= e($old['name'] ?? '') ?>">
          <?= $errorEl('name') ?>
        </div>
        <div class="field">
          <label class="field-label" for="<?= e($id) ?>-email">Email</label>
          <input class="input" type="email"<?= $fieldAttrs('email') ?> required maxlength="254" autocomplete="email" inputmode="email" spellcheck="false" placeholder="name@example.com" value="<?= e($old['email'] ?? '') ?>">
          <?= $errorEl('email') ?>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="<?= e($id) ?>-topic">Topic</label>
        <select class="input"<?= $fieldAttrs('topic') ?> required>
          <option value="">Choose a topic</option>
<?php foreach (FormHandler::CONTACT_TOPICS as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= ($old['topic'] ?? ($_GET['topic'] ?? '')) === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <?= $errorEl('topic') ?>
      </div>

      <div class="field">
        <label class="field-label" for="<?= e($id) ?>-message">Message</label>
        <textarea class="input input--area"<?= $fieldAttrs('message') ?> rows="6" required minlength="10" maxlength="4000" data-label="Message"><?= e($old['message'] ?? '') ?></textarea>
        <?= $errorEl('message') ?>
      </div>

      <button type="submit" class="btn btn--green"><span class="btn-label">Send message</span><?= icon('paper-plane-tilt') ?></button>
      <div class="form-status<?= $state ? ' is-' . e($state['status']) : '' ?>" role="status" aria-live="polite" data-form-status>
<?php if ($state): ?>
        <?= icon($state['status'] === 'success' ? 'check-circle' : 'warning-circle') ?><span><?= e($state['message']) ?></span>
<?php endif; ?>
      </div>
      <p class="wl-note">We use your details only to reply to your message. See our <a href="/privacy">privacy policy</a>.</p>
    </form>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'contact']); ?>
