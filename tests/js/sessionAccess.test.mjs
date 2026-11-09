import test from 'node:test';
import assert from 'node:assert/strict';
import { beginLocalSignOut, confirmServerSignOut, prepareSessionContext, signOutPending, offlineContextKey, pendingSignOutKey, offlineMediaLockKey, canReachSession } from '../../resources/js/portal/sessionAccess.js';

test('pending sign-out needs an actual session response before leaving the cached shell', async () => {
  let options;
  assert.equal(await canReachSession('/api/session', async (_url, config) => { options = config; return { ok: true, status: 200 }; }), true);
  assert.equal(options.cache, 'no-store'); assert.equal(options.credentials, 'same-origin');
  for (const status of [401, 419]) assert.equal(await canReachSession('/api/session', async () => ({ ok: false, status })), true);
  assert.equal(await canReachSession('/api/session', async () => ({ ok: false, status: 503 })), false);
  assert.equal(await canReachSession('/api/session', async () => { throw new TypeError('Network unavailable'); }), false);
});

function storage() {
  const data = new Map([[offlineContextKey, 'learner context'], ['queued-work', 'retained']]);
  return { getItem: key => data.get(key), setItem: (key, value) => data.set(key, value), removeItem: key => data.delete(key) };
}

test('offline sign-out immediately clears identity but retains queued work and a server logout intent', () => {
  const data = storage(); const doc = { cookie: '' };
  beginLocalSignOut(data, doc, '/course');
  assert.equal(data.getItem(offlineContextKey), undefined);
  assert.equal(data.getItem('queued-work'), 'retained');
  assert.match(doc.cookie, /aquadissect_logout_pending=1; Path=\/course\//);
  const bootstrap = { offlineShell: true, user: { id: 1 }, classes: [{ id: 1 }], species: [{ id: 2 }] };
  prepareSessionContext(bootstrap, data);
  assert.equal(bootstrap.user, null); assert.deepEqual(bootstrap.classes, []);
  assert.equal(signOutPending(data), true);
  confirmServerSignOut(data, doc, '/course');
  assert.equal(signOutPending(data, ''), false);
  assert.equal(data.getItem('queued-work'), 'retained');
});

test('an authenticated page cannot restore a signed-out account while server logout is pending', () => {
  const data = storage(); const doc = { cookie: '' };
  beginLocalSignOut(data, doc);
  const bootstrap = { user: { id: 1 }, lessonResult: { score: 9 }, pendingUsers: [{ id: 2 }] };
  prepareSessionContext(bootstrap, data);
  assert.equal(bootstrap.user, null); assert.equal(bootstrap.lessonResult, null);
  assert.deepEqual(bootstrap.pendingUsers, []);
  prepareSessionContext({ user: null }, data);
  assert.equal(data.getItem(pendingSignOutKey), undefined);
  assert.equal(data.getItem(offlineMediaLockKey), '*', 'Finishing server sign-out must not unlock retained media');
});

test('storage or cookie restrictions do not leave local access open', () => {
  const denied = { getItem() { throw new Error('Denied'); }, setItem() { throw new Error('Denied'); }, removeItem() { throw new Error('Denied'); } };
  const doc = { set cookie(_) { throw new Error('Denied'); } };
  assert.doesNotThrow(() => beginLocalSignOut(denied, doc));
  assert.equal(signOutPending(denied), true);
  const bootstrap = { offlineShell: true, user: { id: 1 } };
  prepareSessionContext(bootstrap, denied); assert.equal(bootstrap.user, null);
  confirmServerSignOut(denied, doc);
});
