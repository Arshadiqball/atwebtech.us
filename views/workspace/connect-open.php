<?php
/** @var string $provider  bluesky | mastodon */
/** @var bool $reconnect */
$bluesky = $provider === 'bluesky';
$name = $bluesky ? 'Bluesky' : 'Mastodon';
$token = Security::csrfToken();
?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'plus', 'title' => 'Connect ' . $name, 'actions' => '<a class="ws-btn ws-btn--sm" href="/app/channels">Back to channels</a>']); ?>
  <section class="set-block co-open">
    <form class="tm-form" action="/app/channels/connect/<?= e($provider) ?>" method="post" autocomplete="off">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
<?php if ($reconnect): ?>
      <input type="hidden" name="reconnect" value="1">
<?php endif; ?>
<?php if ($bluesky): ?>
      <p>Bluesky connects with an <b>app password</b>, a separate password you can revoke at any time. atwebtech uses it once to start a session and doesn’t store it.</p>
      <ol class="co-steps">
        <li>In Bluesky, open <b>Settings → Privacy and security → App passwords</b>.</li>
        <li>Click <b>Add app password</b>, name it “atwebtech”, and copy it.</li>
      </ol>
      <div class="tm-row">
        <label class="bio-field"><span>Bluesky handle</span><input class="idea-input" name="handle" required maxlength="253" placeholder="yourname.bsky.social" autocapitalize="off" spellcheck="false"></label>
        <label class="bio-field"><span>App password</span><input class="idea-input" name="app_password" required maxlength="19" placeholder="abcd-efgh-ijkl-mnop" autocapitalize="off" spellcheck="false"></label>
      </div>
<?php else: ?>
      <p>Mastodon runs on many servers. Enter the server your account is on, then approve atwebtech there.</p>
      <label class="bio-field co-instance"><span>Your Mastodon server</span><input class="idea-input" name="instance" required maxlength="253" placeholder="mastodon.social" autocapitalize="off" spellcheck="false"></label>
<?php endif; ?>
      <button type="submit" class="ws-btn ws-btn--dark">Connect <?= e($name) ?></button>
      <p class="wsp-muted"><?= $bluesky ? 'Posts can have up to 300 characters and 4 photos. Replies and mentions show in Community.' : 'Posts can have up to 500 characters and 4 photos or a video. Mentions and replies show in Community.' ?></p>
    </form>
  </section>
</div>
