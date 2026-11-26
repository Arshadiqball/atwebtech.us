<?php $site = data('site'); ?>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-cols">
<?php foreach ($site['footer'] as $heading => $links): ?>
      <nav class="footer-col" aria-label="<?= e($heading) ?>">
        <h2 class="footer-heading"><?= e($heading) ?></h2>
        <ul role="list">
<?php foreach ($links as [$label, $href]): ?>
          <li><a href="<?= e($href) ?>"<?= aria_current($href) ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
        </ul>
      </nav>
<?php endforeach; ?>
    </div>
    <div class="footer-bottom">
      <div class="footer-brand">
        <a href="/" class="footer-logo" aria-label="atwebtech home"><?php partial('partials/logo', ['size' => 'xl']); ?></a>
        <p>The social media workspace for creators and small teams. Plan content, organize ideas, and publish on schedule.</p>
        <a class="btn btn--green btn--sm footer-cta" href="/signup">Start free</a>
      </div>
      <p class="footer-legal">
        Copyright &copy; <?= e(date('Y')) ?> atwebtech
        <span aria-hidden="true">|</span> <a href="/privacy">Privacy</a>
        <span aria-hidden="true">|</span> <a href="/data-deletion">Data deletion</a>
        <span aria-hidden="true">|</span> <a href="/terms">Terms</a>
        <span aria-hidden="true">|</span> <a href="/contact">Contact</a>
      </p>
    </div>
<?php // Directory badges load from 15 other websites, so they appear on the home page only: that is the page the directories check.
if (!empty($site['featured']) && current_path() === '/'): ?>
    <ul class="footer-featured" role="list" aria-label="Featured on">
<?php foreach ($site['featured'] as [$name, $href, $img]): ?>
      <li><a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"><img src="<?= e($img) ?>" alt="Featured on <?= e($name) ?>" height="44" loading="lazy" decoding="async" fetchpriority="low" referrerpolicy="no-referrer"></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </div>
</footer>
