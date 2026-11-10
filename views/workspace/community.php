<?php
/** @var array $user */
/** @var string $state */
/** @var string $view */
/** @var ?int $channelId */
/** @var ?string $item */
/** @var int $page */
/** @var array $inbox */
/** @var array $problems */
/** @var ?array $draft */
/** @var array $saved */
$token = Security::csrfToken();
$tz = Workspace::timezone($user);
$channels = array_column($inbox['channels'], null, 'id');
$counts = $inbox['counts'];
$names = Community::NETWORKS;
$url = static function (array $params) use ($state, $view, $channelId, $item): string {
    $q = $params + ['state' => $state, 'view' => $view, 'channel' => $channelId, 'item' => $item];
    $q = array_filter($q, static fn ($v, $k) => $v !== null && $v !== '' && !($k === 'state' && $v === 'open') && !($k === 'view' && $v === 'post') && !($k === 'page' && (int) $v === 1), ARRAY_FILTER_USE_BOTH);
    return '/app/community' . ($q ? '?' . http_build_query($q) : '');
};
$keep = static function () use ($state, $view, $channelId, $item, $page): string {
    $out = '';
    foreach (['state' => $state, 'view' => $view, 'channel' => $channelId, 'item' => $item, 'page' => $page] as $k => $v) {
        if ($v !== null && $v !== '') {
            $out .= '<input type="hidden" name="' . $k . '" value="' . e((string) $v) . '">';
        }
    }
    return $out;
};
$short = static function (?string $text, int $max): string {
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max)) . '…' : $text;
};
$when = static function (string $utc) use ($tz): string {
    $at = Workspace::local($utc, $tz);
    $ago = time() - $at->getTimestamp();
    return match (true) {
        $ago < 3600 => max(1, intdiv($ago, 60)) . 'm ago',
        $ago < 86400 => intdiv($ago, 3600) . 'h ago',
        $ago < 7 * 86400 => intdiv($ago, 86400) . 'd ago',
        default => $at->format('j M Y'),
    };
};
$lastChecked = null;
foreach ($inbox['channels'] as $c) {
    if (!empty($c['inbox_checked_at']) && ($lastChecked === null || $c['inbox_checked_at'] > $lastChecked)) {
        $lastChecked = $c['inbox_checked_at'];
    }
}
$chosen = $channelId !== null ? ($channels[$channelId] ?? null) : null;

ob_start(); ?>
    <nav class="ws-seg" aria-label="Layout">
      <a href="<?= e($url(['view' => 'post', 'page' => null])) ?>"<?= $view === 'post' ? ' aria-current="page"' : '' ?>>By post</a>
      <a href="<?= e($url(['view' => 'list', 'item' => null, 'page' => null])) ?>"<?= $view === 'list' ? ' aria-current="page"' : '' ?>>List</a>
    </nav>
<?php if ($inbox['channels']): ?>
    <form action="/app/community" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="refresh">
      <?= $keep() ?>
      <button type="submit" class="ws-btn ws-btn--sm" title="<?= e($lastChecked ? 'Last read ' . $when($lastChecked) : 'Not read yet') ?>"><?= icon('arrows-clockwise') ?>Refresh</button>
    </form>
<?php endif; ?>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'chats-circle', 'title' => 'Community', 'actions' => $actions]); ?>

  <div class="wsp-bar">
    <nav class="wsp-tabs" aria-label="Comment status">
<?php foreach (['open' => 'Unanswered', 'replied' => 'Answered', 'done' => 'Done', 'all' => 'All'] as $key => $label): ?>
      <a href="<?= e($url(['state' => $key, 'page' => null, 'item' => null])) ?>"<?= $state === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="cm-n"><?= (int) $counts[$key] ?></span></a>
<?php endforeach; ?>
    </nav>
    <div class="wsp-filters">
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn<?= $chosen ? ' is-active' : '' ?>" aria-expanded="false" aria-controls="cm-channels" data-menu-toggle><?= e($chosen ? $chosen['name'] : 'All channels') ?><?= icon('caret-down') ?></button>
        <div class="ws-menu ws-menu--end" id="cm-channels" hidden data-menu-panel>
<?php if (!$inbox['channels']): ?>
          <p class="ws-menu-note">No YouTube, Facebook, Instagram or Threads channel connected yet.</p>
          <a class="ws-menu-item" href="/app/channels"><?= icon('plus') ?>Connect a channel</a>
<?php else: ?>
          <a class="ws-menu-item" href="<?= e($url(['channel' => null, 'item' => null, 'page' => null])) ?>"<?= $chosen === null ? ' aria-current="true"' : '' ?>>All channels</a>
<?php foreach ($inbox['channels'] as $c): ?>
          <a class="ws-menu-item" href="<?= e($url(['channel' => (int) $c['id'], 'item' => null, 'page' => null])) ?>"<?= $chosen && (int) $chosen['id'] === (int) $c['id'] ? ' aria-current="true"' : '' ?>><?= channel_avatar($c, 'sm') ?><?= e($c['name']) ?></a>
<?php endforeach; ?>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>

<?php $issues = array_intersect_key($problems, $channels); if ($issues): ?>
  <ul class="in-issues cm-issues" role="list">
<?php foreach ($issues as $cid => $message): $c = $channels[$cid]; ?>
    <li>
      <?= channel_avatar($c, 'sm') ?>
      <span><b><?= e($c['name']) ?></b> <?= e($message) ?></span>
<?php if (stripos($message, 'reconnect') !== false): ?>
      <form action="/app/channels/connect/<?= e($c['provider']) ?>" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <button type="submit" class="ws-btn ws-btn--sm">Reconnect</button>
      </form>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($inbox['channels']): $paidSaved = Channels::allowance((int) $user['id'])['paid']; ?>
  <details class="cm-saved"<?= isset($_GET['saved']) ? ' open' : '' ?>>
    <summary><?= icon('chat-circle-text') ?>Saved replies <span class="cm-n"><?= count($saved) ?></span></summary>
    <div class="cm-saved-body">
<?php foreach ($saved as $sr): ?>
      <form class="cm-saved-row" action="/app/community" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="reply_id" value="<?= (int) $sr['id'] ?>">
        <?= $keep() ?>
        <div><b><?= e($sr['title']) ?></b><p><?= e($short($sr['body'], 160)) ?></p></div>
        <button type="submit" name="action" value="delete_reply" class="ws-btn ws-btn--sm ws-btn--ghost">Delete</button>
      </form>
<?php endforeach; ?>
<?php if ($paidSaved || count($saved) < Community::SAVED_FREE): ?>
      <form class="cm-saved-new" action="/app/community" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save_reply">
        <?= $keep() ?>
        <label class="sr-only" for="cm-saved-title">Name</label>
        <input class="idea-input" id="cm-saved-title" name="title" maxlength="80" required placeholder="Name, like “Opening hours”">
        <label class="sr-only" for="cm-saved-body">Reply text</label>
        <textarea class="idea-input" id="cm-saved-body" name="body" rows="2" maxlength="<?= Community::REPLY_MAX ?>" required placeholder="The answer you send often"></textarea>
        <button type="submit" class="ws-btn ws-btn--dark ws-btn--sm">Save reply</button>
      </form>
<?php else: ?>
      <p class="wsp-muted">The Free plan includes <?= Community::SAVED_FREE ?> saved reply. <a href="/app/billing">Upgrade</a> for unlimited.</p>
<?php endif; ?>
    </div>
  </details>
<?php endif; ?>

<?php if (!$inbox['channels']): ?>
  <section class="cm-listen" aria-labelledby="cm-title">
    <span class="cm-wave" aria-hidden="true"><i></i><i></i><i></i></span>
    <h2 id="cm-title">Answer every comment from one inbox</h2>
    <p>Connect YouTube, a Facebook Page, Instagram or Threads. atwebtech reads new comments every 30 minutes, so you can reply, get an AI-suggested answer, or mark them done.</p>
    <ul class="cm-cards" role="list">
      <li>
        <a class="cm-card" href="/app/channels">
          <?= icon('plus') ?>
          <span><b>Connect a channel</b>YouTube comments work right away. Facebook, Instagram and Threads comments need Meta’s comment permission.</span>
        </a>
      </li>
    </ul>
  </section>
<?php elseif ($counts['all'] === 0): ?>
  <section class="cm-listen" aria-labelledby="cm-title">
    <span class="cm-wave" aria-hidden="true"><i></i><i></i><i></i></span>
    <h2 id="cm-title"><?= $lastChecked ? 'No comments yet' : 'Reading your comments…' ?></h2>
    <p><?= $lastChecked
        ? 'atwebtech last read your channels ' . e($when($lastChecked)) . '. New comments on your recent posts and videos appear here.'
        : 'atwebtech reads your channels every 30 minutes. Click Refresh to read them now.' ?></p>
    <p class="cm-meantime"><a href="/app/publish/new">Post something that starts a conversation</a></p>
  </section>
<?php else: ?>
  <div class="cm<?= $view === 'list' ? ' cm--list' : '' ?>">
<?php if ($view === 'post'): ?>
    <aside class="cm-rail" aria-label="Posts with comments">
      <a class="cm-item<?= $item === null ? ' is-on' : '' ?>" href="<?= e($url(['item' => null, 'page' => null])) ?>"><b>All posts</b></a>
<?php foreach ($inbox['items'] as $it): $c = $channels[(int) $it['channel_id']] ?? null; ?>
      <a class="cm-item<?= $item === $it['item_id'] ? ' is-on' : '' ?>" href="<?= e($url(['item' => $it['item_id'], 'page' => null])) ?>">
        <?= $c ? channel_avatar($c, 'sm') : '' ?>
        <span><?= e($short($it['item_title'], 70) ?: 'Untitled post') ?><small><?= (int) $it['n'] ?> <?= (int) $it['n'] === 1 ? 'comment' : 'comments' ?> · <?= e($when((string) $it['latest'])) ?></small></span>
      </a>
<?php endforeach; ?>
<?php if (!$inbox['items']): ?>
      <p>No posts in this view.</p>
<?php endif; ?>
    </aside>
<?php endif; ?>
    <section class="cm-feed" aria-label="Comments">
<?php if (!$inbox['comments']): ?>
      <div class="in-empty">
        <?= icon('check-circle') ?>
        <p><?= $state === 'open' ? 'You’re all caught up. Nothing is waiting for an answer.' : 'No comments in this view.' ?></p>
      </div>
<?php endif; ?>
<?php foreach ($inbox['comments'] as $cm):
    $c = $channels[(int) $cm['channel_id']] ?? null;
    $network = $names[$cm['provider']] ?? $cm['provider'];
    $canReply = $c !== null && Community::canReply($c);
    $text = $draft && (int) $draft['id'] === (int) $cm['id'] ? (string) $draft['text'] : ''; ?>
      <article class="cm-comment cm-comment--<?= e($cm['state']) ?>" id="cm-<?= (int) $cm['id'] ?>">
        <header>
<?php if ($cm['author_avatar']): ?>
          <img class="cm-face" src="<?= e($cm['author_avatar']) ?>" alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer">
<?php else: ?>
          <span class="cm-face" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(ltrim((string) $cm['author_name'], '@'), 0, 1))) ?></span>
<?php endif; ?>
          <div>
            <p class="cm-who">
<?php if ($cm['author_url']): ?>
              <a href="<?= e($cm['author_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($cm['author_name']) ?></a>
<?php else: ?>
              <b><?= e($cm['author_name']) ?></b>
<?php endif; ?>
<?php if ((int) $cm['is_own'] === 1): ?><span class="ws-pill">You</span><?php endif; ?>
            </p>
            <small><?= e($network) ?><?= $c ? ' · ' . e($c['name']) : '' ?> · <time datetime="<?= e(gmdate('c', strtotime($cm['published_at'] . ' UTC'))) ?>"><?= e($when((string) $cm['published_at'])) ?></time><?php
              if ($cm['likes'] !== null && (int) $cm['likes'] > 0): ?> · <?= (int) $cm['likes'] ?> <?= (int) $cm['likes'] === 1 ? 'like' : 'likes' ?><?php endif;
              if ($cm['replies'] !== null && (int) $cm['replies'] > 0): ?> · <?= (int) $cm['replies'] ?> <?= (int) $cm['replies'] === 1 ? 'reply' : 'replies' ?><?php endif; ?></small>
          </div>
<?php if ($cm['item_url']): ?>
          <a class="ws-btn ws-btn--sm" href="<?= e($cm['item_url']) ?>" target="_blank" rel="noopener noreferrer">Open on <?= e($network) ?></a>
<?php endif; ?>
        </header>
        <p class="cm-body"><?= nl2br(e((string) $cm['body'])) ?></p>
<?php if ($view === 'list' && $cm['item_title']): ?>
        <p class="cm-on">On: <?= e($short($cm['item_title'], 110)) ?></p>
<?php endif; ?>
<?php if ($cm['reply_body']): ?>
        <div class="cm-mine">
          <small><?= !empty($cm['auto_reply']) ? 'Auto-reply sent' : 'You replied' ?> <?= e($when((string) $cm['replied_at'])) ?></small>
          <p><?= nl2br(e((string) $cm['reply_body'])) ?></p>
        </div>
<?php endif; ?>
<?php if ($cm['state'] !== 'done'): ?>
<?php if ($canReply): ?>
        <form class="cm-reply" action="/app/community" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $cm['id'] ?>">
          <?= $keep() ?>
          <label class="sr-only" for="cm-r-<?= (int) $cm['id'] ?>">Reply to <?= e($cm['author_name']) ?></label>
          <textarea id="cm-r-<?= (int) $cm['id'] ?>" name="reply" rows="2" maxlength="<?= Community::REPLY_MAX ?>" placeholder="Write a reply…"><?= e($text) ?></textarea>
          <div class="cm-acts">
            <button type="submit" name="action" value="reply" class="ws-btn ws-btn--dark ws-btn--sm">Send reply</button>
<?php if ($saved): ?>
            <select class="cm-saved-pick" aria-label="Insert a saved reply" data-cm-saved>
              <option value="">Saved replies…</option>
<?php foreach ($saved as $sr): ?>
              <option value="<?= (int) $sr['id'] ?>" data-body="<?= e($sr['body']) ?>"><?= e($sr['title']) ?></option>
<?php endforeach; ?>
            </select>
<?php endif; ?>
<?php if (Assistant::enabled()): ?>
            <button type="submit" name="action" value="suggest" class="ws-btn ws-btn--sm" formnovalidate><?= icon('sparkle') ?>Suggest a reply</button>
<?php endif; ?>
            <button type="submit" name="action" value="done" class="ws-btn ws-btn--sm ws-btn--ghost" formnovalidate>Mark done</button>
          </div>
        </form>
<?php else: ?>
        <form class="cm-acts" action="/app/community" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $cm['id'] ?>">
          <?= $keep() ?>
          <span class="cm-hint">Replying from atwebtech needs comment access for <?= e($network) ?>. <?= $cm['item_url'] ? 'Reply on ' . e($network) . ', then mark it done.' : '' ?></span>
          <button type="submit" name="action" value="done" class="ws-btn ws-btn--sm">Mark done</button>
        </form>
<?php endif; ?>
<?php else: ?>
        <form class="cm-acts" action="/app/community" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $cm['id'] ?>">
          <?= $keep() ?>
          <button type="submit" name="action" value="reopen" class="ws-btn ws-btn--sm ws-btn--ghost">Move to Unanswered</button>
        </form>
<?php endif; ?>
      </article>
<?php endforeach; ?>
<?php if ($page > 1 || $inbox['more']): ?>
      <nav class="cm-pages" aria-label="More comments">
<?php if ($page > 1): ?>
        <a class="ws-btn ws-btn--sm" href="<?= e($url(['page' => $page - 1])) ?>">Newer</a>
<?php endif; ?>
<?php if ($inbox['more']): ?>
        <a class="ws-btn ws-btn--sm" href="<?= e($url(['page' => $page + 1])) ?>">Older</a>
<?php endif; ?>
      </nav>
<?php endif; ?>
    </section>
  </div>
<?php endif; ?>
</div>
