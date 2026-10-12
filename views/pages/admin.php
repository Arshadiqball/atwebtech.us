<?php
/** @var array $user */
/** @var list<array<string, mixed>> $messages */
/** @var array<string, mixed>|null $selected */
$open = $selected !== null;
$count = count($messages);
?>
<div class="adm<?= $open ? ' is-open' : '' ?>" id="main" tabindex="-1">
  <header class="adm-bar">
    <a class="adm-logo" href="/admin" aria-label="Messages"><?php partial('partials/logo'); ?></a>
    <p class="sr-only">Signed in as <?= e($user['email']) ?></p>
    <p class="adm-bar-label">Admin</p>
    <div class="adm-tools">
      <a href="/app">Workspace</a>
      <?php partial('partials/theme-toggle'); ?>
      <form action="/logout" method="post">
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
        <button type="submit">Log out</button>
      </form>
    </div>
  </header>

<?php if (!$messages): ?>
  <div class="adm-empty">
    <span class="adm-empty-icon" aria-hidden="true"><?= icon('envelope-simple') ?></span>
    <h1>No messages yet</h1>
    <p>When someone writes through the <a href="/contact">contact form</a>, the message shows up here.</p>
  </div>
<?php else: ?>
  <div class="adm-body">
    <section class="adm-list" aria-label="Contact messages">
      <header class="adm-list-head">
        <h1>Messages</h1>
        <p><?= e($count === 1 ? '1 message' : $count . ' messages') ?></p>
      </header>
      <ul role="list">
<?php foreach ($messages as $row):
    $id = (int) $row['id'];
    $current = $open && (int) $selected['id'] === $id;
    $when = Admin::when((string) $row['created_at']);
?>
        <li>
          <a class="adm-item" href="/admin/messages/<?= $id ?>"<?= $current ? ' aria-current="page"' : '' ?>>
            <span class="adm-item-top">
              <b><?= e($row['name']) ?></b>
              <time datetime="<?= e($when->format('c')) ?>"><?= e($when->format('j M')) ?></time>
            </span>
            <span class="adm-topic"><?= e(Admin::topic((string) $row['topic'])) ?></span>
            <span class="adm-excerpt"><?= e(Admin::excerpt((string) $row['message'])) ?></span>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
    </section>

    <article class="adm-read">
<?php if ($open):
    $when = Admin::when((string) $selected['created_at']);
    $topic = Admin::topic((string) $selected['topic']);
    $mailto = 'mailto:' . rawurlencode((string) $selected['email']) . '?subject=' . rawurlencode('Re: ' . $topic);
?>
      <a class="adm-back" href="/admin"><?= icon('arrow-left') ?>All messages</a>
      <header class="adm-read-head">
        <p class="adm-topic"><?= e($topic) ?></p>
        <h2><?= e($selected['name']) ?></h2>
        <p class="adm-meta">
          <a href="<?= e($mailto) ?>"><?= e($selected['email']) ?></a>
          <time datetime="<?= e($when->format('c')) ?>"><?= e($when->format('j M Y, H:i')) ?> UTC</time>
        </p>
        <a class="adm-reply" href="<?= e($mailto) ?>"><?= icon('arrow-bend-up-left') ?>Reply by email</a>
      </header>
      <div class="adm-message"><?= e($selected['message']) ?></div>
<?php else: ?>
      <div class="adm-placeholder">
        <p>Select a message to read it.</p>
      </div>
<?php endif; ?>
    </article>
  </div>
<?php endif; ?>
</div>
