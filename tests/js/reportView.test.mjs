import { test } from 'node:test';
import assert from 'node:assert/strict';
import { reportNumber, reportChartDefinitions, overviewView, chartCard, supportView, recordsView } from '../../resources/js/portal/reportView.js';

const report = () => ({
  meta: { role: 'instructor', statusBasis: 'Current assignment status' }, filters: {},
  summary: { learners: 1, enrollments: 1, completionRate: 0, completedItems: 0, exceptionItems: 0, meanScore: null, medianScore: null, scoreSamples: 0, needsReview: 0, recordCount: 0, historicalCount: 0, practiceXp: null },
  charts: { completion: [], trend: { interval: 'day', points: [] }, distribution: [] }, support: [], assessments: [],
  forecast: { status: 'insufficient', message: 'More history is needed', history: [], projection: [] },
});
test('report numbers keep an observed zero distinct from missing values and negative differences', () => {
  assert.equal(reportNumber(0, '%'), '0%'); assert.equal(reportNumber(null), 'Not recorded'); assert.equal(reportNumber(-25, ' pp'), '-25 pp');
});
test('empty score samples do not fabricate a zero-score distribution', () => {
  const r = report(); r.charts.distribution = [{ label: '0–19%', count: 0 }];
  const defs = reportChartDefinitions(r); assert.deepEqual(defs.find(c => c.key === 'distribution').labels, []);
  assert.doesNotMatch(overviewView(r, defs), /Your practice XP/); assert.match(overviewView(r, defs), /Not recorded/);
});
test('forecast actual and predicted series retain gaps and bounded server values', () => {
  const r = report(); r.forecast = { status: 'available', assumption: 'If pace continues', history: [{ date: '2026-11-15', completed: 2 }, { date: '2026-11-16', completed: 3 }], projection: [{ date: '2026-11-16', expected: 3, low: 3, high: 3 }, { date: '2026-11-17', expected: 4, low: 3, high: 5 }] };
  const f = reportChartDefinitions(r).find(c => c.key === 'forecast');
  assert.deepEqual(f.series[0].values, [2, 3, null]); assert.deepEqual(f.series[1].values, [null, 3, 4]); assert.equal(f.series[1].dashed, true);
});
test('chart labels and support text are escaped and provide tabular alternatives', () => {
  const html = chartCard({ key: 'x', title: '<script>bad</script>', type: 'bar', note: '<img src=x>', labels: ['<svg onload=x>'], series: [{ name: 'Count', values: [0] }] });
  assert.doesNotMatch(html, /<script>|<img src=x>|<svg onload/); assert.match(html, /View chart data/); assert.match(html, /aria-pressed="true"/); assert.match(html, /<td>0<\/td>/);
});
test('review thresholds default to disabled and do not imply a grade policy', () => {
  assert.match(supportView(report()), /Score-based review is disabled/);
  assert.match(supportView({ ...report(), filters: { scoreThreshold: 60 } }), /below 60%/);
});
test('record rendering preserves zero, missing, practice units, and unattributed history', () => {
  const base = { learner: '<b>Learner</b>', studentId: '00123', section: 'Historical', activity: 'Lesson', kind: 'lessons', score: 0, total: 5, status: 'Completed', date: null, attributed: false };
  const html = recordsView({ rows: [base, { ...base, score: null, total: null }, { ...base, kind: 'practice', xp: 10 }], total: 3, page: 1, lastPage: 1 });
  assert.match(html, /0 \/ 5/); assert.match(html, /10 XP/); assert.match(html, /Not recorded/); assert.match(html, /unattributed/); assert.doesNotMatch(html, /<b>Learner/);
});
