import { escapeHtml, titleCase } from './format.js';

const moduleExperienceControllers = new WeakMap();

function statusLabel(status) {
  return status === 'review' ? 'In faculty review' : titleCase(status);
}

function progressLabel(module) {
  if (module.progress?.completed) return 'Complete';
  if (module.progress?.overrideActive) return 'Access exception active';
  return `${module.progress?.completedSteps || 0} of ${module.progress?.totalSteps || 0} steps`;
}

function studentCard(module, { offline = false } = {}) {
  const complete = Boolean(module.progress?.completed);
  const progress = module.progress?.totalSteps
    ? Math.round(((module.progress?.completedSteps || 0) / module.progress.totalSteps) * 100)
    : 0;

  return `<article class="learning-module-card" data-learning-module-card="${module.id}">
    <div class="learning-module-card__visual" aria-hidden="true">
      <span class="module-depth-plane module-depth-plane--back"></span>
      <i data-lucide="shield-check"></i>
      <span class="module-depth-plane module-depth-plane--front"></span>
    </div>
    <div class="learning-module-card__body">
      <div class="species-card-topline"><span class="status-badge ${complete ? 'is-online' : ''}">${escapeHtml(progressLabel(module))}</span><small>v${escapeHtml(module.version)}</small></div>
      <h2>${escapeHtml(module.title)}</h2>
      <p>${escapeHtml(module.description)}</p>
      ${module.pinned ? '<p class="module-version-note"><i data-lucide="pin" aria-hidden="true"></i> Your started version remains pinned.</p>' : ''}
      <div class="module-progress-track" role="progressbar" aria-label="Module progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress}"><span style="width:${progress}%"></span></div>
      <div class="package-actions">
        <button class="portal-primary" type="button" data-open-learning-module="${module.id}">${complete ? 'Review module' : module.progress?.completedSteps ? 'Resume module' : 'Start module'}</button>
        ${offline ? `<button class="portal-secondary" type="button" data-offline-module-toggle="${module.id}"></button>` : ''}
      </div>
    </div>
  </article>`;
}

function reviewForm(module, currentUser) {
  if (module.status !== 'review' || module.approvalCurrent || String(module.importedBy) === String(currentUser?.id)) return '';

  return `<form class="module-review-form" data-module-review="${module.id}">
    <h3>Independent faculty review</h3>
    <label class="portal-field">Decision<select name="decision" required><option value="approved">Approve</option><option value="changes_requested">Request changes</option></select></label>
    <label class="portal-field">Review notes<textarea name="notes" rows="3" minlength="10" maxlength="3000" required></textarea></label>
    <label class="portal-field">Procedure evidence reference<input name="evidenceReference" maxlength="500" placeholder="Controlled SOP title, version, or faculty record"></label>
    <label class="portal-check"><input name="localSopConfirmed" type="checkbox"><span>I confirmed the content against the current faculty-approved laboratory procedure.</span></label>
    <button class="portal-secondary" type="submit">Record faculty decision</button>
  </form>`;
}

function assetForm(module) {
  if (!['draft', 'review'].includes(module.status)) return '';

  return `<details class="workflow-disclosure module-asset-disclosure"><summary><span><strong>Add an illustration or short video</strong><small>Optional draft media</small></span></summary><form class="module-asset-form" data-module-asset="${module.id}">
    <h3>Media details</h3>
    <div class="module-form-grid">
      <label class="portal-field">Short file name<input name="assetKey" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="scalpel-side-view" required><small>Use lowercase words joined with hyphens.</small></label>
      <label class="portal-field">Image or short video<input name="asset" type="file" accept="image/png,image/jpeg,image/webp,video/mp4,video/webm" required></label>
      <label class="portal-field">Source<input name="source" maxlength="500" required></label>
      <label class="portal-field">Usage licence<input name="licence" maxlength="300" required></label>
      <label class="portal-field module-form-wide">Caption for learners<textarea name="caption" rows="2" maxlength="1000" required></textarea></label>
      <label class="portal-check module-form-wide"><input name="aiGenerated" type="checkbox" value="1"><span>This media was generated with an AI tool.</span></label>
    </div>
    <button class="portal-secondary" type="submit">Check and attach media</button>
    <small>Personal metadata is removed from accepted media before it is saved.</small>
  </form></details>`;
}

function staffCard(module, role, currentUser) {
  const validated = module.tools.filter((tool) => tool.validationStatus === 'faculty_validated').length;
  return `<article class="learning-module-manager-card" data-learning-module-card="${module.id}">
    <div class="learning-module-manager-card__heading">
      <div><span class="portal-kicker">${escapeHtml(module.slug)} · v${escapeHtml(module.version)}</span><h2>${escapeHtml(module.title)}</h2></div>
      <span class="status-badge ${module.status === 'published' ? 'is-online' : ''}">${escapeHtml(statusLabel(module.status))}</span>
    </div>
    <p>${escapeHtml(module.description)}</p>
    <dl class="module-audit-grid"><div><dt>Tools</dt><dd>${module.toolCount}</dd></div><div><dt>Validated</dt><dd>${validated}</dd></div><div><dt>Assets</dt><dd>${module.assetCount}</dd></div><div><dt>Reviews</dt><dd>${module.reviewCount}</dd></div></dl>
    ${module.latestReview ? `<div class="module-review-summary"><strong>${escapeHtml(titleCase(module.latestReview.decision.replace('_', ' ')))}</strong><span>${escapeHtml(module.latestReview.reviewerRole)}</span><p>${escapeHtml(module.latestReview.notes)}</p>${module.latestReview.evidenceReference ? `<small>Evidence: ${escapeHtml(module.latestReview.evidenceReference)}</small>` : ''}</div>` : ''}
    <div class="package-actions">
      <button class="portal-secondary" type="button" data-open-learning-module="${module.id}">Preview module</button>
      ${role !== 'program_head' && module.status === 'draft' ? `<button class="portal-primary" type="button" data-submit-learning-module="${module.id}">Send for faculty review</button>` : ''}
      ${role === 'admin' && module.status === 'review' && module.approvalCurrent ? `<button class="portal-primary" type="button" data-publish-learning-module="${module.id}">Publish approved version</button>` : ''}
      ${role === 'admin' && module.status === 'published' ? `<button class="portal-secondary" type="button" data-archive-learning-module="${module.id}">Archive version</button>` : ''}
    </div>
    <details class="module-tool-audit"><summary>View all ${module.toolCount} tool checks</summary><ul>${module.tools.map((tool) => `<li><span>${escapeHtml(tool.name)}</span><small>${escapeHtml(titleCase(tool.validationStatus.replaceAll('_', ' ')))}</small></li>`).join('')}</ul></details>
    ${reviewForm(module, currentUser)}
    ${role !== 'program_head' ? assetForm(module) : ''}
  </article>`;
}

export function safetyWorkspace(modules, role, currentUser, options = {}) {
  if (!modules.length) {
    return '<div class="empty-state"><strong>No Safety &amp; Tools module is assigned</strong><span>Students receive only an independently reviewed and administrator-published version.</span></div>';
  }
  if (role === 'student') return `<div class="learning-module-grid">${modules.map((module) => studentCard(module, options)).join('')}</div>`;
  return `<div class="learning-module-manager-grid">${modules.map((module) => staffCard(module, role, currentUser)).join('')}</div>`;
}

export function promptStudio(modules) {
  const editable = modules.filter((module) => ['draft', 'review'].includes(module.status));
  if (!editable.length) return '<div class="empty-state"><strong>No editable module version</strong><span>Create a draft version before preparing media prompts.</span></div>';
  const initial = editable[0];

  return `<div class="prompt-studio-steps" aria-label="Media prompt steps"><span><b>1</b>Choose the item</span><span><b>2</b>Build the prompt</span><span><b>3</b>Copy and use it</span></div><div class="prompt-studio-layout">
    <form class="prompt-studio-form" id="mediaPromptForm">
      <div class="module-form-grid">
        <label class="portal-field">Draft module version<select name="versionId" data-prompt-version>${editable.map((module) => `<option value="${module.id}">${escapeHtml(module.title)} · v${escapeHtml(module.version)}</option>`).join('')}</select></label>
        <label class="portal-field">Laboratory item<select name="toolKey" data-prompt-tool>${initial.tools.map((tool) => `<option value="${escapeHtml(tool.key)}">${escapeHtml(tool.name)}</option>`).join('')}</select></label>
        <label class="portal-field">Output type<select name="mediaType"><option value="illustration">2.5D illustration</option><option value="video">Short-loop video</option></select></label>
        <label class="portal-field">Camera or view angle<select name="view"><option value="side">Side</option><option value="three-quarter">Three-quarter</option><option value="top">Top</option></select></label>
        <label class="portal-field">Teaching focus<select name="action"><option value="identification">Identification</option><option value="proper-handling">Proper handling</option><option value="storage-and-disposal">Storage and disposal</option></select></label>
        <label class="portal-field">Safety and PPE shown<select name="ppe"><option value="gloved-hands">Gloved hands</option><option value="full-ppe-context">Full PPE context</option><option value="tool-only">Tool only</option></select></label>
        <label class="portal-check module-form-wide"><input name="attach" type="checkbox"><span>Attach this prompt to the draft audit record.</span></label>
      </div>
      <button class="portal-primary" type="submit">Build prompt</button>
      <p class="portal-muted">AquaDissect prepares text only. It does not contact an external service or store a password or API key.</p>
    </form>
    <section class="prompt-output" aria-live="polite">
      <div><span class="portal-kicker">Ready to use</span><h2>Prompt ready to copy</h2></div>
      <label class="portal-field prompt-output__label" for="mediaPromptOutput">Generated media prompt</label>
      <textarea id="mediaPromptOutput" rows="18" readonly placeholder="Choose the options, then build the prompt."></textarea>
      <button class="portal-secondary" type="button" data-copy-media-prompt disabled>Copy prompt</button>
      <p id="mediaPromptStatus" class="portal-muted"></p>
    </section>
  </div>`;
}

function toolMarkup(tool, active) {
  return `<button class="module-hotspot ${active ? 'is-active' : ''}" type="button" data-module-tool="${escapeHtml(tool.key)}" aria-pressed="${active}">
    <span>${escapeHtml(tool.name)}</span>
  </button>`;
}

function stepContent(step, manifest, selectedKey) {
  const tools = (step.toolKeys || []).map((key) => manifest.tools.find((tool) => tool.key === key)).filter(Boolean);
  const selected = tools.find((tool) => tool.key === selectedKey) || tools[0] || null;
  return {
    tools,
    selected,
    html: `<span class="portal-kicker">${escapeHtml(step.title)}</span>
      ${selected ? `<div class="handling-callout"><h2>${escapeHtml(selected.name)}</h2><p>${escapeHtml(selected.purpose)}</p><dl><div><dt>Proper handling</dt><dd>${escapeHtml(selected.handling)}</dd></div><div><dt>Safety precaution</dt><dd>${escapeHtml(selected.safety)}</dd></div><div><dt>Procedure use</dt><dd>${escapeHtml(selected.applicableStep)}</dd></div></dl></div>` : '<div class="handling-callout"><strong>Faculty-directed preparation</strong><p>Review this step before continuing. The current instructor direction and approved local procedure remain authoritative.</p></div>'}`,
  };
}

function activityMarkup(activity) {
  return `<form class="module-activity" data-module-activity>
    <div class="module-activity-heading"><span class="portal-kicker">Unlimited retries</span><h2>Tool-selection check</h2><p>Submit once to complete this required step. Your score is retained for feedback and does not control completion.</p></div>
    ${(activity.questions || []).map((question, index) => `<fieldset><legend><span>${index + 1}</span>${escapeHtml(question.question)}</legend>${question.options.map((option) => `<label><input type="radio" name="${escapeHtml(question.id)}" value="${escapeHtml(option)}" required><span>${escapeHtml(option)}</span></label>`).join('')}</fieldset>`).join('')}
    <button class="portal-primary" type="submit">Submit formative activity</button>
    <p class="module-activity-result" role="status" aria-live="assertive" aria-atomic="true" tabindex="-1"></p>
  </form>`;
}

export function createModuleExperience(container, manifest, record, callbacks = {}) {
  moduleExperienceControllers.get(container)?.abort();
  const eventController = new AbortController();
  moduleExperienceControllers.set(container, eventController);
  const { signal } = eventController;
  const steps = manifest.steps || [];
  const completed = new Set(record.progress?.completedStepIds || []);
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let index = Math.max(0, steps.findIndex((step) => !completed.has(step.id)));
  if (index < 0) index = 0;
  let selectedKey = steps[index]?.toolKeys?.[0] || null;

  container.innerHTML = `<section class="safety-experience" tabindex="-1">
    <header class="safety-experience__header"><button class="portal-secondary" type="button" data-close-learning-module>← Back to Safety &amp; Tools</button><div><span class="portal-kicker">${escapeHtml(manifest.module?.category || 'Pre-laboratory')} · v${escapeHtml(manifest.moduleVersion)}</span><h2 data-module-title tabindex="-1">${escapeHtml(manifest.module?.title || record.title)}</h2><p>${escapeHtml(manifest.statusNotice || '')}</p></div></header>
    <section class="module-step-summary" data-module-step-summary aria-label="Module progress and current step" aria-live="polite" aria-atomic="true">
      <div class="module-step-summary__progress"><span data-module-step-position></span><strong data-module-progress-percent></strong></div>
      <div class="module-step-summary__titles"><p><span>Current step</span><strong data-module-current-title></strong></p><p data-module-next-step><span>Next step</span><strong data-module-next-title></strong></p></div>
    </section>
    <div class="module-step-progress" aria-label="Required module steps">${steps.map((step, stepIndex) => `<button type="button" data-module-step="${stepIndex}" aria-label="Open step ${stepIndex + 1}: ${escapeHtml(step.title)}" class="${completed.has(step.id) ? 'is-complete' : ''}"><span>${stepIndex + 1}</span><small>${escapeHtml(step.title)}</small></button>`).join('')}</div>
    <label class="portal-field module-step-select">Go to step<select data-module-step-select>${steps.map((step, stepIndex) => `<option value="${stepIndex}">${stepIndex + 1}. ${escapeHtml(step.title)}</option>`).join('')}</select></label>
    <p class="module-step-intro" data-module-step-intro></p>
    <div class="module-tool-picker"><span class="portal-kicker" data-module-picker-label>Choose an item</span><div class="module-hotspots" data-module-hotspots></div></div>
    <div class="module-learning-layout">
      <figure class="module-25d-viewer ${reducedMotion ? 'is-reduced-motion' : ''}" tabindex="0" aria-label="Laboratory tool illustration. Use the left and right arrow keys to move between steps.">
        <div class="module-scene" data-module-scene>
          <span class="module-scene__plane module-scene__plane--back" data-depth="1"></span>
          <span class="module-scene__tray" data-depth="2"></span>
          <div class="module-scene__tool" data-depth="4"></div>
          <span class="module-scene__highlight" data-depth="5"></span>
        </div>
        <div class="module-viewer-controls"><button type="button" data-module-motion aria-pressed="false" ${reducedMotion ? 'disabled' : ''}>Animate image</button><span data-module-motion-status aria-live="polite">${reducedMotion ? 'Reduced motion enabled' : 'Still image'}</span></div>
        <figcaption data-module-caption></figcaption>
      </figure>
      <article class="module-step-copy" data-module-step-copy></article>
    </div>
    <div class="module-step-actions"><button class="portal-secondary" type="button" data-module-previous>Previous</button><button class="portal-primary" type="button" data-module-complete-step>Done — continue</button></div>
    <div data-module-activity-slot></div>
  </section>`;

  const experience = container.querySelector('.safety-experience');
  const viewer = container.querySelector('.module-25d-viewer');
  const scene = container.querySelector('[data-module-scene]');
  const motionStatus = container.querySelector('[data-module-motion-status]');
  const stepCopy = container.querySelector('[data-module-step-copy]');
  const hotspots = container.querySelector('[data-module-hotspots]');
  const activitySlot = container.querySelector('[data-module-activity-slot]');
  const moduleTitle = container.querySelector('[data-module-title]');

  function renderStep(focusTarget = null) {
    const activeElement = document.activeElement;
    const focusedToolKey = activeElement?.closest?.('[data-module-tool]')?.dataset.moduleTool || null;
    const focusedStepIndex = activeElement?.closest?.('[data-module-step]')?.dataset.moduleStep ?? null;
    const step = steps[index];
    selectedKey = step.toolKeys?.includes(selectedKey) ? selectedKey : step.toolKeys?.[0] || null;
    const content = stepContent(step, manifest, selectedKey);
    stepCopy.innerHTML = content.html;
    container.querySelector('[data-module-step-intro]').textContent = step.caption;
    hotspots.innerHTML = content.tools.map((tool) => toolMarkup(tool, tool.key === content.selected?.key)).join('');
    scene.querySelector('.module-scene__tool').innerHTML = content.selected
      ? `<span>${escapeHtml(content.selected.name)}</span><small>Tool reference</small>`
      : '<span>Safety first</span>';
    const illustration = manifest.assetRecords?.[content.selected?.imageAssetKey];
    const iconFallback = scene.querySelector('.module-scene__tool').innerHTML;
    viewer.classList.toggle('has-tool-image', Boolean(illustration?.url));
    if (illustration?.url && illustration.mimeType === 'image/webp') {
      const image = document.createElement('img');
      image.className = 'module-tool-image';
      image.alt = content.selected.name;
      image.width = 1254;
      image.height = 1254;
      image.decoding = 'async';
      image.addEventListener('error', () => {
        if (!image.isConnected) return;
        scene.querySelector('.module-scene__tool').innerHTML = iconFallback;
        viewer.classList.remove('has-tool-image');
      }, { once: true });
      image.src = illustration.url;
      const toolNode = scene.querySelector('.module-scene__tool');
      toolNode.replaceChildren(image);
      const label = document.createElement('span');
      label.textContent = content.selected.name;
      toolNode.append(label);
    }
    container.querySelector('[data-module-caption]').textContent = content.selected
      ? content.selected.name
      : step.caption;
    container.querySelector('[data-module-previous]').disabled = index === 0;
    const completedCount = steps.filter((requiredStep) => completed.has(requiredStep.id)).length;
    const percentComplete = steps.length ? Math.round((completedCount / steps.length) * 100) : 0;
    const nextStep = steps[index + 1] || null;
    container.querySelector('[data-module-step-position]').textContent = `Step ${index + 1} of ${steps.length}`;
    container.querySelector('[data-module-progress-percent]').textContent = callbacks.onProgress ? `${percentComplete}% complete` : 'Staff preview';
    container.querySelector('[data-module-current-title]').textContent = step.title;
    const nextStepSummary = container.querySelector('[data-module-next-step]');
    nextStepSummary.hidden = false;
    container.querySelector('[data-module-next-title]').textContent = nextStep?.title || 'Module completion';
    const completionButton = container.querySelector('[data-module-complete-step]');
    completionButton.textContent = completed.has(step.id) ? (index === steps.length - 1 ? 'Step viewed' : 'Continue') : 'Done — continue';
    if (!callbacks.onProgress) completionButton.textContent = index < steps.length - 1 ? 'Next step' : 'Preview finished';
    completionButton.disabled = step.id === 'tool-selection';
    activitySlot.innerHTML = step.id === 'tool-selection' ? activityMarkup(manifest.activity || {}) : '';
    if (!callbacks.onProgress && activitySlot.querySelector('button[type="submit"]')) activitySlot.querySelector('button[type="submit"]').textContent = 'Preview activity';
    container.querySelectorAll('[data-module-step]').forEach((button, stepIndex) => {
      button.classList.toggle('is-current', stepIndex === index);
      button.classList.toggle('is-complete', completed.has(steps[stepIndex].id));
      if (stepIndex === index) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current');
    });
    container.querySelector('[data-module-step-select]').value = String(index);
    container.querySelector('.module-tool-picker').hidden = content.tools.length === 0;
    container.querySelector('[data-module-picker-label]').textContent = `Choose an item · ${content.tools.length} available`;
    container.querySelector('.module-learning-layout').hidden = step.id === 'tool-selection';
    completionButton.hidden = step.id === 'tool-selection';

    const requestedToolKey = focusTarget
      ? (focusTarget.type === 'tool' ? focusTarget.key : null)
      : focusedToolKey;
    const requestedStepIndex = focusTarget
      ? (focusTarget.type === 'step' ? String(focusTarget.index) : null)
      : focusedStepIndex;
    const focusNode = requestedToolKey
      ? [...container.querySelectorAll('[data-module-tool]')].find((button) => button.dataset.moduleTool === requestedToolKey)
      : [...container.querySelectorAll('[data-module-step]')].find((button) => button.dataset.moduleStep === requestedStepIndex);
    focusNode?.focus({ preventScroll: true });
  }

  async function saveProgress(answers = null) {
    const result = await callbacks.onProgress?.({
      completedStepIds: [...completed],
      ...(answers ? { answers, clientAttemptUuid: crypto.randomUUID() } : {}),
      completedAt: new Date().toISOString(),
    });
    if (result?.progress) {
      completed.clear();
      result.progress.completedStepIds.forEach((id) => completed.add(id));
    }
    return result;
  }

  function toggleMotion() {
    const playing = !viewer.classList.contains('is-playing');
    viewer.classList.toggle('is-playing', playing);
    const button = container.querySelector('[data-module-motion]');
    button.textContent = playing ? 'Pause animation' : 'Animate image';
    button.setAttribute('aria-pressed', String(playing));
    motionStatus.textContent = playing ? 'Animation playing' : 'Still image';
  }

  container.addEventListener('change', (event) => {
    if (!event.target.matches('[data-module-step-select]')) return;
    index = Number(event.target.value);
    selectedKey = null;
    renderStep();
  }, { signal });

  container.addEventListener('click', async (event) => {
    if (event.target.closest('[data-close-learning-module]')) {
      eventController.abort();
      moduleExperienceControllers.delete(container);
      return;
    }
    const stepButton = event.target.closest('[data-module-step]');
    if (stepButton) { index = Number(stepButton.dataset.moduleStep); selectedKey = null; renderStep({ type: 'step', index }); return; }
    const toolButton = event.target.closest('[data-module-tool]');
    if (toolButton) { selectedKey = toolButton.dataset.moduleTool; renderStep({ type: 'tool', key: selectedKey }); return; }
    if (event.target.closest('[data-module-previous]')) { index = Math.max(0, index - 1); selectedKey = null; renderStep(); return; }
    if (event.target.closest('[data-module-motion]') && !reducedMotion) toggleMotion();
    if (event.target.closest('[data-module-complete-step]')) {
      const step = steps[index];
      completed.add(step.id);
      try {
        await saveProgress();
        if (signal.aborted) return;
        if (index < steps.length - 1) index += 1;
        selectedKey = null;
        renderStep();
      } catch (error) {
        if (!signal.aborted) callbacks.onError?.(error);
      }
    }
  }, { signal });

  container.addEventListener('submit', async (event) => {
    if (!event.target.matches('[data-module-activity]')) return;
    event.preventDefault();
    const activityForm = event.target;
    if (activityForm.getAttribute('aria-busy') === 'true') return;
    const submitButton = activityForm.querySelector('button[type="submit"]');
    const submitLabel = submitButton?.textContent || 'Submit formative activity';
    activityForm.setAttribute('aria-busy', 'true');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Saving activity…';
    }
    const answers = Object.fromEntries(new FormData(activityForm));
    completed.add('tool-selection');
    const resultNode = activityForm.querySelector('.module-activity-result');
    try {
      const result = await saveProgress(answers);
      if (signal.aborted) return;
      renderStep();
      const score = result?.attempt;
      const currentResult = activitySlot.querySelector('.module-activity-result');
      if (currentResult) {
        currentResult.textContent = score ? `Score retained: ${score.score} of ${score.total}. Module complete.` : callbacks.onProgress ? 'Activity saved. Module complete.' : 'Preview only. No learner result was saved.';
        currentResult.focus({ preventScroll: true });
      }
      if (callbacks.onProgress) callbacks.onComplete?.(result);
    } catch (error) {
      if (signal.aborted) return;
      completed.delete('tool-selection');
      resultNode.textContent = error.message;
      resultNode.focus({ preventScroll: true });
    } finally {
      activityForm.removeAttribute('aria-busy');
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = submitLabel;
      }
    }
  }, { signal });

  viewer.addEventListener('keydown', (event) => {
    if (event.target !== viewer) return;
    if (event.key === 'ArrowRight' && index < steps.length - 1) { event.preventDefault(); index += 1; selectedKey = null; renderStep(); }
    if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); index -= 1; selectedKey = null; renderStep(); }
    if (event.key === ' ' && !reducedMotion) {
      event.preventDefault();
      toggleMotion();
    }
  }, { signal });
  if (!reducedMotion) {
    viewer.addEventListener('pointermove', (event) => {
      const bounds = viewer.getBoundingClientRect();
      scene.style.setProperty('--parallax-x', `${((event.clientX - bounds.left) / bounds.width - 0.5) * 10}px`);
      scene.style.setProperty('--parallax-y', `${((event.clientY - bounds.top) / bounds.height - 0.5) * 8}px`);
    }, { signal });
    viewer.addEventListener('pointerleave', () => { scene.style.setProperty('--parallax-x', '0px'); scene.style.setProperty('--parallax-y', '0px'); }, { signal });
  }
  renderStep();
  moduleTitle.focus({ preventScroll: true });
}
