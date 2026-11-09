<?php
/** @var array $user */
/** @var list<array> $rows */
/** @var array $q */
$sources = array_values(array_unique(array_map(static fn ($c) => $c['source'], Crm::contacts())));
sort($sources);
$val = static fn (string $k): string => (string) ($q[$k] ?? '');
$exportQ = http_build_query(array_filter(['stage' => $val('stage'), 'status' => $val('status'), 'source' => $val('source'), 'q' => $val('q'), 'export' => 'csv']));
$page = max(1, (int) ($q['page'] ?? 1));
$per = 50;
$total = count($rows);
$rows = array_slice($rows, ($page - 1) * $per, $per);
$pageUrl = static fn (int $p): string => '/admin/crm/contacts?' . http_build_query(array_filter(['stage' => $val('stage'), 'status' => $val('status'), 'source' => $val('source'), 'q' => $val('q'), 'page' => $p > 1 ? $p : null]));
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'contacts']); ?>
  <div class="crm-wrap">
    <header class="crm-head">
      <div>
        <h1>Contacts</h1>
        <p><?= number_format($total) ?> <?= $total === 1 ? 'contact' : 'contacts' ?><?= array_filter([$val('stage'), $val('status'), $val('source'), $val('q')]) ? ' match these filters' : '' ?></p>
      </div>
      <a class="crm-btn" href="/admin/crm/contacts?<?= e($exportQ) ?>"><?= icon('arrow-down') ?>Export CSV</a>
    </header>

    <form class="crm-filters" method="get" action="/admin/crm/contacts">
      <input class="crm-input" type="search" name="q" value="<?= e($val('q')) ?>" placeholder="Search email, name, campaign, tag" aria-label="Search">
      <select class="crm-input" name="stage" aria-label="Stage">
        <option value="">All stages</option>
<?php foreach (Crm::STAGES as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $val('stage') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
      </select>
      <select class="crm-input" name="status" aria-label="Sales status">
        <option value="">Any status</option>
<?php foreach (Crm::STATUSES as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $val('status') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
      </select>
      <select class="crm-input" name="source" aria-label="Source">
        <option value="">All sources</option>
<?php foreach ($sources as $s): ?>
        <option<?= $val('source') === $s ? ' selected' : '' ?>><?= e($s) ?></option>
<?php endforeach; ?>
      </select>
      <button class="crm-btn crm-btn--dark" type="submit">Filter</button>
<?php if (array_filter([$val('stage'), $val('status'), $val('source'), $val('q')])): ?>
      <a class="crm-link" href="/admin/crm/contacts">Clear</a>
<?php endif; ?>
    </form>

<?php if ($rows): ?>
    <div class="crm-table-wrap crm-card">
      <table class="crm-table crm-table--contacts">
        <thead><tr><th>Contact</th><th>Stage</th><th>Status</th><th>Source</th><th>Usage</th><th>Plan</th><th>First seen</th><th>Next follow-up</th></tr></thead>
        <tbody>
<?php foreach ($rows as $c): ?>
          <tr>
            <td><a class="crm-who" href="/admin/crm/contacts/<?= (int) $c['id'] ?>"><b><?= e($c['name'] ?: $c['email']) ?></b><?php if ($c['name']): ?><small><?= e($c['email']) ?></small><?php endif; ?></a></td>
            <td><span class="crm-stage crm-stage--<?= e($c['stage']) ?>"><?= e(Crm::STAGES[$c['stage']]) ?></span></td>
            <td><span class="crm-status crm-status--<?= e($c['status']) ?>"><?= e(Crm::STATUSES[$c['status']] ?? $c['status']) ?></span></td>
            <td><?= e($c['source']) ?><?php if ($c['campaign'] !== ''): ?><small><?= e($c['campaign']) ?></small><?php endif; ?></td>
            <td><?= $c['user'] ? $c['channels'] . ' ch · ' . $c['posts'] . ' posts' : '<small>No account</small>' ?></td>
            <td><?= $c['subscription'] ? e(Admin::plan((string) $c['subscription']['plan'])) . ($c['mrr'] > 0 ? ' <small>$' . number_format($c['mrr'], 2) . '/mo</small>' : ' <small>' . e($c['subscription']['status']) . '</small>') : '<small>Free</small>' ?></td>
            <td><?= e(Crm::local((string) $c['first_seen_at'], 'j M Y')) ?></td>
            <td><?= $c['next_due'] ? '<span class="crm-due' . ($c['next_due'] < gmdate('Y-m-d H:i:s') ? ' is-late' : '') . '">' . e(Crm::local($c['next_due'], 'j M')) . '</span>' : '<small>—</small>' ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php if ($total > $per): ?>
    <nav class="crm-pages" aria-label="Pages">
<?php if ($page > 1): ?><a class="crm-btn" href="<?= e($pageUrl($page - 1)) ?>">Previous</a><?php endif; ?>
      <span>Page <?= $page ?> of <?= (int) ceil($total / $per) ?></span>
<?php if ($page * $per < $total): ?><a class="crm-btn" href="<?= e($pageUrl($page + 1)) ?>">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?php else: ?>
    <p class="crm-empty crm-card">No contacts match. Try clearing a filter.</p>
<?php endif; ?>
  </div>
</div>
