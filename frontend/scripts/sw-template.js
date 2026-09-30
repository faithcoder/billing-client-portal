/* Generated at build time. Never cache application HTML or runtime API data. */
const CACHE = "water-static-__VERSION__";
const ASSETS = new Set(__ASSETS__);
const OFFLINE = "__OFFLINE__";
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll([...ASSETS, OFFLINE])),
  );
});
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith("water-static-") && key !== CACHE)
            .map((key) => caches.delete(key)),
        ),
      )
      .then(() => self.clients.claim()),
  );
});
self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== "GET" || url.origin !== self.location.origin) return;
  // Explicit private exclusions apply to navigations too. No fallback for API/documents.
  if (
    /^\/(api|sanctum|storage|documents|receipts|print)(\/|$)/.test(
      url.pathname,
    ) ||
    /\/(receipt|print)(\/|$)/.test(url.pathname) ||
    /\.(pdf|csv|xlsx?)$/i.test(url.pathname)
  )
    return;
  if (ASSETS.has(url.pathname) && !url.search) {
    event.respondWith(
      caches
        .open(CACHE)
        .then(async (cache) => (await cache.match(request)) || fetch(request)),
    );
    return;
  }
  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request).catch(() =>
        caches.open(CACHE).then((cache) => cache.match(OFFLINE)),
      ),
    );
  }
});
