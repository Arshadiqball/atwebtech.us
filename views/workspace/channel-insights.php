<?php
/** @var array $user */
/** @var array $channel */
/** @var array $insights */
$cid = (int) $channel['id'];
$base = '/app/channels/' . $cid . '/insights';
?>
<div class="wsp">
  <header class="wsp-head ch-head">
    <div class="wsp-title">
      <?= channel_avatar($channel, 'md') ?>
      <div>
        <h1><?= e($channel['name']) ?>
          <a class="ws-icon-btn ws-icon-btn--sm" href="/app/channels/<?= $cid ?>/settings" title="Channel settings"><?= icon('gear') ?><span class="sr-only">Channel settings</span></a>
        </h1>
      </div>
    </div>
    <div class="wsp-actions">
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn" aria-expanded="false" aria-controls="in-export" data-menu-toggle><?= icon('arrow-square-out') ?>Export<?= icon('caret-down') ?></button>
        <div class="ws-menu ws-menu--end" id="in-export" hidden data-menu-panel>
<?php $q = http_build_query(array_filter(['range' => $insights['range'], 'from' => $_GET['from'] ?? null, 'to' => $_GET['to'] ?? null, 'channel' => $cid])); ?>
          <a class="ws-menu-item" href="/app/insights/export?<?= e($q) ?>&amp;format=csv"><?= icon('file-text') ?>Download CSV</a>
          <a class="ws-menu-item" href="/app/insights/export?<?= e($q) ?>&amp;format=report" target="_blank" rel="noopener"><?= icon('chart-bar') ?>PDF report</a>
<?php if (!Channels::allowance((int) $user['id'])['paid']): ?>
          <p class="ws-menu-note">Exports are part of Essentials and Team.</p>
<?php endif; ?>
        </div>
      </div>
    </div>
  </header>
<?php partial('workspace/insights-board', ['insights' => $insights, 'base' => $base, 'user' => $user, 'channel' => $channel]); ?>
</div>
