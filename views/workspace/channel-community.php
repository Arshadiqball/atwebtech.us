<?php
/** @var array $channel */
/** @var string $tab */
/** @var string $view */
$cid = (int) $channel['id'];
$self = '/app/channels/' . $cid . '/community';
$url = static fn (array $params): string => $self . (($q = http_build_query(array_filter($params + ['tab' => $tab, 'view' => $view], static fn ($v) => $v !== 'comments' && $v !== 'post'))) !== '' ? '?' . $q : '');
$target = 'c:' . $cid;
$compose = e(json_encode(['targets' => [$target]]));
?>
<div class="wsp cm-page">
  <header class="wsp-head ch-head">
    <div class="wsp-title">
      <?= channel_avatar($channel, 'md') ?>
      <div>
        <h1><?= e($channel['name']) ?>
          <a class="ws-icon-btn ws-icon-btn--sm" href="/app/channels/<?= $cid ?>/settings" title="Channel settings"><?= icon('gear') ?><span class="sr-only">Channel settings</span></a>
        </h1>
      </div>
    </div>
    <div class="wsp-actions">
      <nav class="ws-seg" aria-label="Layout">
        <a href="<?= e($url(['view' => 'post'])) ?>"<?= $view === 'post' ? ' aria-current="page"' : '' ?>>By post</a>
        <a href="<?= e($url(['view' => 'list'])) ?>"<?= $view === 'list' ? ' aria-current="page"' : '' ?>>List</a>
      </nav>
    </div>
  </header>

  <div class="wsp-bar">
    <nav class="wsp-tabs" aria-label="Community">
      <a href="<?= e($url(['tab' => 'comments'])) ?>"<?= $tab === 'comments' ? ' aria-current="page"' : '' ?>>Comments</a>
      <a href="<?= e($url(['tab' => 'mentions'])) ?>"<?= $tab === 'mentions' ? ' aria-current="page"' : '' ?>>Mentions</a>
    </nav>
    <div class="wsp-filters">
      <button type="button" class="ws-dd-btn" disabled>All<?= icon('caret-down') ?></button>
    </div>
  </div>

  <div class="cm<?= $view === 'list' ? ' cm--list' : '' ?>">
<?php if ($view === 'post'): ?>
    <aside class="cm-rail" aria-label="Posts">
      <div class="cm-rail-head">
        <h2>Posts</h2>
      </div>
      <p class="cm-rail-empty"><b>No posts found</b><span>When atwebtech can read comments, posts that have them will appear here. It doesn’t read comments today.</span></p>
      <a class="ws-btn" href="/app/channels/<?= $cid ?>?tab=sent">Show All Posts</a>
    </aside>
<?php endif; ?>
    <section class="cm-listen" aria-labelledby="cm-title">
      <span class="cm-wave" aria-hidden="true"><i></i><i></i><i></i></span>
      <h2 id="cm-title"><?= $tab === 'mentions' ? 'Mentions aren’t read yet' : 'Comments aren’t read yet' ?></h2>
      <p>atwebtech doesn’t listen for <?= $tab === 'mentions' ? 'mentions' : 'comments' ?> on <?= e($channel['name']) ?>. Nothing will appear here, and we won’t notify you.</p>
      <p class="cm-meantime">In the meantime, keep posting from your queue.</p>
      <ul class="cm-cards" role="list">
        <li>
          <a class="cm-card" href="/app/publish/new?target=<?= e($target) ?>" data-compose="<?= $compose ?>">
            <?= icon('note-pencil') ?>
            <span><b>Post something new</b>Use a template or write something that starts a conversation.</span>
          </a>
        </li>
        <li>
          <a class="cm-card" href="/app/channels">
            <?= icon('plus') ?>
            <span><b>Connect more channels</b>Other networks stay Coming soon. YouTube and a LinkedIn Profile can already publish.</span>
          </a>
        </li>
      </ul>
    </section>
  </div>
</div>
