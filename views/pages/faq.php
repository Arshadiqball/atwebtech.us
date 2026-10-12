<section class="page-hero">
  <div class="container">
    <p class="eyebrow">FAQ</p>
    <h1 class="h-page">Questions, answered.</h1>
    <p class="lead">What atwebtech is, where it stands today, and how to stay in touch.</p>
  </div>
</section>

<section class="section">
  <div class="container faq-wrap">
    <?php partial('partials/faq-list', ['items' => data('faq')]); ?>
    <p class="faq-more">Still have a question? <a href="/contact">Contact the team</a>.</p>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'page']); ?>
