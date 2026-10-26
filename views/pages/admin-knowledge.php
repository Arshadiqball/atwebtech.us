<?php
/** @var array $user */
/** @var array<string, array{label:string, items:list<string>}> $knowledge */
$link = static fn (string $item): string => preg_replace('#\[([^\]\n]{1,80})\]\((/[A-Za-z0-9/_\-?=&\#.%]*)\)#', '<a href="$2">$1</a>', e($item)) ?? e($item);
?>
<div class="adm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'knowledge']); ?>
  <div class="adm-kb">
    <header class="adm-kb-head">
      <img src="<?= e(asset('img/sarah.webp')) ?>" alt="" width="56" height="56">
      <div>
        <h1>What Sarah knows</h1>
        <p>This is the knowledge the website chatbot answers from, built from atwebtech.us content. <?= Assistant::enabled() ? 'Sarah is live on the website.' : 'Sarah is off until an OpenAI API key is set.' ?></p>
      </div>
    </header>
    <ul class="adm-kb-list" role="list">
<?php foreach ($knowledge as $key => $section): ?>
      <li>
        <details class="adm-kb-item">
          <summary>
            <span class="adm-kb-dot" aria-hidden="true"></span>
            <b><?= e($section['label']) ?></b>
            <span class="adm-kb-count"><?= count($section['items']) ?> <?= count($section['items']) === 1 ? 'fact' : 'facts' ?></span>
          </summary>
          <ul>
<?php foreach ($section['items'] as $item): ?>
            <li><?= $link($item) ?></li>
<?php endforeach; ?>
          </ul>
        </details>
      </li>
<?php endforeach; ?>
    </ul>
    <p class="adm-kb-note">Product, pricing, guides, FAQ, contact details and channel status update automatically from the site. Company facts, branding, policies and extra questions are edited in <code>app/data/knowledge.php</code>. Support hours come from <code>SUPPORT_HOURS</code> in <code>.env</code>.</p>
  </div>
</div>
