<?php
/** @var array $user */
/** @var array $posts  Posts with status "review" */
/** @var ?array $reviewLink */
$uid = (int) $user['id'];
$tz = Workspace::timezone($user);
$accounts = array_column(Channels::forUser($uid), null, 'id');
$token = Security::csrfToken();
$json = static fn (array $data): string => e(json_encode($data, JSON_UNESCAPED_UNICODE));
$freshToken = $_SESSION['_review_link'] ?? null;
unset($_SESSION['_review_link']);
$freshUrl = $freshToken ? public_origin() . '/review/' . $freshToken : null;
$freshInvite = $_SESSION['_invite_link'] ?? null;
unset($_SESSION['_invite_link']);
$invites = Approvals::invites($uid);
?>
<section class="ap-share" aria-labelledby="ap-share-title">
  <div>
    <h2 id="ap-share-title"><?= icon('share-network') ?>Share for review</h2>
    <p>Send a private link to a client or manager. They can approve posts or ask for changes without an account. Links expire after <?= Approvals::LINK_DAYS ?> days.</p>
  </div>
<?php if ($freshUrl): ?>
  <div class="ap-link">
    <input class="idea-input" value="<?= e($freshUrl) ?>" readonly aria-label="Review link">
    <button type="button" class="ws-btn ws-btn--dark" data-copy-text="<?= e($freshUrl) ?>"><span aria-live="polite">Copy link</span></button>
  </div>
<?php elseif ($reviewLink): ?>
  <p class="ap-link-state"><?= icon('check-circle') ?>A review link is active until <?= e(Workspace::local((string) $reviewLink['expires_at'], $tz)->format('j M Y')) ?>.</p>
<?php endif; ?>
  <form action="/app/approvals" method="post" class="ap-share-acts">
    <input type="hidden" name="_token" value="<?= e($token) ?>">
    <button type="submit" class="ws-btn<?= $reviewLink ? '' : ' ws-btn--dark' ?>" name="action" value="link"><?= icon('link-simple') ?><?= $reviewLink ? 'Create a new link' : 'Create review link' ?></button>
<?php if ($reviewLink): ?>
    <button type="submit" class="ws-btn" name="action" value="revoke" data-confirm="Turn off the review link? Anyone who has it can no longer open it.">Turn off link</button>
<?php endif; ?>
  </form>
</section>

<section class="ap-share ap-invite" id="invite" aria-labelledby="ap-invite-title">
  <div>
    <h2 id="ap-invite-title"><?= icon('users-three') ?>Invite a reviewer</h2>
    <p>Add a client or manager as a reviewer. They get their own private link by email to approve posts or ask for changes, with no account needed. Remove them any time. To give teammates their own login and role, use <a href="/app/team">Team</a>.</p>
  </div>
  <form action="/app/approvals" method="post" class="ap-invite-form">
    <input type="hidden" name="_token" value="<?= e($token) ?>">
    <input type="hidden" name="action" value="invite">
    <label class="sr-only" for="ap-invite-name">Name</label>
    <input class="idea-input" id="ap-invite-name" name="name" maxlength="18" placeholder="Name (optional)" autocomplete="off">
    <label class="sr-only" for="ap-invite-email">Email</label>
    <input class="idea-input" id="ap-invite-email" type="email" name="email" maxlength="60" placeholder="teammate@company.com" required autocomplete="off">
    <button type="submit" class="ws-btn ws-btn--dark"><?= icon('paper-plane-tilt') ?>Send invite</button>
  </form>
<?php if ($freshInvite): ?>
  <div class="ap-link">
    <input class="idea-input" value="<?= e($freshInvite['url']) ?>" readonly aria-label="Review link for <?= e($freshInvite['email']) ?>">
    <button type="button" class="ws-btn ws-btn--dark" data-copy-text="<?= e($freshInvite['url']) ?>"><span aria-live="polite">Copy link</span></button>
  </div>
<?php endif; ?>
<?php if ($invites): ?>
  <ul class="ap-team" role="list">
<?php foreach ($invites as $inv): ?>
    <li>
      <span class="ap-team-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($inv['name'] !== '' ? $inv['name'] : $inv['email'], 0, 1))) ?></span>
      <span><b><?= e($inv['name'] !== '' ? $inv['name'] : $inv['email']) ?></b><small><?= $inv['name'] !== '' ? e($inv['email']) . ' · ' : '' ?>Reviewer until <?= e(Workspace::local((string) $inv['expires_at'], $tz)->format('j M Y')) ?></small></span>
      <form action="/app/approvals" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
        <button type="submit" class="ws-btn ws-btn--sm" name="action" value="uninvite" data-confirm="Remove <?= e($inv['email']) ?>? Their review link stops working.">Remove</button>
      </form>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
</section>

<?php if ($posts): ?>
  <div class="ap-head">
    <h2><?= count($posts) ?> waiting for approval</h2>
    <form action="/app/approvals" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <button type="submit" class="ws-btn ws-btn--green" name="action" value="approve_all" data-confirm="Approve and schedule all <?= count($posts) ?> posts?"><?= icon('check') ?>Approve all</button>
    </form>
  </div>
  <ul class="dr-list ap-list" role="list">
<?php foreach ($posts as $post): $at = $post['planned_at'] ? Workspace::local((string) $post['planned_at'], $tz) : null; ?>
    <li class="dr-card ap-card">
      <?= post_mark($post, $accounts) ?>
      <div class="dr-body">
<?php if ($post['title']): ?><b><?= e($post['title']) ?></b><?php endif; ?>
<?php if ($post['body'] !== ''): ?><p><?= nl2br(e(mb_strimwidth((string) $post['body'], 0, 400, '…'))) ?></p><?php endif; ?>
        <small><?= $at ? 'Planned for ' . e($at->format('D j M, g:i A')) : 'No time set' ?><?= $post['media_id'] ? ' · file attached' : '' ?></small>
      </div>
      <div class="ap-acts">
        <form action="/app/approvals" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
          <button type="submit" class="ws-btn ws-btn--sm ws-btn--green" name="action" value="approve"><?= icon('check') ?>Approve</button>
        </form>
        <a class="ws-btn ws-btn--sm" href="/app/publish/new?id=<?= (int) $post['id'] ?>" data-compose="<?= $json(Workspace::composeData($post, $tz, $uid)) ?>"><?= icon('pencil-simple') ?>Edit</a>
        <details class="ap-changes">
          <summary class="ws-btn ws-btn--sm">Request changes</summary>
          <form action="/app/approvals" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
            <textarea class="idea-input" name="note" rows="2" maxlength="1000" placeholder="What should change?" required></textarea>
            <button type="submit" class="ws-btn ws-btn--sm ws-btn--dark" name="action" value="changes">Send to Drafts</button>
          </form>
        </details>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon wsp-empty-icon--purple"><?= icon('check-circle') ?></span>
    <h2>Nothing waiting for approval</h2>
    <p>In the post composer, choose <b>Send for approval</b> instead of Schedule. The post waits here and publishes only after it's approved, by you or by anyone you share the review link with.</p>
    <a class="ws-btn ws-btn--dark" href="/app/publish/new" data-compose="{}"><?= icon('plus') ?>Create a post</a>
  </div>
<?php endif; ?>
