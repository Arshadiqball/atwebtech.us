<?php
/** @var array $user  the signed-in person (this page always runs on their own account) */
/** @var array $clients */
/** @var bool $plan */
/** @var bool $isClient */
$token = Security::csrfToken();
$tz = Workspace::timezone($user);
?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'arrows-left-right', 'title' => 'Client workspaces', 'actions' => '']); ?>

  <section class="tm-upsell">
    <h2>One workspace per client</h2>
    <p>Keep each client’s channels, posts, approvals, inbox, insights and branded reports separate. Switch between them from the account menu. Invite the client or your team into just their workspace from Team. Channels in every workspace count toward your plan.</p>
<?php if (!$plan && !$isClient): ?>
    <a class="ws-btn ws-btn--dark" href="/app/billing">See the Team plan</a>
<?php endif; ?>
  </section>

<?php if ($plan && !$isClient): ?>
  <section class="set-block">
    <form class="tm-form" action="/app/workspaces" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="create">
      <h2>New client workspace</h2>
      <div class="set-inline">
        <label class="sr-only" for="ws-new-name">Client or brand name</label>
        <input class="idea-input" id="ws-new-name" name="name" maxlength="80" required placeholder="Client or brand name">
        <button type="submit" class="ws-btn ws-btn--dark">Create and open</button>
      </div>
    </form>
  </section>
<?php endif; ?>

  <section class="set-block" aria-labelledby="ws-list">
    <h2 id="ws-list" class="tm-h">Your client workspaces <small><?= count($clients) ?> of <?= Team::MAX_WORKSPACES ?></small></h2>
<?php if (!$clients): ?>
    <p class="wsp-muted">None yet.</p>
<?php else: ?>
    <ul class="tm-list" role="list">
<?php foreach ($clients as $c): ?>
      <li class="tm-person">
        <span class="ws-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($c['display_name'], 0, 1))) ?></span>
        <div><b><?= e($c['display_name']) ?></b><small><?= (int) $c['channels'] ?> <?= (int) $c['channels'] === 1 ? 'channel' : 'channels' ?> · <?= (int) $c['people'] ?> <?= (int) $c['people'] === 1 ? 'person' : 'people' ?> invited · created <?= e(Workspace::local((string) $c['created_at'], $tz)->format('j M Y')) ?></small></div>
        <form action="/app/workspace" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <button type="submit" name="ws" value="<?= (int) $c['id'] ?>" class="ws-btn ws-btn--sm ws-btn--dark">Open</button>
        </form>
        <details class="tm-edit">
          <summary class="ws-btn ws-btn--sm">Edit</summary>
          <form action="/app/workspaces" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <label class="bio-field"><span>Name</span><input class="idea-input" name="name" maxlength="80" value="<?= e($c['display_name']) ?>"></label>
            <button type="submit" name="action" value="rename" class="ws-btn ws-btn--sm ws-btn--dark">Rename</button>
          </form>
          <form action="/app/workspaces" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <label class="bio-field"><span>Delete: type <b><?= e($c['display_name']) ?></b> to confirm. Its channels, posts, stats and team access are removed and can’t be recovered.</span><input class="idea-input" name="confirm" maxlength="80" autocomplete="off"></label>
            <button type="submit" name="action" value="delete" class="ws-btn ws-btn--sm ws-btn--ghost">Delete workspace</button>
          </form>
        </details>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
</div>
