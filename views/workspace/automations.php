<?php
/** @var array $user */
/** @var array $items */
/** @var array $feeds */
/** @var array $textChannels  channels that can post text with a link */
/** @var array $replyChannels  channels atwebtech can reply on */
/** @var array $allChannels */
/** @var bool $paid */
/** @var bool $ai */
$token = Security::csrfToken();
$tz = Workspace::timezone($user);
$canEdit = Team::can('admin');
$icons = ['feed' => 'link-simple', 'plan' => 'magic-wand', 'reply' => 'chat-circle-text'];
$channelNames = static fn (array $ids): string => implode(', ', array_map(static fn ($id): string => $allChannels[(int) $id]['name'] ?? 'removed channel', $ids)) ?: 'all channels';
$checks = static function (array $channels, string $empty): string {
    if (!$channels) {
        return '<small>' . e($empty) . ' <a href="/app/channels">Connect a channel</a></small>';
    }
    $out = '<div class="au-checks">';
    foreach ($channels as $c) {
        $out .= '<label><input type="checkbox" name="channels[]" value="' . (int) $c['id'] . '" checked> ' . e($c['name']) . '</label>';
    }
    return $out . '</div>';
};
$modes = static function (): string {
    $out = '<label class="bio-field"><span>What to do with the posts</span><select class="idea-input" name="mode">';
    foreach (Automations::MODES as $key => $label) {
        $out .= '<option value="' . e($key) . '">' . e($label) . '</option>';
    }
    return $out . '</select></label>';
};
$summary = static function (array $a) use ($channelNames): string {
    $c = $a['config'];
    $mode = ['draft' => 'saved to Drafts', 'review' => 'sent for approval', 'schedule' => 'scheduled automatically'][$c['mode'] ?? 'draft'] ?? '';
    return match ($a['kind']) {
        'feed' => 'Posts to ' . $channelNames((array) ($c['channel_ids'] ?? [])) . ', ' . $mode . (!empty($c['ai']) ? ', written by AI' : ''),
        'plan' => (int) ($c['per_week'] ?? 3) . ' posts a week for ' . $channelNames((array) ($c['channel_ids'] ?? [])) . ', ' . $mode,
        default => 'On ' . $channelNames((array) ($c['channel_ids'] ?? [])) . ': “' . mb_strimwidth((string) ($c['text'] ?? ''), 0, 70, '…') . '”',
    };
};
?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'lightning', 'title' => 'Automations', 'actions' => '']); ?>

<?php if (!$paid): ?>
  <section class="tm-upsell">
    <h2>Let atwebtech do the weekly work</h2>
    <p>On Essentials and Team, your blog posts turn into social posts, the AI Assistant writes next week’s plan, and common questions get answered, all on their own. You choose whether posts wait for your check or go out automatically.</p>
    <a class="ws-btn ws-btn--dark" href="/app/billing">Upgrade</a>
  </section>
<?php endif; ?>

<?php if ($paid && $canEdit): ?>
  <div class="au-grid">
    <section class="au-card">
      <?= icon('link-simple') ?>
      <h2>Blog to social</h2>
      <p>When your blog or news page publishes something new, atwebtech writes a post with the link for each channel you pick.</p>
      <details>
        <summary class="ws-btn ws-btn--dark">Set up</summary>
        <form action="/app/automations" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="create_feed">
<?php if ($feeds): ?>
          <label class="bio-field"><span>Feed</span>
            <select class="idea-input" name="feed_id">
<?php foreach ($feeds as $f): ?>
              <option value="<?= (int) $f['id'] ?>"><?= e($f['title']) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label class="bio-field"><span>Or a new blog address</span><input class="idea-input" name="feed_url" maxlength="500" placeholder="https://yourbusiness.com/blog"></label>
<?php else: ?>
          <label class="bio-field"><span>Your blog or news page</span><input class="idea-input" name="feed_url" maxlength="500" required placeholder="https://yourbusiness.com/blog"></label>
<?php endif; ?>
          <?= $checks($textChannels, 'Needs a LinkedIn, Facebook, Threads, Bluesky or Mastodon channel.') ?>
          <?= $modes() ?>
<?php if ($ai): ?>
          <label class="au-checks"><span><input type="checkbox" name="ai" value="1" checked> Let the AI Assistant write the caption (otherwise the article title is used)</span></label>
<?php endif; ?>
          <button type="submit" class="ws-btn ws-btn--dark">Turn on</button>
          <small>Starts with the next new article. Checked every 30 minutes.</small>
        </form>
      </details>
    </section>

    <section class="au-card">
      <?= icon('magic-wand') ?>
      <h2>Weekly AI plan</h2>
      <p>Every week the AI Assistant writes your next week of posts and puts them in your free posting times.</p>
      <details>
        <summary class="ws-btn ws-btn--dark"<?= $ai ? '' : ' aria-disabled="true"' ?>>Set up</summary>
        <form action="/app/automations" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="create_plan">
          <label class="bio-field"><span>What your business is about</span><input class="idea-input" name="topic" maxlength="120" required placeholder="A family bakery in Austin"></label>
          <label class="bio-field"><span>Details the posts should use (optional)</span><textarea class="idea-input" name="details" rows="3" maxlength="800" placeholder="Products, opening hours, offers, what customers ask about. The AI never invents prices or facts."></textarea></label>
          <?= $checks($textChannels, 'Needs a LinkedIn, Facebook, Threads, Bluesky or Mastodon channel.') ?>
          <div class="au-two">
            <label class="bio-field"><span>Posts per week, per channel</span>
              <select class="idea-input" name="per_week"><?php foreach ([1, 2, 3, 4, 5, 7] as $n): ?><option value="<?= $n ?>"<?= $n === 3 ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
            </label>
            <label class="bio-field"><span>Tone</span>
              <select class="idea-input" name="tone"><?php foreach (Assistant::TONES as $key => $label): ?><option value="<?= e($key) ?>"><?= e(ucfirst($key)) ?></option><?php endforeach; ?></select>
            </label>
          </div>
          <label class="bio-field"><span>Language</span>
            <select class="idea-input" name="language"><?php foreach (Assistant::LANGUAGES as $l): ?><option><?= e($l) ?></option><?php endforeach; ?></select>
          </label>
          <?= $modes() ?>
          <button type="submit" class="ws-btn ws-btn--dark">Turn on</button>
          <small>Uses your daily AI allowance. Instagram, Pinterest and YouTube need a photo or video, so they aren’t included.</small>
        </form>
      </details>
<?php if (!$ai): ?>
      <small>The AI Assistant isn’t switched on for this workspace yet.</small>
<?php endif; ?>
    </section>

    <section class="au-card">
      <?= icon('chat-circle-text') ?>
      <h2>Auto-replies</h2>
      <p>When a new comment contains a word you choose, like “price” or “hours”, atwebtech answers it right away.</p>
      <details>
        <summary class="ws-btn ws-btn--dark">Set up</summary>
        <form action="/app/automations" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="create_reply">
          <label class="bio-field"><span>Words or phrases, separated by commas</span><input class="idea-input" name="keywords" maxlength="300" required placeholder="price, how much, cost"></label>
          <label class="bio-field"><span>The reply</span><textarea class="idea-input" name="text" rows="3" maxlength="500" required placeholder="Thanks for asking! Our prices are at yourbusiness.com/menu"></textarea></label>
          <?= $checks($replyChannels, 'No connected channel allows replies from atwebtech yet.') ?>
          <button type="submit" class="ws-btn ws-btn--dark">Turn on</button>
          <small>Only new comments, one reply per comment, up to 25 a day. Auto-replies show in Community marked “Auto-reply sent”.</small>
        </form>
      </details>
    </section>
  </div>
<?php endif; ?>

  <section class="set-block" aria-labelledby="au-running">
    <h2 id="au-running" class="tm-h">Your automations <small><?= count($items) ?></small></h2>
<?php if (!$items): ?>
    <p class="wsp-muted">None yet.<?= $paid ? ' Set one up above and it starts working within minutes.' : '' ?></p>
<?php else: ?>
    <ul class="au-list" role="list">
<?php foreach ($items as $a): $on = (int) $a['enabled'] === 1; ?>
      <li class="au-item<?= $on ? '' : ' is-off' ?>">
        <?= icon($icons[$a['kind']] ?? 'lightning') ?>
        <div>
          <b><?= e($a['name']) ?></b>
          <small><?= e($summary($a)) ?></small>
          <small><?= $on ? 'On' : 'Paused' ?> · <?= (int) $a['made'] ?> <?= $a['kind'] === 'reply' ? ((int) $a['made'] === 1 ? 'reply' : 'replies') : ((int) $a['made'] === 1 ? 'post' : 'posts') ?> so far<?= $a['last_result'] ? ' · ' . e($a['last_result']) : ($a['last_run_at'] ? '' : ' · Hasn’t run yet') ?></small>
        </div>
<?php if ($canEdit): ?>
        <form action="/app/automations" method="post">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
<?php if ($on && $a['kind'] !== 'reply' && $paid): ?>
          <button type="submit" name="action" value="run" class="ws-btn ws-btn--sm">Run now</button>
<?php endif; ?>
          <button type="submit" name="action" value="toggle" class="ws-btn ws-btn--sm"><?= $on ? 'Pause' : 'Resume' ?></button>
          <button type="submit" name="action" value="delete" class="ws-btn ws-btn--sm ws-btn--ghost" data-confirm="Delete this automation? Posts it already made stay.">Delete</button>
        </form>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>

  <section class="set-block" aria-labelledby="au-more">
    <h2 id="au-more" class="tm-h">Already working for you</h2>
    <ul class="tm-roles" role="list">
      <li><b>Recurring posts</b> In the composer, next to the time, choose “Repeats every week” (or day, 2 weeks, month). After each one publishes, the next is scheduled.</li>
      <li><b>Evergreen recycling</b> Mark posts as evergreen in Publish → Sent and they refill empty slots.</li>
      <li><b>Best times</b> Insights shows when your posts do best, and applies those times to a channel’s schedule in one click.</li>
      <li><b>Monday summary and alerts</b> An email every Monday with last week’s numbers, plus alerts when a post fails or a queue runs empty. Choose them under <a href="/app/account#notifications">Account → Notifications</a>.</li>
    </ul>
  </section>
</div>
