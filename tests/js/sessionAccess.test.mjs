import test from 'node:test';
import assert from 'node:assert/strict';
import { beginLocalSignOut, confirmServerSignOut, prepareSessionContext, signOutPending, offlineContextKey, pendingSignOutKey } from '../../resources/js/portal/sessionAccess.js';

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
