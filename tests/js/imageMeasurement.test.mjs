import test from 'node:test';
import assert from 'node:assert/strict';
import { fittedImageRect, imagePoint, measuredSegment } from '../../resources/js/imageMeasurementData.js';
import { fishDetailsHtml } from '../../resources/js/fishDetails.js';

const calibration={a:{x:.1,y:.5},b:{x:.9,y:.5},lengthMm:200,width:1000,height:500,imageSha256:'a',source:'Synthetic calibrated reference',basis:'photograph',scope:'body'};
test('letterboxing and zoom preserve the same source endpoints and millimetres',()=>{
 for(const r of [{left:0,top:0,width:400,height:400},{left:20,top:30,width:1600,height:1200},{left:-200,top:-300,width:3000,height:1500}]){
  const fit=fittedImageRect(1000,500,r);
  const a=imagePoint(fit.left+.1*fit.width,fit.top+.5*fit.height,fit);
  const b=imagePoint(fit.left+.9*fit.width,fit.top+.5*fit.height,fit);
  assert.equal(measuredSegment(a,b,1000,500,calibration,true,'a').value,200);
 }
});
test('unapproved, stale, invalid and part-ineligible calibrations never produce physical units',()=>{
 for(const args of [[calibration,false,'a','body'],[calibration,true,'b','body'],[calibration,true,'a','parts'],[{...calibration,b:calibration.a},true,'a','body']]){
  assert.equal(measuredSegment({x:0,y:0},{x:1,y:0},1000,500,...args).unit,'% image width');
 }
 const estimate=measuredSegment({x:.1,y:.5},{x:.9,y:.5},1000,500,{...calibration,basis:'illustrative_estimate',scope:'parts'},true,'a','parts');
 assert.match(estimate.label,/Illustrative estimate/);
 assert.equal(estimate.value,200);
});
test('fish details escape public profile fields and retain old-package fallback',()=>{
 const html=fishDetailsHtml({species:{commonName:'<script>x</script>',scientificName:'Test species'},speciesProfile:{localNames:[{name:'<img>',language:'Test',locality:'Test',source:'Test'}],privateNotes:'NEVER SHOW'},contentData:{lesson:{description:'Legacy description'}}});
 assert(!html.includes('<script>'));assert(!html.includes('NEVER SHOW'));assert(html.includes('&lt;img&gt;'));assert(html.includes('Legacy description'));assert(html.includes('Not supplied'));
});
