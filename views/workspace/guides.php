<?php
/** @var array $guides  From app/data/guides.php */
$minutes = static fn (int $s): string => $s < 60 ? $s . ' sec' : floor($s / 60) . ' min ' . ($s % 60 ? ($s % 60) . ' sec' : '');
$groups = [];
foreach ($guides as $i => $g) {
    $groups[$g['level']][] = $g + ['number' => $i + 1];
}
ob_start(); ?>
    <a class="ws-btn ws-btn--sm ws-btn--green" href="<?= e(asset('guides/atwebtech-user-guide.pdf')) ?>" download><?= icon('file-text') ?>Download all as PDF</a>
<?php $actions = (string) ob_get_clean(); ?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'book-open', 'title' => 'Guide', 'actions' => $actions]); ?>
  <div class="gd">
    <p class="gd-lead">Short videos and step-by-step instructions for everything in atwebtech. New here? Follow the first three in order and your first post will be scheduled in about ten minutes.</p>
<?php foreach ($groups as $level => $list): ?>
    <h2 class="gd-group"><?= e($level) ?></h2>
    <ul class="gd-grid" role="list">
<?php foreach ($list as $g): ?>
      <li class="gd-card">
        <a class="gd-thumb" href="/app/guide/<?= e($g['slug']) ?>" aria-label="Watch: <?= e($g['title']) ?>">
          <img src="<?= e(asset('guides/' . $g['slug'] . '/step-01.jpg')) ?>" alt="" loading="lazy" width="1280" height="720">
          <span class="gd-play" aria-hidden="true"><?= icon('play') ?></span>
          <span class="gd-time"><?= e(trim($minutes((int) $g['seconds']))) ?></span>
        </a>
        <div class="gd-body">
          <span class="gd-num">Guide <?= (int) $g['number'] ?></span>
          <h3><a href="/app/guide/<?= e($g['slug']) ?>"><?= e($g['title']) ?></a></h3>
          <p><?= e($g['summary']) ?></p>
          <div class="gd-links">
            <a href="/app/guide/<?= e($g['slug']) ?>"><?= icon('play') ?>Video</a>
            <a href="/app/guide/<?= e($g['slug']) ?>#steps"><?= icon('list') ?><?= count($g['steps']) ?> steps</a>
            <a href="<?= e(asset('guides/' . $g['slug'] . '.pdf')) ?>" download><?= icon('file-text') ?>PDF</a>
          </div>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endforeach; ?>
    <p class="gd-help"><?= icon('lifebuoy') ?>Still stuck? <a href="/contact">Write to us</a> and a person will answer.</p>
  </div>
</div>
