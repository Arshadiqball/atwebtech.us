<?php
/** @var array $user */
/** @var array $items */
/** @var string $filter */
/** @var int $used  bytes */
$token = Security::csrfToken();
$tz = Workspace::timezone($user);
$mb = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
$quota = 2 * 1024 * 1024 * 1024;
ob_start(); ?>
    <label class="ws-btn ws-btn--dark lib-upload">
      <?= icon('plus') ?>Upload files
      <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm" data-lib-file hidden>
    </label>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp" data-lib data-token="<?= e($token) ?>">
<?php partial('workspace/page-head', ['icon' => 'image', 'title' => 'Media Library', 'actions' => $actions]); ?>

  <div class="wsp-bar">
    <nav class="wsp-tabs" aria-label="Show">
<?php foreach (Library::FILTERS as $key => $label): ?>
      <a href="/app/library<?= $key === 'all' ? '' : '?show=' . e($key) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
    </nav>
    <p class="lib-quota"><?= e($mb($used)) ?> of 2 GB used <span class="lib-meter" aria-hidden="true"><i style="width: <?= min(100, round($used / $quota * 100, 1)) ?>%"></i></span></p>
  </div>

  <div class="lib-drop" data-lib-drop>
    <p data-lib-status>Drop images or videos here to keep them for later posts. Images up to 10 MB, videos up to 200 MB.</p>
    <div class="lib-progress" data-lib-progress hidden><i></i></div>
  </div>

<?php if (!$items): ?>
  <div class="in-empty">
    <?= icon('image') ?>
    <p><?= $filter === 'all' ? 'Your library is empty. Upload photos and videos here, or attach them to a post, and they’ll show up for reuse.' : 'Nothing here.' ?></p>
  </div>
<?php else: ?>
  <ul class="lib-grid" role="list">
<?php foreach ($items as $m): $id = (int) $m['id']; ?>
    <li class="lib-card" id="m-<?= $id ?>">
      <a class="lib-thumb" href="/app/media/<?= $id ?>" target="_blank" rel="noopener" title="Open full size">
<?php if ($m['kind'] === 'video'): ?>
        <video src="/app/media/<?= $id ?>#t=0.5" preload="metadata" muted playsinline></video>
        <span class="lib-badge"><?= icon('play') ?>Video</span>
<?php else: ?>
        <img src="/app/media/<?= $id ?>" alt="" loading="lazy">
<?php endif; ?>
      </a>
      <div class="lib-info">
        <form action="/app/library" method="post" class="lib-name">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="rename">
          <input type="hidden" name="show" value="<?= e($filter) ?>">
          <label class="sr-only" for="lib-n-<?= $id ?>">File name</label>
          <input id="lib-n-<?= $id ?>" name="name" value="<?= e($m['original_name']) ?>" maxlength="190">
        </form>
        <small><?= e($mb((int) $m['size'])) ?> · <?= e(Workspace::local((string) $m['created_at'], $tz)->format('j M Y')) ?> · <?= (int) $m['used'] === 0 ? 'not in a post' : 'in ' . (int) $m['used'] . ' ' . ((int) $m['used'] === 1 ? 'post' : 'posts') ?><?= (int) $m['in_library'] === 1 ? ' · kept' : '' ?></small>
      </div>
      <div class="lib-acts">
        <a class="ws-btn ws-btn--sm ws-btn--dark" href="/app/publish/new?media=<?= $id ?>"><?= icon('note-pencil') ?>Create post</a>
        <form action="/app/library" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="show" value="<?= e($filter) ?>">
<?php if ((int) $m['in_library'] === 1): ?>
          <button type="submit" name="action" value="release" class="ws-btn ws-btn--sm ws-btn--ghost" title="Let atwebtech delete it a week after no post uses it">Un-keep</button>
<?php else: ?>
          <button type="submit" name="action" value="keep" class="ws-btn ws-btn--sm ws-btn--ghost" title="Keep this file even when no post uses it">Keep</button>
<?php endif; ?>
          <button type="submit" name="action" value="delete" class="ws-btn ws-btn--sm ws-btn--ghost" data-confirm="Delete this file? Sent posts keep their link, but it can’t be reused."><?= icon('trash') ?><span class="sr-only">Delete</span></button>
        </form>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
  <script src="<?= e(asset('js/library.js')) ?>" defer></script>
</div>
