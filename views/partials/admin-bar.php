<?php
/** @var array $user */
/** @var string $section messages | subscribers */
?>
<header class="adm-bar">
  <a class="adm-logo" href="/admin" aria-label="Admin home"><?php partial('partials/logo'); ?></a>
  <p class="sr-only">Signed in as <?= e($user['email']) ?></p>
  <nav class="adm-nav" aria-label="Admin">
    <a href="/admin"<?= $section === 'messages' ? ' aria-current="page"' : '' ?>>Messages</a>
    <a href="/admin/subscribers"<?= $section === 'subscribers' ? ' aria-current="page"' : '' ?>>Subscribers</a>
  </nav>
  <div class="adm-tools">
<?php if ((int) ($user['id'] ?? 0) > 0): ?>
    <a href="/app">Workspace</a>
<?php endif; ?>
    <?php partial('partials/theme-toggle'); ?>
    <form action="/logout" method="post">
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
      <button type="submit">Log out</button>
    </form>
  </div>
</header>
