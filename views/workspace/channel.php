<?php
/** @var array $user */
/** @var array $channel */
/** @var string $setup */
/** @var string $tab */
/** @var string $view */
/** @var int $days */
/** @var ?array $queue */
/** @var ?array $week */
/** @var array $posts */
/** @var array $counts */
$token = Security::csrfToken();
$provider = array_column(integrations(), null, 'slug')[$channel['provider']] ?? ['name' => $channel['provider']];
$goal = (int) $channel['posting_goal'];
$goalState = View::formState('goal');
$scheduleState = View::formState('schedule');
$picked = (string) ($goalState['old']['goal'] ?? ($goal ? (isset(Channels::GOALS[$goal]) ? $goal : 'custom') : '3'));
$cid = (int) $channel['id'];
$self = '/app/channels/' . $cid;
$tz = Workspace::timezone($user);
$target = 'c:' . $cid;
$url = static fn (array $params): string => $self . '?' . http_build_query(array_filter($params + ['tab' => $tab, 'view' => $view], static fn ($v) => $v !== '' && $v !== null && $v !== 'queue' && $v !== 'list'));
if ($setup === 'times' && !$goal) {
    $setup = 'goal';
}
$newPost = e(json_encode(['targets' => [$target]]));
$next = $queue['next'] ?? null;
?>
<div class="wsp">
  <header class="wsp-head ch-head">
    <div class="wsp-title">
      <?= channel_avatar($channel, 'md') ?>
      <div>
        <h1><?= e($channel['name']) ?>
          <a class="ws-icon-btn ws-icon-btn--sm" href="<?= e($self) ?>/settings" title="Channel settings"><?= icon('gear') ?><span class="sr-only">Channel settings</span></a>
        </h1>
        <p class="ch-sub"><?php if ($goal): ?><b><?= $counts['sent_week'] ?>/<?= $goal ?></b> posts sent this week<?php else: ?><?= e($provider['name'] . ' ' . $channel['kind']) ?> · <a href="<?= e($self) ?>?setup=goal">Set a posting goal</a><?php endif; ?></p>
      </div>
    </div>
    <div class="wsp-actions">
      <a class="ws-icon-btn wsp-feedback" href="/contact" title="Share feedback"><?= icon('megaphone') ?><span class="sr-only">Share feedback</span></a>
      <nav class="ws-seg" aria-label="Layout">
        <a href="<?= e($url(['tab' => 'queue', 'view' => 'list'])) ?>"<?= $tab === 'queue' && $view === 'list' ? ' aria-current="page"' : '' ?>><?= icon('rows') ?>List</a>
        <a href="<?= e($url(['tab' => 'queue', 'view' => 'calendar'])) ?>"<?= $tab === 'queue' && $view === 'calendar' ? ' aria-current="page"' : '' ?>><?= icon('calendar-blank') ?>Calendar</a>
      </nav>
      <a class="ws-btn" href="<?= e($self) ?>?setup=generate"><?= icon('sparkle') ?>AI Generate</a>
      <a class="ws-btn ws-btn--dark" href="/app/publish/new?target=<?= e($target) ?>" data-compose="<?= $newPost ?>"><?= icon('plus') ?>New Post</a>
    </div>
  </header>

  <div class="wsp-bar">
    <nav class="wsp-tabs" aria-label="<?= e($channel['name']) ?> posts">
      <a href="<?= e($self) ?><?= $view === 'calendar' ? '?view=calendar' : '' ?>"<?= $tab === 'queue' ? ' aria-current="page"' : '' ?>>Queue <span class="wsp-count"><?= $counts['queue'] ?></span></a>
      <a href="<?= e($self) ?>?tab=drafts"<?= $tab === 'drafts' ? ' aria-current="page"' : '' ?>>Drafts <span class="wsp-count"><?= $counts['drafts'] ?></span></a>
      <a href="<?= e($self) ?>?tab=approvals"<?= $tab === 'approvals' ? ' aria-current="page"' : '' ?>>Approvals <span class="wsp-bolt" aria-hidden="true"><?= icon('lightning') ?></span></a>
      <a href="<?= e($self) ?>?tab=sent"<?= $tab === 'sent' ? ' aria-current="page"' : '' ?>>Sent <span class="wsp-count"><?= $counts['sent'] ?></span></a>
    </nav>
    <div class="wsp-filters">
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn" aria-expanded="false" aria-controls="tz-menu" data-menu-toggle><?= icon('globe') ?><?= e(Workspace::timezoneLabel($tz)) ?><?= icon('caret-down') ?></button>
        <form class="ws-menu ws-menu--end ws-menu--form ws-menu-form" id="tz-menu" action="/account/timezone" method="post" hidden data-menu-panel>
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="return" value="<?= e($url([])) ?>">
          <label for="tz-select">Time zone</label>
          <select class="idea-input" id="tz-select" name="timezone">
<?php foreach (DateTimeZone::listIdentifiers() as $name): ?>
            <option<?= $name === $tz->getName() ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
          </select>
          <button type="submit" class="ws-btn ws-btn--sm ws-btn--green">Save</button>
        </form>
      </div>
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-icon-btn" aria-expanded="false" aria-controls="ch-menu" data-menu-toggle><?= icon('dots-three-vertical') ?><span class="sr-only">Channel options</span></button>
        <div class="ws-menu ws-menu--end" id="ch-menu" hidden data-menu-panel>
          <a class="ws-menu-item" href="<?= e($self) ?>?setup=goal"><?= icon('pencil-simple') ?>Change posting goal</a>
          <a class="ws-menu-item" href="<?= e($self) ?>/settings"><?= icon('gear') ?>Channel settings</a>
          <form action="/app/channels/connect/<?= e($channel['provider']) ?>" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="reconnect" value="1">
            <button type="submit" class="ws-menu-item"<?= Channels::connectable($channel['provider']) ? '' : ' disabled' ?>><?= icon('arrows-clockwise') ?>Reconnect channel</button>
          </form>
          <form action="<?= e($self) ?>/disconnect" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <button type="submit" class="ws-menu-item ws-menu-item--danger" data-confirm="Disconnect <?= e($channel['name']) ?>? Its scheduled posts stay in your queue as plans and won't be published."><?= icon('sign-out') ?>Disconnect channel</button>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php if (!$channel['can_publish'] && in_array($channel['provider'], ['youtube', 'linkedin', 'instagram'], true)): ?>
  <div class="ch-banner" role="status">
    <?= icon('warning-circle') ?>
    <p><b>Reconnect to publish.</b> <?= match ($channel['provider']) {
        'linkedin' => 'Allow atwebtech to create posts on your LinkedIn Profile (the “Share on LinkedIn” permission) so scheduled posts can go out.',
        'instagram' => 'Content publishing wasn’t granted, so these posts stay as plans and are not sent. Reconnect and allow Instagram content publishing.',
        default => 'Video upload was not granted. Reconnect and tick “Manage your YouTube videos” on the Google screen. If that choice is missing, add the youtube.upload scope in Google Cloud, then reconnect.',
    } ?></p>
    <form action="/app/channels/connect/<?= e($channel['provider']) ?>" method="post">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <input type="hidden" name="reconnect" value="1">
      <button type="submit" class="ws-btn ws-btn--sm ws-btn--dark"<?= Channels::connectable($channel['provider']) ? '' : ' disabled' ?>>Reconnect</button>
    </form>
  </div>
<?php endif; ?>

<?php if ($tab === 'queue' && $channel['queue_intro_closed_at'] === null): ?>
  <section class="qw" aria-labelledby="qw-title" data-intro>
    <div class="qw-text">
      <h2 id="qw-title">Welcome to your Queue!</h2>
      <p>Everything you schedule for <?= e($channel['name']) ?> lines up here, in order. The empty rows are your posting times. Pick one to fill it, and atwebtech uploads the post when its time comes.</p>
      <p>Want different days or hours? <a href="<?= e($self) ?>/settings">Set a custom schedule</a>.</p>
      <form action="<?= e($self) ?>/intro" method="post" data-intro-form>
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <button type="submit" class="ws-btn ws-btn--green">Got It</button>
      </form>
    </div>
    <svg class="qw-art" viewBox="0 0 220 150" aria-hidden="true">
      <rect x="38" y="16" width="150" height="34" rx="9" class="qw-a"/>
      <rect x="28" y="58" width="170" height="38" rx="10" class="qw-b"/>
      <rect x="38" y="104" width="150" height="34" rx="9" class="qw-a"/>
      <circle cx="54" cy="77" r="9" class="qw-c"/>
      <rect x="72" y="70" width="70" height="6" rx="3" class="qw-d"/>
      <rect x="72" y="81" width="46" height="6" rx="3" class="qw-d"/>
      <path d="M206 34 C 222 64, 222 92, 204 118" class="qw-e"/>
      <path d="M198 112 L204 120 L212 113" class="qw-e"/>
    </svg>
  </section>
<?php endif; ?>

<?php if ($tab === 'queue' && $view === 'list'): ?>
<?php if ($goal): ?>
<?php partial('workspace/queue-list', ['user' => $user, 'queue' => $queue, 'filter' => '', 'endless' => $next && $days < ChannelHandler::QUEUE_DAYS ? [
    'src' => $self . '/queue?from=' . $next->format('Y-m-d'),
    'href' => $self . '?days=' . min(ChannelHandler::QUEUE_DAYS, $days + 28),
] : null]); ?>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('calendar-blank') ?></span>
    <h2>Set a posting goal</h2>
    <p>Choose how often you'd like to post and we'll add posting times to your queue.</p>
    <a class="ws-btn ws-btn--green" href="<?= e($self) ?>?setup=goal">Set posting goal</a>
  </div>
<?php endif; ?>
<?php elseif ($tab === 'queue'): ?>
<?php partial('workspace/calendar', ['user' => $user, 'week' => $week, 'url' => $url, 'target' => $target, 'filter' => '']); ?>
<?php elseif ($tab === 'drafts' || $tab === 'sent'): ?>
<?php partial('workspace/post-list', ['user' => $user, 'posts' => $posts, 'kind' => $tab, 'compose' => json_encode(['targets' => [$target]])]); ?>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon wsp-empty-icon--purple"><?= icon('lightning') ?></span>
    <h2>Approvals <span class="ws-soon">Coming soon</span></h2>
    <p>Approvals will let teammates review posts before they go out. Teams aren't available yet.</p>
  </div>
<?php endif; ?>
</div>

<?php if ($setup !== ''): ?>
<div class="ch-modal" role="dialog" aria-modal="true" aria-labelledby="ch-modal-title" data-ch-modal>
  <div class="ch-modal-box<?= $setup === 'schedule' || $setup === 'generate' ? ' ch-modal-box--wide' : '' ?>">
    <a class="ws-icon-btn ch-modal-close" href="<?= e($self) ?>"><?= icon('x') ?><span class="sr-only">Close</span></a>
<?php if ($setup === 'goal'): ?>
    <div class="ch-confetti" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="ch-modal-body">
      <span class="ch-modal-av"><?= channel_avatar($channel, 'lg') ?><span class="ch-modal-check"><?= icon('check') ?></span></span>
      <h2 id="ch-modal-title">How many times a week would you like to post?</h2>
      <p>Your posting goal sets how many posting times appear in your queue each week.</p>
<?php if ($goalState): ?><p class="composer-error" role="alert"><?= e($goalState['message']) ?></p><?php endif; ?>
      <form class="goal-form" id="goal-form" action="<?= e($self) ?>/goal" method="post" data-goal-form>
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="setup" value="1">
        <fieldset>
          <legend class="sr-only">Posts per week</legend>
<?php foreach (Channels::GOALS as $n => [$label, $freq, $tone]): ?>
          <label class="goal-opt">
            <span class="goal-badge goal-badge--<?= e($tone) ?>"><?= $n ?>x</span>
            <span class="goal-text"><?= e($label) ?> · <?= e($freq) ?></span>
            <input type="radio" name="goal" value="<?= $n ?>"<?= $picked === (string) $n ? ' checked' : '' ?>>
          </label>
<?php endforeach; ?>
          <label class="goal-opt">
            <span class="goal-badge goal-badge--pink"><?= icon('pencil-simple') ?></span>
            <span class="goal-text">Choose your goal</span>
            <input type="radio" name="goal" value="custom"<?= $picked === 'custom' ? ' checked' : '' ?> data-goal-custom>
          </label>
          <label class="goal-custom" data-goal-custom-field>
            <span>Posts per week (1–<?= Channels::MAX_GOAL ?>)</span>
            <input class="idea-input" type="number" name="custom" min="1" max="<?= Channels::MAX_GOAL ?>" inputmode="numeric" value="<?= e((string) ($goalState['old']['custom'] ?? ($picked === 'custom' ? $goal : ''))) ?>">
          </label>
        </fieldset>
      </form>
    </div>
    <footer class="ch-modal-foot">
      <a class="ch-modal-link" href="/faq"><?= icon('question') ?>What are suggested times?</a>
      <button type="submit" form="goal-form" class="ws-btn ws-btn--green">Next<?= icon('arrow-right') ?></button>
    </footer>
<?php elseif ($setup === 'generate'):
    $genState = View::formState('generate');
    $old = $genState['old'] ?? [];
    $mode = (string) ($old['mode'] ?? 'random');
    $flag = static fn (string $key, bool $default): string => ($genState ? !empty($old[$key]) : $default) ? ' checked' : '';
    $pick = static fn (string $key, string $default): string => (string) ($old[$key] ?? $default);
    $pickedDays = array_map('intval', (array) ($old['days'] ?? [1, 2, 3, 4, 5, 6, 7]));
    $mediaNet = in_array($channel['provider'], ['instagram', 'youtube', 'tiktok'], true);
    $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
?>
    <div class="ch-modal-body gen">
      <h2 id="ch-modal-title">Generate posts with AI</h2>
      <p>Four quick steps. The AI writes the posts for <?= e($channel['name']) ?> and puts them on the days and time you choose.</p>
      <ol class="gen-steps" aria-label="Steps">
        <li data-gen-dot="1">Method</li>
        <li data-gen-dot="2">Topic</li>
        <li data-gen-dot="3">Rules</li>
        <li data-gen-dot="4">Schedule</li>
      </ol>
<?php if (!Assistant::enabled()): ?>
      <p class="composer-note"><?= icon('info') ?>The AI Assistant isn’t switched on for this workspace yet. Add an OpenAI API key on the server to use it.</p>
<?php endif; ?>
      <p class="composer-error" role="alert" data-gen-error<?= $genState ? '' : ' hidden' ?>><?= e((string) ($genState['message'] ?? '')) ?></p>
      <form class="gen-form" id="gen-form" action="<?= e($self) ?>/generate" method="post" data-gen data-step="<?= max(1, min(4, (int) ($old['step'] ?? 1))) ?>">
        <input type="hidden" name="_token" value="<?= e($token) ?>">

        <fieldset class="gen-step" data-gen-step="1">
          <legend>How should the AI come up with posts?</legend>
          <div class="gen-modes">
<?php foreach (Assistant::MODES as $key => [$label, $text, $ic]): ?>
            <label class="gen-mode">
              <input type="radio" name="mode" value="<?= e($key) ?>" required<?= $mode === $key ? ' checked' : '' ?>>
              <span class="gen-mode-icon gen-mode-icon--<?= e($key) ?>"><?= icon($ic) ?></span>
              <b><?= e($label) ?></b>
              <small><?= e($text) ?></small>
            </label>
<?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="gen-step" data-gen-step="2">
          <legend>What are the posts about?</legend>
          <div class="idea-field">
            <label for="gen-topic">Topic name</label>
            <input class="idea-input" id="gen-topic" name="topic" minlength="2" maxlength="120" required placeholder="e.g. Website tips for local businesses" value="<?= e($pick('topic', '')) ?>">
          </div>
          <div class="idea-field">
            <label for="gen-details"><span class="gen-if-prompt">Prompt</span><span class="gen-if-not-prompt">Details <small>(optional)</small></span></label>
            <textarea class="idea-input" id="gen-details" name="details" rows="4" maxlength="1000" placeholder="Who you are, who you help, what you offer, and anything the posts should mention." data-gen-details<?= $mode === 'prompt' ? ' required minlength="10"' : '' ?>><?= e($pick('details', '')) ?></textarea>
            <small class="gen-hint gen-if-prompt">Every post follows this brief, each from a different angle. Include the offer, audience, and anything that must be said.</small>
            <small class="gen-hint gen-if-not-prompt">The AI won’t invent prices, results or reviews. Facts you leave out become [placeholders].</small>
          </div>
          <div class="idea-field gen-if-trending">
            <label for="gen-region">Trends from</label>
            <select class="idea-input" id="gen-region" name="region">
<?php foreach (Assistant::TREND_REGIONS as $code => $country): ?>
              <option value="<?= e($code) ?>"<?= $pick('region', Assistant::trendRegion($tz)) === $code ? ' selected' : '' ?>><?= e($country) ?></option>
<?php endforeach; ?>
            </select>
            <small class="gen-hint">Uses today’s trending Google searches in this country. Trends that don’t fit your topic are skipped.</small>
          </div>
        </fieldset>

        <fieldset class="gen-step" data-gen-step="3">
          <legend>Generation rules</legend>
          <div class="gen-row">
            <div class="idea-field">
              <label for="gen-tone">Tone</label>
              <select class="idea-input" id="gen-tone" name="tone">
<?php foreach (Assistant::TONES as $key => $text): ?>
                <option value="<?= e($key) ?>"<?= $pick('tone', 'friendly') === $key ? ' selected' : '' ?>><?= e(ucfirst($text)) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="idea-field">
              <label for="gen-length">Length</label>
              <select class="idea-input" id="gen-length" name="length">
<?php foreach (Assistant::LENGTHS as $key => [$text]): ?>
                <option value="<?= e($key) ?>"<?= $pick('length', $channel['provider'] === 'x' || $channel['provider'] === 'threads' ? 'short' : 'medium') === $key ? ' selected' : '' ?>><?= e(ucfirst($key)) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="idea-field">
              <label for="gen-language">Language</label>
              <select class="idea-input" id="gen-language" name="language">
<?php foreach (Assistant::LANGUAGES as $lang): ?>
                <option<?= $pick('language', 'English') === $lang ? ' selected' : '' ?>><?= e($lang) ?></option>
<?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="gen-checks">
            <label class="gen-check"><input type="checkbox" name="hashtags" value="1"<?= $flag('hashtags', true) ?>>Add hashtags</label>
            <label class="gen-check"><input type="checkbox" name="emojis" value="1"<?= $flag('emojis', false) ?>>Use emojis</label>
            <label class="gen-check"><input type="checkbox" name="cta" value="1"<?= $flag('cta', true) ?>>End with a call to action</label>
            <label class="gen-check"><input type="checkbox" name="question" value="1"<?= $flag('question', false) ?>>Ask the audience a question</label>
<?php if ($mediaNet): ?>
            <label class="gen-check gen-check--wide"><input type="checkbox" name="media" value="1"<?= $flag('media', true) ?>>Attach a starter <?= $channel['provider'] === 'instagram' ? 'photo' : 'video' ?> from the template library, so each post can publish</label>
<?php endif; ?>
          </div>
          <div class="idea-field">
            <label for="gen-rules">Other rules <small>(optional)</small></label>
            <textarea class="idea-input" id="gen-rules" name="rules" rows="3" maxlength="500" placeholder="e.g. Never mention competitors. Sign off with “— Team AT”."><?= e($pick('rules', '')) ?></textarea>
          </div>
        </fieldset>

        <fieldset class="gen-step" data-gen-step="4">
          <legend>When should they go out?</legend>
          <div class="gen-row">
            <div class="idea-field">
              <label for="gen-count">Number of posts</label>
              <input class="idea-input" id="gen-count" type="number" name="count" min="1" max="<?= Assistant::RUN_MAX ?>" inputmode="numeric" required value="<?= e($pick('count', '7')) ?>">
            </div>
            <div class="idea-field">
              <label for="gen-start">Starting</label>
              <input class="idea-input" id="gen-start" type="date" name="start" min="<?= e($today) ?>" required value="<?= e($pick('start', $today)) ?>">
            </div>
            <div class="idea-field">
              <label for="gen-time">Time <small>(<?= e(Workspace::timezoneLabel($tz)) ?>)</small></label>
              <input class="idea-input" id="gen-time" type="time" name="time" required value="<?= e($pick('time', '18:00')) ?>">
            </div>
          </div>
          <fieldset class="gen-days">
            <legend>Post on</legend>
<?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $d => $dayName): ?>
            <label class="gen-day"><input type="checkbox" name="days[]" value="<?= $d ?>"<?= in_array($d, $pickedDays, true) ? ' checked' : '' ?>><span><?= e($dayName) ?></span></label>
<?php endforeach; ?>
          </fieldset>
          <label class="gen-check"><input type="checkbox" name="skip" value="1"<?= $flag('skip', true) ?>>Skip days that already have a scheduled post</label>
          <fieldset class="gen-save">
            <legend>Save them as</legend>
            <label class="gen-mode gen-mode--row">
              <input type="radio" name="save" value="planned"<?= $pick('save', 'planned') !== 'draft' ? ' checked' : '' ?>>
              <b>Scheduled posts</b>
              <small>Go straight into the queue</small>
            </label>
            <label class="gen-mode gen-mode--row">
              <input type="radio" name="save" value="draft"<?= $pick('save', 'planned') === 'draft' ? ' checked' : '' ?>>
              <b>Drafts</b>
              <small>Review each one first</small>
            </label>
          </fieldset>
        </fieldset>
      </form>
    </div>
    <footer class="ch-modal-foot gen-foot">
      <button type="button" class="ws-btn" data-gen-back hidden><?= icon('arrow-left') ?>Back</button>
      <span class="gen-status" role="status" data-gen-status></span>
      <button type="button" class="ws-btn ws-btn--green" data-gen-next hidden>Next<?= icon('arrow-right') ?></button>
      <button type="submit" form="gen-form" class="ws-btn ws-btn--green" data-gen-submit<?= Assistant::enabled() ? '' : ' disabled' ?>><?= icon('sparkle') ?>Generate posts</button>
    </footer>
<?php elseif ($setup === 'schedule'):
    $byDay = [];
    foreach ($channel['times'] as $t) {
        $byDay[(int) $t['day']][] = $t['time'];
    }
?>
    <div class="ch-modal-body">
      <h2 id="ch-modal-title">Posting schedule</h2>
      <p>Pick the days and times your queue offers for <?= e($channel['name']) ?>, in <?= e(Workspace::timezoneLabel($tz)) ?> time. Leave a box empty to skip it.</p>
<?php if ($scheduleState): ?><p class="composer-error" role="alert"><?= e($scheduleState['message']) ?></p><?php endif; ?>
      <form class="sch" id="schedule-form" action="<?= e($self) ?>/schedule" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
<?php foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $d => $dayName): ?>
        <fieldset class="sch-day">
          <legend><?= e($dayName) ?></legend>
<?php for ($i = 0; $i < 3; $i++): ?>
          <label><span class="sr-only"><?= e($dayName) ?> time <?= $i + 1 ?></span><input class="idea-input" type="time" name="times[<?= $d ?>][]" value="<?= e($byDay[$d][$i] ?? '') ?>"></label>
<?php endfor; ?>
        </fieldset>
<?php endforeach; ?>
      </form>
    </div>
    <footer class="ch-modal-foot">
      <a class="ch-modal-link" href="<?= e($self) ?>?setup=goal"><?= icon('arrow-left') ?>Use a posting goal instead</a>
      <button type="submit" form="schedule-form" class="ws-btn ws-btn--green">Save schedule</button>
    </footer>
<?php else: ?>
    <div class="ch-modal-body">
      <span class="ch-modal-av"><?= channel_avatar($channel, 'lg') ?></span>
      <h2 id="ch-modal-title">These are your suggested times to post</h2>
      <p>They're simple starting points spread across the week. Reach and engagement depend on much more than timing.</p>
      <ul class="ch-times ch-times--box" role="list">
<?php foreach ($channel['times'] as $t): ?>
        <li><?= icon('check') ?><?= e(Channels::timeLabel($t)) ?></li>
<?php endforeach; ?>
      </ul>
    </div>
    <footer class="ch-modal-foot">
      <a class="ch-modal-link" href="<?= e($self) ?>?setup=goal"><?= icon('arrow-left') ?>Change Goal</a>
      <a class="ws-btn ws-btn--green" href="<?= e($self) ?>">Got it</a>
    </footer>
<?php endif; ?>
  </div>
</div>
<?php endif; ?>
