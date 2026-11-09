import { request } from './portal/api.js';
import { queuedModuleProgress } from './portal/moduleProgress.js';
import { synchronizeRecords } from './portal/queueSync.js';
import { signOutPending } from './portal/sessionAccess.js';
import { preparedEntries, prepareOfflineMedia, removePreparedMedia } from './offlineMediaAccess.js';
export { registerOfflineWorker } from './offlineMediaAccess.js';

const DB_NAME = 'aquadissect-offline';
const ATTEMPT_STORE = 'attempts';
const MODULE_PROGRESS_STORE = 'moduleProgress';
const selected = (kind, id, classId) => preparedEntries().find(entry => entry.kind === kind && entry.recordId === Number(id) && entry.classId === (classId == null ? null : Number(classId)));

export function isDownloaded(packageId, version, classId = null) { return selected('species', packageId, classId)?.version === version; }
export function isLearningModuleDownloaded(versionId, version, classId = null) { return selected('module', versionId, classId)?.version === version; }
export function downloadedVersionFor(slug, classId = null) {
  return preparedEntries().filter(entry => entry.kind === 'species' && entry.slug === slug && entry.classId === (classId == null ? null : Number(classId))).sort((a,b) => b.preparedAt.localeCompare(a.preparedAt))[0]?.version || null;
}
export function listDownloadedPackages() { return preparedEntries().filter(entry => entry.kind === 'species'); }
export function listDownloadedLearningModules() { return preparedEntries().filter(entry => entry.kind === 'module'); }
export async function downloadPackage(record, classId = record.availableClassId ?? null) {
  if (record.locked) throw new Error(record.lockReason || 'Complete the required Safety & Tools module first.');
  return prepareOfflineMedia({ kind: 'species', recordId: record.id, slug: record.slug, classId });
}
export async function downloadLearningModule(record) { return prepareOfflineMedia({ kind: 'module', recordId: record.id, classId: record.classId ?? null }); }
export async function removeDownloadedPackage(packageId, classId = null) { const entry=selected('species',packageId,classId); if(entry) await removePreparedMedia(entry.key); }
export async function removeDownloadedLearningModule(versionId, classId = null) { const entry=selected('module',versionId,classId); if(entry) await removePreparedMedia(entry.key); }

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
  if (signOutPending()) throw new Error('Sign in again before recording work.');
  const ownerId = window.AQUADISSECT_BOOTSTRAP?.user?.id;
  if (navigator.onLine) {
    try { return await request(`/api/lessons/${encodeURIComponent(lessonKey)}/attempts`, { method: 'POST', body: attempt }); } catch (error) {
      if (!['TypeError', 'NetworkError'].includes(error.name) && navigator.onLine) throw error;
    }
  }
  await storeAttempt({ lessonKey, ...attempt, ownerId, source: 'offline' });
  return { queued: true };
}

export async function synchronizeQueuedAttempts() {
  if (!navigator.onLine || !('indexedDB' in window) || signOutPending()) return { synced: 0, failed: 0, pending: 0 };
  const database = await openDatabase();
  const attempts = await new Promise((resolve, reject) => {
    const requestItems = database.transaction(ATTEMPT_STORE).objectStore(ATTEMPT_STORE).getAll();
    requestItems.onsuccess = () => resolve(requestItems.result);
    requestItems.onerror = () => reject(requestItems.error);
  });
  const ownerId = window.AQUADISSECT_BOOTSTRAP?.user?.id;
  try {
    return await synchronizeRecords(attempts, {
      ownerId,
      canContinue: () => !signOutPending() && window.AQUADISSECT_BOOTSTRAP?.user?.id === ownerId,
      send: ({ lessonKey, ownerId, syncError, lastSyncAt, ...body }) => request(`/api/lessons/${encodeURIComponent(lessonKey)}/attempts`, { method: 'POST', body }),
      remove: item => mutateQueue(database, ATTEMPT_STORE, store => store.delete(item.clientAttemptUuid)),
      failed: (item, error) => mutateQueue(database, ATTEMPT_STORE, store => store.put({ ...item, syncError: error.message, lastSyncAt: new Date().toISOString() })),
    });
  } finally { database.close(); }
}

function mutateQueue(database, storeName, change) {
  return new Promise((resolve, reject) => {
    const transaction = database.transaction(storeName, 'readwrite');
    change(transaction.objectStore(storeName));
    transaction.oncomplete = resolve;
    transaction.onerror = () => reject(transaction.error);
    transaction.onabort = () => reject(transaction.error || new Error('Browser storage could not save the change.'));
  });
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
  if (signOutPending()) throw new Error('Sign in again before recording work.');
  const ownerId = window.AQUADISSECT_BOOTSTRAP?.user?.id;
  const body = { ...progress, source: navigator.onLine ? 'online' : 'offline' };
  if (navigator.onLine) {
    try {
      return await request(`/api/learning-module-versions/${versionId}/progress`, { method: 'POST', body });
    } catch (error) {
      if (!['TypeError', 'NetworkError'].includes(error.name) && navigator.onLine) throw error;
    }
  }
  const queueId = progress.clientAttemptUuid || `${ownerId}:${versionId}:${progress.classId}`;
  await storeModuleProgress({ queueId, versionId, ...progress, ownerId, source: 'offline' });
  return {
    queued: true,
    progress: queuedModuleProgress(progress),
    attempt: null,
  };
}

export async function synchronizeQueuedModuleProgress() {
  if (!navigator.onLine || !('indexedDB' in window) || signOutPending()) return { synced: 0, failed: 0, pending: 0 };
  const database = await openDatabase();
  const queued = await new Promise((resolve, reject) => {
    const items = database.transaction(MODULE_PROGRESS_STORE).objectStore(MODULE_PROGRESS_STORE).getAll();
    items.onsuccess = () => resolve(items.result);
    items.onerror = () => reject(items.error);
  });
  const ownerId = window.AQUADISSECT_BOOTSTRAP?.user?.id;
  try {
    return await synchronizeRecords(queued, {
      ownerId,
      canContinue: () => !signOutPending() && window.AQUADISSECT_BOOTSTRAP?.user?.id === ownerId,
      send: ({ queueId, versionId, ownerId, syncError, lastSyncAt, ...body }) => request(`/api/learning-module-versions/${versionId}/progress`, { method: 'POST', body }),
      remove: item => mutateQueue(database, MODULE_PROGRESS_STORE, store => store.delete(item.queueId)),
      failed: (item, error) => mutateQueue(database, MODULE_PROGRESS_STORE, store => store.put({ ...item, syncError: error.message, lastSyncAt: new Date().toISOString() })),
    });
  } finally { database.close(); }
}

export async function queuedLearningRecords() {
  if (!('indexedDB' in window) || signOutPending()) return [];
  const database = await openDatabase();
  try {
    const lists = await Promise.all([ATTEMPT_STORE, MODULE_PROGRESS_STORE].map(store => new Promise((resolve, reject) => {
      const items = database.transaction(store).objectStore(store).getAll();
      items.onsuccess = () => resolve(items.result.map(item => ({ ...item, kind: store === ATTEMPT_STORE ? 'Lesson' : 'Safety & Tools' })));
      items.onerror = () => reject(items.error);
    })));
    return lists.flat().filter(item => item.ownerId === window.AQUADISSECT_BOOTSTRAP?.user?.id);
  } finally { database.close(); }
}
