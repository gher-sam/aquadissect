import test from 'node:test';
import assert from 'node:assert/strict';
import { nextPracticeIndex, practiceView } from '../../resources/js/practiceView.js';

const data = () => ({
  preview: false,
  challenges: [{ id: 'first', title: 'Function finder', prompt: 'Choose a structure', clue: 'Synthetic function', hint: 'Synthetic hint', options: ['One', 'Two'], points: 10 }, { id: 'second', title: 'System sorter', prompt: 'Choose a system', clue: 'Synthetic structure', hint: 'Synthetic hint', options: ['One', 'Two'], points: 10 }],
  summary: { xp: 0, maxXp: 20, completed: 0, total: 2, completedIds: [], complete: false, badges: [{ id: 'first', name: 'First discovery', description: 'Finish one challenge.', earned: false }] },
});
const state = () => ({ index: 0, selected: null, hint: false, busy: false, feedback: null, finished: false });

test('resume selects the first unfinished challenge and completed sets can be reviewed', () => {
  const list = data().challenges;
  assert.equal(nextPracticeIndex(list), 0);
  assert.equal(nextPracticeIndex(list, ['first']), 1);
  assert.equal(nextPracticeIndex(list, ['first', 'second']), 0);
});
test('answer checking requires a selection and is disabled during a request', () => {
  assert.match(practiceView(data(), state()), /data-practice-submit disabled/);
  assert.doesNotMatch(practiceView(data(), { ...state(), selected: 0 }), /data-practice-submit disabled/);
  const html = practiceView(data(), { ...state(), selected: 0, busy: true });
  assert.match(html, /Checking answer/);
  assert.match(html, /data-practice-submit disabled/);
});
test('incorrect feedback permits retries and never shows an earned reward', () => {
  const html = practiceView(data(), { ...state(), selected: 1, feedback: { correct: false, feedback: 'Try again.' } });
  assert.match(html, /Try again/);
  assert.match(html, /data-practice-submit/);
  assert.doesNotMatch(html, /data-practice-next|Correct!|is-earned/);
});
test('an interrupted check is distinguished from a wrong answer and remains retryable', () => {
  const html = practiceView(data(), { ...state(), selected: 1, feedback: { unconfirmed: true, feedback: 'Reconnect before checking.' } });
  assert.match(html, /Answer not confirmed/);
  assert.match(html, /is-unconfirmed/);
  assert.match(html, /data-practice-submit/);
  assert.doesNotMatch(html, /Try again|Correct!|data-practice-next/);
});
test('the challenge map keeps completed challenges available for review', () => {
  const saved = data();
  saved.summary = { ...saved.summary, completed: 1, completedIds: ['first'] };
  const html = practiceView(saved, { ...state(), index: 1 });
  assert.match(html, /data-practice-go="0" class="is-complete"/);
  assert.match(html, /Challenge 1: Function finder, completed/);
  assert.match(html, /data-practice-go="1" class="" aria-current="step"/);
  assert.match(practiceView(saved, { ...state(), busy: true }), /aria-label="Challenge 1: Function finder, completed" disabled/);
});
test('newly earned badges are announced only with confirmed student feedback', () => {
  const feedback = { correct: true, awardedXp: 10, feedback: 'Correct.', earnedBadges: [{ name: 'First discovery' }] };
  assert.match(practiceView(data(), { ...state(), feedback }), /Badge earned: First discovery/);
  assert.doesNotMatch(practiceView({ ...data(), preview: true }, { ...state(), feedback }), /Badge earned:/);
  assert.doesNotMatch(practiceView(data(), { ...state(), feedback: { ...feedback, correct: false } }), /Badge earned:/);
});
test('a confirmed reward and a repeated completion have different messages', () => {
  const saved = data();
  saved.summary = { ...saved.summary, xp: 10, completed: 1, completedIds: ['first'] };
  assert.match(practiceView(saved, { ...state(), feedback: { correct: true, awardedXp: 10, feedback: 'Correct.' } }), /Correct! \+10 XP/);
  const repeated = practiceView(saved, { ...state(), feedback: { correct: true, awardedXp: 0, feedback: 'Correct.' } });
  assert.match(repeated, /XP already collected/);
  assert.doesNotMatch(repeated, /Correct! \+/);
});
test('staff preview never presents practice feedback as saved rewards', () => {
  const preview = { ...data(), preview: true };
  const html = practiceView(preview, { ...state(), feedback: { correct: true, awardedXp: 0, feedback: 'Correct.' } });
  assert.match(html, /Correct — preview only/);
  assert.match(html, /no XP, badges, or learner records are saved/);
  assert.doesNotMatch(html, /XP already collected/);
});
test('all package text is escaped and hints start collapsed', () => {
  const unsafe = data();
  unsafe.challenges[0] = { ...unsafe.challenges[0], clue: '<img src=x onerror=alert(1)>', options: ['<svg onload=alert(1)>', 'Other'], hint: '<script>alert(1)</script>' };
  const html = practiceView(unsafe, state());
  assert.doesNotMatch(html, /<img|<svg onload|<script/);
  assert.match(html, /&lt;img/);
  assert.match(html, /id="practiceHint" hidden/);
  assert.match(html, /aria-expanded="false"/);
});
test('the collection screen distinguishes confirmed completion and staff preview', () => {
  const finished = data();
  finished.summary.complete = true;
  assert.match(practiceView(finished, { ...state(), finished: true }), /You completed this practice set/);
  assert.match(practiceView({ ...finished, preview: true }, { ...state(), finished: true }), /Preview finished/);
  assert.match(practiceView({ ...data(), challenges: [] }, state()), /No practice set is available/);
});
