<?php
/** @var array $user */
/** @var array $week    From Workspace::week() */
/** @var Closure $url   fn(array $params): string, keeps the page's other query params */
/** @var string $target Composer target for new posts, e.g. 'c:3' or 'n:linkedin' */
/** @var string $filter Provider slug to show, or '' for all */
$uid = (int) $user['id'];
$tz = Workspace::timezone($user);
$accounts = array_column(Channels::forUser($uid), null, 'id');
$excerpt = static fn (string $text, int $len): string => mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len)) . '…' : $text;
$compose = static fn (array $data): string => e(json_encode($data, JSON_UNESCAPED_UNICODE));
$offset = max(-52, min(52, (int) ($_GET['week'] ?? 0)));
$range = $week['start']->format('j') . ' – ' . $week['end']->format('j F Y');
if ($week['start']->format('m') !== $week['end']->format('m')) {
    $range = $week['start']->format('j M') . ' – ' . $week['end']->format('j M Y');
}
?>
  <div class="cal">
    <div class="cal-nav">
      <a class="ws-icon-btn" href="<?= e($url(['week' => $offset - 1 ?: ''])) ?>" title="Previous week"><?= icon('caret-left') ?><span class="sr-only">Previous week</span></a>
      <a class="ws-icon-btn" href="<?= e($url(['week' => $offset + 1 ?: ''])) ?>" title="Next week"><?= icon('caret-right') ?><span class="sr-only">Next week</span></a>
      <h2><?= e($range) ?></h2>
<?php if ($offset !== 0): ?><a class="ws-btn ws-btn--sm" href="<?= e($url(['week' => ''])) ?>">Today</a><?php endif; ?>
    </div>
    <ol class="cal-grid" role="list">
<?php foreach ($week['days'] as $day):
    $date = $day['date'];
    $next = $day['today'] ? $week['now']->modify('+1 hour')->setTime((int) $week['now']->modify('+1 hour')->format('G'), 0) : $date->setTime(9, 0);
    $data = ['target' => $target, 'date' => $next->format('Y-m-d'), 'time' => $next->format('H:i')];
    $posts = array_filter($day['posts'], static fn (array $p): bool => $filter === '' || $p['channel'] === $filter);
?>
      <li class="cal-day<?= $day['today'] ? ' is-today' : '' ?><?= $day['past'] ? ' is-past' : '' ?>"<?= $day['past'] ? '' : ' data-drop-day="' . e($date->format('Y-m-d')) . '"' ?>>
        <h3 class="cal-date"><span><?= e($date->format('D')) ?></span><b><?= e($date->format('j')) ?></b><?php if ($day['today']): ?><span class="sr-only"> (today)</span><?php endif; ?></h3>
        <ul class="cal-posts" role="list">
<?php foreach ($posts as $post): ?>
          <li><a class="cal-post" href="/app/publish/new?id=<?= (int) $post['id'] ?>" data-compose="<?= $compose(Workspace::composeData($post, $tz, $uid)) ?>"<?php if ($post['status'] === 'planned'): ?> draggable="true" data-move="<?= (int) $post['id'] ?>" data-at="<?= e($post['at']->format('Y-m-d H:i')) ?>" title="Drag to another day"<?php endif; ?>><?= post_mark($post, $accounts) ?><span><b><?= e($post['at']->format('g:i A')) ?></b><?= e($excerpt($post['title'] ?: $post['body'], 60)) ?></span></a></li>
<?php endforeach; ?>
        </ul>
<?php if (!$day['past'] && $next->format('Y-m-d') === $date->format('Y-m-d')): ?>
        <a class="cal-add" href="<?= e('/app/publish/new?' . http_build_query($data)) ?>" data-compose="<?= $compose(['targets' => $target !== '' ? [$target] : [], 'date' => $data['date'], 'time' => $data['time']]) ?>"><?= icon('plus') ?><span class="sr-only">New post on <?= e($date->format('l j F')) ?></span></a>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ol>
  </div>
