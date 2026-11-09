<?php
$source = $source ?? 'final-cta';
$plan = $plan ?? null;
?>
<section class="section section--cta" id="join" aria-labelledby="join-title">
  <div class="container">
    <div class="cta-launch">
      <div class="cta-orbs" aria-hidden="true"><span></span><span></span><span></span></div>
      <div class="cta-cubes" aria-hidden="true">
<?php foreach (['instagram', 'youtube', 'tiktok', 'text:in'] as $i => $slug): ?>
        <span class="cube cube--<?= $i + 1 ?>"><?= brand_mark($slug, '', true) ?></span>
<?php endforeach; ?>
      </div>
<?php if ($plan === null): ?>
      <h2 class="h-cta" id="join-title">Your next month of content starts today.</h2>
      <p class="cta-text">Create your free account in under a minute. No credit card, no time limit.</p>
      <div class="hero-ctas">
        <a class="btn btn--green btn--lg" href="/signup">Start free<?= icon('arrow-right') ?></a>
        <a class="btn btn--ghost-light btn--lg" href="/demo"><?= icon('play') ?>Try the live demo</a>
      </div>
<?php else: ?>
      <h2 class="h-cta" id="join-title">Want a paid plan? Get first access.</h2>
      <p class="cta-text">Everyone starts on Free today. Leave your email and we'll let you know the moment Essentials and Team open.</p>
      <?php partial('partials/waitlist-form', ['id' => 'waitlist-final', 'source' => $source, 'variant' => 'cta', 'plan' => $plan, 'button' => 'Notify me']); ?>
      <p class="cta-alt">Or <a href="/signup">start free now</a> and upgrade later.</p>
<?php endif; ?>
    </div>
  </div>
</section>
