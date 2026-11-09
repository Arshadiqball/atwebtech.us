<?php
/** @var array $user */
/** @var string $section crm | contacts | followups | messages | subscribers | knowledge */
?>
<header class="adm-bar">
  <a class="adm-logo" href="/admin" aria-label="Admin home"><?php partial('partials/logo'); ?></a>
  <p class="sr-only">Signed in as <?= e($user['email']) ?></p>
  <nav class="adm-nav" aria-label="Admin">
    <a href="/admin"<?= $section === 'crm' ? ' aria-current="page"' : '' ?>>Dashboard</a>
    <a href="/admin/crm/contacts"<?= $section === 'contacts' ? ' aria-current="page"' : '' ?>>Contacts</a>
<?php $dueCount = count(Crm::followups('due', 100)); ?>
    <a href="/admin/crm/followups"<?= $section === 'followups' ? ' aria-current="page"' : '' ?>>Follow-ups<?php if ($dueCount > 0): ?> <span class="adm-badge"><?= $dueCount ?></span><?php endif; ?></a>
    <a href="/admin/messages"<?= $section === 'messages' ? ' aria-current="page"' : '' ?>>Messages</a>
    <a href="/admin/subscribers"<?= $section === 'subscribers' ? ' aria-current="page"' : '' ?>>Subscribers</a>
    <a href="/admin/knowledge"<?= $section === 'knowledge' ? ' aria-current="page"' : '' ?>>Chatbot</a>
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
