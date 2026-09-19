import { appUrl, request } from './portal/api.js';

const DOWNLOAD_KEY = 'aquadissect.offline-packages.v1';
const MODULE_DOWNLOAD_KEY = 'aquadissect.offline-learning-modules.v1';
const DB_NAME = 'aquadissect-offline';
const ATTEMPT_STORE = 'attempts';
const MODULE_PROGRESS_STORE = 'moduleProgress';

function cacheName(manifest) {
  return `aquadissect-package-${manifest.species.slug}-${manifest.packageVersion}`;
}

function storedDownloads() {
  try { return JSON.parse(localStorage.getItem(DOWNLOAD_KEY) || '{}'); } catch { return {}; }
}

function saveDownloads(downloads) {
  localStorage.setItem(DOWNLOAD_KEY, JSON.stringify(downloads));
}

function storedModuleDownloads() {
  try { return JSON.parse(localStorage.getItem(MODULE_DOWNLOAD_KEY) || '{}'); } catch { return {}; }
}

function saveModuleDownloads(downloads) {
  localStorage.setItem(MODULE_DOWNLOAD_KEY, JSON.stringify(downloads));
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
    [...documentCopy.querySelectorAll('script[src], img[src], link[rel="stylesheet"][href], link[rel="modulepreload"][href]')]
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
  if (record.locked) throw new Error(record.lockReason || 'Complete the required Safety & Tools module first.');
  const classQuery = record.availableClassId ? `?classId=${record.availableClassId}` : '';
  const manifestUrl = appUrl(`/api/species/${record.slug}/packages/${record.id}/manifest${classQuery}`);
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

export function isLearningModuleDownloaded(versionId, version) {
  return storedModuleDownloads()[String(versionId)]?.version === version;
}

export function listDownloadedLearningModules() {
  return Object.values(storedModuleDownloads());
}

export async function downloadLearningModule(record) {
  if (!('caches' in window) || !crypto.subtle) throw new Error('Offline downloads are not supported by this browser.');
  const manifestUrl = record.manifestUrl;
  const response = await fetch(manifestUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('The learning-module manifest could not be downloaded.');
  const manifest = (await response.clone().json()).module;
  const name = `aquadissect-module-${record.slug}-${record.version}`;
  const pendingName = `${name}-pending`;
  await caches.delete(pendingName);
  const pending = await caches.open(pendingName);
  try {
    await pending.put(manifestUrl, response.clone());
    const shell = await fetch(appUrl('/'), { credentials: 'same-origin' });
    if (shell.ok) await cacheApplicationShell(pending, shell);
    for (const asset of Object.values(manifest.assetRecords || {})) {
      const assetResponse = await fetch(asset.url, { credentials: 'same-origin' });
      if (!assetResponse.ok) throw new Error('A learning-module asset could not be downloaded.');
      if ((await sha256(assetResponse.clone())) !== asset.sha256.toLowerCase()) {
        throw new Error('A downloaded module asset failed its integrity check.');
      }
      await pending.put(asset.url, assetResponse);
    }
    const finalCache = await caches.open(name);
    for (const item of await pending.keys()) await finalCache.put(item, await pending.match(item));
    await caches.delete(pendingName);
    const downloads = storedModuleDownloads();
    const previous = Object.entries(downloads).filter(([, item]) => item.slug === record.slug);
    downloads[String(record.id)] = {
      versionId: record.id,
      slug: record.slug,
      title: record.title,
      version: record.version,
      cacheName: name,
      downloadedAt: new Date().toISOString(),
    };
    for (const [key, item] of previous) {
      if (item.cacheName !== name) await caches.delete(item.cacheName);
      if (key !== String(record.id)) delete downloads[key];
    }
    saveModuleDownloads(downloads);
    return downloads[String(record.id)];
  } catch (error) {
    await caches.delete(pendingName);
    throw error;
  }
}

export async function removeDownloadedLearningModule(versionId) {
  const downloads = storedModuleDownloads();
  const entry = downloads[String(versionId)];
  if (entry?.cacheName) await caches.delete(entry.cacheName);
  delete downloads[String(versionId)];
  saveModuleDownloads(downloads);
}

function openDatabase() {
  return new Promise((resolve, reject) => {
    const opening = indexedDB.open(DB_NAME, 2);
    opening.onupgradeneeded = () => {
      if (!opening.result.objectStoreNames.contains(ATTEMPT_STORE)) opening.result.createObjectStore(ATTEMPT_STORE, { keyPath: 'clientAttemptUuid' });
      if (!opening.result.objectStoreNames.contains(MODULE_PROGRESS_STORE)) opening.result.createObjectStore(MODULE_PROGRESS_STORE, { keyPath: 'queueId' });
    };
    opening.onsuccess = () => resolve(opening.result);
    opening.onerror = () => reject(opening.error);
  });
}

async function storeAttempt(attempt) {
  const database = await openDatabase();
  await new Promise((resolve, reject) => {
    const transaction = database.transaction(ATTEMPT_STORE, 'readwrite');
    transaction.objectStore(ATTEMPT_STORE).put(attempt);
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
    const requestItems = database.transaction(ATTEMPT_STORE).objectStore(ATTEMPT_STORE).getAll();
    requestItems.onsuccess = () => resolve(requestItems.result);
    requestItems.onerror = () => reject(requestItems.error);
  });
  let synced = 0;
  for (const attempt of attempts) {
    const { lessonKey, ...body } = attempt;
    await request(`/api/lessons/${encodeURIComponent(lessonKey)}/attempts`, { method: 'POST', body });
    await new Promise((resolve, reject) => {
      const transaction = database.transaction(ATTEMPT_STORE, 'readwrite');
      transaction.objectStore(ATTEMPT_STORE).delete(attempt.clientAttemptUuid);
      transaction.oncomplete = resolve;
      transaction.onerror = () => reject(transaction.error);
    });
    synced += 1;
  }
  database.close();
  return synced;
}

async function storeModuleProgress(record) {
  const database = await openDatabase();
  await new Promise((resolve, reject) => {
    const transaction = database.transaction(MODULE_PROGRESS_STORE, 'readwrite');
    transaction.objectStore(MODULE_PROGRESS_STORE).put(record);
    transaction.oncomplete = resolve;
    transaction.onerror = () => reject(transaction.error);
  });
  database.close();
}

export async function submitOrQueueModuleProgress(versionId, progress) {
  const body = { ...progress, source: navigator.onLine ? 'online' : 'offline' };
  if (navigator.onLine) {
    try {
      return await request(`/api/learning-module-versions/${versionId}/progress`, { method: 'POST', body });
    } catch (error) {
      if (!['TypeError', 'NetworkError'].includes(error.name) && navigator.onLine) throw error;
    }
  }
  const queueId = progress.clientAttemptUuid || `${versionId}:${progress.classId}`;
  await storeModuleProgress({ queueId, versionId, ...progress, source: 'offline' });
  return {
    queued: true,
    progress: {
      completedStepIds: progress.completedStepIds,
      completedSteps: progress.completedStepIds.length,
      totalSteps: progress.totalSteps,
      completed: Boolean(progress.answers),
      completedAt: progress.answers ? progress.completedAt : null,
    },
    attempt: null,
  };
}

export async function synchronizeQueuedModuleProgress() {
  if (!navigator.onLine || !('indexedDB' in window)) return 0;
  const database = await openDatabase();
  const queued = await new Promise((resolve, reject) => {
    const items = database.transaction(MODULE_PROGRESS_STORE).objectStore(MODULE_PROGRESS_STORE).getAll();
    items.onsuccess = () => resolve(items.result);
    items.onerror = () => reject(items.error);
  });
  let synced = 0;
  for (const item of queued) {
    const { queueId, versionId, ...body } = item;
    await request(`/api/learning-module-versions/${versionId}/progress`, { method: 'POST', body });
    await new Promise((resolve, reject) => {
      const transaction = database.transaction(MODULE_PROGRESS_STORE, 'readwrite');
      transaction.objectStore(MODULE_PROGRESS_STORE).delete(queueId);
      transaction.oncomplete = resolve;
      transaction.onerror = () => reject(transaction.error);
    });
    synced += 1;
  }
  database.close();
  return synced;
}
