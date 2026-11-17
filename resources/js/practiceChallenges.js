import { request } from './portal/api.js';
import { signOutPending } from './portal/sessionAccess.js';
import { escapeHtml as e } from './portal/format.js';
import { nextPracticeIndex, practiceView } from './practiceView.js';
import { animateFeedback } from './motionPreferences.js';

export function createPracticeChallenges(dialog, entry, { currentUser, refreshIcons }) {
  let context = null;
  let data = null;
  let state;
  let generation = 0;
  let controller = null;
  let returnFocus = null;
  const resetState = index => ({ index, selected: null, hint: false, feedback: null, busy: false, finished: false });

  function current() {
    return context && !signOutPending() && String(currentUser()?.id) === context.userId;
  }

  function frame(body) {
    dialog.innerHTML = `<header class="practice-header"><div><p class="eyebrow">Optional practice</p><h2 id="practiceTitle">Anatomy challenges</h2><p>${e(context?.title || '')} · v${e(context?.version || '')}</p></div><button type="button" class="icon-button" data-practice-close aria-label="Close practice challenges"><i data-lucide="x" aria-hidden="true"></i></button></header><div class="practice-body">${body}</div>`;
    refreshIcons();
  }

  function render({ focus = false, choice = null } = {}) {
    if (!current() || !dialog.open || !data) return;
    frame(practiceView(data, state));
    if (state.feedback?.correct && !state.feedback.unconfirmed && !state.feedback.motionShown && !data.preview && (state.feedback.awardedXp > 0 || state.feedback.earnedBadges?.length)) {
      state.feedback.motionShown = true;
      const reward = dialog.querySelector('[data-practice-reward]');
      const previousPercent = Math.round(state.feedback.previousCompleted / data.summary.total * 100);
      const currentPercent = Math.round(data.summary.completed / data.summary.total * 100);
      animateFeedback(dialog.querySelector('.practice-progress > span'), [{ width: `${previousPercent}%` }, { width: `${currentPercent}%` }], { duration: 400 });
      animateFeedback(reward, [{ transform: 'translateY(7px) scale(.97)' }, { transform: 'translateY(0) scale(1)' }]);
      animateFeedback(reward?.querySelector('.practice-reward-icon'), [{ transform: 'scale(.75) rotate(-8deg)' }, { transform: 'scale(1.12) rotate(3deg)' }, { transform: 'scale(1)' }], { duration: 450 });
      animateFeedback(reward?.querySelector('.practice-reward-sparks'), [{ transform: 'translateY(8px) scale(.6)', opacity: 0 }, { transform: 'translateY(-8px) scale(1.1)', opacity: 1 }, { transform: 'translateY(0) scale(1)', opacity: 1 }], { duration: 650 });
    }
    if (choice !== null) dialog.querySelector(`[data-practice-choice="${choice}"]`)?.focus();
    else if (focus) dialog.querySelector('[data-practice-focus]')?.focus({ preventScroll: true });
  }

  async function open() {
    if (!current()) return;
    if (!dialog.open) returnFocus = entry;
    controller?.abort();
    controller = new AbortController();
    const version = ++generation;
    data = null;
    frame('<p role="status">Loading your practice challenges…</p>');
    if (!dialog.open) dialog.showModal();
    try {
      if (!navigator.onLine) throw new Error('Connect to the internet to open practice challenges and save rewards. Your downloaded lesson is still available for study.');
      const query = context.classId ? `?classId=${context.classId}` : '';
      const result = await request(`/api/species-packages/${context.packageId}/practice${query}`, { signal: controller.signal });
      if (version !== generation || !current()) return;
      if (String(result.packageId) !== context.packageId || result.packageVersion !== context.version) throw new Error('The lesson version changed. Reopen the lesson before practicing.');
      context.classId = result.classId;
      data = result;
      state = resetState(nextPracticeIndex(data.challenges, data.summary.completedIds));
      state.finished = data.summary.complete;
      render({ focus: true });
    } catch (error) {
      if (version !== generation || error.name === 'AbortError' || !current()) return;
      frame(`<div class="practice-empty"><h3>Practice needs attention</h3><p role="alert">${e(error.message)}</p><button type="button" class="primary-button" data-practice-reload>Try again</button></div>`);
    }
  }

  async function submit() {
    if (!current() || !dialog.open || !data || state.busy || state.selected === null || state.feedback?.correct) return;
    if (!navigator.onLine) {
      state.feedback = { unconfirmed: true, feedback: 'Reconnect before checking this answer. No new reward has been confirmed.' };
      render({ focus: true });
      return;
    }
    const version = generation;
    const challenge = data.challenges[state.index];
    state.busy = true;
    state.feedback = null;
    render();
    try {
      const result = await request(`/api/species-packages/${context.packageId}/practice/answers`, {
        method: 'POST', signal: controller.signal,
        body: { classId: context.classId, packageVersion: context.version, challengeId: challenge.id, answer: challenge.options[state.selected] },
      });
      if (version !== generation || !current()) return;
      const earnedBadges = result.summary.badges.filter(badge => badge.earned && !data.summary.badges.some(previous => previous.id === badge.id && previous.earned));
      const previousCompleted = data.summary.completed;
      data.summary = result.summary;
      state.feedback = { ...result, earnedBadges, previousCompleted };
      window.dispatchEvent(new CustomEvent('aquadissect-practice-updated'));
    } catch (error) {
      if (version !== generation || error.name === 'AbortError' || !current()) return;
      state.feedback = { unconfirmed: true, feedback: `${error.message} Retry when the connection is available; a saved reward cannot be awarded twice.` };
    } finally {
      if (version === generation && current()) {
        state.busy = false;
        render({ focus: true });
      }
    }
  }

  dialog.addEventListener('click', event => {
    if (event.target.closest('[data-practice-close]')) { dialog.close(); return; }
    if (event.target.closest('[data-practice-reload]')) { void open(); return; }
    if (!current() || !data || state.busy) return;
    const destination = event.target.closest('[data-practice-go]');
    if (destination) {
      const index = Number(destination.dataset.practiceGo);
      if (Number.isInteger(index) && index >= 0 && index < data.challenges.length && index !== state.index) {
        state = resetState(index);
        render({ focus: true });
      }
      return;
    }
    const choice = event.target.closest('[data-practice-choice]');
    if (choice && !state.feedback?.correct) {
      const index = Number(choice.dataset.practiceChoice);
      if (!Number.isInteger(index) || !data.challenges[state.index].options[index]) return;
      state.selected = index;
      state.feedback = null;
      render({ choice: index });
    } else if (event.target.closest('[data-practice-hint]')) {
      state.hint = !state.hint;
      render();
      dialog.querySelector('[data-practice-hint]')?.focus();
    } else if (event.target.closest('[data-practice-submit]')) void submit();
    else if (event.target.closest('[data-practice-next]') && state.feedback?.correct) {
      if (state.index === data.challenges.length - 1) state.finished = true;
      else state = resetState(state.index + 1);
      render({ focus: true });
    } else if (event.target.closest('[data-practice-restart]')) {
      state = resetState(0);
      render({ focus: true });
    }
  });
  dialog.addEventListener('close', () => {
    // A queued close event from the previous opening must not cancel a new one.
    if (dialog.open) return;
    generation++;
    controller?.abort();
    if (returnFocus?.isConnected && !entry.hidden) returnFocus.focus({ preventScroll: true });
    returnFocus = null;
  });
  entry.addEventListener('click', () => void open());

  return {
    open,
    snapshot() {
      if (!current() || !dialog.open || !data) return { open: Boolean(dialog.open && current()), loading: Boolean(dialog.open && current() && !data) };
      const challenge = data.challenges[state.index];
      return {
        open: true, preview: data.preview, finished: state.finished, busy: state.busy,
        challenge: challenge ? { index: state.index, kind: challenge.kind, clue: challenge.clue, options: challenge.options, selected: state.selected, hint: state.hint } : null,
        feedback: state.feedback ? { correct: Boolean(state.feedback.correct), unconfirmed: Boolean(state.feedback.unconfirmed), awardedXp: state.feedback.awardedXp || 0 } : null,
        xp: data.summary.xp, completed: data.summary.completed, total: data.summary.total,
        badges: data.summary.badges.filter(badge => badge.earned).map(badge => badge.name),
      };
    },
    load(manifest, { classId = null } = {}) {
      this.clear();
      const user = currentUser();
      if (!user) return;
      context = { packageId: String(manifest.id), version: manifest.packageVersion, classId, userId: String(user.id), title: manifest.contentData.lesson.title };
      entry.hidden = false;
    },
    clear() {
      generation++;
      controller?.abort();
      returnFocus = null;
      context = null;
      data = null;
      entry.hidden = true;
      if (dialog.open) dialog.close();
      dialog.replaceChildren();
    },
  };
}
