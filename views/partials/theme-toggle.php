<?php $current = Auth::user()['theme'] ?? 'light'; ?>
<form class="theme-toggle" action="/account/theme" method="post" data-theme-toggle>
  <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
  <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '/app') ?>">
  <button type="submit" name="theme" value="light" aria-pressed="<?= $current === 'light' ? 'true' : 'false' ?>"><?= icon('sun') ?><span class="sr-only">Light theme</span></button>
  <button type="submit" name="theme" value="dark" aria-pressed="<?= $current === 'dark' ? 'true' : 'false' ?>"><?= icon('moon') ?><span class="sr-only">Dark theme</span></button>
</form>
