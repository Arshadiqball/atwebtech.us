<?php
$flows = data('demo')['ai_flows'];
$payload = array_map(static fn ($f) => ['prompt' => $f['prompt'], 'reply' => $f['reply']], $flows);
?>
<div class="ai-demo" data-ai>
  <div class="ai-window">
    <div class="ai-head">
      <span class="ai-title"><?php partial('partials/logo-mark', ['class' => 'app-logo']); ?>atwebtech assistant</span>
      <?php partial('partials/demo-label', ['text' => 'Scripted demo']); ?>
    </div>
    <div class="ai-log" data-ai-log aria-live="polite">
      <div class="ai-msg ai-msg--bot">
        <span class="ai-avatar" aria-hidden="true"><?= icon('sparkle') ?></span>
        <div class="ai-bubble"><p>Pick an example below to see how the assistant is designed to help. These replies are pre-written examples, not live AI output.</p></div>
      </div>
    </div>
    <div class="ai-prompts" role="group" aria-label="Example requests">
<?php foreach ($flows as $key => $flow): ?>
      <button type="button" class="ai-prompt" data-ai-flow="<?= e($key) ?>" aria-pressed="false"><?= e($flow['label']) ?></button>
<?php endforeach; ?>
    </div>
    <div class="ai-input" aria-hidden="true">
      <span>Live AI is not connected in this demo</span>
      <span class="ai-send"><?= icon('arrow-up') ?></span>
    </div>
  </div>
  <script type="application/json" data-ai-data><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</div>
