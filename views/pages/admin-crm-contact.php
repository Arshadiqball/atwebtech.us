<?php
/** @var array $user */
/** @var array $c  Contact from Crm::contacts() */
/** @var list<array> $timeline */
/** @var list<array> $followups */
$token = Security::csrfToken();
$self = '/admin/crm/contacts/' . (int) $c['id'];
$notice = flash('notice');
$u = $c['user'];
$sub = $c['subscription'];
$tz = Crm::tz();
$tomorrow = (new DateTimeImmutable('tomorrow', $tz))->format('Y-m-d');
$mailto = 'mailto:' . rawurlencode((string) $c['email']) . '?subject=' . rawurlencode('Quick question about atwebtech');
$facts = array_filter([
    'Email' => $c['email'],
    'Source' => $c['source'] . ($c['campaign'] !== '' ? ' · ' . $c['campaign'] : ''),
    'First seen' => Crm::local((string) $c['first_seen_at']),
    'Account created' => $u ? Crm::local((string) $u['created_at']) : 'No account yet',
    'Last sign-in' => $u && $u['last_login_at'] ? Crm::local((string) $u['last_login_at']) : null,
    'Channels' => $u ? (string) $c['channels'] : null,
    'Posts' => $u ? $c['posts'] . ' created, ' . $c['sent'] . ' published' : null,
    'Plan' => $sub ? Admin::plan((string) $sub['plan']) . ' · ' . $sub['channels'] . ' channels · ' . $sub['billing'] . ' · ' . $sub['status'] : ($u ? 'Free' : null),
    'Revenue' => $c['mrr'] > 0 ? '$' . number_format($c['mrr'], 2) . ' per month' : null,
    'Waitlist plan interest' => $c['waitlist'] && $c['waitlist']['plan'] ? Admin::plan((string) $c['waitlist']['plan']) : null,
    'Messages sent' => $c['enquiries'] > 0 ? (string) $c['enquiries'] : null,
    'Last contacted' => $c['last_contacted_at'] ? Crm::local((string) $c['last_contacted_at']) : null,
]);
?>
<div class="adm crm" id="main" tabindex="-1">
  <?php partial('partials/admin-bar', ['user' => $user, 'section' => 'contacts']); ?>
  <div class="crm-wrap">
    <a class="crm-link crm-backlink" href="/admin/crm/contacts"><?= icon('arrow-left') ?>All contacts</a>
    <header class="crm-head crm-person">
      <div class="crm-person-id">
        <span class="crm-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) ($c['name'] ?: $c['email']), 0, 1))) ?></span>
        <div>
          <h1><?= e($c['name'] ?: $c['email']) ?></h1>
          <p><span class="crm-stage crm-stage--<?= e($c['stage']) ?>"><?= e(Crm::STAGES[$c['stage']]) ?></span> <?= e($c['source']) ?></p>
        </div>
      </div>
      <div class="crm-actions">
        <a class="crm-btn crm-btn--dark" href="<?= e($mailto) ?>"><?= icon('envelope-simple') ?>Email</a>
        <form method="post" action="/admin/crm/action" class="crm-inline">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="contact" value="<?= (int) $c['id'] ?>">
          <input type="hidden" name="action" value="status">
          <label class="sr-only" for="crm-status">Sales status</label>
          <select class="crm-input" id="crm-status" name="status" data-autosubmit>
<?php foreach (Crm::STATUSES as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $c['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
          </select>
          <button class="crm-btn" type="submit">Set status</button>
        </form>
      </div>
    </header>
<?php if (is_string($notice) && $notice !== ''): ?>
    <p class="crm-notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>

    <div class="crm-grid crm-grid--detail">
      <div class="crm-col-main">
        <section class="crm-card" aria-labelledby="crm-log">
          <h2 id="crm-log">Log an activity</h2>
          <form method="post" action="/admin/crm/action" class="crm-note-form">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="contact" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="action" value="note">
            <div class="crm-kinds" role="radiogroup" aria-label="Type">
<?php foreach (Crm::NOTE_KINDS as $k => $label): ?>
              <label><input type="radio" name="kind" value="<?= e($k) ?>"<?= $k === 'note' ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
<?php endforeach; ?>
            </div>
            <textarea class="crm-input" name="body" rows="3" maxlength="5000" placeholder="What happened? Calls, emails, and meetings also mark the contact as contacted." required></textarea>
            <button class="crm-btn crm-btn--dark" type="submit">Save to timeline</button>
          </form>
        </section>

        <section class="crm-card" aria-labelledby="crm-timeline">
          <h2 id="crm-timeline">Timeline</h2>
<?php if ($timeline): ?>
          <ol class="crm-timeline" role="list">
<?php foreach ($timeline as $ev): ?>
            <li class="<?= ($ev[4] ?? '') === 'note' ? 'is-note' : '' ?>">
              <span class="crm-tl-icon"><?= icon($ev[1]) ?></span>
              <div>
                <b><?= e($ev[2]) ?></b>
<?php if ($ev[3] !== ''): ?><p><?= nl2br(e($ev[3])) ?></p><?php endif; ?>
                <time><?= e(Crm::local((string) $ev[0])) ?></time>
              </div>
            </li>
<?php endforeach; ?>
          </ol>
<?php else: ?>
          <p class="crm-empty">No activity yet.</p>
<?php endif; ?>
        </section>
      </div>

      <aside class="crm-col-side">
        <section class="crm-card" aria-labelledby="crm-fu">
          <h2 id="crm-fu">Follow-ups</h2>
<?php foreach ($followups as $f): $late = $f['due_at'] < gmdate('Y-m-d H:i:s'); ?>
          <div class="crm-fu">
            <div><b><?= e($f['title']) ?></b><span class="crm-due<?= $late ? ' is-late' : '' ?>"><?= $late ? 'Overdue · ' : '' ?><?= e(Crm::local($f['due_at'], 'D j M, g:i A')) ?></span></div>
            <form method="post" action="/admin/crm/action">
              <input type="hidden" name="_token" value="<?= e($token) ?>">
              <input type="hidden" name="contact" value="<?= (int) $c['id'] ?>">
              <input type="hidden" name="followup" value="<?= (int) $f['id'] ?>">
              <button class="crm-btn crm-btn--sm" name="action" value="done"><?= icon('check') ?>Done</button>
            </form>
          </div>
<?php endforeach; ?>
          <form method="post" action="/admin/crm/action" class="crm-fu-form">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="contact" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="action" value="followup">
            <input type="hidden" name="tz" value="<?= e($tz->getName()) ?>">
            <input class="crm-input" name="title" maxlength="200" placeholder="e.g. Call about Team plan" required>
            <div class="crm-fu-when">
              <input class="crm-input" type="date" name="date" value="<?= e($tomorrow) ?>" required aria-label="Date">
              <input class="crm-input" type="time" name="time" value="10:00" aria-label="Time">
            </div>
            <button class="crm-btn crm-btn--dark" type="submit"><?= icon('plus') ?>Schedule follow-up</button>
            <small class="crm-fine">Times are <?= e($tz->getName()) ?>.</small>
          </form>
        </section>

        <section class="crm-card" aria-labelledby="crm-facts">
          <h2 id="crm-facts">Details</h2>
          <dl class="crm-facts">
<?php foreach ($facts as $label => $value): ?>
            <dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd>
<?php endforeach; ?>
          </dl>
          <form method="post" action="/admin/crm/action" class="crm-tags">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="contact" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="action" value="tags">
            <label for="crm-tags-in">Tags</label>
            <input class="crm-input" id="crm-tags-in" name="tags" value="<?= e((string) ($c['tags'] ?? '')) ?>" placeholder="agency, hot lead, demo booked">
            <button class="crm-btn crm-btn--sm" type="submit">Save tags</button>
          </form>
        </section>
      </aside>
    </div>
  </div>
</div>
