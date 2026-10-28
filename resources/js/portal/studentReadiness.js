import { escapeHtml as e } from './format.js';

export function readinessSections(classes = [], packages = [], modules = []) {
  return classes.filter(section => section.status === 'active').map(section => {
    const gate = section.prerequisiteStatus;
    const pending = modules.some(module => String(module.classId) === String(section.id) && module.progress?.pendingSync);
    let safety;
    if (!gate) safety = { status: 'Check online', detail: 'Reconnect to confirm the required safety activity and your saved status.' };
    else if (gate.required === 0) safety = { status: 'Not assigned', detail: 'No required Safety & Tools prerequisite is assigned to this section. Check your teacher’s instructions.' };
    else if (gate.satisfied) safety = gate.overridden
      ? { status: 'Exception active', detail: 'An authorized prerequisite exception permits access. This does not mean the safety activity was completed.' }
      : { status: 'Complete', detail: 'The server confirms completion of the required safety activity.' };
    else safety = pending
      ? { status: 'Waiting to synchronize', detail: 'Device progress is not yet confirmed by the server. Reconnect and retry synchronization.' }
      : { status: 'To do', detail: 'Finish every required step and submit the tool-selection activity in Safety & Tools.' };
    const assignments = (section.assignments || []).filter(item => item.speciesSlug === 'rabbitfish');
    const lessons = assignments.map(assignment => {
      const record = packages.find(item => String(item.id) === String(assignment.packageId));
      if (!record || !['published', 'archived'].includes(record.status)) return { status: 'Unavailable', detail: 'The assigned version is not available to your account. Ask your teacher to check publication and assignment.' };
      if (assignment.locked || gate?.satisfied === false) return { status: 'Locked', detail: 'The required Safety & Tools prerequisite for this section is not yet satisfied.' };
      return { status: 'Ready to open', detail: `Assigned Rabbitfish lesson · v${record.version}`, packageId: record.id, classId: section.id };
    });
    if (!lessons.length) lessons.push({ status: 'Waiting for your teacher', detail: 'No published Rabbitfish lesson is assigned to this section. Your teacher must arrange publication and assignment.' });
    return { id: section.id, name: section.name, safety, lessons };
  });
}

export function studentReadinessView(tutorials = {}, classes = [], { user = null, packages = [], learningModules = [], offline = false } = {}) {
  const version = tutorials.guides?.find(guide => guide.id === 'student-orientation')?.version ?? 1;
  const progress = tutorials.progress?.find(item => item.guide_key === 'student-orientation' && Number(item.version) === Number(version));
  const viewed = Boolean(progress?.viewed_at);
  const guideUrl = `${window.AQUADISSECT_ROUTES.about}?guide=student-orientation`;
  const sections = readinessSections(classes, packages, learningModules);
  const step = (title, status, detail, action = '') => `<li class="readiness-step"><div><strong>${e(title)}</strong><p>${e(detail)}</p></div><span class="status-badge">${e(offline ? 'Check online' : status)}</span>${action ? `<div class="package-actions">${action}</div>` : ''}</li>`;
  const account = user?.mustChangePassword || user?.status !== 'active'
    ? step('Account setup', 'To do', 'Finish the required password change before opening course activities.')
    : step('Account setup', 'Complete', 'You are signed in with an active account and have completed the required password setup.');
  const orientation = step('Orientation', viewed ? 'Viewed' : progress ? 'In progress' : 'Not started', viewed ? 'Orientation guide viewed. Guide viewing is separate from safety completion.' : 'Five short steps explain navigation, content availability and support.', `<a class="portal-secondary button-link" href="${e(guideUrl)}">${viewed ? 'Review orientation' : progress ? 'Continue orientation' : 'Start orientation'}</a>`);
  const sectionsView = sections.length ? sections.map(section => `<div class="readiness-section"><h3>${e(section.name)}</h3><ol class="readiness-list">${step('Safety & Tools', section.safety.status, section.safety.detail, '<button type="button" class="portal-secondary" data-portal-page="safety">Open Safety &amp; Tools</button>')}${section.lessons.map(lesson => step('Rabbitfish', lesson.status, lesson.detail, lesson.packageId && !offline ? `<button type="button" class="portal-secondary" data-open-package="${e(lesson.packageId)}" data-class-id="${e(lesson.classId)}">Open Rabbitfish</button>` : '')).join('')}</ol></div>`).join('') : `<ol class="readiness-list">${step('Section needs checking', 'Waiting for your administrator', 'No active section is linked to this account. Ask your administrator to verify enrollment. You can complete orientation now.')}</ol>`;
  return `<section class="workspace-panel student-orientation-path" id="studentOrientationPath" aria-labelledby="studentOrientationTitle"><span class="portal-kicker">Your next steps</span><h2 id="studentOrientationTitle">What must I finish?</h2>${offline ? '<p role="status">Showing saved information. Connect and refresh to confirm account, enrollment and completion status.</p>' : '<p>Check your account and orientation, then follow the activities assigned to each section.</p>'}<ol class="readiness-list">${account}${orientation}</ol>${sectionsView}<div class="package-actions"><button class="portal-secondary" type="button" data-portal-page="classes">Check my section</button></div><p class="portal-muted">Orientation does not unlock unpublished lessons or complete the safety prerequisite. Draft notes, guide viewing and course access do not establish research consent or learning outcomes.</p></section>`;
}
