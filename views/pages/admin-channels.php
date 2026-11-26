<?php
/** @var array $user */
/** @var list<array> $channels */
/** @var string $q */
$notice = flash('notice');
$self = '/admin/channels' . ($q !== '' ? '?q=' . rawurlencode($q) : '');
$paused = count(array_filter($channels, static fn ($c) => $c['paused_at'] !== null));
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'channels']); ?>
  <div class="crm-wrap">
    <header class="crm-head">
      <div>
        <h1>Channels</h1>
        <p><?= number_format(count($channels)) ?> connected · <?= $paused ?> paused. A paused channel gets nothing published until it is resumed.</p>
      </div>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
    <form class="crm-filters" method="get" action="/admin/channels">
      <input class="crm-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search channel, network or owner email" aria-label="Search">
      <button class="crm-btn crm-btn--dark" type="submit">Search</button>
<?php if ($q !== ''): ?><a class="crm-link" href="/admin/channels">Clear</a><?php endif; ?>
    </form>
<?php if ($channels): ?>
    <?php partial('partials/admin-channel-table', ['channels' => $channels, 'back' => $self]); ?>
<?php else: ?>
    <p class="crm-empty">No channels found.</p>
<?php endif; ?>
  </div>
</div>
