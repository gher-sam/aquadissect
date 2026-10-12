import test from 'node:test';
import assert from 'node:assert/strict';
import { measurementFields, parseMeasurement, parseCount, csvCell, notebookCsv } from '../../resources/js/observationNotebookData.js';

test('all 16 measurement codes are unique', () => { assert.equal(measurementFields.length, 16); assert.equal(new Set(measurementFields.map(r => r[0])).size, 16); });
test('missing observations never turn into zero', () => {
  for (const state of ['unknown', 'not_observed', 'not_applicable']) { assert.equal(parseMeasurement('', state), null); assert.equal(parseMeasurement('23', state), null); assert.equal(parseCount('0', state), null); }
});
test('measured lengths require positive finite values', () => {
  assert.equal(parseMeasurement('12.5', 'observed'), 12.5);
  for (const value of ['', ' ', '0', '-2', 'NaN', 'Infinity', '1,2']) assert.throws(() => parseMeasurement(value, 'observed'));
  assert.throws(() => parseMeasurement('12', 'made_up'));
});
test('meristic counts permit observed zero but reject fractional and signed input', () => {
  assert.equal(parseCount('0', 'observed'), 0); assert.equal(parseCount('13', 'observed'), 13);
  for (const value of ['', '-1', '1.1', 'Infinity', '1e3', '9007199254740992']) assert.throws(() => parseCount(value, 'observed'));
  assert.throws(() => parseCount('2', 'made_up'));
});
test('spreadsheet formulas and quotes are safely escaped', () => {
  assert.equal(csvCell('=SUM(1,2)'), '\"\'=SUM(1,2)\"');
  assert.equal(csvCell('hello \"fish\"'), '\"hello \"\"fish\"\"\"');
  for (const text of ['+cmd', '-1', '@x', '  =x', '\ttext', '\ntext']) assert.ok(csvCell(text).startsWith('\"\''));
});
test('CSV retains missing states, version, source and observations', () => {
  const csv = notebookCsv({package:{scientificName:'Siganus guttatus',version:'1.1.0'},basis:'virtual_illustration',specimenReference:'',referenceAndTools:'',measurements:[{code:'TL',status:'unknown',value:null}],classifications:[],features:[],counts:[{field:'Dorsal fin spines',status:'observed',value:0}],organs:[{structure:'liver',status:'not_observed',remarks:'Uncertain'}],reflections:{orientation:'=unsafe'},notice:'Draft'});
  assert.match(csv, /unknown/); assert.match(csv, /1\.1\.0/); assert.match(csv, /virtual_illustration/); assert.match(csv, /Uncertain/); assert.match(csv, /'=unsafe/); assert.match(csv, /\"0\"/);
});
