<?php
$blocks = [
    ['id' => 'calendar', 'label' => 'Publishing calendar', 'panel' => 'pink', 'demo' => 'demos/calendar', 'href' => '/features/publishing',
        'text' => 'Switch between week and month views, filter channels, and select a post to preview it.'],
    ['id' => 'board', 'label' => 'Idea board', 'panel' => 'green', 'demo' => 'demos/idea-board', 'href' => '/features/content-planner',
        'text' => 'Add an idea, then drag it or use the arrows to move it from Ideas to Drafting to Ready for review.'],
    ['id' => 'inbox', 'label' => 'Community inbox', 'panel' => 'yellow', 'demo' => 'demos/inbox', 'href' => '/features/community',
        'text' => 'Filter by channel, open conversations to mark them read, and type a reply. Replies are never sent.'],
    ['id' => 'analytics', 'label' => 'Analytics', 'panel' => 'blue', 'demo' => 'demos/analytics', 'href' => '/features/analytics',
        'text' => 'Compare the last 30 and 90 days across audience growth, engagement, top posts, and channels.'],
];
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Interactive demo</p>
    <h1 class="h-page">Explore the atwebtech workspace.</h1>
    <p class="lead">Try each part of the product with sample data. Everything runs in your browser: nothing is saved, published, or sent.</p>
    <nav class="demo-index" aria-label="Demo sections">
<?php foreach ($blocks as $b): ?>
      <a href="#<?= e($b['id']) ?>"><?= e($b['label']) ?></a>
<?php endforeach; ?>
      <a href="#ai">AI workflow</a>
    </nav>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="notice" role="note">
      <?= icon('info') ?>
      <p><b>About this demo</b>All names, posts, conversations, and numbers are illustrative samples for a fictional ceramics studio. atwebtech does not yet connect to social accounts. Interactions reset when you reload the page.</p>
    </div>
  </div>
</section>

<?php foreach ($blocks as $b): ?>
<section class="section demo-block" id="<?= e($b['id']) ?>" aria-labelledby="<?= e($b['id']) ?>-title">
  <div class="container">
    <div class="demo-block-head">
      <div>
        <h2 class="h-section" id="<?= e($b['id']) ?>-title"><?= e($b['label']) ?></h2>
        <p><?= e($b['text']) ?></p>
      </div>
      <a class="link-arrow" href="<?= e($b['href']) ?>">About this feature<?= icon('arrow-right') ?></a>
    </div>
    <div class="showcase-card"><div class="showcase-panel panel--<?= e($b['panel']) ?>"><?php partial($b['demo']); ?></div></div>
  </div>
</section>
<?php endforeach; ?>

<section class="section demo-block" id="ai" aria-labelledby="ai-title">
  <div class="container">
    <div class="ai-card">
      <div class="ai-copy">
        <p class="eyebrow eyebrow--light">AI workflow</p>
        <h2 class="h-section" id="ai-title">Move from a blank page to a clearer plan.</h2>
        <p>Choose an example request to step through a scripted conversation. No AI model is connected; these replies are written examples of how the assistant is intended to help.</p>
        <a class="link-arrow" href="/features/ai-assistant">About AI writing assistance<?= icon('arrow-right') ?></a>
      </div>
      <div class="ai-visual"><?php partial('demos/ai-chat'); ?></div>
    </div>
  </div>
</section>

<?php partial('partials/cta-final', ['source' => 'demo']); ?>
