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
        <h2>Campaign tracking <span class="ws-soon">Coming soon</span></h2>
        <p>Google Analytics tags for posts aren’t built yet. Choose a link shortener first — that part isn’t available either.</p>
      </div>
    </div>
  </section>

  <section class="set-block">
    <div class="set-row">
      <div>
        <h2>Bulk Post Upload <span class="ws-soon">Coming soon</span></h2>
        <p>Scheduling many posts from a CSV file isn’t built yet. Add posts one at a time from Create Post.</p>
      </div>
      <button type="button" class="ws-btn set-control" disabled>Bulk Upload</button>
    </div>
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

  <section class="set-block">
    <h2 class="set-group">Queue Actions</h2>
    <div class="set-row">
      <div>
        <h2>Pause Queue <span class="ws-soon">Coming soon</span></h2>
        <p>Stopping all posts from sending on this channel isn’t built yet. Scheduled posts still go out at their time.</p>
      </div>
      <label class="set-switch is-disabled"><input type="checkbox" disabled><span aria-hidden="true"></span><span class="sr-only">Off</span></label>
    </div>
    <div class="set-row">
      <div>
        <h2>Shuffle Queue <span class="ws-soon">Coming soon</span></h2>
        <p>Randomising queued posts isn’t built yet. Times stay in the order you set.</p>
      </div>
      <button type="button" class="ws-btn set-control" disabled>Shuffle Queue</button>
    </div>
    <div class="set-row">
      <div>
        <h2>Clean Queue <span class="ws-soon">Coming soon</span></h2>
        <p>A one-tap clean-up of failed posts isn’t built yet. Failed posts stay under Needs attention, where you can retry or edit them.</p>
      </div>
      <button type="button" class="ws-btn set-control" disabled>Clean Queue</button>
    </div>
    <div class="set-row">
      <div>
        <h2>Delete All Drafts <span class="ws-soon">Coming soon</span></h2>
        <p>Bulk-deleting drafts for this channel isn’t built yet. Open a draft to delete it.</p>
      </div>
      <button type="button" class="ws-btn ws-btn--danger set-control" disabled>Delete Drafts</button>
    </div>
    <div class="set-row">
      <div>
        <h2>Empty Queue <span class="ws-soon">Coming soon</span></h2>
        <p>Clearing every scheduled post on this channel isn’t built yet. Delete posts from the queue one at a time.</p>
      </div>
      <button type="button" class="ws-btn ws-btn--danger set-control" disabled>Empty Queue</button>
    </div>
  </section>
<?php endif; ?>
</div>
