<?php
/** @var string $tab */
/** @var string $view */
$url = static fn (array $params): string => '/app/community' . (($q = http_build_query(array_filter($params + ['tab' => $tab, 'view' => $view], static fn ($v) => $v !== 'comments' && $v !== 'post'))) !== '' ? '?' . $q : '');

ob_start(); ?>
    <nav class="ws-seg" aria-label="Layout">
      <a href="<?= e($url(['view' => 'post'])) ?>"<?= $view === 'post' ? ' aria-current="page"' : '' ?>>By post</a>
      <a href="<?= e($url(['view' => 'list'])) ?>"<?= $view === 'list' ? ' aria-current="page"' : '' ?>>List</a>
    </nav>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'chats-circle', 'title' => 'Community', 'actions' => $actions]); ?>

  <div class="wsp-bar">
    <nav class="wsp-tabs" aria-label="Community">
      <a href="<?= e($url(['tab' => 'comments'])) ?>"<?= $tab === 'comments' ? ' aria-current="page"' : '' ?>>Comments</a>
      <a href="<?= e($url(['tab' => 'mentions'])) ?>"<?= $tab === 'mentions' ? ' aria-current="page"' : '' ?>>Mentions</a>
    </nav>
    <div class="wsp-filters">
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn" aria-expanded="false" aria-controls="cm-channels" data-menu-toggle>Channels<?= icon('caret-down') ?></button>
        <div class="ws-menu ws-menu--end" id="cm-channels" hidden data-menu-panel>
          <p class="ws-menu-note">No channels connected yet.</p>
          <a class="ws-menu-item" href="/app/channels"><?= icon('plus') ?>Connect a channel</a>
        </div>
      </div>
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn" aria-expanded="false" aria-controls="cm-status" data-menu-toggle>All<?= icon('caret-down') ?></button>
        <div class="ws-menu ws-menu--end" id="cm-status" hidden data-menu-panel>
          <span class="ws-menu-item" aria-current="true">All</span>
          <span class="ws-menu-item is-disabled" aria-disabled="true">Unanswered</span>
          <span class="ws-menu-item is-disabled" aria-disabled="true">Answered</span>
          <p class="ws-menu-note">Filters work once comments arrive.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="cm<?= $view === 'list' ? ' cm--list' : '' ?>">
<?php if ($view === 'post'): ?>
    <aside class="cm-rail" aria-label="Posts">
      <div class="cm-rail-skel" aria-hidden="true"><i></i><i></i><i></i></div>
      <p>Your posts will appear here</p>
    </aside>
<?php endif; ?>
    <section class="cm-listen" aria-labelledby="cm-title">
      <span class="cm-wave" aria-hidden="true"><i></i><i></i><i></i></span>
      <h2 id="cm-title"><?= $tab === 'mentions' ? 'Mentions aren’t read yet' : 'Comments aren’t read yet' ?></h2>
      <p>atwebtech doesn’t collect comments or mentions from any network. This inbox will stay empty until that is built.</p>
      <p class="cm-meantime">In the meantime, keep posting from your queue.</p>
      <ul class="cm-cards" role="list">
        <li>
          <a class="cm-card" href="/app/publish/new">
            <?= icon('note-pencil') ?>
            <span><b>Post something new</b>Use a template or write something that starts a conversation.</span>
          </a>
        </li>
        <li>
          <a class="cm-card" href="/app/channels">
            <?= icon('plus') ?>
            <span><b>Connect more channels</b>YouTube and a LinkedIn Profile can publish today. Other networks stay Coming soon.</span>
          </a>
        </li>
      </ul>
    </section>
  </div>
</div>
