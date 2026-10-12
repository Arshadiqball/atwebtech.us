<?php
/** @var array $articles */
$order = ['weekly-content-calendar', 'adapting-ideas-for-each-channel', 'choosing-social-metrics', 'getting-started'];
?>
<div class="bento">
<?php foreach ($order as $i => $slug): $a = $articles[$slug]; ?>
  <a class="bento-card bento-card--<?= $i + 1 ?> panel--<?= e($a['panel']) ?> reveal" href="/resources/<?= e($slug) ?>">
    <span class="bento-head">
      <span class="bento-title"><?= e($a['title']) ?></span>
      <?= icon('arrow-right', 'icon bento-arrow') ?>
    </span>
    <span class="bento-text"><?= e($a['summary']) ?></span>
    <span class="bento-art" aria-hidden="true">
<?php if ($slug === 'weekly-content-calendar'): ?>
      <span class="art-sheet art-week">
        <span class="art-sheet-head"><b>This week</b><span>3 posts</span></span>
<?php foreach (['Mon' => ['Something new', 'pink'], 'Tue' => null, 'Wed' => ['Conversation', 'yellow'], 'Thu' => null, 'Fri' => ['Something useful', 'green']] as $d => $slot): ?>
        <span class="art-row"><span class="art-day"><?= e($d) ?></span><?php if ($slot): ?><span class="art-slot art-slot--<?= e($slot[1]) ?>"><?= e($slot[0]) ?></span><?php else: ?><span class="art-slot art-slot--empty">Open</span><?php endif; ?></span>
<?php endforeach; ?>
      </span>
<?php elseif ($slug === 'adapting-ideas-for-each-channel'): ?>
      <span class="art-adapt">
        <span class="art-idea"><?= icon('lightbulb') ?>One idea: autumn mugs</span>
        <span class="art-variants">
          <span class="art-variant"><?= channel_icon('instagram', 'ch--xs') ?>Carousel</span>
          <span class="art-variant"><?= channel_icon('tiktok', 'ch--xs') ?>Kiln reveal</span>
          <span class="art-variant"><?= channel_icon('linkedin', 'ch--xs') ?>Lessons learned</span>
          <span class="art-variant"><?= channel_icon('threads', 'ch--xs') ?>Quick poll</span>
        </span>
      </span>
<?php elseif ($slug === 'choosing-social-metrics'): ?>
      <span class="art-sheet art-metrics">
        <span class="art-sheet-head"><b>Goal: community</b></span>
        <span class="art-metric"><span>Comments</span><span class="art-bar" style="--w: 82%"></span></span>
        <span class="art-metric"><span>Saves</span><span class="art-bar" style="--w: 64%"></span></span>
        <span class="art-metric"><span>Replies</span><span class="art-bar" style="--w: 48%"></span></span>
      </span>
<?php else: ?>
      <span class="art-sheet art-start">
        <span class="art-check"><?= icon('check-circle') ?>Explore the demo</span>
        <span class="art-check"><?= icon('check-circle') ?>Join the waitlist</span>
        <span class="art-check art-check--todo"><?= icon('clock') ?>Launch updates by email</span>
      </span>
<?php endif; ?>
    </span>
  </a>
<?php endforeach; ?>
</div>
