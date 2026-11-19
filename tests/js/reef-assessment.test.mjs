import test from 'node:test';
import assert from 'node:assert/strict';
import { createDiveAnswers, recordedPair } from '../../resources/js/portal/reefAssessmentAnswers.js';

test('a dive preserves faculty order and exact choices, including edits and a retry after a lost receipt', () => {
  const questions = [
    { id: '__proto__', options: ['A & B', '<C>'] },
    { id: 'return-07', options: ['Fín 2', 'Fín 10', 'Fín 1'] },
  ];
  const original = structuredClone(questions);
  const draft = createDiveAnswers(questions);
  const id = () => 'one-submission';
  assert.equal(draft.submission(id), null);
  draft.choose(1, 2);
  draft.choose(0, 9);
  assert.equal(draft.count, 1);
  draft.choose(0, 0);
  draft.choose(1, 1);
  assert.equal(draft.count, 2);
  assert.equal(draft.selected(1), 1);
  const payload = draft.submission(id);
  assert.deepEqual(Object.entries(payload.answers), [['__proto__', 'A & B'], ['return-07', 'Fín 10']]);
  draft.choose(0, 1);
  assert.equal(draft.selected(0), 0);
  assert.equal(draft.submission(() => assert.fail('Allocated a second submission ID')), payload);
  assert.deepEqual(questions, original);
});

test('one-question and empty forms never count an unanswered selection as the first choice', () => {
  const draft = createDiveAnswers([{ id: '001', options: ['0', '1'] }]);
  assert.equal(draft.selected(0), null);
  assert.equal(draft.submission(() => assert.fail()), null);
  draft.choose(0, 0);
  assert.equal(draft.submission(() => 'id').answers['001'], '0');
  assert.equal(createDiveAnswers([]).submission(() => assert.fail()), null);
});

test('a receipt compares only its own complete, comparable pair and preserves zero and negative differences', () => {
  const row = { studentId: 2, preState: 'submitted', postState: 'submitted', pre: { id: 8, score: 2, total: 4 },
    post: { id: 9, score: 1, total: 4 }, pointDifference: -1, percentagePointDifference: -25 };
  const pair = changed => [{ id: 3, rows: [{ ...row, ...changed }] }];
  assert.deepEqual(recordedPair(pair(), 3, 2, 9), row);
  assert.equal(recordedPair(pair({ pointDifference: 0, percentagePointDifference: 0 }), 3, 2, 9).pointDifference, 0);
  for (const change of [{ pre: null }, { post: null }, { preState: 'voided' }, { postState: 'voided' },
    { pre: { total: 17 } }, { pointDifference: null }, { percentagePointDifference: null }]) {
    assert.equal(recordedPair(pair(change), 3, 2, 9), null);
  }
  for (const ids of [[4, 2, 9], [3, 1, 9], [3, 2, 10]]) assert.equal(recordedPair(pair(), ...ids), null);
});
