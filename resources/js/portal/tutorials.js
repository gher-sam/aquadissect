import { request } from './api.js';
import { escapeHtml as e } from './format.js';
import { BookOpen, Compass, ShieldCheck, Users, ChartNoAxesCombined, ClipboardCheck, createElement } from 'lucide';

const tutorialStyles = {
  class: ['cyan', Users], sections: ['cyan', Users], students: ['cyan', Users],
  safety: ['amber', ShieldCheck], exceptions: ['amber', ShieldCheck], approve: ['amber', ShieldCheck],
  results: ['violet', ChartNoAxesCombined], progress: ['violet', ChartNoAxesCombined], reports: ['violet', ChartNoAxesCombined], overview: ['violet', ChartNoAxesCombined],
  review: ['rose', ClipboardCheck], publish: ['rose', ClipboardCheck], content: ['rose', ClipboardCheck],
};
const styleFor = guide => tutorialStyles[guide.id.split('-').at(-1)] || ['blue', BookOpen];
const icon = node => createElement(node, { 'aria-hidden': 'true', focusable: 'false', width: 24, height: 24 }).outerHTML;
const progressBar = (value, total, label) => `<progress class="tutorial-progress" max="${total}" value="${value}" aria-label="${e(label)}">${value} of ${total}</progress>`;

const helpUrl = () => window.AQUADISSECT_ROUTES.about;
const progressFor = (data, guide) => data?.progress?.find(p => p.guide_key === guide.id && Number(p.version) === guide.version);
const guideUrl = guide => `${helpUrl()}?guide=${encodeURIComponent(guide.id)}`;

export function startHereCard(data) {
  if (!data?.guides?.length) return '';
  const next = data.guides.find(g => !progressFor(data, g)?.viewed_at) || data.guides[0];
  const viewed = data.guides.filter(g => progressFor(data, g)?.viewed_at).length;
  return `<section class="workspace-panel tutorial-start" aria-labelledby="startHereTitle"><div><span class="tutorial-icon">${icon(Compass)}</span><span class="portal-kicker">Your quick start</span><h2 id="startHereTitle">Start here</h2><p>Get comfortable with AquaDissect, one short guide at a time.</p>${progressBar(viewed, data.guides.length, 'Guides viewed')}<small>${viewed} of ${data.guides.length} guides viewed · Separate from lesson progress</small></div><div class="tutorial-next"><span class="portal-kicker">${viewed === data.guides.length ? 'Revisit a guide' : 'Suggested next guide'}</span><strong>${e(next.title)}</strong><span>${next.steps.length} easy-to-follow steps</span><a class="portal-primary button-link" href="${e(guideUrl(next))}">${progressFor(data, next)?.viewed_at ? 'Replay guide' : progressFor(data, next) ? 'Continue guide' : 'Start guide'}</a><a href="${e(helpUrl())}">Browse all guides</a></div></section>`;
}

export function setupTutorials(data, user) {
  if (!data?.guides?.length) return;
  const params = new URLSearchParams(location.search);
  const guideRoot = document.querySelector('#tutorialGuides');
  let selected = data.guides.find(g => g.id === params.get('guide'));
  let current = 0;
  let panel;
  let highlighted;
  let returnFocus;
  let observer;
  let frame;
  let writeQueue = Promise.resolve();
  let unsaved = null;
  let saveFailed = false;
  let lastTarget;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');

  function renderGuides() {
    if (!guideRoot) return;
    const viewed = data.guides.filter(g => progressFor(data, g)?.viewed_at).length;
    const roleName = { student: 'Student', instructor: 'Teacher', program_head: 'Program Head', admin: 'Administrator' }[user.role] || 'Your';
    guideRoot.innerHTML = `<div class="tutorial-heading"><div class="tutorial-heading-intro"><span class="tutorial-icon">${icon(Compass)}</span><div><span class="portal-kicker">${e(roleName)} guides</span><h2>What would you like to do?</h2><p>Choose a task. Read at your own pace or let us show you where to go.</p></div></div><div class="tutorial-overview"><strong>${viewed} of ${data.guides.length} guides viewed</strong>${progressBar(viewed, data.guides.length, 'Guides viewed')}<small>Guides help you get started. Lesson progress and class records stay separate.</small></div><label for="tutorialSearch">Find a task</label><input id="tutorialSearch" type="search" placeholder="Search by task or keyword…"><p id="tutorialEmpty" role="status" hidden>No matching guides. Try a shorter task name.</p></div><div class="tutorial-grid">${data.guides.map(g => {
      const progress = progressFor(data, g);
      const [tone, glyph] = styleFor(g);
      return `<article class="tutorial-card tutorial-tone-${tone}" id="guide-${e(g.id)}"><div class="tutorial-card-top"><span class="tutorial-icon">${icon(glyph)}</span><span class="tutorial-state">${progress?.viewed_at ? 'Viewed' : progress ? 'In progress' : 'Ready to start'}</span></div><h3>${e(g.title)}</h3><p>${e(g.purpose)}</p><small class="tutorial-length">${g.steps.length} steps · Go at your own pace</small><div class="tutorial-prerequisite"><strong>Before you start</strong><p>${e(g.prerequisite)}</p></div><details><summary>Read instructions</summary><ol class="tutorial-instructions">${g.steps.map(s => `<li><strong>${e(s.title)}</strong><p>${e(s.text)}</p></li>`).join('')}</ol><div class="tutorial-result"><strong>What to expect</strong><p>${e(g.result)}</p></div><p class="tutorial-support">${e(g.support)}</p></details><button class="portal-primary" type="button" data-start-guide="${e(g.id)}">${progress?.viewed_at ? 'Replay walkthrough' : progress ? 'Continue walkthrough' : 'Start walkthrough'}</button></article>`;
    }).join('')}</div>`;
    guideRoot.querySelector('#tutorialSearch').addEventListener('input', event => {
      const query = event.target.value.trim().toLowerCase();
      const cards = [...guideRoot.querySelectorAll('.tutorial-card')];
      cards.forEach(card => { card.hidden = !card.textContent.toLowerCase().includes(query); });
      guideRoot.querySelector('#tutorialEmpty').hidden = cards.some(card => !card.hidden);
    });
    guideRoot.querySelectorAll('[data-start-guide]').forEach(button => button.addEventListener('click', () => start(button.dataset.startGuide, button)));
  }

  function setSaveMessage(message) {
    const status = panel?.querySelector('[data-guide-save]');
    if (status) status.textContent = message;
    const retry = panel?.querySelector('[data-guide-retry]');
    if (retry) retry.hidden = !saveFailed;
  }

  function save(viewed = false) {
    const guide = selected;
    const payload = { version: guide.version, step: guide.steps[current].id, viewed };
    const item = { guide, payload };
    unsaved = item;
    saveFailed = false;
    setSaveMessage('Saving your place…');
    writeQueue = writeQueue.catch(() => {}).then(async () => {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 12000);
      try {
        const response = await request(`/api/tutorials/${guide.id}/progress`, { method: 'PUT', body: payload, signal: controller.signal });
        data.progress = [...(data.progress || []).filter(p => !(p.guide_key === guide.id && Number(p.version) === guide.version)), response.progress];
        if (unsaved === item) { unsaved = null; saveFailed = false; setSaveMessage(viewed ? 'Guide viewed. Your learning records are unchanged.' : 'Your place is saved to your account.'); }
      } catch {
        if (unsaved === item) { saveFailed = true; setSaveMessage('Your place was not saved. Reconnect and choose Retry before leaving to retain it.'); }
      } finally { clearTimeout(timeout); }
    });
    return writeQueue;
  }

  function clearHighlight() {
    highlighted?.classList.remove('tutorial-highlight');
    highlighted = null;
    lastTarget = undefined;
  }

  function updateTarget(scroll = false) {
    if (!panel || !selected) return;
    const step = selected.steps[current];
    const samePage = window.AQUADISSECT_PAGE === step.page;
    const target = samePage ? [...document.querySelectorAll(step.target)].find(el => el.checkVisibility ? el.checkVisibility({ checkVisibilityCSS: true }) : el.getClientRects().length && !el.closest('[hidden]')) : null;
    if (lastTarget === target && !scroll) return;
    clearHighlight();
    lastTarget = target;
    const note = panel.querySelector('[data-guide-missing]');
    note.textContent = target ? '' : samePage ? step.missing : 'Open the page below to see this step in the application.';
    note.hidden = Boolean(target);
    if (target) {
      highlighted = target;
      highlighted.classList.add('tutorial-highlight');
      if (scroll) target.scrollIntoView({ block: 'start', behavior: reduced.matches ? 'instant' : 'smooth' });
    }
  }

  function draw() {
    clearHighlight();
    const step = selected.steps[current];
    const destination = new URL(window.AQUADISSECT_ROUTES[step.page], location.origin);
    destination.searchParams.set('guide', selected.id);
    destination.searchParams.set('step', step.id);
    destination.searchParams.set('walkthrough', '1');
    const currentUrl = new URL(location.href);
    currentUrl.searchParams.set('guide', selected.id);
    currentUrl.searchParams.set('step', step.id);
    currentUrl.searchParams.set('walkthrough', '1');
    history.replaceState(null, '', currentUrl);
    panel.innerHTML = `<header><span class="portal-kicker">${e(selected.title)}</span><button type="button" class="portal-link" data-guide-exit aria-label="Exit walkthrough">Exit</button></header><p class="tutorial-step-count" aria-live="polite">Step ${current + 1} of ${selected.steps.length}</p><h2 id="tutorialStepTitle" tabindex="-1">${e(step.title)}</h2><p>${e(step.text)}</p><p class="tutorial-missing" data-guide-missing>${e(step.missing)}</p><div class="tutorial-actions"><a href="${e(destination.href)}" class="portal-secondary button-link">Open ${e(document.querySelector(`[data-nav-label="${step.page}"]`)?.textContent || ({home:'Home', reports:'Reports', manager:'Species content', access:'Account roles'}[step.page]) || 'page')}</a><button type="button" class="portal-link" data-guide-target>Show control</button></div><footer><button class="portal-secondary" type="button" data-guide-back ${current === 0 ? 'disabled' : ''}>Back</button><button class="portal-primary" type="button" data-guide-next>${current === selected.steps.length - 1 ? 'Finish guide' : 'Next'}</button></footer><small data-guide-save role="status"></small><button type="button" class="portal-link" data-guide-retry hidden>Retry saving</button>`;
    panel.querySelector('.tutorial-step-count').insertAdjacentHTML('afterend', `<div class="tutorial-step-track" aria-hidden="true">${selected.steps.map((_, index) => `<span class="${index <= current ? 'is-reached' : ''}"></span>`).join('')}</div>`);
    panel.querySelector('[data-guide-exit]').onclick = exit;
    panel.querySelector('[data-guide-back]').onclick = () => { current--; draw(); void save(); };
    panel.querySelector('[data-guide-next]').onclick = async event => {
      if (current < selected.steps.length - 1) { current++; draw(); void save(); }
      else {
        event.target.disabled = true;
        await save(true);
        if (!unsaved) {
          exit();
          renderGuides();
          guideRoot?.querySelector(`[data-start-guide="${selected.id}"]`)?.focus({ preventScroll: true });
        }
        else if (panel) event.target.disabled = false;
      }
    };
    panel.querySelector('[data-guide-retry]').onclick = () => { if (unsaved) void save(unsaved.payload.viewed); };
    panel.querySelector('[data-guide-target]').onclick = () => updateTarget(true);
    const openPage = panel.querySelector('a');
    openPage.hidden = window.AQUADISSECT_PAGE === step.page;
    openPage.addEventListener('click', async event => {
      if (!unsaved) return;
      event.preventDefault();
      await writeQueue;
      if (!unsaved) window.location.assign(destination.href);
      else {
        setSaveMessage('Your place was not saved. Retry saving, or choose Open page without saving.');
        openPage.textContent = 'Open page without saving';
        openPage.onclick = () => { window.location.assign(destination.href); };
      }
    });
    panel.querySelector('#tutorialStepTitle').focus({ preventScroll: true });
    updateTarget(false);
    if (unsaved) setSaveMessage('Your place has not finished saving.');
  }

  function exit() {
    observer?.disconnect();
    cancelAnimationFrame(frame);
    clearHighlight();
    panel?.remove();
    panel = null;
    document.body.classList.remove('tutorial-active');
    const url = new URL(location.href);
    ['walkthrough', 'step'].forEach(key => url.searchParams.delete(key));
    history.replaceState(null, '', url);
    (returnFocus?.isConnected ? returnFocus : document.querySelector('.portal-page h1'))?.focus({ preventScroll: true });
  }

  function start(id, trigger, stepId = null) {
    exit();
    selected = data.guides.find(g => g.id === id);
    if (!selected) return;
    returnFocus = trigger;
    const stored = progressFor(data, selected);
    const requestedStep = stepId || (!stored?.viewed_at ? stored?.step_key : null);
    current = Math.max(0, selected.steps.findIndex(step => step.id === requestedStep));
    panel = document.createElement('aside');
    panel.className = 'tutorial-panel';
    panel.setAttribute('role', 'region');
    panel.setAttribute('aria-labelledby', 'tutorialStepTitle');
    document.body.append(panel);
    document.body.classList.add('tutorial-active');
    draw();
    void save();
    observer = new MutationObserver(records => {
      if (records.every(record => panel?.contains(record.target))) return;
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => updateTarget());
    });
    observer.observe(document.querySelector('#aquaPortal'), { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'open', 'class'] });
    const lesson = document.querySelector('#lesson-content');
    if (lesson) observer.observe(lesson, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'class'] });
  }

  renderGuides();
  if (selected && params.get('walkthrough') === '1') start(selected.id, null, params.get('step'));
  else if (selected && guideRoot) {
    const card = document.getElementById(`guide-${selected.id}`);
    card?.scrollIntoView({ block: 'center' });
    card?.querySelector('button')?.focus({ preventScroll: true });
  }
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && panel) { event.preventDefault(); exit(); } });
  window.addEventListener('pagehide', () => { observer?.disconnect(); cancelAnimationFrame(frame); });
}

export async function setupStaffRoles(confirm) {
  const root = document.querySelector('#staffRoleTools');
  if (!root) return;
  async function render() {
    try {
      const data = await request('/api/admin/staff-roles');
      root.innerHTML = `<h2>Program Head access</h2><p>Choose a designated active staff account. Section assignments and past records remain saved. Instructor management privileges stop while Program Head access is active.</p><div class="role-staff-list">${data.staff.map(user => `<form data-staff-role="${user.id}"><label>${e(user.firstName)} ${e(user.lastName)} <small>${e(user.email)}</small><select name="role" aria-label="Role for ${e(user.firstName)} ${e(user.lastName)}"><option value="instructor" ${user.role === 'instructor' ? 'selected' : ''}>Instructor</option><option value="program_head" ${user.role === 'program_head' ? 'selected' : ''}>Program Head</option></select></label><button type="submit" class="portal-secondary">Review change</button></form>`).join('') || '<p>No eligible active staff accounts.</p>'}</div><details><summary>Recent role changes</summary><ul>${data.history.map(item => `<li>Account #${item.user_id}: ${e(item.previous_role.replaceAll('_',' '))} → ${e(item.new_role.replaceAll('_',' '))} · Administrator #${item.actor_id} · ${e(new Date(item.created_at).toLocaleString())}</li>`).join('') || '<li>No recorded role changes.</li>'}</ul></details><p data-role-message role="status"></p>`;
      root.querySelectorAll('form').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        const role = new FormData(form).get('role');
        try {
          const accepted = await confirm({ title: `Change this account to ${role === 'program_head' ? 'Program Head' : 'Instructor'}?`, description: 'Existing section assignments and history remain saved. Program Heads can inspect all sections and review content, but cannot manage teaching activities, accounts, or publication.', confirmLabel: 'Apply role change' });
          if (!accepted) return;
          await request(`/api/admin/staff-roles/${form.dataset.staffRole}`, { method: 'PATCH', body: { role, confirmed: true } });
          await render();
          root.querySelector('[data-role-message]').textContent = 'Role updated. The account will refresh on its next session check.';
        } catch (error) { root.querySelector('[data-role-message]').textContent = error.message; }
        finally { button.disabled = false; }
      }));
    } catch {
      root.innerHTML = '<h2>Program Head access</h2><p role="alert">Staff accounts could not load.</p><button type="button" class="portal-secondary">Try again</button>';
      root.querySelector('button').onclick = render;
    }
  }
  await render();
}
