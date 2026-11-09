<?php
/** @var array $user */
/** @var array $posts  Posts that failed to publish */
$uid = (int) $user['id'];
$tz = Workspace::timezone($user);
$accounts = array_column(Channels::forUser($uid), null, 'id');
$token = Security::csrfToken();
$json = static fn (array $data): string => e(json_encode($data, JSON_UNESCAPED_UNICODE));
?>
<?php if ($posts): ?>
  <div class="ap-head">
    <div>
      <h2><?= count($posts) ?> failed to publish</h2>
      <p class="ap-sub">Fix what each message asks for, then retry. Posts that need a different file or a reconnected channel will fail again until that's done.</p>
    </div>
    <form action="/app/posts" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="return" value="/app/publish?tab=failed">
      <button type="submit" class="ws-btn ws-btn--dark" name="action" value="retry_all" data-confirm="Retry all <?= count($posts) ?> failed posts now?"><?= icon('arrows-clockwise') ?>Retry all</button>
    </form>
  </div>
  <ul class="dr-list ap-list" role="list">
<?php foreach ($posts as $post): $at = $post['planned_at'] ? Workspace::local((string) $post['planned_at'], $tz) : null; ?>
    <li class="dr-card ap-card ap-card--failed">
      <?= post_mark($post, $accounts) ?>
      <div class="dr-body">
<?php if ($post['title']): ?><b><?= e($post['title']) ?></b><?php endif; ?>
<?php if ($post['body'] !== ''): ?><p><?= nl2br(e(mb_strimwidth((string) $post['body'], 0, 280, '…'))) ?></p><?php endif; ?>
        <p class="q-error"><?= icon('warning-circle') ?><?= e($post['error'] ?: 'Publishing failed.') ?></p>
        <small><?= $at ? 'Was due ' . e($at->format('D j M, g:i A')) : '' ?><?= (int) $post['attempts'] > 0 ? ' · tried ' . (int) $post['attempts'] . ' ' . ((int) $post['attempts'] === 1 ? 'time' : 'times') : '' ?></small>
      </div>
      <div class="ap-acts">
        <form action="/app/posts" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
          <input type="hidden" name="return" value="/app/publish?tab=failed">
          <button type="submit" class="ws-btn ws-btn--sm" name="action" value="retry"><?= icon('arrows-clockwise') ?>Retry</button>
        </form>
        <a class="ws-btn ws-btn--sm" href="/app/publish/new?id=<?= (int) $post['id'] ?>" data-compose="<?= $json(Workspace::composeData($post, $tz, $uid)) ?>"><?= icon('pencil-simple') ?>Edit</a>
        <form action="/app/posts" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
          <input type="hidden" name="return" value="/app/publish?tab=failed">
          <button type="submit" class="ws-icon-btn" name="action" value="delete" data-confirm="Delete this post?"><?= icon('trash') ?><span class="sr-only">Delete</span></button>
        </form>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('check-circle') ?></span>
    <h2>No failed posts</h2>
    <p>If a network turns a post down, it lands here with the reason and a Retry button.</p>
  </div>
<?php endif; ?>
