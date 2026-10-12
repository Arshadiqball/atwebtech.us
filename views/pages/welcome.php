<?php
/** @var array $user */
/** @var array $onboarding */
/** @var int $step */
/** @var array $steps */
$total = count($steps);
$state = View::formState('onboarding');
?>
<div class="onb<?= $step === 0 ? ' onb--welcome' : '' ?>">
  <header class="onb-top">
    <div class="onb-top-left">
<?php if ($step > 0): ?>
      <a class="onb-back" href="/welcome<?= $step > 1 ? '?step=' . ($step - 1) : '' ?>"><?= icon('arrow-left') ?><span class="sr-only">Back</span></a>
<?php endif; ?>
      <span class="onb-logo"><?php partial('partials/logo'); ?></span>
    </div>
<?php if ($step > 0): ?>
    <ol class="onb-dots" role="list" aria-label="Question <?= $step ?> of <?= $total ?>">
<?php for ($i = 1; $i <= $total; $i++): ?>
      <li<?= $i === $step ? ' class="is-current" aria-current="step"' : '' ?>><span class="sr-only">Question <?= $i ?></span></li>
<?php endfor; ?>
    </ol>
<?php endif; ?>
    <?php partial('partials/theme-toggle'); ?>
  </header>

<?php if ($step === 0): ?>
<?php partial('partials/hero-tiles', ['modifier' => 'faint']); ?>
  <main class="onb-main onb-main--welcome" id="main" tabindex="-1">
    <h1 class="onb-hello">Hey there <?= e($user['display_name']) ?> <span class="onb-wave" aria-hidden="true">👋</span><br>Welcome to atwebtech</h1>
    <a class="onb-start" href="/welcome?step=1">Let's start<?= icon('arrow-right') ?></a>
  </main>
<?php else:
    $q = $steps[$step];
    $saved = array_filter(explode(',', (string) ($onboarding[$q['field']] ?? '')));
    $options = !empty($q['channels'])
        ? array_map(static fn ($c) => [$c['name'], $c], array_column(integrations(), null, 'slug'))
        : $q['options'];
    $first = empty($q['channels']) ? reset($q['options']) : [];
    $kind = !empty($q['channels']) ? 'channels' : (count($first) > 1 ? ($q['multiple'] ? 'checks' : 'cards') : 'plain');
?>
  <main class="onb-main" id="main" tabindex="-1">
    <form class="onb-form" action="/welcome" method="post" data-onboarding>
      <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
      <input type="hidden" name="step" value="<?= $step ?>">
      <fieldset class="onb-fieldset">
        <legend class="onb-q"><h1><?= e($q['title']) ?></h1></legend>
<?php if ($q['multiple']): ?>
        <p class="sr-only">Choose all that apply.</p>
<?php endif; ?>
<?php if ($state): ?>
        <p class="onb-error" role="alert"><?= e($state['message']) ?></p>
<?php endif; ?>
        <div class="onb-options onb-options--<?= e($kind) ?>">
<?php foreach ($options as $value => $opt):
    $checked = in_array((string) $value, $saved, true);
    $inputId = 'opt-' . $step . '-' . preg_replace('/[^a-z0-9]+/', '-', (string) $value);
?>
          <label class="onb-opt" for="<?= e($inputId) ?>">
            <input type="<?= $q['multiple'] ? 'checkbox' : 'radio' ?>" id="<?= e($inputId) ?>" name="<?= $q['multiple'] ? 'answer[]' : 'answer' ?>" value="<?= e((string) $value) ?>"<?= $checked ? ' checked' : '' ?>>
<?php if ($kind === 'channels'): $ch = $opt[1]; ?>
            <span class="onb-ch" style="--c: <?= e($ch['color']) ?>"><?= brand_mark($ch['icon'], $ch['name'], true) ?></span>
            <span class="onb-opt-label"><?= e($opt[0]) ?></span>
<?php else: ?>
<?php if (isset($opt[1])): ?>
            <span class="onb-emoji onb-emoji--<?= e($opt[2]) ?>" aria-hidden="true"><?= e($opt[1]) ?></span>
<?php endif; ?>
            <span class="onb-opt-text">
              <span class="onb-opt-label"><?= e($opt[0]) ?></span>
<?php if (isset($opt[3])): ?>
              <span class="onb-opt-hint"><?= e($opt[3]) ?></span>
<?php endif; ?>
            </span>
<?php if ($kind === 'checks'): ?>
            <span class="onb-box" aria-hidden="true"><?= icon('check') ?></span>
<?php endif; ?>
<?php endif; ?>
          </label>
<?php endforeach; ?>
        </div>
      </fieldset>
      <div class="onb-actions">
        <button type="submit" class="onb-continue" name="action" value="continue" data-onboarding-continue>Continue<?= icon('arrow-right') ?></button>
        <button type="submit" class="onb-skip" name="action" value="skip">Skip</button>
      </div>
    </form>
  </main>
<?php endif; ?>
</div>
