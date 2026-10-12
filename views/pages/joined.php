<?php $joined = flash('joined') === '1'; ?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow"><?= $joined ? 'You are on the list' : 'Waitlist' ?></p>
    <h1 class="h-page"><?= $joined ? 'We will email you when there is news.' : 'Join the list to hear from us.' ?></h1>
    <p class="lead"><?= $joined
        ? 'Your email is saved for atwebtech product and early-access updates. You can create an account now if you want to use the workspace.'
        : 'This page confirms a waitlist signup. Add your email on the join page and we will bring you back here.' ?></p>
    <div class="btn-row">
<?php if ($joined): ?>
      <a class="btn btn--green" href="/signup">Create an account</a>
      <a class="btn btn--outline" href="/demo">Explore the demo</a>
<?php else: ?>
      <a class="btn btn--green" href="/join">Join the waitlist</a>
<?php endif; ?>
    </div>
  </div>
</section>
