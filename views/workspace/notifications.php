<?php
/** @var array $user */
/** @var array $items */
/** @var array $person */
$token = Security::csrfToken();
$tz = Workspace::timezone($person);
$icons = ['post_failed' => 'warning-circle', 'channel_reconnect' => 'link-simple', 'approval_needed' => 'check-circle', 'approval_decided' => 'chat-circle-text'];
$unread = count(array_filter($items, static fn (array $n): bool => $n['read_at'] === null));
ob_start(); ?>
<?php if ($unread > 0): ?>
    <form action="/app/notifications" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <button type="submit" class="ws-btn ws-btn--sm"><?= icon('check') ?>Mark all as read</button>
    </form>
<?php endif; ?>
    <a class="ws-btn ws-btn--sm" href="/app/account#notifications"><?= icon('gear') ?>Email preferences</a>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'bell', 'title' => 'Notifications', 'actions' => $actions]); ?>
<?php if (!$items): ?>
  <div class="in-empty">
    <?= icon('bell') ?>
    <p>Nothing yet. You’ll hear here, and by email, when a post fails, a channel needs reconnecting, or a post is waiting for approval.</p>
  </div>
<?php else: ?>
  <ul class="nt-list" role="list">
<?php foreach ($items as $n):
    $where = (int) $n['workspace_id'] === (int) $person['id'] || $n['ws_name'] === null ? '' : ($n['ws_parent'] !== null ? (string) $n['ws_name'] : $n['ws_name'] . '’s workspace'); ?>
    <li>
      <a class="nt-item<?= $n['read_at'] === null ? ' is-unread' : '' ?>" href="/app/notifications/open/<?= (int) $n['id'] ?>">
        <?= icon($icons[$n['kind']] ?? 'bell') ?>
        <div>
          <b><?= e($n['title']) ?></b>
<?php if ($n['body']): ?>
          <p><?= e($n['body']) ?></p>
<?php endif; ?>
          <small><?= e(Workspace::local((string) $n['created_at'], $tz)->format('j M, g:i A')) ?><?= $where !== '' ? ' · ' . e($where) : '' ?><?= $n['read_at'] === null ? ' · new' : '' ?></small>
        </div>
      </a>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
</div>
