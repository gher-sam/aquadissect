import test from 'node:test';
import assert from 'node:assert/strict';
import { synchronizeRecords } from '../../resources/js/portal/queueSync.js';

for (const kind of ['lesson', 'safety']) {
  test(`${kind}: a rejected record does not block a later valid record or cross account boundaries`, async () => {
    const records = [{ id: 1, ownerId: 1 }, { id: 2, ownerId: 2 }, { id: 3, ownerId: 1 }];
    const sent = [], removed = [], failures = [];
    const result = await synchronizeRecords(records, { ownerId: 1,
      send: async item => { sent.push(item.id); if (item.id === 1) throw Object.assign(new Error('No longer assigned'), { status: 403 }); },
      remove: async item => removed.push(item.id), failed: async (item, error) => failures.push([item.id, error.message]),
    });
    assert.deepEqual(sent, [1, 3]); assert.deepEqual(removed, [3]); assert.deepEqual(failures, [[1, 'No longer assigned']]);
    assert.deepEqual(result, { synced: 1, failed: 1, pending: 0 });
  });
}

for (const status of [undefined, 401, 419, 429, 500]) {
  test(`transient or session failure ${status} retains records for a later retry`, async () => {
    let removed = 0;
    const result = await synchronizeRecords([{ ownerId: 1 }, { ownerId: 1 }], { ownerId: 1,
      send: async () => { throw Object.assign(new Error('Retry later'), { status }); }, remove: async () => { removed += 1; }, failed: async () => {},
    });
    assert.equal(removed, 0); assert.deepEqual(result, { synced: 0, failed: 1, pending: 1 });
  });
}

test('signing out during sync stops later requests without deleting their records', async () => {
  let active = true; const removed = [];
  const result = await synchronizeRecords([{ id: 1, ownerId: 1 }, { id: 2, ownerId: 1 }], { ownerId: 1, canContinue: () => active,
    send: async () => { active = false; }, remove: async item => removed.push(item.id), failed: async () => {},
  });
  assert.deepEqual(removed, [1]); assert.deepEqual(result, { synced: 1, failed: 0, pending: 1 });
});
