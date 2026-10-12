<?php
/** @var array $user */
/** @var array $onboarding */
/** @var array $prefill */
?>
<div class="wsp wsp--cp">
  <a class="wsp-back" href="/app/publish"><?= icon('arrow-left') ?>Back to Publish</a>
  <div class="wsp-card">
<?php partial('workspace/composer', ['user' => $user, 'onboarding' => $onboarding, 'prefill' => $prefill, 'dialog' => false]); ?>
  </div>
</div>
