<?php
$site = data('site');
$channels = integrations();
$availableCount = count(array_filter($channels, static fn ($c) => $c['status'] === 'available'));
$articles = data('articles');
?>
<section class="hero" aria-labelledby="hero-title">
<?php partial('partials/hero-tiles'); ?>
  <div class="container hero-inner">
    <h1 class="h-hero" id="hero-title"><span>Your content. Your community.</span> <span>One workspace.</span></h1>
    <p class="hero-sub">Meet atwebtech: a simpler way to plan your social content, organize ideas, and build a more consistent online presence.</p>
    <?php partial('partials/waitlist-form', ['id' => 'waitlist-hero', 'source' => 'hero', 'variant' => 'hero']); ?>
    <a class="btn btn--outline hero-demo" href="/demo">Explore the demo<?= icon('arrow-right') ?></a>
  </div>
  <div class="container hero-visual">
    <?php partial('demos/hero-mockup'); ?>
  </div>
</section>

<section class="audience" aria-labelledby="audience-title">
  <div class="container">
    <h2 class="rule-title" id="audience-title"><span>Designed for the people behind the posts.</span></h2>
    <ul class="audience-list" role="list">
<?php foreach ($site['solutions'] as $s): ?>
      <li><a href="<?= e($s['href']) ?>"><?= icon($s['icon'], 'icon audience-icon') ?><?= e(ucfirst(strtolower($s['label']))) ?></a></li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section core" aria-label="Core features">
  <div class="container core-grid">
    <article class="core-card core-card--wide reveal" aria-labelledby="f-publishing">
      <div class="core-panel panel--pink"><?php partial('demos/calendar'); ?></div>
      <div class="core-body core-body--split">
        <div>
          <p class="eyebrow">Publishing</p>
          <h2 class="h-card" id="f-publishing">Make room for a more consistent posting rhythm.</h2>
        </div>
        <div>
          <p>Organize upcoming posts in a clear calendar, tailor your content for each channel, and keep your next campaign moving.</p>
          <a class="link-arrow" href="/features/publishing">Explore publishing<?= icon('arrow-right') ?></a>
        </div>
      </div>
    </article>

    <article class="core-card reveal" aria-labelledby="f-content">
      <div class="core-panel panel--green"><?php partial('demos/idea-board'); ?></div>
      <div class="core-body">
        <p class="eyebrow">Content planning</p>
        <h2 class="h-card" id="f-content">Give every good idea somewhere to grow.</h2>
        <p>Collect inspiration, shape your drafts, and keep your content organized from the first thought to the final review.</p>
        <a class="link-arrow" href="/features/content-planner">Explore content planning<?= icon('arrow-right') ?></a>
      </div>
    </article>

    <article class="core-card reveal" aria-labelledby="f-community">
      <div class="core-panel panel--yellow"><?php partial('demos/inbox'); ?></div>
      <div class="core-body">
        <p class="eyebrow">Community</p>
        <h2 class="h-card" id="f-community">Keep the conversation moving.</h2>
        <p>Explore a shared inbox designed to help your team organize conversations, find unanswered questions, and prepare thoughtful replies.</p>
        <a class="link-arrow" href="/features/community">Explore the inbox<?= icon('arrow-right') ?></a>
      </div>
    </article>

    <article class="core-card core-card--wide reveal" aria-labelledby="f-analytics">
      <div class="core-panel panel--blue"><?php partial('demos/analytics'); ?></div>
      <div class="core-body core-body--split">
        <div>
          <p class="eyebrow">Analytics</p>
          <h2 class="h-card" id="f-analytics">Understand the story behind your content.</h2>
        </div>
        <div>
          <p>Explore clear reporting views that help you compare posts, spot useful patterns, and decide what to create next.</p>
          <a class="link-arrow" href="/features/analytics">Explore analytics<?= icon('arrow-right') ?></a>
        </div>
      </div>
    </article>
  </div>
</section>

<section class="section more" aria-labelledby="more-title">
  <div class="container">
    <h2 class="h-section text-center" id="more-title">And plenty more to keep you organized.</h2>
    <div class="more-grid">
<?php
$more = [
    ['kind' => 'collab', 'panel' => 'coral', 'title' => 'Collaboration', 'text' => 'Bring feedback and approvals into one place.', 'href' => '/features/collaboration', 'link' => 'Explore collaboration'],
    ['kind' => 'mobile', 'panel' => 'lavender', 'title' => 'Mobile-friendly workspace', 'text' => 'Keep your plans close, wherever you work.', 'href' => '/demo', 'link' => 'Try the demo on mobile'],
    ['kind' => 'linkbio', 'panel' => 'orange', 'title' => 'Link in bio', 'text' => 'Give your audience a clear next step.', 'href' => '/features/link-in-bio', 'link' => 'Explore link in bio'],
    ['kind' => 'ai', 'panel' => 'teal', 'title' => 'AI writing assistance', 'text' => 'Turn a starting thought into a stronger draft.', 'href' => '/features/ai-assistant', 'link' => 'Explore AI assistance'],
];
foreach ($more as $m): ?>
      <article class="more-card reveal">
        <div class="more-panel panel--<?= e($m['panel']) ?>"><?php partial('demos/illustration', ['kind' => $m['kind']]); ?></div>
        <div class="more-body">
          <h3 class="h-small"><?= e($m['title']) ?></h3>
          <p><?= e($m['text']) ?></p>
          <a class="link-arrow" href="<?= e($m['href']) ?>"><?= e($m['link']) ?><?= icon('arrow-right') ?></a>
        </div>
      </article>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section channels-sec" aria-labelledby="channels-title">
  <div class="container">
    <div class="channels-strip">
      <div class="channels-copy">
        <h2 class="h-strip" id="channels-title">Plan for the channels your audience uses</h2>
        <p><?= $availableCount === 0 ? 'No channel connections are live yet. Each one shows its current status.' : e($availableCount . ' of ' . count($channels) . ' channels are available. The rest are planned.') ?></p>
        <a class="link-arrow" href="/integrations">See integration status<?= icon('arrow-right') ?></a>
      </div>
      <ul class="channel-tiles" role="list">
<?php foreach ($channels as $ch): ?>
        <li>
          <a class="channel-tile" href="/integrations#<?= e($ch['slug']) ?>">
            <span class="brand-tile" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span>
            <span class="channel-name"><?= e($ch['name']) ?></span>
            <span class="status status--<?= e($ch['status']) ?>"><?= $ch['status'] === 'available' ? 'Available' : 'Planned' ?></span>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="section ai-sec" aria-labelledby="ai-title">
  <div class="container">
    <div class="ai-card">
      <div class="ai-copy">
        <p class="eyebrow eyebrow--light">AI workflow preview</p>
        <h2 class="h-section" id="ai-title">Move from a blank page to a clearer plan.</h2>
        <p>See how an assistant inside atwebtech is designed to help you get from a rough thought to a plan you can review and edit.</p>
        <ul class="ai-steps" role="list">
          <li><b>Suggest:</b> content ideas around a product, a season, or a question.</li>
          <li><b>Refine:</b> a draft caption until the tone and length feel right.</li>
          <li><b>Organize:</b> a handful of ideas into a simple posting plan.</li>
        </ul>
        <div class="btn-row">
          <a class="btn btn--green btn--square" href="/features/ai-assistant">Explore AI assistance<?= icon('arrow-right') ?></a>
          <a class="btn btn--ghost-light btn--square" href="/demo#ai">Open the full demo<?= icon('arrow-right') ?></a>
        </div>
      </div>
      <div class="ai-visual"><?php partial('demos/ai-chat'); ?></div>
    </div>
  </div>
</section>

<section class="section support" aria-labelledby="support-title">
  <div class="container support-grid">
    <div class="support-copy">
      <p class="eyebrow eyebrow--muted">Support</p>
      <h2 class="h-section" id="support-title">A clear path when you need a hand.</h2>
      <p>Whether you are curious about the product, want to share feedback, or are planning ahead for launch, there is a straightforward place to start.</p>
      <div class="btn-row">
        <a class="btn btn--green btn--sm" href="/contact">Contact the team</a>
        <a class="btn btn--outline btn--sm" href="/faq">Read the FAQ</a>
      </div>
    </div>
    <div class="support-panel">
      <a class="help-link" href="/resources/getting-started">
        <span class="help-icon panel--yellow"><?= icon('rocket-launch') ?></span>
        <span><b>Getting started</b><small>What exists today, how the demo works, and how to hear about launch.</small></span>
        <?= icon('arrow-right', 'icon help-arrow') ?>
      </a>
      <a class="help-link" href="/faq">
        <span class="help-icon panel--blue"><?= icon('question') ?></span>
        <span><b>Frequently asked questions</b><small>Straight answers about the product, integrations, and status.</small></span>
        <?= icon('arrow-right', 'icon help-arrow') ?>
      </a>
      <a class="help-link" href="/contact">
        <span class="help-icon panel--green"><?= icon('envelope-simple') ?></span>
        <span><b>Contact</b><small>Send a question, feedback, or partnership enquiry to the team.</small></span>
        <?= icon('arrow-right', 'icon help-arrow') ?>
      </a>
    </div>
  </div>
</section>

<section class="section resources-sec" aria-labelledby="res-title">
  <div class="container">
    <p class="eyebrow eyebrow--muted">Resources</p>
    <h2 class="h-section res-title" id="res-title">Practical guides for everyday social work.</h2>
    <?php partial('partials/resource-bento', ['articles' => $articles]); ?>
  </div>
</section>

<section class="section about-sec" aria-labelledby="about-title">
  <div class="container">
    <div class="about-head">
      <div>
        <p class="eyebrow eyebrow--muted">About atwebtech</p>
        <h2 class="h-section" id="about-title">Built to make everyday social work simpler.</h2>
        <p class="about-text">atwebtech is being built for the people who plan, write, and look after social content, often alongside many other jobs. Our focus is clarity and organization: one calm place to see what is planned, what is in progress, and what comes next.</p>
      </div>
      <a class="btn btn--outline" href="/about">More about atwebtech<?= icon('arrow-right') ?></a>
    </div>
    <?php partial('partials/principles'); ?>
  </div>
</section>

<section class="section faq-sec" id="faq" aria-labelledby="faq-title">
  <div class="container faq-grid">
    <div>
      <h2 class="h-section" id="faq-title">Questions, answered.</h2>
      <p>Can't find what you are looking for? <a href="/contact">Contact the team</a>.</p>
    </div>
    <?php partial('partials/faq-list'); ?>
  </div>
</section>

<?php partial('partials/cta-final'); ?>
