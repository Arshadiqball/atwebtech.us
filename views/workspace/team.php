<?php
/** @var array $user */
/** @var array|false $owner */
/** @var array $members */
/** @var array $invites */
/** @var bool $plan */
/** @var array $teamChannels */
/** @var ?array $link */
$token = Security::csrfToken();
$tz = Workspace::timezone($user);
$canManage = Team::can('admin');
$isOwner = Team::role() === 'owner';
$byId = array_column($teamChannels, null, 'id');
$access = static function (?string $json) use ($byId): string {
    if ($json === null) {
        return 'All channels';
    }
    $names = array_map(static fn ($id): string => $byId[(int) $id]['name'] ?? 'Removed channel', (array) json_decode($json, true));
    return $names ? implode(', ', $names) : 'No channels';
};
$picker = static function (string $prefix, ?string $json) use ($teamChannels): string {
    $chosen = $json === null ? null : array_map('intval', (array) json_decode($json, true));
    ob_start(); ?>
      <fieldset class="tm-access">
        <legend>Channel access</legend>
        <label class="tm-radio"><input type="radio" name="access" value="all"<?= $chosen === null ? ' checked' : '' ?>> All channels, including ones connected later</label>
        <label class="tm-radio"><input type="radio" name="access" value="some"<?= $chosen !== null ? ' checked' : '' ?>> Only these channels:</label>
        <div class="tm-channels">
<?php foreach ($teamChannels as $c): ?>
          <label><input type="checkbox" name="channels[]" value="<?= (int) $c['id'] ?>"<?= $chosen !== null && in_array((int) $c['id'], $chosen, true) ? ' checked' : '' ?>> <?= channel_avatar($c, 'sm') ?><?= e($c['name']) ?></label>
<?php endforeach; ?>
<?php if (!$teamChannels): ?>
          <small>No channels connected yet.</small>
<?php endif; ?>
        </div>
      </fieldset>
<?php return (string) ob_get_clean();
};
$roleSelect = static function (string $current) use ($isOwner): string {
    $out = '<select class="idea-input" name="role" aria-label="Role">';
    foreach (Team::ROLES as $key => [$label]) {
        if ($key === 'admin' && !$isOwner && $current !== 'admin') {
            continue;
        }
        $out .= '<option value="' . e($key) . '"' . ($key === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $out . '</select>';
};
?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'user-plus', 'title' => 'Team', 'actions' => '']); ?>

<?php if (!$plan): ?>
  <section class="tm-upsell">
    <h2>Work on social media together</h2>
    <p>On the Team plan, invite teammates and clients with their own login. Choose what each person can do and which channels they can use. Contributors’ posts wait for approval before anything is published.</p>
<?php if ($isOwner): ?>
    <a class="ws-btn ws-btn--dark" href="/app/billing">See the Team plan</a>
<?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($link): ?>
  <div class="tm-link" role="status">
    <p><b>Invite link for <?= e($link['email']) ?></b> Send it yourself if the email doesn’t arrive. It works once, for 7 days.</p>
    <input class="idea-input" readonly value="<?= e($link['url']) ?>" aria-label="Invite link" data-copy-text="<?= e($link['url']) ?>">
  </div>
<?php endif; ?>

<?php if ($plan && $canManage): ?>
  <section class="set-block" id="invite" aria-labelledby="tm-invite">
    <form class="tm-form" action="/app/team" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="invite">
      <h2 id="tm-invite">Invite a teammate</h2>
      <div class="tm-row">
        <label class="bio-field"><span>Email</span><input class="idea-input" type="email" name="email" required maxlength="254" placeholder="name@company.com"></label>
        <label class="bio-field"><span>Name (optional)</span><input class="idea-input" name="name" maxlength="80"></label>
        <label class="bio-field"><span>Role</span><?= $roleSelect('editor') ?></label>
      </div>
      <?= $picker('new', null) ?>
      <ul class="tm-roles" role="list">
<?php foreach (Team::ROLES as [$label, $text]): ?>
        <li><b><?= e($label) ?></b> <?= e($text) ?></li>
<?php endforeach; ?>
      </ul>
      <button type="submit" class="ws-btn ws-btn--dark">Send invite</button>
    </form>
  </section>
<?php endif; ?>

  <section class="set-block" aria-labelledby="tm-people">
    <h2 id="tm-people" class="tm-h">People <small><?= count($members) + 1 ?></small></h2>
    <ul class="tm-list" role="list">
<?php if ($owner): ?>
      <li class="tm-person">
        <span class="ws-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($owner['display_name'], 0, 1))) ?></span>
        <div><b><?= e($owner['display_name']) ?></b><small><?= e($owner['email']) ?></small></div>
        <span class="ws-pill">Owner</span>
      </li>
<?php endif; ?>
<?php foreach ($members as $m): $editable = $canManage && ($isOwner || $m['role'] !== 'admin'); $me = (int) $m['user_id'] === (int) ($user['person_id'] ?? 0); ?>
      <li class="tm-person">
        <span class="ws-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($m['display_name'], 0, 1))) ?></span>
        <div>
          <b><?= e($m['display_name']) ?><?= $me ? ' (you)' : '' ?></b>
          <small><?= e($m['email']) ?> · <?= e($access($m['channels'])) ?> · <?= $m['last_seen_at'] ? 'active ' . e(Workspace::local((string) $m['last_seen_at'], $tz)->format('j M')) : 'not active yet' ?></small>
        </div>
        <span class="ws-pill"><?= e(Team::ROLES[$m['role']][0] ?? $m['role']) ?></span>
<?php if ($editable): ?>
        <details class="tm-edit">
          <summary class="ws-btn ws-btn--sm">Edit</summary>
          <form action="/app/team" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <label class="bio-field"><span>Role</span><?= $roleSelect($m['role']) ?></label>
            <?= $picker('m' . (int) $m['id'], $m['channels']) ?>
            <div class="set-inline">
              <button type="submit" name="action" value="update" class="ws-btn ws-btn--dark ws-btn--sm">Save</button>
              <button type="submit" name="action" value="remove" class="ws-btn ws-btn--sm ws-btn--ghost" data-confirm="Remove <?= e($m['display_name']) ?> from the team? They lose access right away.">Remove</button>
            </div>
          </form>
        </details>
<?php elseif ($me): ?>
        <form action="/app/team" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <button type="submit" name="action" value="leave" class="ws-btn ws-btn--sm ws-btn--ghost" data-confirm="Leave this workspace? You’ll need a new invite to come back.">Leave</button>
        </form>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
  </section>

<?php if ($invites && $canManage): ?>
  <section class="set-block" aria-labelledby="tm-pending">
    <h2 id="tm-pending" class="tm-h">Pending invites <small><?= count($invites) ?></small></h2>
    <ul class="tm-list" role="list">
<?php foreach ($invites as $i): ?>
      <li class="tm-person">
        <span class="ws-avatar is-pending" aria-hidden="true"><?= icon('envelope-simple') ?></span>
        <div><b><?= e($i['name'] ?: $i['email']) ?></b><small><?= e($i['email']) ?> · <?= e(Team::ROLES[$i['role']][0] ?? $i['role']) ?> · <?= e($access($i['channels'])) ?> · expires <?= e(Workspace::local((string) $i['expires_at'], $tz)->format('j M')) ?></small></div>
        <form action="/app/team" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
          <button type="submit" name="action" value="revoke" class="ws-btn ws-btn--sm ws-btn--ghost">Cancel invite</button>
        </form>
      </li>
<?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
  <p class="wsp-muted tm-note">Clients who only need to approve posts don’t need an account: share a review link from Publish → Approvals.</p>
</div>
