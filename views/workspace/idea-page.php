<?php
/** @var array $user */
/** @var ?array $idea */
/** @var int $group */
?>
<div class="wsp wsp--narrow">
  <a class="wsp-back" href="/app/create"><?= icon('arrow-left') ?>Back to ideas</a>
  <div class="wsp-card">
<?php partial('workspace/idea-form', ['user' => $user, 'idea' => $idea, 'group' => $group, 'dialog' => false]); ?>
  </div>
</div>
