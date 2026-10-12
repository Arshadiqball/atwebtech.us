<?php
$tiles = [
    ['instagram', 't1'], ['youtube', 't2'], ['text:in', 't3'], ['ui:calendar-blank', 't4'], ['tiktok', 't5'], ['ui:chart-line-up', 't6'],
    ['x', 't7'], ['pinterest', 't8'], ['threads', 't9'], ['facebook', 't10'], ['ui:sparkle', 't11'], ['ui:storefront', 't12'], ['ui:lightbulb', 't13'], ['ui:chats-circle', 't14'],
];
$tileColors = array_column(integrations(), 'color', 'icon');
?>
  <div class="hero-tiles<?= isset($modifier) ? ' hero-tiles--' . e($modifier) : '' ?>" aria-hidden="true">
<?php foreach ($tiles as [$iconKey, $pos]): ?>
    <span class="tile <?= e($pos) ?>" style="--c: <?= e($tileColors[$iconKey] ?? '#213130') ?>"><?= brand_mark($iconKey, '', true) ?></span>
<?php endforeach; ?>
  </div>
