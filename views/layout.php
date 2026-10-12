<?php
/** @var string $content */
/** @var array $meta */
$canonical = absolute_url($meta['path']);
$ogImage = str_starts_with($meta['og_image'], 'http') ? $meta['og_image'] : absolute_url(strtok($meta['og_image'], '?'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($meta['title']) ?></title>
  <meta name="description" content="<?= e($meta['description']) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($meta['noindex']): ?>
  <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
  <meta property="og:site_name" content="atwebtech">
  <meta property="og:type" content="<?= e($meta['type']) ?>">
  <meta property="og:title" content="<?= e($meta['title']) ?>">
  <meta property="og:description" content="<?= e($meta['description']) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="atwebtech: your content, your community, one workspace">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="#FEFDFB">
  <meta name="color-scheme" content="light">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <link rel="preload" href="/assets/fonts/figtree-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/plus-jakarta-sans-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ($meta['schema'] as $schema): ?>
  <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php partial('partials/header'); ?>
  <main id="main" tabindex="-1">
<?= $content ?>
  </main>
  <?php partial('partials/footer'); ?>
</body>
</html>
