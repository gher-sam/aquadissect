import { request } from './api.js';
import { escapeHtml as e } from './format.js';

const fields = { identityNumber: 'Student number', firstName: 'First name', lastName: 'Surname', email: 'Email (optional)', program: 'Program (optional)', yearLevel: 'Year level (optional)', homeSection: 'Home section (optional)' };
const aliases = { identityNumber: ['studentno', 'studentnumber', 'studentid', 'identitynumber', 'institutionalid'], firstName: ['firstname', 'givenname'], lastName: ['surname', 'lastname', 'familyname'], email: ['email', 'emailaddress', 'institutionalemail'], program: ['program', 'programme', 'course'], yearLevel: ['year', 'yearlevel'], homeSection: ['section', 'homesection'] };
let state = {};
let owner;

export function setupBulkRoster(root, classes, userId, completed) {
  const host = root.querySelector('#bulkRosterUpload');
  if (!host) return;
  if (owner !== userId) { state = {}; owner = userId; }
  if (state.draftId || state.message) host.closest('details').open = true;
  const offerings = classes.filter(c => c.status === 'active' && c.courseCode === 'MB-304L');
  const options = (values, selected, blank = 'All') => `<option value="">${blank}</option>${values.map(v => `<option ${String(v) === String(selected) ? 'selected' : ''} value="${e(v)}">${e(v)}</option>`).join('')}`;
  const destinations = selected => `<option value="">Choose a course section</option>${offerings.filter(c => String(c.id) === String(selected) || ((!state.year || c.academicYear === state.year) && (!state.term || c.semester === state.term))).map(c => `<option value="${c.id}" ${String(c.id) === String(selected) ? 'selected' : ''}>${e(c.academicYear)} · ${e(c.semester)} · ${e(c.sectionCode || c.name)}</option>`).join('')}`;
  const filtered = () => (state.rows || []).filter(r => (!state.search || `${r.identityNumber} ${r.firstName} ${r.lastName} ${r.email}`.toLowerCase().includes(state.search.toLowerCase())) && ['program', 'yearLevel', 'homeSection'].every(k => !state[k] || r[k] === state[k]));
  const invalidate = () => { state.review = null; };
  const render = (focus = false) => {
    const sheet = state.sheets?.[state.sheet || 0];
    const columns = sheet?.rows[(state.headerRow || 1) - 1] || [];
    host.innerHTML = `<div class="bulk-roster" aria-labelledby="bulkRosterTitle">
      <h2 id="bulkRosterTitle" tabindex="-1">Upload students</h2>
      <p>CSV, XLSX, XLS, XML, JSON or TSV · up to 5 MB and 1,000 students. Students may be from any year level, including irregular students. Nothing is added until you import.</p>
      <p data-bulk-message role="${state.error ? 'alert' : 'status'}">${e(state.error || state.message || '')}</p>
      ${!state.draftId ? `<form data-bulk-upload><label class="portal-field">Student records file<input name="roster" type="file" accept=".csv,.xlsx,.xls,.xml,.json,.tsv" required></label><p>For a PDF, image or another format, export a structured table first. Keep student numbers as text. Paste formula values before uploading.</p><div class="bulk-actions"><button class="portal-primary">Upload and inspect</button>${['csv', 'tsv', 'json', 'xml'].map(f => `<button class="portal-secondary" type="button" data-template="${f}">${f.toUpperCase()} template</button>`).join('')}</div></form>` : `
      <p>Private ${e(state.format.toUpperCase())} draft · expires ${e(new Date(state.expiresAt * 1000).toLocaleTimeString())}. Leaving this page keeps the draft only for this session; expired files are removed automatically.</p>
      <details ${!state.rows ? 'open' : ''}><summary>1. Map columns</summary><form data-bulk-map>
      <label class="portal-field">Worksheet / XML record element<select data-sheet>${state.sheets.map((s, i) => `<option value="${i}" ${i === (state.sheet || 0) ? 'selected' : ''}>${e(s.name)} (${s.rows.length - 1} rows)</option>`).join('')}</select></label>
      <label class="portal-field">Header row<input data-header type="number" min="1" max="100" value="${state.headerRow || 1}" ${sheet.hasHeader ? '' : 'disabled'}></label>
      <div class="bulk-table"><table><caption>File sample · first 6 rows</caption><tbody>${sheet.rows.slice(0, 6).map((r, i) => `<tr><th scope="row">${i + 1}</th>${r.map(v => `<td>${e(v)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>
      <div class="bulk-fields">${Object.entries(fields).map(([key, label]) => `<label class="portal-field">${label}<select data-map="${key}" ${['identityNumber', 'firstName', 'lastName'].includes(key) ? 'required' : ''}><option value="">${key === 'email' ? 'No email for now' : 'Not mapped'}</option>${columns.map((c, i) => `<option value="${i}" ${state.mapping?.[key] === i ? 'selected' : ''}>${i + 1}: ${e(c || '(unnamed)')}</option>`).join('')}</select></label>`).join('')}</div><button class="portal-secondary">Use these columns</button></form></details>
      ${state.rows ? `<section><h3>2. Select students and assign sections</h3><p>Home section is a filter only. Select students explicitly and choose their destination. Emails may stay blank. New accounts without email remain pending until an email is added for activation. Check suggested emails against existing accounts.</p>
      <div class="bulk-fields"><label class="portal-field">Search students<input data-search type="search" value="${e(state.search || '')}"></label>${['program', 'yearLevel', 'homeSection'].map(k => `<label class="portal-field">${fields[k]}<select data-filter="${k}">${options([...new Set(state.rows.map(r => r[k]).filter(Boolean))], state[k])}</select></label>`).join('')}
      <label class="portal-field">Academic year<select data-year>${options([...new Set(offerings.map(c => c.academicYear))], state.year, 'All years')}</select></label><label class="portal-field">Semester<select data-term>${options([...new Set(offerings.map(c => c.semester))], state.term, 'All semesters')}</select></label></div>
      <div class="bulk-actions"><button type="button" class="portal-secondary" data-select-filtered>Select filtered students</button><button type="button" class="portal-secondary" data-clear-selection>Clear selection</button><label class="portal-field">Destination for selected students<select data-group-destination>${destinations(state.groupDestination)}</select></label><button type="button" class="portal-secondary" data-assign>Assign selected students</button></div>
      <p role="status">${state.selected.size} selected · ${filtered().length} shown · ${state.rows.length} total</p>
      <div class="bulk-table"><table><caption>Student selection and optional emails</caption><thead><tr><th>Select</th><th>Student</th><th>Email</th><th>Upload context</th><th>Course section</th></tr></thead><tbody>${filtered().map(r => `<tr><td><input type="checkbox" data-select="${r.id}" aria-label="Select row ${r.id}, ${e(r.firstName)} ${e(r.lastName)}" ${state.selected.has(r.id) ? 'checked' : ''}></td><th scope="row">${e(r.firstName)} ${e(r.lastName)}<small>${e(r.identityNumber)} · row ${r.id}</small></th><td><input type="email" data-email="${r.id}" aria-label="Email for row ${r.id}" value="${e(r.email)}" maxlength="255">${r.emailSuggested ? '<small>Suggested from existing student number. Please check.</small>' : ''}</td><td>${e([r.program, r.yearLevel, r.homeSection].filter(Boolean).join(' · '))}</td><td><select data-destination="${r.id}" aria-label="Course section for row ${r.id}">${destinations(r.offeringId)}</select></td></tr>`).join('')}</tbody></table></div>
      <button type="button" class="portal-primary" data-review ${state.selected.size ? '' : 'disabled'}>Review selected students</button></section>` : ''}
      ${state.review ? `<section><h3 tabindex="-1" data-review-title>3. Review and import</h3><p>Selected: ${state.review.counts.selected} · New accounts: ${state.review.counts.new} · Existing accounts: ${state.review.counts.existing_student} · Already enrolled: ${state.review.counts.already_enrolled} · Errors: ${state.review.counts.errors}</p><ul>${state.review.destinations.map(d => `<li>${e(d.name)} · ${e(d.academicYear)} · semester ${e(d.semester)}: ${d.count} students</li>`).join('')}</ul><div class="bulk-table"><table><caption>Final reviewed selection</caption><thead><tr><th>Row</th><th>Student</th><th>Email</th><th>Destination</th><th>Result</th></tr></thead><tbody>${state.review.rows.map(r => `<tr><td>${r.id}</td><th scope="row">${e(r.firstName)} ${e(r.lastName)}<small>${e(r.identityNumber)}</small></th><td>${e(r.email)}</td><td>${e(state.review.destinations.find(d => d.id === r.offeringId)?.name || "No section")}<small>${e(state.review.destinations.find(d => d.id === r.offeringId)?.academicYear || "")}</small></td><td>${e(r.message)}</td></tr>`).join('')}</tbody></table></div><p>Existing account details and earlier results are retained. Already enrolled students are skipped. Accounts without email stay pending and receive no activation code. Deliver any new activation codes privately.</p><button type="button" class="portal-primary" data-commit ${state.review.counts.errors ? 'disabled' : ''}>Import selected students</button></section>` : ''}
      <button type="button" class="portal-secondary" data-cancel>Cancel upload and delete draft</button>`}
      </div>`;
    if (state.busy) host.querySelectorAll('button,input,select').forEach(el => { el.disabled = true; });
    if (focus) host.querySelector('[data-review-title], #bulkRosterTitle')?.focus();
  };
  const suggest = () => {
    const sheet = state.sheets[state.sheet || 0];
    const columns = sheet.rows[(state.headerRow || 1) - 1] || [];
    state.mapping = {};
    for (const key of Object.keys(fields)) {
      const i = columns.findIndex(c => aliases[key].includes(String(c).toLowerCase().replace(/[^a-z0-9]/g, '')));
      if (i >= 0) state.mapping[key] = i;
    }
  };
  const run = async action => {
    if (state.busy) return;
    state.busy = true; state.error = ''; render();
    try { await action(); } catch (err) { state.error = err.message; }
    finally { state.busy = false; render(true); }
  };
  host.onsubmit = event => {
    event.preventDefault();
    if (event.target.matches('[data-bulk-upload]')) {
      const body = new FormData(event.target);
      void run(async () => { state = { ...await request('/api/admin/roster/uploads', { method: 'POST', body }), busy: true }; suggest(); });
    } else if (event.target.matches('[data-bulk-map]')) {
      void run(async () => {
        const data = await request(`/api/admin/roster/uploads/${state.draftId}/map`, { method: 'POST', body: { revision: state.revision, sheet: state.sheet || 0, headerRow: state.headerRow || 1, mapping: state.mapping } });
        Object.assign(state, data, { selected: new Set(), review: null, search: '', program: '', yearLevel: '', homeSection: '' });
      });
    }
  };
  host.oninput = event => {
    const el = event.target;
    if (el.matches('[data-search]')) {
      state.search = el.value;
      render();
      host.querySelector('[data-search]').focus();
    }
    if (el.matches('[data-email]')) { state.rows.find(r => r.id === Number(el.dataset.email)).email = el.value; invalidate(); host.querySelector('[data-commit]')?.setAttribute('disabled', ''); }
  };
  host.onchange = event => {
    const el = event.target;
    if (el.matches('input[type="file"], [data-search], [data-email]')) return;
    if (el.matches('[data-sheet]')) { state.sheet = Number(el.value); state.headerRow = 1; suggest(); invalidate(); state.rows = null; }
    if (el.matches('[data-header]')) { state.headerRow = Number(el.value); suggest(); invalidate(); state.rows = null; }
    if (el.matches('[data-map]')) { state.mapping[el.dataset.map] = el.value === '' ? null : Number(el.value); invalidate(); state.rows = null; }
    if (el.matches('[data-filter]')) state[el.dataset.filter] = el.value;
    if (el.matches('[data-search]')) state.search = el.value;
    if (el.matches('[data-year]')) state.year = el.value;
    if (el.matches('[data-term]')) state.term = el.value;
    if (el.matches('[data-group-destination]')) state.groupDestination = el.value;
    if (el.matches('[data-select]')) { const id = Number(el.dataset.select); if (el.checked) state.selected.add(id); else state.selected.delete(id); invalidate(); }
    if (el.matches('[data-destination]')) { state.rows.find(r => r.id === Number(el.dataset.destination)).offeringId = Number(el.value); invalidate(); }
    render();
  };
  host.onclick = event => {
    const button = event.target.closest('button');
    if (!button || state.busy) return;
    if (button.dataset.template) {
      const f = button.dataset.template;
      const record = { StudentNo: '0001-DEMO', 'First Name': 'Sample', Surname: 'Learner', Email: 'sample.learner@example.test', Program: 'Marine Biology', 'Year Level': '2', 'Home Section': 'Irregular' };
      const content = f === 'json' ? JSON.stringify([record], null, 2) : f === 'xml' ? '<students><student><StudentNo>0001-DEMO</StudentNo><FirstName>Sample</FirstName><Surname>Learner</Surname><Email>sample.learner@example.test</Email><YearLevel>2</YearLevel></student></students>' : `${Object.keys(record).join(f === 'tsv' ? '\t' : ',')}\n${Object.values(record).join(f === 'tsv' ? '\t' : ',')}\n`;
      const url = URL.createObjectURL(new Blob([content], { type: 'application/octet-stream' }));
      const link = document.createElement('a'); link.href = url; link.download = `synthetic-students.${f}`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    if (button.hasAttribute('data-select-filtered')) { filtered().forEach(r => state.selected.add(r.id)); invalidate(); render(); }
    if (button.hasAttribute('data-clear-selection')) { state.selected.clear(); invalidate(); render(); }
    if (button.hasAttribute('data-assign')) { state.rows.filter(r => state.selected.has(r.id)).forEach(r => { r.offeringId = Number(state.groupDestination); }); invalidate(); render(); }
    if (button.hasAttribute('data-cancel')) void run(async () => { await request(`/api/admin/roster/uploads/${state.draftId}`, { method: 'DELETE' }); state = { message: 'Upload cancelled. No students were imported.' }; });
    if (button.hasAttribute('data-review')) void run(async () => {
      const selected = state.rows.filter(r => state.selected.has(r.id)).map(r => ({ id: r.id, email: r.email, offeringId: r.offeringId || 0 }));
      state.review = await request(`/api/admin/roster/uploads/${state.draftId}/review`, { method: 'POST', body: { revision: state.revision, selected } });
      state.revision = state.review.revision;
    });
    if (button.hasAttribute('data-commit')) void run(async () => {
      const result = await request(`/api/admin/roster/uploads/${state.draftId}/import`, { method: 'POST', body: { revision: state.revision } });
      state = { message: `Import complete. ${result.counts.new} accounts created; ${result.counts.existing_student} existing accounts enrolled; ${result.counts.already_enrolled} already enrolled students skipped.` };
      await completed(result);
    });
  };
  render();
}
