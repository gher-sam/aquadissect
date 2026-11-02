import '../css/bootstrap.scss';
import 'bootstrap/js/dist/dropdown';
import '@fontsource/figtree/latin-400.css';
import '@fontsource/figtree/latin-400-italic.css';
import '@fontsource/figtree/latin-500.css';
import '@fontsource/figtree/latin-600.css';
import '@fontsource/figtree/latin-700.css';
import '@fontsource/figtree/latin-800.css';
import {
  Calendar, Bell, ChartNoAxesCombined, ArrowLeft, ArrowRight, Badge, BadgeCheck, BookOpen, Check, ChevronRight, Circle, CircleDashed, CircleHelp, ClipboardList,
  Download, Eye, Fish, Focus, GraduationCap, Info, KeyRound, Layers3, LayoutDashboard, LockKeyhole, LogOut, Mail,
  Maximize2, Menu, PackageOpen, Pause, Play, RotateCcw, ScanSearch, School, ShieldCheck, Sparkles, User,
  Users, UsersRound, Waves, X, createIcons,
} from 'lucide';
import { createObservationNotebook } from './observationNotebook.js';
import { createViewer } from './viewerFactory.js';
import { setupAquaPortal } from './portal/index.js';
import '../css/style.css';
import '../css/fishEnhancements.css';
import { showFishDetails } from './fishDetails.js';
import { setupFishEnhancements } from './portal/fishEnhancements.js';
import '../css/portal.css';
import '../css/threeViewer.css';
import '../css/imageViewer.css';
import '../css/polish.css';
import '../css/login.css';
import '../css/roster-tables.css';
import '../css/workspace.css';
import '../css/typography.css';
import '../css/interface.css';

const app = document.querySelector('#app');
app.innerHTML = `
  <div class="app-shell">
    <header class="app-header">
      <div class="brand-lockup" aria-label="AquaDissect lesson">
        <span class="brand-mark" aria-hidden="true"><i data-lucide="waves"></i></span>
        <span><strong>AquaDissect</strong><small id="lessonBrandSubtitle">Species lesson</small></span>
      </div>
      <div class="header-progress" role="progressbar" aria-label="Lesson progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="progress-copy"><span id="progressLabel">Orientation</span><strong id="progressPercent">0%</strong></div>
        <div class="progress-track" aria-hidden="true"><span id="progressBar"></span></div>
      </div>
      <div class="header-actions">
        <button class="text-button compact-label" id="portalReturnButton" type="button" aria-label="Return to dashboard"><i data-lucide="arrow-left"></i><span>Dashboard</span></button>
        <button class="text-button" id="fishDetailsButton" type="button">Fish details</button>
        <button class="icon-button" id="helpButton" type="button" aria-label="Open lesson information"><i data-lucide="circle-help"></i></button>
        <button class="text-button compact-label" id="sourcesButton" type="button" aria-label="Open references"><i data-lucide="book-open"></i><span>References</span></button>
      </div>
    </header>
    <main class="workspace" id="lesson-content" tabindex="-1">
      <aside class="lesson-panel panel-surface" aria-labelledby="stepTitle">
        <div class="lesson-panel__top"><span class="eyebrow" id="stepEyebrow">Orientation</span><span class="duration" id="lessonDuration"></span></div>
        <h1 id="stepTitle" tabindex="-1">Select a species</h1><p class="lesson-description" id="stepDescription"></p>
        <section class="task-card" aria-labelledby="taskHeading"><div class="task-card__icon" aria-hidden="true"><i data-lucide="scan-search"></i></div><div><span class="task-label" id="taskHeading">Your task</span><p id="stepPrompt"></p></div></section>
        <div class="target-list" id="targetList" aria-live="polite"></div>
        <form class="quiz" id="quiz" hidden></form>
        <button class="text-button notebook-entry" id="notebookButton" type="button" aria-label="Open observation notebook" hidden><i data-lucide="clipboard-list"></i><span>Open observation notebook</span></button>
        <div class="lesson-tip" id="lessonTip"><i data-lucide="info" aria-hidden="true"></i><p><strong>Educational visualization</strong><span id="lessonSafety">Follow instructor guidance and applicable laboratory safety rules.</span></p></div>
        <div class="lesson-actions">
          <button class="primary-button" id="primaryAction" type="button"><span>Continue</span><i data-lucide="arrow-right"></i></button>
          <div class="secondary-actions"><button class="text-button" id="previousStep" type="button" disabled><i data-lucide="arrow-left"></i><span>Previous</span></button><button class="text-button" id="freeExplore" type="button"><i data-lucide="sparkles"></i><span>Explore freely</span></button></div>
        </div>
      </aside>
      <section class="viewer-panel" aria-label="Layered aquatic anatomy viewer">
        <div class="scene" id="scene" tabindex="0" aria-describedby="sceneInstructions">
          <div class="scene-loader" id="sceneLoader"><span></span><p>Loading the selected species…</p></div>
          <div class="scene-topbar">
            <span class="mode-badge" id="modeBadge"><span></span>Natural specimen · 2.5D</span>
            <div class="scene-tools">
              <label class="viewer-mode-control" id="viewerModeControl" hidden><span class="sr-only">Viewer mode</span><select id="viewerMode"><option value="2d">2.5D layers</option><option value="3d">3D model</option></select></label>
              <button class="scene-button" id="labelsButton" type="button" aria-pressed="false"><i data-lucide="eye"></i><span>Labels</span></button>
              <button class="scene-button" id="resetView" type="button"><i data-lucide="rotate-ccw"></i><span>Reset</span></button>
              <button class="scene-button scene-button--icon" id="fullscreenView" type="button" aria-label="Open viewer full screen"><i data-lucide="maximize-2"></i></button>
            </div>
          </div>
          <div class="interaction-hint" id="sceneInstructions">Scroll or use the slider to move from natural appearance to internal anatomy. Select a hotspot for details.</div>
        </div>
        <div class="viewer-dock" aria-label="Specimen controls">
          <button class="dock-button" id="rotateToggle" type="button" aria-pressed="false"><i data-lucide="play"></i><span>Play layers</span></button>
          <div class="dock-divider"></div>
          <label class="explode-control" for="explodeRange"><span><i data-lucide="layers-3"></i> Anatomy depth</span><input id="explodeRange" type="range" min="0" max="100" value="0" step="1"><output id="explodeOutput" for="explodeRange">0%</output></label>
        </div>
      </section>
      <aside class="anatomy-panel panel-surface" aria-labelledby="anatomyHeading">
        <div class="panel-heading"><div><span class="eyebrow">Specimen guide</span><h2 id="anatomyHeading">Anatomy</h2></div><span class="part-count" id="partCount">0 parts</span></div>
        <section class="selected-card" id="selectedCard" aria-live="polite"><div class="selected-card__empty"><span class="selected-illustration" aria-hidden="true"><i data-lucide="focus"></i></span><h3>Select a structure</h3><p>Choose a visible hotspot or use the structure list.</p></div></section>
        <div class="anatomy-list-heading"><span>Visible structures</span><span id="foundCount">0 found</span></div><div class="anatomy-list" id="anatomyList"></div>
      </aside>
    </main>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <dialog class="observation-notebook" id="observationNotebook" aria-labelledby="notebookTitle"></dialog>
    <dialog class="info-dialog" id="infoDialog" aria-labelledby="infoTitle"><button class="dialog-close" data-close-dialog="infoDialog" aria-label="Close information"><i data-lucide="x"></i></button><span class="dialog-icon" aria-hidden="true"><i data-lucide="waves"></i></span><p class="eyebrow">About this lesson</p><h2 id="infoTitle">A safe first look at aquatic anatomy</h2><p id="infoDescription"></p><div class="dialog-callout"><i data-lucide="badge-check"></i><p><strong>Learning goals</strong><span id="learningGoals"></span></p></div><button class="primary-button dialog-action" data-close-dialog="infoDialog">Continue lesson</button></dialog>
    <dialog class="references-dialog" id="referencesDialog" aria-labelledby="referencesTitle"><button class="dialog-close" data-close-dialog="referencesDialog" aria-label="Close references"><i data-lucide="x"></i></button><p class="eyebrow">Content foundations</p><h2 id="referencesTitle">Educational references</h2><p>Package references support the lesson. Illustrations remain simplified teaching aids.</p><div class="reference-list" id="referenceList"></div></dialog>
    <dialog class="completion-dialog" id="completionDialog" aria-labelledby="completionTitle"><span class="completion-mark" aria-hidden="true"><i data-lucide="badge-check"></i></span><p class="eyebrow">Formative activity complete</p><h2 id="completionTitle">Observation recorded</h2><p id="completionCopy"></p><div class="score-card"><span>Your score</span><strong id="scoreValue">0 / 0</strong></div><div class="completion-actions"><button class="primary-button" id="reviewLesson" type="button"><i data-lucide="rotate-ccw"></i><span>Review lesson</span></button><button class="text-button" id="completionDashboard" type="button">Return to dashboard</button><button class="text-button" data-close-dialog="completionDialog" type="button">Keep exploring</button></div></dialog>
  </div>`;

const icons = { Calendar, Bell, ChartNoAxesCombined, ArrowLeft, ArrowRight, Badge, BadgeCheck, BookOpen, Check, ChevronRight, Circle, CircleDashed, CircleHelp, ClipboardList, Download, Eye, Fish, Focus, GraduationCap, Info, KeyRound, Layers3, LayoutDashboard, LockKeyhole, LogOut, Mail, Maximize2, Menu, PackageOpen, Pause, Play, RotateCcw, ScanSearch, School, ShieldCheck, Sparkles, User, Users, UsersRound, Waves, X };
const refreshIcons = () => createIcons({ icons, attrs: { 'stroke-width': 1.8, 'aria-hidden': 'true' } });
const query = (selector) => document.querySelector(selector);
const elements = {
  scene: query('#scene'), sceneLoader: query('#sceneLoader'), brandSubtitle: query('#lessonBrandSubtitle'),
  stepEyebrow: query('#stepEyebrow'), stepTitle: query('#stepTitle'), stepDescription: query('#stepDescription'),
  stepPrompt: query('#stepPrompt'), targetList: query('#targetList'), primaryAction: query('#primaryAction'),
  previousStep: query('#previousStep'), freeExplore: query('#freeExplore'), anatomyList: query('#anatomyList'),
  selectedCard: query('#selectedCard'), partCount: query('#partCount'), foundCount: query('#foundCount'),
  progressRegion: query('.header-progress'), progressLabel: query('#progressLabel'), progressPercent: query('#progressPercent'), progressBar: query('#progressBar'),
  modeBadge: query('#modeBadge'), labelsButton: query('#labelsButton'), resetView: query('#resetView'),
  fullscreenView: query('#fullscreenView'), rotateToggle: query('#rotateToggle'), explodeRange: query('#explodeRange'),
  explodeOutput: query('#explodeOutput'), viewerMode: query('#viewerMode'), viewerModeControl: query('#viewerModeControl'),
  explodeControl: query('.explode-control'), dockDivider: query('.dock-divider'), sceneInstructions: query('#sceneInstructions'),
  lessonDuration: query('#lessonDuration'), lessonSafety: query('#lessonSafety'), quiz: query('#quiz'),
  toast: query('#toast'), referenceList: query('#referenceList'), infoTitle: query('#infoTitle'),
  infoDescription: query('#infoDescription'), learningGoals: query('#learningGoals'), scoreValue: query('#scoreValue'),
  completionCopy: query('#completionCopy'),
};
const state = {
  manifest: null, record: null, anatomy: {}, steps: [], questions: [], model: null, viewerType: '2d',
  step: 0, discoveries: [], selected: null, labels: false, actionCompleted: new Set(), toastTimer: null, stepFocusTimer: null,
  motion: null,
};
const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
const safeReferenceUrl = (value) => { try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.href : '#'; } catch { return '#'; } };

function capturePartButtonFocus() {
  const activeElement = document.activeElement;
  if (!(activeElement instanceof HTMLElement)) return null;
  const button = activeElement.closest('button[data-part-id]');
  const container = button?.closest('#targetList, #anatomyList');
  if (!button || !container) return null;
  return { containerId: container.id, partId: button.dataset.partId };
}

function restorePartButtonFocus(focusTarget) {
  if (!focusTarget) return;
  const container = query(`#${focusTarget.containerId}`);
  const button = [...container.querySelectorAll('button[data-part-id]')]
    .find((candidate) => candidate.dataset.partId === focusTarget.partId);
  button?.focus({ preventScroll: true });
}

let portal;
const notebook = createObservationNotebook(query('#observationNotebook'), query('#notebookButton'));

function anatomyMap(structures) {
  return Object.fromEntries(structures.map((item) => [item.id, item]));
}

function showToast(message, tone = 'neutral') {
  clearTimeout(state.toastTimer);
  elements.toast.textContent = message;
  elements.toast.dataset.tone = tone;
  elements.toast.classList.add('is-visible');
  state.toastTimer = setTimeout(() => elements.toast.classList.remove('is-visible'), 2800);
}

async function buildViewer(type = '2d') {
  state.model?.destroy?.();
  state.viewerType = type;
  elements.scene.classList.toggle('image-scene', type === '2d');
  const callbacks = {
    onSelect: handlePartSelection,
    onDepthChange: (depth, layer) => {
      elements.explodeRange.value = String(Math.round(depth));
      elements.explodeOutput.textContent = `${Math.round(depth)}%`;
      elements.modeBadge.innerHTML = `<span></span>${escapeHtml(layer.name)}`;
      elements.modeBadge.classList.toggle('is-internal', layer.key === 'internal');
    },
    onPlaybackChange: (playing) => {
      if (state.viewerType !== 'video') return;
      elements.rotateToggle.setAttribute('aria-pressed', String(playing));
      elements.rotateToggle.querySelector('span').textContent = playing ? 'Pause video' : 'Play video';
    },
  };
  state.model = await createViewer(type, elements.scene, state.anatomy, state.manifest, callbacks);
  const isVideo = type === 'video';
  elements.scene.parentElement.classList.toggle('is-video-mode', isVideo);
  elements.labelsButton.hidden = isVideo;
  elements.explodeControl.hidden = isVideo;
  elements.dockDivider.hidden = isVideo;
  elements.explodeRange.disabled = isVideo;
  elements.modeBadge.classList.remove('is-internal');
  elements.rotateToggle.setAttribute('aria-pressed', 'false');

  if (isVideo) {
    const observationPrompt = String(state.steps[0]?.prompt || 'Observe the specimen movement')
      .split(/,\s*then\s+/i)[0]
      .replace(/[.!?]+$/, '');
    elements.modeBadge.innerHTML = '<span></span>Swimming reference · Video';
    elements.rotateToggle.querySelector('span').textContent = 'Play video';
    elements.sceneInstructions.textContent = `${observationPrompt}. The camera remains fixed.`;
    elements.scene.parentElement.setAttribute('aria-label', 'Natural swimming reference video');
  } else if (type === '3d') {
    elements.rotateToggle.querySelector('span').textContent = 'Swim motion';
    elements.explodeRange.previousElementSibling.lastChild.textContent = ' Exploded view';
    elements.sceneInstructions.textContent = 'Drag to inspect the model. Use the slider to separate internal structures.';
    elements.scene.parentElement.setAttribute('aria-label', 'Interactive three-dimensional anatomy viewer');
  } else {
    elements.rotateToggle.querySelector('span').textContent = 'Play layers';
    elements.explodeRange.previousElementSibling.lastChild.textContent = ' Anatomy depth';
    elements.sceneInstructions.textContent = 'Scroll or use the slider to move from natural appearance to internal anatomy. Select a hotspot for details.';
    elements.scene.parentElement.setAttribute('aria-label', 'Layered 2.5D anatomy viewer');
  }
  state.model.setStep(state.step);
  state.model.setLabelsVisible(state.labels);
  state.model.setHighlighted(state.steps[state.step]?.targets || []);
  await state.model.ready;
  if (isVideo) {
    const playing = !state.model.video.paused;
    elements.rotateToggle.setAttribute('aria-pressed', String(playing));
    elements.rotateToggle.querySelector('span').textContent = playing ? 'Pause video' : 'Play video';
  }
  elements.sceneLoader.classList.add('is-hidden');
  state.motion?.revealViewer(elements.scene);
}

async function openPackage(manifest, record, { viewerType = 'auto', classId = null } = {}) {
  if (!state.motion) {
    const { LessonMotion } = await import('./lessonMotion.js');
    state.motion = new LessonMotion(app);
  }
  if (!await notebook.load(manifest, { packageId: record.id, classId })) throw new Error('The previous notebook is still open. Download your entries before leaving.');
  state.manifest = manifest;
  state.record = record;
  state.anatomy = anatomyMap(manifest.contentData.anatomy.structures);
  state.steps = manifest.contentData.lesson.steps;
  state.questions = manifest.contentData.quiz.questions;
  state.step = 0;
  state.discoveries = state.steps.map(() => new Set());
  state.selected = null;
  state.labels = false;
  state.actionCompleted = new Set();
  elements.sceneLoader.classList.remove('is-hidden');
  elements.brandSubtitle.textContent = manifest.contentData.lesson.title;
  elements.lessonDuration.textContent = manifest.contentData.lesson.duration;
  elements.lessonSafety.textContent = manifest.safetyNotices.join(' ');
  elements.infoTitle.textContent = manifest.contentData.lesson.title;
  elements.infoDescription.textContent = manifest.contentData.lesson.description;
  elements.learningGoals.textContent = manifest.contentData.lesson.learningGoals.join(' · ');
  elements.referenceList.innerHTML = manifest.references.map((reference, index) => `<a href="${safeReferenceUrl(reference.url)}" target="_blank" rel="noreferrer"><span>${String(index + 1).padStart(2, '0')}</span><span><strong>${escapeHtml(reference.title)}</strong><small>${escapeHtml(reference.publisher || '')}</small></span><i data-lucide="arrow-right"></i></a>`).join('');
  const has3d = Boolean(manifest.viewer.optional3d || manifest.assets.model);
  const hasVideo = Boolean(manifest.assetRecords?.video);
  const viewerOptions = [
    ...(hasVideo ? [{ value: 'video', label: 'Swimming video' }] : []),
    { value: '2d', label: '2.5D anatomy' },
    ...(has3d ? [{ value: '3d', label: '3D anatomy' }] : []),
  ];
  elements.viewerMode.innerHTML = viewerOptions.map((option) => `<option value="${option.value}">${option.label}</option>`).join('');
  let initialViewer = viewerType;
  if (viewerType === 'auto') initialViewer = hasVideo ? 'video' : '2d';
  if (!viewerOptions.some((option) => option.value === initialViewer)) initialViewer = '2d';
  elements.viewerMode.value = initialViewer;
  elements.viewerModeControl.hidden = viewerOptions.length < 2;
  await buildViewer(initialViewer);
  renderStep();
  refreshIcons();
}

function availableParts() {
  if (!state.model) return [];
  if (state.step < 2) return state.model.externalIds;
  if (state.step < 4) return [...state.model.externalIds, ...state.model.internalIds.filter((id) => id === 'gills')];
  return [...state.model.externalIds, ...state.model.internalIds];
}

function targetComplete() {
  return (state.steps[state.step]?.targets || []).every((id) => state.discoveries[state.step].has(id));
}

function renderTargets() {
  const focusTarget = capturePartButtonFocus();
  const definition = state.steps[state.step];
  const targets = definition.targets || [];
  elements.targetList.hidden = targets.length === 0;
  elements.targetList.innerHTML = targets.map((id, index) => {
    const complete = state.discoveries[state.step].has(id);
    return `<button class="target-item ${complete ? 'is-complete' : ''}" data-part-id="${id}" type="button"><span class="target-status">${complete ? '<i data-lucide="check"></i>' : index + 1}</span><span>${escapeHtml(state.anatomy[id]?.name || id)}</span><i data-lucide="chevron-right"></i></button>`;
  }).join('');
  restorePartButtonFocus(focusTarget);
}

function renderQuiz() {
  const isQuiz = state.steps[state.step]?.kind === 'quiz';
  elements.quiz.hidden = !isQuiz;
  elements.quiz.innerHTML = isQuiz ? state.questions.map((question, index) => `<fieldset class="quiz-question"><legend><span>${index + 1}</span>${escapeHtml(question.question)}</legend><div class="quiz-options">${question.options.map((option) => `<label><input type="radio" name="${escapeHtml(question.id)}" value="${escapeHtml(option)}"><span>${escapeHtml(option)}</span></label>`).join('')}</div></fieldset>`).join('') : '';
}

let quizSaving = false;
function updatePrimaryAction() {
  const definition = state.steps[state.step];
  let disabled = ['targets', 'ordered-targets', 'action-target'].includes(definition.kind) && !targetComplete();
  if (definition.kind === 'quiz') {
    const answers = new FormData(elements.quiz);
    disabled = state.questions.some((question) => !answers.get(question.id));
    if (portal.currentUser()?.role === 'program_head') {
      elements.primaryAction.disabled = true;
      elements.primaryAction.querySelector('span').textContent = 'Review preview — no result submitted';
      return;
    }
  }
  elements.primaryAction.disabled = disabled || quizSaving;
  elements.primaryAction.querySelector('span').textContent = quizSaving ? 'Saving answers…' : definition.action;
}

function renderAnatomyList() {
  const focusTarget = capturePartButtonFocus();
  const visible = availableParts();
  const found = new Set(state.discoveries.flatMap((items) => [...items]));
  elements.partCount.textContent = `${visible.length} ${visible.length === 1 ? 'part' : 'parts'}`;
  elements.foundCount.textContent = `${visible.filter((id) => found.has(id)).length} found`;
  elements.anatomyList.innerHTML = visible.map((id) => {
    const part = state.anatomy[id];
    return `<button class="anatomy-item ${state.selected === id ? 'is-selected' : ''}" data-part-id="${id}" type="button" aria-pressed="${state.selected === id}"><span class="part-swatch" style="--part-color:${part.color}"></span><span><strong>${escapeHtml(part.name)}</strong><small>${escapeHtml(part.system)}</small></span><i data-lucide="chevron-right"></i></button>`;
  }).join('');
  restorePartButtonFocus(focusTarget);
}

function renderSelectedPart(id) {
  const part = state.anatomy[id];
  if (!part) return;
  state.selected = id;
  state.model.selectPart(id);
  elements.selectedCard.innerHTML = `<div class="selected-card__content"><div class="selected-card__heading"><span class="selected-swatch" style="--part-color:${part.color}"></span><span><small>${escapeHtml(part.system)}</small><h3>${escapeHtml(part.name)}</h3></span></div><p>${escapeHtml(part.summary)}</p><details><summary>More detail <i data-lucide="chevron-right"></i></summary><p>${escapeHtml(part.detail)}</p></details></div>`;
  state.motion?.revealPart(elements.selectedCard);
  renderAnatomyList();
}

function handlePartSelection(id) {
  if (!availableParts().includes(id)) {
    showToast('Continue the guided sequence to reveal that structure.', 'hint');
    return;
  }
  const definition = state.steps[state.step];
  if (definition.kind === 'ordered-targets') {
    const next = definition.targets[state.discoveries[state.step].size];
    if (id !== next) { showToast(`Follow the route: select ${state.anatomy[next].name} next.`, 'hint'); return; }
  }
  state.discoveries[state.step].add(id);
  renderSelectedPart(id);
  renderTargets();
  updatePrimaryAction();
  refreshIcons();
}

function renderStep(direction = 0) {
  clearTimeout(state.stepFocusTimer);
  state.stepFocusTimer = null;
  const definition = state.steps[state.step];
  elements.stepEyebrow.textContent = definition.eyebrow;
  elements.stepTitle.textContent = definition.title;
  elements.stepDescription.textContent = definition.description;
  elements.stepPrompt.textContent = definition.prompt;
  const percent = state.steps.length <= 1 ? 100 : Math.round((state.step / (state.steps.length - 1)) * 100);
  elements.progressLabel.textContent = definition.eyebrow;
  elements.progressPercent.textContent = `${percent}%`;
  elements.progressRegion.setAttribute('aria-valuemin', '0');
  elements.progressRegion.setAttribute('aria-valuemax', '100');
  elements.progressRegion.setAttribute('aria-valuenow', String(percent));
  if (state.motion) state.motion.setProgress(elements.progressBar, percent);
  else elements.progressBar.style.width = `${percent}%`;
  elements.previousStep.disabled = state.step === 0;
  state.model?.setStep(state.step);
  state.model?.setHighlighted(definition.targets || []);
  renderTargets();
  renderQuiz();
  renderAnatomyList();
  updatePrimaryAction();
  refreshIcons();
  state.motion?.revealStep([
    elements.stepEyebrow,
    elements.stepTitle,
    elements.stepDescription,
    elements.stepPrompt.closest('.task-card'),
    elements.targetList.hidden ? null : elements.targetList,
    elements.quiz.hidden ? null : elements.quiz,
  ], direction);
  if (direction !== 0) {
    const focusStepTitle = () => {
      if (![document.body, document.documentElement, elements.primaryAction, elements.previousStep].includes(document.activeElement)) return;
      elements.stepTitle.focus({ preventScroll: true });
    };
    if (state.motion && !state.motion.reduceMotion) state.stepFocusTimer = setTimeout(focusStepTitle, 500);
    else focusStepTitle();
  }
}

async function submitQuiz() {
  if (quizSaving) return;
  quizSaving = true;
  updatePrimaryAction();
  const submittedPackageId = state.manifest.id;
  const data = new FormData(elements.quiz);
  const answers = Object.fromEntries(state.questions.map((question) => [question.id, data.get(question.id)]));
  const localScore = state.questions.filter((question) => answers[question.id] === question.answer).length;
  try {
    const response = await portal.recordAttempt(state.manifest.lessonKey, {
      packageId: state.manifest.id,
      packageVersion: state.manifest.packageVersion,
      clientAttemptUuid: crypto.randomUUID(),
      source: navigator.onLine ? 'online' : 'offline',
      completedAt: new Date().toISOString(),
      answers,
    });
    if (state.manifest.id !== submittedPackageId) return;
    const result = response.result || { score: localScore, total: state.questions.length };
    elements.scoreValue.textContent = `${result.score} / ${result.total}`;
    elements.completionCopy.textContent = response.queued ? 'Your answers are stored on this device and will synchronize when you reconnect.' : 'Your answers were scored by the server and saved with this package version.';
    query('#completionDialog').showModal();
  } catch (error) { showToast(error.message, 'hint'); }
  finally { quizSaving = false; updatePrimaryAction(); }
}

elements.primaryAction.addEventListener('click', async () => {
  const definition = state.steps[state.step];
  if (definition.kind === 'quiz') { await submitQuiz(); return; }
  if (definition.kind === 'action-target') state.model.traceIncision();
  if (definition.kind === 'action') state.model.openBodyWall();
  if (state.step < state.steps.length - 1) {
    state.step += 1;
    if (state.viewerType === 'video') {
      elements.sceneLoader.classList.remove('is-hidden');
      elements.viewerMode.value = '2d';
      await buildViewer('2d');
    }
    renderStep(1);
  }
});
elements.previousStep.addEventListener('click', () => { if (state.step > 0) { state.step -= 1; renderStep(-1); } });
elements.freeExplore.addEventListener('click', async () => {
  if (state.viewerType === 'video') {
    elements.sceneLoader.classList.remove('is-hidden');
    elements.viewerMode.value = '2d';
    await buildViewer('2d');
  }
  state.labels = true;
  state.model.setLabelsVisible(true);
  elements.labelsButton.setAttribute('aria-pressed', 'true');
  showToast('Free exploration enabled.');
});
elements.labelsButton.addEventListener('click', () => { state.labels = !state.labels; state.model.setLabelsVisible(state.labels); elements.labelsButton.setAttribute('aria-pressed', String(state.labels)); });
elements.resetView.addEventListener('click', () => state.model.resetView());
elements.fullscreenView.addEventListener('click', () => state.model.toggleFullscreen());
elements.rotateToggle.addEventListener('click', () => {
  const playing = state.model.togglePlayback();
  elements.rotateToggle.setAttribute('aria-pressed', String(playing));
  elements.rotateToggle.querySelector('span').textContent = playing ? (state.viewerType === 'video' ? 'Pause video' : 'Pause') : state.viewerType === 'video' ? 'Play video' : state.viewerType === '3d' ? 'Swim motion' : 'Play layers';
});
elements.explodeRange.addEventListener('input', () => state.model.setExplode(Number(elements.explodeRange.value) / 100));
elements.viewerMode.addEventListener('change', async () => {
  elements.sceneLoader.classList.remove('is-hidden');
  try { await buildViewer(elements.viewerMode.value); renderStep(); }
  catch (error) { elements.viewerMode.value = '2d'; await buildViewer('2d'); showToast(error.message, 'hint'); }
});
document.addEventListener('click', (event) => {
  const part = event.target.closest('[data-part-id]')?.dataset.partId;
  if (part && !event.target.closest('.layered-anatomy-viewer')) handlePartSelection(part);
  const dialogId = event.target.closest('[data-close-dialog]')?.dataset.closeDialog;
  if (dialogId) query(`#${dialogId}`).close();
});
elements.quiz.addEventListener('change', updatePrimaryAction);
query('#fishDetailsButton').addEventListener('click', () => state.manifest && showFishDetails(state.manifest));
setupFishEnhancements();
query('#helpButton').addEventListener('click', () => query('#infoDialog').showModal());
query('#sourcesButton').addEventListener('click', () => query('#referencesDialog').showModal());
query('#reviewLesson').addEventListener('click', async () => {
  query('#completionDialog').close();
  state.step = 0;
  if (state.manifest.assetRecords?.video && state.viewerType !== 'video') {
    elements.sceneLoader.classList.remove('is-hidden');
    elements.viewerMode.value = 'video';
    await buildViewer('video');
  }
  renderStep();
});
query('#completionDashboard').addEventListener('click', () => { query('#completionDialog').close(); portal.openDashboard(); });
query('#portalReturnButton').addEventListener('click', () => portal.openDashboard());

portal = setupAquaPortal({ app, onOpenPackage: openPackage, onLeaveLesson: options => notebook.clear(options) });
refreshIcons();
