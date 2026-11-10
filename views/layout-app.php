<?php
/** @var string $content */
/** @var array $meta */
$theme = Auth::person()['theme'] ?? 'light';
?>
<!doctype html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($meta['title']) ?></title>
  <meta name="description" content="<?= e($meta['description']) ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="color-scheme" content="<?= $theme === 'dark' ? 'dark' : 'light' ?>">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="<?= $theme === 'dark' ? '#0C100F' : '#F1F0EB' ?>">
  <link rel="preload" href="/assets/fonts/figtree-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/plus-jakarta-sans-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/account.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
<?php if (!empty($meta['admin'])): ?>
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php endif; ?>
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (Meta::enabled()): ?>
  <script type="application/json" id="meta-events"><?= json_encode(Meta::takeBrowserEvents(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script src="<?= e(asset('js/meta-pixel.js')) ?>" data-pixel="<?= e(Meta::pixelId()) ?>" data-pageview="0" defer></script>
<?php endif; ?>
</head>
<body class="acct">
  <a class="skip-link" href="#main">Skip to content</a>
<?= $content ?>
</body>
</html>
