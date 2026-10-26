<?php
if (!Assistant::enabled()) {
    return;
}
$avatar = asset('img/sarah.webp');
$verified = '<svg class="sb-verified" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1.5l2.6 1.9 3.2-.1 1 3.1 2.6 1.9-1 3.1 1 3.1-2.6 1.9-1 3.1-3.2-.1L12 22.5l-2.6-1.9-3.2.1-1-3.1-2.6-1.9 1-3.1-1-3.1 2.6-1.9 1-3.1 3.2.1z"/><path d="M7.8 12.2l2.9 2.9 5.6-5.8" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<div class="sb" data-chatbot data-token="<?= e(Security::csrfToken()) ?>" data-welcome="<?= e(Chatbot::WELCOME) ?>" data-avatar="<?= e($avatar) ?>">
  <button type="button" class="sb-launch" aria-haspopup="dialog" aria-controls="sb-dialog" data-sb-open>
    <img src="<?= e($avatar) ?>" alt="" width="40" height="40">
    <span>Ask Sarah</span>
  </button>
  <dialog class="sb-dialog" id="sb-dialog" aria-labelledby="sb-title">
    <div class="sb-card">
      <aside class="sb-profile">
        <div class="sb-band" aria-hidden="true"></div>
        <img class="sb-photo" src="<?= e($avatar) ?>" alt="" width="96" height="96">
        <h2 class="sb-name" id="sb-title">Sarah <?= $verified ?><span class="sr-only">, atwebtech assistant</span></h2>
        <p class="sb-tagline">Ready to help on atwebtech.us</p>
        <ul class="sb-points">
          <li><?= icon('check-circle') ?>Available 24/7</li>
          <li><?= icon('check-circle') ?>Answers product and feature questions</li>
          <li><?= icon('check-circle') ?>Guides visitors through the interactive demo</li>
        </ul>
        <div class="sb-ctas">
          <a class="sb-btn sb-btn--dark" href="/signup">Create account<?= icon('arrow-right') ?></a>
          <a class="sb-btn sb-btn--light" href="/contact?topic=demo">Book a demo</a>
        </div>
      </aside>
      <section class="sb-chat" aria-label="Chat with Sarah">
        <header class="sb-head">
          <img src="<?= e($avatar) ?>" alt="" width="48" height="48">
          <div class="sb-head-text">
            <b>Sarah</b>
            <small>AI Chatbot for atwebtech</small>
          </div>
          <button type="button" class="sb-icon" data-sb-reset title="New chat"><?= icon('note-pencil') ?><span class="sr-only">Start a new chat</span></button>
          <button type="button" class="sb-icon" data-sb-close title="Close"><?= icon('x') ?><span class="sr-only">Close chat</span></button>
        </header>
        <div class="sb-mobile-ctas">
          <a href="/signup">Create account</a>
          <a href="/contact?topic=demo">Book a demo</a>
        </div>
        <div class="sb-log" role="log" aria-live="polite" aria-relevant="additions" tabindex="0" data-sb-log></div>
        <div class="sb-foot">
          <div class="sb-meter" data-sb-meter hidden>
            <span class="sb-bar"><i data-sb-bar></i></span>
            <small data-sb-left></small>
          </div>
          <p class="sb-error" role="alert" data-sb-error hidden></p>
          <div class="sb-preview" data-sb-preview hidden>
            <img alt="Attached screenshot" data-sb-preview-img>
            <button type="button" class="sb-preview-x" data-sb-unattach><?= icon('x') ?><span class="sr-only">Remove attachment</span></button>
          </div>
          <form class="sb-form" data-sb-form>
            <label class="sb-icon sb-icon--box" title="Attach a screenshot">
              <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" data-sb-file>
              <?= icon('paperclip') ?><span class="sr-only">Attach a screenshot</span>
            </label>
            <label class="sr-only" for="sb-input">Message</label>
            <textarea id="sb-input" rows="1" maxlength="800" placeholder="Write a message…" autocomplete="off" data-sb-input></textarea>
            <button type="submit" class="sb-icon sb-icon--box sb-send" data-sb-send><?= icon('paper-plane-tilt') ?><span class="sr-only">Send</span></button>
          </form>
        </div>
      </section>
    </div>
  </dialog>
</div>
