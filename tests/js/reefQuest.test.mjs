import test from 'node:test';
import assert from 'node:assert/strict';
import { questContexts, questBadgeView } from '../../resources/js/portal/reefQuestView.js';
import { resolveMotionPreference, motionPreferenceQuery, onMotionChange } from '../../resources/js/motionPreferences.js';
import { practiceView } from '../../resources/js/practiceView.js';

test('learning contexts retain section/version boundaries and exclude unpublished or archived offerings', () => {
  const packages=[{id:10,status:'published',version:'1.0'},{id:11,status:'review'},{id:12,status:'archived',version:'0.9'}];
  const classes=[{id:1,status:'active',prerequisiteStatus:{satisfied:true},assignments:[{packageId:10},{packageId:10},{packageId:11},{packageId:12}]},
    {id:2,status:'active',prerequisiteStatus:{satisfied:false},assignments:[{packageId:10}]},{id:3,status:'archived',assignments:[{packageId:10}]}];
  const contexts=questContexts(classes,packages);
  assert.deepEqual(contexts.map(c=>[c.key,c.ready]),[['1:10',true],['2:10',false]]);
  assert.equal(questContexts([{id:4,status:'active',assignments:[{packageId:10}]}],packages)[0].ready,false);
});

test('badge rendering escapes retained content and does not turn unearned badges into earned ones', () => {
  const html=questBadgeView([{name:'<script>private</script>',earned:false},{name:'First discovery',earned:true}]);
  assert.ok(!html.includes('<script>'));
  assert.ok(html.includes('&lt;script&gt;'));
  assert.equal((html.match(/reef-badge is-earned/g)||[]).length,1);
  assert.ok(html.includes('To earn'));
});

test('device reduced motion takes precedence over a saved on preference', () => {
  assert.equal(resolveMotionPreference('on',true),false);
  assert.equal(resolveMotionPreference('off',false),false);
  assert.equal(resolveMotionPreference(null,false),true);
  const query=motionPreferenceQuery(); const listener=()=>{};
  query.addEventListener('change',listener); query.addEventListener('change',listener); query.removeEventListener('change',listener);
  const unsubscribe=onMotionChange(listener); unsubscribe();
});

const data={preview:false,challenges:[{id:'c1',kind:'function',clue:'Saved package clue',options:['A','B'],points:10}],summary:{xp:10,maxXp:10,completed:1,total:1,completedIds:['c1'],badges:[]}};
const state={index:0,selected:0,busy:false,hint:false,finished:false};
test('reward celebration uses only a confirmed new reward and is absent on repeat or unconfirmed answers', () => {
  assert.ok(practiceView(data,{...state,feedback:{correct:true,awardedXp:10,earnedBadges:[{name:'First discovery'}]}}).includes('data-practice-reward'));
  for(const feedback of [{correct:true,awardedXp:0,earnedBadges:[]},{unconfirmed:true},{correct:true,unconfirmed:true,awardedXp:10},{correct:false,awardedXp:0}]) {
    assert.ok(!practiceView(data,{...state,feedback}).includes('data-practice-reward'));
  }
  assert.ok(!practiceView({...data,preview:true},{...state,feedback:{correct:true,awardedXp:10,earnedBadges:[]}}).includes('data-practice-reward'));
});
