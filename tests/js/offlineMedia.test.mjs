import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createHash, webcrypto } from 'node:crypto';
import { IDBFactory } from 'fake-indexeddb';

const source = readFileSync(new URL('../../public/aquadissect-offline-media.js', import.meta.url), 'utf8');
const scope = 'https://aqua.example.test/course/';
const bytes = '0123456789';
const hash = createHash('sha256').update(bytes).digest('hex');
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; };

class CacheStorage {
  stores = new Map();
  quotaFailure = false;
  async keys() { return [...this.stores.keys()]; }
  async delete(name) { return this.stores.delete(name); }
  async open(name) {
    if (!this.stores.has(name)) this.stores.set(name, new Map());
    const store = this.stores.get(name);
    return {
      put: async (request, response) => { if (this.quotaFailure) throw new DOMException('Storage full', 'QuotaExceededError'); store.set(typeof request === 'string' ? request : request.url, response.clone()); },
      match: async request => store.get(typeof request === 'string' ? request : request.url)?.clone(),
      keys: async () => [...store.keys()].map(url => new Request(url)),
    };
  }
}

function harness() {
  const indexedDB = new IDBFactory();
  const caches = new CacheStorage();
  const state = { online: true, userId: 1, sessionStatus: 200, deniedClasses: new Set(), corrupt: false, assetStatus: 200, assetFetches: 0, holdAsset: null, holdSession: null };
  const windows = new Map(['one', 'two', 'new'].map(id => [id, { id, url: scope+'offline-access', messages: [], postMessage(message) { this.messages.push(message); } }]));
  const assetUrl = (kind = 'species', classId = 10, id = 31) => `${scope}api/${kind === 'species' ? 'species-packages' : 'learning-module-versions'}/${id}/assets/7?mediaPolicy=2&classId=${classId}`;
  const fetch = async input => {
    if (!state.online) throw new TypeError('Network unavailable');
    const url = new URL(typeof input === 'string' ? input : input.url);
    const path = url.pathname.slice('/course/'.length);
    if (path === 'api/session') {
      const status=state.sessionStatus;
      const userId=state.userId;
      if (state.holdSession) { const hold = state.holdSession; state.holdSession = null; hold.entered.resolve(); await hold.release.promise; }
      return Response.json(status===200 ? { user: { id: userId, role: 'student', status: 'active' } } : {message:'Sign in again'}, {status});
    }
    const classId = Number(url.searchParams.get('classId'));
    if (state.deniedClasses.has(classId)) return Response.json({ message: 'Section unavailable' }, { status: 403 });
    if (/\/manifest$/.test(path)) {
      const kind = path.startsWith('api/species/') ? 'species' : 'module';
      const id = Number(path.match(/\/(\d+)\/manifest$/)[1]);
      const manifest = { id, status: 'published', packageVersion: '1.3.0', moduleVersion: '0.2.0', species: { slug: 'rabbitfish', commonName: 'Synthetic Rabbitfish' }, module: { slug: 'lab-safety-tools', title: 'Synthetic Safety & Tools' },
        assetRecords: { video: { url: assetUrl(kind, classId, id), mimeType: 'video/mp4', sha256: hash, sizeBytes: bytes.length } } };
      const response = Response.json({ [kind === 'species' ? 'package' : 'module']: manifest });
      if (state.holdManifest) { const hold = state.holdManifest; state.holdManifest = null; hold.entered.resolve(); await hold.release.promise; }
      return response;
    }
    if (/\/assets\/7$/.test(path)) {
      state.assetFetches += 1;
      const assetStatus = state.assetStatus; const corrupt = state.corrupt;
      if (state.holdAsset) { const hold = state.holdAsset; state.holdAsset = null; hold.entered.resolve(); await hold.release.promise; if (hold.failure) throw new TypeError('Connection interrupted'); }
      return new Response(corrupt ? 'corrupt media' : bytes, { status: assetStatus, headers: { 'Content-Type': 'video/mp4' } });
    }
    return new Response('Missing', { status: 404 });
  };
  let media;
  const restart = () => {
    const self = { registration: { scope }, navigator: { storage: { estimate: async () => ({ quota: 1000000, usage: 0 }) } },
      clients: { get: async id => windows.get(id), matchAll: async () => [...windows.values()] } };
    const context = vm.createContext({ self, indexedDB, caches, fetch, crypto: webcrypto, URL, Response, Request, Headers, Uint8Array, console });
    vm.runInContext(source, context);
    media = self.AquaOfflineMedia;
  };
  restart();
  const rpc = (type, data = {}, clientId = 'one') => new Promise((resolve, reject) => {
    media.message({ source: { id: clientId }, data: { protocol: 2, type, ...data }, ports: [{ postMessage: reply => resolve(structuredClone(reply)) }] }).catch(reject);
  });
  const connect = async (ownerId = state.userId, offline = false, clientId = 'one') => rpc('CONTEXT', { ownerId, offline }, clientId);
  const prepare = (kind = 'species', classId = 10, clientId = 'one') => rpc('PREPARE', { kind, recordId: 31, slug: 'rabbitfish', classId }, clientId);
  const resource = (url = assetUrl(), range = null, clientId = 'one', mode = 'cors') => media.resource({ clientId, request: new Request(url, { headers: range ? { range } : {}, mode }) });
  return { state, caches, indexedDB, windows, restart, rpc, connect, prepare, resource, assetUrl, get media() { return media; } };
}

test('offline media is scoped to the account, section, record and complete URL', async () => {
  const h = harness();
  assert.equal((await h.connect()).ok, true);
  assert.equal((await h.prepare()).ok, true);
  assert.equal((await h.prepare('module')).ok, true);
  h.state.online = false;
  assert.equal(await (await h.resource()).text(), bytes);
  assert.equal(await (await h.resource(h.assetUrl('module'))).text(), bytes);
  assert.equal((await h.resource(h.assetUrl('species', 20))).status, 403);
  assert.equal((await h.resource(h.assetUrl('species', 10, 32))).status, 403);
  assert.equal((await h.resource(h.assetUrl().replace('mediaPolicy=2&', ''))).status, 403);
  assert.equal((await h.resource(h.assetUrl(), null, 'two')).status, 403);
  assert.equal((await h.connect(1, true, 'two')).ok, true);
  assert.equal((await h.resource(h.assetUrl(), null, 'two')).status, 200);
});

test('sign-out locks across clients and worker restarts while retaining reusable media', async () => {
  const h = harness(); await h.connect(); await h.prepare(); await h.connect(1, false, 'two');
  const names = await h.caches.keys();
  const fetches = h.state.assetFetches;
  await h.rpc('LOCK'); h.state.online = false; h.restart();
  assert.deepEqual(await h.caches.keys(), names);
  assert.equal((await h.connect(1, true)).ok, false);
  assert.equal((await h.resource(h.assetUrl(), null, 'two')).status, 403);
  h.state.online = true;
  assert.equal((await h.connect()).ok, true);
  assert.equal(h.state.assetFetches, fetches, 'Unlock should recheck permissions without downloading the media again');
  h.state.online = false;
  assert.equal(await (await h.resource()).text(), bytes);
});

test('another enrolled account never inherits prepared copies of the same lesson', async () => {
  const h = harness(); await h.connect(); await h.prepare();
  h.state.userId = 2;
  const second = await h.connect(2, false, 'two');
  assert.equal(second.ok, true); assert.equal(second.entries.length, 0);
  h.state.online = false;
  assert.equal((await h.resource(h.assetUrl(), null, 'two')).status, 403);
  assert.equal((await h.resource()).status, 403);
  assert.equal((await h.caches.keys()).length, 1);
  assert.ok(h.windows.get('one').messages.some(message => message.type === 'OFFLINE_MEDIA_LOCKED' && message.ownerId === 1));
});

test('the same module in two sections has independent copies and removal', async () => {
  const h = harness(); await h.connect();
  const first = await h.prepare('module', 10); const second = await h.prepare('module', 20);
  assert.notEqual(first.entry.key, second.entry.key);
  assert.notEqual(first.entry.cacheName, second.entry.cacheName);
  assert.equal((await h.rpc('REMOVE', { key: first.entry.key })).ok, true);
  h.state.online = false;
  assert.equal((await h.resource(h.assetUrl('module', 10))).status, 403);
  assert.equal(await (await h.resource(h.assetUrl('module', 20))).text(), bytes);
});

test('a delayed failure from an older account cannot lock a newer signed-in account', async () => {
  const h = harness(); await h.connect(); await h.prepare();
  h.state.userId = 2; await h.connect(2, false, 'two'); await h.prepare('module', 10, 'two');
  await h.rpc('LOCK', { expectedOwnerId: 1 });
  h.state.online = false;
  assert.equal((await h.resource(h.assetUrl('module'), null, 'two')).status, 200);
  assert.equal((await h.resource()).status, 403);
});

test('a stale signed-out tab cannot revoke or list another active account', async () => {
  const h=harness(); await h.connect(); await h.prepare();
  h.state.userId=2; await h.connect(2,false,'two'); await h.prepare('module',10,'two');
  const guest=await h.rpc('GUEST');
  assert.equal(guest.entries.length,0);
  h.state.online=false;
  assert.equal((await h.resource(h.assetUrl('module'),null,'two')).status,200);
});

test('a verified expired session locks retained copies, but a stale guest response cannot undo fresh verification', async () => {
  const h=harness();await h.connect();await h.prepare();
  h.state.sessionStatus=401;
  const hold={entered:deferred(),release:deferred()};h.state.holdSession=hold;
  const guest=h.rpc('GUEST');await hold.entered.promise;
  h.state.sessionStatus=200;await h.connect();hold.release.resolve();await guest;
  h.state.online=false;assert.equal((await h.resource()).status,200);
  h.state.online=true;h.state.sessionStatus=401;await h.rpc('GUEST');
  h.state.online=false;assert.equal((await h.resource()).status,403);
  assert.equal((await h.caches.keys()).length,1);
});

test('corruption and storage failures retain the last verified copy atomically', async () => {
  const h = harness(); await h.connect(); const first = await h.prepare();
  h.state.corrupt = true;
  const corrupt = await h.prepare();
  assert.equal(corrupt.ok, false); assert.equal(corrupt.status, 422);
  h.state.corrupt = false; h.caches.quotaFailure = true;
  const full = await h.prepare();
  assert.equal(full.ok, false); assert.equal(full.status, 507);
  assert.deepEqual(await h.caches.keys(), [first.entry.cacheName]);
  h.caches.quotaFailure = false; h.state.online = false;
  assert.equal(await (await h.resource()).text(), bytes);
});

test('sign-out during media preparation cannot commit or unlock a new copy', async () => {
  const h = harness(); await h.connect();
  const hold = { entered: deferred(), release: deferred() }; h.state.holdAsset = hold;
  const pending = h.prepare(); await hold.entered.promise;
  await h.rpc('LOCK'); hold.release.resolve();
  assert.equal((await pending).ok, false);
  assert.deepEqual(await h.caches.keys(), []);
  h.state.online = false; assert.equal((await h.connect(1, true)).ok, false);
});

test('a delayed identity response cannot undo a newer sign-out lock', async () => {
  const h = harness(); await h.connect();
  const hold = { entered: deferred(), release: deferred() }; h.state.holdSession = hold;
  const pending = h.connect(); await hold.entered.promise;
  await h.rpc('LOCK'); hold.release.resolve();
  assert.equal((await pending).ok, false);
  h.state.online = false; assert.equal((await h.connect(1, true)).ok, false);
});

test('explicit access denial never falls back to cached media', async () => {
  const h = harness(); await h.connect(); await h.prepare('module', 10); await h.prepare('module', 20);
  h.state.deniedClasses.add(20);
  assert.equal((await h.resource(h.assetUrl('module', 20))).status, 403);
  h.state.online = false;
  assert.equal((await h.resource(h.assetUrl('module', 20))).status, 403);
  assert.equal((await h.resource(h.assetUrl('module', 10))).status, 200);
  h.state.online = true; h.state.assetStatus = 401;
  assert.equal((await h.resource(h.assetUrl('module', 10))).status, 401);
  h.state.online = false; assert.equal((await h.resource(h.assetUrl('module', 10))).status, 403);
});

test('ordinary worker sleep and restart preserves a registered active viewing context', async () => {
  const h = harness(); await h.connect(); await h.prepare(); h.state.online = false; h.restart();
  assert.equal(await (await h.resource()).text(), bytes);
});

test('a permission denial during replacement cannot commit a newly unlocked copy', async () => {
  const h = harness(); await h.connect(); const first = await h.prepare('module', 10); await h.prepare('module', 20);
  const hold = { entered: deferred(), release: deferred() }; h.state.holdAsset = hold;
  const pending = h.prepare('module', 10); await hold.entered.promise;
  h.state.deniedClasses.add(10);
  assert.equal((await h.resource(h.assetUrl('module', 10))).status, 403);
  hold.release.resolve(); assert.equal((await pending).ok, false);
  assert.ok((await h.caches.keys()).includes(first.entry.cacheName));
  h.state.online = false;
  assert.equal((await h.resource(h.assetUrl('module', 10))).status, 403);
  assert.equal(await (await h.resource(h.assetUrl('module', 20))).text(), bytes);
});

test('reconnection denial during preparation preserves the prior locked copy', async () => {
  const h = harness(); await h.connect(); const first = await h.prepare();
  const hold = { entered: deferred(), release: deferred() }; h.state.holdAsset = hold;
  const pending = h.prepare(); await hold.entered.promise;
  h.state.deniedClasses.add(10); assert.equal((await h.connect()).ok, true);
  hold.release.resolve(); assert.equal((await pending).ok, false);
  assert.deepEqual(await h.caches.keys(), [first.entry.cacheName]);
  h.state.online = false; assert.equal((await h.resource()).status, 403);
});

test('a delayed positive permission check cannot overwrite a more recent denial', async () => {
  const h = harness(); await h.connect(); await h.prepare('module', 10); await h.prepare('module', 20);
  const hold = { entered: deferred(), release: deferred() }; h.state.holdManifest = hold;
  const pending = h.connect(); await hold.entered.promise;
  h.state.deniedClasses.add(10); assert.equal((await h.resource(h.assetUrl('module', 10))).status, 403);
  hold.release.resolve(); assert.equal((await pending).ok, false);
  h.state.online = false;
  assert.equal((await h.resource(h.assetUrl('module', 10))).status, 403);
  assert.equal((await h.resource(h.assetUrl('module', 20))).status, 200);
});

test('a delayed denial blocks the current generation without restoring a deleted cache index', async () => {
  const h = harness(); await h.connect(); const first = await h.prepare();
  const hold = { entered: deferred(), release: deferred() }; h.state.holdAsset = hold; h.state.assetStatus = 403;
  const pending = h.resource(); await hold.entered.promise;
  h.state.assetStatus = 200; const replacement = await h.prepare(); assert.equal(replacement.ok, true);
  hold.release.resolve(); assert.equal((await pending).status, 403);
  const entry = (await h.rpc('LIST')).entries[0];
  assert.equal(entry.cacheName, replacement.entry.cacheName); assert.equal(entry.allowed, false);
  assert.notEqual(entry.cacheName, first.entry.cacheName);
  await h.connect(); h.state.online = false;
  assert.equal(await (await h.resource()).text(), bytes);
});

test('a denied first preparation cannot create a copy after another request observes the denial', async () => {
  const h = harness(); await h.connect(); const retained = await h.prepare('module', 20);
  const hold = { entered: deferred(), release: deferred() }; h.state.holdAsset = hold;
  const pending = h.prepare(); await hold.entered.promise;
  h.state.deniedClasses.add(10); assert.equal((await h.resource()).status, 403);
  hold.release.resolve(); assert.equal((await pending).ok, false);
  assert.deepEqual(await h.caches.keys(), [retained.entry.cacheName]);
  h.state.online = false; assert.equal((await h.resource()).status, 403);
});

test('a pending network failure cannot fall back to a copy denied by another request', async () => {
  const h = harness(); await h.connect(); await h.prepare('module', 10); await h.prepare('module', 20);
  const hold = { entered: deferred(), release: deferred(), failure: true }; h.state.holdAsset = hold;
  const pending = h.resource(h.assetUrl('module', 10)); await hold.entered.promise;
  h.state.deniedClasses.add(10); assert.equal((await h.resource(h.assetUrl('module', 10))).status, 403);
  h.state.online = false; hold.release.resolve(); assert.equal((await pending).status, 403);
  assert.equal((await h.resource(h.assetUrl('module', 20))).status, 200);
});

test('a pending network failure uses the latest verified cache generation', async () => {
  const h = harness(); await h.connect(); await h.prepare();
  const hold = { entered: deferred(), release: deferred(), failure: true }; h.state.holdAsset = hold;
  const pending = h.resource(); await hold.entered.promise;
  const replacement = await h.prepare(); assert.equal(replacement.ok, true);
  h.state.online = false; hold.release.resolve(); assert.equal(await (await pending).text(), bytes);
  assert.deepEqual(await h.caches.keys(), [replacement.entry.cacheName]);
});

test('a delayed permission refresh cannot restore an obsolete cache generation', async () => {
  const h = harness(); await h.connect(); await h.prepare();
  const hold = { entered: deferred(), release: deferred() }; h.state.holdManifest = hold;
  const pending = h.connect(); await hold.entered.promise;
  const replacement = await h.prepare(); assert.equal(replacement.ok, true);
  hold.release.resolve(); const refreshed = await pending; assert.equal(refreshed.ok, true);
  assert.equal(refreshed.entries[0].cacheName, replacement.entry.cacheName);
  h.state.online = false; assert.equal(await (await h.resource()).text(), bytes);
});

test('a delayed permission refresh cannot recreate a removed prepared copy', async () => {
  const h = harness(); await h.connect(); const first = await h.prepare();
  const hold = { entered: deferred(), release: deferred() }; h.state.holdManifest = hold;
  const pending = h.connect(); await hold.entered.promise;
  assert.equal((await h.rpc('REMOVE', { key: first.entry.key })).ok, true);
  hold.release.resolve(); const refreshed = await pending; assert.equal(refreshed.ok, true);
  assert.equal(refreshed.entries.length, 0); assert.equal((await h.rpc('LIST')).entries.length, 0);
  assert.deepEqual(await h.caches.keys(), []);
});

test('byte ranges support seeking, suffix requests, bounds and invalid inputs', async () => {
  const h = harness(); await h.connect(); await h.prepare(); h.state.online = false;
  const range = await h.resource(h.assetUrl(), 'bytes=2-5');
  assert.equal(range.status, 206); assert.equal(range.headers.get('content-range'), 'bytes 2-5/10'); assert.equal(await range.text(), '2345');
  assert.equal(await (await h.resource(h.assetUrl(), 'bytes=-3')).text(), '789');
  assert.equal(await (await h.resource(h.assetUrl(), 'bytes=8-999')).text(), '89');
  for (const value of ['bytes=99-', 'bytes=5-2', 'bytes=-0', 'bytes=-', 'bytes=0-1,3-4', 'bad']) assert.equal((await h.resource(h.assetUrl(), value)).status, 416);
});

test('migration removes unowned legacy media without deleting current scoped copies', async () => {
  const h = harness(); await h.connect(); const prepared = await h.prepare();
  await h.caches.open('aquadissect-package-rabbitfish-1.3.0'); await h.caches.open('aquadissect-module-tools-0.2.0'); await h.caches.open('unrelated-application');
  await h.media.migrate();
  assert.deepEqual((await h.caches.keys()).sort(), [prepared.entry.cacheName, 'unrelated-application'].sort());
  assert.deepEqual((await h.indexedDB.databases()).map(item => item.name), [h.media.databaseName]);
});

test('messages from outside the application scope and malformed preparations are rejected', async () => {
  const h = harness(); h.windows.set('foreign', { id: 'foreign', url: 'https://foreign.example.test/', postMessage() {} });
  assert.equal((await h.connect(1, false, 'foreign')).status, 403);
  await h.connect();
  assert.equal((await h.rpc('PREPARE', { kind: 'species', recordId: 31, slug: '../private', classId: 10 })).status, 422);
  assert.equal((await h.rpc('PREPARE', { kind: 'species', recordId: 31, slug: 'rabbitfish', classId: null })).status, 422);
  assert.equal((await h.rpc('REMOVE', { key: '2:species:31:10' })).status, 403);
});
