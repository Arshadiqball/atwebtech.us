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
        <p>A social media workspace in development, designed to help creators and businesses plan content, organize ideas, and keep a consistent presence.</p>
      </div>
      <p class="footer-legal">
        Copyright &copy; <?= e(date('Y')) ?> atwebtech
        <span aria-hidden="true">|</span> <a href="/privacy">Privacy</a>
        <span aria-hidden="true">|</span> <a href="/data-deletion">Data deletion</a>
        <span aria-hidden="true">|</span> <a href="/terms">Terms</a>
        <span aria-hidden="true">|</span> <a href="/contact">Contact</a>
      </p>
    </div>
  </div>
</footer>
