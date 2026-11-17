import { request } from './api.js';
import { escapeHtml as e } from './format.js';
import { signOutPending } from './sessionAccess.js';
import { animateFeedback } from '../motionPreferences.js';

import { questContexts, questBadgeView } from './reefQuestView.js';

export function mountReefQuest(root, { getState, openPackage }) {
  const owner = String(getState().currentUser?.id);
  const contexts = questContexts(getState().classes, getState().species);
  const field = root.querySelector('[data-quest-context]');
  field.innerHTML = `<option value="">${contexts.length ? 'Choose a section and lesson' : 'No available lesson assignment'}</option>${contexts.map(c => `<option value="${e(c.key)}">${e(c.section.name)} · ${e(c.record.commonName)} · v${e(c.record.version)}</option>`).join('')}`;
  if (contexts.length === 1) field.value = contexts[0].key;
  let version = 0, controller = null, disposed = false, canPractice = false;
  const node = name => root.querySelector(`[data-quest-${name}]`);
  const current = () => !disposed && root.isConnected && !signOutPending() && String(getState().currentUser?.id) === owner && getState().currentUser?.role === 'student';
  const online = () => navigator.onLine && getState().readinessConfirmed;
  const selected = () => contexts.find(context => context.key === field.value);
  function clear() {
    version++; controller?.abort(); canPractice = false;
    node('xp').textContent = 'Not confirmed'; node('count').textContent = 'Not confirmed'; node('badges').replaceChildren();
    node('badge-note').textContent = 'Choose a section and lesson to confirm your collection.';
    node('safety').textContent = node('lesson').textContent = node('practice').textContent = 'Choose a lesson';
    node('safety-icon').classList.remove('is-complete');
    node('practice-open').disabled = true; node('continue').disabled = true;
    root.querySelector('.reef-hero-art').hidden = true;
    const image = root.querySelector('.reef-hero-art img');
    image.onload = image.onerror = null; image.removeAttribute('src');
  }
  async function load() {
    clear();
    if (!current()) return;
    const context = selected();
    if (!context) {
      node('status').textContent = contexts.length ? 'Select your section and lesson. Rewards are not combined across versions or sections.' : 'Your teacher must arrange a permitted lesson assignment. Account and orientation guidance is below.';
      return;
    }
    const { record, section } = context;
    node('status').textContent = `${section.name} · ${record.commonName} v${record.version}`;
    node('badge-note').textContent = 'Confirming your saved collection…';
    if (!online()) {
      node('safety').textContent = node('lesson').textContent = node('practice').textContent = 'Check online';
      node('status').textContent = 'Connect and refresh to confirm this trail. Downloaded lessons remain available through Offline access.';
      node('badge-note').textContent = 'Connect to confirm rewards. No offline XP is awarded.';
      return;
    }
    const gate = section.prerequisiteStatus;
    node('safety').textContent = !gate ? 'Check online' : !gate.required ? 'Not assigned' : gate.overridden ? 'Exception active' : gate.satisfied ? 'Completed' : 'Preparation required';
    node('safety-icon').classList.toggle('is-complete', Boolean(gate?.required && gate.satisfied && !gate.overridden));
    node('lesson').textContent = context.ready ? 'Ready to explore' : 'Prerequisite pending';
    if (!context.ready) { node('practice').textContent = 'Prerequisite pending'; node('badge-note').textContent = 'Complete the assigned safety prerequisite or check your teacher’s instructions.'; return; }
    node('continue').disabled = false; node('continue').innerHTML = 'Continue learning <i data-lucide="arrow-right" aria-hidden="true"></i>';
    const generation = version; controller = new AbortController();
    if (record.naturalImageUrl) {
      const art = root.querySelector('.reef-hero-art'), image = art.querySelector('img');
      image.onload = () => {
        if (!current() || generation !== version || !online()) return;
        art.hidden = false;
        animateFeedback(art, [{ transform: 'translateX(0)' }, { transform: 'translateX(-8px)' }, { transform: 'translateX(0)' }], { duration: 900, easing: 'ease-in-out' });
      };
      image.onerror = () => { art.hidden = true; };
      image.alt = `${record.commonName} specimen illustration`; node('species').textContent = record.commonName;
      image.src = record.naturalImageUrl;
    }
    const results = await Promise.allSettled([
      request(`/api/species-packages/${record.id}/practice?classId=${section.id}`, { signal: controller.signal }),
      request(`/api/reports/analytics?classId=${section.id}&activity=lessons&activityKey=lesson:${record.id}`, { signal: controller.signal }),
    ]);
    if (!current() || generation !== version || !online()) return;
    const [practice, report] = results;
    if (report.status === 'fulfilled' && report.value.summary) node('lesson').textContent = report.value.summary.completedItems > 0 ? 'Completed · review available' : 'Ready to explore';
    if (practice.status === 'fulfilled' && String(practice.value.classId) === String(section.id) && String(practice.value.packageId) === String(record.id) && practice.value.packageVersion === record.version && !practice.value.preview) {
      const summary = practice.value.summary;
      canPractice = summary.total > 0;
      node('xp').textContent = String(summary.xp); node('count').textContent = `${summary.completed} / ${summary.total}`;
      node('badges').innerHTML = questBadgeView(summary.badges);
      node('badge-note').textContent = canPractice ? 'Saved collection for this section and lesson version.' : 'This lesson does not currently have a practice set.';
      node('practice').textContent = !canPractice ? 'Not available' : summary.complete ? 'Completed · replay available' : 'Ready to practice';
      node('practice-open').disabled = !canPractice;
    } else {
      node('practice').textContent = 'Not confirmed'; node('badge-note').textContent = 'Rewards could not be confirmed. Refresh this selection when connected.';
      node('status').textContent += ' · Some saved progress could not be confirmed.';
    }
    document.dispatchEvent(new CustomEvent('aquadissect-refresh-icons'));
  }
  const onChange = () => void load();
  const onClick = event => {
    const practice = event.target.closest('[data-quest-practice-open]');
    if (!practice && !event.target.closest('[data-quest-continue]')) return;
    const context = selected();
    if (current() && online() && context?.ready && (!practice || canPractice)) void openPackage(context.record.id, 'auto', context.section.id, Boolean(practice));
  };
  const onSignout = event => { if (event.target.closest?.('[data-portal-signout]')) clear(); };
  const onReward = () => { if (root.getClientRects().length) void load(); else clear(); };
  const onStorage = event => { if (event.key === 'aquadissect.sign-out-pending.v1' && event.newValue === '1') clear(); };
  const onPageShow = event => { if (event.persisted && current()) void load(); };
  field.addEventListener('change', onChange); root.addEventListener('click', onClick);
  document.addEventListener('click', onSignout, true);
  window.addEventListener('offline', onChange); window.addEventListener('online', onChange);
  window.addEventListener('aquadissect-authorization-lost', clear); window.addEventListener('aquadissect-practice-updated', onReward);
  window.addEventListener('pagehide', clear); window.addEventListener('storage', onStorage);
  window.addEventListener('pageshow', onPageShow);
  void load();
  return { destroy() {
    disposed = true; clear();
    field.removeEventListener('change', onChange); root.removeEventListener('click', onClick); document.removeEventListener('click', onSignout, true);
    window.removeEventListener('offline', onChange); window.removeEventListener('online', onChange); window.removeEventListener('aquadissect-authorization-lost', clear);
    window.removeEventListener('aquadissect-practice-updated', onReward); window.removeEventListener('pagehide', clear); window.removeEventListener('storage', onStorage);
    window.removeEventListener('pageshow', onPageShow);
  } };
}
