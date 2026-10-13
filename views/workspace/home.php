<?php
/** @var array $user */
/** @var array $onboarding */
$hour = (int) gmdate('G');
[$greeting, $emoji] = $hour < 12 ? ['Good morning', '☀️'] : ($hour < 18 ? ['Good afternoon', '🌤️'] : ['Good evening', '🌙']);
$showSurvey = $onboarding['referral'] === null && $onboarding['survey_closed_at'] === null;
$survey = View::formState('survey');
$referral = data('onboarding')['referral'];
$stats = [
    ['Week Streak', 'Weeks in a row with at least one published post.'],
    ['Posting Goals', 'Weekly posting goals you have met.'],
    ['Comment Score', 'How many comments you have replied to.'],
];
$connected = Channels::forUser((int) $user['id']);
$counts = Workspace::counts((int) $user['id']);
$steps = [
    $connected
        ? ['1. Connect a channel', ($plan = Channels::allowance())['paid'] ? sprintf('%d channels connected. Add another or set its posting goal.', count($connected)) : sprintf('%d of %d channels connected. Add another or set its posting goal.', count($connected), $plan['limit']), '/app/channels', 'plus', 'Manage Channels', true]
        : ['1. Connect a channel', 'Link the channels you post to. See which you can connect today and which are on the way.', '/app/channels', 'plus', 'Connect Channel', false],
    ['2. Create a post', "Write a post and schedule it. Posts for a connected YouTube channel publish at their time; other networks are saved as plans.", '/app/publish/new', 'pencil-simple', 'Create Post', $counts['queue'] + $counts['drafts'] > 0],
    ['3. Explore the demo', 'Try the calendar, ideas board, inbox, and analytics with sample data.', '/demo', 'arrow-up-right', 'Open Demo', false],
];
?>
<div class="ws-page">
  <header class="ws-greet" data-greeting>
    <span class="ws-greet-emoji" aria-hidden="true" data-greeting-emoji><?= $emoji ?></span>
    <div>
      <h1><span data-greeting-text><?= e($greeting) ?></span>, <?= e($user['display_name']) ?>!</h1>
      <p class="ws-date" data-greeting-date><?= e(gmdate('D, M j Y')) ?></p>
    </div>
  </header>

  <section class="ws-stats-card" aria-label="Your activity">
    <ul class="ws-stats" role="list">
<?php foreach ($stats as $i => [$label, $desc]): ?>
      <li class="ws-stat">
        <span class="ws-stat-num">0</span>
        <span class="ws-stat-label"><?= e($label) ?></span>
        <span class="tip">
          <button type="button" class="tip-btn" aria-describedby="stat-tip-<?= $i ?>" aria-label="About <?= e($label) ?>"><?= icon('info') ?></button>
          <span class="tip-body" role="tooltip" id="stat-tip-<?= $i ?>"><?= e($desc) ?></span>
        </span>
      </li>
<?php endforeach; ?>
    </ul>
    <p class="ws-stats-note"><?= icon('info') ?><span>Streaks, goals and comment scores start counting once publishing and the comment inbox are live. <a href="/app/channels">Manage channels</a>.</span></p>
  </section>

  <section class="ws-block" aria-labelledby="first-steps">
    <h2 class="ws-h" id="first-steps">First Steps</h2>
    <ul class="ws-steps" role="list">
<?php foreach ($steps as [$title, $text, $href, $icon, $cta, $done]): ?>
      <li class="ws-step<?= $done ? ' is-done' : '' ?>">
        <div class="ws-step-head"><h3><?= e($title) ?><?php if ($done): ?><span class="sr-only"> (done)</span><?php endif; ?></h3><span class="ws-step-check" aria-hidden="true"><?= icon('check-circle') ?></span></div>
        <p><?= e($text) ?></p>
        <a class="ws-btn" href="<?= e($href) ?>"><?= icon($icon) ?><?= e($cta) ?></a>
      </li>
<?php endforeach; ?>
    </ul>
  </section>

  <div class="ws-split">
    <section class="ws-block" aria-labelledby="up-next">
      <h2 class="ws-h" id="up-next">Up Next</h2>
      <div class="ws-empty">
        <span class="ws-empty-icon"><?= icon('calendar-blank') ?></span>
        <p><?php if ($counts['queue'] > 0): ?><?= $counts['queue'] ?> planned <?= $counts['queue'] === 1 ? 'post' : 'posts' ?> in your queue.<br><a href="/app/publish">Open the queue</a><?php else: ?>No posts planned yet.<br>You'll see upcoming posts here.<?php endif; ?></p>
      </div>
    </section>
    <section class="ws-block" aria-labelledby="comments">
      <h2 class="ws-h" id="comments">Comments</h2>
      <div class="ws-empty">
        <span class="ws-empty-icon"><?= icon('chat-circle-text') ?></span>
        <p>No comments yet.<br>You'll see recent comments here.</p>
      </div>
    </section>
  </div>

  <section class="ws-block" aria-labelledby="templates">
    <h2 class="ws-h" id="templates">Templates</h2>
    <div class="ws-empty ws-empty--row">
      <span class="ws-empty-icon"><?= icon('note-pencil') ?></span>
      <p>Start from a writing prompt or save your own. <a href="/app/create?tab=templates">Browse templates</a>.</p>
    </div>
  </section>
</div>

<?php if ($showSurvey): ?>
<section class="ws-survey" id="survey" aria-labelledby="survey-title" data-dismissible>
  <form action="/app/survey" method="post" data-survey data-quiet-form>
    <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
    <div class="ws-survey-head">
      <h2 id="survey-title">How did you hear about atwebtech?</h2>
      <button type="submit" class="ws-icon-btn" name="action" value="close" formnovalidate><?= icon('x') ?><span class="sr-only">Close survey</span></button>
    </div>
    <fieldset>
      <legend class="sr-only">How did you hear about atwebtech?</legend>
<?php foreach ($referral as $key => $label): ?>
      <label class="ws-radio"><input type="radio" name="referral" value="<?= e($key) ?>"<?= ($survey['old']['referral'] ?? '') === $key ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
<?php endforeach; ?>
      <label class="sr-only" for="referral-other">Where did you hear about atwebtech?</label>
      <input class="ws-survey-other" id="referral-other" type="text" name="referral_other" maxlength="200" placeholder="Please specify" value="<?= e($survey['old']['referral_other'] ?? '') ?>" data-survey-other>
    </fieldset>
    <p class="ws-survey-error" role="alert" data-survey-error<?= $survey ? '' : ' hidden' ?>><?= e($survey['message'] ?? '') ?></p>
    <button type="submit" class="ws-survey-submit" name="action" value="submit" data-survey-submit>Submit</button>
  </form>
</section>
<?php endif; ?>
