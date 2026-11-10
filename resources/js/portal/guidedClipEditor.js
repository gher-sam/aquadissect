import { request } from './api.js';
import { escapeHtml as e } from './format.js';
import { signOutPending, pendingSignOutKey } from './sessionAccess.js';
import { createUuid } from '../uuid.js';
import { editableClipSteps, moveClipStep, facultyWordingPrompt, acceptFacultySuggestion } from './guidedClipState.js';

let initialized = false;
const mib = bytes => `${(bytes / 1048576).toFixed(1)} MiB`;

async function openEditor(packageId) {
  const base = `/api/staff/species-packages/${packageId}/clip-drafts`;
  let state = await request(base, { method: 'POST' });
  if (signOutPending()) return;
  const endpoint = `${base}/${state.draftId}`;
  const d = document.createElement('dialog');
  d.className = 'guided-clip-editor';
  d.setAttribute('aria-labelledby', 'guidedClipTitle');
  d.innerHTML = `<header><h2 id="guidedClipTitle">Manage Rabbitfish guided clips</h2><button type="button" data-close>Close</button></header><p>Faculty write the steps and check the continuity. Saving creates a new draft lesson version for independent faculty review.</p><p data-state></p><p role="status" aria-live="polite" data-message></p><div data-content></div>`;
  document.body.append(d);
  let changed = false, busy = false, saved = false, savedVersion = '';
  const status = text => { if (d.isConnected) d.querySelector('[data-message]').textContent = text; };
  const warn = event => { if (changed) { event.preventDefault(); event.returnValue = ''; } };
  const lock = () => { d.querySelectorAll('dialog[open]').forEach(p => p.close()); d.close(); };
  const signout = event => { if (event.target.closest('[data-portal-signout]')) lock(); };
  const sessionChange = event => { if (event.key === pendingSignOutKey && event.newValue === '1') lock(); };
  window.addEventListener('beforeunload', warn);
  window.addEventListener('aquadissect-authorization-lost', lock);
  window.addEventListener('storage', sessionChange);
  document.addEventListener('click', signout);
  d.addEventListener('close', () => {
    d.querySelectorAll('video').forEach(v => { v.pause(); v.removeAttribute('src'); v.load(); });
    window.removeEventListener('beforeunload', warn);
    window.removeEventListener('aquadissect-authorization-lost', lock);
    window.removeEventListener('storage', sessionChange);
    document.removeEventListener('click', signout);
    d.remove();
  });
  async function saveWorking() {
    if (!changed) return;
    state = await request(endpoint, { method: 'PATCH', body: { revision: state.revision, steps: editableClipSteps(state.steps) } });
    changed = false;
  }
  async function run(work, saveFirst = true) {
    if (busy) return;
    busy = true;
    const disabled = [...d.querySelectorAll('button,input,textarea,select')].filter(x => !x.disabled);
    disabled.forEach(x => { x.disabled = true; });
    status('Saving or processing. Keep this window open.');
    try {
      if (saveFirst) await saveWorking();
      const result = await work();
      if (!d.isConnected || signOutPending()) return;
      render();
      status(saved ? `Draft v${savedVersion} saved. Send it for independent faculty review from Species content.` : 'Working draft saved. Existing lesson versions are unchanged.');
      return result;
    } catch (error) {
      status(`${error.status === 429 ? 'The request limit was reached. Wait a minute and retry.' : error.message} Your last saved working draft is retained.`);
    } finally {
      busy = false;
      disabled.forEach(x => { x.disabled = false; });
    }
  }
  function render() {
    const open = new Set([...d.querySelectorAll('details[data-step][open]')].map(x => x.dataset.step));
    d.querySelector('[data-state]').textContent = `Source v${state.sourceVersion}. Estimated lesson size ${mib(state.sizeBytes)} of ${mib(state.maxPackageBytes)}. Working draft expires ${new Date(state.expiresAt * 1000).toLocaleString()}.`;
    const host = d.querySelector('[data-content]');
    host.querySelectorAll('video').forEach(v => { v.pause(); v.removeAttribute('src'); v.load(); });
    if (saved || state.committedPackageId) {
      saved = true;
      host.innerHTML = `<p><strong>Draft ${savedVersion ? `v${e(savedVersion)} ` : 'version '}saved.</strong> Review, publication and section assignment remain separate.</p><button type="button" data-refresh>Close and refresh Species content</button>`;
      return;
    }
    host.innerHTML = `<div class="clip-editor-actions"><button type="button" data-add>Add clip step</button><button type="button" data-save>Save working draft</button><button type="button" data-preview>Preview sequence</button></div>
      <p>Save the working draft before leaving. Uploads and step edits remain private for 24 hours. Clips accept MP4/WebM up to 80 MiB and 10 seconds. Playback copies are silent and sized for browser viewing.</p>
      <ol class="clip-step-list">${state.steps.map((s,i) => `<li><details data-step="${e(s.id)}" ${open.has(s.id) ? 'open' : ''}><summary><strong>${i+1}. ${e(s.title)}</strong><span>${s.clipOnly ? 'Clip step' : 'Required activity'}${s.videoAsset ? ' · Clip attached' : ''}</span></summary>
        <div class="clip-step-fields"><label>Step title<input data-field="title" value="${e(s.title)}" maxlength="200" required></label><label>Faculty instructions<textarea data-field="description" rows="4" maxlength="5000" required>${e(s.description)}</textarea></label><label>Learner task<textarea data-field="prompt" rows="2" maxlength="2000" required>${e(s.prompt)}</textarea></label><label>Next action label<input data-field="action" value="${e(s.action)}" maxlength="100" required></label></div>
        <div class="clip-editor-actions">${s.clipOnly ? `<button type="button" data-move="-1" ${i===0?'disabled':''}>Move up</button><button type="button" data-move="1" ${i===state.steps.length-1 || state.steps[i+1]?.terminal?'disabled':''}>Move down</button><button type="button" data-remove>Remove clip step from draft</button>` : ''}${s.videoAsset && state.assets[s.videoAsset] ? '<button type="button" data-preview-step>Preview clip</button>' : ''}<button type="button" data-wording>Clarify wording</button></div>
        ${s.videoAsset && state.assets[s.videoAsset] ? `<video controls playsinline preload="none" aria-label="${e(s.title)}" src="${e(state.assets[s.videoAsset].url)}"></video><p>${e(state.assets[s.videoAsset].caption || '')}${state.assets[s.videoAsset].aiGenerated ? ' AI-generated educational media.' : ''} Faculty review required for this new version.</p>` : '<p>No clip attached.</p>'}
        <details class="clip-upload"><summary>${s.videoAsset ? 'Replace clip' : 'Upload clip'}</summary><form data-upload><label>MP4 or WebM<input name="asset" type="file" accept="video/mp4,video/webm" required></label><label>Caption for students<textarea name="caption" maxlength="1000" required></textarea></label><label>Source<input name="source" maxlength="500" required></label><label>Usage licence<input name="licence" maxlength="300" required></label><label>Was this video generated with AI?<select name="aiGenerated" required><option value="">Choose</option><option value="1">Yes</option><option value="0">No</option></select></label><button type="submit">Check and attach clip</button></form></details>
        </details></li>`).join('')}</ol>
      <section class="clip-version-save"><h3>Save as a new lesson version</h3><form data-commit><label>New version<input name="version" placeholder="For example: 1.3.1" maxlength="40" required></label><button type="submit">Save new draft version</button></form><button type="button" data-cancel-draft>Cancel and remove this working draft</button></section>`;
  }
  d.addEventListener('input', event => {
    const field = event.target.dataset.field;
    if (!field) return;
    const step = state.steps.find(x => x.id === event.target.closest('[data-step]').dataset.step);
    step[field] = event.target.value; changed = true;
    status('Unsaved instructions. Choose Save working draft before leaving.');
  });
  function showPreview(steps, title) {
    const p = document.createElement('dialog'); p.className='guided-clip-preview';p.setAttribute('aria-label',title);
    const heading=document.createElement('h2');heading.textContent=title;p.append(heading);
    const close=document.createElement('button');close.type='button';close.textContent='Close preview';p.append(close);
    steps.forEach(s=>{const section=document.createElement('section');const h=document.createElement('h3');h.textContent=`${state.steps.findIndex(x=>x.id===s.id)+1}. ${s.title}`;section.append(h);const text=document.createElement('p');text.textContent=s.description;section.append(text);const asset=state.assets[s.videoAsset];if(asset){const v=document.createElement('video');v.controls=true;v.playsInline=true;v.preload='none';v.src=asset.url;v.setAttribute('aria-label',s.title);section.append(v);const caption=document.createElement('p');caption.textContent=[asset.caption,asset.aiGenerated?'AI-generated educational media.':''].filter(Boolean).join(' ');section.append(caption);}p.append(section);});
    d.append(p);close.onclick=()=>p.close();p.onclose=()=>{p.querySelectorAll('video').forEach(v=>{v.pause();v.removeAttribute('src');v.load();});p.remove();};p.showModal();
  }
  d.addEventListener('click', event => {
    const b = event.target.closest('button'); if (!b || busy) return;
    const row = b.closest('[data-step]'); const step = row && state.steps.find(x => x.id === row.dataset.step);
    if (b.hasAttribute('data-close')) void run(async () => d.close());
    if (b.hasAttribute('data-refresh')) window.location.reload();
    if (b.hasAttribute('data-save')) void run(async () => {});
    if (b.hasAttribute('data-add')) void run(async () => {
      const next = { id: `n-${createUuid().replaceAll('-','')}`, title: 'New guided clip', description: 'Faculty instructions to be completed.', prompt: 'Watch the clip, then continue.', action: 'Continue', videoAsset: null, clipOnly: true };
      const terminal = state.steps.findIndex(s => s.terminal);
      state.steps.splice(terminal < 0 ? state.steps.length : terminal, 0, next);
      changed = true; await saveWorking();
      return next.id;
    }).then(id=>{const row=id && d.querySelector(`[data-step="${id}"]`);if(row){row.open=true;row.querySelector('[data-field="title"]').focus();}});
    if (b.hasAttribute('data-move')) void run(async () => { state.steps = moveClipStep(state.steps, step.id, Number(b.dataset.move)); changed = true; await saveWorking(); }).then(()=>{const row=d.querySelector(`[data-step="${step.id}"]`);const move=row?.querySelector(`[data-move="${b.dataset.move}"]:not(:disabled)`);(move||row?.querySelector('summary'))?.focus();});
    if (b.hasAttribute('data-remove')) void run(async () => { state.steps = state.steps.filter(x => x.id !== step.id); changed = true; await saveWorking(); });
    if (b.hasAttribute('data-cancel-draft')) void run(async () => { await request(endpoint, {method:'DELETE'}); changed = false; d.close(); }, false);
    if (b.hasAttribute('data-preview')) void run(async () => showPreview(state.steps, 'Preview lesson sequence'));
    if (b.hasAttribute('data-preview-step')) void run(async () => showPreview([state.steps.find(s=>s.id===step.id)], 'Preview clip'));
    if (b.hasAttribute('data-wording')) {
      const original=step.description;
      const p=document.createElement('dialog');p.className='guided-wording-helper';
      p.setAttribute('aria-label','Clarify faculty wording');
      p.innerHTML=`<h2>Clarify faculty wording</h2><p>Copy this prompt into your existing assistant. Compare its suggestion with the faculty instructions before accepting.</p><label>Prompt<textarea rows="7" readonly data-prompt>${e(facultyWordingPrompt(step))}</textarea></label><button type="button" data-copy>Copy prompt</button><p role="status" data-copy-status></p><label>Original faculty instructions<textarea rows="4" readonly>${e(original)}</textarea></label><label>Suggested wording<textarea rows="5" maxlength="5000" data-suggestion></textarea></label><button type="button" data-accept>Accept reviewed wording</button><button type="button" data-keep>Keep current wording</button>`;
      d.append(p);p.showModal();p.onclose=()=>p.remove();p.querySelector('[data-keep]').onclick=()=>p.close();
      p.querySelector('[data-copy]').onclick=async()=>{const text=p.querySelector('[data-prompt]');try{await navigator.clipboard.writeText(text.value);p.querySelector('[data-copy-status]').textContent='Prompt copied.';}catch{text.focus();text.select();p.querySelector('[data-copy-status]').textContent='Press Ctrl+C or use your device’s Copy command to copy the selected prompt.';}};
      p.querySelector('[data-accept]').onclick=()=>{try{state.steps=acceptFacultySuggestion(state.steps,step.id,p.querySelector('[data-suggestion]').value);changed=true;p.close();render();status('Reviewed wording accepted locally. Save the working draft to retain it.');}catch(error){p.querySelector('[data-copy-status]').textContent=error.message;}};
    }
  });
  d.addEventListener('submit', event => {
    event.preventDefault();const form=event.target;if(busy || !form.reportValidity())return;
    const body = new FormData(form);
    if (form.hasAttribute('data-upload')) {
      const stepId=form.closest('[data-step]').dataset.step;
      const file=body.get('asset');if(file.size>80*1048576){status('Choose a file no larger than 80 MiB.');return;}
      void run(async()=>{body.set('revision',state.revision);body.set('stepId',stepId);state=await request(`${endpoint}/assets`,{method:'POST',body});});
    }
    if (form.hasAttribute('data-commit')) void run(async()=>{const result=await request(`${endpoint}/commit`,{method:'POST',body:{revision:state.revision,version:body.get('version')}});saved=true;savedVersion=result.package.version;state.committedPackageId=result.package.id;});
  });
  d.addEventListener('cancel', event => {event.preventDefault();if(!busy)void run(async()=>d.close());});
  render();d.showModal();
}

export function setupGuidedClipEditor() {
  if(initialized)return;initialized=true;
  document.addEventListener('click',event=>{const button=event.target.closest('[data-manage-clips]');if(!button)return;button.disabled=true;void openEditor(button.dataset.manageClips).catch(error=>{const p=document.createElement('p');p.setAttribute('role','alert');p.textContent=error.message;button.parentElement.append(p);}).finally(()=>{button.disabled=false;});});
}
