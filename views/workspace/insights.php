<?php
/** @var array $user */
/** @var array $insights */
?>
<div class="wsp">
<?php
ob_start(); ?>
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn" aria-expanded="false" aria-controls="in-export" data-menu-toggle><?= icon('arrow-square-out') ?>Export<?= icon('caret-down') ?></button>
        <div class="ws-menu ws-menu--end" id="in-export" hidden data-menu-panel>
          <span class="ws-menu-item is-disabled" aria-disabled="true">CSV</span>
          <span class="ws-menu-item is-disabled" aria-disabled="true">PDF report</span>
          <p class="ws-menu-note">Export isn’t built yet.</p>
        </div>
      </div>
<?php $actions = (string) ob_get_clean();
partial('workspace/page-head', ['icon' => 'chart-bar', 'title' => 'Insights', 'actions' => $actions]);
partial('workspace/insights-board', ['insights' => $insights, 'base' => '/app/insights', 'user' => $user, 'channel' => null]);
?>
</div>
