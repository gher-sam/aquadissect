import { request, appUrl } from './api.js';
import { sourceHandover } from './sourceHandover.js';
import { escapeHtml as e } from './format.js';
import { rosterTable, setupRosterTables } from './rosterTables.js';
import { setupNavigationGroups } from './interface.js';

const dateText = (value) => value ? new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const localDate = (value) => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(value));
const localInput = (value) => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date(value)).replace(' ', 'T');
const day = (date, amount = 0) => { const result = new Date(`${date}T12:00:00Z`); result.setUTCDate(result.getUTCDate() + amount); return result.toISOString().slice(0, 10); };

export function setupWorkspaceShell(portal, user) {
  setupNavigationGroups(portal);
  const search = portal.querySelector('#workspaceSearch');
  const results = portal.querySelector('#workspaceSearchResults');
  const help = [
    ['Activate a student account', 'activate'], ['Instructor access request', 'register'],
    ['Account roles and permissions', 'access'], ['How to explore anatomy and save results', 'about'],
  ];
  const pages = [...portal.querySelectorAll('.portal-nav [data-portal-page]')].filter(el => !el.hidden)
    .map(el => [el.textContent.trim(), el.dataset.portalPage]);
  search.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    results.hidden = !query;
    search.setAttribute('aria-expanded', String(Boolean(query)));
    if (!query) return;
    const guides = (window.AQUADISSECT_BOOTSTRAP?.tutorials?.guides || []).map(g => [g.title, 'about', g.id]);
    const matches = [...pages, ...help, ...guides].filter(([label]) => label.toLowerCase().includes(query)).slice(0, 8);
    results.innerHTML = matches.map(([label, key, guide]) => `<a href="${e(window.AQUADISSECT_ROUTES[key])}${guide ? `?guide=${encodeURIComponent(guide)}` : ''}">${e(label)}</a>`).join('') || '<p>No matching pages or help topics.</p>';
  });
  const closeSearch = () => { results.hidden = true; search.setAttribute('aria-expanded', 'false'); };
  portal.querySelector('.workspace-search').addEventListener('keydown', event => {
    const links = [...results.querySelectorAll('a')];
    if (event.key === 'Escape') { closeSearch(); search.focus(); }
    if (!results.hidden && ['ArrowDown','ArrowUp'].includes(event.key) && links.length) {
      event.preventDefault(); const index = links.indexOf(document.activeElement);
      const direction = event.key === 'ArrowDown' ? 1 : -1;
      const nextIndex = index < 0 ? (direction === 1 ? 0 : links.length - 1) : (index + direction + links.length) % links.length;
      links[nextIndex].focus();
    }
    if (event.key === 'Enter' && event.target === search && !results.hidden && links.length) { event.preventDefault(); links[0].click(); }
  });
  document.addEventListener('click', event => { if (!event.target.closest('.workspace-search')) closeSearch(); });
  const badge = portal.querySelector('#notificationCount');
  const refreshCount = async () => {
    if (!user || !navigator.onLine) return;
    try {
      const data = await request('/api/notifications'); badge.textContent = data.unread > 99 ? '99+' : String(data.unread); badge.hidden = !data.unread;
      const recent = portal.querySelector('#dashboardRecent');
      if (recent) recent.innerHTML = `<div class="workspace-toolbar"><h2>Recent updates</h2><a href="${e(window.AQUADISSECT_ROUTES.notifications)}">Open inbox</a></div><ul class="notification-list">${data.notifications.data.slice(0,5).map(n=>`<li><div><a href="${e(n.url)}">${e(n.summary)}</a><small>${e(dateText(n.createdAt))}</small></div></li>`).join('') || '<li>No new updates yet.</li>'}</ul><div class="workspace-toolbar"><a href="${e(window.AQUADISSECT_ROUTES.calendar)}">View class calendar</a><a href="${e(window.AQUADISSECT_ROUTES.reports)}">View learning reports</a></div>`;
    }
    catch { badge.hidden = true; const recent=portal.querySelector('#dashboardRecent'); if(recent) recent.innerHTML='<h2>Recent updates</h2><p>Updates are unavailable right now. Reopen this page after reconnecting.</p>'; }
  };
  void refreshCount();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) void refreshCount(); });
  return refreshCount;
}

export async function setupWorkspacePage(page, root, user, notify, refreshCount) {
  if (!root) return;
  root.setAttribute('aria-busy', 'true');
  root.innerHTML = '<div class="workspace-loading" role="status"><span class="ui-skeleton"></span><span class="ui-skeleton"></span><span class="ui-skeleton"></span><p>Loading your workspace…</p></div>';
  const fail = error => { root.innerHTML = `<div class="empty-state" role="alert"><strong>Unable to load this page</strong><p>${e(error.message)}</p><button type="button" data-retry-workspace>Try again</button></div>`; root.querySelector('button').onclick = () => setupWorkspacePage(page, root, user, notify, refreshCount); };
  try {
    if (page === 'source-handover') await sourceHandover(root);
    if (page === 'notifications') await notifications(root, refreshCount, notify);
    if (page === 'calendar') await calendar(root, user, notify, refreshCount);
    if (page === 'reports') await reports(root, notify);
  } catch (error) { fail(error); }
  finally { root.setAttribute('aria-busy', 'false'); }
}

async function notifications(root, refreshCount, notify) {
  let page = 1;
  const render = async () => {
    const data = await request(`/api/notifications?page=${page}`);
    root.innerHTML = `<section class="workspace-panel"><div class="workspace-toolbar"><h2>Inbox <small>${data.unread} unread</small></h2><button type="button" data-read-all ${data.unread ? '' : 'disabled'}>Mark all read</button></div><ul class="notification-list">${data.notifications.data.map(n => `<li class="${n.read ? '' : 'is-unread'}"><div><a href="${e(n.url)}">${e(n.summary)}</a><small>${e(dateText(n.createdAt))}</small></div>${n.read ? '<span>Read</span>' : `<button type="button" data-read="${n.id}">Mark read</button>`}</li>`).join('') || '<li>No notifications yet. New updates will appear here.</li>'}</ul><div class="workspace-toolbar"><button type="button" data-notification-prev ${page === 1 ? 'disabled' : ''}>Previous</button><span>Page ${page} of ${data.notifications.last_page}</span><button type="button" data-notification-next ${page >= data.notifications.last_page ? 'disabled' : ''}>Next</button></div></section>`;
  };
  root.onclick = async event => {
    const button = event.target.closest('button'); if (!button) return;
    button.disabled = true;
    try {
      if (button.hasAttribute('data-read-all')) await request('/api/notifications/read-all', { method: 'POST' });
      else if (button.dataset.read) await request(`/api/notifications/${button.dataset.read}/read`, { method: 'POST' });
      else if (button.hasAttribute('data-notification-prev')) page--;
      else if (button.hasAttribute('data-notification-next')) page++;
      await render();
      const heading = root.querySelector('h2'); heading.tabIndex = -1; heading.focus({preventScroll:true});
      await refreshCount();
    } catch (error) { notify(error.message, { tone: 'error', persistent: true }); button.disabled = false; }
  };
  await render();
}

async function calendar(root, user, notify, refreshCount) {
  let mode = matchMedia('(max-width: 700px)').matches ? 'list' : 'month';
  let selected = localDate(new Date());
  let data;
  let sectionFilter = '';
  let renderSequence = 0;
  const canManage = user && ['admin', 'instructor'].includes(user.role);
  const render = async () => {
    const sequence = ++renderSequence;
    const startOfMonth = `${selected.slice(0, 7)}-01`;
    let start = mode === 'month' ? day(startOfMonth, -new Date(`${startOfMonth}T12:00:00Z`).getUTCDay()) : mode === 'week' ? day(selected, -new Date(`${selected}T12:00:00Z`).getUTCDay()) : selected;
    const count = mode === 'month' ? 42 : mode === 'week' ? 7 : mode === 'day' ? 1 : 30;
    const end = day(start, count);
    const response = await request(`/api/calendar?from=${encodeURIComponent(start+'T00:00:00+08:00')}&to=${encodeURIComponent(end+'T00:00:00+08:00')}`);
    if (sequence !== renderSequence) return;
    data = response;
    const options = data.classes.map(c => `<option value="${c.id}" ${String(c.id) === sectionFilter ? 'selected' : ''}>${e(c.name)}</option>`).join('');
    const events = data.events.filter(event => !sectionFilter || String(event.classId) === sectionFilter).sort((a,b) => a.startsAt.localeCompare(b.startsAt));
    const eventHtml = event => `<button type="button" class="calendar-event ${event.cancelled ? 'is-cancelled' : ''}" data-calendar-event="${e(event.id)}"><strong>${e(event.title)}</strong><small>${e(dateText(event.startsAt))}${event.cancelled ? ' · Cancelled' : ''}</small></button>`;
    const days = Array.from({ length: count }, (_, index) => day(start, index));
    root.innerHTML = `<section class="workspace-panel"><div class="workspace-toolbar"><div><button type="button" data-calendar-shift="-1" aria-label="Previous period">Previous</button><button type="button" data-calendar-today>Today</button><button type="button" data-calendar-shift="1" aria-label="Next period">Next</button></div><h2>${e(mode === 'month' ? new Intl.DateTimeFormat('en-PH',{month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(selected+'T12:00:00Z')) : `${start} — ${day(end,-1)}`)}</h2>${canManage ? '<button type="button" class="portal-primary" data-calendar-new>New event</button>' : ''}</div><div class="workspace-toolbar"><label>Section <select data-calendar-section><option value="">All my sections</option>${options}</select></label><div class="workspace-view-switch">${['month','week','day','list'].map(value => `<button type="button" data-calendar-mode="${value}" aria-pressed="${value===mode}">${value[0].toUpperCase()+value.slice(1)}</button>`).join('')}</div></div>
      ${mode === 'list' ? `<div class="calendar-list">${events.map(eventHtml).join('') || '<p>No scheduled activities in this period.</p>'}</div>` : `<div class="calendar-scroll"><div class="calendar-grid calendar-${mode}">${days.map(date => `<section class="calendar-day ${date === localDate(new Date()) ? 'is-today' : ''}"><h3>${e(new Intl.DateTimeFormat('en-PH',{weekday:'short',month:'short',day:'numeric',timeZone:'UTC'}).format(new Date(date+'T12:00:00Z')))}</h3>${events.filter(event => event.kind === 'event' ? new Date(event.startsAt) < new Date(day(date,1)+'T00:00:00+08:00') && new Date(event.endsAt) > new Date(date+'T00:00:00+08:00') : localDate(event.startsAt) === date).map(eventHtml).join('') || '<span class="calendar-empty">—</span>'}</section>`).join('')}</div></div>`}</section><dialog id="calendarDialog" class="workspace-dialog" aria-label="Class event"></dialog>`;
  };
  const showEvent = event => {
    const dialog = root.querySelector('#calendarDialog');
    const opener = document.activeElement;
    dialog.addEventListener('close', () => { if (opener?.isConnected) opener.focus({preventScroll:true}); }, {once:true});
    // Close only a backdrop click; clicks within the event form stay inside the dialog.
    dialog.onclick = click => { const box = dialog.getBoundingClientRect(); if (click.target === dialog && (click.clientX < box.left || click.clientX > box.right || click.clientY < box.top || click.clientY > box.bottom)) dialog.close(); };
    const editable = canManage && (!event || (event.kind === 'event' && !event.cancelled && data.classes.find(c=>c.id===event.classId)?.status === 'active'));
    const sectionOptions = data.classes.filter(c=>c.status==='active').map(c=>`<option value="${c.id}" ${c.id === event?.classId ? 'selected' : ''}>${e(c.name)}</option>`).join('');
    dialog.innerHTML = `<div class="workspace-toolbar"><h2>${event ? 'Event details' : 'New class event'}</h2><button type="button" data-dialog-close aria-label="Close event dialog">Close</button></div>${editable ? `<form id="calendarEventForm"><label>Section<select name="class_id" ${event ? 'disabled' : ''} required>${sectionOptions}</select></label><label>Title<input name="title" maxlength="160" required value="${e(event?.title || '')}"></label><label>Location<input name="location" maxlength="255" value="${e(event?.location || '')}"></label><label>Starts (Philippine time)<input name="starts_at" type="datetime-local" required value="${event ? localInput(event.startsAt) : selected+'T09:00'}"></label><label>Ends (Philippine time)<input name="ends_at" type="datetime-local" required value="${event ? localInput(event.endsAt) : selected+'T10:00'}"></label><label>Description<textarea name="description" maxlength="3000">${e(event?.description || '')}</textarea></label><p data-event-error role="alert"></p><button type="submit" class="portal-primary" ${sectionOptions ? '' : 'disabled'}>Save event</button>${event ? '<button type="button" data-cancel-event>Cancel event</button>' : ''}${sectionOptions ? '' : '<p>Create or obtain an active section assignment first.</p>'}</form>` : `<h3>${e(event.title)}</h3><p>${e(dateText(event.startsAt))} — ${e(dateText(event.endsAt))}</p><p>${e(event.location || '')}</p><p>${e(event.description || '')}</p><p>${event.cancelled ? 'This event was cancelled.' : e(data.classes.find(c=>c.id===event.classId)?.name || '')}</p>${event.kind !== 'event' ? `<a href="${e(window.AQUADISSECT_ROUTES[event.kind==='safety'?'safety':'classes'])}">Open learning activity</a>` : ''}`}`;
    dialog.querySelector('[data-dialog-close]').onclick = () => dialog.close();
    dialog.querySelector('[data-cancel-event]')?.addEventListener('click', async () => {
      const cancelButton = dialog.querySelector('[data-cancel-event]');
      if (!cancelButton.dataset.confirmed) { cancelButton.dataset.confirmed = 'true'; cancelButton.textContent = 'Confirm cancellation'; dialog.querySelector('[data-event-error]').textContent = 'The event will remain visible as cancelled. Select Confirm cancellation to continue.'; return; }
      cancelButton.disabled = true;
      try { await request(`/api/calendar/events/${event.id}/cancel`, { method: 'POST' }); dialog.close(); await render(); root.querySelector('[data-calendar-new]')?.focus(); await refreshCount(); notify('Event cancelled.'); }
      catch(error) { dialog.querySelector('[data-event-error]').textContent=error.message; cancelButton.disabled = false; }
    });
    dialog.querySelector('form')?.addEventListener('submit', async submit => {
      submit.preventDefault();
      const button = submit.target.querySelector('[type="submit"]'); button.disabled=true;
      const values = Object.fromEntries(new FormData(submit.target));
      values.class_id = event?.classId || Number(values.class_id);
      values.starts_at += ':00+08:00'; values.ends_at += ':00+08:00';
      try { await request(event ? `/api/calendar/events/${event.id}` : '/api/calendar/events', { method:event?'PATCH':'POST', body:values }); dialog.close(); await render(); root.querySelector('[data-calendar-new]')?.focus(); await refreshCount(); notify('Event saved.'); }
      catch(error) { dialog.querySelector('[data-event-error]').textContent=error.message; button.disabled=false; }
    });
    dialog.showModal();
  };
  root.onclick = async event => {
    const target=event.target.closest('button'); if(!target || target.closest('dialog')) return;
    try {
      if (target.hasAttribute('data-calendar-new')) return showEvent(null);
      if (target.dataset.calendarEvent) return showEvent(data.events.find(item=>String(item.id)===target.dataset.calendarEvent));
      if (target.dataset.calendarMode) mode=target.dataset.calendarMode;
      else if(target.hasAttribute('data-calendar-today')) selected=localDate(new Date());
      else if(target.dataset.calendarShift) {
        const amount=Number(target.dataset.calendarShift);
        if(mode==='month') { const d=new Date(selected.slice(0,7)+'-01T12:00:00Z'); d.setUTCMonth(d.getUTCMonth()+amount); selected=d.toISOString().slice(0,10); }
        else selected=day(selected,amount*(mode==='week'?7:mode==='list'?30:1));
      } else return;
      await render();
    } catch(error) { notify(error.message,{tone:'error',persistent:true}); }
  };
  root.onchange = async event => { if(event.target.matches('[data-calendar-section]')) { sectionFilter=event.target.value; try{await render();}catch(error){notify(error.message,{tone:'error',persistent:true});} } };
  await render();
}

async function reports(root, notify) {
  root.innerHTML=`<section class="workspace-panel"><div class="workspace-toolbar report-presets"><h2>Learning report</h2><div role="group" aria-label="Report date shortcuts"><button type="button" data-report-period="7">Last 7 days</button><button type="button" data-report-period="30">Last 30 days</button><button type="button" data-report-reset>Reset filters</button></div></div><form id="reportFilters" class="workspace-toolbar"><label>Section<select name="classId"><option value="">All available records</option></select></label><label>From<input type="date" name="from"></label><label>To<input type="date" name="to"></label><label>Activity<select name="activity"><option value="all">All activities</option><option value="lessons">Species lessons</option><option value="safety">Safety modules</option></select></label><label>Recorded activity<select name="recordedActivity"><option value="">All recorded activities</option></select></label><button type="submit" class="portal-primary">Apply filters</button><a id="reportExport" aria-disabled="true">Download CSV</a></form><p id="reportFeedback" role="status" aria-live="polite"></p></section><div id="reportResults"></div>`;
  const form=root.querySelector('form');
  let renderSequence = 0;
  let hadFailure = false;
  const feedback = root.querySelector('#reportFeedback');
  const exportLink = root.querySelector('#reportExport');
  exportLink.addEventListener('click', event => { if (exportLink.getAttribute('aria-disabled') === 'true') event.preventDefault(); });
  const render = async () => {
    const sequence = ++renderSequence;
    const params=new URLSearchParams([...new FormData(form)].filter(([,value])=>value));
    const results=root.querySelector('#reportResults');
    results.setAttribute('aria-busy','true');
    exportLink.removeAttribute('href'); exportLink.setAttribute('aria-disabled','true');
    feedback.textContent='Updating report…';
    let data;
    try { data=await request('/api/reports?'+params); }
    catch (error) { if (sequence !== renderSequence) return; hadFailure = true; results.setAttribute('aria-busy','false'); feedback.textContent='Report could not be updated. Select Apply filters to retry. The previous results are still shown.'; throw error; }
    if (sequence !== renderSequence) return;
    if(form.elements.classId.options.length===1) data.classes.forEach(c=>{const option=document.createElement('option');option.value=c.id;option.textContent=c.name;form.elements.classId.append(option);});
    const recorded = form.elements.recordedActivity.value;
    form.elements.recordedActivity.innerHTML='<option value="">All recorded activities</option>';
    [...new Set([...data.activities, ...(recorded ? [recorded] : [])])].forEach(value=>{ const option=document.createElement('option'); option.value=value; option.textContent=value; form.elements.recordedActivity.append(option); });
    form.elements.recordedActivity.value=recorded;
    exportLink.href=appUrl('/api/reports?'+params+'&format=csv'); exportLink.setAttribute('aria-disabled','false');
    const records=data.rows.filter(r=>r.attributed);
    const lessons=records.filter(r=>r.kind==='lessons');
    const scored=lessons.filter(r=>r.score!==null && r.total!==null && Number.isFinite(Number(r.score)) && Number(r.total)>0);
    const earned=scored.reduce((sum,r)=>sum+Number(r.score),0), available=scored.reduce((sum,r)=>sum+Number(r.total),0);
    const percentage=available?Math.round(100*earned/available):null;
    const activityCounts = [...new Set(records.map(r=>r.activity))].map(activity=>({activity,count:records.filter(r=>r.activity===activity).length}));
    const largest = Math.max(1,...activityCounts.map(item=>item.count));
    results.innerHTML=`<section class="workspace-panel report-summary"><div><h2>Attributed records</h2><p>${lessons.length} lesson attempts · ${records.filter(r=>r.kind==='safety'&&r.status==='Completed').length} completed safety progress records</p><p>${data.rows.length-records.length} unattributed historical records shown separately below.</p></div><div>${percentage===null?'<p class="report-no-score">Aggregate formative score: <strong>Not recorded</strong></p><small>No scored lesson attempts in these results.</small>':`<label for="reportScore">Aggregate formative score: ${percentage}%</label><meter id="reportScore" min="0" max="100" value="${percentage}">${percentage}%</meter><small>${earned} earned points / ${available} available points</small>`}</div></section><section class="workspace-panel"><h2>Records by activity</h2><p>Counts in this filtered report. Select an activity to see its records. These are records, not unique learners or evidence of learning effectiveness.</p><ul class="report-activity-bars">${activityCounts.map(({activity,count})=>`<li><button type="button" data-report-activity="${e(activity)}">${e(activity)}</button><meter min="0" max="${largest}" value="${count}" aria-label="${e(activity)}: ${count} records">${count}</meter><strong>${count} <span class="sr-only">records</span></strong></li>`).join('')||'<li>No attributed records match these filters.</li>'}</ul></section>`+[true,false].map(attributed => rosterTable(attributed?'reports':'historical', attributed?'Section learning records':'Unattributed historical records',['Learner','Section','Activity','Result','Status','Recorded'],data.rows.filter(r=>r.attributed===attributed).map(r=>`<tr data-table-row><td>${e(r.learner)}</td><td>${e(r.section)}</td><td>${e(r.activity)}</td><td>${r.total===null?'—':`${r.score} / ${r.total}`}</td><td>${e(r.status)}</td><td data-sort-value="${e(r.date||'')}">${e(dateText(r.date))}</td></tr>`).join(''))).join('');
    setupRosterTables(results);
    results.setAttribute('aria-busy','false'); feedback.textContent=`${data.rows.length} records loaded. Filters also apply to the CSV download.`;
    if (hadFailure) { hadFailure = false; notify('Report updated.'); }
  };
  root.onclick = async event => {
    const button=event.target.closest('[data-report-period],[data-report-reset],[data-report-activity]'); if(!button) return;
    if(button.hasAttribute('data-report-reset')) form.reset();
    if(button.dataset.reportPeriod) { form.elements.to.value=localDate(new Date()); form.elements.from.value=day(form.elements.to.value,1-Number(button.dataset.reportPeriod)); }
    if(button.dataset.reportActivity) form.elements.recordedActivity.value=button.dataset.reportActivity;
    try { await render(); if(button.dataset.reportActivity) { const heading=root.querySelector('#reportResults h2'); heading.tabIndex=-1; heading.focus({preventScroll:true}); } } catch(error) { notify(error.message,{tone:'error',persistent:true}); }
  };
  form.onsubmit=async event=>{event.preventDefault();try{await render();}catch(error){notify(error.message,{tone:'error',persistent:true});}};
  await render();
}
