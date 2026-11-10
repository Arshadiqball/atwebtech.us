<?php
$site = data('site');
$channels = integrations();
$available = array_values(array_filter($channels, static fn ($c) => $c['status'] === 'available'));
$articles = data('articles');
$pricing = data('pricing');
$yearlyRate = static fn (string $plan): string => (string) round($pricing['plans'][$plan]['rate'] * $pricing['yearly_months'] / 12, 2);
$newNames = array_map(static fn ($c) => $c['name'], $available);
$nameList = count($newNames) > 1 ? implode(', ', array_slice($newNames, 0, -1)) . ' and ' . end($newNames) : implode('', $newNames);
?>
<section class="hero hero--launch" aria-labelledby="hero-title">
  <div class="aurora" aria-hidden="true"><span class="aurora-blob a1"></span><span class="aurora-blob a2"></span><span class="aurora-blob a3"></span></div>
<?php partial('partials/hero-tiles'); ?>
  <div class="container hero-inner">
<?php if ($newNames): ?>
    <a class="announce" href="/integrations">
      <span class="announce-tag">New</span>
      <span><?= count($newNames) > 3 ? 'Now publishing to ' . count($newNames) . ' networks, including ' . e(implode(', ', array_slice($newNames, 0, 3))) : 'Publish to ' . e($nameList) . ' from atwebtech' ?></span>
      <?= icon('arrow-right') ?>
    </a>
<?php endif; ?>
    <h1 class="h-hero" id="hero-title">Plan, schedule, and publish your social content <span class="grad">in one place.</span></h1>
    <p class="hero-sub">atwebtech gives creators and small teams a visual calendar, an idea board, and an AI writing assistant, then publishes your posts on time.</p>
    <div class="hero-ctas">
      <a class="btn btn--primary btn--lg" href="/signup">Start free<?= icon('arrow-right') ?></a>
      <a class="btn btn--glass btn--lg" href="/demo"><?= icon('play') ?>Try the live demo</a>
    </div>
    <ul class="trust-row" role="list">
      <li><?= icon('check-circle') ?>Free plan, no time limit</li>
      <li><?= icon('check-circle') ?>No credit card required</li>
      <li><?= icon('check-circle') ?>Set up in minutes</li>
    </ul>
  </div>
  <div class="container hero-visual">
    <div class="stage-3d" data-tilt-scroll>
      <div class="stage-3d-inner">
        <?php partial('demos/hero-mockup'); ?>
      </div>
      <div class="stage-glow" aria-hidden="true"></div>
    </div>
  </div>
</section>

<section class="logos-band" aria-labelledby="logos-title">
  <div class="container">
    <h2 class="logos-title" id="logos-title">One workspace for the networks your audience uses</h2>
  </div>
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track">
<?php for ($i = 0; $i < 2; $i++): foreach ($channels as $ch): ?>
      <span class="marquee-item"><span class="marquee-mark" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span><?= e($ch['name']) ?></span>
<?php endforeach; endfor; ?>
    </div>
  </div>
  <p class="logos-note container"><a class="link-arrow" href="/integrations">See which channels are live<?= icon('arrow-right') ?></a></p>
</section>

<section class="section steps-sec" aria-labelledby="steps-title">
  <div class="container">
    <div class="sec-head">
      <p class="eyebrow">How it works</p>
      <h2 class="h-section" id="steps-title">From idea to published post in three steps.</h2>
    </div>
    <ol class="steps" role="list">
      <li class="step reveal">
        <span class="step-num">1</span>
        <span class="step-icon"><?= icon('share-network') ?></span>
        <h3 class="h-small">Connect your channels</h3>
        <p>Sign in with your social accounts and pick the profiles you want to manage. Your access tokens are encrypted.</p>
      </li>
      <li class="step reveal">
        <span class="step-num">2</span>
        <span class="step-icon"><?= icon('magic-wand') ?></span>
        <h3 class="h-small">Plan and write</h3>
        <p>Capture ideas on a board, draft with the AI assistant, and tailor each post to the channel it's going to.</p>
      </li>
      <li class="step reveal">
        <span class="step-num">3</span>
        <span class="step-icon"><?= icon('paper-plane-tilt') ?></span>
        <h3 class="h-small">Schedule and relax</h3>
        <p>Drop posts into your queue. atwebtech publishes them at your best times, and retries if a network hiccups.</p>
      </li>
    </ol>
  </div>
</section>

<section class="section core" aria-labelledby="core-title">
  <div class="container">
    <div class="sec-head">
      <p class="eyebrow">Features</p>
      <h2 class="h-section" id="core-title">Everything you need to show up consistently.</h2>
    </div>
  </div>
  <div class="container core-grid">
    <article class="core-card core-card--wide reveal" data-tilt aria-labelledby="f-publishing">
      <div class="core-panel panel--pink"><?php partial('demos/calendar'); ?></div>
      <div class="core-body core-body--split">
        <div>
          <p class="eyebrow">Publishing</p>
          <h3 class="h-card" id="f-publishing">Fill your calendar once. Post all week.</h3>
        </div>
        <div>
          <p>Plan posts on a clear calendar, tailor them for each channel, and let your posting schedule fill the queue with the right times.</p>
          <a class="link-arrow" href="/features/publishing">Explore publishing<?= icon('arrow-right') ?></a>
        </div>
      </div>
    </article>

    <article class="core-card reveal" data-tilt aria-labelledby="f-content">
      <div class="core-panel panel--green"><?php partial('demos/idea-board'); ?></div>
      <div class="core-body">
        <p class="eyebrow">Content planning</p>
        <h3 class="h-card" id="f-content">Never start from a blank page.</h3>
        <p>Save inspiration the moment it hits, shape drafts on a board, and turn any idea into a scheduled post in one click.</p>
        <a class="link-arrow" href="/features/content-planner">Explore content planning<?= icon('arrow-right') ?></a>
      </div>
    </article>

    <article class="core-card reveal" data-tilt aria-labelledby="f-community">
      <div class="core-panel panel--yellow"><?php partial('demos/inbox'); ?></div>
      <div class="core-body">
        <p class="eyebrow">Community</p>
        <h3 class="h-card" id="f-community">Every conversation in one inbox.</h3>
        <p>Find unanswered comments, reply from one place, and let AI suggest a reply in your brand's voice.</p>
        <a class="link-arrow" href="/features/community">Explore the inbox<?= icon('arrow-right') ?></a>
      </div>
    </article>

    <article class="core-card core-card--wide reveal" data-tilt aria-labelledby="f-analytics">
      <div class="core-panel panel--blue"><?php partial('demos/analytics'); ?></div>
      <div class="core-body core-body--split">
        <div>
          <p class="eyebrow">Analytics</p>
          <h3 class="h-card" id="f-analytics">See what's working, then do more of it.</h3>
        </div>
        <div>
          <p>Track what you've published, compare posts side by side, and decide what to create next with clear reports.</p>
          <a class="link-arrow" href="/features/analytics">Explore analytics<?= icon('arrow-right') ?></a>
        </div>
      </div>
    </article>
  </div>
</section>

<section class="section ai-sec" aria-labelledby="ai-title">
  <div class="container">
    <div class="ai-card">
      <div class="ai-copy">
        <p class="eyebrow eyebrow--light">AI writing assistant</p>
        <h2 class="h-section" id="ai-title">Write a week of posts in minutes.</h2>
        <p>Give the assistant a short brief and get a draft in your tone, ready to edit. It leaves placeholders instead of making up facts, and nothing is posted without your OK.</p>
        <ul class="ai-steps" role="list">
          <li><b>Suggest:</b> post ideas around a product, a season, or a question.</li>
          <li><b>Refine:</b> a caption until the tone and length feel right.</li>
          <li><b>Adapt:</b> one idea into versions for each channel.</li>
        </ul>
        <div class="btn-row">
          <a class="btn btn--green btn--square" href="/signup">Try it free<?= icon('arrow-right') ?></a>
          <a class="btn btn--ghost-light btn--square" href="/features/ai-assistant">How it works<?= icon('arrow-right') ?></a>
        </div>
      </div>
      <div class="ai-visual"><?php partial('demos/ai-chat'); ?></div>
    </div>
  </div>
</section>

<section class="section why-sec" aria-labelledby="why-title">
  <div class="container">
    <div class="sec-head">
      <p class="eyebrow">Why atwebtech</p>
      <h2 class="h-section" id="why-title">Built for people who have more than one job.</h2>
    </div>
    <div class="why-grid">
<?php
$why = [
    ['clock', 'Batch your week', 'Plan and schedule a week of content in one sitting, then get back to running your business.'],
    ['calendar-blank', 'Your best times, automatically', 'Set a posting goal per channel and atwebtech suggests the time slots for you.'],
    ['shield-check', 'Secure by default', 'Two-factor sign-in, encrypted channel tokens, and we never sell your data.'],
    ['tag', 'Fair, per-channel pricing', 'Start free. Upgrade only for the channels you manage, and pay less per channel as you grow.'],
];
foreach ($why as [$ic, $title, $text]): ?>
      <div class="why-card reveal">
        <span class="why-icon"><?= icon($ic) ?></span>
        <h3 class="h-small"><?= e($title) ?></h3>
        <p><?= e($text) ?></p>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section audience" aria-labelledby="audience-title">
  <div class="container">
    <h2 class="rule-title" id="audience-title"><span>Made for the people behind the posts</span></h2>
    <ul class="audience-cards" role="list">
<?php foreach ($site['solutions'] as $s): ?>
      <li>
        <a class="audience-card" href="<?= e($s['href']) ?>">
          <span class="audience-badge"><?= icon($s['icon']) ?></span>
          <b><?= e($s['label']) ?></b>
          <small><?= e($s['desc']) ?></small>
          <?= icon('arrow-right', 'icon audience-arrow') ?>
        </a>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section plans-sec" aria-labelledby="plans-title">
  <div class="container">
    <div class="sec-head">
      <p class="eyebrow">Pricing</p>
      <h2 class="h-section" id="plans-title">Simple pricing that grows with you.</h2>
      <p class="sec-sub">Start free with up to <?= e((string) $pricing['free_channels']) ?> channels. Paid plans are priced per channel, so you only pay for what you use.</p>
    </div>
    <div class="plan-teasers">
      <div class="plan-teaser reveal">
        <h3 class="h-small">Free</h3>
        <p class="pt-price"><b>$0</b><span>forever</span></p>
        <p>Up to <?= e((string) $pricing['free_channels']) ?> channels, 10 scheduled posts each, and 100 ideas.</p>
        <a class="btn btn--outline btn--block" href="/signup">Start free</a>
      </div>
      <div class="plan-teaser plan-teaser--hot reveal">
        <span class="pt-badge">Recommended</span>
        <h3 class="h-small">Essentials</h3>
        <p class="pt-price"><b>$<?= e($yearlyRate('essentials')) ?></b><span>per channel / month, billed yearly</span></p>
        <p>Unlimited posts and ideas, advanced analytics, hashtag manager, and first comments.</p>
        <a class="btn btn--primary btn--block" href="/pricing?plan=essentials">See Essentials</a>
      </div>
      <div class="plan-teaser reveal">
        <h3 class="h-small">Team</h3>
        <p class="pt-price"><b>$<?= e($yearlyRate('team')) ?></b><span>per channel / month, billed yearly</span></p>
        <p>Everything in Essentials, plus up to 50 teammates with roles, channel access levels, and approvals.</p>
        <a class="btn btn--outline btn--block" href="/pricing?plan=team">See Team</a>
      </div>
    </div>
    <p class="text-center plans-more"><a class="link-arrow" href="/pricing">Compare all plans and features<?= icon('arrow-right') ?></a></p>
  </div>
</section>

<section class="section resources-sec" aria-labelledby="res-title">
  <div class="container">
    <p class="eyebrow eyebrow--muted">Resources</p>
    <h2 class="h-section res-title" id="res-title">Practical guides for everyday social work.</h2>
    <?php partial('partials/resource-bento', ['articles' => $articles]); ?>
  </div>
</section>

<section class="section faq-sec" id="faq" aria-labelledby="faq-title">
  <div class="container faq-grid">
    <div>
      <h2 class="h-section" id="faq-title">Questions, answered.</h2>
      <p>Can't find what you're looking for? <a href="/contact">Talk to our team</a>.</p>
    </div>
    <?php partial('partials/faq-list'); ?>
  </div>
</section>

<?php partial('partials/cta-final'); ?>
