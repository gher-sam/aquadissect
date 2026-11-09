const SHELL_CACHE = 'aquadissect-shell-v25';
// Refresh scoped media with network-aware locks and concurrent permission checks.
const SHELL_CACHE_PREFIX = 'aquadissect-shell-';
const scopeUrl = new URL(self.registration.scope);
const withinScope = (url) => url.origin === scopeUrl.origin && url.pathname.startsWith(scopeUrl.pathname);
const fromScope = (path = '') => new URL(path, scopeUrl).href;
importScripts(fromScope('aquadissect-offline-media.js'));
self.addEventListener('message', event => { event.waitUntil(self.AquaOfflineMedia.message(event)); });

async function cacheResponse(cache, request) {
  try {
    const response = await fetch(request);
    if (response.ok) await cache.put(request, response.clone());
    return response;
  } catch {
    return null;
  }
}

async function cacheApplicationShell() {
  const cache = await caches.open(SHELL_CACHE);
  const shellRequest = new Request(fromScope('offline'), { credentials: 'omit', cache: 'reload' });
  const shellResponse = await cacheResponse(cache, shellRequest);
  const fixedUrls = [
    fromScope('manifest.webmanifest'),
    fromScope('pwa-icons/icon-192.png'),
    fromScope('pwa-icons/icon-512.png'),
    fromScope('pwa-icons/maskable-512.png'),
    fromScope('pwa-icons/apple-touch-icon.png'),
  ];
  await Promise.allSettled(fixedUrls.map((url) => cacheResponse(cache, new Request(url, { credentials: 'same-origin', cache: 'reload' }))));

  if (shellResponse?.ok) {
    const shellHtml = await shellResponse.text();
    const embeddedUrls = [...shellHtml.matchAll(/<(?:img|source)\b[^>]*\bsrc=["']([^"']+)["']/gi)]
      .map((match) => new URL(match[1], scopeUrl).href)
      .filter((url) => withinScope(new URL(url)));
    await Promise.allSettled([...new Set(embeddedUrls)]
      .map((url) => cacheResponse(cache, new Request(url, { credentials: 'same-origin', cache: 'reload' }))));
  }

  const buildManifestUrl = fromScope('build/manifest.json');
  const buildManifestResponse = await cacheResponse(cache, new Request(buildManifestUrl, { credentials: 'same-origin', cache: 'reload' }));
  if (!buildManifestResponse?.ok) return;

  const buildManifest = await buildManifestResponse.json();
  const buildUrls = new Set();
  for (const entry of Object.values(buildManifest)) {
    for (const path of [entry.file, ...(entry.css || []), ...(entry.assets || [])].filter(Boolean)) {
      buildUrls.add(fromScope(`build/${path}`));
    }
  }
  await Promise.allSettled([...buildUrls].map((url) => cacheResponse(cache, new Request(url, { credentials: 'same-origin', cache: 'reload' }))));
}

self.addEventListener('install', (event) => {
  event.waitUntil(cacheApplicationShell().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames
      .filter((name) => name.startsWith(SHELL_CACHE_PREFIX) && name !== SHELL_CACHE)
      .map((name) => caches.delete(name)));
    // Refresh unowned legacy media copies without changing queued learning records.
    await self.AquaOfflineMedia.migrate();
    await self.clients.claim();
  })());
});

async function navigationResponse(request) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const response = await fetch(request);
    return response;
  } catch {
    return (await cache.match(fromScope('offline')))
      || new Response('AquaDissect is offline. Reconnect and prepare your lessons for offline viewing in this browser.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
      });
  }
}

async function cacheFirst(request) {
  const shell = await caches.open(SHELL_CACHE);
  const cached = await shell.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response.ok) {
    const cache = await caches.open(SHELL_CACHE);
    await cache.put(request, response.clone());
  }
  return response;
}

async function networkFirstResource(request) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const response = await fetch(request);
    if (response.ok) await cache.put(request, response.clone());
    return response;
  } catch {
    return (await cache.match(request))
      || new Response('This AquaDissect resource is not available offline.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
      });
  }
}

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (!withinScope(url)) return;
  if (self.AquaOfflineMedia.protectedRoute(event.request.url)) {
    event.respondWith(self.AquaOfflineMedia.resource(event));
    return;
  }
  // Source handover must never fall back to any shared offline cache.
  if (url.pathname.includes('/source-handover') || url.pathname.includes('/auth/google/') || url.pathname.includes('/auth/gmail/') || url.pathname.endsWith('/admin/email-delivery') || url.pathname.includes('/student-setup/') || url.pathname.includes('/student-password-setup/') || url.pathname.endsWith('/change-password') || url.pathname.endsWith('/activate')) {
    event.respondWith(fetch(event.request));
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(navigationResponse(event.request));
    return;
  }

  const buildPath = fromScope('build/');
  const buildManifestUrl = fromScope('build/manifest.json');
  const iconPath = fromScope('pwa-icons/');
  if (event.request.url === buildManifestUrl) {
    event.respondWith(networkFirstResource(event.request));
    return;
  }

  if (event.request.url.startsWith(buildPath)
    || event.request.url.startsWith(iconPath)
    || event.request.url === fromScope('manifest.webmanifest')) {
    event.respondWith(cacheFirst(event.request));
    return;
  }

  // Other APIs and staff exports must never fall back to a media cache.
  event.respondWith(fetch(event.request));
});
