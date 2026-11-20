<?php
/** @var array $guide */
/** @var array|null $previous */
/** @var array|null $next */
/** @var string $home  Where the guide pages live: /app/guide in the panel, /guide on the website */
$base = 'guides/' . $guide['slug'];
$rich = static fn (string $text): string => preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($text));
?>
    <video class="gd-video" controls playsinline preload="metadata" poster="<?= e(asset($base . '/poster.jpg')) ?>" width="1280" height="720">
      <source src="<?= e(asset($base . '/video.mp4')) ?>" type="video/mp4">
      Your browser can’t play this video. <a href="<?= e(asset($base . '/video.mp4')) ?>">Download it</a>.
    </video>
    <p class="gd-note">The video has a voice-over. Every step is also written out below.</p>

    <h3 class="gd-group" id="steps">Step by step</h3>
    <ol class="gd-steps" role="list">
<?php foreach ($guide['steps'] as $i => $step): ?>
      <li>
        <span class="gd-n" aria-hidden="true"><?= $i + 1 ?></span>
        <div>
          <h4><?= e($step['title']) ?></h4>
          <p><?= $rich($step['text']) ?></p>
          <a href="<?= e(asset($base . '/' . $step['image'])) ?>" target="_blank" rel="noopener"><img src="<?= e(asset($base . '/' . $step['image'])) ?>" alt="Screen for step <?= $i + 1 ?>: <?= e($step['title']) ?>" loading="lazy" width="1280" height="720"></a>
        </div>
      </li>
<?php endforeach; ?>
    </ol>

    <nav class="gd-next" aria-label="More guides">
<?php if ($previous): ?>
      <a href="<?= e($home) ?>/<?= e($previous['slug']) ?>"><small><?= icon('arrow-left') ?>Previous</small><b><?= e($previous['title']) ?></b></a>
<?php else: ?>
      <span></span>
<?php endif; ?>
<?php if ($next): ?>
      <a class="is-next" href="<?= e($home) ?>/<?= e($next['slug']) ?>"><small>Next<?= icon('arrow-right') ?></small><b><?= e($next['title']) ?></b></a>
<?php endif; ?>
    </nav>
