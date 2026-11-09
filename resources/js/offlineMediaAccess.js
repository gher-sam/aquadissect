import { appUrl } from './portal/api.js';
import { offlineMediaLockKey, signOutPending } from './portal/sessionAccess.js';

let registrationPromise;
let connection;
let ownerId = null;
let entries = [];
let unlocked = false;
let generation = 0;
let verification = null;

export function offlineCapability() {
  if (!window.isSecureContext) return 'Offline preparation needs HTTPS or localhost. You can still view lessons while connected to this classroom laptop.';
  if (!navigator.serviceWorker || !window.caches || !window.indexedDB || !window.crypto?.subtle) return 'This browser cannot prepare offline lessons. Connected viewing is still available.';
  return '';
}

export function mediaManifestPath(kind, id, slug, classId = null) {
  const path = kind === 'species' ? `/api/species/${encodeURIComponent(slug)}/packages/${id}/manifest` : `/api/learning-modules/${id}/manifest`;
  const search = new URLSearchParams({ mediaPolicy: '2' });
  if (classId !== null) search.set('classId', String(classId));
  return `${path}?${search}`;
}

export function mediaUrlForSection(value, classId) {
  if (!value) return null;
  const url = new URL(value, location.href);
  url.searchParams.set('mediaPolicy', '2');
  if (classId == null) url.searchParams.delete('classId'); else url.searchParams.set('classId', String(classId));
  return url.href;
}

export function registerOfflineWorker() {
  if (offlineCapability()) return Promise.resolve(null);
  if (!registrationPromise) registrationPromise = (async () => {
    const expectedScope = new URL(appUrl('/'), location.href).href;
    const existing = await navigator.serviceWorker.getRegistration(expectedScope).catch(() => null);
    if (!navigator.onLine && existing?.active && existing.scope === expectedScope) return existing;
    try { return await navigator.serviceWorker.register(appUrl('/aquadissect-sw.js'), { scope: appUrl('/'), updateViaCache: 'none' }); }
    catch (exception) { if (existing?.active && existing.scope === expectedScope) return existing; throw exception; }
  })().catch(exception => { registrationPromise = null; throw exception; });
  return registrationPromise;
}

async function controller() {
  await registerOfflineWorker();
  if (navigator.serviceWorker.controller) return navigator.serviceWorker.controller;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => { navigator.serviceWorker.removeEventListener('controllerchange', changed); reject(new Error('Refresh AquaDissect while connected to finish preparing offline access.')); }, 15000);
    function changed() {
      if (!navigator.serviceWorker.controller) return;
      clearTimeout(timer); navigator.serviceWorker.removeEventListener('controllerchange', changed); resolve(navigator.serviceWorker.controller);
    }
    navigator.serviceWorker.addEventListener('controllerchange', changed);
    changed();
  });
}

async function rpc(type, data = {}, canSend = null) {
  const worker = await controller();
  if (canSend && !canSend()) return { skipped: true };
  return new Promise((resolve, reject) => {
    const channel = new MessageChannel();
    const timer = setTimeout(() => { channel.port1.close(); reject(new Error(type === 'PREPARE' ? 'Preparation timed out. Reconnect and retry; the previous verified copy is retained.' : 'Refresh AquaDissect while connected to update offline access.')); }, type === 'PREPARE' ? 300000 : 30000);
    channel.port1.onmessage = event => {
      clearTimeout(timer); channel.port1.close();
      if (event.data?.protocol !== 2) { reject(new Error('Refresh AquaDissect to update offline access.')); return; }
      if (!event.data.ok) { reject(Object.assign(new Error(event.data.message), { status: event.data.status })); return; }
      resolve(event.data);
    };
    worker.postMessage({ protocol: 2, type, ...data }, [channel.port2]);
  });
}

function notify(type, detail = {}) { window.dispatchEvent(new CustomEvent(type, { detail })); }

function markLocked(affectedOwner, broadcast = true) {
  if (affectedOwner != null && ownerId !== affectedOwner) return;
  const affected = affectedOwner ?? ownerId;
  unlocked = false; entries = []; connection = null; generation += 1;
  if (broadcast) { try { localStorage.setItem(offlineMediaLockKey, affected == null ? '*' : String(affected)); } catch { /* The worker also persists its lock. */ } }
  notify('aquadissect-offline-media-locked', { ownerId: affected });
}

export async function lockOfflineMedia({ automatic = false, ownerOnly = false } = {}) {
  const previousOwner = ownerId;
  const previousVerification = verification;
  markLocked(null);
  const lockGeneration = generation;
  if (offlineCapability()) return;
  await rpc('LOCK', ownerOnly && previousOwner ? { expectedOwnerId: previousOwner, ...(previousVerification == null ? {} : { expectedVerification: previousVerification }) } : {}, automatic ? () => generation === lockGeneration && ownerId === previousOwner : null).catch(() => undefined);
}

export function preparedEntries() { return unlocked && !signOutPending() ? entries : []; }

export function connectOfflineMedia(user, { offline = false, force = false } = {}) {
  if (offlineCapability()) return Promise.resolve({ supported: false, entries: [] });
  if (signOutPending()) return lockOfflineMedia({ automatic: true, ownerOnly: true }).then(() => ({ supported: true, locked: true, entries: [] }));
  if (!user) {
    unlocked = false; entries = []; connection = null;
    return rpc('GUEST').catch(() => undefined).then(() => ({ supported: true, locked: true, entries: [] }));
  }
  if (ownerId === user.id && connection && !force) return connection;
  ownerId = user.id; entries = []; unlocked = false;
  const expectedOwner = ownerId;
  const expectedGeneration = ++generation;
  connection = (async () => {
    let lockValue;
    try { lockValue = localStorage.getItem(offlineMediaLockKey); } catch { /* Worker checks remain authoritative for cached access. */ }
    if (offline && (lockValue === '*' || lockValue === String(user.id))) throw Object.assign(new Error('Connect and sign in again to unlock retained lessons.'), { status: 401 });
    let result;
    try { result = await rpc('CONTEXT', { ownerId: user.id, offline }); }
    catch (exception) { if (exception.status !== 409 || signOutPending()) throw exception; result = await rpc('CONTEXT', { ownerId: user.id, offline }); }
    if (ownerId !== expectedOwner || expectedGeneration !== generation || signOutPending()) throw new Error('This account view has closed.');
    unlocked = true; verification = result.verification; entries = result.entries || [];
    if (!result.offline) { try { if (['*', String(user.id)].includes(localStorage.getItem(offlineMediaLockKey))) localStorage.removeItem(offlineMediaLockKey); } catch { /* No secret is stored here. */ } }
    notify('aquadissect-offline-media-ready');
    return { supported: true, ...result };
  })().catch(exception => { if (ownerId === expectedOwner && expectedGeneration === generation) { connection = null; unlocked = false; entries = []; } throw exception; });
  return connection;
}

export async function prepareOfflineMedia(data) {
  if (offlineCapability()) throw new Error(offlineCapability());
  if (signOutPending() || !unlocked) throw new Error('Reconnect and confirm this account before preparing offline lessons.');
  const result = await rpc('PREPARE', data);
  if (!unlocked || signOutPending()) throw new Error('The account closed during preparation. Its media remains locked.');
  entries = result.entries;
  notify('aquadissect-offline-media-ready');
  return result.entry;
}

export async function removePreparedMedia(key) {
  const result = await rpc('REMOVE', { key });
  if (unlocked) entries = result.entries;
  notify('aquadissect-offline-media-ready');
}

if (typeof window !== 'undefined') {
  window.addEventListener('storage', event => {
    if (event.key === offlineMediaLockKey && (event.newValue === '*' || event.newValue === String(ownerId))) markLocked(ownerId, false);
  });
  window.addEventListener('aquadissect-authorization-lost', () => { void lockOfflineMedia({ ownerOnly: true }); });
  navigator.serviceWorker?.addEventListener('controllerchange', () => { registrationPromise = null; notify('aquadissect-offline-controller-changed'); });
  navigator.serviceWorker?.addEventListener('message', event => {
    if (event.source !== navigator.serviceWorker.controller || event.data?.protocol !== 2) return;
    const data = event.data;
    if (data.type === 'OFFLINE_MEDIA_LOCKED') markLocked(data.ownerId);
    if (data.type === 'OFFLINE_MEDIA_ENTRY_BLOCKED' && data.ownerId === ownerId) notify('aquadissect-offline-media-blocked', data);
    if (data.type === 'OFFLINE_MEDIA_ENTRIES_CHANGED' && data.ownerId === ownerId && unlocked) {
      void rpc('LIST').then(result => { if (unlocked && result.ownerId === ownerId) { entries = result.entries; notify('aquadissect-offline-media-ready'); } }).catch(() => undefined);
    }
  });
}
