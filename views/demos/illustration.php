<?php
/** @var string $kind collab | mobile | linkbio | ai */
?>
<?php if ($kind === 'collab'): ?>
<div class="ill ill--collab" aria-hidden="true">
  <div class="ill-card ill-post">
    <img src="/assets/img/posts/bread-240.webp" alt="" width="240" height="240" loading="lazy" decoding="async">
    <div class="ill-lines"><b>Morning set collab</b><span>Draft for Instagram</span></div>
  </div>
  <div class="ill-card ill-comment">
    <span class="avatar avatar--sm avatar--threads">SK</span>
    <p><b>Sam</b> Love it. Can we try the oat mug in the first photo?</p>
  </div>
  <div class="ill-card ill-approve">
    <span class="ill-btn ill-btn--green"><?= icon('check') ?>Approve</span>
    <span class="ill-btn"><?= icon('pencil-simple') ?>Request changes</span>
  </div>
</div>
<?php elseif ($kind === 'mobile'): ?>
<div class="ill ill--mobile" aria-hidden="true">
  <div class="ill-phone">
    <div class="ill-phone-bar"><?php partial('partials/logo-mark', ['class' => 'app-logo']); ?><b>Queue</b></div>
    <div class="ill-q"><span class="ill-q-time"><?= icon('clock') ?>Today, 6:00 PM</span><div class="ill-q-row"><img src="/assets/img/posts/plants-240.webp" alt="" width="240" height="240" loading="lazy" decoding="async"><span>Planter restock is back in stock</span></div></div>
    <div class="ill-q"><span class="ill-q-time"><?= icon('clock') ?>Tomorrow, 9:30 AM</span><div class="ill-q-row"><img src="/assets/img/posts/wheel-240.webp" alt="" width="240" height="240" loading="lazy" decoding="async"><span>Behind the wheel</span></div></div>
  </div>
  <div class="ill-card ill-pill"><?= icon('device-mobile') ?>Designed for mobile browsers</div>
</div>
<?php elseif ($kind === 'linkbio'): ?>
<div class="ill ill--linkbio" aria-hidden="true">
  <div class="ill-phone ill-phone--bio">
    <img class="ill-bio-avatar" src="/assets/img/posts/mugs-240.webp" alt="" width="240" height="240" loading="lazy" decoding="async">
    <b>Marigold Ceramics</b>
    <small>Handmade stoneware for slow mornings</small>
    <span class="ill-link ill-link--hi">Shop the autumn collection</span>
    <span class="ill-link">Book a wheel workshop</span>
    <span class="ill-link">Stoneware care guide</span>
  </div>
</div>
<?php elseif ($kind === 'ai'): ?>
<div class="ill ill--ai" aria-hidden="true">
  <div class="ill-card ill-ai">
    <p class="ill-ai-label"><?= icon('sparkle') ?>Writing assistant</p>
    <p class="ill-ai-q">What would you like to write about?</p>
    <div class="ill-ai-draft">New autumn mugs, three glazes, warm and short</div>
    <div class="ill-ai-tools"><span>Warmer</span><span>Shorter</span><span>For LinkedIn</span></div>
  </div>
  <span class="ill-ai-btn"><?= icon('sparkle') ?>Generate draft</span>
</div>
<?php endif; ?>
