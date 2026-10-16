<?php
/** @var array $user */
/** @var array $pending */
/** @var array $connected */
$provider = array_column(integrations(), null, 'slug')[$pending['provider']];
$state = View::formState('confirm');
$known = array_column(array_filter($connected, static fn (array $c): bool => $c['provider'] === $pending['provider']), 'external_id');
$token = Security::csrfToken();
?>
<main class="cc" id="main">
  <div class="cc-box">
    <header class="cc-head">
      <form action="/app/channels/connect/<?= e($pending['provider']) ?>" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="switch" value="1">
        <button type="submit" class="cc-switch">Switch Account</button>
      </form>
      <span class="cc-logos" aria-hidden="true">
        <?php partial('partials/logo-mark', ['class' => 'cc-logo']); ?>
        <?= icon('arrows-left-right') ?>
        <span class="cc-brand" style="--c: <?= e($provider['color']) ?>"><?= brand_mark($provider['icon'], $provider['name'], true) ?></span>
      </span>
      <form action="/app/channels/confirm" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <button type="submit" class="ws-icon-btn" name="action" value="cancel"><?= icon('x') ?><span class="sr-only">Cancel and go back</span></button>
      </form>
    </header>

    <form class="cc-form" action="/app/channels/confirm" method="post" id="confirm-form">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <h1>Confirm your Account</h1>
      <p class="cc-sub">Choose the <?= e($provider['name']) ?> <?= count($pending['accounts']) === 1 ? strtolower((string) ($pending['accounts'][0]['kind'] ?? 'channel')) : 'channels' ?> to add to your workspace.</p>
<?php if ($state): ?><p class="composer-error" role="alert"><?= e($state['message']) ?></p><?php endif; ?>
      <fieldset>
        <legend class="sr-only">Channels</legend>
<?php foreach ($pending['accounts'] as $i => $acct): ?>
        <label class="cc-acct">
          <?= channel_avatar(['provider' => $pending['provider'], 'name' => $acct['name'], 'avatar_url' => $acct['avatar']], 'md') ?>
          <span class="cc-acct-text"><b><?= e($acct['name']) ?></b><small><?= e($acct['kind'] ?? 'Channel') ?><?= in_array($acct['id'], $known, true) ? ' · already connected' : '' ?></small></span>
          <input type="checkbox" name="accounts[]" value="<?= e($acct['id']) ?>"<?= $i === 0 ? ' checked' : '' ?>>
        </label>
<?php endforeach; ?>
      </fieldset>
      <p class="cc-fine"><?= match ($pending['provider']) {
          'linkedin' => 'atwebtech can create posts on this LinkedIn Profile. It can’t see your messages, and Company Pages aren’t available yet. You can disconnect at any time.',
          'tiktok' => 'atwebtech can read this TikTok account’s name and picture. Publishing isn’t available yet, so nothing is posted. You can disconnect at any time.',
          'x' => 'atwebtech can read this X account’s name and picture. Publishing isn’t available yet, so nothing is posted. You can disconnect at any time.',
          'facebook' => 'atwebtech can read this Facebook Page’s name and picture. Publishing isn’t available yet, so nothing is posted. You can disconnect at any time.',
          'instagram' => 'atwebtech can read this Instagram account’s name and picture. Publishing isn’t available yet, so nothing is posted. You can disconnect at any time.',
          'pinterest' => !empty($pending['sandbox'])
              ? 'This is the Pinterest sandbox account. atwebtech can read its name and picture. Publishing isn’t available yet, and this is not a live Pinterest profile. You can disconnect at any time.'
              : 'atwebtech can read this Pinterest account’s name and picture. Publishing isn’t available yet, so nothing is posted. You can disconnect at any time.',
          default => 'atwebtech can read this channel and upload videos you schedule. It can’t delete videos or reply to comments. You can disconnect at any time.',
      } ?></p>
    </form>

    <footer class="cc-foot">
      <a class="cc-help" href="/faq"><?= icon('question') ?>Need Help</a>
      <button type="submit" form="confirm-form" class="ws-btn ws-btn--green">Finish Connection</button>
    </footer>
  </div>
</main>
