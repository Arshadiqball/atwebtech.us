<?php
/** @var array $user */
/** @var list<array<string, mixed>> $subscribers */
/** @var array<string, mixed>|null $selected */
$open = $selected !== null;
$count = count($subscribers);
?>
<div class="adm<?= $open ? ' is-open' : '' ?>" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'subscribers']); ?>

<?php if (!$subscribers): ?>
  <div class="adm-empty">
    <span class="adm-empty-icon" aria-hidden="true"><?= icon('envelope-simple') ?></span>
    <h1>No subscribers yet</h1>
    <p>When someone joins the <a href="/join">waitlist</a>, their email shows up here for future updates.</p>
  </div>
<?php else: ?>
  <div class="adm-body">
    <section class="adm-list" aria-label="Waitlist subscribers">
      <header class="adm-list-head">
        <h1>Subscribers</h1>
        <p><?= e($count === 1 ? '1 email' : $count . ' emails') ?></p>
      </header>
      <ul role="list">
<?php foreach ($subscribers as $row):
    $id = (int) $row['id'];
    $current = $open && (int) $selected['id'] === $id;
    $when = Admin::when((string) $row['updated_at']);
?>
        <li>
          <a class="adm-item" href="/admin/subscribers/<?= $id ?>"<?= $current ? ' aria-current="page"' : '' ?>>
            <span class="adm-item-top">
              <b><?= e($row['email']) ?></b>
              <time datetime="<?= e($when->format('c')) ?>"><?= e($when->format('j M')) ?></time>
            </span>
            <span class="adm-topic"><?= e(Admin::source((string) $row['source'])) ?></span>
<?php if ($row['plan']): ?>
            <span class="adm-excerpt"><?= e(Admin::plan((string) $row['plan'])) ?></span>
<?php endif; ?>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
    </section>

    <article class="adm-read">
<?php if ($open):
    $joined = Admin::when((string) $selected['created_at']);
    $updated = Admin::when((string) $selected['updated_at']);
    $mailto = 'mailto:' . rawurlencode((string) $selected['email']) . '?subject=' . rawurlencode('atwebtech');
    $facts = array_filter([
        'Joined' => $joined->format('j M Y, H:i') . ' UTC',
        'Updated' => $updated->format('c') !== $joined->format('c') ? $updated->format('j M Y, H:i') . ' UTC' : null,
        'From' => Admin::source((string) $selected['source']),
        'Role' => $selected['role'] ? Admin::role((string) $selected['role']) : null,
        'Plan' => $selected['plan'] ? Admin::plan((string) $selected['plan']) : null,
        'Billing' => $selected['billing'] ? ucfirst((string) $selected['billing']) : null,
        'Channels' => $selected['channels'] !== null && $selected['channels'] !== '' ? (string) $selected['channels'] : null,
        'Campaign' => $selected['utm_campaign'] ?: null,
        'Campaign source' => $selected['utm_source'] ?: null,
        'Medium' => $selected['utm_medium'] ?: null,
    ]);
?>
      <a class="adm-back" href="/admin/subscribers"><?= icon('arrow-left') ?>All subscribers</a>
      <header class="adm-read-head">
        <p class="adm-topic">Waitlist</p>
        <h2><?= e($selected['email']) ?></h2>
        <p class="adm-meta">
          <time datetime="<?= e($joined->format('c')) ?>">Joined <?= e($joined->format('j M Y, H:i')) ?> UTC</time>
        </p>
        <a class="adm-reply" href="<?= e($mailto) ?>"><?= icon('envelope-simple') ?>Email subscriber</a>
      </header>
      <dl class="adm-facts">
<?php foreach ($facts as $label => $value): ?>
        <div>
          <dt><?= e($label) ?></dt>
          <dd><?= e($value) ?></dd>
        </div>
<?php endforeach; ?>
      </dl>
<?php else: ?>
      <div class="adm-placeholder">
        <p>Select a subscriber to see the signup.</p>
      </div>
<?php endif; ?>
    </article>
  </div>
<?php endif; ?>
</div>
