<?php
/** @var array $user */
/** @var array $channel */
/** @var string $tab */
$token = Security::csrfToken();
$cid = (int) $channel['id'];
$self = '/app/channels/' . $cid;
$settings = $self . '/settings';
$provider = array_column(integrations(), null, 'slug')[$channel['provider']] ?? ['name' => $channel['provider']];
$tz = Workspace::timezone($user);
$goal = max(1, (int) $channel['posting_goal']);
$goalState = View::formState('goal');
$scheduleState = View::formState('schedule');
$byDay = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];
foreach ($channel['times'] as $t) {
    $byDay[(int) $t['day']][] = $t['time'];
}
$week = [7 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
$linkedin = $channel['provider'] === 'linkedin';
?>
<div class="wsp set">
  <header class="set-head">
    <a class="ws-icon-btn" href="<?= e($self) ?>"><?= icon('arrow-left') ?><span class="sr-only">Back to <?= e($channel['name']) ?></span></a>
    <div class="wsp-title">
      <?= channel_avatar($channel, 'md') ?>
      <div>
        <h1><?= e($channel['name']) ?></h1>
        <p class="set-kicker">Channel Settings</p>
      </div>
    </div>
  </header>

  <nav class="set-tabs" aria-label="Channel settings">
    <a href="<?= e($settings) ?>"<?= $tab === 'schedule' ? ' aria-current="page"' : '' ?>>Posting Schedule</a>
    <a href="<?= e($settings) ?>?tab=general"<?= $tab === 'general' ? ' aria-current="page"' : '' ?>>General</a>
  </nav>

<?php if ($tab === 'schedule'): ?>
  <section class="set-block">
    <div class="set-row">
      <div>
        <h2>Time Zone</h2>
        <p>Time zone used for the queue on every channel. This is your workspace time zone, not a separate one per channel.</p>
      </div>
      <form class="set-control set-tz" action="/account/timezone" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="return" value="<?= e($settings) ?>">
        <label class="sr-only" for="set-tz">Time zone</label>
        <span class="set-tz-ico" aria-hidden="true"><?= icon('globe') ?></span>
        <select class="idea-input" id="set-tz" name="timezone" data-auto-submit>
<?php foreach (DateTimeZone::listIdentifiers() as $name): ?>
          <option<?= $name === $tz->getName() ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="ws-btn ws-btn--sm">Save</button></noscript>
      </form>
    </div>
  </section>

  <section class="set-block">
    <div class="set-row">
      <div>
        <h2>Posting Goal</h2>
        <p>How often you aim to post on this channel each week. Changing the goal fills the calendar with suggested times.</p>
      </div>
      <form class="set-step" action="<?= e($self) ?>/goal" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="return" value="<?= e($settings) ?>">
        <button type="submit" name="goal" value="<?= max(1, $goal - 1) ?>"<?= $goal <= 1 ? ' disabled' : '' ?> aria-label="Fewer posts"><?= icon('minus') ?></button>
        <span aria-live="polite"><?= $goal ?></span>
        <button type="submit" name="goal" value="<?= min(Channels::MAX_GOAL, $goal + 1) ?>"<?= $goal >= Channels::MAX_GOAL ? ' disabled' : '' ?> aria-label="More posts"><?= icon('plus') ?></button>
      </form>
    </div>
<?php if ($goalState): ?><p class="composer-error" role="alert"><?= e($goalState['message']) ?></p><?php endif; ?>
  </section>

  <section class="set-block" aria-labelledby="set-slots">
    <div class="set-row set-row--stack">
      <div>
        <h2 id="set-slots">Posting Slots</h2>
        <p>Your posting times tell atwebtech when to send posts in your Queue. The next posts you add use the next upcoming times you choose below, in <?= e(Workspace::timezoneLabel($tz)) ?> time.</p>
      </div>
      <form action="<?= e($self) ?>/goal" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="return" value="<?= e($settings) ?>">
        <input type="hidden" name="goal" value="<?= $goal ?>">
        <button type="submit" class="ws-btn ws-btn--green set-gen"><?= icon('sparkle') ?>Generate New Posting Slots</button>
      </form>
    </div>
<?php if ($scheduleState): ?><p class="composer-error" role="alert"><?= e($scheduleState['message']) ?></p><?php endif; ?>

    <form class="set-cal" action="<?= e($self) ?>/schedule" method="post" data-slots>
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="return" value="<?= e($settings) ?>">
      <div class="set-week" role="table" aria-label="Posting times">
        <div class="set-week-head" role="row">
<?php foreach ($week as $label): ?>
          <span role="columnheader"><?= e($label) ?></span>
<?php endforeach; ?>
        </div>
        <div class="set-week-body" role="row">
<?php foreach ($week as $d => $label): $times = $byDay[$d]; ?>
          <div class="set-day<?= $times ? ' is-on' : '' ?>" data-day="<?= $d ?>">
            <label class="set-switch">
              <input type="checkbox"<?= $times ? ' checked' : '' ?> data-day-on="<?= $d ?>" aria-label="<?= e($label) ?> posting times">
              <span aria-hidden="true"></span>
            </label>
            <div class="set-day-times" data-day-times="<?= $d ?>">
<?php foreach ($times ?: [''] as $time): ?>
              <input type="time" name="times[<?= $d ?>][]" value="<?= e($time) ?>"<?= $times ? '' : ' disabled' ?> aria-label="<?= e($label) ?> time">
<?php endforeach; ?>
            </div>
          </div>
<?php endforeach; ?>
        </div>
      </div>

      <div class="set-add">
        <span>Add a new posting time for</span>
        <label><span class="sr-only">Day</span>
          <select class="idea-input" data-slot-day>
            <option value="0">Every day</option>
<?php foreach ($week as $d => $label): ?>
            <option value="<?= $d ?>"><?= e($label) ?></option>
<?php endforeach; ?>
          </select>
        </label>
        <span>at</span>
        <label><span class="sr-only">Time</span><input class="idea-input" type="time" value="10:30" data-slot-time></label>
        <button type="button" class="ws-btn ws-btn--green" data-slot-add>Add Posting Slot</button>
        <button type="button" class="ws-btn set-clear" data-slot-clear><?= icon('trash') ?>Clear All</button>
        <noscript><button type="submit" class="ws-btn ws-btn--green">Save schedule</button></noscript>
      </div>
    </form>
  </section>

<?php else: ?>
  <section class="set-block">
    <div class="set-row">
      <div>
        <h2>Link Shortening <span class="ws-soon">Coming soon</span></h2>
        <p>A short link service isn’t connected. When it is, you can shorten links in posts from this channel. Nothing is shortened today.</p>
      </div>
      <select class="idea-input set-control" disabled aria-disabled="true"><option>No Shortening</option></select>
    </div>
    <div class="set-row">
      <div>
        <h2>Campaign tracking (UTM)</h2>
        <p>Add Google Analytics UTM tags to the links in your posts automatically. It's one setting for all channels, under <a href="/app/account#utm">Account → Link tracking</a>.</p>
      </div>
    </div>
  </section>

  <section class="set-block" id="bulk">
    <form class="set-row set-row--stack" action="/app/channels/<?= (int) $channel['id'] ?>/queue-tools" method="post" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
      <input type="hidden" name="action" value="bulk">
      <div>
        <h2>Bulk Post Upload</h2>
<?php if (in_array($channel['provider'], QueueTools::TEXT_NETWORKS, true)): ?>
        <p>Schedule up to <?= QueueTools::MAX_ROWS ?> posts at once from a CSV file with the columns <code>date</code> (YYYY-MM-DD), <code>time</code> (HH:MM, 24-hour, your time zone), <code>text</code>, and optionally <code>tags</code>. <a href="/assets/files/bulk-upload-template.csv" download>Download the template</a>.</p>
<?php $bulkForm = View::formState('bulk'); if ($bulkForm && $bulkForm['status'] === 'error'): ?>
        <p class="bill-warn"><?= icon('warning-circle') ?><span><?= e($bulkForm['message']) ?><?php if (!empty($bulkForm['errors']['rows'])): ?><br><?= nl2br(e($bulkForm['errors']['rows'])) ?><?php endif; ?></span></p>
<?php endif; ?>
        <input class="idea-input" type="file" name="csv" accept=".csv,text/csv" required style="margin-top:10px;padding-top:7px">
      </div>
      <button type="submit" class="ws-btn ws-btn--dark set-control"><?= icon('upload-simple') ?>Upload and schedule</button>
<?php else: ?>
        <p>Bulk upload is for text posts. This network needs a photo or video on every post, so add those one at a time from Create Post.</p>
      </div>
<?php endif; ?>
    </form>
  </section>

<?php if ($linkedin): ?>
  <section class="set-block">
    <div class="set-row">
      <div>
        <h2>Show atwebtech on your LinkedIn Profile <span class="ws-soon">Coming soon</span></h2>
        <p>atwebtech doesn’t write a featured section on your LinkedIn Profile. This toggle does nothing.</p>
      </div>
      <label class="set-switch is-disabled"><input type="checkbox" disabled><span aria-hidden="true"></span><span class="sr-only">Off</span></label>
    </div>
    <div class="set-row">
      <div>
        <h2>Check for LinkedIn skills <span class="ws-soon">Coming soon</span></h2>
        <p>atwebtech doesn’t read or suggest LinkedIn skills. Last checked: never.</p>
      </div>
      <button type="button" class="ws-btn set-control" disabled>Check for LinkedIn skills</button>
    </div>
  </section>
<?php endif; ?>

  <section class="set-block" id="queue-actions">
    <h2 class="set-group">Queue Actions</h2>
<?php $qa = static function (string $action, string $label, string $class = '', string $confirm = '') use ($channel): string {
    return '<form action="/app/channels/' . (int) $channel['id'] . '/queue-tools" method="post"><input type="hidden" name="_token" value="' . e(Security::csrfToken()) . '"><button type="submit" name="action" value="' . e($action) . '" class="ws-btn set-control ' . e($class) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . e($label) . '</button></form>';
}; ?>
    <form class="set-row" action="/app/channels/<?= (int) $channel['id'] ?>/queue-tools" method="post">
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
      <input type="hidden" name="action" value="pause">
      <div>
        <h2>Pause Queue<?php if (!empty($channel['paused_at'])): ?> <span class="ws-soon">Paused</span><?php endif; ?></h2>
        <p><?= !empty($channel['paused_at']) ? 'Nothing on this channel publishes while paused. Resume to send posts that are due.' : 'Stop every post on this channel from publishing until you resume. Nothing is deleted.' ?></p>
      </div>
      <button type="submit" name="paused" value="<?= empty($channel['paused_at']) ? '1' : '' ?>" class="ws-btn set-control"><?= empty($channel['paused_at']) ? 'Pause queue' : 'Resume queue' ?></button>
    </form>
    <div class="set-row">
      <div><h2>Shuffle Queue</h2><p>Mix up the order of scheduled posts. The time slots stay the same.</p></div>
      <?= $qa('shuffle', 'Shuffle queue') ?>
    </div>
    <div class="set-row">
      <div><h2>Clean Queue</h2><p>Remove every post on this channel that failed to publish.</p></div>
      <?= $qa('clean', 'Remove failed posts', '', 'Delete all failed posts on this channel?') ?>
    </div>
    <div class="set-row">
      <div><h2>Delete All Drafts</h2><p>Delete every draft for this channel. This can't be undone.</p></div>
      <?= $qa('drafts', 'Delete drafts', 'ws-btn--danger', 'Delete all drafts for this channel? This cannot be undone.') ?>
    </div>
    <div class="set-row">
      <div><h2>Empty Queue</h2><p>Remove every scheduled post and post waiting for approval on this channel. This can't be undone.</p></div>
      <?= $qa('empty', 'Empty queue', 'ws-btn--danger', 'Remove every scheduled post on this channel? This cannot be undone.') ?>
    </div>
  </section>
<?php endif; ?>
</div>
