<?php
/** @var array $user */
/** @var array $feeds */
/** @var array $items */
/** @var int $limit */
$token = Security::csrfToken();
$form = View::formState('feeds');
$paid = Channels::allowance((int) $user['id'])['paid'];
$tz = Workspace::timezone($user);
$current = (int) ($_GET['feed'] ?? 0);
$json = static fn (array $d): string => e(json_encode($d, JSON_UNESCAPED_UNICODE));
?>
<section class="tk" aria-labelledby="fd-title">
  <header class="tk-head">
    <div>
      <h2 id="fd-title">Feeds</h2>
      <p>Follow blogs and news sites. New articles appear here every hour, ready to share as a post or save as an idea.</p>
    </div>
    <span class="tk-count"><?= count($feeds) ?> / <?= $paid ? $limit : $limit ?> feeds</span>
  </header>

<?php if ($form && $form['status'] === 'error'): ?>
  <p class="bill-warn"><?= icon('warning-circle') ?><?= e($form['message']) ?><?php if (!empty($form['errors']['url']) && $form['errors']['url'] !== 'Enter a full web address.'): ?> <small>(<?= e($form['errors']['url']) ?>)</small><?php endif; ?></p>
<?php endif; ?>

<?php if (count($feeds) < $limit): ?>
  <form class="tk-form" action="/app/feeds" method="post">
    <input type="hidden" name="_token" value="<?= e($token) ?>">
    <input type="hidden" name="action" value="add">
    <label class="bio-field tk-grow"><span>Website or feed address</span><input class="idea-input" name="url" maxlength="500" required placeholder="https://blog.example.com" value="<?= e((string) ($form['old']['url'] ?? '')) ?>"></label>
    <button type="submit" class="ws-btn ws-btn--dark"><?= icon('plus') ?>Follow</button>
  </form>
<?php else: ?>
  <p class="bill-note"><?= icon('info') ?>You follow <?= $limit ?> feeds, the most on your plan.<?php if (!$paid): ?> <a href="/app/billing">Upgrade</a> to follow up to <?= Feeds::MAX_FEEDS ?>.<?php endif; ?></p>
<?php endif; ?>

<?php if ($feeds): ?>
  <div class="tk-feeds">
    <a class="ws-chip<?= $current === 0 ? ' is-on' : '' ?>" href="/app/create?tab=feeds">All feeds</a>
<?php foreach ($feeds as $f): ?>
    <span class="tk-feed<?= $current === (int) $f['id'] ? ' is-on' : '' ?>">
      <a href="/app/create?tab=feeds&amp;feed=<?= (int) $f['id'] ?>" title="<?= e($f['url']) ?>"><?= e($f['title']) ?></a>
<?php if ($f['last_error']): ?><span class="tk-err" title="<?= e($f['last_error']) ?>"><?= icon('warning-circle') ?></span><?php endif; ?>
      <form action="/app/feeds" method="post"><input type="hidden" name="_token" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button type="submit" name="action" value="remove" class="tk-x" data-confirm="Stop following <?= e($f['title']) ?>?"><?= icon('x') ?><span class="sr-only">Remove <?= e($f['title']) ?></span></button></form>
    </span>
<?php endforeach; ?>
    <form action="/app/feeds" method="post" class="tk-refresh"><input type="hidden" name="_token" value="<?= e($token) ?>"><button type="submit" name="action" value="refresh" class="ws-btn ws-btn--sm"><?= icon('arrows-clockwise') ?>Refresh now</button></form>
  </div>

<?php if ($items): ?>
  <ul class="tk-articles" role="list">
<?php foreach ($items as $it): $body = $it['title'] . "\n\n" . $it['link']; ?>
    <li class="tk-article<?= $it['status'] === 'used' ? ' is-used' : '' ?>">
      <div>
        <small><?= e($it['feed_title']) ?><?= $it['published_at'] ? ' · ' . e(Workspace::local((string) $it['published_at'], $tz)->format('M j, g:i A')) : '' ?><?= $it['status'] === 'used' ? ' · Shared' : '' ?></small>
        <a href="<?= e($it['link']) ?>" target="_blank" rel="noopener noreferrer"><b><?= e($it['title']) ?></b></a>
<?php if ($it['summary']): ?><p><?= e(mb_strimwidth((string) $it['summary'], 0, 200, '…')) ?></p><?php endif; ?>
      </div>
      <div class="tk-acts">
        <a class="ws-btn ws-btn--sm ws-btn--dark" href="/app/publish/new?<?= e(http_build_query(['text' => $body])) ?>" data-compose="<?= $json(['body' => $body]) ?>"><?= icon('paper-plane-tilt') ?>Create post</a>
        <form action="/app/feeds" method="post"><input type="hidden" name="_token" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button type="submit" name="action" value="idea" class="ws-btn ws-btn--sm"><?= icon('lightbulb') ?>Save idea</button></form>
        <form action="/app/feeds" method="post"><input type="hidden" name="_token" value="<?= e($token) ?>"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button type="submit" name="action" value="dismiss" class="ws-icon-btn" title="Hide"><?= icon('eye-slash') ?><span class="sr-only">Hide</span></button></form>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <p class="wsp-muted">No new articles yet. Feeds refresh every hour, or use Refresh now.</p>
<?php endif; ?>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('book-open') ?></span>
    <h2>Never run out of things to share</h2>
    <p>Paste a blog or news site above, for example your industry's top blog or your own website. atwebtech finds its RSS feed automatically.</p>
  </div>
<?php endif; ?>
</section>
