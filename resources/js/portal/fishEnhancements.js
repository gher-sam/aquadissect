import { request, appUrl } from './api.js';
import { escapeHtml as e } from './format.js';
import { showFishDetails, fishProfileFields } from '../fishDetails.js';
import { signOutPending } from './sessionAccess.js';
import { createUuid } from '../uuid.js';

function routePath(url){const u=new URL(url,location.href);if(u.origin!==location.origin)throw new Error('Invalid lesson URL.');const base=String(window.AQUADISSECT_BASE_URL||'').replace(/\/$/,'');if(base && !u.pathname.startsWith(base+'/'))throw new Error('Invalid lesson path.');return u.pathname.slice(base.length)+u.search;}

function dialog(title,body) {
  const d=document.createElement('dialog');d.className='enhancement-dialog';d.innerHTML=`<button type="button" data-close>Close</button><h2>${e(title)}</h2>${body}<p role="status" class="enhancement-message" data-message></p>`;
  d.querySelector('[data-close]').onclick=()=>d.close();d.onclose=()=>d.remove();document.body.append(d);d.showModal();return d;
}
function message(d,text){d.querySelector('[data-message]').textContent=text;}
const nameRow=n=>`<fieldset data-local-name><legend>Local name</legend>${['name','language','locality','source'].map(k=>`<label>${k==='language'?'Language or dialect':k[0].toUpperCase()+k.slice(1)}<input data-name-field="${k}" required maxlength="${k==='source'?500:150}" value="${e(n[k]||'')}"></label>`).join('')}<button type="button" data-remove-name>Remove this name</button></fieldset>`;

async function revision(button) {
  const {package:m}=await request(routePath(button.dataset.manifestUrl));
  if(signOutPending())return;
  const p=m.speciesProfile||{};
  const d=dialog('Create a revised fish lesson',`<p>Save a new draft version. Independent faculty review and administrator publication are required before students receive it.</p><form data-revision>
    <label>New version<input name="version" required placeholder="For example: 1.2.0"></label>
    <p>${e(m.species.commonName)} · <em>${e(m.species.scientificName)}</em></p>
    ${Object.entries(fishProfileFields).map(([key,label])=>`<label>${label}<textarea name="${key}" maxlength="5000">${e(p[key]||'')}</textarea></label>`).join('')}
    <h3>Local names</h3><div data-names>${(p.localNames||[]).map(nameRow).join('')}</div><button type="button" data-add-name>Add local name</button>
    <details><summary>Correct anatomy images and labels</summary><p>Upload reviewed source imagery for a new draft. Each image is re-encoded to remove metadata. The original package is retained. Matching hotspot definitions are required if structures move.</p>
    ${['natural','external','internal'].map(k=>`<label>${k} image<input type="file" name="${k}" accept="image/png,image/jpeg,image/webp"></label>`).join('')}
    <label>Complete anatomy and hotspot file (JSON)<input type="file" name="anatomy" accept=".json"></label></details>
    <details><summary>Measurement references</summary><p>Only the new package's independent approval enables physical units. Reference endpoints use percentages of the source image. An illustration gives an illustrative estimate, not an actual specimen size. Replacing an image clears its old calibration.</p>
    ${['natural','external','internal'].map(k=>{const c=m.calibrations?.[k];return `<fieldset data-calibration="${k}"><legend>${k} image</legend><label><input type="checkbox" data-enabled ${c?'checked':''}> Include calibration</label>
      ${[['ax','Start X (%)',c?.a?.x*100],['ay','Start Y (%)',c?.a?.y*100],['bx','End X (%)',c?.b?.x*100],['by','End Y (%)',c?.b?.y*100],['lengthMm','Reference length (mm)',c?.lengthMm]].map(([n,l,v])=>`<label>${l}<input data-cal="${n}" type="number" step="any" min="0" ${n==='lengthMm'?'':'max="100"'} value="${Number.isFinite(v)?v:''}"></label>`).join('')}
      <label>Reference source<input data-cal="source" value="${e(c?.source||'')}" maxlength="1000"></label><label>Image basis<select data-cal="basis"><option value="illustrative_estimate">Illustration — estimate only</option><option value="photograph" ${c?.basis==='photograph'?'selected':''}>Calibrated specimen photograph</option></select></label><label>Reviewed scope<select data-cal="scope"><option value="body">Body only</option><option value="parts" ${c?.scope==='parts'?'selected':''}>Body and anatomical parts</option></select></label></fieldset>`;}).join('')}</details>
    <button type="submit">Save new draft version</button></form>`);
  const form=d.querySelector('form');
  d.querySelector('[data-add-name]').onclick=()=>d.querySelector('[data-names]').insertAdjacentHTML('beforeend',nameRow({}));
  d.addEventListener('click',ev=>ev.target.closest('[data-remove-name]')?.closest('fieldset')?.remove());
  form.onsubmit=async ev=>{
    ev.preventDefault();const submit=form.querySelector('[type=submit]');submit.disabled=true;
    try{
      const profile=Object.fromEntries(Object.keys(fishProfileFields).map(k=>[k,form.elements[k].value.trim()]));
      profile.localNames=[...form.querySelectorAll('[data-local-name]')].map(row=>Object.fromEntries([...row.querySelectorAll('[data-name-field]')].map(f=>[f.dataset.nameField,f.value.trim()])));
      const body=new FormData();body.set('version',form.elements.version.value);body.set('profile',JSON.stringify(profile));
      const calibrations={};
      for(const row of form.querySelectorAll('[data-calibration]')){
        const key=row.dataset.calibration,upload=form.elements[key].files[0];
        if(upload)body.set(key,upload);
        if(!row.querySelector('[data-enabled]').checked)continue;
        if(upload)throw new Error('Save the replacement image without calibration first. Then create a revision using its exact source image and reference endpoints.');
        const fields=Object.fromEntries([...row.querySelectorAll('[data-cal]')].map(f=>[f.dataset.cal,f.value]));
        if(['ax','ay','bx','by','lengthMm'].some(k=>fields[k].trim()==='')||!fields.source.trim())throw new Error('Complete all enabled calibration fields.');
        const image=new Image();image.src=m.assetRecords[key].url;await image.decode();
        calibrations[key]={a:{x:Number(fields.ax)/100,y:Number(fields.ay)/100},b:{x:Number(fields.bx)/100,y:Number(fields.by)/100},lengthMm:Number(fields.lengthMm),width:image.naturalWidth,height:image.naturalHeight,imageSha256:m.assetRecords[key].sha256,source:fields.source,basis:fields.basis,scope:fields.scope};
      }
      body.set('calibrations',JSON.stringify(calibrations));
      if(form.elements.anatomy.files[0])body.set('anatomy',await form.elements.anatomy.files[0].text());
      const saved=await request('/api/staff/species-packages/'+button.dataset.revisePackage+'/revision',{method:'POST',body});
      message(d,'Draft v'+saved.package.version+' saved. Close and reload Species content to preview it and send it for faculty review.');
      form.hidden=true;
    }catch(error){message(d,error.message);}finally{submit.disabled=false;}
  };
}

export function setupFishEnhancements(){
  document.addEventListener('click',async ev=>{
    if(ev.target.closest('[data-portal-signout]'))document.querySelectorAll('.enhancement-dialog').forEach(d=>d.close());
    const details=ev.target.closest('[data-fish-manifest]'),edit=ev.target.closest('[data-revise-package]');
    if(!details&&!edit)return;
    try{if(edit)await revision(edit);else{const {package:m}=await request(routePath(details.dataset.fishManifest));if(!signOutPending())showFishDetails(m);}}
    catch(error){const d=dialog('Fish details','');message(d,error.message);}
  });
}

export async function mountAssessmentPanel(container,state){
  if(!container||!state.currentUser)return;
  const owner=state.currentUser.id,role=state.currentUser.role;
  const host=document.createElement('section');host.className='enhancement-panel';host.innerHTML='<h2>Pretest and posttest</h2><p role="status">Loading assessment records…</p>';container.append(host);
  try{
    const data=await request('/api/paired-assessments');
    if(signOutPending()||state.currentUser?.id!==owner||!host.isConnected)return;
    const reload=()=>{host.remove();void mountAssessmentPanel(container,state);};
    host.innerHTML='<h2>Pretest and posttest</h2><p>Scores show recorded changes. Missing scores are not zero. New instruments stay disabled until independent faculty approval and administrator activation.</p>';
    for(const study of data.studies){
      const learnerLabel=id=>{if(role==='student')return 'You';const learner=state.classes.find(c=>c.id===study.classId)?.students?.find(u=>u.id===id);return learner ? learner.name+' ('+(learner.identityNumber||id)+')' : 'Account '+id;};
      const section=document.createElement('article');section.className='enhancement-panel';
      const score=(a,state)=>state==='voided'?'Voided — history retained':a?`${a.score} / ${a.total}`:'Not yet taken';
      section.innerHTML=`<h3>${e(study.title)} · ${e(study.instrumentVersion)}</h3><p>Section ${study.classId} · ${e(study.status)}</p><div class="table-scroll"><table><thead><tr><th>Learner</th><th>Pretest</th><th>Posttest</th><th>Change</th></tr></thead><tbody>${study.rows.map(row=>`<tr><td>${e(learnerLabel(row.studentId))}</td><td>${score(row.pre,row.preState)}</td><td>${score(row.post,row.postState)}</td><td>${row.pointDifference===null?'Awaiting a complete pair':`${row.pointDifference>0?'+':''}${row.pointDifference} points · ${row.percentagePointDifference>0?'+':''}${row.percentagePointDifference} percentage points`}</td></tr>`).join('')}</tbody></table></div><a href="${appUrl('/api/paired-assessments/'+study.id+'/export')}">Download score CSV</a>`;
      host.append(section);
      if(role!=='student'&&study.status==='approved')section.insertAdjacentHTML('beforeend','<p>Faculty approved · Awaiting administrator activation. Students cannot access this test pair yet. An administrator must publish and assign the reviewed lesson, then select “Activate approved tests” here.</p>');
      if(role==='student'){
        const row=study.rows.find(r=>r.studentId===owner);
        for(const phase of ['pre','post'])if(study.status==='active' && (!row || row[phase+'State']==='not_taken')){const b=document.createElement('button');b.textContent=phase==='pre'?'Take pretest':row?.pre?'Take posttest':'Posttest · take pretest first';b.disabled=phase==='post'&&!row?.pre;b.onclick=()=>void takeAssessment(study,phase,reload);section.append(b);}
      }else{
        const view=document.createElement('button');view.textContent='Review test forms and keys';view.onclick=async()=>{try{const def=await request('/api/paired-assessments/'+study.id+'/definition');if(signOutPending())return;dialog('Test forms and answer keys',['pre','post'].map(phase=>`<h3>${phase==='pre'?'Pretest':'Posttest'}</h3><ol>${def[phase+'Questions'].map(q=>`<li>${e(q.prompt)}<ul>${q.options.map(o=>`<li>${e(o)}</li>`).join('')}</ul><p>Key: ${e(q.answer)}</p></li>`).join('')}</ol>`).join(''));}catch(err){section.querySelector('p').textContent=err.message;}};section.append(view);
        const actions=[];
        if(study.status==='draft'&&study.authorId!==owner)actions.push(['approve','Record faculty approval']);
        if(role==='admin'&&study.status==='approved')actions.push(['activate','Activate approved tests']);
        if(['admin','instructor'].includes(role)&&study.status!=='disabled')actions.push(['disable','Disable tests']);
        for(const [action,label]of actions){const b=document.createElement('button');b.textContent=label;b.onclick=()=>actionDialog(study,action,reload);section.append(b);}
        if(['admin','instructor'].includes(role)&&study.attemptHistory?.length){const history=document.createElement('details');history.innerHTML='<summary>Retained attempt history</summary>'+study.attemptHistory.map(a=>`<p>Attempt ${a.id} · Learner ${a.user_id} · ${e(a.phase)} · ${a.score}/${a.total} · ${a.voided_at?'Voided (retained)':`<button type="button" data-void="${a.id}">Void with reason</button>`}</p>`).join('');history.onclick=ev=>{const b=ev.target.closest('[data-void]');if(b)actionDialog(study,'void',reload,Number(b.dataset.void));};section.append(history);}
      }
    }
    if(!data.studies.length)host.insertAdjacentHTML('beforeend','<p>No enabled pretest/posttest assignment is available.</p>');
    if(['admin','instructor'].includes(role)){
      const create=document.createElement('details');create.innerHTML=`<summary>Prepare a test pair for faculty review</summary><form><label>Section<select name="classId" required>${state.classes.filter(c=>c.status==='active').map(c=>`<option value="${c.id}">${e(c.name)}</option>`).join('')}</select></label><label>Lesson version<select name="packageId" required>${state.species.map(p=>`<option value="${p.id}">${e(p.commonName)} v${e(p.version)}</option>`).join('')}</select></label><label>Test title<input name="title" required maxlength="160"></label><label>Instrument version<input name="instrumentVersion" required maxlength="80"></label><p>Upload each faculty-prepared question form as JSON: an array of objects with id, prompt, options (array of choices), and answer (one exact choice). Equal item totals are required. Keys remain private.</p><label>Pretest form<input name="pre" type="file" accept=".json" required></label><label>Posttest form<input name="post" type="file" accept=".json" required></label><button type="submit">Save disabled draft</button><p data-message role="status"></p></form>`;
      const f=create.querySelector('form');f.onsubmit=async ev=>{ev.preventDefault();const b=f.querySelector('button');b.disabled=true;try{await request('/api/paired-assessments',{method:'POST',body:{classId:Number(f.elements.classId.value),packageId:Number(f.elements.packageId.value),title:f.elements.title.value,instrumentVersion:f.elements.instrumentVersion.value,preQuestions:JSON.parse(await f.elements.pre.files[0].text()),postQuestions:JSON.parse(await f.elements.post.files[0].text())}});reload();}catch(err){f.querySelector('[data-message]').textContent=err.message;}finally{b.disabled=false;}};host.append(create);
    }
  }catch(error){host.querySelector('[role=status]').textContent=error.message;}
}
function actionDialog(study,action,reload,attemptId){
  const approve=action==='approve',activate=action==='activate';
  const d=dialog(approve?'Faculty assessment review':activate?'Activate reviewed assessment':'Record assessment action',`<form>${approve?'<label>Faculty validation evidence<textarea name="evidence" required minlength="10" maxlength="3000"></textarea></label><label><input name="formsValidated" type="checkbox" required>I reviewed the forms, keys, equivalence and instrument validation.</label><label><input name="policyConfirmed" type="checkbox" required>I confirmed one scored attempt per phase, timing and applicable research permissions.</label>':activate?'<p>Enable this reviewed pair for its selected section? Existing learning and formative records stay retained.</p>':'<label>Reason<textarea name="reason" required minlength="10" maxlength="1000"></textarea></label>'}<button type="submit">Confirm ${e(action)}</button></form>`);
  const f=d.querySelector('form');f.onsubmit=async ev=>{ev.preventDefault();const body=Object.fromEntries(new FormData(f));if(approve){body.formsValidated=f.elements.formsValidated.checked;body.policyConfirmed=f.elements.policyConfirmed.checked;}if(attemptId)body.attemptId=attemptId;try{await request('/api/paired-assessments/'+study.id+'/actions/'+action,{method:'POST',body});d.close();reload();}catch(err){message(d,err.message);}};
}
async function takeAssessment(study,phase,reload){
  try{
    const data=await request('/api/paired-assessments/'+study.id+'/'+phase+'/questions');
    if(signOutPending())return;
    const d=dialog(phase==='pre'?'Pretest':'Posttest',`<p>One scored submission. Stay online to submit.</p><form>${data.questions.map((q,i)=>`<fieldset><legend>${i+1}. ${e(q.prompt)}</legend>${q.options.map((option,n)=>`<label><input type="radio" name="q${i}" value="${n}" required>${e(option)}</label>`).join('')}</fieldset>`).join('')}<button type="submit">Submit answers</button></form>`);
    const uuid=createUuid(),f=d.querySelector('form');f.onsubmit=async ev=>{ev.preventDefault();const b=f.querySelector('button');b.disabled=true;try{const answers=Object.fromEntries(data.questions.map((q,i)=>[q.id,q.options[Number(f.elements['q'+i].value)]]));const result=await request('/api/paired-assessments/'+study.id+'/'+phase+'/attempts',{method:'POST',body:{attemptUuid:uuid,answers}});message(d,'Saved: '+result.result.score+' / '+result.result.total);f.hidden=true;reload();}catch(err){message(d,err.message);}finally{b.disabled=false;}};
  }catch(error){const d=dialog('Assessment unavailable','');message(d,error.message);}
}
