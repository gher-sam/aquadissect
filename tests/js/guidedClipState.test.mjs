import test from 'node:test';
import assert from 'node:assert/strict';
import { editableClipSteps, moveClipStep, facultyWordingPrompt, acceptFacultySuggestion } from '../../resources/js/portal/guidedClipState.js';

test('clip movement preserves required activity order across the full sequence',()=>{
  const core=[{id:'s1',clipOnly:false},{id:'s2',clipOnly:false,terminal:true}];
  let steps=[core[0],...Array.from({length:72},(_,i)=>({id:`clip${i}`,clipOnly:true})),core[1]];
  steps=moveClipStep(steps,'clip0',-1);
  assert.deepEqual(steps.filter(s=>!s.clipOnly).map(s=>s.id),['s1','s2']);
  assert.equal(steps[0].id,'clip0');
  assert.equal(moveClipStep(steps,'s1',1),steps);
  assert.equal(moveClipStep(steps,'clip0',-1),steps);
  assert.equal(moveClipStep(steps,'clip71',1),steps);
});
test('wording remains faculty authored until explicit acceptance and prompts preserve hazards',()=>{
  const step={id:'s1',title:'Inspect markings',description:'Keep the fish in the tray. Avoid the venom-bearing spines.',prompt:'Observe the markings.',action:'Continue',clipOnly:true,videoAsset:'clip-01'};
  const steps=[step];const prompt=facultyWordingPrompt(step);
  assert.match(prompt,/every safety warning/);assert.match(prompt,/Do not invent procedural actions/);assert.match(prompt,/Keep the fish in the tray/);
  assert.equal(step.description,steps[0].description);
  const accepted=acceptFacultySuggestion(steps,'s1','Keep the specimen in its existing tray.');
  assert.equal(steps[0].description,step.description);assert.equal(accepted[0].description,'Keep the specimen in its existing tray.');
  assert.throws(()=>acceptFacultySuggestion(steps,'s1',' '));
  assert.equal('clipOnly' in editableClipSteps(steps)[0],false);
});
