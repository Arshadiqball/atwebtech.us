<?php
$source = $source ?? 'final-cta';
$plan = $plan ?? null;
?>
<section class="section section--cta" id="join" aria-labelledby="join-title">
  <div class="container">
    <div class="cta-card">
      <div class="cta-panel">
        <h2 class="h-cta" id="join-title">Make space for your next great idea.</h2>
        <p class="cta-text">Join the atwebtech waitlist for product updates and early-access news.</p>
        <?php partial('partials/waitlist-form', ['id' => 'waitlist-final', 'source' => $source, 'variant' => 'cta', 'plan' => $plan]); ?>
      </div>
    </div>
  </div>
</section>
