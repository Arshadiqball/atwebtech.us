<?php
/** @var array $user */
/** @var array $onboarding */
/** @var array $prefill  From Workspace::composeData(), or a partial set (targets, body, date, time, idea) */
/** @var bool $dialog */
$settings = data('workspace');
$yt = data('youtube');
$uid = (int) $user['id'];
$all = array_column(integrations(), null, 'slug');
$mine = Workspace::channels($onboarding);
$networks = array_merge($mine, array_diff(array_keys($all), $mine));
$accounts = Channels::forUser($uid);
$state = $dialog ? null : View::formState('composer');
$old = $state['old'] ?? [];
$errors = $state['errors'] ?? [];
$p = array_merge(['id' => 0, 'targets' => [], 'body' => '', 'title' => '', 'tags' => '', 'meta' => [], 'media' => null, 'first_comment' => '', 'date' => '', 'time' => '', 'idea' => 0], array_filter($prefill, static fn ($v) => $v !== '' && $v !== 0 && $v !== []), $old);
$meta = array_merge(Workspace::youtubeMeta([]), (array) $p['meta']);
if (!$p['targets']) {
    $p['targets'] = [$accounts ? 'c:' . (int) $accounts[0]['id'] : 'n:' . $mine[0]];
}
$picked = array_flip((array) $p['targets']);
$tz = Workspace::timezone($user);
$now = new DateTimeImmutable('now', $tz);
if ($p['date'] === '' || $p['time'] === '') {
    $next = $now->modify('+1 hour')->setTime((int) $now->modify('+1 hour')->format('G'), 0);
    $p['date'] = $p['date'] ?: $next->format('Y-m-d');
    $p['time'] = $p['time'] ?: $next->format('H:i');
}
$px = $dialog ? 'dc' : 'pc';
$editing = (int) $p['id'] > 0;
$library = data('templates');
$featured = $library['items'];
$interests = $library['interests'];
$images = $library['images'];
$personal = Workspace::personalTemplates($uid);
$ai = Assistant::enabled();
$err = static fn (string $key): string => isset($errors[$key]) ? '' : ' hidden';
$limitOf = static fn (string $provider): int => (int) ($settings['limits'][$provider] ?? $settings['post_max']);
$hasYoutube = false;
foreach (array_keys($picked) as $key) {
    $hasYoutube = $hasYoutube || $key === 'n:youtube' || (str_starts_with((string) $key, 'c:') && in_array((int) substr((string) $key, 2), array_map(static fn ($a) => $a['provider'] === 'youtube' ? (int) $a['id'] : 0, $accounts), true));
}
$media = $p['media'];
?>
<form class="cp" action="/app/posts" method="post" novalidate data-cp>
  <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>" data-field="id">
  <input type="hidden" name="idea" value="<?= (int) $p['idea'] ?>" data-field="idea">
  <input type="hidden" name="media_id" value="<?= (int) ($media['id'] ?? 0) ?>" data-field="media_id">
  <input type="hidden" name="return" value="<?= e($dialog ? current_path() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '') : '/app/publish/new') ?>">

  <header class="cp-head">
    <h2 id="<?= $px ?>-title" data-compose-title><?= $editing ? 'Edit Post' : 'Create Post' ?></h2>
    <details class="cp-pop cp-tags" data-cp-pop>
      <summary class="ws-dd-btn"><?= icon('tag') ?><span data-cp-tags-label><?= $p['tags'] !== '' ? e(str_replace(',', ', ', $p['tags'])) : 'Tags' ?></span><?= icon('caret-down') ?></summary>
      <div class="cp-pop-panel">
        <label for="<?= $px ?>-tags">Tags</label>
        <input class="idea-input" id="<?= $px ?>-tags" name="tags" value="<?= e(str_replace(',', ', ', (string) $p['tags'])) ?>" maxlength="200" placeholder="launch, tips" data-field="tags">
        <small>Up to 6, separated by commas. Tags are for organising your posts and aren’t published.</small>
      </div>
    </details>
    <div class="cp-head-end">
      <div class="cp-panels" role="group" aria-label="Side panels" data-cp-panels hidden>
        <button type="button" class="cp-tab" aria-pressed="false" data-cp-tab="templates"><?= icon('file-text') ?>Templates</button>
        <button type="button" class="cp-tab" aria-pressed="false" data-cp-tab="ai"><?= icon('magic-wand') ?>AI Assistant</button>
        <button type="button" class="cp-tab" aria-pressed="false" data-cp-tab="preview"><?= icon('eye') ?>Preview</button>
      </div>
<?php if ($dialog): ?>
      <button type="button" class="ws-icon-btn" aria-pressed="false" data-cp-expand title="Expand"><?= icon('arrows-out') ?><span class="sr-only">Expand</span></button>
      <button type="button" class="ws-icon-btn" data-dialog-close><?= icon('x') ?><span class="sr-only">Close</span></button>
<?php else: ?>
      <a class="ws-icon-btn" href="/app/publish"><?= icon('x') ?><span class="sr-only">Close</span></a>
<?php endif; ?>
    </div>
  </header>

  <div class="cp-body">
    <div class="cp-main">
      <p class="composer-error" role="alert" data-form-error<?= isset($state['message']) ? '' : ' hidden' ?>><?= e($state['message'] ?? '') ?></p>

      <fieldset class="cp-targets" data-cp-targets>
        <legend class="sr-only">Channels</legend>
        <div class="cp-row">
<?php foreach ($accounts as $account): $key = 'c:' . (int) $account['id']; ?>
          <label class="cp-target" title="<?= e($account['name']) ?>">
            <input type="checkbox" id="<?= $px ?>-t-<?= (int) $account['id'] ?>" name="targets[]" value="<?= e($key) ?>" data-provider="<?= e($account['provider']) ?>" data-limit="<?= $limitOf($account['provider']) ?>" data-name="<?= e($account['name']) ?>" data-publish="<?= $account['can_publish'] ? '1' : '0' ?>"<?= isset($picked[$key]) ? ' checked' : '' ?>>
            <?= channel_avatar($account, 'md') ?><span class="sr-only"><?= e($account['name']) ?></span>
          </label>
<?php endforeach; ?>
<?php foreach ($networks as $slug): $ch = $all[$slug]; $key = 'n:' . $slug; ?>
          <label class="cp-target cp-target--net" title="<?= e($ch['name']) ?> (plan only)" style="--c: <?= e($ch['color']) ?>">
            <input type="checkbox" id="<?= $px ?>-t-<?= e($slug) ?>" name="targets[]" value="<?= e($key) ?>" data-provider="<?= e($slug) ?>" data-limit="<?= $limitOf($slug) ?>" data-name="<?= e($ch['name']) ?>" data-publish="0"<?= isset($picked[$key]) ? ' checked' : '' ?>>
            <span class="ws-ch"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span><span class="sr-only"><?= e($ch['name']) ?> (plan only)</span>
          </label>
<?php endforeach; ?>
          <details class="cp-pop cp-picker" data-cp-pop>
            <summary class="cp-add" title="Choose channels"><?= icon('plus') ?><?= icon('caret-down') ?><span class="sr-only">Choose channels</span></summary>
            <div class="cp-pop-panel cp-picker-panel">
              <label class="sr-only" for="<?= $px ?>-search">Search channels</label>
              <input class="idea-input cp-search" id="<?= $px ?>-search" type="search" placeholder="Search channels" autocomplete="off" data-cp-search>
              <div class="cp-picker-head"><b>Channels</b><button type="button" class="ws-link" data-cp-none>Deselect all</button></div>
              <ul class="cp-picker-list" role="list">
<?php foreach ($accounts as $account): ?>
                <li data-cp-name="<?= e(mb_strtolower($account['name'])) ?>"><label for="<?= $px ?>-t-<?= (int) $account['id'] ?>" class="cp-pick"><span class="cp-check" aria-hidden="true"><?= icon('check') ?></span><?= channel_avatar($account, 'xs') ?><?= e($account['name']) ?></label></li>
<?php endforeach; ?>
              </ul>
              <div class="cp-picker-head"><b>Plan only</b><small>Not connected. Saved to your queue, never sent.</small></div>
              <ul class="cp-picker-list" role="list">
<?php foreach ($networks as $slug): $ch = $all[$slug]; ?>
                <li data-cp-name="<?= e(mb_strtolower($ch['name'])) ?>"><label for="<?= $px ?>-t-<?= e($slug) ?>" class="cp-pick"><span class="cp-check" aria-hidden="true"><?= icon('check') ?></span><span class="ws-ch ws-ch--sm" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name']) ?></span><?= e($ch['name']) ?></label></li>
<?php endforeach; ?>
              </ul>
              <a class="cp-connect" href="/app/channels#connect"><?= icon('plus') ?>Connect a Channel</a>
            </div>
          </details>
        </div>
        <p class="composer-field-error" data-error-for="targets"<?= $err('targets') ?>><?= e($errors['targets'] ?? '') ?></p>
        <p class="cp-hint" data-cp-plan-hint hidden><?= icon('info') ?><span>Some picked channels aren’t connected. Those posts are saved as a plan and never sent.</span></p>
      </fieldset>

      <section class="cp-card">
        <div class="cp-card-head">
          <span class="cp-card-for" data-cp-for></span>
          <span class="cp-type" data-yt-only<?= $hasYoutube ? '' : ' hidden' ?>>
            <label title="YouTube shows vertical videos of up to 3 minutes as Shorts."><input type="radio" name="yt_type" value="short" checked> Short</label>
          </span>
        </div>
        <label class="sr-only" for="<?= $px ?>-body">Post text</label>
        <textarea id="<?= $px ?>-body" name="body" rows="6" maxlength="<?= (int) $settings['post_max'] ?>" placeholder="Start writing or get inspired with Templates" aria-describedby="<?= $px ?>-count" data-field="body"<?= isset($errors['body']) ? ' aria-invalid="true"' : '' ?>><?= e($p['body']) ?></textarea>

        <div class="cp-media" data-cp-media-wrap>
          <div class="cp-attached" data-cp-media<?= $media ? '' : ' hidden' ?>>
            <span class="cp-thumb" data-cp-thumb><?php if ($media && $media['kind'] === 'image'): ?><img src="<?= e($media['url']) ?>" alt=""><?php elseif ($media): ?><video src="<?= e($media['url']) ?>" muted preload="metadata"></video><?php endif; ?></span>
            <span class="cp-file"><b data-cp-file-name><?= e($media['name'] ?? '') ?></b><small data-cp-file-meta><?= $media ? e(strtoupper($media['kind']) . ' · ' . ($media['size'] < 1048576 ? max(1, (int) round($media['size'] / 1024)) . ' KB' : number_format($media['size'] / 1048576, 1) . ' MB')) : '' ?></small>
              <span class="cp-progress" data-cp-progress hidden><span></span></span></span>
            <button type="button" class="ws-icon-btn" data-cp-media-remove title="Remove file"><?= icon('trash') ?><span class="sr-only">Remove file</span></button>
          </div>
          <label class="cp-drop" data-cp-drop<?= $media ? ' hidden' : '' ?>>
            <input type="file" class="sr-only" accept="<?= e(implode(',', array_keys(Media::TYPES))) ?>" data-cp-file>
            <?= icon('image') ?><span>Drag &amp; drop or <u>select a file</u></span>
            <small>Images up to 10 MB · videos up to 200 MB</small>
          </label>
          <noscript><p class="cp-hint">Attaching files needs JavaScript.</p></noscript>
          <p class="composer-field-error" data-error-for="media"<?= $err('media') ?>><?= e($errors['media'] ?? '') ?></p>
        </div>

        <div class="cp-tools">
          <button type="button" class="ws-icon-btn" data-cp-pick-file title="Add image or video"><?= icon('plus') ?><span class="sr-only">Add image or video</span></button>
          <details class="cp-pop cp-emoji" data-cp-pop>
            <summary class="ws-icon-btn" title="Emoji"><?= icon('smiley') ?><span class="sr-only">Emoji</span></summary>
            <div class="cp-pop-panel cp-emoji-grid">
<?php foreach (['😀', '😂', '😍', '🥳', '🤔', '😎', '🙌', '👏', '👍', '🙏', '💪', '🔥', '✨', '🎉', '🚀', '💡', '📣', '📅', '✅', '❤️', '⭐', '📈', '🎬', '👇'] as $emoji): ?>
              <button type="button" data-cp-insert="<?= e($emoji) ?>"><?= $emoji ?></button>
<?php endforeach; ?>
            </div>
          </details>
          <button type="button" class="ws-icon-btn" data-cp-insert="#" title="Add a hashtag"><?= icon('hash') ?><span class="sr-only">Add a hashtag</span></button>
          <button type="button" class="ws-icon-btn" data-cp-open="ai" title="AI Assistant"><?= icon('magic-wand') ?><span class="sr-only">AI Assistant</span></button>
          <span class="composer-count" id="<?= $px ?>-count" data-count></span>
        </div>
        <p class="composer-field-error" data-error-for="body"<?= $err('body') ?>><?= e($errors['body'] ?? '') ?></p>
        <label class="cp-first" data-li-only hidden>
          <span>First Comment</span>
          <input class="idea-input" type="text" name="first_comment" maxlength="1250" value="<?= e((string) $p['first_comment']) ?>" placeholder="Your comment" data-field="first_comment"<?= isset($errors['first_comment']) ? ' aria-invalid="true"' : '' ?>>
        </label>
        <p class="composer-field-error" data-error-for="first_comment"<?= $err('first_comment') ?>><?= e($errors['first_comment'] ?? '') ?></p>
        <p class="cp-hint" data-li-only hidden><?= icon('info') ?><span>Posted on LinkedIn right after the main post. If LinkedIn refuses it, the post still goes out and you’ll see a note on Sent.</span></p>
      </section>

      <section class="cp-yt" data-yt-only<?= $hasYoutube ? '' : ' hidden' ?> aria-labelledby="<?= $px ?>-yt">
        <h3 id="<?= $px ?>-yt"><span class="ws-ch ws-ch--sm" style="--c: <?= e($all['youtube']['color'] ?? '#f00') ?>"><?= brand_mark($all['youtube']['icon'] ?? 'youtube', 'YouTube') ?></span>YouTube</h3>
        <div class="cp-yt-grid">
          <label class="cp-field cp-field--wide"><span>Title</span>
            <input class="idea-input" name="title" maxlength="<?= (int) $yt['title_max'] ?>" value="<?= e((string) $p['title']) ?>" placeholder="Enter a title for your video" data-field="title"<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>>
          </label>
          <p class="composer-field-error cp-field--wide" data-error-for="title"<?= $err('title') ?>><?= e($errors['title'] ?? '') ?></p>
          <label class="cp-field"><span>Category</span>
            <select class="idea-input" name="yt_category" data-field="yt_category">
<?php foreach ($yt['categories'] as $id => $name): ?>
              <option value="<?= e((string) $id) ?>"<?= (string) $id === (string) $meta['category'] ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label class="cp-field"><span>Visibility</span>
            <select class="idea-input" name="yt_visibility" data-field="yt_visibility">
<?php foreach ($yt['visibility'] as $value => $name): ?>
              <option value="<?= e($value) ?>"<?= $value === $meta['visibility'] ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
            </select>
          </label>
          <label class="cp-field cp-field--wide"><span>License</span>
            <select class="idea-input" name="yt_license" data-field="yt_license">
<?php foreach ($yt['licenses'] as $value => $name): ?>
              <option value="<?= e($value) ?>"<?= $value === $meta['license'] ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="cp-checks">
          <label><input type="checkbox" name="yt_notify" value="1"<?= $meta['notify'] ? ' checked' : '' ?> data-field="yt_notify"> Notify Subscribers</label>
          <label><input type="checkbox" name="yt_embeddable" value="1"<?= $meta['embeddable'] ? ' checked' : '' ?> data-field="yt_embeddable"> Allow Embedding</label>
          <label><input type="checkbox" name="yt_kids" value="1"<?= $meta['kids'] ? ' checked' : '' ?> data-field="yt_kids"> Made for Kids</label>
          <label class="cp-switch"><input type="checkbox" role="switch" name="yt_synthetic" value="1"<?= $meta['synthetic'] ? ' checked' : '' ?> data-field="yt_synthetic"><span aria-hidden="true"></span> AI-Generated
            <span class="cp-tip" tabindex="0" title="Turn on if the video has realistic altered or synthetic content. YouTube shows a label for it."><?= icon('info') ?><span class="sr-only">Turn on if the video has realistic altered or synthetic content. YouTube shows a label for it.</span></span>
          </label>
        </div>
        <p class="cp-hint"><?= icon('info') ?><span>Until Google reviews atwebtech’s app, YouTube keeps videos uploaded through it private, whatever visibility you choose.</span></p>
      </section>
    </div>

    <aside class="cp-side" data-cp-side hidden>
      <section class="cp-panel" data-cp-panel="templates" hidden aria-label="Templates">
        <h3>Templates</h3>
        <label class="cp-interest" for="<?= $px ?>-interest">Interest
          <select class="idea-input" id="<?= $px ?>-interest" data-cp-interest>
            <option value="">All interests</option>
<?php foreach ($interests as $key => $group): ?>
            <option value="<?= e($key) ?>"><?= e($group[0]) ?></option>
<?php endforeach; ?>
          </select>
        </label>
        <p class="cp-panel-lede">Choose an interest to see starting points you can rewrite. These are original prompts, not posts copied from other accounts.</p>
        <ul class="cp-tpl-list" role="list">
<?php foreach ($personal as $tpl): ?>
          <li data-cp-topic=""><b><?= e($tpl['title']) ?></b><p><?= e(mb_strimwidth($tpl['prompt'], 0, 110, '…')) ?></p><button type="button" class="ws-btn ws-btn--sm" data-cp-use="<?= e($tpl['prompt']) ?>">Use</button></li>
<?php endforeach; ?>
<?php foreach ($featured as $key => $tpl): ?>
          <li data-cp-topic=""><img src="/assets/img/templates/<?= e($images[$key]) ?>" alt="" width="640" height="360" loading="lazy" decoding="async"><b><?= e($tpl[2]) ?></b><p><?= e(mb_strimwidth($tpl[3], 0, 110, '…')) ?></p><button type="button" class="ws-btn ws-btn--sm" data-cp-use="<?= e($tpl[3]) ?>">Use</button></li>
<?php endforeach; ?>
<?php foreach ($interests as $key => $group): foreach ($group[1] as $post): ?>
          <li data-cp-topic="<?= e($key) ?>" hidden><img src="/assets/img/templates/<?= e($images[$key]) ?>" alt="" width="640" height="360" loading="lazy" decoding="async"><b><?= e($post[0]) ?></b><p><?= e(mb_strimwidth($post[1], 0, 110, '…')) ?></p><button type="button" class="ws-btn ws-btn--sm" data-cp-use="<?= e($post[1]) ?>">Use</button></li>
<?php endforeach; endforeach; ?>
        </ul>
        <a class="ws-link" href="/app/create?tab=templates">Manage templates</a>
      </section>

      <section class="cp-panel" data-cp-panel="ai" hidden aria-label="AI Assistant">
        <h3>AI Assistant</h3>
<?php if ($ai): ?>
        <label for="<?= $px ?>-ai">What do you want to write about?</label>
        <textarea class="idea-input" id="<?= $px ?>-ai" rows="5" maxlength="1000" placeholder="e.g. A short video showing how we pack each order by hand, for first-time customers, ending with a link to the shop" data-ai-prompt></textarea>
        <p class="cp-panel-lede"><?= icon('lightbulb') ?>Pro tip: say what it’s about, who it’s for and what you want people to do.</p>
        <button type="button" class="ws-btn ws-btn--dark" data-ai-go><?= icon('sparkle') ?>Generate</button>
        <p class="composer-field-error" role="alert" data-ai-error hidden></p>
        <div class="cp-ai-out" data-ai-out hidden>
          <p data-ai-text></p>
          <div><button type="button" class="ws-btn ws-btn--sm ws-btn--green" data-ai-insert="replace">Use this</button><button type="button" class="ws-btn ws-btn--sm" data-ai-insert="append">Add below</button></div>
        </div>
        <p class="cp-panel-foot">AI can get facts wrong. Read every word before you post. <a href="https://platform.openai.com/docs/guides/prompt-engineering" target="_blank" rel="noopener noreferrer">Learn more about prompts and the limits of AI</a></p>
<?php else: ?>
        <p class="cp-panel-lede">The AI Assistant isn’t switched on for this workspace yet. Once the site owner adds an OpenAI key, you can draft posts from a short brief here.</p>
<?php endif; ?>
      </section>

      <section class="cp-panel" data-cp-panel="preview" hidden aria-label="Preview">
        <h3 data-pv-title>Preview</h3>
        <div class="pv" data-pv>
          <div class="pv-yt" data-pv-yt hidden>
            <div class="pv-frame" data-pv-media><span class="pv-empty"><?= icon('play') ?>Your video shows here</span></div>
            <div class="pv-info">
              <b data-pv-name></b>
              <strong data-pv-heading></strong>
              <p data-pv-text></p>
            </div>
          </div>
          <div class="pv-post" data-pv-post hidden>
            <div class="pv-who">
              <span class="pv-face" data-pv-face aria-hidden="true"></span>
              <span class="pv-id"><b data-pv-name2></b><small data-pv-meta></small></span>
            </div>
            <p data-pv-text2></p>
            <div class="pv-img" data-pv-media2></div>
            <div class="pv-react" data-pv-react hidden>
              <span><?= icon('thumbs-up') ?>Like</span>
              <span><?= icon('chat-circle-text') ?>Comment</span>
              <span><?= icon('arrows-clockwise') ?>Repost</span>
              <span><?= icon('paper-plane-tilt') ?>Send</span>
            </div>
          </div>
          <p class="pv-blank" data-pv-blank>See your post’s preview here.</p>
        </div>
        <p class="cp-panel-foot">Previews are approximate. Each network crops and trims text in its own way.</p>
      </section>
    </aside>
  </div>

  <footer class="cp-foot">
    <span class="cp-foot-start">
      <label class="cp-another" data-cp-another-wrap hidden><input type="checkbox" data-cp-another> Create Another</label>
      <button type="submit" class="ws-btn ws-btn--danger" name="action" value="delete" formnovalidate data-confirm="Delete this post?" data-compose-delete<?= $editing ? '' : ' hidden' ?>><?= icon('trash') ?>Delete</button>
    </span>
    <span class="cp-foot-end">
      <button type="submit" class="ws-btn" name="action" value="draft">Save Draft</button>
      <span class="cp-when">
        <?= icon('calendar-blank') ?>
        <label><span class="sr-only">Date (<?= e(Workspace::timezoneLabel($tz)) ?> time)</span><input type="date" name="date" min="<?= e($now->format('Y-m-d')) ?>" max="<?= e($now->modify('+1 year')->format('Y-m-d')) ?>" value="<?= e($p['date']) ?>" data-field="date"></label>
        <label><span class="sr-only">Time</span><input type="time" name="time" step="60" value="<?= e($p['time']) ?>" data-field="time"></label>
      </span>
      <button type="submit" class="ws-btn ws-btn--green" name="action" value="plan" data-cp-schedule>Schedule Post</button>
    </span>
    <p class="composer-field-error cp-foot-error" data-error-for="date"<?= $err('date') ?>><?= e($errors['date'] ?? '') ?></p>
  </footer>
</form>
