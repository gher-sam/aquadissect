import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { referenceFigureFor } from '../../resources/js/anatomyReferenceFigures.js';
import { builtInRegionMaps } from '../../resources/js/data/anatomyRegionMaps.js';
import { regionFor } from '../../resources/js/anatomyRegions.js';

const map = Object.values(builtInRegionMaps).find(map => map.layer === 'internal');
const source = map.sources.find(source => source.id === 'guttatus-kidney-2026');
const kidney = { id: 'kidney', name: 'Kidney', layer: 'internal' };
const manifest = { species: { slug: 'rabbitfish', scientificName: 'Siganus guttatus' }, assetRecords: { internal: { sha256: map.imageSha256 } }, contentData: { anatomy: { structures: [kidney] } } };

test('the published kidney figure stays separate from unavailable whole-organ geometry', () => {
  const region = regionFor(manifest, kidney), figure = referenceFigureFor(region.sources[0], kidney, manifest);
  assert.equal(region.available, false); assert.equal(region.polygons, undefined);
  assert.equal(figure.id, 'guttatus-kidney-histology-2026');
  assert.match(figure.limitation, /Microscope view of diseased/);
  assert.match(figure.caption, /panel C/);
});

test('reference images cannot be reassigned to another species, part or layer', () => {
  assert.equal(referenceFigureFor(source, kidney, { species: { scientificName: 'Siganus canaliculatus' } }), null);
  assert.equal(referenceFigureFor(source, { ...kidney, name: 'Heart' }, manifest), null);
  assert.equal(referenceFigureFor(source, { ...kidney, layer: 'external' }, manifest), null);
  assert.equal(referenceFigureFor({ ...source, appliesTo: [{ id: 'heart', label: 'Heart' }] }, kidney, manifest), null);
});

test('a citation edit retains the image only while it still refers to the same publication', () => {
  assert.ok(referenceFigureFor({ ...source, title: 'Edited citation', url: source.url + '#fig5' }, kidney, manifest));
  assert.equal(referenceFigureFor({ ...source, url: 'https://doi.org/10.1111/jwas.70106' }, kidney, manifest), null);
  assert.equal(referenceFigureFor({ ...source, figureId: '__proto__' }, kidney, manifest), null);
  assert.equal(referenceFigureFor({ ...source, figureId: 'unregistered-image' }, kidney, manifest), null);
});

test('future structure IDs can use the same source without hard-coded kidney identifiers', () => {
  const part = { ...kidney, id: 'renal-tissue' };
  assert.ok(referenceFigureFor({ ...source, appliesTo: [{ id: part.id, label: part.name }] }, part, manifest));
  assert.equal(referenceFigureFor(source, part, manifest), null);
});

test('the licensed image matches the original NCBI figure bytes and retains attribution', () => {
  const figure = referenceFigureFor(source, kidney, manifest);
  const bytes = readFileSync(new URL('../../resources/media/references/guttatus-histopathology-2026-figure5.jpg', import.meta.url));
  assert.equal(createHash('md5').update(bytes).digest('hex'), '2e1b8d37803671ed4fe481dbfaf60919');
  assert.equal(createHash('sha256').update(bytes).digest('hex'), figure.imageSha256);
  assert.equal(figure.licenseUrl, 'https://creativecommons.org/licenses/by/4.0/');
  assert.match(figure.credit, /Hoai et al/);
});
