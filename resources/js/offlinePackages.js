import { appUrl, request } from './portal/api.js';

const DOWNLOAD_KEY = 'aquadissect.offline-packages.v1';
const DB_NAME = 'aquadissect-offline';
const STORE_NAME = 'attempts';

function cacheName(manifest) {
  return `aquadissect-package-${manifest.species.slug}-${manifest.packageVersion}`;
}

function storedDownloads() {
  try { return JSON.parse(localStorage.getItem(DOWNLOAD_KEY) || '{}'); } catch { return {}; }
}

function saveDownloads(downloads) {
  localStorage.setItem(DOWNLOAD_KEY, JSON.stringify(downloads));
}

async function sha256(response) {
  const bytes = await response.arrayBuffer();
  const digest = await crypto.subtle.digest('SHA-256', bytes);
  return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

async function cacheIfAvailable(cache, url) {
  const response = await fetch(url, { credentials: 'same-origin' });
  if (response.ok) await cache.put(url, response);
  return response;
}

async function cacheApplicationShell(cache, shellResponse) {
  await cache.put(appUrl('/'), shellResponse.clone());
  const documentCopy = new DOMParser().parseFromString(await shellResponse.text(), 'text/html');
  const shellUrls = new Set(
    [...documentCopy.querySelectorAll('script[src], link[rel="stylesheet"][href], link[rel="modulepreload"][href]')]
      .map((element) => new URL(element.src || element.href, location.href).href)
      .filter((url) => new URL(url).origin === location.origin),
  );

  const buildManifestUrl = appUrl('/build/manifest.json');
  const buildManifestResponse = await fetch(buildManifestUrl, { credentials: 'same-origin' });
  if (buildManifestResponse.ok) {
    await cache.put(buildManifestUrl, buildManifestResponse.clone());
    const buildManifest = await buildManifestResponse.json();
    for (const entry of Object.values(buildManifest)) {
      for (const path of [entry.file, ...(entry.css || []), ...(entry.assets || [])].filter(Boolean)) {
        shellUrls.add(appUrl(`/build/${path}`));
      }
    }
  }

  for (const shellUrl of shellUrls) await cacheIfAvailable(cache, shellUrl);
}

export function isDownloaded(packageId, version) {
  return storedDownloads()[String(packageId)]?.version === version;
}

export function downloadedVersionFor(slug) {
  return Object.values(storedDownloads()).find((entry) => entry.slug === slug)?.version || null;
}

export async function registerOfflineWorker() {
  if (!('serviceWorker' in navigator) || !window.isSecureContext) return null;
  return navigator.serviceWorker.register(appUrl('/aquadissect-sw.js'), {
    scope: `${appUrl('/')}`,
    updateViaCache: 'none',
  });
}

export async function downloadPackage(record) {
  if (!('caches' in window) || !crypto.subtle) throw new Error('Offline downloads are not supported by this browser.');
  const manifestUrl = appUrl(`/api/species/${record.slug}/packages/${record.id}/manifest`);
  const manifestResponse = await fetch(manifestUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!manifestResponse.ok) throw new Error('The package manifest could not be downloaded.');
  const payload = await manifestResponse.clone().json();
  const manifest = payload.package;
  const pendingName = `${cacheName(manifest)}-pending`;
  await caches.delete(pendingName);
  const pending = await caches.open(pendingName);

  try {
    await pending.put(manifestUrl, manifestResponse.clone());
    const shell = await fetch(appUrl('/'), { credentials: 'same-origin' });
    if (shell.ok) await cacheApplicationShell(pending, shell);

    for (const asset of Object.values(manifest.assetRecords)) {
      const response = await fetch(asset.url, { credentials: 'same-origin' });
      if (!response.ok) throw new Error('A package asset could not be downloaded.');
      if ((await sha256(response.clone())) !== asset.sha256.toLowerCase()) {
        throw new Error('A downloaded asset failed its integrity check. The previous version was kept.');
      }
      await pending.put(asset.url, response);
    }

    const finalName = cacheName(manifest);
    const finalCache = await caches.open(finalName);
    for (const requestItem of await pending.keys()) {
      await finalCache.put(requestItem, await pending.match(requestItem));
    }
    await caches.delete(pendingName);

    const downloads = storedDownloads();
    const previousEntries = Object.entries(downloads).filter(([, entry]) => entry.slug === record.slug);
    downloads[String(record.id)] = {
      packageId: record.id,
      slug: record.slug,
      commonName: record.commonName,
      version: record.version,
      cacheName: finalName,
      downloadedAt: new Date().toISOString(),
    };
    saveDownloads(downloads);
    for (const [key, previous] of previousEntries) {
      if (previous.cacheName !== finalName) await caches.delete(previous.cacheName);
      if (key !== String(record.id)) delete downloads[key];
    }
    saveDownloads(downloads);
    return downloads[String(record.id)];
  } catch (error) {
    await caches.delete(pendingName);
    throw error;
  }
}

export async function removeDownloadedPackage(packageId) {
  const downloads = storedDownloads();
  const entry = downloads[String(packageId)];
  if (entry?.cacheName) await caches.delete(entry.cacheName);
  delete downloads[String(packageId)];
  saveDownloads(downloads);
}

export function listDownloadedPackages() {
  return Object.values(storedDownloads());
}

function openDatabase() {
  return new Promise((resolve, reject) => {
    const opening = indexedDB.open(DB_NAME, 1);
    opening.onupgradeneeded = () => opening.result.createObjectStore(STORE_NAME, { keyPath: 'clientAttemptUuid' });
    opening.onsuccess = () => resolve(opening.result);
    opening.onerror = () => reject(opening.error);
  });
}

async function storeAttempt(attempt) {
  const database = await openDatabase();
  await new Promise((resolve, reject) => {
    const transaction = database.transaction(STORE_NAME, 'readwrite');
    transaction.objectStore(STORE_NAME).put(attempt);
    transaction.oncomplete = resolve;
    transaction.onerror = () => reject(transaction.error);
  });
  database.close();
}

export async function submitOrQueueAttempt(lessonKey, attempt) {
  if (navigator.onLine) {
    try { return await request(`/api/lessons/${encodeURIComponent(lessonKey)}/attempts`, { method: 'POST', body: attempt }); } catch (error) {
      if (!['TypeError', 'NetworkError'].includes(error.name) && navigator.onLine) throw error;
    }
  }
  await storeAttempt({ lessonKey, ...attempt, source: 'offline' });
  return { queued: true };
}

export async function synchronizeQueuedAttempts() {
  if (!navigator.onLine || !('indexedDB' in window)) return 0;
  const database = await openDatabase();
  const attempts = await new Promise((resolve, reject) => {
    const requestItems = database.transaction(STORE_NAME).objectStore(STORE_NAME).getAll();
    requestItems.onsuccess = () => resolve(requestItems.result);
    requestItems.onerror = () => reject(requestItems.error);
  });
  let synced = 0;
  for (const attempt of attempts) {
    const { lessonKey, ...body } = attempt;
    await request(`/api/lessons/${encodeURIComponent(lessonKey)}/attempts`, { method: 'POST', body });
    await new Promise((resolve, reject) => {
      const transaction = database.transaction(STORE_NAME, 'readwrite');
      transaction.objectStore(STORE_NAME).delete(attempt.clientAttemptUuid);
      transaction.oncomplete = resolve;
      transaction.onerror = () => reject(transaction.error);
    });
    synced += 1;
  }
  database.close();
  return synced;
}
