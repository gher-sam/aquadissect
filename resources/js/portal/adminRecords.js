import { request } from './api.js';
import { escapeHtml as e } from './format.js';
import { createUuid } from '../uuid.js';
import '../../css/admin-records.css';

const mounted = new WeakSet();
const resources = { students: 'Student accounts', enrollments: 'Student enrollments', sections: 'Class sections', 'species-versions': 'Species lesson versions', 'safety-versions': 'Safety & Tools versions' };
const statusLabel = value => value === 'withdrawn' ? 'Archived draft' : value.charAt(0).toUpperCase() + value.slice(1);
const actionLabel = { archive: 'Archive', restore: 'Restore', delete: 'Permanently delete' };

export function mountRecordManagement(host, onChanged = () => {}) {
  if (!host || mounted.has(host)) return;
  mounted.add(host);
  const state = { resource: 'students', view: 'current', q: '', page: 1, perPage: 25, data: { rows: [], total: 0, lastPage: 1, audit: [] }, selected: new Set(), action: 'archive', reason: '', loading: false, error: '', success: '' };
  let sequence = 0;
  let review = null;
  let preparing = false;
  let committing = false;
  const selector = (name, values, value) => `<select name="${name}">${Object.entries(values).map(([id,label]) => `<option value="${id}" ${String(value) === id ? 'selected' : ''}>${e(label)}</option>`).join('')}</select>`;

  function render() {
    host.setAttribute('aria-busy', String(state.loading));
    host.innerHTML = `<div class="record-management">
      <div class="page-guidance"><p>Archive retains records and can be reversed. Permanent deletion is restricted to unused entries. Saved learning results, enrollments, published content and review history are protected.</p></div>
      ${state.error ? `<p class="record-error" role="alert">${e(state.error)}</p>` : ''}${state.success ? `<p class="record-success" role="status">${e(state.success)}</p>` : ''}
      <form data-record-filters class="record-filters">
        <label class="portal-field">Record type${selector('resource', resources, state.resource)}</label>
        <label class="portal-field">Show${selector('view', {current:'Current records',archived:'Archived records',all:'All records'}, state.view)}</label>
        <label class="portal-field record-search">Search<input name="q" type="search" maxlength="120" value="${e(state.q)}" placeholder="Name, student ID, section or version"></label>
        <button type="submit" class="portal-secondary">Search / refresh</button>
        <label class="portal-field">Rows${selector('perPage', {10:'10',25:'25',50:'50',100:'100'}, state.perPage)}</label>
      </form>
      <p class="record-selection-note">Select up to 100 records on this page. Changing the page or filters clears the selection.</p>
      <div class="roster-table-scroll record-table" tabindex="0" role="region" aria-label="${e(resources[state.resource])}">
        <table><caption>${e(resources[state.resource])}</caption><thead><tr><th><input type="checkbox" data-record-select-page aria-label="Select every record on this page" ${!state.data.rows.length ? 'disabled' : ''}></th><th scope="col">Record</th><th scope="col">Status</th></tr></thead>
        <tbody>${state.data.rows.map(row => `<tr><td><input type="checkbox" data-record-id="${row.id}" aria-label="Select ${e(row.label)}" ${state.selected.has(row.id) ? 'checked' : ''}></td><td><strong>${e(row.label)}</strong><small>Record ${row.id}</small></td><td>${e(statusLabel(row.status))}</td></tr>`).join('') || '<tr><td colspan="3">No records match these filters.</td></tr>'}</tbody></table>
      </div>
      <div class="record-pager"><span>${state.data.total} records · Page ${state.page} of ${state.data.lastPage}</span><div><button type="button" class="portal-secondary" data-record-page="previous" ${state.page <= 1 ? 'disabled' : ''}>Previous</button><button type="button" class="portal-secondary" data-record-page="next" ${state.page >= state.data.lastPage ? 'disabled' : ''}>Next</button></div></div>
      <form data-record-review class="workspace-panel record-actions">
        <strong data-record-count>0 records selected on this page</strong>
        <label class="portal-field">Action${selector('action', {archive:'Archive selected',restore:'Restore selected',delete:'Permanently delete unused records'}, state.action)}</label>
        <label class="portal-field record-reason">Reason<textarea name="reason" required minlength="10" maxlength="500" rows="2" placeholder="Explain the administrative reason. Keep passwords, codes and private personal details out of this field.">${e(state.reason)}</textarea></label>
        <button class="portal-primary" type="submit" data-record-review-button disabled>Review selection</button>
      </form>
      <section class="workspace-panel record-audit"><h2>Recent bulk actions</h2><p>Completed actions record the administrator, selected record IDs, reason and time.</p>
        ${state.data.audit.length ? `<div class="roster-table-scroll" tabindex="0" role="region" aria-label="Recent bulk action history"><table><thead><tr><th>Time</th><th>Action</th><th>Records</th><th>Administrator</th><th>Reason</th></tr></thead><tbody>${state.data.audit.map(a => `<tr><td>${e(new Date(a.createdAt).toLocaleString())}</td><td>${e(actionLabel[a.action])} · ${e(resources[a.resource])}</td><td>${a.count}</td><td>Account ${a.actorId}</td><td>${e(a.reason)}</td></tr>`).join('')}</tbody></table></div>` : '<p>No bulk actions have been completed.</p>'}
      </section>
    </div>`;
    host.querySelectorAll('input,select,textarea,button').forEach(control => { if (state.loading) control.disabled = true; });
    updateSelection();
  }

  function updateSelection() {
    const count = state.selected.size;
    const all = host.querySelector('[data-record-select-page]');
    all.checked = count > 0 && count === state.data.rows.length;
    all.indeterminate = count > 0 && count < state.data.rows.length;
    host.querySelector('[data-record-count]').textContent = `${count} record${count === 1 ? '' : 's'} selected on this page`;
    host.querySelector('[data-record-review-button]').disabled = !count || state.loading;
  }

  async function load() {
    const current = ++sequence;
    state.selected.clear(); state.loading = true; state.error = '';
    render();
    try {
      const params = new URLSearchParams({resource:state.resource,view:state.view,q:state.q,page:String(state.page),perPage:String(state.perPage)});
      const data = await request(`/api/admin/records?${params}`);
      if (current !== sequence) return;
      state.data = data;
      if (state.page > data.lastPage) { state.page = data.lastPage; return load(); }
    } catch (error) { if (current === sequence) state.error = error.message; }
    finally { if (current === sequence) { state.loading = false; render(); } }
  }

  function closeReview() {
    const dialog = host.querySelector('[data-record-dialog]');
    dialog?.close(); dialog?.remove(); review = null;
    host.querySelector('[data-record-review-button]')?.focus();
  }

  function showReview(data) {
    review = { ...data, requestUuid: createUuid() };
    const dialog = document.createElement('dialog');
    dialog.className = 'record-review-dialog'; dialog.dataset.recordDialog = '';
    dialog.setAttribute('aria-labelledby','recordReviewTitle');
    const countLabel = `${state.selected.size} record${state.selected.size === 1 ? '' : 's'}`;
    dialog.innerHTML = `<form data-record-commit>
      <h2 id="recordReviewTitle">Review: ${e(actionLabel[state.action])} ${countLabel}</h2>
      <p>${e(state.reason)}</p>
      ${state.action === 'delete' ? '<p>Removal is permanent in the application. Setup audit entries are retained in the bulk audit; uploaded source files remain private for recovery. Removed version numbers cannot be reused.</p>' : state.action === 'restore' ? '<p>Restoring lesson content does not publish or assign it. A new faculty decision is required.</p>' : '<p>Account or section access may stop. Existing learning records stay retained. Previously published safety work keeps its existing retention rules.</p>'}
      <ul class="record-review-list">${data.rows.map(row => `<li><strong>${e(row.label)}</strong><span>${e(statusLabel(row.status))} · ${row.allowed ? 'Ready' : 'Blocked'}</span><p>${e(row.message)}</p></li>`).join('')}</ul>
      ${!data.allowed ? '<p class="record-error" role="alert">The selection contains protected records. No records will change. Cancel, adjust the selection or choose Archive.</p>' : ''}
      ${state.action === 'delete' && data.allowed ? '<label class="portal-field">Type DELETE to confirm<input name="confirmation" required pattern="DELETE" autocomplete="off" spellcheck="false"></label>' : ''}
      <p class="record-error" data-record-commit-error role="alert" hidden></p>
      <div class="record-review-footer"><button type="button" class="portal-secondary" data-record-cancel>Cancel</button><button class="portal-primary" type="submit" ${!data.allowed ? 'disabled' : ''}>${e(actionLabel[state.action])} ${countLabel}</button></div>
    </form>`;
    dialog.addEventListener('cancel', event => { event.preventDefault(); if (!committing) closeReview(); });
    host.append(dialog); dialog.showModal();
  }

  host.addEventListener('change', event => {
    if (preparing || committing) return;
    if (event.target.matches('[data-record-select-page]')) {
      state.selected = event.target.checked ? new Set(state.data.rows.map(row => row.id)) : new Set();
      host.querySelectorAll('[data-record-id]').forEach(input => { input.checked = state.selected.has(Number(input.dataset.recordId)); });
      updateSelection();
    } else if (event.target.matches('[data-record-id]')) {
      const id = Number(event.target.dataset.recordId);
      if (event.target.checked) state.selected.add(id); else state.selected.delete(id);
      updateSelection();
    } else if (event.target.closest('[data-record-filters]') && event.target.tagName === 'SELECT') {
      const values = new FormData(event.target.form);
      const resourceChanged = state.resource !== values.get('resource');
      state.resource = values.get('resource'); state.view = values.get('view'); state.perPage = Number(values.get('perPage'));
      state.q = resourceChanged ? '' : String(values.get('q')); state.page = 1; state.success = '';
      void load();
    }
  });

  host.addEventListener('click', event => {
    if (preparing || committing) return;
    const page = event.target.closest('[data-record-page]');
    if (page && !page.disabled) { state.page += page.dataset.recordPage === 'next' ? 1 : -1; state.success = ''; void load(); }
    if (event.target.closest('[data-record-cancel]') && !committing) closeReview();
  });

  host.addEventListener('submit', async event => {
    event.preventDefault();
    if (preparing || committing) return;
    if (event.target.matches('[data-record-filters]')) {
      state.q = String(new FormData(event.target).get('q')); state.page = 1; state.success = ''; await load();
    } else if (event.target.matches('[data-record-review]') && state.selected.size && !state.loading) {
      const values = new FormData(event.target); state.action = values.get('action'); state.reason = String(values.get('reason')).trim();
      const button = event.target.querySelector('button'); button.disabled = true;
      preparing = true; host.inert = true; host.setAttribute('aria-busy','true');
      try {
        const data = await request('/api/admin/records/preview', {method:'POST',body:{resource:state.resource,action:state.action,ids:[...state.selected],reason:state.reason}});
        host.inert = false; showReview(data);
      } catch (error) { state.error = error.message; render(); }
      finally { preparing = false; host.inert = false; host.setAttribute('aria-busy','false'); if (button.isConnected) button.disabled = false; }
    } else if (event.target.matches('[data-record-commit]') && review?.allowed && !committing) {
      const form = event.target;
      const confirmation = state.action === 'delete' ? new FormData(form).get('confirmation') : 'CONFIRM';
      if (state.action === 'delete' && confirmation !== 'DELETE') return;
      committing = true; form.querySelectorAll('button').forEach(b => { b.disabled = true; });
      try {
        const result = await request('/api/admin/records/execute', {method:'POST',body:{token:review.token,requestUuid:review.requestUuid,confirmation}});
        closeReview(); state.success = `${({archive:'Archived',restore:'Restored',delete:'Deleted'})[result.action]} ${result.count} record${result.count === 1 ? '' : 's'}. Audit ${result.batchId} saved.`; state.reason = ''; state.loading = true; render();
        try { await onChanged(); } catch { state.success += ' Refresh the workspace if shared counts need updating.'; }
        await load();
      } catch (error) {
        const message = form.querySelector('[data-record-commit-error]'); message.hidden = false; message.textContent = `${error.message} You can retry this request, or cancel and review again.`;
      } finally { committing = false; if (form.isConnected) form.querySelectorAll('button').forEach(b => { b.disabled = false; }); }
    }
  });
  void load();
}
