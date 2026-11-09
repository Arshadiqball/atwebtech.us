<?php
/** @var array $page */
/** @var array $links */
$accent = in_array($page['accent'], Bio::ACCENTS, true) ? $page['accent'] : Bio::ACCENTS[0];
$url = Bio::publicUrl((string) $page['handle']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page['title']) ?></title>
  <meta name="description" content="<?= e($page['bio'] ?: $page['title']) ?>">
  <link rel="canonical" href="<?= e($url) ?>">
  <meta property="og:title" content="<?= e($page['title']) ?>">
  <meta property="og:description" content="<?= e($page['bio'] ?: 'Links from ' . $page['title']) ?>">
  <meta property="og:url" content="<?= e($url) ?>">
  <meta name="theme-color" content="<?= e($accent) ?>">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <style>
    :root { --accent: <?= e($accent) ?>; --bg: #F7F7F5; --ink: #141A19; --muted: #5E6461; --card: #fff; }
    @media (prefers-color-scheme: dark) { :root { --bg: #0F1413; --ink: #F1F4F2; --muted: #A3ABA7; --card: #19201F; } }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; background: radial-gradient(120% 60% at 50% 0%, color-mix(in srgb, var(--accent) 22%, var(--bg)), var(--bg) 70%); color: var(--ink);
      font: 16px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
    main { max-width: 560px; margin: 0 auto; padding: 56px 20px 40px; display: flex; flex-direction: column; align-items: center; text-align: center; }
    .avatar { display: grid; place-items: center; width: 88px; height: 88px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 36px; font-weight: 700;
      box-shadow: 0 0 0 5px color-mix(in srgb, var(--accent) 22%, transparent), 0 18px 40px -16px var(--accent); }
    h1 { margin: 18px 0 6px; font-size: 24px; letter-spacing: -0.02em; }
    p { margin: 0 0 26px; color: var(--muted); max-width: 440px; white-space: pre-line; }
    ul { list-style: none; margin: 0; padding: 0; width: 100%; display: grid; gap: 12px; }
    a.link { display: block; padding: 16px 20px; border-radius: 16px; background: var(--card); color: var(--ink); text-decoration: none; font-weight: 600;
      box-shadow: 0 0 0 1px color-mix(in srgb, var(--ink) 10%, transparent), 0 10px 24px -18px rgb(0 0 0 / .5); transition: transform .15s, box-shadow .15s; }
    a.link:hover, a.link:focus-visible { transform: translateY(-2px); box-shadow: 0 0 0 2px var(--accent), 0 14px 28px -18px var(--accent); outline: none; }
    footer { margin-top: 40px; font-size: 13px; }
    footer a { color: var(--muted); text-decoration: none; }
    footer a:hover { color: var(--ink); }
  </style>
</head>
<body>
  <main>
    <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $page['title'], 0, 1))) ?></span>
    <h1><?= e($page['title']) ?></h1>
<?php if ($page['bio']): ?>
    <p><?= e($page['bio']) ?></p>
<?php else: ?>
    <p></p>
<?php endif; ?>
    <ul>
<?php foreach ($links as $link): ?>
      <li><a class="link" href="/l/<?= e($page['handle']) ?>/<?= (int) $link['id'] ?>" rel="noopener nofollow"><?= e($link['label']) ?></a></li>
<?php endforeach; ?>
    </ul>
    <footer><a href="https://atwebtech.us/?utm_source=linkinbio&amp;utm_medium=referral">Made with atwebtech · Create your own free page</a></footer>
  </main>
</body>
</html>
