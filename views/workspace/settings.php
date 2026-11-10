<?php
/** @var array $user  the workspace these settings belong to */
/** @var array $utm */
$token = Security::csrfToken();
?>
<div class="wsp set">
<?php partial('workspace/page-head', ['icon' => 'gear', 'title' => 'Workspace settings', 'actions' => '']); ?>
  <p class="wsp-muted set-scope">These apply to <b><?= e(isset($user['team_role']) ? $user['display_name'] : 'your workspace') ?></b>: every channel and everyone working in it. Your own name, password and email alerts are under <a href="/app/account">Account</a>.</p>

  <section class="set-block" id="utm" aria-labelledby="utm-title">
    <form class="set-row set-row--stack" action="/app/account/utm" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <div>
        <h2 id="utm-title">Link tracking (UTM) <?php if (!Channels::allowance((int) $user['id'])['paid']): ?><span class="ws-soon">Essentials and Team</span><?php endif; ?></h2>
        <p>When a post publishes, atwebtech adds <code>utm_source</code> (the network), <code>utm_medium=social</code>, <code>utm_campaign</code>, and <code>utm_content</code> to its links, so Google Analytics shows which posts bring visitors. Links that already have UTM tags are left alone.</p>
        <label class="bio-toggle" style="margin-top:12px"><input type="checkbox" name="utm_enabled" value="1"<?= $utm['enabled'] ? ' checked' : '' ?>><span><b>Add UTM tags to links</b><small>Applies to posts published from now on.</small></span></label>
        <label class="bio-field" style="margin-top:12px;max-width:360px"><span>Campaign name</span><input class="idea-input" name="utm_campaign" maxlength="80" value="<?= e($utm['campaign']) ?>" placeholder="atwebtech"></label>
      </div>
      <button type="submit" class="ws-btn ws-btn--dark">Save link tracking</button>
    </form>
  </section>

<?php $rs = ClientReports::settings((int) $user['id']); $paidReports = Channels::allowance((int) $user['id'])['paid']; ?>
  <section class="set-block" id="reports" aria-labelledby="reports-title">
    <form class="set-row set-row--stack" action="/app/account/reports" method="post" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <div>
        <h2 id="reports-title">Branded reports <?php if (!$paidReports): ?><span class="ws-soon">Essentials and Team</span><?php endif; ?></h2>
        <p>Put your logo, name and color on Insights reports, share a private report link with clients, and email the report every week or month.</p>
        <div class="rb-grid">
          <label class="bio-field"><span>Brand or client name</span><input class="idea-input" name="brand_name" maxlength="80" value="<?= e((string) $rs['brand_name']) ?>" placeholder="Acme Bakery"></label>
          <label class="bio-field"><span>Brand color</span><input class="idea-input rb-color" type="color" name="brand_color" value="<?= e($rs['brand_color']) ?>"></label>
          <label class="bio-field"><span>Logo (PNG, JPG or WebP, under 512 KB)</span><input class="idea-input" type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
<?php if ($rs['logo_file'] && $rs['share_token']): ?>
          <div class="rb-logo"><img src="/r/<?= e($rs['share_token']) ?>/logo" alt="Current logo"><button type="submit" name="action" value="remove_logo" class="ws-btn ws-btn--sm ws-btn--ghost">Remove logo</button></div>
<?php endif; ?>
          <label class="bio-field"><span>Email the report</span>
            <select class="idea-input" name="frequency">
<?php foreach (ClientReports::FREQUENCIES as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= $rs['frequency'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label class="bio-field"><span>Send to (up to <?= ClientReports::MAX_RECIPIENTS ?> emails, comma separated)</span><input class="idea-input" name="recipients" maxlength="600" value="<?= e(implode(', ', $rs['recipients'])) ?>" placeholder="client@example.com, you@example.com"></label>
        </div>
        <label class="bio-toggle" style="margin-top:12px"><input type="checkbox" name="white_label" value="1"<?= $rs['white_label'] ? ' checked' : '' ?>><span><b>White label</b><small>Leave “atwebtech” off reports and report emails.</small></span></label>
<?php if ($paidReports && $rs['share_token']): ?>
        <p class="rb-link">Client link: <a href="/r/<?= e($rs['share_token']) ?>" target="_blank" rel="noopener"><?= e(absolute_url('/r/' . $rs['share_token'])) ?></a> <small>Anyone with the link can see your report.<?= $rs['last_sent_at'] ? ' Last emailed ' . e(Workspace::local((string) $rs['last_sent_at'], Workspace::timezone($user))->format('j M Y')) . '.' : '' ?></small></p>
<?php endif; ?>
      </div>
      <div class="set-inline rb-acts">
        <button type="submit" name="action" value="save" class="ws-btn ws-btn--dark">Save branding</button>
<?php if ($paidReports && $rs['recipients']): ?>
        <button type="submit" name="action" value="send_now" class="ws-btn">Send report now</button>
<?php endif; ?>
<?php if ($paidReports && $rs['share_token']): ?>
        <button type="submit" name="action" value="new_link" class="ws-btn ws-btn--ghost" data-confirm="The current client link will stop working. Continue?">New link</button>
<?php endif; ?>
      </div>
    </form>
  </section>

</div>
