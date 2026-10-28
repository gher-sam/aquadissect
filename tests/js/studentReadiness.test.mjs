import test from 'node:test';
import assert from 'node:assert/strict';
import { readinessSections, studentReadinessView } from '../../resources/js/portal/studentReadiness.js';
globalThis.window = { AQUADISSECT_ROUTES: { about: '/help' } };
const packages = [{ id: 7, status: 'published', version: 'qa.1' }];
const section = (id, gate) => ({ id, name: `QA section ${id}`, status: 'active', prerequisiteStatus: gate, assignments: [{ speciesSlug: 'rabbitfish', packageId: 7, locked: !gate.satisfied }] });
test('readiness keeps two sections independent even when they share a lesson', () => {
  const result = readinessSections([section(1, { required: 1, satisfied: false }), section(2, { required: 1, completed: 1, satisfied: true })], packages);
  assert.equal(result[0].lessons[0].status, 'Locked');
  assert.equal(result[1].lessons[0].status, 'Ready to open');
  assert.equal(result[1].lessons[0].classId, 2);
});
test('documented exceptions permit access without claiming safety completion', () => {
  const result = readinessSections([section(1, { required: 1, overridden: 1, satisfied: true })], packages);
  assert.equal(result[0].safety.status, 'Exception active');
  assert.equal(result[0].lessons[0].status, 'Ready to open');
});
test('queued safety completion remains pending and the lesson stays locked', () => {
  const result = readinessSections([section(1, { required: 1, satisfied: false })], packages, [{ classId: 1, progress: { completed: true, pendingSync: true } }]);
  assert.equal(result[0].safety.status, 'Waiting to synchronize');
  assert.equal(result[0].lessons[0].status, 'Locked');
});
test('unassigned activities, unpublished versions and archived sections never get an open button', () => {
  const missing = { ...section(1, { required: 0, satisfied: true }), assignments: [] };
  assert.equal(readinessSections([missing])[0].lessons[0].status, 'Waiting for your teacher');
  assert.equal(readinessSections([missing])[0].safety.status, 'Not assigned');
  assert.equal(readinessSections([section(1, { satisfied: true })], [{ ...packages[0], status: 'review' }])[0].lessons[0].status, 'Unavailable');
  assert.deepEqual(readinessSections([{ ...missing, status: 'archived' }]), []);
});
test('offline checklist does not claim current confirmation or offer a lesson open button', () => {
  const html = studentReadinessView({}, [section(1, { required: 1, satisfied: true })], { user: { status: 'active' }, packages, offline: true });
  assert.match(html, /Showing saved information/);
  assert.match(html, /Check online/);
  assert.doesNotMatch(html, /data-open-package/);
});
test('orientation follows the actual guide version, independent of lesson permission', () => {
  const html = studentReadinessView({ guides: [{ id: 'student-orientation', version: 2 }], progress: [{ guide_key: 'student-orientation', version: 1, viewed_at: '2026-10-28' }] }, [], { user: { status: 'active', mustChangePassword: true } });
  assert.match(html, /Not started/);
  assert.match(html, /required password change/);
  assert.match(html, /Section needs checking/);
});
test('section and lesson labels are escaped before rendering', () => {
  const html = studentReadinessView({}, [{ ...section(1, { required: 1, satisfied: true }), name: '<img src=x onerror=alert(1)>' }], { user: { status: 'active' }, packages });
  assert.doesNotMatch(html, /<img/);
  assert.match(html, /&lt;img/);
});
