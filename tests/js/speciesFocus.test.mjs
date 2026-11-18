import test from 'node:test';
import assert from 'node:assert/strict';
import { focusedSpecies, focusedClasses } from '../../resources/js/portal/speciesFocus.js';
const species = [{ id: 1, slug: 'tilapia' }, { id: 2, slug: 'rabbitfish' }, { id: 3, slug: 'scad' }];
test('old offline catalogs show only rabbitfish and retain originals', () => {
  assert.deepEqual(focusedSpecies(species), [species[1]]);
  assert.equal(species.length, 3);
  assert.deepEqual(focusedSpecies(species, []), species);
});
test('offline assignments use slug or package mapping without deleting originals', () => {
  const classes = [{id:1, assignments:[{packageId:1},{speciesPackageId:2},{speciesSlug:'rabbitfish', packageId:4},{packageId:999}]}];
  assert.equal(focusedClasses(classes,species)[0].assignments.length,2);
  assert.equal(classes[0].assignments.length,4);
  assert.deepEqual(focusedClasses(classes,species,[]),classes);
});

test('explicit catalog inclusion reaches the interface and offline assignments while old hidden species stay hidden', () => {
  const future = {id:9, slug:'qa-future-species', catalogVisible:true};
  const records = [...species, future];
  assert.deepEqual(focusedSpecies(records), [species[1], future]);
  const classes = [{id:1, assignments:[{packageId:1}, {packageId:9}, {packageId:10, speciesSlug:'another-future', catalogVisible:true}]}];
  assert.deepEqual(focusedClasses(classes, records)[0].assignments, classes[0].assignments.slice(1));
  assert.equal(focusedSpecies([{id:10,slug:'tilapia',catalogVisible:'true'}]).length,0);
});
