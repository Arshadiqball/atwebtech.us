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
          <span class="ws-menu-item is-disabled" aria-disabled="true">CSV</span>
          <span class="ws-menu-item is-disabled" aria-disabled="true">PDF report</span>
          <p class="ws-menu-note">Export isn’t built yet.</p>
        </div>
      </div>
    </div>
  </header>
<?php partial('workspace/insights-board', ['insights' => $insights, 'base' => $base, 'user' => $user, 'channel' => $channel]); ?>
</div>
