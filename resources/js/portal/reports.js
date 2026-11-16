import { Chart, BarController, LineController, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler } from 'chart.js';
import { request, appUrl } from './api.js';
import { escapeHtml as e } from './format.js';
import { signOutPending } from './sessionAccess.js';
import { reportChartDefinitions, overviewView, supportView, assessmentsView, recordsView, reportDate, reportColors } from './reportView.js';
import universityLogo from '../../reports/branding/university.png';
import '../../css/reports.css';

Chart.register(BarController, LineController, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);
const day = (value, offset = 0) => { const d = new Date(`${value}T12:00:00Z`); d.setUTCDate(d.getUTCDate() + offset); return d.toISOString().slice(0, 10); };
const today = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Manila' }).format(new Date());
const select = (name, label, options) => `<label>${label}<select name="${name}">${options.map(([value, title]) => `<option value="${e(value)}">${e(title)}</option>`).join('')}</select></label>`;

export async function mountReports(root, user, notify) {
  const learner = user.role === 'student';
  root.classList.add('analytics-workspace');
  root.innerHTML = `<section class="analytics-banner"><div class="analytics-brand"><img src="${universityLogo}" alt="Southern Leyte State University"><span>Bontoc Campus · MB 304L</span></div><div><p class="analytics-eyebrow">Learning analytics</p><h2>Recorded progress and next steps</h2><p>Review completion, explore results, and generate SLSU reports.</p></div><div class="analytics-banner-actions"><span class="analytics-scope">${learner ? 'Your own records' : 'Authorized sections'}</span><div><button class="portal-secondary" type="button" data-report-refresh>Refresh</button><button class="portal-primary" type="button" data-report-export disabled>Generate report</button></div></div></section>
    <section class="analytics-filter-card"><div class="analytics-filter-heading"><h2>Report filters</h2><div class="analytics-periods" role="group" aria-label="Report date shortcuts"><button type="button" data-period="7">Last 7 days</button><button type="button" data-period="30">Last 30 days</button><button type="button" data-report-reset>Reset</button></div></div>
    <form id="analyticsFilters"><div class="analytics-filter-grid">
    ${select('academicYear', 'Academic year', [['', 'All available years']])}${select('semester', 'Semester', [['', 'All semesters']])}${select('classId', 'Section', [['', 'All authorized sections']])}
    ${select('activity', 'Activity type', [['all', 'All report activities'], ['lessons', 'Species lessons'], ['safety', 'Safety modules'], ['assessments', 'Formal assessments'], ...(learner ? [['practice', 'Your practice XP']] : [])])}
    ${select('activityKey', 'Specific activity', [['', 'All matching activities']])}${select('offeringStatus', 'Section status', [['active', 'Active sections'], ['all', 'All retained sections'], ['archived', 'Archived sections']])}
    <label>From <span class="sr-only">Philippine date</span><input type="date" name="from" required></label><label>To <span class="sr-only">Philippine date</span><input type="date" name="to" max="${today()}" required></label>
    ${!learner ? select('horizon', 'Forecast horizon', [['14', 'Next 14 days'], ['7', 'Next 7 days'], ['30', 'Next 30 days']]) : '<input type="hidden" name="horizon" value="14">'}
    <label>Score review threshold (%)<input name="scoreThreshold" type="number" min="0" max="100" step="0.1" placeholder="Disabled until set"><small>For review only; does not change grades.</small></label></div>
    <input type="hidden" name="learnerId"><div class="analytics-filter-footer"><button class="portal-primary" type="submit">Apply filters</button><button class="portal-secondary" type="button" data-clear-learner hidden>Clear learner filter</button><p id="analyticsFeedback" role="status" aria-live="polite">Loading the report…</p></div></form></section>
    <div class="analytics-tabs" role="tablist" aria-label="Report views">${[['overview', 'Overview'], ['support', learner ? 'My next steps' : 'Learner support'], ['assessments', 'Assessments'], ['records', 'Records']].map(([key, label], i) => `<button id="report-tab-${key}" type="button" role="tab" aria-selected="${i === 0}" aria-controls="report-panel-${key}" tabindex="${i === 0 ? 0 : -1}" data-report-tab="${key}">${label}</button>`).join('')}</div>
    <div id="analyticsResults" aria-busy="true"><div class="analytics-loading" role="status">Preparing your report…</div></div>
    <dialog class="analytics-export-dialog" aria-labelledby="reportExportTitle"><header><div><p class="analytics-eyebrow">SLSU Bontoc Campus</p><h2 id="reportExportTitle">Generate learning report</h2></div><button class="analytics-close" type="button" data-export-close aria-label="Close report generation">×</button></header><form id="reportExportForm"><p>Downloads use your applied report filters and include all permitted matching records.</p><label>Report layout<select name="layout"><option value="summary">Summary · metrics, charts and forecast basis</option><option value="detailed">Detailed · summary plus learner records</option></select></label><label>File format<select name="format"><option value="pdf">PDF document (.pdf)</option><option value="xlsx">Excel workbook (.xlsx)</option><option value="docx">Word document (.docx)</option><option value="csv">CSV report (.csv)</option><option value="xml">XML report (.xml)</option></select></label><div class="analytics-export-preview"><img src="${universityLogo}" alt="SLSU letterhead"><strong>Bontoc Campus</strong><p data-export-scope></p><small>PDF, Word and Excel include visual letterhead. CSV includes branded text rows; XML includes structured report metadata.</small></div><p data-export-error role="alert" hidden></p><div class="analytics-export-actions"><button class="portal-primary" type="submit">Generate and download</button><button class="portal-secondary" type="button" data-export-close>Cancel</button></div></form></dialog>`;
  const form = root.querySelector('#analyticsFilters');
  const feedback = root.querySelector('#analyticsFeedback');
  const results = root.querySelector('#analyticsResults');
  const exportButton = root.querySelector('[data-report-export]');
  const dialog = root.querySelector('dialog');
  const exportForm = root.querySelector('#reportExportForm');
  const elements = name => form.elements.namedItem(name);
  let data = null, applied = null, sequence = 0, controller = null, recordController = null, exportController = null;
  let busy = false, dirty = false, tab = 'overview', recordPage = 1, recordSequence = 0, recordTimer = null;
  const charts = new Map();
  const current = () => root.isConnected && !signOutPending() && String(window.AQUADISSECT_BOOTSTRAP?.user?.id) === String(user.id);
  const icons = () => document.dispatchEvent(new CustomEvent('aquadissect-refresh-icons'));
  const destroyCharts = () => { charts.forEach(c => c.destroy()); charts.clear(); };
  const setDefaults = () => { form.reset(); elements('to').value = today(); elements('from').value = day(today(), -29); elements('horizon').value = '14'; elements('learnerId').value = ''; };
  setDefaults();
  const initial = new URLSearchParams(location.search);
  for (const [key, value] of initial) {
    const field = elements(key);
    if (!field) continue;
    if (field.tagName === 'SELECT' && ![...field.options].some(o => o.value === value)) field.add(new Option('Selected filter', value));
    field.value = value;
  }
  if (['overview', 'support', 'assessments', 'records'].includes(initial.get('tab'))) tab = initial.get('tab');

  function fillSelect(name, choices, first) {
    const field = elements(name); const value = field.value;
    field.innerHTML = `<option value="">${first}</option>${choices.map(([v, label]) => `<option value="${e(v)}">${e(label)}</option>`).join('')}`;
    field.value = choices.some(([v]) => String(v) === value) ? value : '';
  }
  function populate() {
    if (!data) return;
    fillSelect('academicYear', data.options.academicYears.map(v => [v, v]), 'All available years');
    fillSelect('semester', data.options.semesters.map(v => [v, v]), 'All semesters');
    const classes = data.options.classes.filter(c => (!elements('academicYear').value || c.academic_year === elements('academicYear').value) && (!elements('semester').value || c.semester === elements('semester').value));
    fillSelect('classId', classes.map(c => [c.id, `${c.name}${c.status === 'archived' ? ' · archived' : ''}`]), 'All authorized sections');
    const classIds = new Set(classes.map(c => String(c.id)));
    fillSelect('activityKey', data.options.activities.filter(a => (elements('activity').value === 'all' || a.kind === elements('activity').value)
      && (!elements('classId').value || a.classIds.some(id => String(id) === elements('classId').value))
      && a.classIds.some(id => classIds.has(String(id)))).map(a => [a.key, a.name]), 'All matching activities');
  }
  function showTab(key, focus = false) {
    tab = key;
    root.querySelectorAll('[data-report-tab]').forEach(b => { const selected = b.dataset.reportTab === key; b.setAttribute('aria-selected', String(selected)); b.tabIndex = selected ? 0 : -1; if (selected && focus) b.focus(); });
    root.querySelectorAll('[role="tabpanel"]').forEach(panel => { panel.hidden = panel.id !== `report-panel-${key}`; });
    charts.forEach(chart => chart.resize());
  }
  function render() {
    destroyCharts();
    const definitions = reportChartDefinitions(data);
    const scope = [data.filters.from + ' to ' + data.filters.to,
      data.options.classes.find(c => String(c.id) === String(data.filters.classId))?.name || data.filters.academicYear || 'All authorized sections',
      data.filters.semester ? 'Semester ' + data.filters.semester : '', data.filters.offeringStatus + ' sections',
      data.options.activities.find(a => a.key === data.filters.activityKey)?.name || (data.filters.activity === 'all' ? 'All activities' : data.filters.activity),
      data.filters.learnerId ? 'One selected learner' : ''].filter(Boolean).join(' · ');
    results.innerHTML = `<p class="analytics-status-basis"><strong>Applied selection:</strong> ${e(scope)} · Philippine time</p><section role="tabpanel" id="report-panel-overview" aria-labelledby="report-tab-overview">${overviewView(data, definitions)}</section><section role="tabpanel" id="report-panel-support" aria-labelledby="report-tab-support" hidden>${supportView(data)}</section><section role="tabpanel" id="report-panel-assessments" aria-labelledby="report-tab-assessments" hidden>${assessmentsView(data, definitions)}</section><section role="tabpanel" id="report-panel-records" aria-labelledby="report-tab-records" hidden><section class="analytics-card"><div class="analytics-card-title"><h2>Saved learning records</h2><span class="analytics-chart-label">${data.summary.recordCount} records</span></div><p class="analytics-card-description">Table search and sorting affect this view. Generated reports include all records matching the applied report filters. Unattributed history is labeled separately.</p><div class="analytics-record-controls"><label>Search this table<input type="search" id="reportRecordSearch" placeholder="Learner, ID, activity or status"></label><label>Sort<select id="reportRecordSort"><option value="date_desc">Newest first</option><option value="date_asc">Oldest first</option><option value="learner">Learner name</option></select></label><label>Rows<select id="reportRecordSize"><option>25</option><option>50</option><option>100</option></select></label></div><div id="reportRecordRows"></div></section></section>`;
    showTab(tab);
    for (const def of definitions) {
      const canvas = root.querySelector(`#report-chart-${def.key}`); if (!canvas || !def.labels.length) continue;
      const chart = new Chart(canvas, { type: def.type, data: { labels: def.labels, datasets: def.series.map((series, index) => ({
        label: series.name, data: series.values, backgroundColor: series.fill ? 'rgba(193,144,66,.14)' : series.color || reportColors[index % reportColors.length],
        borderColor: series.color || reportColors[index % reportColors.length], borderWidth: def.type === 'line' ? 2 : 0,
        borderDash: series.dashed ? [6, 4] : [], borderRadius: def.type === 'bar' ? 4 : 0,
        pointRadius: def.labels.length > 60 ? 0 : 2, pointHoverRadius: 4, fill: series.fill || false, tension: 0, spanGaps: false,
      })) }, options: { responsive: true, maintainAspectRatio: false, indexAxis: def.horizontal ? 'y' : 'x',
        animation: matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 180 },
        interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false }, tooltip: { enabled: true } },
        scales: { x: { beginAtZero: true, stacked: Boolean(def.horizontal), grid: { color: def.horizontal ? '#edf1f7' : 'transparent' }, ticks: { maxTicksLimit: 8, font: { family: 'Figtree', size: 11 } } }, y: { beginAtZero: true, stacked: Boolean(def.horizontal), grid: { color: def.horizontal ? 'transparent' : '#edf1f7' }, ticks: { precision: 0, font: { family: 'Figtree', size: 11 } } } },
        onClick: (_event, points) => { if (def.drillKeys && points.length) { elements('activityKey').value = def.drillKeys[points[0].index]; void load(true); } },
      } }); charts.set(def.key, chart);
    }
    icons();
  }

  async function records() {
    if (!current() || !applied) return;
    const target = root.querySelector('#reportRecordRows'); if (!target) return;
    const recordVersion = ++recordSequence; const version = sequence;
    recordController?.abort(); recordController = new AbortController();
    const params = new URLSearchParams(applied);
    params.set('page', recordPage); params.set('perPage', root.querySelector('#reportRecordSize').value);
    params.set('sort', root.querySelector('#reportRecordSort').value);
    const search = root.querySelector('#reportRecordSearch').value.trim(); if (search) params.set('search', search);
    target.setAttribute('aria-busy', 'true');
    try {
      const response = await request(`/api/reports/records?${params}`, { signal: recordController.signal });
      if (version !== sequence || recordVersion !== recordSequence || !current()) return;
      target.innerHTML = recordsView(response);
    } catch (error) {
      if (error.name !== 'AbortError' && version === sequence && recordVersion === recordSequence && current()) target.innerHTML = `<div class="analytics-empty" role="alert"><strong>Records could not be loaded</strong><p>${e(error.message)}</p><button type="button" class="portal-secondary" data-record-retry>Try again</button></div>`;
    } finally { if (recordVersion === recordSequence) target.setAttribute('aria-busy', 'false'); }
  }

  async function load(focus = false) {
    if (!current()) return;
    const version = ++sequence; controller?.abort(); recordController?.abort(); controller = new AbortController();
    busy = true; exportButton.disabled = true; results.setAttribute('aria-busy', 'true'); feedback.textContent = 'Updating your report…';
    const params = new URLSearchParams([...new FormData(form)].filter(([, value]) => value !== ''));
    try {
      const response = await request(`/api/reports/analytics?${params}`, { signal: controller.signal });
      if (version !== sequence || !current()) return;
      data = response; applied = params; dirty = false; recordPage = 1;
      populate(); render(); await records();
      if (version !== sequence || !current()) return;
      root.querySelector('[data-clear-learner]').hidden = !elements('learnerId').value;
      const url = new URL(location.href); url.search = params.toString(); history.replaceState(null, '', url);
      feedback.textContent = `${data.summary.recordCount} records · Updated ${reportDate(data.meta.generatedAt)} PHT`;
      if (focus) { const heading = results.querySelector(`#report-panel-${tab} h2`); heading?.setAttribute('tabindex', '-1'); heading?.focus({ preventScroll: true }); }
    } catch (error) {
      if (error.name === 'AbortError' || version !== sequence || !current()) return;
      dirty = true; feedback.textContent = `Report not updated. ${error.message} Apply filters to retry. Previously loaded results remain labeled with their earlier scope.`;
      notify(error.message, { tone: 'error', persistent: true });
      if (!data) results.innerHTML = '<div class="analytics-empty" role="alert"><strong>The report is unavailable</strong><p>Review the filters and select Apply filters to retry.</p></div>';
    } finally {
      if (version === sequence && current()) { busy = false; results.setAttribute('aria-busy', 'false'); exportButton.disabled = !data || dirty; }
    }
  }
  function markDirty() { dirty = true; exportButton.disabled = true; feedback.textContent = 'Filter changes are pending. Apply filters to update the report and downloads.'; }
  form.addEventListener('submit', event => { event.preventDefault(); void load(true); });
  form.addEventListener('input', markDirty);
  form.addEventListener('change', event => {
    if (['academicYear', 'semester', 'classId', 'activity'].includes(event.target.name)) {
      elements('activityKey').value = '';
      if (event.target.name === 'classId' && elements('classId').value && data?.options.classes.find(c => String(c.id) === elements('classId').value)?.status === 'archived') elements('offeringStatus').value = 'all';
      populate();
    }
    markDirty();
  });
  root.addEventListener('click', event => {
    const button = event.target.closest('button'); if (!button) return;
    if (button.dataset.reportTab) { showTab(button.dataset.reportTab); return; }
    if (button.hasAttribute('data-export-close')) { exportController?.abort(); dialog.close(); return; }
    if (button.hasAttribute('data-report-export') && data && !busy && !dirty) {
      root.querySelector('[data-export-scope]').textContent = `${data.filters.from} to ${data.filters.to} · ${data.summary.recordCount} records in this selection`;
      root.querySelector('[data-export-error]').hidden = true; dialog.showModal(); return;
    }
    if (button.dataset.chartToggle) {
      const [key, index] = button.dataset.chartToggle.split(':'); const chart = charts.get(key); if (!chart) return;
      const visible = !chart.isDatasetVisible(Number(index)); chart.setDatasetVisibility(Number(index), visible); chart.update(); button.setAttribute('aria-pressed', String(visible)); return;
    }
    if (button.dataset.recordPage) { recordPage = Number(button.dataset.recordPage); void records(); return; }
    if (button.hasAttribute('data-record-retry')) { void records(); return; }
    if (button.dataset.reportDrill) { elements('activityKey').value = button.dataset.reportDrill; showTab('records'); void load(true); return; }
    if (button.dataset.reviewLearner) {
      elements('learnerId').value = button.dataset.reviewLearner; elements('classId').value = button.dataset.reviewClass; elements('activityKey').value = button.dataset.reviewActivity; showTab('records'); void load(true); return;
    }
    if (button.hasAttribute('data-clear-learner')) { elements('learnerId').value = ''; void load(true); return; }
    if (button.hasAttribute('data-report-reset')) { setDefaults(); populate(); void load(true); return; }
    if (button.dataset.period) { elements('to').value = today(); elements('from').value = day(today(), 1 - Number(button.dataset.period)); void load(true); return; }
    if (button.hasAttribute('data-report-refresh')) void load();
  });
  root.querySelector('[role="tablist"]').addEventListener('keydown', event => {
    if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault(); const tabs = [...root.querySelectorAll('[data-report-tab]')];
    const index = tabs.findIndex(b => b.dataset.reportTab === tab);
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    showTab(tabs[next].dataset.reportTab, true);
  });
  root.addEventListener('input', event => { if (event.target.id === 'reportRecordSearch') { clearTimeout(recordTimer); recordPage = 1; recordTimer = setTimeout(() => void records(), 250); } });
  root.addEventListener('change', event => { if (['reportRecordSort', 'reportRecordSize'].includes(event.target.id)) { recordPage = 1; void records(); } });

  exportForm.addEventListener('submit', async event => {
    event.preventDefault(); if (!current() || !applied || exportController || busy || dirty) return;
    const errorNode = root.querySelector('[data-export-error]'); const button = exportForm.querySelector('[type="submit"]');
    const params = new URLSearchParams(applied); params.set('format', exportForm.elements.format.value); params.set('layout', exportForm.elements.layout.value);
    exportController = new AbortController(); button.disabled = true; button.textContent = 'Generating report…'; errorNode.hidden = true;
    try {
      const response = await fetch(appUrl(`/api/reports/export?${params}`), { credentials: 'same-origin', cache: 'no-store', signal: exportController.signal, headers: { Accept: 'application/octet-stream' } });
      if (!response.ok) {
        const failure = await response.json().catch(() => ({}));
        if ([401, 419].includes(response.status)) { window.dispatchEvent(new CustomEvent('aquadissect-authorization-lost')); window.location.assign(appUrl('/login')); }
        if (failure.passwordChangeRequired) window.location.assign(failure.passwordChangeUrl || appUrl('/change-password'));
        throw new Error(Object.values(failure.errors || {}).flat()[0] || failure.message || 'The report could not be generated. Please retry.');
      }
      const blob = await response.blob(); if (!current()) return;
      const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url;
      link.download = `AquaDissect-${exportForm.elements.layout.value}-${params.get('to')}.${exportForm.elements.format.value}`;
      document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 10000);
      dialog.close(); notify('Your report is ready.');
    } catch (error) { if (error.name !== 'AbortError' && current()) { errorNode.textContent = error.message; errorNode.hidden = false; } }
    finally { exportController = null; button.disabled = false; button.textContent = 'Generate and download'; }
  });
  dialog.addEventListener('cancel', () => exportController?.abort());
  function clearPrivate() {
    sequence++; recordSequence++; controller?.abort(); recordController?.abort(); exportController?.abort(); clearTimeout(recordTimer); destroyCharts();
    data = null; applied = null; if (dialog.open) dialog.close(); results.replaceChildren(); feedback.textContent = ''; exportButton.disabled = true;
    for (const name of ['academicYear', 'semester', 'classId', 'activityKey']) elements(name).replaceChildren();
    elements('learnerId').value = ''; root.querySelector('[data-export-scope]').textContent = '';
  }
  const onSignout = event => { if (event.target.closest?.('[data-portal-signout]')) clearPrivate(); };
  document.addEventListener('click', onSignout, true);
  window.addEventListener('aquadissect-authorization-lost', clearPrivate);
  window.addEventListener('pagehide', clearPrivate, { once: true });
  window.addEventListener('pageshow', event => { if (event.persisted && current()) window.location.reload(); });
  window.addEventListener('storage', event => { if (event.key === 'aquadissect.sign-out-pending.v1' && event.newValue === '1') clearPrivate(); });
  await load();
}
