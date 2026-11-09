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
<?php $q = http_build_query(array_filter(['range' => $insights['range'], 'from' => $_GET['from'] ?? null, 'to' => $_GET['to'] ?? null])); ?>
          <a class="ws-menu-item" href="/app/insights/export?<?= e($q) ?>&amp;format=csv"><?= icon('file-text') ?>Download CSV</a>
          <a class="ws-menu-item" href="/app/insights/export?<?= e($q) ?>&amp;format=report" target="_blank" rel="noopener"><?= icon('chart-bar') ?>PDF report</a>
<?php if (!Channels::allowance((int) $user['id'])['paid']): ?>
          <p class="ws-menu-note">Exports are part of Essentials and Team.</p>
<?php endif; ?>
        </div>
      </div>
<?php $actions = (string) ob_get_clean();
partial('workspace/page-head', ['icon' => 'chart-bar', 'title' => 'Insights', 'actions' => $actions]);
partial('workspace/insights-board', ['insights' => $insights, 'base' => '/app/insights', 'user' => $user, 'channel' => null]);
?>
  <section class="tk" aria-labelledby="cmp-title">
    <header class="tk-head"><div><h2 id="cmp-title">Campaigns</h2><p>Every post tag is a campaign. Tag posts in the composer (for example "black-friday") to see them grouped here for <?= e(strtolower($insights['label'])) ?>.</p></div></header>
<?php if ($campaigns): ?>
    <div class="crm-table-wrap"><table class="cmp-table"><thead><tr><th>Campaign</th><th>Published</th><th>Scheduled</th><th>Failed</th><th>Drafts</th><th>Networks</th></tr></thead><tbody>
<?php foreach ($campaigns as $c): ?>
      <tr><td><b><?= e($c['name']) ?></b></td><td><?= (int) $c['sent'] ?></td><td><?= (int) $c['scheduled'] ?></td><td><?= (int) $c['failed'] ?></td><td><?= (int) $c['drafts'] ?></td><td><?= e(implode(', ', array_map(static fn ($n) => array_column(integrations(), 'name', 'slug')[$n] ?? $n, array_keys($c['networks'])))) ?></td></tr>
<?php endforeach; ?>
    </tbody></table></div>
<?php else: ?>
    <p class="wsp-muted">No tagged posts in this period yet.</p>
<?php endif; ?>
  </section>
</div>
