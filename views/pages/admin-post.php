<?php
/** @var array $user */
/** @var array $post from AdminAccounts::post() */
$notice = flash('notice');
$token = Security::csrfToken();
$tz = AdminAccounts::zone($post);
$at = AdminAccounts::local($post['planned_at'], $tz);
$sent = $post['status'] === 'sent' ? AdminAccounts::local($post['sent_at'] ?: $post['planned_at'], $tz) : null;
$locked = in_array($post['status'], ['sent', 'publishing'], true);
$text = trim((string) ($post['title'] ?: $post['body']));
$facts = array_filter([
    'Owner' => $post['email'],
    'Channel' => $post['channel_id'] ? $post['channel_name'] . ' (' . ucfirst((string) $post['provider']) . ')' . ($post['paused_at'] !== null ? ' · paused' : '') : ucfirst((string) ($post['channel'] ?: 'none')) . ' (plan only, no connected channel)',
    'Status' => AdminAccounts::STATUSES[$post['status']] ?? $post['status'],
    'Scheduled for' => $at ? $at->format('D j M Y, g:i A') . ' · ' . Workspace::timezoneLabel($tz) : null,
    'Published' => $sent ? $sent->format('D j M Y, g:i A') : null,
    'Attempts' => (int) $post['attempts'] > 0 ? (string) $post['attempts'] : null,
    'Last error' => $post['error'] ?: null,
    'Created' => Admin::when((string) $post['created_at'])->setTimezone($tz)->format('j M Y, g:i A'),
]);
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'posts']); ?>
  <div class="crm-wrap">
    <a class="crm-link crm-backlink" href="/admin/posts"><?= icon('arrow-left') ?>All posts</a>
    <header class="crm-head">
      <div>
        <h1>Post #<?= (int) $post['id'] ?></h1>
        <p><?= e($text !== '' ? mb_strimwidth($text, 0, 120, '…') : '(no text)') ?></p>
      </div>
      <div class="crm-actions">
<?php if ($post['external_url']): ?>
        <a class="crm-btn" href="<?= e((string) $post['external_url']) ?>" target="_blank" rel="noopener noreferrer">View on network</a>
<?php endif; ?>
        <a class="crm-btn" href="/admin/users/<?= (int) $post['user_id'] ?>">Owner</a>
<?php if ($post['status'] !== 'publishing'): ?>
        <form class="crm-inline" method="post" action="/admin/posts/<?= (int) $post['id'] ?>/delete">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="back" value="/admin/posts">
          <button class="crm-btn" type="submit" data-confirm="<?= $post['status'] === 'sent' ? 'Delete this post from atwebtech? It stays on the network; only atwebtech’s copy is removed.' : 'Delete this post? This cannot be undone.' ?>">Delete</button>
        </form>
<?php endif; ?>
      </div>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
    <div class="crm-grid crm-grid--detail">
      <div class="crm-col-main">
        <section class="crm-card" aria-labelledby="post-edit">
          <h2 id="post-edit"><?= $locked ? 'Content' : 'Edit and reschedule' ?></h2>
<?php if ($locked): ?>
          <p class="crm-fine"><?= $post['status'] === 'sent' ? 'This post is already published. Editing here would not change it on the network, so it is read-only.' : 'This post is being sent right now. Refresh in a minute.' ?></p>
<?php if ($post['title']): ?><p><b><?= e((string) $post['title']) ?></b></p><?php endif; ?>
          <p><?= nl2br(e((string) $post['body'])) ?></p>
<?php else: ?>
          <form method="post" action="/admin/posts/<?= (int) $post['id'] ?>" class="crm-note-form">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <label for="post-title">Title <small>(YouTube, Pinterest and similar; leave empty if not used)</small></label>
            <input class="crm-input" id="post-title" name="title" maxlength="300" value="<?= e((string) $post['title']) ?>">
            <label for="post-body">Text</label>
            <textarea class="crm-input" id="post-body" name="body" rows="9" maxlength="65000"><?= e((string) $post['body']) ?></textarea>
            <div class="crm-filters">
              <label>Date <input class="crm-input" type="date" name="date" value="<?= $at ? e($at->format('Y-m-d')) : '' ?>"></label>
              <label>Time <input class="crm-input" type="time" name="time" value="<?= $at ? e($at->format('H:i')) : '' ?>"></label>
              <label>Status
                <select class="crm-input" name="status">
<?php foreach (['planned' => 'Scheduled', 'review' => 'Waiting for approval', 'draft' => 'Draft'] as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= ($post['status'] === $k || ($post['status'] === 'failed' && $k === 'planned')) ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
                </select>
              </label>
            </div>
            <p class="crm-fine">Times are in the owner’s time zone, <?= e(Workspace::timezoneLabel($tz)) ?>. A scheduled post whose time has passed goes out on the next publishing run (within about 5 minutes).<?= $post['status'] === 'failed' ? ' Saving a failed post as Scheduled clears the error and tries again.' : '' ?></p>
            <button class="crm-btn crm-btn--dark" type="submit">Save</button>
          </form>
<?php endif; ?>
        </section>
      </div>
      <aside class="crm-col-side">
        <section class="crm-card" aria-labelledby="post-facts">
          <h2 id="post-facts">Details</h2>
          <dl class="crm-facts">
<?php foreach ($facts as $label => $value): ?>
            <dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd>
<?php endforeach; ?>
          </dl>
        </section>
      </aside>
    </div>
  </div>
</div>
