import test from 'node:test';
import assert from 'node:assert/strict';
import { queuedModuleProgress, moduleSaveMessage } from '../../resources/js/portal/moduleProgress.js';

const requiredStepIds = ['orientation', 'handling', 'tool-selection'];
test('early offline activity never completes missing steps', () => {
  const result = queuedModuleProgress({ requiredStepIds, completedStepIds:['tool-selection'], answers:{q1:'x'} });
  assert.equal(result.completed, false);
  assert.equal(result.activitySubmitted, true);
  assert.equal(result.pendingSync, true);
  assert.doesNotMatch(moduleSaveMessage({progress:result}), /Module complete/);
});
test('all viewed steps still require an activity submission', () => {
  assert.equal(queuedModuleProgress({requiredStepIds, completedStepIds:requiredStepIds}).completed, false);
  const progress = queuedModuleProgress({requiredStepIds, completedStepIds:requiredStepIds, activitySubmitted:true, completedAt:'2026-10-20T00:00:00Z'});
  assert.equal(progress.completed, true);
  assert.match(moduleSaveMessage({queued:true,progress}), /Reconnect/);
  assert.doesNotMatch(moduleSaveMessage({queued:true,progress}), /Module complete/);
});
test('duplicate or unknown IDs cannot replace missing required steps', () => {
  assert.equal(queuedModuleProgress({requiredStepIds, completedStepIds:['orientation','orientation','unknown'],answers:{}}).completed,false);
});
test('zero score and incomplete status remain separate', () => {
  assert.match(moduleSaveMessage({attempt:{score:0,total:5},progress:{completed:false}}), /0 of 5.*Finish every remaining/);
  assert.match(moduleSaveMessage({attempt:{score:0,total:5},progress:{completed:true}}), /0 of 5.*Module complete/);
  assert.match(moduleSaveMessage({},false), /Preview only/);
});
