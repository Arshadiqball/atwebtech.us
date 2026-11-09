<?php
/** @var array $user */
/** @var array|null $bioPage */
/** @var array $bioLinks */
/** @var string $suggested */
$token = Security::csrfToken();
$form = View::formState('bio');
$linkForm = View::formState('bio-link');
$old = ($form['old'] ?? []) + ($bioPage ?? []) + ['handle' => $suggested, 'title' => $user['display_name'], 'bio' => '', 'accent' => Bio::ACCENTS[0], 'published' => 0];
$errors = $form['errors'] ?? [];
$linkErrors = $linkForm['errors'] ?? [];
$url = Bio::publicUrl((string) $old['handle']);
$totalClicks = array_sum(array_map(static fn ($l) => (int) $l['clicks'], $bioLinks));
?>
<div class="wsp set bio">
  <header class="set-head bio-head">
    <div class="wsp-title">
      <span class="ws-avatar tfa-avatar" aria-hidden="true"><?= icon('link-simple') ?></span>
      <div>
        <h1>Link in Bio</h1>
        <p class="set-kicker">One link for every profile. Put it in your Instagram, TikTok, and X bio.</p>
      </div>
    </div>
<?php if ($bioPage && $bioPage['published']): ?>
    <div class="bio-head-actions">
      <button type="button" class="ws-btn" data-copy-text="<?= e($url) ?>"><?= icon('paperclip') ?><span aria-live="polite">Copy link</span></button>
      <a class="ws-btn ws-btn--dark" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= icon('arrow-square-out') ?>View live page</a>
    </div>
<?php endif; ?>
  </header>

<?php if ($bioPage): ?>
  <section class="bio-stats" aria-label="Page stats">
    <div><b><?= number_format((int) $bioPage['views']) ?></b><span>Page views</span></div>
    <div><b><?= number_format($totalClicks) ?></b><span>Link clicks</span></div>
    <div><b><?= (int) $bioPage['views'] > 0 ? round($totalClicks / (int) $bioPage['views'] * 100) . '%' : '—' ?></b><span>Click rate</span></div>
    <div><b class="bio-state <?= $bioPage['published'] ? 'is-live' : '' ?>"><?= $bioPage['published'] ? 'Live' : 'Hidden' ?></b><span>Status</span></div>
  </section>
<?php endif; ?>

  <div class="bio-grid">
    <div class="bio-edit">
      <section class="set-block" aria-labelledby="bio-page-title">
        <h2 class="bio-sec-title" id="bio-page-title"><?= $bioPage ? 'Page details' : 'Step 1: Set up your page' ?></h2>
<?php if ($form && $form['status'] === 'error'): ?>
        <p class="bill-warn"><?= icon('warning-circle') ?><?= e($form['message']) ?></p>
<?php endif; ?>
        <form class="bio-form" action="/app/link-in-bio" method="post" data-bio-form>
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <label class="bio-field">
            <span>Page address</span>
            <span class="bio-handle"><span><?= e(preg_replace('#^https?://#', '', public_origin())) ?>/l/</span><input class="idea-input" name="handle" value="<?= e($old['handle']) ?>" maxlength="30" required pattern="[a-z0-9][a-z0-9-]{1,28}[a-z0-9]" autocomplete="off" data-bio-live="handle"></span>
            <?php if (isset($errors['handle'])): ?><small class="field-error"><?= e($errors['handle']) ?></small><?php endif; ?>
          </label>
          <label class="bio-field">
            <span>Title</span>
            <input class="idea-input" name="title" value="<?= e($old['title']) ?>" maxlength="80" required data-bio-live="title">
            <?php if (isset($errors['title'])): ?><small class="field-error"><?= e($errors['title']) ?></small><?php endif; ?>
          </label>
          <label class="bio-field">
            <span>Short description <small>(optional)</small></span>
            <textarea class="idea-input" name="bio" rows="3" maxlength="280" data-bio-live="bio"><?= e((string) $old['bio']) ?></textarea>
            <?php if (isset($errors['bio'])): ?><small class="field-error"><?= e($errors['bio']) ?></small><?php endif; ?>
          </label>
          <fieldset class="bio-field bio-accents">
            <legend>Brand color</legend>
<?php foreach (Bio::ACCENTS as $c): ?>
            <label class="bio-swatch" style="--c: <?= e($c) ?>"><input type="radio" name="accent" value="<?= e($c) ?>"<?= $old['accent'] === $c ? ' checked' : '' ?> data-bio-live="accent"><span class="sr-only"><?= e($c) ?></span></label>
<?php endforeach; ?>
          </fieldset>
          <label class="bio-toggle">
            <input type="checkbox" name="published" value="1"<?= !empty($old['published']) ? ' checked' : '' ?>>
            <span><b>Publish my page</b><small>Anyone with the link can see it. Turn off to hide it.</small></span>
          </label>
          <button type="submit" class="ws-btn ws-btn--dark bio-save"><?= $bioPage ? 'Save changes' : 'Create my page' ?></button>
        </form>
      </section>

      <section class="set-block" aria-labelledby="bio-links-title">
        <h2 class="bio-sec-title" id="bio-links-title"><?= $bioPage ? 'Links' : 'Step 2: Add your links' ?> <small><?= count($bioLinks) ?>/<?= Bio::MAX_LINKS ?></small></h2>
<?php if (!$bioPage): ?>
        <p class="bill-note"><?= icon('info') ?>Create your page first, then add buttons for your shop, booking page, latest video, or anything else.</p>
<?php else: ?>
<?php if ($linkForm && $linkForm['status'] === 'error'): ?>
        <p class="bill-warn"><?= icon('warning-circle') ?><?= e($linkForm['message']) ?></p>
<?php endif; ?>
        <ul class="bio-links" role="list">
<?php foreach ($bioLinks as $i => $link): ?>
          <li class="bio-link">
            <form action="/app/link-in-bio/links" method="post" class="bio-link-edit">
              <input type="hidden" name="_token" value="<?= e($token) ?>">
              <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
              <input class="idea-input" name="label" value="<?= e($link['label']) ?>" maxlength="80" aria-label="Button label" required>
              <input class="idea-input" name="url" value="<?= e($link['url']) ?>" maxlength="500" aria-label="Web address" required>
              <span class="bio-link-clicks"><?= number_format((int) $link['clicks']) ?> clicks</span>
              <span class="bio-link-actions">
                <button type="submit" name="action" value="update" class="ws-btn ws-btn--sm">Save</button>
                <button type="submit" name="action" value="up" class="ws-icon-btn" formnovalidate<?= $i === 0 ? ' disabled' : '' ?>><?= icon('arrow-up') ?><span class="sr-only">Move up</span></button>
                <button type="submit" name="action" value="down" class="ws-icon-btn" formnovalidate<?= $i === count($bioLinks) - 1 ? ' disabled' : '' ?>><?= icon('arrow-down') ?><span class="sr-only">Move down</span></button>
                <button type="submit" name="action" value="delete" class="ws-icon-btn" formnovalidate><?= icon('trash') ?><span class="sr-only">Delete link</span></button>
              </span>
            </form>
          </li>
<?php endforeach; ?>
        </ul>
<?php if (count($bioLinks) < Bio::MAX_LINKS): ?>
        <form action="/app/link-in-bio/links" method="post" class="bio-link-add">
          <input type="hidden" name="_token" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="add">
          <input class="idea-input" name="label" placeholder="Button label, e.g. Shop the collection" maxlength="80" required value="<?= e($linkForm['old']['label'] ?? '') ?>">
          <input class="idea-input" name="url" placeholder="https://" maxlength="500" required value="<?= e($linkForm['old']['url'] ?? '') ?>">
          <button type="submit" class="ws-btn ws-btn--dark"><?= icon('plus') ?>Add link</button>
          <?php foreach ($linkErrors as $msg): ?><small class="field-error"><?= e($msg) ?></small><?php endforeach; ?>
        </form>
<?php endif; ?>
<?php endif; ?>
      </section>
    </div>

    <aside class="bio-preview" aria-label="Preview">
      <p class="bio-preview-label">Preview</p>
      <div class="bio-phone" style="--accent: <?= e($old['accent']) ?>" data-bio-preview>
        <span class="bio-phone-avatar" data-bio-initial><?= e(mb_strtoupper(mb_substr((string) $old['title'], 0, 1))) ?></span>
        <b data-bio-title><?= e($old['title']) ?></b>
        <p data-bio-bio><?= e((string) $old['bio']) ?></p>
<?php foreach ($bioLinks ?: [['label' => 'Your first link'], ['label' => 'Another link']] as $link): ?>
        <span class="bio-phone-btn"><?= e($link['label']) ?></span>
<?php endforeach; ?>
        <small class="bio-phone-foot">Made with atwebtech</small>
      </div>
    </aside>
  </div>
</div>
