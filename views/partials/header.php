<?php
$site = data('site');
$menus = [
    'features' => ['label' => 'Features', 'items' => $site['features'], 'more' => ['See all features', '/features']],
    'solutions' => ['label' => 'Solutions', 'items' => $site['solutions'], 'more' => ['See all solutions', '/solutions']],
];
?>
<header class="site-header" data-header>
  <div class="header-inner">
    <a class="header-logo" href="/" aria-label="atwebtech home"><?php partial('partials/logo'); ?></a>

    <nav class="site-nav" aria-label="Main">
      <ul class="nav-list">
<?php foreach ($menus as $key => $menu): ?>
        <li class="nav-item" data-menu>
          <button type="button" class="nav-trigger" aria-expanded="false" aria-controls="menu-<?= e($key) ?>">
            <?= e($menu['label']) ?><?= icon('caret-down', 'icon nav-caret') ?>
          </button>
          <div class="mega mega--<?= e($key) ?>" id="menu-<?= e($key) ?>" hidden>
            <ul class="mega-grid" role="list">
<?php foreach ($menu['items'] as $item): ?>
              <li>
                <a class="mega-link" href="<?= e($item['href']) ?>"<?= aria_current($item['href']) ?>>
                  <span class="mega-icon"><?= icon($item['icon']) ?></span>
                  <span class="mega-text">
                    <span class="mega-title"><?= e($item['label']) ?></span>
                    <span class="mega-desc"><?= e($item['desc']) ?></span>
                  </span>
                </a>
              </li>
<?php endforeach; ?>
            </ul>
<?php if ($menu['more']): ?>
            <a class="mega-more" href="<?= e($menu['more'][1]) ?>"><?= e($menu['more'][0]) ?><?= icon('arrow-right') ?></a>
<?php endif; ?>
          </div>
        </li>
<?php endforeach; ?>
        <li class="nav-item"><a class="nav-link" href="/integrations"<?= aria_current('/integrations') ?>>Integrations</a></li>
        <li class="nav-item"><a class="nav-link" href="/pricing"<?= aria_current('/pricing') ?>>Pricing</a></li>
      </ul>
    </nav>

    <div class="header-actions">
      <a class="header-login" href="<?= Auth::check() ? '/app' : '/login' ?>"><?= Auth::check() ? 'Open workspace' : 'Log in' ?></a>
      <a class="btn btn--outline btn--sm header-demo" href="/demo">Live demo</a>
<?php if (!Auth::check()): ?>
      <a class="btn btn--primary btn--sm header-start" href="/signup">Start free<?= icon('arrow-right') ?></a>
<?php endif; ?>
      <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="mobile-menu" data-menu-open>
        <?= icon('list', 'icon icon--lg') ?><span class="sr-only">Open menu</span>
      </button>
    </div>
  </div>

  <div class="mobile-menu" id="mobile-menu" role="dialog" aria-modal="true" aria-label="Main menu" hidden data-mobile-menu>
    <div class="mobile-menu-head">
      <a class="header-logo" href="/" aria-label="atwebtech home"><?php partial('partials/logo'); ?></a>
      <button type="button" class="menu-toggle menu-close" data-menu-close>
        <?= icon('x', 'icon icon--lg') ?><span class="sr-only">Close menu</span>
      </button>
    </div>
    <nav class="mobile-nav" aria-label="Mobile">
<?php foreach ($menus as $key => $menu): ?>
      <div class="m-group">
        <button type="button" class="m-trigger" aria-expanded="false" aria-controls="m-<?= e($key) ?>">
          <?= e($menu['label']) ?><?= icon('caret-down', 'icon nav-caret') ?>
        </button>
        <ul class="m-list" id="m-<?= e($key) ?>" role="list" hidden>
<?php foreach ($menu['items'] as $item): ?>
          <li><a href="<?= e($item['href']) ?>"<?= aria_current($item['href']) ?>><?= e($item['label']) ?></a></li>
<?php endforeach; ?>
<?php if ($menu['more']): ?>
          <li><a class="m-more" href="<?= e($menu['more'][1]) ?>"><?= e($menu['more'][0]) ?></a></li>
<?php endif; ?>
        </ul>
      </div>
<?php endforeach; ?>
      <a class="m-link" href="/integrations"<?= aria_current('/integrations') ?>>Integrations</a>
      <a class="m-link" href="/pricing"<?= aria_current('/pricing') ?>>Pricing</a>
    </nav>
    <div class="mobile-menu-actions">
      <a class="btn btn--outline btn--block" href="<?= Auth::check() ? '/app' : '/login' ?>"><?= Auth::check() ? 'Open workspace' : 'Log in' ?></a>
      <a class="btn btn--outline btn--block" href="/demo">Live demo</a>
<?php if (!Auth::check()): ?>
      <a class="btn btn--primary btn--block" href="/signup">Start free<?= icon('arrow-right') ?></a>
<?php endif; ?>
    </div>
  </div>
</header>
