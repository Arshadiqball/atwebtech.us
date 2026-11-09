<?php
/** @var string $token */
/** @var array $user  Account owner: id, display_name, timezone */
/** @var array $posts Posts waiting for approval */
/** @var ?string $notice */
$tz = in_array((string) ($user['timezone'] ?? ''), DateTimeZone::listIdentifiers(), true) ? new DateTimeZone($user['timezone']) : new DateTimeZone('UTC');
$names = array_column(integrations(), 'name', 'slug');
$colors = array_column(integrations(), 'color', 'slug');
$csrf = Security::csrfToken();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Review posts for <?= e($user['display_name']) ?> | atwebtech</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <style>
    :root { --bg: #F6F6F3; --card: #fff; --ink: #141A19; --muted: #5E6461; --line: #E3E1DB; --green: #16A34A; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
    header { background: #0B1514; color: #fff; padding: 28px 20px; }
    header div, main { max-width: 760px; margin: 0 auto; }
    header small { color: rgb(255 255 255 / .65); font-size: 13px; letter-spacing: .04em; text-transform: uppercase; }
    h1 { margin: 6px 0 4px; font-size: 26px; letter-spacing: -0.02em; }
    header p { margin: 0; color: rgb(255 255 255 / .75); }
    main { padding: 24px 20px 60px; }
    .notice { padding: 12px 16px; border-radius: 12px; background: #E6F6EC; color: #145A2C; margin-bottom: 18px; font-weight: 500; }
    .name { display: flex; gap: 10px; align-items: center; margin-bottom: 18px; font-size: 14px; color: var(--muted); flex-wrap: wrap; }
    .name input { flex: 1; min-width: 200px; }
    article { background: var(--card); border-radius: 16px; padding: 18px 20px; margin-bottom: 14px; box-shadow: 0 0 0 1px var(--line), 0 12px 30px -24px rgb(0 0 0 / .4); }
    .meta { display: flex; gap: 8px; align-items: center; font-size: 13px; color: var(--muted); margin-bottom: 10px; }
    .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--c); }
    .meta b { color: var(--ink); }
    .title { font-weight: 700; margin: 0 0 4px; }
    .body { white-space: pre-line; margin: 0 0 12px; }
    .file { font-size: 13px; color: var(--muted); margin: 0 0 12px; }
    .acts { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-start; }
    button { font: inherit; cursor: pointer; border-radius: 10px; padding: 10px 16px; border: 1px solid var(--line); background: #fff; color: var(--ink); font-weight: 600; }
    .approve { background: var(--green); border-color: var(--green); color: #fff; }
    details { flex: 1; min-width: 240px; }
    summary { list-style: none; display: inline-block; cursor: pointer; border-radius: 10px; padding: 10px 16px; border: 1px solid var(--line); font-weight: 600; }
    summary::-webkit-details-marker { display: none; }
    details form { display: grid; gap: 8px; margin-top: 10px; }
    textarea, input { font: inherit; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: var(--ink); }
    .empty { text-align: center; padding: 48px 20px; color: var(--muted); }
    footer { text-align: center; font-size: 13px; color: var(--muted); padding: 0 20px 30px; }
    footer a { color: inherit; }
  </style>
</head>
<body>
  <header><div>
    <small>Content review</small>
    <h1><?= e($user['display_name']) ?> would like your approval</h1>
    <p>Approve a post to schedule it, or ask for changes. Nothing is published until it's approved.</p>
  </div></header>
  <main>
<?php if ($notice): ?>
    <p class="notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($posts): ?>
    <label class="name">Your name (optional, shown with your decision) <input id="reviewer" maxlength="60" autocomplete="name" form="none"></label>
<?php foreach ($posts as $post): $at = $post['planned_at'] ? Workspace::local((string) $post['planned_at'], $tz) : null; ?>
    <article>
      <p class="meta"><span class="dot" style="--c: <?= e($colors[$post['channel']] ?? '#888') ?>"></span><b><?= e($names[$post['channel']] ?? $post['channel']) ?></b><?= $at ? ' · ' . e($at->format('D j M, g:i A T')) : '' ?></p>
<?php if ($post['title']): ?><p class="title"><?= e($post['title']) ?></p><?php endif; ?>
<?php if ($post['body'] !== ''): ?><p class="body"><?= e($post['body']) ?></p><?php endif; ?>
<?php if ($post['media_id']): ?><p class="file">Includes a photo or video.</p><?php endif; ?>
      <div class="acts">
        <form method="post" action="/review/<?= e($token) ?>" data-review>
          <input type="hidden" name="_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
          <input type="hidden" name="reviewer" value="">
          <button class="approve" name="decision" value="approve">Approve</button>
        </form>
        <details>
          <summary>Request changes</summary>
          <form method="post" action="/review/<?= e($token) ?>" data-review>
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
            <input type="hidden" name="reviewer" value="">
            <textarea name="note" rows="3" maxlength="1000" placeholder="What should change?" required></textarea>
            <button name="decision" value="changes">Send feedback</button>
          </form>
        </details>
      </div>
    </article>
<?php endforeach; ?>
<?php else: ?>
    <p class="empty">All caught up. There are no posts waiting for your review.</p>
<?php endif; ?>
  </main>
  <footer>Review link from <a href="https://atwebtech.us/">atwebtech</a>, the social media workspace.</footer>
  <script src="<?= e(asset('js/review.js')) ?>" defer></script>
</body>
</html>
