const SHELL_CACHE = 'aquadissect-shell-v12';
const SHELL_CACHE_PREFIX = 'aquadissect-shell-';
const scopeUrl = new URL(self.registration.scope);
const withinScope = (url) => url.origin === scopeUrl.origin && url.pathname.startsWith(scopeUrl.pathname);
const fromScope = (path = '') => new URL(path, scopeUrl).href;

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
    // Remove legacy personalized HTML from package caches; retain downloaded lesson assets.
    for (const name of cacheNames.filter(name => name.startsWith('aquadissect-package-') || name.startsWith('aquadissect-module-'))) {
      const cache = await caches.open(name);
      for (const request of await cache.keys()) {
        const response = await cache.match(request);
        if (response?.headers.get('content-type')?.includes('text/html')) await cache.delete(request);
      }
    }
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
      || new Response('AquaDissect is offline. Reconnect once, then download a species package for offline study.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
      });
  }
}

async function cacheFirst(request) {
  const cached = await caches.match(request, { ignoreSearch: true });
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
    return (await cache.match(request, { ignoreSearch: true }))
      || new Response('This AquaDissect resource is not available offline.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
      });
  }
}

async function cachedResponseFor(request) {
  const cached = await caches.match(request, { ignoreSearch: true });
  const range = request.headers.get('range');
  if (!cached || !range) return cached;

  const match = /^bytes=(\d*)-(\d*)$/i.exec(range.trim());
  if (!match) return cached;
  const bytes = await cached.arrayBuffer();
  let start = match[1] ? Number(match[1]) : Math.max(0, bytes.byteLength - Number(match[2] || 0));
  let end = match[2] && match[1] ? Number(match[2]) : bytes.byteLength - 1;
  end = Math.min(end, bytes.byteLength - 1);
  if (!Number.isFinite(start) || !Number.isFinite(end) || start < 0 || start > end || start >= bytes.byteLength) {
    return new Response(null, { status: 416, headers: { 'Content-Range': `bytes */${bytes.byteLength}` } });
  }

  const headers = new Headers(cached.headers);
  headers.set('Accept-Ranges', 'bytes');
  headers.set('Content-Length', String(end - start + 1));
  headers.set('Content-Range', `bytes ${start}-${end}/${bytes.byteLength}`);
  return new Response(bytes.slice(start, end + 1), { status: 206, statusText: 'Partial Content', headers });
}

async function networkWithOfflineFallback(request) {
  try {
    return await fetch(request);
  } catch {
    return (await cachedResponseFor(request))
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
  // Source handover must never fall back to any shared offline cache.
  if (url.pathname.includes('/source-handover')) {
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

  event.respondWith(networkWithOfflineFallback(event.request));
});
