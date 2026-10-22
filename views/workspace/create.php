<?php
/** @var array $user */
/** @var string $tab */
/** @var string $view */
/** @var string $tag */
/** @var string $set */
/** @var string $category */
/** @var string $network */
/** @var string $q */
/** @var ?array $board */
/** @var array $personal */
$token = Security::csrfToken();
$library = data('templates');
$discover = $library['items'];
$categories = $library['categories'];
$tplNetworks = $library['networks'];
$images = $library['images'];
if (!isset($tplNetworks[$network])) {
    $network = '';
}
$excerpt = static fn (string $text, int $len): string => mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len)) . '…' : $text;
$ideaJson = static fn (array $idea): string => json_encode(['id' => (int) $idea['id'], 'title' => $idea['title'], 'body' => (string) $idea['body'], 'tags' => str_replace(',', ', ', (string) $idea['tags']), 'group' => (int) ($idea['group_id'] ?? 0)], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
$url = static fn (array $params): string => '/app/create?' . http_build_query(array_filter($params, static fn ($v) => $v !== '' && $v !== null));

ob_start();
if ($tab === 'ideas'): ?>
    <a class="ws-btn ws-btn--tint" href="/demo#ai" title="Try idea generation in the demo"><?= icon('magic-wand') ?>Generate Ideas<span class="ws-chip">Demo</span></a>
    <a class="ws-btn" href="/app/create/idea" data-idea-open="{}"><?= icon('plus') ?>New Idea</a>
<?php elseif ($tab === 'templates'): ?>
    <a class="ws-btn" href="<?= e($url(['tab' => 'templates', 'set' => 'personal'])) ?>#new-template"><?= icon('plus') ?>New Template</a>
<?php endif;
$actions = (string) ob_get_clean();

$renderTemplate = static function (string $key, string $emoji, string $title, string $prompt, bool $own = false, string $image = '', string $channel = '', string $postTitle = '', string $video = '') use ($token, $excerpt): void {
    $compose = ['body' => $prompt];
    if ($video !== '') {
        $compose['video'] = '/assets/video/templates/' . $video;
    } elseif ($image !== '') {
        $compose['image'] = '/assets/img/templates/' . $image;
    }
    if ($channel !== '') {
        $compose['channel'] = $channel;
    }
    if ($postTitle !== '') {
        $compose['title'] = $postTitle;
    }
    ?>
      <li class="tpl-card">
<?php if ($video !== ''): ?>
        <video src="/assets/video/templates/<?= e($video) ?>" muted playsinline autoplay loop preload="metadata"></video>
<?php elseif ($image !== ''): ?>
        <img src="/assets/img/templates/<?= e($image) ?>" alt="" width="640" height="360" loading="lazy" decoding="async">
<?php else: ?>
        <span class="tpl-emoji" aria-hidden="true"><?= e($emoji) ?></span>
<?php endif; ?>
        <h3 class="tpl-title"><?= e($title) ?></h3>
        <p class="tpl-text"><?= e($excerpt($prompt, 120)) ?></p>
        <div class="tpl-actions">
          <a class="ws-btn ws-btn--sm" href="/app/publish/new?template=<?= e($key) ?>" data-compose="<?= e(json_encode($compose, JSON_UNESCAPED_UNICODE)) ?>"><?= icon('note-pencil') ?>Write post</a>
          <form action="/app/templates" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="key" value="<?= e($key) ?>">
            <button type="submit" class="ws-btn ws-btn--sm ws-btn--ghost" name="action" value="idea"><?= icon('lightbulb') ?>Save as idea</button>
          </form>
<?php if ($own): ?>
          <form action="/app/templates" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= (int) substr($key, 1) ?>">
            <button type="submit" class="ws-icon-btn" name="action" value="delete" data-confirm="Delete this template?" title="Delete template"><?= icon('trash') ?><span class="sr-only">Delete template</span></button>
          </form>
<?php endif; ?>
        </div>
      </li>
<?php };
?>
<div class="wsp">
<?php partial('workspace/page-head', ['icon' => 'lightbulb', 'title' => 'Create', 'actions' => $actions]); ?>

  <div class="wsp-bar">
    <nav class="wsp-tabs" aria-label="Create">
      <a href="/app/create"<?= $tab === 'ideas' ? ' aria-current="page"' : '' ?>>Ideas</a>
      <a href="/app/create?tab=templates"<?= $tab === 'templates' ? ' aria-current="page"' : '' ?>>Templates</a>
      <a href="/app/create?tab=feeds"<?= $tab === 'feeds' ? ' aria-current="page"' : '' ?>>Feeds</a>
    </nav>
<?php if ($tab === 'ideas'): ?>
    <div class="wsp-filters">
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn<?= $tag !== '' ? ' is-active' : '' ?>" aria-expanded="false" aria-controls="tag-menu" data-menu-toggle><?= $tag !== '' ? '#' . e($tag) : 'Tags' ?><?= icon('caret-down') ?></button>
        <div class="ws-menu" id="tag-menu" hidden data-menu-panel>
          <a class="ws-menu-item" href="<?= e($url(['view' => $view === 'gallery' ? 'gallery' : ''])) ?>"<?= $tag === '' ? ' aria-current="true"' : '' ?>>All tags</a>
<?php foreach ($board['tags'] as $t): ?>
          <a class="ws-menu-item" href="<?= e($url(['view' => $view === 'gallery' ? 'gallery' : '', 'tag' => $t])) ?>"<?= $t === $tag ? ' aria-current="true"' : '' ?>>#<?= e($t) ?></a>
<?php endforeach; ?>
<?php if (!$board['tags']): ?>
          <p class="ws-menu-note">Add tags to ideas to filter them here.</p>
<?php endif; ?>
        </div>
      </div>
      <nav class="ws-seg" aria-label="Layout">
        <a href="<?= e($url(['tag' => $tag])) ?>"<?= $view === 'board' ? ' aria-current="page"' : '' ?>><?= icon('kanban') ?>Board</a>
        <a href="<?= e($url(['view' => 'gallery', 'tag' => $tag])) ?>"<?= $view === 'gallery' ? ' aria-current="page"' : '' ?>><?= icon('squares-four') ?>Gallery</a>
      </nav>
    </div>
<?php endif; ?>
  </div>

<?php if ($tab === 'ideas'):
    $matches = static fn (array $idea): bool => $tag === '' || in_array($tag, $idea['tag_list'], true);
?>
<?php if ($view === 'board'): ?>
  <div class="bd" data-idea-board data-token="<?= e($token) ?>">
<?php foreach ($board['columns'] as $col): $ideas = array_values(array_filter($col['ideas'], $matches)); $cid = (int) $col['id']; ?>
    <section class="bd-col" data-col="<?= $cid ?>" data-label="<?= e($col['name']) ?>" aria-labelledby="col-<?= $cid ?>">
      <header class="bd-col-head">
        <h2 id="col-<?= $cid ?>"><?= e($col['name']) ?></h2>
        <span class="bd-count" data-count><?= count($ideas) ?></span>
        <span class="bd-col-tools">
          <a class="ws-icon-btn ws-icon-btn--sm" href="/app/create/idea?group=<?= $cid ?>" data-idea-open='{"group":<?= $cid ?>}' title="New idea in <?= e($col['name']) ?>"><?= icon('plus') ?><span class="sr-only">New idea in <?= e($col['name']) ?></span></a>
<?php if ($cid > 0): ?>
          <span class="ws-dd" data-ws-dd>
            <button type="button" class="ws-icon-btn ws-icon-btn--sm" aria-expanded="false" aria-controls="col-menu-<?= $cid ?>" data-menu-toggle><?= icon('dots-three') ?><span class="sr-only"><?= e($col['name']) ?> options</span></button>
            <span class="ws-menu ws-menu--form" id="col-menu-<?= $cid ?>" hidden data-menu-panel>
              <form action="/app/groups" method="post" class="ws-menu-form">
                <input type="hidden" name="_token" value="<?= e($token) ?>">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <label for="rename-<?= $cid ?>">Group name</label>
                <span class="ws-inline">
                  <input class="idea-input" id="rename-<?= $cid ?>" name="name" type="text" maxlength="60" required value="<?= e($col['name']) ?>">
                  <button type="submit" class="ws-btn ws-btn--sm" name="action" value="rename">Rename</button>
                </span>
              </form>
              <form action="/app/groups" method="post">
                <input type="hidden" name="_token" value="<?= e($token) ?>">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <button type="submit" class="ws-menu-item ws-menu-item--danger" name="action" value="delete" data-confirm="Delete “<?= e($col['name']) ?>”? Its ideas move to Unassigned."><?= icon('trash') ?>Delete group</button>
              </form>
            </span>
          </span>
<?php endif; ?>
        </span>
      </header>
      <ul class="bd-list" role="list" data-list>
<?php foreach ($ideas as $idea): ?>
        <li class="bd-card" draggable="true" data-idea="<?= (int) $idea['id'] ?>">
          <a class="bd-card-link" href="/app/create/idea?id=<?= (int) $idea['id'] ?>" data-idea-open="<?= e($ideaJson($idea)) ?>">
            <span class="bd-card-title"><?= e($idea['title']) ?></span>
<?php if ((string) $idea['body'] !== ''): ?>
            <span class="bd-card-text"><?= e($excerpt((string) $idea['body'], 110)) ?></span>
<?php endif; ?>
<?php if ($idea['tag_list']): ?>
            <span class="bd-tags"><?php foreach ($idea['tag_list'] as $t): ?><span class="bd-tag">#<?= e($t) ?></span><?php endforeach; ?></span>
<?php endif; ?>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
      <a class="bd-add" href="/app/create/idea?group=<?= $cid ?>" data-idea-open='{"group":<?= $cid ?>}'><?= icon('plus') ?>New Idea</a>
    </section>
<?php endforeach; ?>
    <div class="bd-newgroup ws-dd" data-ws-dd>
      <button type="button" class="bd-newgroup-btn" aria-expanded="false" aria-controls="new-group" data-menu-toggle><?= icon('plus') ?>New Group</button>
      <form class="ws-menu ws-menu--form ws-menu-form" id="new-group" action="/app/groups" method="post" hidden data-menu-panel>
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <label for="new-group-name">Group name</label>
        <span class="ws-inline">
          <input class="idea-input" id="new-group-name" name="name" type="text" maxlength="60" required placeholder="e.g. Next week">
          <button type="submit" class="ws-btn ws-btn--sm ws-btn--green" name="action" value="create">Add</button>
        </span>
      </form>
    </div>
    <p class="sr-only" aria-live="polite" data-board-live></p>
  </div>
<?php else: ?>
<?php $all = []; foreach ($board['columns'] as $col) { foreach (array_filter($col['ideas'], $matches) as $idea) { $all[] = $idea + ['group_name' => $col['name']]; } } ?>
<?php if ($all): ?>
  <ul class="gl" role="list">
<?php foreach ($all as $idea): ?>
    <li class="bd-card gl-card">
      <a class="bd-card-link" href="/app/create/idea?id=<?= (int) $idea['id'] ?>" data-idea-open="<?= e($ideaJson($idea)) ?>">
        <span class="gl-group"><?= e($idea['group_name']) ?></span>
        <span class="bd-card-title"><?= e($idea['title']) ?></span>
<?php if ((string) $idea['body'] !== ''): ?>
        <span class="bd-card-text"><?= e($excerpt((string) $idea['body'], 180)) ?></span>
<?php endif; ?>
<?php if ($idea['tag_list']): ?>
        <span class="bd-tags"><?php foreach ($idea['tag_list'] as $t): ?><span class="bd-tag">#<?= e($t) ?></span><?php endforeach; ?></span>
<?php endif; ?>
      </a>
    </li>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('lightbulb') ?></span>
    <h2><?= $tag !== '' ? 'No ideas tagged #' . e($tag) : 'No ideas yet' ?></h2>
    <p>Ideas you save will show up here as cards.</p>
    <a class="ws-btn ws-btn--green" href="/app/create/idea" data-idea-open="{}"><?= icon('plus') ?>New Idea</a>
  </div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($tab === 'templates'):
    $needle = mb_strtolower($q);
    $filtered = array_filter($discover, static fn (array $t): bool => ($category === '' || $t[0] === $category) && ($needle === '' || str_contains(mb_strtolower($t[2] . ' ' . $t[3]), $needle)));
?>
  <div class="tpl-bar">
    <nav class="tpl-sets" aria-label="Template sets">
      <a href="/app/create?tab=templates"<?= $set === 'discover' ? ' aria-current="page"' : '' ?>>Discover <span><?= count($discover) ?></span></a>
      <a href="/app/create?tab=templates&amp;set=personal"<?= $set === 'personal' ? ' aria-current="page"' : '' ?>>Personal <span><?= count($personal) ?></span></a>
    </nav>
<?php if ($set === 'discover'): ?>
    <nav class="tpl-nets" aria-label="Networks">
      <a href="<?= e($url(['tab' => 'templates', 'q' => $q, 'category' => $category])) ?>"<?= $network === '' ? ' aria-current="page"' : '' ?>>All</a>
<?php foreach ($tplNetworks as $slug => $group): ?>
      <a href="<?= e($url(['tab' => 'templates', 'network' => $slug])) ?>"<?= $slug === $network ? ' aria-current="page"' : '' ?>><?= e($group[0]) ?></a>
<?php endforeach; ?>
    </nav>
    <div class="wsp-filters">
      <form class="tpl-search" action="/app/create" method="get" role="search">
        <input type="hidden" name="tab" value="templates">
<?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
        <label class="sr-only" for="tpl-q">Search templates</label>
        <?= icon('magnifying-glass') ?>
        <input id="tpl-q" name="q" type="search" value="<?= e($q) ?>" placeholder="Search" autocomplete="off">
      </form>
      <div class="ws-dd" data-ws-dd>
        <button type="button" class="ws-dd-btn<?= $category !== '' ? ' is-active' : '' ?>" aria-expanded="false" aria-controls="tpl-filter" data-menu-toggle><?= icon('funnel') ?><?= e($categories[$category] ?? 'Filter') ?><?= icon('caret-down') ?></button>
        <div class="ws-menu ws-menu--end" id="tpl-filter" hidden data-menu-panel>
          <a class="ws-menu-item" href="<?= e($url(['tab' => 'templates', 'q' => $q])) ?>"<?= $category === '' ? ' aria-current="true"' : '' ?>>All categories</a>
<?php foreach ($categories as $key => $label): ?>
          <a class="ws-menu-item" href="<?= e($url(['tab' => 'templates', 'category' => $key, 'q' => $q])) ?>"<?= $key === $category ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
        </div>
      </div>
    </div>
<?php endif; ?>
  </div>

<?php if ($set === 'personal'):
    $tplState = View::formState('template');
?>
  <section class="tpl-new" id="new-template" aria-labelledby="new-template-title">
    <h2 id="new-template-title">New template</h2>
    <p>Save a prompt you reuse often. Templates are private to your workspace.</p>
<?php if ($tplState): ?><p class="composer-error" role="alert"><?= e($tplState['message']) ?></p><?php endif; ?>
    <form action="/app/templates" method="post" class="tpl-new-form">
      <input type="hidden" name="_token" value="<?= e($token) ?>">
      <label for="tpl-title">Title</label>
      <input class="idea-input" id="tpl-title" name="title" type="text" maxlength="140" required value="<?= e($tplState['old']['title'] ?? '') ?>"<?= isset($tplState['errors']['title']) ? ' aria-invalid="true" aria-describedby="tpl-title-err"' : '' ?>>
<?php if (isset($tplState['errors']['title'])): ?><p class="composer-field-error" id="tpl-title-err"><?= e($tplState['errors']['title']) ?></p><?php endif; ?>
      <label for="tpl-prompt">Prompt</label>
      <textarea class="idea-input" id="tpl-prompt" name="prompt" rows="3" maxlength="2000" required<?= isset($tplState['errors']['prompt']) ? ' aria-invalid="true" aria-describedby="tpl-prompt-err"' : '' ?>><?= e($tplState['old']['prompt'] ?? '') ?></textarea>
<?php if (isset($tplState['errors']['prompt'])): ?><p class="composer-field-error" id="tpl-prompt-err"><?= e($tplState['errors']['prompt']) ?></p><?php endif; ?>
      <button type="submit" class="ws-btn ws-btn--green" name="action" value="create">Save template</button>
    </form>
  </section>
<?php if ($personal): ?>
  <ul class="tpl-grid" role="list">
<?php foreach ($personal as $t) { $renderTemplate('p' . $t['id'], '📝', $t['title'], $t['prompt'], true); } ?>
  </ul>
<?php endif; ?>

<?php elseif ($network !== ''): $group = $tplNetworks[$network]; ?>
  <section class="tpl-section" aria-labelledby="tpl-net">
    <header class="tpl-section-head">
      <h2 id="tpl-net"><?= e($group[0]) ?></h2>
      <a href="/app/create?tab=templates">All networks<?= icon('x') ?></a>
    </header>
    <ul class="tpl-grid" role="list">
<?php foreach ($group[1] as $i => $post) { $videoNet = in_array($network, ['youtube', 'tiktok'], true); $renderTemplate($network . '-' . $i, $videoNet ? '🎬' : '📝', $post[1], $post[2], false, $videoNet ? '' : $images[$network], $network, $post[3] ?? '', $videoNet ? (string) ($library['videos'][$network][$i] ?? '') : ''); } ?>
    </ul>
  </section>

<?php elseif ($category === '' && $q === ''): ?>
  <section class="tpl-featured" aria-labelledby="tpl-featured-title">
    <p class="tpl-kicker">Featured templates</p>
    <h2 id="tpl-featured-title">Our top picks</h2>
    <p class="tpl-sub">Helpful starting points for your next post.</p>
    <ul class="tpl-grid tpl-grid--3" role="list">
<?php foreach ($library['featured'] as $key) { [, $emoji, $title, $prompt] = $discover[$key]; $renderTemplate($key, $emoji, $title, $prompt, false, $images[$key] ?? ''); } ?>
    </ul>
  </section>
<?php foreach ($categories as $cat => $label):
    $items = array_slice(array_filter($discover, static fn (array $t): bool => $t[0] === $cat), 0, 4, true);
?>
  <section class="tpl-section" aria-labelledby="tpl-<?= e($cat) ?>">
    <header class="tpl-section-head">
      <h2 id="tpl-<?= e($cat) ?>"><?= e($label) ?></h2>
      <a href="<?= e($url(['tab' => 'templates', 'category' => $cat])) ?>">See All<?= icon('caret-right') ?></a>
    </header>
    <ul class="tpl-grid" role="list">
<?php foreach ($items as $key => [, $emoji, $title, $prompt]) { $renderTemplate($key, $emoji, $title, $prompt, false, $images[$key] ?? ''); } ?>
    </ul>
  </section>
<?php endforeach; ?>

<?php else: ?>
  <section class="tpl-section" aria-labelledby="tpl-results">
    <header class="tpl-section-head">
      <h2 id="tpl-results"><?= e($categories[$category] ?? 'Results') ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?></h2>
      <a href="/app/create?tab=templates">Clear<?= icon('x') ?></a>
    </header>
<?php if ($filtered): ?>
    <ul class="tpl-grid" role="list">
<?php foreach ($filtered as $key => [, $emoji, $title, $prompt]) { $renderTemplate($key, $emoji, $title, $prompt, false, $images[$key] ?? ''); } ?>
    </ul>
<?php else: ?>
    <p class="wsp-muted">No templates match. Try another word or category.</p>
<?php endif; ?>
  </section>
<?php endif; ?>

<?php else: ?>
  <div class="wsp-empty">
    <span class="wsp-empty-icon"><?= icon('book-open') ?></span>
    <h2>Feeds <span class="ws-soon">Coming soon</span></h2>
    <p>Follow blogs and news sites so there's always something worth sharing. Feeds aren't available yet. Until then, save links as ideas.</p>
    <a class="ws-btn" href="/app/create/idea" data-idea-open="{}"><?= icon('plus') ?>New Idea</a>
  </div>
<?php endif; ?>
</div>
