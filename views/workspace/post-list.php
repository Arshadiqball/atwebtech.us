<?php
/** @var array $user */
/** @var array $posts  Drafts or sent posts */
/** @var string $kind  'drafts' | 'sent' */
/** @var ?string $compose JSON prefill for the empty state's New Post button */
$uid = (int) $user['id'];
$tz = Workspace::timezone($user);
$accounts = array_column(Channels::forUser($uid), null, 'id');
$excerpt = static fn (string $text, int $len): string => mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len)) . '…' : $text;
$json = static fn (array $data): string => e(json_encode($data, JSON_UNESCAPED_UNICODE));
$compose ??= '{}';
?>
<?php if ($posts): ?>
  <ul class="dr-list" role="list">
<?php foreach ($posts as $post): ?>
    <li class="dr-card">
      <?= post_mark($post, $accounts) ?>
      <div class="dr-body">
<?php if ($post['title']): ?><b><?= e($post['title']) ?></b><?php endif; ?>
<?php if ($post['body'] !== ''): ?><p><?= nl2br(e($excerpt($post['body'], 280))) ?></p><?php endif; ?>
<?php if ($kind === 'sent'): ?>
        <small>Published <?= e(Workspace::local((string) $post['sent_at'], $tz)->format('j M Y, g:i A')) ?><?= !empty($post['recycled_from']) ? ' · recycled' : '' ?><?= !empty($post['recycled_at']) ? ' · last recycled ' . e(Workspace::local((string) $post['recycled_at'], $tz)->format('j M')) : '' ?></small>
<?php if (!empty($post['first_comment'])): ?>
        <small>First comment: <?= e(mb_strlen((string) $post['first_comment']) > 80 ? rtrim(mb_substr((string) $post['first_comment'], 0, 80)) . '…' : $post['first_comment']) ?></small>
<?php endif; ?>
<?php if (!empty($post['first_comment_error'])): ?>
        <p class="composer-error" role="status"><?= e($post['first_comment_error']) ?></p>
<?php endif; ?>
<?php else: ?>
<?php if (!empty($post['review_note'])): ?>
        <p class="ap-note"><?= icon('chat-circle-text') ?><span><b><?= empty($post['reviewed_at']) ? 'Note' : 'Changes requested' . (!empty($post['reviewed_by']) ? ' by ' . e($post['reviewed_by']) : '') ?>:</b> <?= e($post['review_note']) ?></span></p>
<?php endif; ?>
        <small>Edited <?= e(Workspace::local($post['updated_at'], $tz)->format('j M, g:i A')) ?><?= $post['media_id'] ? (($n = count(Media::extraIds($post['extra_media'] ?? null))) > 0 ? ' · carousel of ' . ($n + 1) . ' photos' : ' · file attached') : '' ?></small>
<?php endif; ?>
      </div>
      <form action="/app/posts" method="post" class="dr-evergreen">
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
        <button type="submit" name="action" value="duplicate" class="ws-btn ws-btn--sm" title="Copy this post to a new draft"><?= icon('copy') ?>Duplicate</button>
      </form>
<?php if ($kind === 'sent'): ?>
      <form action="/app/evergreen" method="post" class="dr-evergreen">
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
        <input type="hidden" name="back" value="<?= e(strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '#')) ?>">
        <button type="submit" name="on" value="<?= empty($post['evergreen']) ? '1' : '' ?>" class="ws-btn ws-btn--sm<?= !empty($post['evergreen']) ? ' is-on' : '' ?>" aria-pressed="<?= !empty($post['evergreen']) ? 'true' : 'false' ?>" title="<?= !empty($post['evergreen']) ? 'Evergreen: re-posted into empty slots. Click to stop.' : 'Re-post this into empty slots later' ?>"><?= icon('arrows-clockwise') ?><?= !empty($post['evergreen']) ? 'Evergreen' : 'Recycle' ?></button>
      </form>
<?php if ($post['external_url']): ?>
      <a class="ws-btn ws-btn--sm" href="<?= e($post['external_url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('arrow-square-out') ?>View on <?= e(array_column(integrations(), 'name', 'slug')[$post['channel']] ?? 'the network') ?></a>
<?php endif; ?>
<?php else: ?>
      <a class="ws-btn ws-btn--sm" href="/app/publish/new?id=<?= (int) $post['id'] ?>" data-compose="<?= $json(Workspace::composeData($post, $tz, $uid)) ?>"><?= icon('pencil-simple') ?>Edit</a>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
<?php elseif ($kind === 'sent'): ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('paper-plane-tilt') ?></span>
    <h2>Nothing sent yet</h2>
    <p>Posts atwebtech publishes for you appear here with a link to the live post. Plan-only posts, for networks that aren’t connected, are never sent.</p>
  </div>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('note-pencil') ?></span>
    <h2>No drafts</h2>
    <p>Posts you save as drafts wait here until you're ready to schedule them.</p>
    <a class="ws-btn ws-btn--green" href="/app/publish/new" data-compose="<?= e($compose) ?>"><?= icon('plus') ?>New Post</a>
  </div>
<?php endif; ?>
