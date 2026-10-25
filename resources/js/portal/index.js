import { setupBulkRoster } from './bulkRoster.js';
import '../../css/bulk-roster.css';
import { setupFishCarousels } from './fishAngles.js';
import { focusedSpecies, focusedClasses } from './speciesFocus.js';
import { setupRosterTables, cardsToTable } from './rosterTables.js';
import { setupWorkspaceShell, setupWorkspacePage } from './workspace.js';
import { setupTutorials, startHereCard, setupStaffRoles } from './tutorials.js';
import { request } from './api.js';
import { adminDashboard, adminRosterView, classCards, instructorDashboard, managerView, speciesLibrary, studentDashboard, studentOrientationPath } from './dashboards.js';
import { escapeHtml, titleCase } from './format.js';
import { createModuleExperience, promptStudio, safetyWorkspace } from './safetyTools.js';
import {
  downloadedVersionFor, downloadLearningModule, downloadPackage, isDownloaded, isLearningModuleDownloaded,
  listDownloadedLearningModules, listDownloadedPackages, removeDownloadedLearningModule, removeDownloadedPackage,
  submitOrQueueAttempt, submitOrQueueModuleProgress, synchronizeQueuedAttempts, synchronizeQueuedModuleProgress,
} from '../offlinePackages.js';
import { setupProgressiveWebApp } from '../pwa.js';

const bootstrap = window.AQUADISSECT_BOOTSTRAP || {};
const visibleSpecies = window.AQUADISSECT_VISIBLE_SPECIES || ['rabbitfish'];
const focusSpecies = records => focusedSpecies(records, visibleSpecies);
const focusClasses = (classes, species) => focusedClasses(classes, species, visibleSpecies);
const offlineContextKey = 'aquadissect.offline-context.v2';
function saveOfflineContext(data) {
  const learner = data.user?.role === 'student';
  const offlineCatalog = records => (records || []).filter(r=>r.status==='published').map(({latestReview, importedBy, events, ...record})=>record);
  try {
    localStorage.setItem(offlineContextKey, JSON.stringify({
      user: learner ? { id: data.user.id, firstName: 'Learner', lastName: '', role: 'student', status: 'active' } : null,
      species: learner ? offlineCatalog(data.species) : [],
      learningModules: learner ? offlineCatalog(data.learningModules) : [],
      classes: learner ? (data.classes || []).map(c => ({ id:c.id, name:c.name, status:c.status, assignments:c.assignments })) : [],
    }));
  } catch { /* Storage restrictions must not prevent online learning. */ }
}
if (bootstrap.offlineShell) {
  try { Object.assign(bootstrap, JSON.parse(localStorage.getItem(offlineContextKey) || '{}')); } catch { /* Empty offline context. */ }
} else if (bootstrap.user) {
  saveOfflineContext(bootstrap);
}

bootstrap.classes = focusClasses(bootstrap.classes, bootstrap.species);
bootstrap.species = focusSpecies(bootstrap.species);

const dashboardCopy = {
  program_head: ['Program Head workspace', 'Program overview', 'Review progress across MB 304L sections and independently review learning content.'],
  student: ['Student workspace', 'Welcome back', 'Choose your next activity below. AquaDissect keeps your saved progress with the lesson.'],
  instructor: ['Instructor workspace', 'Welcome', 'Open a class section to review students, safety status, and learning activities.'],
  admin: ['Administrator workspace', 'System overview', 'Review the people, class sections, and learning content that need attention.'],
};
const pageLabels = {
  'source-handover': 'Source Structure',
  calendar: 'Calendar', notifications: 'Notifications', reports: 'Learning reports',
  home: 'Home', library: 'Species lessons', classes: 'Class sections', roster: 'People & sections', assignments: 'Class activities',
  safety: 'Safety & Tools', downloads: 'Offline access', manager: 'Species content', 'prompt-studio': 'Create media prompts', access: 'Account roles', about: 'Help & guide',
};
const rolePageLabels = {
  student: { library: 'My lessons', classes: 'My class' },
};

function pageLabel(name, role = null) {
  return rolePageLabels[role]?.[name] || pageLabels[name] || 'AquaDissect';
}

function requireElement(root, selector) {
  const element = root.querySelector(selector);
  if (!element) throw new Error(`AquaDissect portal element is missing: ${selector}`);
  return element;
}

export function setupAquaPortal({ app, onOpenPackage, onLeaveLesson = () => {} }) {
  const portal = requireElement(document, '#aquaPortal');
  const elements = {
    skipLink: requireElement(document, '#primarySkipLink'),
    signIn: requireElement(portal, '#portalSignIn'),
    userCard: requireElement(portal, '#portalUserCard'),
    userInitials: requireElement(portal, '#portalUserInitials'),
    userName: requireElement(portal, '#portalUserName'),
    userRole: requireElement(portal, '#portalUserRole'),
    menuButton: requireElement(portal, '#dashboardMenuButton'),
    installButton: requireElement(portal, '#installAppButton'),
    sidebarScrim: requireElement(portal, '#portalSidebarScrim'),
    sidebar: requireElement(portal, '#portalSidebar'),
    portalMain: requireElement(portal, '#portal-main'),
    roleLabel: requireElement(portal, '#dashboardRoleLabel'),
    pageLabel: requireElement(portal, '#dashboardPageLabel'),
    loginForm: portal.querySelector('#loginForm'),
    loginMessage: portal.querySelector('#loginMessage'),
    registrationForm: portal.querySelector('#registrationForm'),
    registrationMessage: portal.querySelector('#registrationMessage'),
    activationForm: portal.querySelector('#activationForm'),
    activationMessage: portal.querySelector('#activationMessage'),
    registrationPassword: portal.querySelector('#registrationPassword'),
    registrationPasswordConfirmation: portal.querySelector('#registrationPasswordConfirmation'),
    passwordRequirements: portal.querySelector('#passwordRequirements'),
    dashboardKicker: portal.querySelector('#dashboardKicker'),
    dashboardGreeting: portal.querySelector('#dashboardGreeting'),
    dashboardSummary: portal.querySelector('#dashboardSummary'),
    dashboardContent: portal.querySelector('#dashboardContent'),
    library: portal.querySelector('#speciesLibraryContent'),
    classes: portal.querySelector('#classesContent'),
    roster: portal.querySelector('#rosterContent'),
    assignments: portal.querySelector('#assignmentsContent'),
    downloads: portal.querySelector('#downloadsContent'),
    manager: portal.querySelector('#speciesManagerContent'),
    safety: portal.querySelector('#safetyToolsContent') || portal.querySelector('#downloadsContent'),
    safetyExperience: portal.querySelector('#safetyModuleExperience'),
    promptStudio: portal.querySelector('#mediaPromptStudioContent'),
    sidebarSpeciesCount: requireElement(portal, '#sidebarSpeciesCount'),
    systemStatus: requireElement(portal, '#dashboardSystemStatus'),
    confirmDialog: requireElement(portal, '#portalConfirmDialog'),
    confirmTitle: requireElement(portal, '#portalConfirmTitle'),
    confirmDescription: requireElement(portal, '#portalConfirmDescription'),
    confirmButton: requireElement(portal, '#portalConfirmButton'),
    toast: requireElement(portal, '#portalToast'),
  };
  let sidebarReturnFocus = null;
  let initialPage = true;
  const state = {
    currentUser: bootstrap.user || null,
    latestResult: bootstrap.lessonResult || null,
    pendingUsers: Array.isArray(bootstrap.pendingUsers) ? [...bootstrap.pendingUsers] : [],
    species: Array.isArray(bootstrap.species) ? [...bootstrap.species] : [],
    classes: Array.isArray(bootstrap.classes) ? [...bootstrap.classes] : [],
    learningModules: Array.isArray(bootstrap.learningModules) ? [...bootstrap.learningModules] : [],
    adminRoster: null,
    rosterPreview: [],
    rosterFile: null,
    rosterClassId: null,
    activationCodes: [],
    activeClassId: null,
    openLearningModuleId: null,
    rosterError: null,
    toastTimer: null,
    toastReturnFocus: null,
    currentPage: 'home',
  };
  app.hidden = true;

  function dismissToast() {
    clearTimeout(state.toastTimer);
    const shouldRestoreFocus = document.activeElement === elements.toast;
    const returnFocus = state.toastReturnFocus;
    elements.toast.classList.remove('is-visible', 'is-error');
    elements.toast.setAttribute('role', 'status');
    elements.toast.setAttribute('aria-live', 'polite');
    elements.toast.tabIndex = -1;
    elements.toast.removeAttribute('aria-label');
    state.toastReturnFocus = null;
    if (shouldRestoreFocus) {
      const target = returnFocus?.isConnected && returnFocus.getClientRects().length ? returnFocus : currentPageHeading();
      if (target && !target.matches('button, a[href], input, select, textarea, [tabindex]')) target.setAttribute('tabindex', '-1');
      target?.focus({ preventScroll: true });
    }
  }

  function showToast(message, { tone = 'status', persistent = false } = {}) {
    dismissToast();
    if (tone === 'error' && document.activeElement instanceof HTMLElement) state.toastReturnFocus = document.activeElement;
    elements.toast.textContent = message;
    elements.toast.classList.toggle('is-error', tone === 'error');
    elements.toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');
    elements.toast.setAttribute('aria-live', tone === 'error' ? 'assertive' : 'polite');
    elements.toast.tabIndex = tone === 'error' ? 0 : -1;
    elements.toast.setAttribute('aria-label', tone === 'error' ? `${message}. Press Escape to dismiss.` : message);
    elements.toast.classList.add('is-visible');
    if (!persistent) state.toastTimer = setTimeout(dismissToast, 4000);
  }

  function confirmPortalAction({ title, description, confirmLabel = 'Confirm' }) {
    if (typeof elements.confirmDialog.showModal !== 'function') return Promise.resolve(window.confirm(description));
    elements.confirmTitle.textContent = title;
    elements.confirmDescription.textContent = description;
    elements.confirmButton.textContent = confirmLabel;
    elements.confirmDialog.returnValue = '';
    elements.confirmDialog.showModal();
    return new Promise((resolve) => {
      elements.confirmDialog.addEventListener('close', () => resolve(elements.confirmDialog.returnValue === 'confirm'), { once: true });
    });
  }

  function setMessage(element, message = '') {
    element.textContent = message;
    element.hidden = !message;
  }

  function setSkipDestination(destination) {
    const lessonOpen = destination === 'lesson';
    elements.skipLink.href = lessonOpen ? '#lesson-content' : '#portal-main';
    elements.skipLink.textContent = lessonOpen ? 'Skip to lesson' : 'Skip to main content';
  }

  function focusActivePage(name) {
    requestAnimationFrame(() => {
      const panel = portal.querySelector(`[data-portal-panel="${name}"]`);
      const heading = panel?.querySelector('h1, h2');
      if (!heading) return;
      heading.setAttribute('tabindex', '-1');
      heading.focus({ preventScroll: true });
    });
  }

  function findDataElement(attribute, value, root = portal) {
    return [...root.querySelectorAll(`[${attribute}]`)]
      .find((element) => element.getAttribute(attribute) === String(value));
  }

  function currentPageHeading() {
    return portal.querySelector(`[data-portal-panel="${state.currentPage}"] h1, [data-portal-panel="${state.currentPage}"] h2`);
  }

  function focusAfterRender(resolveTarget) {
    const attemptFocus = () => {
      if (![document.body, document.documentElement].includes(document.activeElement)) return;
      let target = resolveTarget?.();
      if (!target?.getClientRects().length) target = currentPageHeading();
      if (!target) return;
      if (!target.matches('button, a[href], input, select, textarea, [tabindex]')) target.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
    };
    requestAnimationFrame(attemptFocus);
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) setTimeout(attemptFocus, 380);
  }

  function formFocusResolver(form) {
    const formId = form.id;
    const dataEntry = Object.entries(form.dataset).find(([, value]) => value);
    const packageId = form.closest('[data-package-card]')?.dataset.packageCard;
    const moduleId = form.closest('[data-learning-module-card]')?.dataset.learningModuleCard;
    const classId = form.closest('[data-class-card]')?.dataset.classCard;
    return () => {
      if (packageId) return findDataElement('data-package-card', packageId)?.querySelector('h2');
      if (moduleId) return findDataElement('data-learning-module-card', moduleId)?.querySelector('h2');
      if (classId) return findDataElement('data-class-card', classId)?.querySelector('h2, h3');
      const nextForm = [...portal.querySelectorAll('form')].find((candidate) => (
        (formId && candidate.id === formId)
        || (dataEntry && candidate.dataset[dataEntry[0]] === dataEntry[1])
      ));
      return nextForm?.querySelector('h1, h2, h3') || nextForm?.querySelector('button[type="submit"]');
    };
  }

  function updateNetworkStatus() {
    const online = navigator.onLine;
    elements.systemStatus.classList.toggle('is-offline', !online);
    elements.systemStatus.querySelector('.dashboard-system-status-label').textContent = online ? 'Online' : 'Offline';
  }

  function updateSession() {
    bootstrap.user = state.currentUser;
    if (state.currentUser) saveOfflineContext({ user: state.currentUser, species: state.species, learningModules: state.learningModules, classes: state.classes });
    const user = state.currentUser;
    const authenticated = Boolean(user);
    portal.classList.toggle('is-authenticated', authenticated);
    elements.signIn.hidden = authenticated;
    elements.userCard.hidden = !authenticated;
    portal.querySelectorAll('[data-role-nav]').forEach((item) => {
      item.hidden = !user || !item.dataset.roleNav.split(',').includes(user.role);
    });
    if (user) {
      elements.userInitials.textContent = `${user.firstName?.[0] || ''}${user.lastName?.[0] || ''}`.toUpperCase();
      elements.userName.textContent = `${user.firstName} ${user.lastName}`;
      elements.userRole.textContent = titleCase(user.role.replaceAll('_', ' '));
      elements.roleLabel.textContent = `${titleCase(user.role.replaceAll('_', ' '))} workspace`;
    } else {
      elements.roleLabel.textContent = 'AquaDissect';
    }
    const published = state.species.filter((item) => item.status === 'published').length;
    elements.sidebarSpeciesCount.textContent = `${published} lesson${published === 1 ? '' : 's'} ready`;
    portal.querySelectorAll('[data-nav-label]').forEach((label) => {
      label.textContent = pageLabel(label.dataset.navLabel, user?.role);
    });
  }

  function setSidebarOpen(open, { restoreFocus = true } = {}) {
    const mobile = window.matchMedia('(max-width: 780px)').matches;
    const drawerMode = mobile && portal.classList.contains('is-authenticated');
    const wasOpen = portal.classList.contains('is-sidebar-open');
    const shouldOpen = Boolean(open && drawerMode);
    const focusInsideSidebar = elements.sidebar.contains(document.activeElement);
    if (!shouldOpen && drawerMode && focusInsideSidebar) {
      elements.menuButton.focus({ preventScroll: true });
    }
    portal.classList.toggle('is-sidebar-open', shouldOpen);
    document.body.classList.toggle('portal-navigation-open', shouldOpen);
    elements.portalMain.inert = shouldOpen;
    elements.sidebar.inert = drawerMode && !shouldOpen;
    if (drawerMode) elements.sidebar.setAttribute('aria-hidden', String(!shouldOpen));
    else elements.sidebar.removeAttribute('aria-hidden');
    const desktopExpanded = !mobile && !portal.classList.contains('is-desktop-collapsed');
    elements.menuButton.setAttribute('aria-expanded', String(shouldOpen || desktopExpanded));
    elements.menuButton.setAttribute('aria-label', shouldOpen ? 'Close navigation' : desktopExpanded ? 'Collapse navigation' : 'Open navigation');
    if (shouldOpen) {
      sidebarReturnFocus = document.activeElement;
      requestAnimationFrame(() => elements.sidebar.querySelector('.portal-nav button:not([hidden])')?.focus());
    } else if (wasOpen && restoreFocus) {
      (sidebarReturnFocus instanceof HTMLElement ? sidebarReturnFocus : elements.menuButton).focus();
      sidebarReturnFocus = null;
    }
  }

  function handleSidebarKeydown(event) {
    if (!portal.classList.contains('is-sidebar-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      setSidebarOpen(false);
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...elements.sidebar.querySelectorAll('button:not([disabled]):not([hidden]), a[href]')]
      .filter((item) => item.getClientRects().length > 0);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function assignmentView() {
    const published = state.species.filter((item) => item.status === 'published');
    const activeClasses = state.classes.filter((item) => item.status === 'active');
    if (!activeClasses.length) return '<div class="empty-state"><strong>No assigned section is available</strong><span>Ask the administrator to assign you to an active MB 304L section.</span></div>';
    if (!published.length) return '<div class="empty-state assignment-empty-state"><strong>No published species lesson is ready</strong><span>Complete the review and publication steps in Species content before assigning a lesson.</span><button class="portal-secondary" type="button" data-portal-page="manager">Open Species content</button></div>';
    return `<div class="assignment-grid">${activeClasses.map((course) => {
      const currentAssignments = course.assignments || [];
      const assignmentsBySpecies = new Map(currentAssignments.map((assignment) => [String(assignment.speciesId), assignment]));
      const selectedPackage = published.find((item) => currentAssignments.some((assignment) => String(assignment.packageId) === String(item.id))) || published[0];
      const selectedAssignment = assignmentsBySpecies.get(String(selectedPackage.speciesId));
      const dueValue = selectedAssignment?.dueAt ? String(selectedAssignment.dueAt).slice(0, 10) : '';
      const currentSummary = currentAssignments.length
        ? `<ul>${currentAssignments.map((assignment) => {
          const assignmentDue = assignment.dueAt ? String(assignment.dueAt).slice(0, 10) : '';
          const dueLabel = assignmentDue ? new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(`${assignmentDue}T00:00:00`)) : 'No due date';
          return `<li><span><strong>${escapeHtml(assignment.commonName || 'Species lesson')}</strong><small>Version ${escapeHtml(assignment.version || '')}</small></span><small>${escapeHtml(dueLabel)}</small></li>`;
        }).join('')}</ul>`
        : '<p>No lesson assigned yet.</p>';
      return `
      <form class="assignment-card" data-assignment-form="${course.id}">
        <span class="portal-kicker">${escapeHtml(course.code)}</span><h2>${escapeHtml(course.name)}</h2>
        <div class="assignment-current-summary"><span>Current activities</span>${currentSummary}</div>
        <label class="portal-field">Published species lesson<select name="speciesPackageId" data-assignment-package required>${published.map((item) => {
          const current = assignmentsBySpecies.get(String(item.speciesId));
          const currentDue = current?.dueAt ? String(current.dueAt).slice(0, 10) : '';
          return `<option value="${item.id}" data-current-due="${escapeHtml(currentDue)}" ${String(item.id) === String(selectedPackage.id) ? 'selected' : ''}>${escapeHtml(item.commonName)} · v${escapeHtml(item.version)}</option>`;
        }).join('')}</select></label>
        <label class="portal-field">Due date (optional)<input name="dueAt" type="date" value="${escapeHtml(dueValue)}"></label>
        <button class="portal-primary" type="submit">${currentAssignments.length ? 'Save activity' : 'Assign lesson'}</button>
        <small>Students who already started remain on their saved lesson version unless you intentionally assign a new version.</small>
      </form>`;
    }).join('')}</div>`;
  }

  function renderPage(name = state.currentPage) {
    if (!portal.querySelector(`[data-portal-panel="${name}"]`)) return;
    if (name === 'home') {
      const user = state.currentUser;
      if (!user) return;
      const [kicker, greeting, summary] = dashboardCopy[user.role];
      elements.dashboardKicker.textContent = kicker;
      elements.dashboardGreeting.textContent = user.role === 'admin' ? greeting : `${greeting}, ${user.firstName}`;
      elements.dashboardSummary.textContent = summary;
      elements.dashboardContent.innerHTML = user.role === 'student'
        ? studentDashboard(state.species)
        : user.role === 'instructor'
          ? instructorDashboard(state.species, state.classes)
          : user.role === 'program_head'
            ? `<div class="workspace-panel"><h2>Academic oversight</h2><p>${state.classes.length} sections available for review. Content decisions require an independent review; administrators handle publication.</p><div class="package-actions"><button type="button" class="portal-primary" data-portal-page="classes">Review sections</button><button type="button" class="portal-secondary" data-portal-page="reports">View learning reports</button><button type="button" class="portal-secondary" data-portal-page="manager">Review species content</button><button type="button" class="portal-secondary" data-portal-page="safety">Review Safety &amp; Tools</button></div></div>`
            : adminDashboard(state.pendingUsers, state.species, state.classes);
      elements.dashboardContent.insertAdjacentHTML('afterbegin', user.role === 'student' ? studentOrientationPath(bootstrap.tutorials, state.classes) : startHereCard(bootstrap.tutorials));
    }
    if (name === 'library') elements.library.innerHTML = speciesLibrary(state.species, { singleSpecies: state.currentUser?.role !== 'student' });
    if (name === 'safety') {
      elements.safety.hidden = false;
      elements.safetyExperience.hidden = true;
      elements.safety.innerHTML = safetyWorkspace(state.learningModules, state.currentUser?.role, state.currentUser, { classes: state.classes });
      if (state.currentUser?.role !== 'student') cardsToTable(elements.safety, '.learning-module-manager-card', 'safety-records', 'Safety module versions', state.learningModules);
    }
    if (name === 'classes') {
      elements.classes.innerHTML = classCards(state.classes, state.currentUser?.role);
      if (state.currentUser?.role !== 'student') cardsToTable(elements.classes, '.class-card', 'classes', 'Class sections', state.classes);
    }
    if (name === 'roster' && elements.roster) {
      elements.roster.innerHTML = adminRosterView(state.adminRoster, state.rosterPreview, state.activationCodes, state.rosterError);
      setupRosterTables(elements.roster);
      setupBulkRoster(elements.roster, state.adminRoster?.classes || [], state.currentUser?.id, async result => {
        state.activationCodes = result.activationCodes || [];
        await refreshRoster();
      });
    }
    if (name === 'assignments') {
      elements.assignments.innerHTML = assignmentView();
      cardsToTable(elements.assignments, '.assignment-card', 'activities', 'Class activities', state.classes.filter(c=>c.status==='active'));
    }
    if (name === 'downloads') {
      elements.downloads.innerHTML = `${bootstrap.offlineShell ? '<p class="page-guidance">Downloaded learning materials on this device. Reconnect and sign in to synchronize your own work.</p>' : ''}${bootstrap.offlineShell && state.classes.length > 1 ? `<label class="portal-field">Study section<select id="offlineSection"><option value="">Select a section before opening a lesson</option>${state.classes.map(c=>`<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('')}</select></label>` : ''}<div class="download-summary"><strong>${focusSpecies(listDownloadedPackages()).length + listDownloadedLearningModules().length}</strong><span>learning items saved on this device</span></div><div class="portal-page-title portal-page-title--compact"><span class="portal-kicker">Prerequisite</span><h2>Safety module</h2></div>${safetyWorkspace(state.learningModules, 'student', state.currentUser, { offline: true })}<div class="portal-page-title portal-page-title--compact"><span class="portal-kicker">Assigned anatomy</span><h2>Species lessons</h2></div><div class="species-library-grid">${speciesLibrary(state.species.filter((item) => item.status === 'published'), { offline: true })}</div>`;
      state.species.forEach((item) => {
        const button = elements.downloads.querySelector(`[data-offline-toggle="${item.id}"]`);
        if (button) {
          const downloadedVersion = downloadedVersionFor(item.slug);
          button.textContent = isDownloaded(item.id, item.version)
            ? 'Remove download'
            : downloadedVersion ? `Update offline copy · v${downloadedVersion} → v${item.version}` : 'Download for offline use';
        }
      });
      state.learningModules.forEach((module) => {
        const button = elements.downloads.querySelector(`[data-offline-module-toggle="${module.id}"]`);
        if (button) button.textContent = isLearningModuleDownloaded(module.id, module.version) ? 'Remove download' : 'Download for offline use';
      });
    }
    if (name === 'manager') {
      elements.manager.innerHTML = managerView(state.species, state.currentUser?.role, state.currentUser);
      cardsToTable(elements.manager, '.species-card', 'content', 'Species content versions');
    }
    if (name === 'prompt-studio') elements.promptStudio.innerHTML = promptStudio(state.learningModules);

    const activePanel = portal.querySelector(`[data-portal-panel="${name}"]`);
    activePanel?.querySelectorAll('[data-card-video]').forEach((video) => {
      const poster = video.poster;
      const button = video.closest('.species-card-image').querySelector('[data-card-motion]');
      const syncButton = () => {
        if (!button) return;
        button.textContent = video.paused ? 'Play' : 'Pause';
        button.setAttribute('aria-label', video.paused ? 'Play fish animation' : 'Pause fish animation');
      };
      const fallback = () => {
        if (video.hidden) return;
        video.hidden = true;
        if (button) button.hidden = true;
        const image = document.createElement('img');
        image.src = poster;
        image.dataset.cardFallback = '';
        image.hidden = video.closest('[data-fish-carousel]')?.querySelector('select')?.value !== '0';
        image.alt = 'Rabbitfish specimen illustration';
        video.after(image);
      };
      video.addEventListener('error', fallback, { once: true });
      video.querySelector('source')?.addEventListener('error', fallback, { once: true });
      video.addEventListener('loadeddata', () => video.removeAttribute('poster'), { once: true });
      video.addEventListener('play', syncButton);
      video.addEventListener('pause', syncButton);
      button?.addEventListener('click', () => {
        if (video.paused) video.play().catch(syncButton);
        else video.pause();
      });
      const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
      motion.addEventListener('change', () => { if (motion.matches) video.pause(); });
      if (!motion.matches) video.play().catch(syncButton);
    });
    setupFishCarousels(activePanel);
    import('../portalMotion.js')
      .then(({ revealPortalPage }) => revealPortalPage(activePanel))
      .catch(() => undefined);
  }

  function showPage(name) {
    if (!state.currentUser && !(bootstrap.offlineShell && name === 'downloads') && !['login', 'register', 'activate', 'access', 'about'].includes(name)) name = 'login';
    if (name === 'roster' && state.currentUser?.role !== 'admin') name = state.currentUser ? 'home' : 'login';
    if (name === 'prompt-studio' && !['instructor', 'admin'].includes(state.currentUser?.role)) name = state.currentUser ? 'home' : 'login';
    if (!portal.querySelector(`[data-portal-panel="${name}"]`)) {
      window.location.assign(window.AQUADISSECT_ROUTES[name] || window.AQUADISSECT_ROUTES.home);
      return;
    }
    state.currentPage = name;
    portal.querySelectorAll('[data-portal-panel]').forEach((panel) => { panel.hidden = panel.dataset.portalPanel !== name; });
    portal.querySelectorAll('.portal-nav [data-portal-page]').forEach((button) => {
      const active = button.dataset.portalPage === name;
      button.classList.toggle('is-active', active);
      if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
    elements.pageLabel.textContent = pageLabel(name, state.currentUser?.role);
    setSkipDestination('portal');
    setSidebarOpen(false);
    renderPage(name);
    if (name === 'roster' && !state.adminRoster) void refreshRoster();
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    if (!initialPage) focusActivePage(name);
    initialPage = false;
  }

  function signIn(payload) {
    onLeaveLesson();
    bootstrap.tutorials = payload.tutorials || { guides: [], progress: [] };
    state.currentUser = payload.user;
    state.latestResult = payload.lessonResult || null;
    state.pendingUsers = payload.pendingUsers || [];
    state.species = focusSpecies(payload.species);
    state.classes = focusClasses(payload.classes, payload.species);
    state.learningModules = payload.learningModules || [];
    state.adminRoster = null;
    state.rosterError = null;
    state.activationCodes = [];
    updateSession();
    showPage('home');
    showToast(`Signed in as ${titleCase(payload.user.role)}`);
  }

  async function refreshData() {
    const [speciesResponse, classesResponse, modulesResponse] = await Promise.all([request('/api/species'), request('/api/classes'), request('/api/learning-modules')]);
    state.species = focusSpecies(speciesResponse.species);
    state.classes = classesResponse.classes || [];
    state.learningModules = modulesResponse.learningModules || [];
    updateSession();
    renderPage();
  }

  async function refreshRoster() {
    if (state.currentUser?.role !== 'admin') return;
    state.rosterError = null;
    if (!state.adminRoster) renderPage('roster');
    try {
      state.adminRoster = await request('/api/admin/roster');
      state.classes = state.adminRoster.classes || [];
      renderPage();
    } catch (error) {
      state.rosterError = error.message;
      renderPage('roster');
      showToast(error.message, { tone: 'error', persistent: true });
    }
  }

  async function signOut() {
    onLeaveLesson();
    try {
      await request('/api/logout', { method: 'POST' });
      localStorage.removeItem(offlineContextKey);
      state.currentUser = null;
      state.latestResult = null;
      state.pendingUsers = [];
      state.species = [];
      state.classes = [];
      state.learningModules = [];
      state.adminRoster = null;
      state.rosterError = null;
      state.activationCodes = [];
      state.rosterPreview = [];
      state.rosterFile = null;
      state.activeClassId = null;
      updateSession();
      showPage('login');
      showToast('Signed out');
    } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
  }

  async function openPackage(id, viewerType = 'auto', selectedClassId = null) {
    const record = state.species.find((item) => String(item.id) === String(id));
    if (!record) return;
    const matchingClasses = state.currentUser?.role === 'student'
      ? state.classes.filter((item) => item.status === 'active' && item.assignments.some((assignment) => String(assignment.packageId) === String(id)))
      : [];
    if (bootstrap.offlineShell && matchingClasses.length > 1 && !selectedClassId) {
      selectedClassId = Number(portal.querySelector('#offlineSection')?.value) || null;
      if (!matchingClasses.some(c=>c.id===selectedClassId)) { showToast('Select the study section before opening this lesson.'); return; }
    }
    if (matchingClasses.length > 1 && !selectedClassId) {
      showPage('classes');
      showToast('Open this lesson from the correct section below.');
      return;
    }
    state.activeClassId = selectedClassId || matchingClasses[0]?.id || null;
    try {
      const classQuery = state.activeClassId ? `?classId=${state.activeClassId}` : '';
      const response = await request(`/api/species/${record.slug}/packages/${record.id}/manifest${classQuery}`);
      await onOpenPackage(response.package, record, { viewerType });
      portal.hidden = true;
      app.hidden = false;
      setSkipDestination('lesson');
      document.body.classList.add('lesson-is-open');
      window.scrollTo({ top: 0, behavior: 'instant' });
      requestAnimationFrame(() => {
        const heading = app.querySelector('#stepTitle');
        heading?.setAttribute('tabindex', '-1');
        heading?.focus({ preventScroll: true });
      });
      window.dispatchEvent(new Event('resize'));
    } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
  }

  async function openLearningModule(id) {
    const record = state.learningModules.find((item) => String(item.id) === String(id));
    if (!record) return;
    try {
      const classQuery = state.currentUser?.role === 'student' ? `?classId=${record.classId}` : '';
      const response = await request(`/api/learning-modules/${record.id}/manifest${classQuery}`);
      state.openLearningModuleId = record.id;
      elements.safety.hidden = true;
      elements.safetyExperience.hidden = false;
      createModuleExperience(elements.safetyExperience, response.module, record, {
        onProgress: state.currentUser?.role === 'student' ? async (progress) => {
          const result = await submitOrQueueModuleProgress(record.id, {
            ...progress,
            classId: record.classId,
            totalSteps: response.module.requiredStepIds?.length || 0,
            requiredStepIds: response.module.requiredStepIds || [],
          });
          record.progress = { ...record.progress, ...result.progress, pendingSync: Boolean(result.queued) };
          saveOfflineContext({ user: state.currentUser, species: state.species, learningModules: state.learningModules, classes: state.classes });
          if (result.queued) showToast('Progress saved offline and queued for synchronization');
          return result;
        } : undefined,
        onComplete: async (result) => {
          showToast(result?.queued
            ? 'Steps and activity saved on this device. Reconnect to confirm completion and lesson access.'
            : 'Safety & Tools saved. Check Species lessons for your assigned activities.');
          if (!result?.queued && navigator.onLine) {
            try { await refreshData(); }
            catch { showToast('Safety progress is saved. Reopen the page to refresh lesson availability.', { tone: 'error', persistent: true }); }
          }
        },
        onError: (error) => showToast(error.message, { tone: 'error', persistent: true }),
      });
      setSkipDestination('portal');
      window.scrollTo({ top: 0, behavior: 'instant' });
    } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
  }

  function openDashboard() {
    onLeaveLesson();
    app.hidden = true;
    portal.hidden = false;
    document.body.classList.remove('lesson-is-open');
    showPage('home');
  }

  async function lifecycleAction(action, packageId) {
    const prefix = state.currentUser.role === 'admin' ? '/api/admin' : '/api/instructor';
    try {
      await request(`${prefix}/species-packages/${packageId}/${action}`, { method: 'POST' });
      await refreshData();
      focusAfterRender(() => findDataElement('data-package-card', packageId)?.querySelector('h2'));
      const messages = {
        submit: 'Species lesson sent for independent faculty review',
        publish: 'Approved species lesson published',
        activate: 'Active lesson version updated',
        archive: 'Species lesson archived with its history retained',
        restore: 'Species lesson restored and waiting for a fresh faculty review',
      };
      showToast(messages[action] || 'Species lesson updated');
    } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
  }

  async function moduleLifecycleAction(action, versionId, body = undefined) {
    const prefix = action === 'publish' || action === 'archive' ? '/api/admin' : '/api/staff';
    try {
      const response = await request(`${prefix}/learning-module-versions/${versionId}/${action}`, { method: 'POST', body });
      await refreshData();
      focusAfterRender(() => findDataElement('data-learning-module-card', versionId)?.querySelector('h2'));
      const detail = action === 'publish' ? ` ${response.assignments || 0} section assignment(s) updated; ${response.legacyOverrides || 0} legacy exception(s) retained.` : '';
      showToast(`Learning module ${action} completed.${detail}`);
    } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
  }

  async function handlePortalClick(event) {
    const visibilityButton = event.target.closest('[data-password-toggle]');
    if (visibilityButton) {
      const input = requireElement(portal, `#${visibilityButton.dataset.passwordToggle}`);
      const reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      visibilityButton.textContent = reveal ? 'Hide' : 'Show';
      visibilityButton.setAttribute('aria-pressed', String(reveal));
      input.focus({ preventScroll: true });
      return;
    }
    const navigation = event.target.closest('[data-portal-page]');
    if (navigation) showPage(navigation.dataset.portalPage);
    if (event.target.closest('[data-retry-roster]')) await refreshRoster();
    const demoButton = event.target.closest('[data-demo-role]');
    if (demoButton) {
      setMessage(elements.loginMessage);
      try { signIn(await request('/api/login', { method: 'POST', body: { email: demoButton.dataset.demoEmail, password: demoButton.dataset.demoPassword } })); }
      catch (error) { setMessage(elements.loginMessage, error.message); }
    }
    if (event.target.closest('[data-portal-signout]')) await signOut();
    const openButton = event.target.closest('[data-open-package]');
    const openId = openButton?.dataset.openPackage;
    if (openId) await openPackage(openId, openButton.dataset.viewerMode || 'auto', openButton.dataset.classId ? Number(openButton.dataset.classId) : null);
    const openModuleButton = event.target.closest('[data-open-learning-module]');
    if (openModuleButton) await openLearningModule(openModuleButton.dataset.openLearningModule);
    if (event.target.closest('[data-close-learning-module]')) {
      const returningModuleId = state.openLearningModuleId;
      state.openLearningModuleId = null;
      elements.safetyExperience.hidden = true;
      elements.safety.hidden = false;
      renderPage();
      const restoreModuleTriggerFocus = () => {
        if (![document.body, document.documentElement].includes(document.activeElement)) return;
        elements.safety.querySelector(`[data-open-learning-module="${returningModuleId}"]`)?.focus({ preventScroll: true });
      };
      requestAnimationFrame(restoreModuleTriggerFocus);
      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) setTimeout(restoreModuleTriggerFocus, 380);
    }
    const approvalId = event.target.closest('[data-approve-account]')?.dataset.approveAccount;
    if (approvalId && state.currentUser?.role === 'admin') {
      try {
        await request(`/api/admin/users/${approvalId}/approve`, { method: 'POST' });
        state.pendingUsers = state.pendingUsers.filter((item) => String(item.id) !== approvalId);
        renderPage();
        focusAfterRender(() => elements.dashboardGreeting);
        showToast('Instructor account approved');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
    }
    const offeringAction = event.target.closest('[data-offering-status]');
    if (offeringAction && state.currentUser?.role === 'admin') {
      if (offeringAction.dataset.offeringStatus === 'archived' && !await confirmPortalAction({ title: 'Archive this section?', description: 'The section and its learning records will be retained, but it will no longer appear as an active class.', confirmLabel: 'Archive section' })) return;
      try {
        await request(`/api/admin/offerings/${offeringAction.dataset.offeringId}/status`, { method: 'PATCH', body: { status: offeringAction.dataset.offeringStatus } });
        await refreshRoster();
        focusAfterRender(() => findDataElement('data-class-card', offeringAction.dataset.offeringId)?.querySelector('h2, h3'));
        showToast(`Section ${offeringAction.dataset.offeringStatus}`);
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
    }
    const activationId = event.target.closest('[data-issue-activation]')?.dataset.issueActivation;
    if (activationId && state.currentUser?.role === 'admin') {
      try {
        const result = await request(`/api/admin/students/${activationId}/activation`, { method: 'POST' });
        state.activationCodes = [{ email: result.email, code: result.code }];
        renderPage('roster');
        focusAfterRender(() => findDataElement('data-issue-activation', activationId) || currentPageHeading());
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
    }
    if (event.target.closest('[data-import-reviewed-roster]') && state.rosterFile) {
      const body = new FormData();
      body.append('classId', state.rosterClassId);
      body.append('roster', state.rosterFile);
      try {
        const result = await request('/api/admin/roster/import', { method: 'POST', body });
        state.activationCodes = result.activationCodes || [];
        state.rosterPreview = [];
        state.rosterFile = null;
        await refreshRoster();
        focusAfterRender(currentPageHeading);
        showToast('Roster imported. Existing accounts were reused.');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
    }
    for (const action of ['submit', 'publish', 'activate', 'archive', 'restore']) {
      const id = event.target.closest(`[data-${action}-package]`)?.dataset[`${action}Package`];
      if (!id) continue;
      const record = state.species.find((item) => String(item.id) === String(id));
      const confirmations = {
        publish: ['Publish this lesson?', `${record?.commonName || 'This lesson'} will become available for assignment after publication.`, 'Publish lesson'],
        activate: ['Make this the active version?', `New assignments will use ${record?.commonName || 'this lesson'} version ${record?.version || ''}.`, 'Make active'],
        archive: ['Archive this lesson?', 'The lesson history will be retained, but this version will no longer be available for new assignments.', 'Archive lesson'],
        restore: ['Restore this lesson?', 'The lesson will return as a draft and must complete a fresh faculty review before publication.', 'Restore lesson'],
      };
      if (confirmations[action]) {
        const [title, description, confirmLabel] = confirmations[action];
        if (!await confirmPortalAction({ title, description, confirmLabel })) return;
      }
      await lifecycleAction(action, id);
      return;
    }
    for (const action of ['submit', 'publish', 'archive']) {
      const button = event.target.closest(`[data-${action}-learning-module]`);
      if (!button) continue;
      const classIds = action === 'publish' ? [...button.closest('[data-learning-module-card]').querySelectorAll('[data-module-publish-section]:checked')].map(input => Number(input.value)) : [];
      if (action === 'publish') {
        const selected = state.classes.filter(item => classIds.includes(Number(item.id)));
        const destinations = selected.map(item => `${item.name} (${item.academicYear}, semester ${item.semester})`).join('; ');
        const description = selected.length ? `Publish and assign this required module to: ${destinations}. Other section assignments remain unchanged. Started learners keep their version.` : 'Publish this approved version without creating or changing any section assignments.';
        if (!await confirmPortalAction({ title: 'Publish this Safety & Tools version?', description, confirmLabel: 'Publish module' })) return;
      }
      if (action === 'archive' && !await confirmPortalAction({ title: 'Archive this module version?', description: 'Its history will be retained and the version will no longer be available for new assignments.', confirmLabel: 'Archive version' })) return;
      await moduleLifecycleAction(action, button.dataset[`${action}LearningModule`], action === 'publish' ? { classIds } : undefined);
      return;
    }
    const revokeOverride = event.target.closest('[data-revoke-module-override]');
    if (revokeOverride) {
      const classId = revokeOverride.closest('[data-class-card]')?.dataset.classCard;
      if (!await confirmPortalAction({ title: 'Revoke this prerequisite exception?', description: 'The learner or section will need to complete Safety & Tools before opening assigned anatomy lessons.', confirmLabel: 'Revoke exception' })) return;
      try {
        await request(`/api/staff/learning-module-overrides/${revokeOverride.dataset.revokeModuleOverride}`, { method: 'DELETE' });
        await refreshData();
        focusAfterRender(() => findDataElement('data-class-card', classId)?.querySelector('h2, h3'));
        showToast('Prerequisite exception revoked with its history retained');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
    }
    const copyPrompt = event.target.closest('[data-copy-media-prompt]');
    if (copyPrompt) {
      const output = requireElement(portal, '#mediaPromptOutput');
      try { await navigator.clipboard.writeText(output.value); showToast('Media prompt copied'); }
      catch { output.select(); document.execCommand('copy'); showToast('Media prompt copied'); }
    }
    const offlineButton = event.target.closest('[data-offline-toggle]');
    const offlineId = offlineButton?.dataset.offlineToggle;
    if (offlineId) {
      const record = state.species.find((item) => String(item.id) === offlineId);
      try {
        if (isDownloaded(record.id, record.version)) {
          await removeDownloadedPackage(record.id);
          showToast('Offline package removed from this device');
        } else {
          offlineButton.disabled = true;
          offlineButton.textContent = 'Verifying download…';
          await downloadPackage(record);
          showToast('Package verified and ready offline');
        }
        renderPage('downloads');
        focusAfterRender(() => findDataElement('data-offline-toggle', offlineId));
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); renderPage('downloads'); }
    }
    const offlineModuleButton = event.target.closest('[data-offline-module-toggle]');
    if (offlineModuleButton) {
      const record = state.learningModules.find((item) => String(item.id) === offlineModuleButton.dataset.offlineModuleToggle);
      try {
        if (isLearningModuleDownloaded(record.id, record.version)) {
          await removeDownloadedLearningModule(record.id);
          showToast('Offline Safety & Tools module removed from this device');
        } else {
          offlineModuleButton.disabled = true;
          offlineModuleButton.textContent = 'Verifying download…';
          await downloadLearningModule(record);
          showToast('Safety & Tools verified and ready offline');
        }
        renderPage('downloads');
        focusAfterRender(() => findDataElement('data-offline-module-toggle', offlineModuleButton.dataset.offlineModuleToggle));
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); renderPage('downloads'); }
    }
  }

  async function handlePortalSubmit(event) {
    if (event.target.closest('#workspaceTool')) return;
    if (event.target.closest('#portalConfirmDialog') || event.target.matches('[data-module-activity]')) return;
    const form = event.target;
    const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    const shouldRestoreFocus = form.contains(document.activeElement);
    const resolveReturnFocus = formFocusResolver(form);
    const originalContent = submitter instanceof HTMLButtonElement ? submitter.innerHTML : null;
    form.setAttribute('aria-busy', 'true');
    if (submitter) {
      submitter.disabled = true;
      if (submitter instanceof HTMLButtonElement) submitter.textContent = 'Please wait…';
    }
    try {
      await handlePortalSubmitAction(event);
    } finally {
      form.removeAttribute('aria-busy');
      if (submitter) {
        submitter.disabled = false;
        if (submitter instanceof HTMLButtonElement && originalContent !== null) submitter.innerHTML = originalContent;
      }
      window.lucide?.createIcons();
      if (shouldRestoreFocus && !form.isConnected) focusAfterRender(resolveReturnFocus);
    }
  }

  async function handlePortalSubmitAction(event) {
    if (event.target.matches('[data-student-email]')) {
      event.preventDefault();
      const form = event.target;
      const data = new FormData(form);
      const email = String(data.get('email')).trim().toLowerCase();
      const identityNumber = String(data.get('identityNumber'));
      const message = form.querySelector('[data-email-message]');
      setMessage(message);
      if (!await confirmPortalAction({ title: 'Save this student email?', description: `Institutional ID ${identityNumber}: ${email}. Confirm the address with the student before saving. The account stays pending and no activation code is issued.`, confirmLabel: 'Save email' })) return;
      try {
        const result = await request(`/api/admin/students/${form.dataset.studentEmail}/email`, { method: 'PATCH', body: { email, identityNumber } });
        await refreshRoster();
        focusAfterRender(() => findDataElement('data-issue-activation', form.dataset.studentEmail) || currentPageHeading());
        showToast(result.message);
      } catch (error) { setMessage(message, error.message); }
      return;
    }
    if (event.target === elements.loginForm) {
      event.preventDefault();
      const data = new FormData(elements.loginForm);
      try { signIn(await request('/api/login', { method: 'POST', body: { email: String(data.get('email')).trim().toLowerCase(), password: String(data.get('password')), remember: data.get('remember') === 'on' } })); }
      catch (error) { setMessage(elements.loginMessage, error.message); }
      return;
    }
    if (event.target === elements.registrationForm) {
      event.preventDefault();
      setMessage(elements.registrationMessage);
      const data = new FormData(elements.registrationForm);
      const googleRegistration = elements.registrationForm.dataset.googleRegistration === 'true';
      const email = String(data.get('email')).trim().toLowerCase();
      const password = String(data.get('password'));
      const passwordConfirmation = String(data.get('passwordConfirmation'));
      if (!googleRegistration && password !== passwordConfirmation) {
        setMessage(elements.registrationMessage, 'The password confirmation does not match.');
        elements.registrationPasswordConfirmation.focus();
        return;
      }
      try {
        const response = await request(googleRegistration ? '/api/register/google' : '/api/register', { method: 'POST', body: { firstName: String(data.get('firstName')).trim(), lastName: String(data.get('lastName')).trim(), email, identityNumber: String(data.get('identityNumber')).trim(), role: 'instructor', password: googleRegistration ? undefined : password, agreement: data.get('agreement') === 'on' } });
        elements.registrationForm.reset();
        updatePasswordGuidance();
        showPage('login');
        showToast(response.message);
      } catch (error) { setMessage(elements.registrationMessage, error.message); }
      return;
    }
    if (event.target === elements.activationForm) {
      event.preventDefault();
      setMessage(elements.activationMessage);
      const data = new FormData(event.target);
      if (data.get('password') !== data.get('passwordConfirmation')) {
        setMessage(elements.activationMessage, 'The password confirmation does not match.');
        return;
      }
      try {
        const response = await request('/api/activate', { method: 'POST', body: { email: String(data.get('email')).trim().toLowerCase(), code: String(data.get('code')).trim(), password: String(data.get('password')), password_confirmation: String(data.get('passwordConfirmation')) } });
        elements.activationForm.reset();
        showPage('login');
        showToast(response.message);
      } catch (error) { setMessage(elements.activationMessage, error.message); }
      return;
    }
    if (event.target.matches('[data-species-review]')) {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        await request(`/api/staff/species-packages/${event.target.dataset.speciesReview}/review`, {
          method: 'POST',
          body: {
            decision: data.get('decision'),
            notes: data.get('notes'),
            evidenceReference: data.get('evidenceReference') || null,
            curriculumAlignmentConfirmed: data.get('curriculumAlignmentConfirmed') === 'on',
            safetyReviewConfirmed: data.get('safetyReviewConfirmed') === 'on',
          },
        });
        await refreshData();
        showToast('Faculty decision recorded in the species lesson history');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.matches('[data-module-review]')) {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        await request(`/api/staff/learning-module-versions/${event.target.dataset.moduleReview}/review`, {
          method: 'POST',
          body: {
            decision: data.get('decision'),
            notes: data.get('notes'),
            evidenceReference: data.get('evidenceReference') || null,
            localSopConfirmed: data.get('localSopConfirmed') === 'on',
          },
        });
        await refreshData();
        showToast('Faculty decision recorded in the module audit history');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.matches('[data-module-asset]')) {
      event.preventDefault();
      const body = new FormData(event.target);
      body.set('aiGenerated', body.get('aiGenerated') ? '1' : '0');
      try {
        await request(`/api/staff/learning-module-versions/${event.target.dataset.moduleAsset}/assets`, { method: 'POST', body });
        await refreshData();
        showToast('Media sanitized and attached for faculty review');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.id === 'mediaPromptForm') {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        const response = await request('/api/staff/media-prompts/preview', {
          method: 'POST',
          body: {
            versionId: Number(data.get('versionId')),
            toolKey: data.get('toolKey'),
            mediaType: data.get('mediaType'),
            view: data.get('view'),
            action: data.get('action'),
            ppe: data.get('ppe'),
            attach: data.get('attach') === 'on',
          },
        });
        const output = requireElement(portal, '#mediaPromptOutput');
        output.value = response.prompt;
        requireElement(portal, '[data-copy-media-prompt]').disabled = false;
        requireElement(portal, '#mediaPromptStatus').textContent = response.recordId ? 'Prompt attached to the draft audit record.' : 'Preview only; this prompt was not retained.';
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.matches('[data-section-override], [data-learner-override]')) {
      event.preventDefault();
      const data = new FormData(event.target);
      const classId = event.target.dataset.sectionOverride || event.target.dataset.learnerOverride;
      const moduleId = event.target.dataset.moduleId;
      try {
        await request(`/api/staff/classes/${classId}/learning-modules/${moduleId}/overrides`, {
          method: 'POST',
          body: {
            reason: data.get('reason'),
            studentId: event.target.dataset.studentId ? Number(event.target.dataset.studentId) : null,
          },
        });
        await refreshData();
        showToast('Documented prerequisite exception granted');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.id === 'packageImportForm') {
      event.preventDefault();
      const message = event.target.querySelector('[data-import-message]');
      try {
        await request('/api/instructor/species-packages', { method: 'POST', body: new FormData(event.target) });
        await refreshData();
        showToast('Package validated and imported as a draft');
      } catch (error) { setMessage(message, error.message); }
      return;
    }
    if (event.target.id === 'offeringCreateForm') {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        await request('/api/admin/offerings', { method: 'POST', body: { academicYear: data.get('academicYear'), semester: data.get('semester'), sectionCode: data.get('sectionCode') } });
        await refreshRoster();
        showToast('Section created for the existing MB 304L course');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.id === 'studentCreateForm') {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        const result = await request('/api/admin/students', { method: 'POST', body: Object.fromEntries(data) });
        state.activationCodes = result.activationCodes || [];
        await refreshRoster();
        showToast('Student roster updated');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.id === 'rosterCsvForm') {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        const result = await request('/api/admin/roster/preview', { method: 'POST', body: data });
        state.rosterFile = data.get('roster');
        state.rosterClassId = data.get('classId');
        state.rosterPreview = result.rows || [];
        renderPage('roster');
        showToast('Roster preview ready. No accounts changed.');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.matches('[data-instructor-form]')) {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        await request(`/api/admin/offerings/${event.target.dataset.instructorForm}/instructors`, { method: 'POST', body: { instructorId: Number(data.get('instructorId')) } });
        await refreshRoster();
        showToast('Instructor assigned to section');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
      return;
    }
    if (event.target.matches('[data-assignment-form]')) {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        await request(`/api/instructor/classes/${event.target.dataset.assignmentForm}/assignments`, { method: 'POST', body: { speciesPackageId: Number(data.get('speciesPackageId')), dueAt: data.get('dueAt') || null } });
        await refreshData();
        showToast('Assignment saved with the selected package version');
      } catch (error) { showToast(error.message, { tone: 'error', persistent: true }); }
    }
  }

  function updatePasswordGuidance() {
    if (!elements.registrationPassword) return;
    const password = elements.registrationPassword.value;
    const rules = {
      length: password.length >= 8,
      letter: /[A-Za-z]/.test(password),
      number: /\d/.test(password),
    };
    elements.passwordRequirements.querySelectorAll('[data-password-rule]').forEach((item) => {
      item.classList.toggle('is-met', Boolean(rules[item.dataset.passwordRule]));
    });
  }

  function handlePortalChange(event) {
    if (event.target.matches('[data-assignment-package]')) {
      const dueInput = event.target.closest('form')?.querySelector('input[name="dueAt"]');
      if (dueInput) dueInput.value = event.target.selectedOptions[0]?.dataset.currentDue || '';
      return;
    }
    if (!event.target.matches('[data-prompt-version]')) return;
    const module = state.learningModules.find((item) => String(item.id) === String(event.target.value));
    const toolSelect = portal.querySelector('[data-prompt-tool]');
    if (toolSelect && module) toolSelect.innerHTML = module.tools.map((tool) => `<option value="${escapeHtml(tool.key)}">${escapeHtml(tool.name)}</option>`).join('');
  }

  portal.addEventListener('click', handlePortalClick);
  portal.addEventListener('submit', handlePortalSubmit);
  portal.addEventListener('change', handlePortalChange);
  elements.menuButton.addEventListener('click', () => {
    if (matchMedia('(min-width: 781px)').matches) {
      const collapsed = portal.classList.toggle('is-desktop-collapsed');
      elements.menuButton.setAttribute('aria-expanded', String(!collapsed));
      elements.menuButton.setAttribute('aria-label', collapsed ? 'Open navigation' : 'Collapse navigation');
    } else setSidebarOpen(!portal.classList.contains('is-sidebar-open'));
  });
  elements.sidebarScrim.addEventListener('click', () => setSidebarOpen(false));
  document.addEventListener('keydown', handleSidebarKeydown);
  window.addEventListener('resize', () => setSidebarOpen(false, { restoreFocus: false }));
  elements.registrationPassword?.addEventListener('input', updatePasswordGuidance);
  elements.toast.addEventListener('click', () => {
    if (elements.toast.classList.contains('is-error')) dismissToast();
  });
  elements.toast.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && elements.toast.classList.contains('is-error')) dismissToast();
  });
  portal.querySelector('#forgotPassword')?.addEventListener('click', () => showToast('Contact your administrator for account support.'));
  async function synchronizeLearningRecords() {
    updateNetworkStatus();
    if (!state.currentUser || bootstrap.offlineShell) return;
    try {
      const [attempts, modules] = await Promise.all([synchronizeQueuedAttempts(), synchronizeQueuedModuleProgress()]);
      if (attempts || modules) {
        await refreshData();
        showToast(`${attempts + modules} offline learning record${attempts + modules === 1 ? '' : 's'} synchronized`);
      }
    } catch { showToast('Offline learning records will retry when the connection is stable.', { tone: 'error', persistent: true }); }
  }
  window.addEventListener('online', synchronizeLearningRecords);
  if (navigator.onLine) void synchronizeLearningRecords();
  window.addEventListener('offline', updateNetworkStatus);

  updateSession();
  portal.querySelectorAll('.portal-nav [data-portal-page]').forEach(link => { link.setAttribute('aria-label', link.textContent.trim()); link.title = link.textContent.trim(); });
  window.addEventListener('pageshow', event => { if (event.persisted && navigator.onLine) window.location.reload(); });
  if (bootstrap.offlineShell) portal.querySelectorAll('[data-portal-page]').forEach(link => { if(link.dataset.portalPage !== 'downloads') link.addEventListener('click', event => { event.stopImmediatePropagation(); window.location.assign(window.AQUADISSECT_ROUTES[link.dataset.portalPage]); }); });
  const refreshNotificationCount = setupWorkspaceShell(portal, state.currentUser);
  void setupWorkspacePage(window.AQUADISSECT_PAGE, portal.querySelector('#workspaceTool'), state.currentUser, showToast, refreshNotificationCount);
  updateNetworkStatus();
  const helpSearch = portal.querySelector('#helpSearch');
  helpSearch?.addEventListener('input', () => {
    const topics = [...portal.querySelectorAll('.help-topic')];
    topics.forEach(topic => { topic.hidden = !topic.textContent.toLowerCase().includes(helpSearch.value.trim().toLowerCase()); });
    portal.querySelector('#helpEmpty').hidden = topics.some(topic => !topic.hidden);
  });
  updatePasswordGuidance();
  showPage(window.AQUADISSECT_PAGE || (state.currentUser ? 'home' : 'login'));
  if (state.currentUser && !bootstrap.offlineShell) {
    setupTutorials(bootstrap.tutorials, state.currentUser);
    if (window.AQUADISSECT_PAGE === 'access' && state.currentUser.role === 'admin') void setupStaffRoles(confirmPortalAction);
    const checkRole = async () => {
      if (document.hidden || !navigator.onLine) return;
      try {
        const session = await request('/api/session');
        if (session.user?.role !== state.currentUser.role || session.user?.status !== state.currentUser.status) window.location.reload();
      } catch { /* Server still enforces current permissions on every action. */ }
    };
    document.addEventListener('visibilitychange', checkRole);
    window.addEventListener('focus', checkRole);
    window.addEventListener('online', checkRole);
    const roleTimer = setInterval(checkRole, 60000);
    window.addEventListener('pagehide', () => clearInterval(roleTimer), { once: true });
  }
  setupProgressiveWebApp({ installButton: elements.installButton, notify: showToast })
    .catch(() => { elements.installButton.hidden = true; });

  return {
    openDashboard,
    currentUser: () => state.currentUser,
    async recordAttempt(lessonKey, attempt) {
      if (!state.currentUser) throw new Error('Reconnect and sign in to record an attempt.');
      const response = await submitOrQueueAttempt(lessonKey, { ...attempt, classId: state.activeClassId });
      if (response.queued) showToast('Attempt saved offline and queued for synchronization');
      else {
        state.latestResult = response.result;
        try { await refreshData(); }
        catch { showToast('Your answers are saved. Reopen the page to refresh your dashboard.', { tone: 'error', persistent: true }); }
      }
      return response;
    },
  };
}
