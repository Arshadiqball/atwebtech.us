// Google tag (Google Analytics) for the public atwebtech pages. Loaded only when GOOGLE_TAG_ID is set.
// Skipped on local addresses and when the browser sends the Global Privacy Control signal.
(() => {
  'use strict';
  const tag = document.currentScript || document.querySelector('script[data-google-tag]');
  const id = tag && tag.dataset.googleTag;
  if (!id || !/^(GTM|G|GT|AW)-[A-Z0-9]{4,16}$/.test(id) || navigator.globalPrivacyControl === true) return;
  if (/^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname)) return;

  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());

  const s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/' + (id.indexOf('GTM-') === 0 ? 'gtm.js' : 'gtag/js') + '?id=' + encodeURIComponent(id);
  document.head.appendChild(s);
  if (id.indexOf('GTM-') !== 0) window.gtag('config', id);
})();
