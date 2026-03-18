self.addEventListener("install", (event) => {
  // Minimal SW for PWA installability. No aggressive caching of dynamic pages.
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

