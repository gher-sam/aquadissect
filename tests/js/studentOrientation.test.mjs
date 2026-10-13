import test from 'node:test';
import assert from 'node:assert/strict';
import { studentOrientationPath } from '../../resources/js/portal/dashboards.js';
globalThis.window = { AQUADISSECT_ROUTES: { about: '/help' } };
test('orientation is available with no class or lesson assignment', () => {
  const html = studentOrientationPath({}, []);
  assert.match(html, /Start orientation/);
  assert.match(html, /Section needs checking/);
  assert.match(html, /guide=student-orientation/);
  assert.match(html, /Not started/);
});
test('completion uses the current orientation guide version', () => {
  const old = studentOrientationPath({progress:[{guide_key:'student-orientation',version:99,viewed_at:'2026-10-13'}]},[]);
  assert.match(old, /Not started/);
  const current = studentOrientationPath({progress:[{guide_key:'student-orientation',version:1,viewed_at:'2026-10-13'}]},[{id:1}]);
  assert.match(current, /Review orientation/);
  assert.match(current, /Orientation guide viewed/);
  assert.match(current, /does not unlock unpublished lessons/);
});
