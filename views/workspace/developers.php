<?php
/** @var array $user */
/** @var array $keys */
/** @var array $hooks */
/** @var ?array $fresh */
/** @var bool $paid */
$token = Security::csrfToken();
$tz = Workspace::timezone($user);
$when = static fn (?string $t): string => $t ? Workspace::local($t, $tz)->format('j M Y, g:i A') : 'never';
?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'link-simple', 'title' => 'API & webhooks', 'actions' => '<a class="ws-btn ws-btn--sm" href="/developers" target="_blank" rel="noopener">API docs</a>']); ?>

  <section class="tm-upsell">
    <h2>Connect atwebtech to your other tools</h2>
    <p>Schedule posts from your own app, a spreadsheet or an automation, and get notified when posts publish, fail, or get comments. Works with Zapier, Make and n8n through their webhook and HTTP steps.<?= $paid ? '' : ' Available on Essentials and Team.' ?></p>
<?php if (!$paid): ?>
    <a class="ws-btn ws-btn--dark" href="/app/billing">Upgrade</a>
<?php endif; ?>
  </section>

<?php if ($fresh): ?>
  <div class="tm-link" role="status">
    <p><b><?= $fresh['type'] === 'key' ? 'Your new API key' : 'Your webhook signing secret' ?></b> Copy it now. It won’t be shown again.</p>
    <input class="idea-input" readonly value="<?= e($fresh['value']) ?>" aria-label="<?= $fresh['type'] === 'key' ? 'API key' : 'Signing secret' ?>">
  </div>
<?php endif; ?>

<?php if ($paid): ?>
  <section class="set-block" aria-labelledby="dev-keys">
    <h2 id="dev-keys" class="tm-h">API keys <small><?= count($keys) ?></small></h2>
    <form class="set-inline dev-new" action="/app/developers" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="key">
      <label class="sr-only" for="dev-key-name">Key name</label>
      <input class="idea-input" id="dev-key-name" name="name" maxlength="60" placeholder="What it’s for, like “Zapier”">
      <button type="submit" class="ws-btn ws-btn--dark">Create key</button>
    </form>
    <ul class="tm-list" role="list">
<?php foreach ($keys as $k): ?>
      <li class="tm-person">
        <div><b><?= e($k['name']) ?></b><small><code><?= e($k['prefix']) ?>…</code> · created <?= e($when($k['created_at'])) ?> · last used <?= e($when($k['last_used_at'])) ?></small></div>
        <form action="/app/developers" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
          <button type="submit" name="action" value="revoke" class="ws-btn ws-btn--sm ws-btn--ghost" data-confirm="Revoke this key? Apps using it stop working right away.">Revoke</button>
        </form>
      </li>
<?php endforeach; ?>
    </ul>
  </section>

  <section class="set-block" aria-labelledby="dev-hooks">
    <h2 id="dev-hooks" class="tm-h">Webhooks <small><?= count($hooks) ?></small></h2>
    <form class="tm-form" action="/app/developers" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="hook">
      <label class="bio-field"><span>Endpoint URL (https)</span><input class="idea-input" name="url" type="url" maxlength="500" required placeholder="https://hooks.zapier.com/hooks/catch/…"></label>
      <fieldset class="tm-access"><legend>Send these events</legend>
<?php foreach (Webhooks::EVENTS as $event => $label): ?>
        <label class="tm-radio"><input type="checkbox" name="events[]" value="<?= e($event) ?>" checked> <code><?= e($event) ?></code> <?= e($label) ?></label>
<?php endforeach; ?>
      </fieldset>
      <button type="submit" class="ws-btn ws-btn--dark">Add webhook</button>
    </form>
    <ul class="tm-list" role="list">
<?php foreach ($hooks as $h): ?>
      <li class="tm-person">
        <div><b class="dev-url"><?= e($h['url']) ?></b><small><?= e(str_replace(',', ', ', $h['events'])) ?> · last delivery <?= e($when($h['last_sent_at'])) ?><?= $h['last_status'] !== null ? ' (' . (int) $h['last_status'] . ')' : '' ?><?= (int) $h['failures'] >= 20 ? ' · paused after 20 failures' : ((int) $h['failures'] > 0 ? ' · ' . (int) $h['failures'] . ' failures in a row' : '') ?></small></div>
        <form action="/app/developers" method="post" class="set-inline">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
          <button type="submit" name="action" value="test" class="ws-btn ws-btn--sm">Send test</button>
          <button type="submit" name="action" value="unhook" class="ws-btn ws-btn--sm ws-btn--ghost">Remove</button>
        </form>
      </li>
<?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
</div>
