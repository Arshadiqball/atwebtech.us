<?php $items = $items ?? data('faq'); $faqIcon = $icon ?? 'plus'; ?>
<div class="faq-list">
<?php foreach ($items as $i => $item): ?>
  <details class="faq-item"<?= ($open ?? false) && $i === 0 ? ' open' : '' ?>>
    <summary class="faq-q">
      <span><?= e($item['q']) ?></span>
      <span class="faq-icon" aria-hidden="true"><?= icon($faqIcon) ?></span>
    </summary>
    <div class="faq-a"><?= $item['a'] ?></div>
  </details>
<?php endforeach; ?>
</div>
