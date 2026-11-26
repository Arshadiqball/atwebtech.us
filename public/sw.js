// Retired. The old service worker sat in front of every page load and, in some browsers, page loads
// through it never finished (Nov 2026). This version removes itself: it clears the old cache,
// unregisters, and reloads any open tab so it loads straight from the network.
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
    await self.registration.unregister();
    const tabs = await self.clients.matchAll({ type: 'window' });
    tabs.forEach((tab) => tab.navigate(tab.url).catch(() => {}));
  })());
});
