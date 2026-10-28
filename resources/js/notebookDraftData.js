import { measurementFields, classificationFields, featureFields, countFields, observationStates, parseMeasurement, parseCount } from './observationNotebookData.js';

export const notebookOrgans = manifest => manifest.contentData.anatomy.structures.filter(part => part.layer === 'internal' || ['gills', 'heart', 'liver', 'gallbladder', 'stomach', 'intestine', 'spleen', 'swimBladder', 'kidney', 'gonad', 'spine'].includes(part.id));
export const organNotes = ['color', 'position', 'sizeShape', 'texture', 'condition', 'remarks'];
export const reflectionFields = ['orientation', 'sequence', 'interpretation', 'physicalFollowUp'];
const bases = ['virtual_illustration', 'physical_specimen', 'reference_material'];
const featureStates = ['unknown', 'not_observed', 'not_applicable', 'present', 'absent'];

export function notebookScope(ownerId, manifest, { packageId, classId = null } = {}) {
  if (!ownerId || !packageId || manifest.species?.slug !== 'rabbitfish' || !manifest.packageVersion) return null;
  return JSON.stringify([String(ownerId), String(packageId), manifest.species.slug, manifest.packageVersion, classId === null ? null : String(classId)]);
}

export function notebookRules(manifest) {
  const rules = { basis: bases, specimenReference: 1000, referenceAndTools: 1000 };
  measurementFields.forEach((_, i) => { rules[`measure-status-${i}`] = observationStates; rules[`measure-value-${i}`] = 100; });
  countFields.forEach((_, i) => { rules[`count-status-${i}`] = observationStates; rules[`count-value-${i}`] = 100; });
  classificationFields.forEach((_, i) => { rules[`class-status-${i}`] = observationStates; rules[`class-value-${i}`] = 200; });
  featureFields.forEach((_, i) => { rules[`feature-${i}`] = featureStates; });
  notebookOrgans(manifest).forEach((_, i) => {
    rules[`organ-status-${i}`] = observationStates;
    organNotes.forEach((_, j) => { rules[`organ-${i}-${j}`] = 1000; });
  });
  reflectionFields.forEach(key => { rules[key] = 1000; });
  return rules;
}

// Drafts retain incomplete inputs; numerical validation still occurs on export.
export function normalizeNotebookFields(fields, manifest) {
  if (!fields || typeof fields !== 'object' || Array.isArray(fields)) throw new Error('Invalid notebook fields.');
  const rules = notebookRules(manifest);
  if (Object.keys(fields).some(key => !Object.hasOwn(rules, key))) throw new Error('The draft contains unknown fields.');
  return Object.fromEntries(Object.entries(rules).map(([key, rule]) => {
    const value = Object.hasOwn(fields, key) ? fields[key] : Array.isArray(rule) ? rule[0] : '';
    if (typeof value !== 'string' || (Array.isArray(rule) ? !rule.includes(value) : value.length > rule)) throw new Error('The draft contains an invalid field value.');
    return [key, value];
  }));
}

function rows(value, expected, key) {
  if (!Array.isArray(value) || value.length !== expected.length || value.some((item, i) => !item || item[key] !== expected[i])) throw new Error('This file does not match the current notebook fields.');
  return value;
}

export function importNotebookFields(value, manifest) {
  if (!value || value.package?.slug !== manifest.species.slug || value.package.version !== manifest.packageVersion || value.package.scientificName !== manifest.species.scientificName) throw new Error('Choose an AquaDissect JSON notebook for this exact lesson version.');
  if (value.schemaVersion === 2 && value.type === 'aquadissect-notebook-draft') return normalizeNotebookFields(value.fields, manifest);
  if (value.schemaVersion !== 1) throw new Error('This notebook file format is not supported.');
  const fields = { basis: value.basis, specimenReference: value.specimenReference, referenceAndTools: value.referenceAndTools };
  rows(value.measurements, measurementFields.map(([code]) => code), 'code').forEach((row, i) => {
    if (row.unit !== 'mm' || (row.status !== 'observed' && row.value !== null) || (row.status === 'observed' && typeof row.value !== 'number')) throw new Error('Invalid measurement in the import.');
    parseMeasurement(row.value, row.status);
    if (value.basis === 'virtual_illustration' && row.status === 'observed') throw new Error('Virtual images have no calibrated measurement scale.');
    fields[`measure-status-${i}`] = row.status; fields[`measure-value-${i}`] = row.value === null ? '' : String(row.value);
  });
  rows(value.counts, countFields, 'field').forEach((row, i) => {
    if ((row.status !== 'observed' && row.value !== null) || (row.status === 'observed' && typeof row.value !== 'number')) throw new Error('Invalid count in the import.');
    parseCount(row.value, row.status);
    fields[`count-status-${i}`] = row.status; fields[`count-value-${i}`] = row.value === null ? '' : String(row.value);
  });
  rows(value.classifications, classificationFields, 'field').forEach((row, i) => {
    if (row.status === 'observed' ? typeof row.value !== 'string' || !row.value.trim() : row.value !== null) throw new Error('Invalid classification in the import.');
    fields[`class-status-${i}`] = row.status; fields[`class-value-${i}`] = row.value ?? '';
  });
  rows(value.features, featureFields, 'field').forEach((row, i) => { fields[`feature-${i}`] = row.status; });
  rows(value.organs, notebookOrgans(manifest).map(part => part.id), 'structure').forEach((row, i) => {
    fields[`organ-status-${i}`] = row.status;
    organNotes.forEach((key, j) => { fields[`organ-${i}-${j}`] = row[key]; });
  });
  if (!value.reflections || typeof value.reflections !== 'object') throw new Error('Missing notebook reflections.');
  reflectionFields.forEach(key => { fields[key] = value.reflections[key]; });
  return normalizeNotebookFields(fields, manifest);
}
