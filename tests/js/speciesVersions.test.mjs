import test from 'node:test';
import assert from 'node:assert/strict';
import { staffLessonVersions } from '../../resources/js/portal/speciesFocus.js';
import { speciesLibrary } from '../../resources/js/portal/dashboards.js';
const old = {id:1,slug:'rabbitfish',version:'1.0.0',status:'review',commonName:'Rabbitfish'};
const latest = {...old,id:2,version:'1.1.0'};
test('staff lessons select the newest review version, retaining source records', () => {
  const records=[old,latest];
  assert.deepEqual(staffLessonVersions(records),[latest]);
  assert.equal(records.length,2);
  assert.equal((speciesLibrary(records,{singleSpecies:true}).match(/data-package-card=/g)||[]).length,1);
  assert.match(speciesLibrary(records,{singleSpecies:true}),/Version history \(2\)/);
});
test('numeric versions, active publication and archives are handled correctly', () => {
  const active={...old,status:'published',isCurrent:true};
  assert.deepEqual(staffLessonVersions([latest,active]),[active]);
  assert.deepEqual(staffLessonVersions([{...old,version:'1.9.0'},{...latest,version:'1.10.0'}]),[{...latest,version:'1.10.0'}]);
  assert.deepEqual(staffLessonVersions([{...latest,status:'archived'}]),[]);
});
test('student assigned versions and content management remain separate entries', () => {
  assert.equal((speciesLibrary([old,latest]).match(/data-package-card=/g)||[]).length,2);
  assert.equal((speciesLibrary([old,latest],{manager:true,singleSpecies:true}).match(/data-package-card=/g)||[]).length,2);
});

test('an unprepared offline species copy has no lesson-open or details action', () => {
  const html=speciesLibrary([{...latest,status:'published',offlineUnavailable:true,naturalImageUrl:null}],{offline:true});
  assert.doesNotMatch(html,/data-open-package=|data-fish-manifest=/);
  assert.match(html,/Prepare this lesson while connected/);
});
