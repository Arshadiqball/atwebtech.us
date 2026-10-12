<?php
$demo = data('demo');
$convos = $demo['conversations'];
$first = $convos[0];
$unread = count(array_filter($convos, static fn ($c) => $c['unread']));
$channelsInInbox = array_values(array_unique(array_column($convos, 'channel')));
$payload = [];
foreach ($convos as $c) {
    $payload[$c['id']] = [
        'name' => $c['name'],
        'channel' => $demo['channels'][$c['channel']]['name'],
        'post' => $c['post'],
        'initials' => $c['initials'],
        'messages' => $c['messages'],
    ];
}
?>
<div class="demo demo-inbox app-window" data-inbox>
  <div class="demo-bar">
    <div class="demo-bar-start">
      <?php partial('partials/logo-mark', ['class' => 'app-logo']); ?>
      <strong>Inbox</strong>
      <span class="badge" data-unread-count><?= $unread ?> unread</span>
    </div>
    <?php partial('partials/demo-label'); ?>
  </div>
  <div class="inbox-tools">
    <label class="sr-only" for="inbox-filter">Filter by channel</label>
    <select class="input input--sm" id="inbox-filter" data-inbox-filter>
      <option value="all">All channels</option>
<?php foreach ($channelsInInbox as $slug): ?>
      <option value="<?= e($slug) ?>"><?= e($demo['channels'][$slug]['name']) ?></option>
<?php endforeach; ?>
    </select>
    <label class="check"><input type="checkbox" data-inbox-unread> Unread only</label>
  </div>
  <div class="inbox-layout">
    <ul class="convo-list" role="list" aria-label="Conversations" data-convo-list>
<?php foreach ($convos as $c): ?>
      <li data-channel="<?= e($c['channel']) ?>">
        <button type="button" class="convo<?= $c['unread'] ? ' is-unread' : '' ?>" data-convo="<?= e($c['id']) ?>" aria-pressed="<?= $c === $first ? 'true' : 'false' ?>">
          <span class="avatar avatar--<?= e($c['channel']) ?>"><?= e($c['initials']) ?><?= channel_icon($c['channel'], 'ch--xs avatar-ch') ?></span>
          <span class="convo-body">
            <span class="convo-top"><b><?= e($c['name']) ?></b><small><?= e($c['time']) ?></small></span>
            <span class="convo-snippet"><?= e(end($c['messages'])['text']) ?></span>
            <span class="convo-meta"><span class="tag tag--plain"><?= e($c['tag']) ?></span><?php if ($c['unread']): ?><span class="unread-dot"><span class="sr-only">Unread</span></span><?php endif; ?></span>
          </span>
        </button>
      </li>
<?php endforeach; ?>
    </ul>
    <div class="thread" data-thread>
      <div class="thread-head">
        <span class="avatar" data-th-initials><?= e($first['initials']) ?></span>
        <span><b data-th-name><?= e($first['name']) ?></b><small>on <span data-th-channel><?= e($demo['channels'][$first['channel']]['name']) ?></span>: <span data-th-post><?= e($first['post']) ?></span></small></span>
      </div>
      <div class="thread-msgs" data-msgs aria-live="polite">
<?php foreach ($first['messages'] as $m): ?>
        <p class="msg msg--<?= e($m['from']) ?>"><?= e($m['text']) ?></p>
<?php endforeach; ?>
      </div>
      <form class="composer" data-composer>
        <label class="sr-only" for="inbox-reply">Write a reply</label>
        <textarea class="input composer-input" id="inbox-reply" rows="2" maxlength="500" placeholder="Write a reply..."></textarea>
        <div class="composer-foot">
          <span class="composer-note"><?= icon('lock-simple') ?>Demo only. Replies are never sent.</span>
          <button type="submit" class="btn btn--dark btn--xs">Add demo reply</button>
        </div>
      </form>
    </div>
  </div>
  <script type="application/json" data-inbox-data><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</div>
