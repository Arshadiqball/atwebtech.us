<?php
/** @var array $user */
/** @var ?array $idea */
/** @var int $group */
/** @var bool $dialog */
$groups = Workspace::groups((int) $user['id']);
$state = $dialog ? null : View::formState('idea');
$errors = $state['errors'] ?? [];
$i = array_merge(
    ['id' => 0, 'title' => '', 'body' => '', 'tags' => '', 'group' => $group],
    $idea ? ['id' => (int) $idea['id'], 'title' => $idea['title'], 'body' => (string) $idea['body'], 'tags' => (string) $idea['tags'], 'group' => (int) $idea['group_id']] : [],
    $state['old'] ?? []
);
$px = $dialog ? 'di' : 'pi';
$editing = (int) $i['id'] > 0;
?>
<form class="composer idea-form" action="/app/ideas" method="post" novalidate data-idea-form>
  <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int) $i['id'] ?>" data-field="id">
  <input type="hidden" name="return" value="<?= $dialog ? '/app/create' : e('/app/create/idea' . ($editing ? '?id=' . (int) $i['id'] : '')) ?>">

  <header class="composer-head">
    <h2 id="<?= $dialog ? 'idea-title' : 'idea-page-title' ?>" data-idea-heading><?= $editing ? 'Edit idea' : 'New idea' ?></h2>
<?php if ($dialog): ?>
    <button type="button" class="ws-icon-btn" data-dialog-close><?= icon('x') ?><span class="sr-only">Close</span></button>
<?php endif; ?>
  </header>
  <p class="composer-error" role="alert" data-form-error<?= isset($state['message']) ? '' : ' hidden' ?>><?= e($state['message'] ?? '') ?></p>

  <div class="idea-field">
    <label for="<?= $px ?>-title">Title</label>
    <input class="idea-input" id="<?= $px ?>-title" name="title" type="text" maxlength="140" required value="<?= e($i['title']) ?>" placeholder="Give your idea a title" data-field="title"<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>>
    <p class="composer-field-error" data-error-for="title"<?= isset($errors['title']) ? '' : ' hidden' ?>><?= e($errors['title'] ?? '') ?></p>
  </div>
  <div class="idea-field">
    <label for="<?= $px ?>-body">Notes</label>
    <textarea class="idea-input" id="<?= $px ?>-body" name="body" rows="5" maxlength="5000" placeholder="Links, angles, a first draft…" data-field="body"><?= e($i['body']) ?></textarea>
    <p class="composer-field-error" data-error-for="body"<?= isset($errors['body']) ? '' : ' hidden' ?>><?= e($errors['body'] ?? '') ?></p>
  </div>
  <div class="idea-row">
    <div class="idea-field">
      <label for="<?= $px ?>-group">Group</label>
      <select class="idea-input" id="<?= $px ?>-group" name="group" data-field="group">
        <option value="0">Unassigned</option>
<?php foreach ($groups as $g): ?>
        <option value="<?= (int) $g['id'] ?>"<?= (int) $g['id'] === (int) $i['group'] ? ' selected' : '' ?>><?= e($g['name']) ?></option>
<?php endforeach; ?>
      </select>
    </div>
    <div class="idea-field">
      <label for="<?= $px ?>-tags">Tags <small>comma separated</small></label>
      <input class="idea-input" id="<?= $px ?>-tags" name="tags" type="text" maxlength="160" value="<?= e(str_replace(',', ', ', $i['tags'])) ?>" placeholder="launch, video" autocomplete="off" data-field="tags">
    </div>
  </div>

  <footer class="composer-foot">
    <span class="composer-foot-start" data-idea-existing<?= $editing ? '' : ' hidden' ?>>
      <button type="submit" class="ws-btn ws-btn--danger" name="action" value="delete" formnovalidate data-confirm="Delete this idea?"><?= icon('trash') ?>Delete</button>
      <a class="ws-btn" href="/app/publish/new?idea=<?= (int) $i['id'] ?>" data-idea-to-post><?= icon('paper-plane-tilt') ?>Create post</a>
    </span>
    <span class="composer-foot-end">
<?php if ($dialog): ?>
      <button type="button" class="ws-btn" data-dialog-close>Cancel</button>
<?php else: ?>
      <a class="ws-btn" href="/app/create">Cancel</a>
<?php endif; ?>
      <button type="submit" class="ws-btn ws-btn--green" name="action" value="save">Save idea</button>
    </span>
  </footer>
</form>
