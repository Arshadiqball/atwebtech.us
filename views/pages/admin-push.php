<?php
/** @var array $user */
/** @var array<string, array{people:int, devices:int}> $reach */
/** @var list<string> $missing */
/** @var list<array> $history */
$form = flash('form');
$form = is_array($form) && ($form['form'] ?? '') === 'admin-push' ? $form : null;
$old = $form['old'] ?? [];
$notice = flash('notice');
$audience = (string) ($old['audience'] ?? 'all');
$link = (string) ($old['url'] ?? '/app/notifications');
$inbox = $old ? !empty($old['in_inbox']) : true;
?>
<div class="adm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'push']); ?>
  <div class="adm-kb adm-push">
    <header class="adm-push-head">
      <h1>Push updates</h1>
      <p>Send an update to people who use the atwebtech app: a notification on their phone, and an entry in the app’s Notifications list.</p>
    </header>

<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="adm-push-note is-ok" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($form): ?>
    <p class="adm-push-note is-bad" role="alert"><?= e((string) $form['message']) ?></p>
<?php endif; ?>

    <ul class="adm-push-reach" role="list">
<?php foreach (Broadcasts::AUDIENCES as $key => $label): ?>
      <li><b><?= (int) $reach[$key]['devices'] ?></b><span><?= e($label) ?></span><small><?= (int) $reach[$key]['people'] ?> signed in · <?= (int) $reach[$key]['devices'] ?> with notifications on</small></li>
<?php endforeach; ?>
    </ul>

<?php if ($missing): ?>
    <div class="adm-push-setup">
      <h2>Finish setting up push</h2>
      <p>Phones can’t receive a push until these are in place. Updates can still be added to the app’s Notifications list.</p>
      <ul>
<?php foreach ($missing as $item): ?>
        <li><?= e($item) ?></li>
<?php endforeach; ?>
      </ul>
    </div>
<?php endif; ?>

    <form class="adm-push-form" action="/admin/push" method="post">
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
      <label for="push-title">Title</label>
      <input id="push-title" name="title" type="text" maxlength="<?= Broadcasts::TITLE_MAX ?>" required value="<?= e((string) ($old['title'] ?? '')) ?>" placeholder="New: carousels for Threads">
      <label for="push-body">Message</label>
      <textarea id="push-body" name="body" rows="3" maxlength="<?= Broadcasts::BODY_MAX ?>" required placeholder="Add up to 10 photos to one post. Open the composer to try it."><?= e((string) ($old['body'] ?? '')) ?></textarea>
      <div class="adm-push-row">
        <div>
          <label for="push-audience">Send to</label>
          <select id="push-audience" name="audience">
<?php foreach (Broadcasts::AUDIENCES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $audience === $key ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="push-url">A tap opens</label>
          <select id="push-url" name="url">
<?php foreach (Broadcasts::LINKS as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $link === $key ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>
      <label class="adm-push-check"><input type="checkbox" name="in_inbox" value="1"<?= $inbox ? ' checked' : '' ?>> Also add to their Notifications list in the app</label>
      <button type="submit">Send update</button>
      <p class="adm-push-hint">It goes out as soon as you press Send and can’t be recalled.</p>
    </form>

    <h2 class="adm-push-sub">Sent</h2>
<?php if (!$history): ?>
    <p class="adm-push-empty">Nothing sent yet.</p>
<?php else: ?>
    <ul class="adm-push-history" role="list">
<?php foreach ($history as $row): ?>
      <li>
        <b><?= e((string) $row['title']) ?></b>
        <p><?= e((string) $row['body']) ?></p>
        <small><?= e(Admin::when((string) $row['created_at'])->format('j M Y, g:i A')) ?> · <?= e(Broadcasts::AUDIENCES[$row['audience']] ?? (string) $row['audience']) ?> · push delivered to <?= (int) $row['delivered'] ?> of <?= (int) $row['devices'] ?><?= (int) $row['failed'] > 0 ? ' (' . (int) $row['failed'] . ' failed)' : '' ?><?= (int) $row['in_inbox'] ? ' · added to the Notifications list of ' . (int) $row['people'] . ((int) $row['people'] === 1 ? ' person' : ' people') : '' ?><?= $row['sent_by'] ? ' · by ' . e((string) $row['sent_by']) : '' ?></small>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </div>
</div>
