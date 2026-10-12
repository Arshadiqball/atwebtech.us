<?php
/** @var array $user */
/** @var array $queue   From Workspace::queue() */
/** @var string $filter Provider slug to show, or '' for all */
/** @var ?string $more  Link for more suggested times */
/** @var ?array $endless ['href' => no-JS link, 'src' => fragment URL] to keep loading later weeks */
/** @var bool $fragment Render only the day sections (endless loading) */
$fragment ??= false;
$endless ??= null;
$more ??= null;
$all = array_column(integrations(), null, 'slug');
$uid = (int) $user['id'];
$tz = Workspace::timezone($user);
$accounts = array_column(Channels::forUser($uid), null, 'id');
$token = Security::csrfToken();
$excerpt = static fn (string $text, int $len): string => mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len)) . '…' : $text;
$compose = static fn (array $data): string => e(json_encode($data, JSON_UNESCAPED_UNICODE));
$composeHref = static fn (array $data): string => e('/app/publish/new?' . http_build_query($data));
$shown = static fn (string $slug): bool => $filter === '' || $slug === $filter;
$postJson = static fn (array $post): array => Workspace::composeData($post, $tz, $uid);
$text = static fn (array $post): string => $post['title'] ? $post['title'] . ($post['body'] !== '' ? ' — ' . $post['body'] : '') : ($post['body'] !== '' ? $post['body'] : 'Video post');
$hasItems = false;
?>
<?php if (!$fragment): ?>
  <div class="q" data-queue>
<?php $overdue = array_filter($queue['overdue'], static fn (array $p): bool => $shown($p['channel'])); if ($overdue): $hasItems = true; ?>
    <section class="q-day q-day--overdue" aria-labelledby="q-overdue">
      <h2 id="q-overdue">Needs attention</h2>
      <ul class="q-list" role="list">
<?php foreach ($overdue as $post): ?>
        <li class="q-row">
          <span class="q-time"><?= e($post['at']->format('j M, g:i A')) ?></span>
          <div class="q-post q-post--flag"<?php if ($post['status'] !== 'publishing'): ?> draggable="true" data-move="<?= (int) $post['id'] ?>" data-at="<?= e($post['at']->format('Y-m-d H:i')) ?>" title="Drag to another time"<?php endif; ?>>
            <?= post_mark($post, $accounts) ?>
            <span class="q-post-text"><?= e($excerpt($text($post), 140)) ?>
<?php if ($post['status'] === 'failed'): ?>
              <small class="q-error"><?= icon('warning-circle') ?><?= e($post['error'] ?: 'Publishing failed.') ?></small>
<?php elseif ($post['status'] === 'planned' && !empty($accounts[(int) $post['channel_id']]['can_publish'])): ?>
              <small>Waiting for the publisher. It runs every minute.</small>
<?php else: ?>
              <small>This was a plan only, so nothing was sent. Pick a new time or delete it.</small>
<?php endif; ?>
            </span>
            <?= post_badge($post, $accounts) ?>
            <span class="q-acts">
<?php if ($post['status'] === 'failed'): ?>
              <form action="/app/posts" method="post">
                <input type="hidden" name="_token" value="<?= e($token) ?>">
                <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                <input type="hidden" name="return" value="<?= e(current_path()) ?>">
                <button type="submit" class="ws-btn ws-btn--sm" name="action" value="retry"><?= icon('arrows-clockwise') ?>Retry</button>
              </form>
<?php endif; ?>
<?php if ($post['status'] !== 'publishing'): ?>
              <a class="ws-btn ws-btn--sm" href="/app/publish/new?id=<?= (int) $post['id'] ?>" data-compose="<?= $compose($postJson($post)) ?>"><?= icon('pencil-simple') ?>Edit</a>
<?php endif; ?>
            </span>
          </div>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endif; ?>
<?php endif; ?>
<?php foreach ($queue['days'] as $day): $items = array_filter($day['items'], static fn (array $item): bool => $shown($item['type'] === 'post' ? $item['post']['channel'] : $item['channel'])); if (!$items) continue; $hasItems = true; ?>
    <section class="q-day" aria-labelledby="q-<?= e($day['date']) ?>"<?= $day['date'] >= $queue['now']->format('Y-m-d') ? ' data-drop-day="' . e($day['date']) . '"' : '' ?>>
      <h2 id="q-<?= e($day['date']) ?>"><?= e($day['label']) ?><?php if ($day['date'] > $queue['now']->modify('+300 day')->format('Y-m-d')): ?> <?= e(substr($day['date'], 0, 4)) ?><?php endif; ?></h2>
      <ul class="q-list" role="list">
<?php foreach ($items as $item): $time = $item['at']->format('g:i A'); ?>
        <li class="q-row"<?= $item['type'] === 'slot' ? ' data-drop="' . e($item['at']->format('Y-m-d H:i')) . '"' : '' ?>>
          <span class="q-time"><?= e($time) ?></span>
<?php if ($item['type'] === 'slot'):
    $data = ['target' => $item['account'] ? 'c:' . (int) $item['account']['id'] : 'n:' . $item['channel'], 'date' => $item['at']->format('Y-m-d'), 'time' => $item['at']->format('H:i')];
    $who = $item['account'] ? $item['account']['name'] : $all[$item['channel']]['name'];
?>
          <a class="q-slot" href="<?= $composeHref($data) ?>" data-compose="<?= $compose(['targets' => [$data['target']], 'date' => $data['date'], 'time' => $data['time']]) ?>" aria-label="New post for <?= e($who) ?> at <?= e($time) ?>, <?= e($day['label']) ?>">
            <?= $item['account'] ? channel_avatar($item['account'], 'sm') : post_mark(['channel' => $item['channel'], 'channel_id' => null], []) ?><span class="q-slot-new"><?= icon('plus') ?>New</span>
          </a>
<?php else: $post = $item['post']; $busy = $post['status'] === 'publishing'; ?>
          <<?= $busy ? 'div' : 'a' ?> class="q-post"<?= $busy ? '' : ' href="/app/publish/new?id=' . (int) $post['id'] . '" data-compose="' . $compose($postJson($post)) . '" draggable="true" data-move="' . (int) $post['id'] . '" data-at="' . e($item['at']->format('Y-m-d H:i')) . '" title="Drag to another time"' ?>>
            <?= post_mark($post, $accounts) ?><span class="q-post-text"><?= e($excerpt($text($post), 140)) ?></span><?= post_badge($post, $accounts) ?><?php if (!$busy): ?><span class="q-edit"><?= icon('pencil-simple') ?>Edit</span><?php endif; ?>
          </<?= $busy ? 'div' : 'a' ?>>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endforeach; ?>
<?php if ($endless): ?>
    <div class="q-sentinel" data-queue-more="<?= e($endless['src']) ?>">
      <a class="q-more" href="<?= e($endless['href']) ?>"><?= icon('plus') ?>Show later weeks</a>
    </div>
<?php endif; ?>
<?php if (!$fragment): ?>
<?php if (!$hasItems && !$endless): ?>
    <p class="wsp-muted">Nothing planned and no suggested times in this period.</p>
<?php endif; ?>
<?php if ($more): ?>
    <a class="q-more" href="<?= e($more) ?>"><?= icon('plus') ?>More Recommended Times</a>
<?php endif; ?>
  </div>
<?php endif; ?>
