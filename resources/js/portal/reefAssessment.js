import { createElement, Fish, Shell, Flag, Check, X } from 'lucide';
import { escapeHtml as escape } from './format.js';
import { createUuid } from '../uuid.js';
import { animateFeedback } from '../motionPreferences.js';
import { createDiveAnswers } from './reefAssessmentAnswers.js';

export const diveNames = { pre: 'First Dive', post: 'Return Dive' };

function icon(shape, className) {
  return createElement(shape, { class: className, 'aria-hidden': 'true', focusable: 'false' });
}

function button(action, label, primary = false) {
  return `<button type="button" data-action="${action}" class="rq-button${primary ? ' rq-primary' : ''}">${label}</button>`;
}

export function reefAssessmentSnapshot() {
  const dialog = document.querySelector('.reef-assessment[open]');
  if (!dialog) return null;
  const { phase, screen, question, total, answered } = dialog.dataset;
  return { phase, screen, question: question ? Number(question) : null, total: Number(total), answered: Number(answered) };
}

export function diveComparisonMarkup(row) {
  const signed = value => value > 0 ? '+' + value : String(value);
  return `<h4>Your paired scores</h4><dl><div><dt>Pre-test</dt><dd>${row.pre.score} / ${row.pre.total}</dd></div>
    <div><dt>Post-test</dt><dd>${row.post.score} / ${row.post.total}</dd></div></dl>
    <p>Score change <strong>${signed(row.pointDifference)} ${Math.abs(row.pointDifference) === 1 ? 'point' : 'points'}</strong> (${signed(row.percentagePointDifference)} percentage points)</p>`;
}

export function openDive({ study, phase, launcher, isCurrent, refresh, source, listenForAccessLoss, demo = false }) {
  if (!isCurrent() || document.querySelector('.reef-assessment[open]')) return;
  const phaseLabel = phase === 'pre' ? 'Pre-test' : 'Post-test';
  const backLabel = demo ? 'Back to demo' : 'Back to My class';
  const session = new AbortController();
  const { signal } = session;
  const dialog = document.createElement('dialog');
  dialog.className = 'reef-assessment';
  Object.assign(dialog.dataset, { phase, screen: 'loading', total: '0', answered: '0' });
  dialog.setAttribute('aria-labelledby', 'reef-dive-title');
  dialog.innerHTML = `<header class="rq-header"><div class="rq-brand">Reef Quest <span>${phaseLabel}</span></div>
    <button type="button" class="rq-close" data-action="close" aria-label="Close assessment"></button>
    <h2 id="reef-dive-title">${diveNames[phase]}</h2><p class="rq-study">${escape(study.title)} · ${escape(study.instrumentVersion)}</p></header>
    <div class="rq-progress" hidden><div class="rq-progress-label"></div><div class="rq-track" role="progressbar" aria-label="Questions answered" aria-valuemin="0"><div class="rq-fill"></div></div></div>
    <div class="rq-viewport"><div class="rq-main"><p role="status">Opening your ${phaseLabel.toLowerCase()}…</p></div></div>`;
  const main = dialog.querySelector('.rq-main');
  const progress = dialog.querySelector('.rq-progress');
  const find = selector => dialog.querySelector(selector);
  find('.rq-header').prepend(icon(phase === 'pre' ? Fish : Shell, 'rq-emblem'));
  find('.rq-close').append(icon(X, 'rq-icon'));
  document.body.append(dialog);
  dialog.showModal();

  let questions = [], draft, index = 0, editing = false, earlierScreen, refreshNeeded = false;
  const active = () => dialog.open && !signal.aborted && isCurrent();
  const close = () => { if (dialog.open) dialog.close(); };
  const forceClose = () => { refreshNeeded = false; close(); };

  listenForAccessLoss?.(forceClose, signal);
  window.addEventListener('pagehide', forceClose, { signal });
  window.addEventListener('popstate', forceClose, { signal });
  window.addEventListener('beforeunload', event => {
    if (dialog.open && draft?.count && ['question', 'review', 'discard'].includes(dialog.dataset.screen)) {
      event.preventDefault();
      event.returnValue = '';
    }
  }, { signal });
  dialog.addEventListener('close', () => {
    session.abort();
    dialog.remove();
    if (!isCurrent()) return;
    if (refreshNeeded) refresh();
    else launcher.focus();
  }, { once: true });

  function setProgress() {
    dialog.dataset.answered = String(draft.count);
    find('.rq-progress-label').textContent = `${draft.count} of ${questions.length} answered`;
    const track = find('.rq-track');
    track.setAttribute('aria-valuenow', String(draft.count));
    track.setAttribute('aria-valuemax', String(questions.length));
    find('.rq-fill').style.width = `${100 * draft.count / questions.length}%`;
  }

  function show(screen, html, direction = 1) {
    dialog.dataset.screen = screen;
    dialog.dataset.question = screen === 'question' ? String(index + 1) : '';
    progress.hidden = !['question', 'review'].includes(screen);
    main.innerHTML = html;
    dialog.scrollTop = 0;
    const heading = main.querySelector('[data-heading]');
    if (heading) {
      heading.tabIndex = -1;
      heading.focus({ preventScroll: true });
    }
    animateFeedback(main, [{ opacity: 0.75, transform: `translateX(${direction * 9}px)` }, { opacity: 1, transform: 'none' }], { duration: 205 });
  }

  function showQuestion(direction = 1) {
    const q = questions[index];
    const options = q.options.map((option, choice) => `<label class="rq-choice">
      <input type="radio" name="reef-choice" value="${choice}" required${draft.selected(index) === choice ? ' checked' : ''}>
      <span class="rq-letter" aria-hidden="true">${String.fromCharCode(65 + choice)}</span>
      <span class="rq-option-text">${escape(option)}</span><span class="rq-tick" aria-hidden="true">✓</span></label>`).join('');
    const forward = editing ? 'Return to review' : index === questions.length - 1 ? 'Review answers' : 'Next question';
    show('question', `<p class="rq-kicker">Question ${index + 1} of ${questions.length}</p><form>
      <fieldset aria-labelledby="rq-prompt"><legend id="rq-prompt" data-heading>${escape(q.prompt)}</legend><div class="rq-choices">${options}</div></fieldset>
      <div class="rq-actions">${!editing && index > 0 ? button('back', 'Previous') : ''}<button type="submit" class="rq-button rq-primary">${forward}</button></div></form>`, direction);
    setProgress();
  }

  function showReview() {
    editing = false;
    const items = questions.map((q, n) => `<li><div><span class="rq-review-number">${n + 1}</span>
      <strong>${escape(q.prompt)}</strong><span class="rq-review-answer">${escape(q.options[draft.selected(n)])}</span></div>
      <button type="button" class="rq-edit" data-edit="${n}" aria-label="Change answer to question ${n + 1}">Change</button></li>`).join('');
    show('review', `<h3 data-heading>Review your answers</h3><p>You can change any answer here. ${demo ? 'Submit to see your sample score.' : 'Submitting records your one scored attempt.'}</p>
      <ol class="rq-review">${items}</ol><div class="rq-actions">${button('submit', 'Submit '+phaseLabel.toLowerCase(), true)}</div>`);
  }

  function requestClose() {
    if (dialog.dataset.screen === 'discard') {
      resumeDraft();
    } else if (draft?.count && ['question', 'review'].includes(dialog.dataset.screen)) {
      earlierScreen = dialog.dataset.screen;
      show('discard', `<h3 data-heading>Leave this dive?</h3><p>Your answers are only saved when you submit. Leaving now will discard this draft.</p>
        <div class="rq-actions">${button('discard', 'Discard and leave')}${button('resume', 'Keep working', true)}</div>`);
    } else close();
  }
  function resumeDraft() {
    if (earlierScreen === 'review') showReview();
    else showQuestion(-1);
  }
  function unavailable(text) {
    show('unavailable', `<h3 data-heading>Assessment unavailable</h3><p>${escape(text)}</p><div class="rq-actions">${button('close', backLabel, true)}</div>`);
  }

  async function submit() {
    if (!['review', 'retry'].includes(dialog.dataset.screen) || !active()) return;
    let payload;
    try { payload = draft.submission(createUuid); }
    catch (error) { unavailable(error.message); return; }
    if (!payload) return;
    refreshNeeded = true;
    show('saving', demo ? '<h3 data-heading>Checking your answers…</h3>' : '<h3 data-heading>Recording your answers…</h3><p role="status">Please stay online. You can check your score in My class after submission.</p>');
    try {
      const result = await source.submit(payload, signal);
      if (!active()) return;
      if (!result || result.phase !== phase || !Number.isFinite(result.score) || !Number.isFinite(result.total)) throw new Error('Missing receipt');
      show('result', `<div class="rq-receipt"><span class="rq-stamp" aria-hidden="true"></span><div>
        <h3 data-heading>${demo ? diveNames[phase] + ' complete' : phaseLabel + ' recorded'}</h3><p class="rq-score"><strong>${result.score}</strong> / ${result.total} <span>points</span></p></div></div>
        <div class="rq-comparison" role="status">${phase === 'pre' ? (demo ? 'Explore the sample lesson to open Return Dive.' : 'Complete the assigned lesson to open Return Dive.') : 'Loading your paired scores…'}</div>
        <div class="rq-actions">${button('close', demo && phase === 'pre' ? 'Continue to sample lesson' : backLabel, true)}</div>`);
      const stamp = find('.rq-stamp');
      stamp.append(icon(phase === 'pre' ? Check : Flag, 'rq-icon'));
      animateFeedback(stamp, [{ transform: 'scale(.85) rotate(-10deg)' }, { transform: 'scale(1.1)' }, { transform: 'none' }], { duration: 390 });
      if (phase === 'post') await showComparison(result.id);
    } catch (error) {
      if (!active()) return;
      if (demo || [403, 404, 409, 422].includes(error.status)) { unavailable(error.message); return; }
      show('retry', `<h3 data-heading>Submission not confirmed</h3><p>Reconnect and retry to confirm these same answers. You can also check My class for your recorded score.</p>
        <div class="rq-actions">${button('close', 'Back to My class')}${button('submit', 'Retry submission', true)}</div>`);
    }
  }

  async function showComparison(resultId) {
    const panel = find('.rq-comparison');
    try {
      const row = await source.comparison(resultId, signal);
      if (!active()) return;
      if (!row) { panel.textContent = 'A matched pre-test and post-test comparison is currently unavailable.'; return; }
      panel.innerHTML = diveComparisonMarkup(row);
    } catch {
      if (active()) panel.textContent = demo ? 'Close this dive to view your sample scores.' : 'Your post-test was recorded. Open My class to check the paired scores.';
    }
  }

  dialog.addEventListener('cancel', event => { event.preventDefault(); requestClose(); });
  dialog.addEventListener('change', event => {
    if (dialog.dataset.screen !== 'question' || !event.target.matches('[name="reef-choice"]')) return;
    draft.choose(index, Number(event.target.value));
    setProgress();
    animateFeedback(event.target.parentElement.querySelector('.rq-letter'), [{ transform: 'scale(.86)' }, { transform: 'scale(1.12)' }, { transform: 'none' }], { duration: 220 });
  });
  dialog.addEventListener('submit', event => {
    event.preventDefault();
    if (dialog.dataset.screen !== 'question' || draft.selected(index) == null) return;
    if (editing || index === questions.length - 1) showReview();
    else { index++; showQuestion(); }
  });
  dialog.addEventListener('click', event => {
    const target = event.target.closest('button');
    if (!target) return;
    if (target.hasAttribute('data-edit')) {
      index = Number(target.dataset.edit);
      editing = true;
      showQuestion(-1);
      return;
    }
    switch (target.dataset.action) {
      case 'close': requestClose(); break;
      case 'discard': close(); break;
      case 'resume': resumeDraft(); break;
      case 'back': index--; showQuestion(-1); break;
      case 'submit': void submit(); break;
    }
  });

  void (async () => {
    try {
      const items = await source.questions(signal);
      if (!active()) return;
      if (!items?.length) { unavailable('This form has no questions. Contact your instructor.'); return; }
      questions = items;
      draft = createDiveAnswers(questions);
      dialog.dataset.total = String(questions.length);
      showQuestion();
    } catch (error) {
      if (active()) { refreshNeeded = error.status === 409; unavailable(error.message); }
    }
  })();
}
