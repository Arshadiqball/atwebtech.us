// Meta Pixel for atwebtech. Loaded only when META_PIXEL_ID is set (see app/Meta.php).
// Respects the Global Privacy Control signal. Conversions queued by the server are fired with
// the same event ID the server sent to the Conversions API, so Meta counts each one once.
(() => {
  'use strict';
  const tag = document.currentScript || document.querySelector('script[data-pixel]');
  const pixel = tag && tag.dataset.pixel;
  if (!pixel || !/^\d{5,20}$/.test(pixel) || navigator.globalPrivacyControl === true) return;

  /* Meta's standard loader */
  if (!window.fbq) {
    const n = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
    window.fbq = n; if (!window._fbq) window._fbq = n;
    n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
    const s = document.createElement('script');
    s.async = true; s.src = 'https://connect.facebook.net/en_US/fbevents.js';
    document.head.appendChild(s);
  }

  window.fbq('init', pixel);
  if (tag.dataset.pageview === '1') window.fbq('track', 'PageView');

  window.atwMeta = (events) => {
    (events || []).forEach((ev) => {
      if (ev && typeof ev.event === 'string') window.fbq('track', ev.event, ev.data || {}, { eventID: ev.id });
    });
  };
  const queued = document.getElementById('meta-events');
  if (queued) {
    try { window.atwMeta(JSON.parse(queued.textContent || '[]')); } catch (e) { /* ignore */ }
  }
})();
