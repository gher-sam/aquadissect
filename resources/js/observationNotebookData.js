export const measurementFields = [
  ['TL', 'Total length'], ['FL', 'Fork length'], ['SL', 'Standard length'],
  ['PAL', 'Pre-anal length'], ['HL', 'Head length'], ['BD', 'Body depth'],
  ['ED', 'Eye diameter'], ['UJL', 'Upper jaw length'], ['LJL', 'Lower jaw length'],
  ['PDL', 'Pre-dorsal length'], ['IDS', 'Inter-dorsal space'],
  ['1st DF BL', 'First dorsal fin base length'], ['2nd DF BL', 'Second dorsal fin base length'],
  ['AF BL', 'Anal fin base length'], ['P1 BL', 'Pectoral fin base length'], ['P2 BL', 'Pelvic fin base length'],
];
export const classificationFields = ['Scale type', 'Caudal fin type', 'Mouth position', 'Pelvic fin position'];
export const featureFields = ['Nostril', 'Cirri', 'Premaxilla', 'Maxilla', 'Barbel', 'Isthmus',
  'Branchiostegal rays', 'Pre-opercle', 'Opercle', 'Opercular spines', 'Pectoral fin', 'Pelvic fin',
  'Anus', 'Anal fin', 'Anal finlets', 'Caudal fin', 'Keel', 'Caudal peduncle', 'Lateral line',
  'Scales', 'Ventral scutes', 'Lateral scutes', 'Adipose fin', 'Dorsal fin', 'Dorsal finlets'];
export const countFields = ['Dorsal fin spines', 'Dorsal soft rays', 'Anal fin spines', 'Anal soft rays', 'Pelvic fin spines', 'Pelvic soft rays'];
export const observationStates = ['unknown', 'not_observed', 'not_applicable', 'observed'];

// Blank measurements must remain missing; never coerce them to zero.
export function parseMeasurement(value, status) {
  if (!observationStates.includes(status)) throw new Error('Unknown observation state.');
  if (status !== 'observed') return null;
  const raw = String(value ?? '').trim();
  if (!raw) throw new Error('Enter a value for each measured field, or choose a missing-data state.');
  const number = Number(raw);
  if (!Number.isFinite(number) || number <= 0) throw new Error('Measurements must be positive millimetre values.');
  return number;
}

export function parseCount(value, status) {
  if (!observationStates.includes(status)) throw new Error('Unknown observation state.');
  if (status !== 'observed') return null;
  const raw = String(value ?? '').trim();
  if (!/^\d+$/.test(raw) || !Number.isSafeInteger(Number(raw))) throw new Error('Observed counts must be whole numbers, including zero when appropriate.');
  return Number(raw);
}

// Spreadsheet applications can execute user text beginning with formula characters.
export function csvCell(value) {
  let text = String(value ?? '');
  if (/^[\s]*[=+@-]/.test(text) || /^[\t\r\n]/.test(text)) text = "'" + text;
  return '"' + text.replaceAll('"', '""') + '"';
}
export function notebookCsv(draft) {
  const rows = [['section', 'field', 'status', 'value', 'unit', 'source'],
    ['identity', 'scientificName', 'package_identity_unconfirmed', draft.package.scientificName, '', 'package'],
    ['identity', 'version', 'package_version', draft.package.version, '', 'package'],
    ['record', 'basis', '', draft.basis, '', 'learner'],
    ['record', 'specimenReference', '', draft.specimenReference, '', 'learner'],
    ['record', 'referenceAndTools', '', draft.referenceAndTools, '', 'learner'],
    ...draft.measurements.map(r => ['measurement', r.code, r.status, r.value, 'mm', draft.basis]),
    ...draft.classifications.map(r => ['classification', r.field, r.status, r.value, '', draft.basis]),
    ...draft.features.map(r => ['external_feature', r.field, r.status, '', '', draft.basis]),
    ...draft.counts.map(r => ['meristic_count', r.field, r.status, r.value, 'count', draft.basis]),
    ...draft.organs.flatMap(r => Object.entries(r).filter(([key]) => key !== 'structure').map(([key, value]) => ['organ', r.structure + '.' + key, r.status, value, '', draft.basis])),
    ...Object.entries(draft.reflections).map(([key, value]) => ['reflection', key, '', value, '', 'learner']),
    ['notice', 'limitations', '', draft.notice, '', 'application'],
  ];
  return rows.map(row => row.map(csvCell).join(',')).join('\r\n');
}
