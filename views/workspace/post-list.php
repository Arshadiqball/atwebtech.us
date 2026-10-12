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
        <small>Published <?= e(Workspace::local((string) $post['sent_at'], $tz)->format('j M Y, g:i A')) ?></small>
<?php if (!empty($post['first_comment'])): ?>
        <small>First comment: <?= e(mb_strlen((string) $post['first_comment']) > 80 ? rtrim(mb_substr((string) $post['first_comment'], 0, 80)) . '…' : $post['first_comment']) ?></small>
<?php endif; ?>
<?php if (!empty($post['first_comment_error'])): ?>
        <p class="composer-error" role="status"><?= e($post['first_comment_error']) ?></p>
<?php endif; ?>
<?php else: ?>
        <small>Edited <?= e(Workspace::local($post['updated_at'], $tz)->format('j M, g:i A')) ?><?= $post['media_id'] ? ' · file attached' : '' ?></small>
<?php endif; ?>
      </div>
<?php if ($kind === 'sent'): ?>
<?php if ($post['external_url']): ?>
      <a class="ws-btn ws-btn--sm" href="<?= e($post['external_url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('arrow-square-out') ?>View on <?= e($post['channel'] === 'linkedin' ? 'LinkedIn' : 'YouTube') ?></a>
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
    <p>Posts atwebtech publishes to a connected YouTube channel or LinkedIn Profile appear here with a link. Plan-only posts for other networks are never sent.</p>
  </div>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('note-pencil') ?></span>
    <h2>No drafts</h2>
    <p>Posts you save as drafts wait here until you're ready to schedule them.</p>
    <a class="ws-btn ws-btn--green" href="/app/publish/new" data-compose="<?= e($compose) ?>"><?= icon('plus') ?>New Post</a>
  </div>
<?php endif; ?>
