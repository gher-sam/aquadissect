import test from 'node:test';
import assert from 'node:assert/strict';
import { createDemoSession } from '../../resources/js/reefDemoSession.js';
import { createDiveAnswers } from '../../resources/js/portal/reefAssessmentAnswers.js';

function play(session, phase, choices) {
  const dive = session.source(phase);
  const draft = createDiveAnswers(dive.questions());
  choices.forEach((choice, index) => draft.choose(index, choice));
  const payload = draft.submission(() => 'sample-' + phase);
  const receipt = dive.submit(payload);
  assert.strictEqual(dive.submit(payload), receipt);
  return receipt;
}

test('each visit starts fresh and Return Dive requires the sample lesson', () => {
  const first = createDemoSession();
  first.finishLesson();
  assert.equal(first.stage, 'pre');
  assert.throws(() => first.source('post').questions());
  const form = first.source('pre');
  assert.equal(form.questions().length, 4);
  assert.equal(Object.hasOwn(form.questions()[0], 'key'), false);
  assert.throws(() => form.submit({ attemptUuid: 'demo', answers: {} }));
  assert.equal(first.firstScore, null);
  play(first, 'pre', [0, 1, 2, 3]);
  assert.equal(first.stage, 'lesson');
  assert.throws(() => first.source('post').questions());
  assert.equal(first.comparison(), null);
  first.finishLesson();
  assert.equal(first.stage, 'post');
  const otherVisit = createDemoSession();
  assert.equal(otherVisit.stage, 'pre');
  assert.equal(otherVisit.firstScore, null);
});

test('sample comparisons reflect the selected answers, including ties and lower scores', () => {
  for (const [preChoices, postChoices, preScore, postScore] of [
    [[0, 1, 0, 0], [1, 2, 3, 1], 2, 3],
    [[1, 0, 0, 0], [0, 0, 0, 1], 0, 0],
    [[0, 1, 2, 3], [0, 0, 0, 1], 4, 0],
  ]) {
    const session = createDemoSession();
    assert.equal(play(session, 'pre', preChoices).score, preScore);
    session.finishLesson();
    assert.equal(play(session, 'post', postChoices).score, postScore);
    const comparison = session.comparison();
    assert.equal(session.stage, 'complete');
    assert.deepEqual([comparison.pre.total, comparison.post.total], [4, 4]);
    assert.equal(comparison.pointDifference, postScore - preScore);
    assert.equal(comparison.percentagePointDifference, (postScore - preScore) * 25);
  }
});
