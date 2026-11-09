<?php $joined = flash('joined') === '1'; ?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow"><?= $joined ? 'You are subscribed' : 'Product updates' ?></p>
    <h1 class="h-page"><?= $joined ? 'Thanks. We will keep you posted.' : 'Get atwebtech product updates.' ?></h1>
    <p class="lead"><?= $joined
        ? 'Your email is saved for atwebtech product updates. Want to start planning now? Create a free account and use the workspace today.'
        : 'This page confirms an updates signup. Add your email on the updates page, or create a free account to get started right away.' ?></p>
    <div class="btn-row">
<?php if ($joined): ?>
      <a class="btn btn--green" href="/signup">Start free</a>
      <a class="btn btn--outline" href="/demo">View demo</a>
<?php else: ?>
      <a class="btn btn--green" href="/signup">Start free</a>
      <a class="btn btn--outline" href="/join">Get product updates</a>
<?php endif; ?>
    </div>
  </div>
</section>
