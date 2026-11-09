/* Account-scoped offline viewing. This is an application access control, not DRM. */
(() => {
  const scope = new URL(self.registration.scope);
  const prefix = `aquadissect-media-v2-${encodeURIComponent(scope.pathname)}-`;
  const databaseName = `aquadissect-offline-media-v2:${scope.pathname}`;
  const fromScope = path => new URL(path, scope).href;
  const validId = value => Number.isSafeInteger(value) && value > 0;
  const error = (message, status = 503) => Object.assign(new Error(message), { status });
  const denied = (message = 'Reconnect and sign in before opening prepared media.', status = 403) => new Response(JSON.stringify({ message }), { status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } });
  let opening;

  function database() {
    if (!opening) opening = new Promise((resolve, reject) => {
      const request = indexedDB.open(databaseName, 1);
      request.onupgradeneeded = () => {
        request.result.createObjectStore('access');
        request.result.createObjectStore('entries', { keyPath: 'key' });
        request.result.createObjectStore('contexts', { keyPath: 'clientId' });
      };
      request.onsuccess = () => { request.result.onversionchange = () => request.result.close(); resolve(request.result); };
      request.onerror = () => { opening = null; reject(error('Browser storage is unavailable. Connected viewing still works.')); };
      request.onblocked = () => { opening = null; reject(error('Close other AquaDissect tabs, then retry offline preparation.')); };
    });
    return opening;
  }

  async function read(store, key) {
    const db = await database();
    return new Promise((resolve, reject) => {
      const request = key === undefined ? db.transaction(store).objectStore(store).getAll() : db.transaction(store).objectStore(store).get(key);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async function write(stores, callback) {
    const db = await database();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction(stores, 'readwrite');
      let value;
      transaction.oncomplete = () => resolve(value);
      transaction.onerror = transaction.onabort = () => reject(transaction.error || error('The account changed. Refresh AquaDissect and try again.', 409));
      try { callback(transaction, result => { value = result; }); }
      catch (exception) { transaction.abort(); reject(exception); }
    });
  }

  async function announce(type, ownerId, extra = {}, excluded = '') {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: false });
    for (const client of clients) {
      if (client.id !== excluded && withinScope(client.url)) client.postMessage({ protocol: 2, type, ownerId, ...extra });
    }
  }

  function withinScope(value) {
    try { const url = new URL(value); return url.origin === scope.origin && url.pathname.startsWith(scope.pathname); }
    catch { return false; }
  }

  async function lock(reason = 'signed-out', expectedOwner = null, expectedVerification = null) {
    const owner = await write(['access', 'contexts'], (transaction, result) => {
      const request = transaction.objectStore('access').get('current');
      request.onsuccess = () => {
        const previous = request.result || { revision: 0, ownerId: null };
        if (expectedOwner !== null && previous.ownerId !== expectedOwner) return;
        if (expectedVerification !== null && (previous.verification || 0) !== expectedVerification) return;
        transaction.objectStore('access').put({ ...previous, locked: true, revision: previous.revision + 1 }, 'current');
        transaction.objectStore('contexts').clear();
        result(previous.ownerId);
      };
    });
    if (owner != null) await announce('OFFLINE_MEDIA_LOCKED', owner, { reason });
    return { locked: true, entries: [] };
  }

  async function identity(ownerId, verification = null) {
    let response;
    try { response = await fetch(fromScope('api/session'), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } }); }
    catch { throw error('The server cannot be reached. Use an already prepared copy or reconnect.', 0); }
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.user?.id !== ownerId || payload.user?.status !== 'active') {
      await lock('session-unavailable', ownerId, verification);
      throw error('Reconnect and sign in again to unlock prepared media.', 401);
    }
    return payload.user;
  }

  function manifestUrl(kind, id, slug, classId) {
    const path = kind === 'species' ? `api/species/${encodeURIComponent(slug)}/packages/${id}/manifest` : `api/learning-modules/${id}/manifest`;
    const url = new URL(path, scope);
    url.searchParams.set('mediaPolicy', '2');
    if (classId !== null) url.searchParams.set('classId', String(classId));
    return url.href;
  }

  function protectedRoute(value) {
    if (!withinScope(value)) return false;
    const path = new URL(value).pathname.slice(scope.pathname.length);
    return /^api\/(?:species\/[^/]+\/packages\/\d+\/manifest|species-packages\/\d+\/(?:assets\/\d+|card-preview)|learning-modules\/\d+\/manifest|learning-module-versions\/\d+\/assets\/\d+)$/.test(path);
  }

  async function validateEntry(entry, permitStale, verification) {
    try {
      const response = await fetch(entry.manifestUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      if ([401, 419].includes(response.status)) { await lock('session-unavailable', entry.ownerId, verification); throw error('Sign in again to open prepared media.', 401); }
      if (response.ok) {
        const payload = await response.json();
        const manifest = payload.package || payload.module;
        const allowed = manifest?.id === entry.recordId && manifest?.[entry.kind === 'species' ? 'packageVersion' : 'moduleVersion'] === entry.version
          && (manifest.status === 'published' || (entry.kind === 'module' && manifest.status === 'archived'));
        return { entry: { ...entry, allowed, verifiedAt: new Date().toISOString() }, denied: !allowed };
      }
      return { entry: { ...entry, allowed: response.status >= 500 && permitStale ? entry.allowed : false }, denied: response.status >= 400 && response.status < 500 };
    } catch (exception) {
      if (exception.status === 401) throw exception;
      return { entry: { ...entry, allowed: permitStale ? entry.allowed : false }, denied: false };
    }
  }

  async function context(clientId, ownerId, offline) {
    if (!validId(ownerId)) throw error('Sign in before preparing offline lessons.', 401);
    const previous = await read('access', 'current');
    const revision = previous?.revision || 0;
    let user;
    if (!offline) {
      try { user = await identity(ownerId, previous?.verification || 0); }
      catch (exception) { if (exception.status !== 0) throw exception; }
    }
    if (!user && (!previous || previous.locked || previous.ownerId !== ownerId)) throw error('Reconnect and sign in to unlock this account’s prepared lessons.', 401);
    const owned = (await read('entries')).filter(entry => entry.ownerId === ownerId);
    const validations = user ? await Promise.all(owned.map(entry => validateEntry(entry, previous?.ownerId === ownerId && !previous.locked, previous?.verification || 0))) : owned.map(entry => ({ entry, denied: false }));
    const entries = validations.map(result => result.entry);
    let changedOwner = null;
    const access = await write(['access', 'contexts', 'entries'], (transaction, result) => {
      const request = transaction.objectStore('access').get('current');
      request.onsuccess = () => {
        const latest = request.result;
        if ((latest?.revision || 0) !== revision || (latest?.authorizationRevision || 0) !== (previous?.authorizationRevision || 0)) { transaction.abort(); return; }
        const change = latest?.ownerId !== ownerId || latest?.locked || (user && latest?.role !== user.role);
        const next = { ownerId, role: user?.role || latest.role, locked: false, revision: change ? revision + 1 : revision, verification: (latest?.verification || 0) + (user ? 1 : 0), authorizationRevision: (latest?.authorizationRevision || 0) + (validations.some(result => result.denied) ? 1 : 0) };
        if (change && latest?.ownerId && !latest.locked) changedOwner = latest.ownerId;
        transaction.objectStore('access').put(next, 'current');
        transaction.objectStore('contexts').put({ clientId, ownerId, revision: next.revision });
        if (user) for (const entry of entries) {
          const record = transaction.objectStore('entries').get(entry.key);
          record.onsuccess = () => {
            const current = record.result;
            if (current?.ownerId === ownerId && current.version === entry.version) transaction.objectStore('entries').put({ ...current, allowed: entry.allowed, ...(entry.verifiedAt ? { verifiedAt: entry.verifiedAt } : {}) });
          };
        }
        result(next);
      };
    });
    if (changedOwner) await announce('OFFLINE_MEDIA_LOCKED', changedOwner, { reason: 'account-changed' }, clientId);
    return { ownerId, role: access.role, verification: access.verification, locked: false, entries: (await read('entries')).filter(entry => entry.ownerId === ownerId), offline: !user };
  }

  async function clientAccess(clientId) {
    const [access, client] = await Promise.all([read('access', 'current'), clientId ? read('contexts', clientId) : null]);
    if (!access || access.locked || !client || client.ownerId !== access.ownerId || client.revision !== access.revision) throw error('Reconnect and sign in to unlock prepared media.', 401);
    return access;
  }

  function sameAccess(left, right) { return left.ownerId === right.ownerId && left.revision === right.revision && !right.locked; }
  function sameAuthorization(left, right) { return sameAccess(left, right) && (left.authorizationRevision || 0) === (right.authorizationRevision || 0); }
  const entryKey = (owner, kind, id, classId) => `${owner}:${kind}:${id}:${classId ?? 0}`;

  async function prepare(clientId, data) {
    const access = await clientAccess(clientId);
    const kind = data.kind;
    const classId = data.classId == null ? null : Number(data.classId);
    if (!['species', 'module'].includes(kind) || !validId(data.recordId) || (kind === 'species' && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(data.slug || ''))
      || (classId !== null && !validId(classId)) || (access.role === 'student' && classId === null)) throw error('Choose the section before preparing this lesson.', 422);
    await identity(access.ownerId, access.verification || 0);
    const key = entryKey(access.ownerId, kind, data.recordId, classId);
    const url = manifestUrl(kind, data.recordId, data.slug, classId);
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!response.ok) {
      if ([401, 419].includes(response.status)) await lock('session-unavailable', access.ownerId, access.verification || 0);
      const previous = await read('entries', key);
      if ([403, 404, 410].includes(response.status)) await blockEntry(previous, access);
      throw error('This lesson cannot be prepared. Reconnect and confirm your section access.', response.status);
    }
    const payload = await response.clone().json();
    const manifest = payload.package || payload.module;
    if (manifest?.id !== data.recordId || !(manifest.status === 'published' || (kind === 'module' && manifest.status === 'archived'))) throw error('Only authorized published lessons can be prepared.', 403);
    const assets = Object.values(manifest.assetRecords || {});
    if (!assets.length) throw error('This lesson does not contain verified offline assets.', 422);
    const estimate = await self.navigator?.storage?.estimate?.();
    const size = assets.reduce((total, asset) => total + (Number(asset.sizeBytes) || 0), 0);
    if (estimate?.quota && estimate.quota - estimate.usage < size) throw error('Not enough browser storage. Remove an older prepared copy and try again.', 507);
    const suffix = [...crypto.getRandomValues(new Uint8Array(12))].map(value => value.toString(16).padStart(2, '0')).join('');
    const name = `${prefix}${access.ownerId}-${suffix}`;
    const cache = await caches.open(name);
    let committed = false;
    try {
      await cache.put(url, response);
      for (const asset of assets) {
        const assetUrl = new URL(asset.url);
        const expectedPath = kind === 'species' ? `api/species-packages/${data.recordId}/assets/` : `api/learning-module-versions/${data.recordId}/assets/`;
        if (!withinScope(asset.url) || !assetUrl.pathname.slice(scope.pathname.length).startsWith(expectedPath)
          || assetUrl.searchParams.get('mediaPolicy') !== '2' || (assetUrl.searchParams.get('classId') || null) !== (classId === null ? null : String(classId))
          || !/^[a-f0-9]{64}$/i.test(asset.sha256 || '')) throw error('The lesson contains an invalid media reference.', 422);
        const assetResponse = await fetch(asset.url, { credentials: 'same-origin', cache: 'no-store' });
        if (!assetResponse.ok) {
          if ([401, 419].includes(assetResponse.status)) await lock('session-unavailable', access.ownerId, access.verification || 0);
          const previous = await read('entries', key);
          if ([403, 404, 410].includes(assetResponse.status)) await blockEntry(previous, access);
          throw error('A lesson asset is unavailable. Your earlier verified copy was kept.', assetResponse.status);
        }
        const hash = [...new Uint8Array(await crypto.subtle.digest('SHA-256', await assetResponse.clone().arrayBuffer()))].map(value => value.toString(16).padStart(2, '0')).join('');
        if (hash !== asset.sha256.toLowerCase()) throw error('A media integrity check failed. Your earlier verified copy was kept.', 422);
        if (!sameAuthorization(access, await clientAccess(clientId))) throw error('Account or lesson access changed during preparation. Reconnect and retry.', 409);
        await cache.put(asset.url, assetResponse);
      }
      const entry = { key, ownerId: access.ownerId, kind, recordId: data.recordId, classId, version: kind === 'species' ? manifest.packageVersion : manifest.moduleVersion,
        slug: kind === 'species' ? manifest.species.slug : manifest.module.slug, title: kind === 'species' ? manifest.species.commonName : manifest.module.title,
        cacheName: name, manifestUrl: url, urls: [url, ...assets.map(asset => asset.url)], allowed: true, sizeBytes: size, preparedAt: new Date().toISOString() };
      const previous = await write(['access', 'entries'], (transaction, result) => {
        const request = transaction.objectStore('access').get('current');
        request.onsuccess = () => {
          if (!sameAuthorization(access, request.result || {})) { transaction.abort(); return; }
          const old = transaction.objectStore('entries').get(key);
          old.onsuccess = () => { result(old.result); transaction.objectStore('entries').put(entry); };
        };
      });
      committed = true;
      if (previous?.cacheName) await caches.delete(previous.cacheName).catch(() => undefined);
      await announce('OFFLINE_MEDIA_ENTRIES_CHANGED', access.ownerId);
      return { entry, entries: (await read('entries')).filter(item => item.ownerId === access.ownerId) };
    } catch (exception) {
      if (!committed) await caches.delete(name).catch(() => undefined);
      if (exception.name === 'QuotaExceededError') throw error('Browser storage is full. Remove an older prepared copy and try again.', 507);
      throw exception;
    }
  }

  async function remove(clientId, key) {
    const access = await clientAccess(clientId);
    const entry = await read('entries', key);
    if (!entry || entry.ownerId !== access.ownerId) throw error('This prepared copy belongs to another account.', 403);
    await write(['access', 'entries'], transaction => {
      const request = transaction.objectStore('access').get('current');
      request.onsuccess = () => { if (!sameAccess(access, request.result || {})) transaction.abort(); else transaction.objectStore('entries').delete(key); };
    });
    await caches.delete(entry.cacheName);
    await announce('OFFLINE_MEDIA_ENTRIES_CHANGED', access.ownerId);
    return { entries: (await read('entries')).filter(item => item.ownerId === access.ownerId) };
  }

  async function partial(response, range) {
    if (!range) return response;
    const match = /^bytes=(\d*)-(\d*)$/i.exec(range.trim());
    const bytes = await response.arrayBuffer();
    const invalid = () => new Response(null, { status: 416, headers: { 'Content-Range': `bytes */${bytes.byteLength}` } });
    if (!match || (!match[1] && !match[2])) return invalid();
    const start = match[1] ? Number(match[1]) : Math.max(0, bytes.byteLength - Number(match[2]));
    const end = Math.min(match[1] && match[2] ? Number(match[2]) : bytes.byteLength - 1, bytes.byteLength - 1);
    if (!Number.isSafeInteger(start) || !Number.isSafeInteger(end) || start < 0 || start > end || start >= bytes.byteLength || (!match[1] && Number(match[2]) === 0)) return invalid();
    const headers = new Headers(response.headers);
    headers.set('Accept-Ranges', 'bytes'); headers.set('Content-Length', String(end - start + 1)); headers.set('Content-Range', `bytes ${start}-${end}/${bytes.byteLength}`);
    return new Response(bytes.slice(start, end + 1), { status: 206, headers });
  }

  async function blockEntry(entry, access) {
    await write(['access', 'entries'], transaction => {
      const request = transaction.objectStore('access').get('current');
      request.onsuccess = () => {
        const current = request.result;
        if (access && sameAccess(access, current || {})) transaction.objectStore('access').put({ ...current, authorizationRevision: (current.authorizationRevision || 0) + 1 }, 'current');
        if (!entry) return;
        const record = transaction.objectStore('entries').get(entry.key);
        record.onsuccess = () => {
          if (record.result?.ownerId === entry.ownerId) transaction.objectStore('entries').put({ ...record.result, allowed: false });
        };
      };
    });
    if (entry) await announce('OFFLINE_MEDIA_ENTRY_BLOCKED', entry.ownerId, { key: entry.key, recordId: entry.recordId, classId: entry.classId, kind: entry.kind });
  }

  async function resource(event) {
    const url = new URL(event.request.url);
    let access;
    try { access = await clientAccess(event.clientId); } catch { /* Connected requests still use server authorization. */ }
    let entry;
    try { if (access) entry = (await read('entries')).find(item => item.ownerId === access.ownerId && item.allowed && item.urls.includes(url.href)); }
    catch { access = null; }
    try {
      const response = await fetch(new Request(event.request, { cache: 'no-store' }));
      if ([401, 419].includes(response.status)) await lock('session-unavailable', access?.ownerId ?? null, access?.verification ?? null);
      else if ([403, 404, 410].includes(response.status) && access) await blockEntry(entry, access);
      return response;
    } catch {
      if (!access || !entry || event.request.mode === 'navigate' || url.searchParams.get('mediaPolicy') !== '2') return denied();
      try {
        const current = await clientAccess(event.clientId);
        if (!sameAccess(access, current)) return denied();
        const latest = await read('entries', entry.key);
        if (!latest?.allowed || latest.ownerId !== current.ownerId || !latest.urls.includes(url.href)) return denied();
        const cache = await caches.open(latest.cacheName);
        const response = await cache.match(event.request);
        if (!response) return denied('Prepare this lesson while connected before opening it offline.', 503);
        if (!sameAuthorization(current, await clientAccess(event.clientId))) return denied();
        return partial(response, event.request.headers.get('range'));
      } catch { return denied(); }
    }
  }

  async function message(event) {
    const data = event.data;
    if (data?.protocol !== 2 || !event.ports?.[0] || !event.source?.id || !['CONTEXT', 'GUEST', 'LOCK', 'LIST', 'PREPARE', 'REMOVE'].includes(data.type)) return;
    const port = event.ports[0];
    try {
      const client = await self.clients.get(event.source.id);
      if (!client || !withinScope(client.url)) throw error('Invalid application context.', 403);
      let result;
      if (data.type === 'LOCK') {
        if (data.expectedOwnerId !== undefined && !validId(data.expectedOwnerId)) throw error('Invalid account context.', 422);
        if (data.expectedVerification !== undefined && (!Number.isSafeInteger(data.expectedVerification) || data.expectedVerification < 0)) throw error('Invalid account context.', 422);
        result = await lock('signed-out', data.expectedOwnerId ?? null, data.expectedVerification ?? null);
      }
      else if (data.type === 'GUEST') {
        const previous = await read('access', 'current');
        let response;
        try { response = await fetch(fromScope('api/session'), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } }); } catch { /* An old guest tab cannot revoke another account while disconnected. */ }
        if (response && [401, 403, 419].includes(response.status)) await lock('session-unavailable', previous?.ownerId ?? null, previous?.verification || 0);
        result = { locked: true, entries: [] };
      }
      else if (data.type === 'CONTEXT') result = await context(client.id, data.ownerId, data.offline === true);
      else if (data.type === 'PREPARE') result = await prepare(client.id, data);
      else if (data.type === 'REMOVE') result = await remove(client.id, data.key);
      else { const access = await clientAccess(client.id); result = { ownerId: access.ownerId, entries: (await read('entries')).filter(entry => entry.ownerId === access.ownerId) }; }
      port.postMessage({ protocol: 2, ok: true, ...result });
    } catch (exception) { port.postMessage({ protocol: 2, ok: false, status: exception.status || 503, message: exception.message || 'Offline storage is unavailable. Reconnect and retry.' }); }
  }

  async function migrate() {
    const names = await caches.keys();
    await Promise.all(names.filter(name => name.startsWith('aquadissect-package-') || name.startsWith('aquadissect-module-')).map(name => caches.delete(name)));
  }

  self.AquaOfflineMedia = { message, resource, protectedRoute, migrate, lock, partial, databaseName, prefix };
})();
