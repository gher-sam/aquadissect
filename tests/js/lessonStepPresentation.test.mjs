import test from 'node:test';
import assert from 'node:assert/strict';
import { lessonStepPresentation } from '../../resources/js/lessonStepPresentation.js';

const step = Object.freeze({ title: 'Trace an incision', description: 'Source description', prompt: 'Trace the cut', action: 'Trace incision', kind: 'action-target', targets: Object.freeze(['vent']) });

test('an unavailable incision guide becomes an honest reference review without changing targets', () => {
  const shown = lessonStepPresentation(step, false);
  assert.equal(shown.referenceOnly, true);
  assert.equal(shown.title, 'Review incision planning');
  assert.match(shown.description, /does not provide a verified incision path/);
  assert.equal(shown.targets, step.targets); assert.equal(shown.kind, step.kind);
  assert.equal(step.title, 'Trace an incision');
});

test('only an explicit supported-guide capability retains tracing instructions', () => {
  const shown = lessonStepPresentation(step, true);
  assert.equal(shown.referenceOnly, false); assert.equal(shown.prompt, step.prompt); assert.equal(shown.action, step.action);
  for (const capability of [undefined, null, false, 'true', 1]) assert.equal(lessonStepPresentation(step, capability).referenceOnly, true);
});

test('other lesson steps and question definitions retain their original content', () => {
  for (const kind of ['continue', 'targets', 'ordered-targets', 'action', 'quiz', 'preparation']) {
    const original = { ...step, kind, questions: [{ id: 'retained', answer: 'original answer' }] };
    assert.deepEqual(lessonStepPresentation(original), { ...original, referenceOnly: false });
  }
});

test('the portal can render before a lesson has been selected', () => {
  assert.deepEqual(lessonStepPresentation(), { referenceOnly: false });
});
