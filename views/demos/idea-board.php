<?php
$demo = data('demo');
$columns = ['ideas' => 'Ideas', 'drafting' => 'Drafting', 'review' => 'Ready for review'];
$keys = array_keys($columns);
?>
<div class="demo demo-board app-window" data-board>
  <div class="demo-bar">
    <div class="demo-bar-start">
      <?php partial('partials/logo-mark', ['class' => 'app-logo']); ?>
      <strong>Content board</strong>
    </div>
    <?php partial('partials/demo-label'); ?>
  </div>
  <form class="board-add" data-board-add>
    <label class="sr-only" for="board-new">New idea title</label>
    <input class="input input--sm" id="board-new" type="text" maxlength="80" placeholder="Add an idea..." autocomplete="off">
    <button type="submit" class="btn btn--dark btn--xs"><?= icon('plus') ?>Add idea</button>
  </form>
  <div class="board-cols">
<?php foreach ($columns as $key => $label): $index = array_search($key, $keys, true); ?>
    <section class="board-col board-col--<?= e($key) ?>" data-col="<?= e($key) ?>" data-label="<?= e($label) ?>" aria-labelledby="col-<?= e($key) ?>">
      <header class="board-col-head">
        <h3 id="col-<?= e($key) ?>"><?= e($label) ?></h3>
        <span class="count" data-count><?= count($demo['ideas'][$key]) ?></span>
      </header>
      <ul class="board-list" role="list" data-list>
<?php foreach ($demo['ideas'][$key] as $card): ?>
        <li class="idea-card" draggable="true" data-card>
<?php if ($card['image']): ?>
          <img src="/assets/img/posts/<?= e($card['image']) ?>-240.webp" alt="" width="240" height="240" loading="lazy" decoding="async">
<?php endif; ?>
          <p class="idea-title"><?= e($card['title']) ?></p>
          <p class="idea-note"><?= e($card['note']) ?></p>
          <div class="idea-foot">
            <span class="idea-tags">
<?php foreach ($card['tags'] as $tag): ?>
              <span class="tag tag--<?= e(strtolower($tag)) ?>"><?= e($tag) ?></span>
<?php endforeach; ?>
            </span>
            <span class="idea-moves">
              <button type="button" class="icon-btn" data-move="-1" aria-label="Move back"><?= icon('caret-left') ?></button>
              <button type="button" class="icon-btn" data-move="1" aria-label="Move forward"><?= icon('caret-right') ?></button>
            </span>
          </div>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endforeach; ?>
  </div>
  <p class="demo-foot">Drag cards or use the arrows. Changes stay in your browser and reset on reload.</p>
  <p class="sr-only" aria-live="polite" data-board-live></p>
</div>
