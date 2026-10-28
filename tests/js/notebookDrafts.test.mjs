import test from 'node:test';
import assert from 'node:assert/strict';
import { notebookScope, normalizeNotebookFields, importNotebookFields } from '../../resources/js/notebookDraftData.js';
import { measurementFields, classificationFields, featureFields, countFields } from '../../resources/js/observationNotebookData.js';

const manifest = { species: { slug: 'rabbitfish', scientificName: 'QA specimen' }, packageVersion: 'qa.1', contentData: { anatomy: { structures: [{ id: 'heart', layer: 'internal' }] } } };
function legacyExport() {
  return { schemaVersion: 1, package: { slug: 'rabbitfish', scientificName: 'QA specimen', version: 'qa.1' }, basis: 'physical_specimen', specimenReference: '', referenceAndTools: '',
    measurements: measurementFields.map(([code]) => ({ code, status: 'unknown', value: null, unit: 'mm' })),
    classifications: classificationFields.map(field => ({ field, status: 'unknown', value: null })),
    features: featureFields.map(field => ({ field, status: 'unknown' })),
    counts: countFields.map(field => ({ field, status: 'unknown', value: null })),
    organs: [{ structure: 'heart', status: 'unknown', color: '', position: '', sizeShape: '', texture: '', condition: '', remarks: '' }],
    reflections: { orientation: '', sequence: '', interpretation: '', physicalFollowUp: '' } };
}
test('draft storage separates owners, sections, package identities and versions', () => {
  const scope = notebookScope(1, manifest, { packageId: 5, classId: 10 });
  assert.equal(scope, notebookScope('1', manifest, { packageId: '5', classId: '10' }));
  assert.notEqual(scope, notebookScope(2, manifest, { packageId: 5, classId: 10 }));
  assert.notEqual(scope, notebookScope(1, manifest, { packageId: 5, classId: 11 }));
  assert.notEqual(scope, notebookScope(1, manifest, { packageId: 6, classId: 10 }));
  assert.notEqual(scope, notebookScope(1, { ...manifest, packageVersion: 'qa.2' }, { packageId: 5, classId: 10 }));
  assert.equal(notebookScope(null, manifest, { packageId: 5 }), null);
});
test('device drafts preserve incomplete values and distinguish missing from observed zero', () => {
  const fields = normalizeNotebookFields({ 'measure-status-0': 'observed', 'measure-value-0': '', 'count-status-0': 'observed', 'count-value-0': '0', orientation: '<script>literal notes</script>' }, manifest);
  assert.equal(fields['measure-value-0'], '');
  assert.equal(fields['count-value-0'], '0');
  assert.equal(fields['measure-status-1'], 'unknown');
  assert.equal(fields.orientation, '<script>literal notes</script>');
});
test('partial draft backups can be restored without making them valid assessments', () => {
  const file = legacyExport();
  const imported = importNotebookFields({ schemaVersion: 2, type: 'aquadissect-notebook-draft', package: file.package, fields: { 'measure-status-0': 'observed', 'measure-value-0': '', sequence: 'unfinished' } }, manifest);
  assert.equal(imported['measure-value-0'], '');
  assert.equal(imported.sequence, 'unfinished');
});
test('legacy JSON imports preserve measured values and actual observed zero', () => {
  const file = legacyExport();
  file.measurements[0] = { ...file.measurements[0], status: 'observed', value: 12.5 };
  file.counts[0] = { ...file.counts[0], status: 'observed', value: 0 };
  const fields = importNotebookFields(file, manifest);
  assert.equal(fields['measure-value-0'], '12.5');
  assert.equal(fields['count-value-0'], '0');
});
for (const [description, mutate] of [
  ['wrong species', file => { file.package.slug = 'other'; }],
  ['wrong version', file => { file.package.version = 'qa.2'; }],
  ['wrong identity', file => { file.package.scientificName = 'other'; }],
  ['unsupported format', file => { file.schemaVersion = 99; }],
  ['missing fields', file => { file.measurements.pop(); }],
  ['duplicate fields', file => { file.counts[1].field = file.counts[0].field; }],
  ['negative measurement', file => { file.measurements[0].status = 'observed'; file.measurements[0].value = -1; }],
  ['fractional count', file => { file.counts[0].status = 'observed'; file.counts[0].value = 1.5; }],
  ['missing classification', file => { file.classifications[0].status = 'observed'; }],
  ['virtual measured value', file => { file.basis = 'virtual_illustration'; file.measurements[0].status = 'observed'; file.measurements[0].value = 1; }],
  ['overlong text', file => { file.reflections.sequence = 'x'.repeat(1001); }],
  ['invalid state', file => { file.features[0].status = 'invented'; }],
]) test(`import rejects ${description} without changing the current draft`, () => {
  const file = legacyExport(); mutate(file);
  assert.throws(() => importNotebookFields(file, manifest));
});
test('stored draft normalization rejects unexpected field names and non-string values', () => {
  assert.throws(() => normalizeNotebookFields({ ownerId: '2' }, manifest));
  assert.throws(() => normalizeNotebookFields({ orientation: {} }, manifest));
  assert.throws(() => normalizeNotebookFields(JSON.parse('{"__proto__":"bad"}'), manifest));
});
