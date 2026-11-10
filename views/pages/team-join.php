<?php
/** @var ?array $invite */
/** @var ?string $owner */
/** @var ?array $person */
/** @var string $token */
$next = '/join/' . $token;
$matches = $invite && $person && strtolower((string) $person['email']) === strtolower((string) $invite['email']);
?>
<div class="auth">
  <main class="auth-main" id="main" tabindex="-1">
    <div class="auth-box tm-join">
      <a class="auth-logo" href="/" aria-label="atwebtech home"><?php partial('partials/logo-mark'); ?></a>
<?php if (!$invite): ?>
      <h1 class="auth-title">This invite isn’t valid</h1>
      <p class="auth-switch">It expired, was cancelled, or was already used. Ask the person who invited you for a new link.</p>
      <a class="auth-submit tm-join-btn" href="<?= $person ? '/app' : '/login' ?>"><?= $person ? 'Go to my workspace' : 'Log in' ?></a>
<?php else: ?>
      <h1 class="auth-title">Join <?= e((string) $owner) ?>’s workspace</h1>
      <p class="auth-switch">You’ve been invited as <b><?= e(Team::ROLES[$invite['role']][0] ?? $invite['role']) ?></b>. <?= e(Team::ROLES[$invite['role']][1] ?? '') ?></p>
<?php if ($matches): ?>
      <form action="/join/<?= e($token) ?>" method="post">
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
        <button type="submit" class="auth-submit tm-join-btn">Accept and open the workspace</button>
      </form>
<?php elseif ($person): ?>
      <p class="form-status is-error" role="alert"><span>This invite is for <?= e($invite['email']) ?>, but you’re logged in as <?= e($person['email']) ?>. Log out, then open the link again.</span></p>
      <form action="/logout" method="post">
        <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
        <button type="submit" class="auth-submit tm-join-btn">Log out</button>
      </form>
<?php else: ?>
      <p class="auth-switch">Use <b><?= e($invite['email']) ?></b> so atwebtech can match the invite.</p>
      <a class="auth-submit tm-join-btn" href="/signup?next=<?= e(rawurlencode($next)) ?>">Create my account</a>
      <p class="auth-switch">Already have an account? <a href="/login?next=<?= e(rawurlencode($next)) ?>">Log in</a></p>
<?php endif; ?>
<?php endif; ?>
    </div>
  </main>
</div>
