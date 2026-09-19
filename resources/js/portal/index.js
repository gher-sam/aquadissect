import { request } from './api.js';
import { adminDashboard, adminRosterView, classCards, instructorDashboard, managerView, speciesLibrary, studentDashboard } from './dashboards.js';
import { escapeHtml, titleCase } from './format.js';
import { createModuleExperience, promptStudio, safetyWorkspace } from './safetyTools.js';
import {
  downloadedVersionFor, downloadLearningModule, downloadPackage, isDownloaded, isLearningModuleDownloaded,
  listDownloadedLearningModules, listDownloadedPackages, removeDownloadedLearningModule, removeDownloadedPackage,
  submitOrQueueAttempt, submitOrQueueModuleProgress, synchronizeQueuedAttempts, synchronizeQueuedModuleProgress,
} from '../offlinePackages.js';
import { setupProgressiveWebApp } from '../pwa.js';

const bootstrap = window.AQUADISSECT_BOOTSTRAP || {};
const dashboardCopy = {
  student: ['Student workspace', 'Welcome back', 'Choose your next activity below. AquaDissect keeps your saved progress with the lesson.'],
  instructor: ['Instructor workspace', 'Welcome', 'Open a class section to review students, safety status, and learning activities.'],
  admin: ['Administrator workspace', 'System overview', 'Review the people, class sections, and learning content that need attention.'],
};
const pageLabels = {
  home: 'Home', library: 'Species lessons', classes: 'Class sections', roster: 'People & sections', assignments: 'Class activities',
  safety: 'Safety & Tools', downloads: 'Offline access', manager: 'Species content', 'prompt-studio': 'Create media prompts', access: 'Account roles', about: 'Help & guide',
};

function requireElement(root, selector) {
  const element = root.querySelector(selector);
  if (!element) throw new Error(`AquaDissect portal element is missing: ${selector}`);
  return element;
}

export function setupAquaPortal({ app, onOpenPackage }) {
  const portal = requireElement(document, '#aquaPortal');
  const elements = {
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
    loginForm: requireElement(portal, '#loginForm'),
    loginMessage: requireElement(portal, '#loginMessage'),
    registrationForm: requireElement(portal, '#registrationForm'),
    registrationMessage: requireElement(portal, '#registrationMessage'),
    activationForm: requireElement(portal, '#activationForm'),
    activationMessage: requireElement(portal, '#activationMessage'),
    registrationPassword: requireElement(portal, '#registrationPassword'),
    registrationPasswordConfirmation: requireElement(portal, '#registrationPasswordConfirmation'),
    passwordRequirements: requireElement(portal, '#passwordRequirements'),
    dashboardKicker: requireElement(portal, '#dashboardKicker'),
    dashboardGreeting: requireElement(portal, '#dashboardGreeting'),
    dashboardSummary: requireElement(portal, '#dashboardSummary'),
    dashboardContent: requireElement(portal, '#dashboardContent'),
    library: requireElement(portal, '#speciesLibraryContent'),
    classes: requireElement(portal, '#classesContent'),
    roster: requireElement(portal, '#rosterContent'),
    assignments: requireElement(portal, '#assignmentsContent'),
    downloads: requireElement(portal, '#downloadsContent'),
    manager: requireElement(portal, '#speciesManagerContent'),
    safety: requireElement(portal, '#safetyToolsContent'),
    safetyExperience: requireElement(portal, '#safetyModuleExperience'),
    promptStudio: requireElement(portal, '#mediaPromptStudioContent'),
    sidebarSpeciesCount: requireElement(portal, '#sidebarSpeciesCount'),
    systemStatus: requireElement(portal, '#dashboardSystemStatus'),
    toast: requireElement(portal, '#portalToast'),
  };
  let sidebarReturnFocus = null;
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
    toastTimer: null,
    currentPage: 'home',
  };
  app.hidden = true;

  function showToast(message) {
    clearTimeout(state.toastTimer);
    elements.toast.textContent = message;
    elements.toast.classList.add('is-visible');
    state.toastTimer = setTimeout(() => elements.toast.classList.remove('is-visible'), 3000);
  }

  function setMessage(element, message = '') {
    element.textContent = message;
    element.hidden = !message;
  }

  function updateNetworkStatus() {
    const online = navigator.onLine;
    elements.systemStatus.classList.toggle('is-offline', !online);
    elements.systemStatus.querySelector('.dashboard-system-status-label').textContent = online ? 'Online' : 'Offline';
  }

  function updateSession() {
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
      elements.userRole.textContent = titleCase(user.role);
      elements.roleLabel.textContent = `${titleCase(user.role)} workspace`;
    } else {
      elements.roleLabel.textContent = 'AquaDissect';
    }
    const published = state.species.filter((item) => item.status === 'published').length;
    elements.sidebarSpeciesCount.textContent = `${published} lesson${published === 1 ? '' : 's'} ready`;
    portal.querySelectorAll('[data-nav-label]').forEach((label) => {
      label.textContent = pageLabels[label.dataset.navLabel] || label.textContent;
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
    elements.menuButton.setAttribute('aria-expanded', String(shouldOpen));
    elements.menuButton.setAttribute('aria-label', shouldOpen ? 'Close navigation' : 'Open navigation');
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
    if (state.currentUser?.role === 'student') return `<div class="species-library-grid">${speciesLibrary(state.species)}</div>`;
    const published = state.species.filter((item) => item.status === 'published');
    const activeClasses = state.classes.filter((item) => item.status === 'active');
    if (!activeClasses.length) return '<div class="empty-state"><strong>No assigned section is available</strong><span>Ask the administrator to assign you to an active MB 304L section.</span></div>';
    return `<div class="assignment-grid">${activeClasses.map((course) => `
      <form class="assignment-card" data-assignment-form="${course.id}">
        <span class="portal-kicker">${escapeHtml(course.code)}</span><h2>${escapeHtml(course.name)}</h2>
        <label class="portal-field">Published species lesson<select name="speciesPackageId" required>${published.map((item) => `<option value="${item.id}">${escapeHtml(item.commonName)} · v${escapeHtml(item.version)}</option>`).join('')}</select></label>
        <label class="portal-field">Due date (optional)<input name="dueAt" type="date"></label>
        <button class="portal-primary" type="submit">Assign lesson</button>
        <small>Students who already started remain on their saved lesson version unless you intentionally assign a new version.</small>
      </form>`).join('')}</div>`;
  }

  function renderPage(name = state.currentPage) {
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
          : adminDashboard(state.pendingUsers, state.species, state.classes);
    }
    if (name === 'library') elements.library.innerHTML = speciesLibrary(state.species);
    if (name === 'safety') {
      elements.safety.hidden = false;
      elements.safetyExperience.hidden = true;
      elements.safety.innerHTML = safetyWorkspace(state.learningModules, state.currentUser?.role, state.currentUser);
    }
    if (name === 'classes') {
      elements.classes.innerHTML = classCards(state.classes, state.currentUser?.role);
    }
    if (name === 'roster') elements.roster.innerHTML = adminRosterView(state.adminRoster, state.rosterPreview, state.activationCodes);
    if (name === 'assignments') elements.assignments.innerHTML = assignmentView();
    if (name === 'downloads') {
      elements.downloads.innerHTML = `<div class="download-summary"><strong>${listDownloadedPackages().length + listDownloadedLearningModules().length}</strong><span>learning items saved on this device</span></div><div class="portal-page-title portal-page-title--compact"><span class="portal-kicker">Prerequisite</span><h2>Safety module</h2></div>${safetyWorkspace(state.learningModules, state.currentUser?.role, state.currentUser, { offline: true })}<div class="portal-page-title portal-page-title--compact"><span class="portal-kicker">Assigned anatomy</span><h2>Species lessons</h2></div><div class="species-library-grid">${speciesLibrary(state.species.filter((item) => item.status === 'published'), { offline: true })}</div>`;
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
    if (name === 'manager') elements.manager.innerHTML = managerView(state.species, state.currentUser?.role, state.currentUser);
    if (name === 'prompt-studio') elements.promptStudio.innerHTML = promptStudio(state.learningModules);

    const activePanel = portal.querySelector(`[data-portal-panel="${name}"]`);
    import('../portalMotion.js')
      .then(({ revealPortalPage }) => revealPortalPage(activePanel))
      .catch(() => undefined);
  }

  function showPage(name) {
    if (!state.currentUser && !['login', 'register', 'activate', 'access', 'about'].includes(name)) name = 'login';
    if (name === 'roster' && state.currentUser?.role !== 'admin') name = state.currentUser ? 'home' : 'login';
    if (name === 'prompt-studio' && !['instructor', 'admin'].includes(state.currentUser?.role)) name = state.currentUser ? 'home' : 'login';
    state.currentPage = name;
    portal.querySelectorAll('[data-portal-panel]').forEach((panel) => { panel.hidden = panel.dataset.portalPanel !== name; });
    portal.querySelectorAll('.portal-nav [data-portal-page]').forEach((button) => {
      const active = button.dataset.portalPage === name;
      button.classList.toggle('is-active', active);
      if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
    elements.pageLabel.textContent = pageLabels[name] || 'AquaDissect';
    setSidebarOpen(false);
    renderPage(name);
    if (name === 'roster' && !state.adminRoster) void refreshRoster();
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  }

  function signIn(payload) {
    state.currentUser = payload.user;
    state.latestResult = payload.lessonResult || null;
    state.pendingUsers = payload.pendingUsers || [];
    state.species = payload.species || [];
    state.classes = payload.classes || [];
    state.learningModules = payload.learningModules || [];
    state.adminRoster = null;
    state.activationCodes = [];
    updateSession();
    showPage('home');
    showToast(`Signed in as ${titleCase(payload.user.role)}`);
  }

  async function refreshData() {
    const [speciesResponse, classesResponse, modulesResponse] = await Promise.all([request('/api/species'), request('/api/classes'), request('/api/learning-modules')]);
    state.species = speciesResponse.species || [];
    state.classes = classesResponse.classes || [];
    state.learningModules = modulesResponse.learningModules || [];
    updateSession();
    renderPage();
  }

  async function refreshRoster() {
    if (state.currentUser?.role !== 'admin') return;
    try {
      state.adminRoster = await request('/api/admin/roster');
      state.classes = state.adminRoster.classes || [];
      renderPage();
    } catch (error) { showToast(error.message); }
  }

  async function signOut() {
    try {
      await request('/api/logout', { method: 'POST' });
      state.currentUser = null;
      state.latestResult = null;
      state.pendingUsers = [];
      state.species = [];
      state.classes = [];
      state.learningModules = [];
      state.adminRoster = null;
      state.activationCodes = [];
      state.rosterPreview = [];
      state.rosterFile = null;
      state.activeClassId = null;
      updateSession();
      showPage('login');
      showToast('Signed out');
    } catch (error) { showToast(error.message); }
  }

  async function openPackage(id, viewerType = 'auto', selectedClassId = null) {
    const record = state.species.find((item) => String(item.id) === String(id));
    if (!record) return;
    const matchingClasses = state.currentUser?.role === 'student'
      ? state.classes.filter((item) => item.status === 'active' && item.assignments.some((assignment) => String(assignment.packageId) === String(id)))
      : [];
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
      document.body.classList.add('lesson-is-open');
      window.scrollTo({ top: 0, behavior: 'instant' });
      window.dispatchEvent(new Event('resize'));
    } catch (error) { showToast(error.message); }
  }

  async function openLearningModule(id) {
    const record = state.learningModules.find((item) => String(item.id) === String(id));
    if (!record) return;
    try {
      const classQuery = state.currentUser?.role === 'student' ? `?classId=${record.classId}` : '';
      const response = await request(`/api/learning-modules/${record.id}/manifest${classQuery}`);
      elements.safety.hidden = true;
      elements.safetyExperience.hidden = false;
      createModuleExperience(elements.safetyExperience, response.module, record, {
        onProgress: state.currentUser?.role === 'student' ? async (progress) => {
          const result = await submitOrQueueModuleProgress(record.id, {
            ...progress,
            classId: record.classId,
            totalSteps: response.module.requiredStepIds?.length || 0,
          });
          record.progress = { ...record.progress, ...result.progress };
          if (result.progress?.completed) {
            state.species.forEach((species) => {
              if (species.locked) {
                species.locked = false;
                species.availableClassId = record.classId;
                species.lockReason = null;
              }
            });
          }
          if (result.queued) showToast('Progress saved offline and queued for synchronization');
          return result;
        } : undefined,
        onComplete: async () => {
          showToast('Safety & Tools completed. Assigned anatomy lessons are now available.');
          if (navigator.onLine) await refreshData();
        },
        onError: (error) => showToast(error.message),
      });
      window.scrollTo({ top: 0, behavior: 'instant' });
    } catch (error) { showToast(error.message); }
  }

  function openDashboard() {
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
      const messages = {
        submit: 'Species lesson sent for independent faculty review',
        publish: 'Approved species lesson published',
        activate: 'Active lesson version updated',
        archive: 'Species lesson archived with its history retained',
        restore: 'Species lesson restored and waiting for a fresh faculty review',
      };
      showToast(messages[action] || 'Species lesson updated');
    } catch (error) { showToast(error.message); }
  }

  async function moduleLifecycleAction(action, versionId) {
    const prefix = action === 'publish' || action === 'archive' ? '/api/admin' : '/api/staff';
    try {
      const response = await request(`${prefix}/learning-module-versions/${versionId}/${action}`, { method: 'POST' });
      await refreshData();
      const detail = action === 'publish' ? ` ${response.assignments || 0} section assignment(s) updated; ${response.legacyOverrides || 0} legacy exception(s) retained.` : '';
      showToast(`Learning module ${action} completed.${detail}`);
    } catch (error) { showToast(error.message); }
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
      elements.safetyExperience.hidden = true;
      elements.safety.hidden = false;
      renderPage('safety');
    }
    const approvalId = event.target.closest('[data-approve-account]')?.dataset.approveAccount;
    if (approvalId && state.currentUser?.role === 'admin') {
      try {
        await request(`/api/admin/users/${approvalId}/approve`, { method: 'POST' });
        state.pendingUsers = state.pendingUsers.filter((item) => String(item.id) !== approvalId);
        renderPage();
        showToast('Instructor account approved');
      } catch (error) { showToast(error.message); }
    }
    const offeringAction = event.target.closest('[data-offering-status]');
    if (offeringAction && state.currentUser?.role === 'admin') {
      try {
        await request(`/api/admin/offerings/${offeringAction.dataset.offeringId}/status`, { method: 'PATCH', body: { status: offeringAction.dataset.offeringStatus } });
        await refreshRoster();
        showToast(`Section ${offeringAction.dataset.offeringStatus}`);
      } catch (error) { showToast(error.message); }
    }
    const activationId = event.target.closest('[data-issue-activation]')?.dataset.issueActivation;
    if (activationId && state.currentUser?.role === 'admin') {
      try {
        const result = await request(`/api/admin/students/${activationId}/activation`, { method: 'POST' });
        state.activationCodes = [{ email: result.email, code: result.code }];
        renderPage('roster');
      } catch (error) { showToast(error.message); }
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
        showToast('Roster imported. Existing accounts were reused.');
      } catch (error) { showToast(error.message); }
    }
    for (const action of ['submit', 'publish', 'activate', 'archive', 'restore']) {
      const id = event.target.closest(`[data-${action}-package]`)?.dataset[`${action}Package`];
      if (id) await lifecycleAction(action, id);
    }
    for (const action of ['submit', 'publish', 'archive']) {
      const button = event.target.closest(`[data-${action}-learning-module]`);
      if (button) await moduleLifecycleAction(action, button.dataset[`${action}LearningModule`]);
    }
    const revokeOverride = event.target.closest('[data-revoke-module-override]');
    if (revokeOverride) {
      try {
        await request(`/api/staff/learning-module-overrides/${revokeOverride.dataset.revokeModuleOverride}`, { method: 'DELETE' });
        await refreshData();
        showToast('Prerequisite exception revoked with its history retained');
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); renderPage('downloads'); }
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
      } catch (error) { showToast(error.message); renderPage('downloads'); }
    }
  }

  async function handlePortalSubmit(event) {
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
      const email = String(data.get('email')).trim().toLowerCase();
      const password = String(data.get('password'));
      const passwordConfirmation = String(data.get('passwordConfirmation'));
      if (password !== passwordConfirmation) {
        setMessage(elements.registrationMessage, 'The password confirmation does not match.');
        elements.registrationPasswordConfirmation.focus();
        return;
      }
      try {
        const response = await request('/api/register', { method: 'POST', body: { firstName: String(data.get('firstName')).trim(), lastName: String(data.get('lastName')).trim(), email, identityNumber: String(data.get('identityNumber')).trim(), role: 'instructor', password, agreement: data.get('agreement') === 'on' } });
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
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); }
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
      } catch (error) { showToast(error.message); }
      return;
    }
    if (event.target.matches('[data-instructor-form]')) {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        await request(`/api/admin/offerings/${event.target.dataset.instructorForm}/instructors`, { method: 'POST', body: { instructorId: Number(data.get('instructorId')) } });
        await refreshRoster();
        showToast('Instructor assigned to section');
      } catch (error) { showToast(error.message); }
      return;
    }
    if (event.target.matches('[data-assignment-form]')) {
      event.preventDefault();
      const data = new FormData(event.target);
      try {
        await request(`/api/instructor/classes/${event.target.dataset.assignmentForm}/assignments`, { method: 'POST', body: { speciesPackageId: Number(data.get('speciesPackageId')), dueAt: data.get('dueAt') || null } });
        await refreshData();
        showToast('Assignment saved with the selected package version');
      } catch (error) { showToast(error.message); }
    }
  }

  function updatePasswordGuidance() {
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
    if (!event.target.matches('[data-prompt-version]')) return;
    const module = state.learningModules.find((item) => String(item.id) === String(event.target.value));
    const toolSelect = portal.querySelector('[data-prompt-tool]');
    if (toolSelect && module) toolSelect.innerHTML = module.tools.map((tool) => `<option value="${escapeHtml(tool.key)}">${escapeHtml(tool.name)}</option>`).join('');
  }

  portal.addEventListener('click', handlePortalClick);
  portal.addEventListener('submit', handlePortalSubmit);
  portal.addEventListener('change', handlePortalChange);
  elements.menuButton.addEventListener('click', () => setSidebarOpen(!portal.classList.contains('is-sidebar-open')));
  elements.sidebarScrim.addEventListener('click', () => setSidebarOpen(false));
  document.addEventListener('keydown', handleSidebarKeydown);
  window.addEventListener('resize', () => setSidebarOpen(false, { restoreFocus: false }));
  elements.registrationPassword.addEventListener('input', updatePasswordGuidance);
  requireElement(portal, '#forgotPassword').addEventListener('click', () => showToast('Contact your administrator for account support.'));
  window.addEventListener('online', async () => {
    updateNetworkStatus();
    try {
      const [attempts, modules] = await Promise.all([synchronizeQueuedAttempts(), synchronizeQueuedModuleProgress()]);
      if (attempts || modules) {
        await refreshData();
        showToast(`${attempts + modules} offline learning record${attempts + modules === 1 ? '' : 's'} synchronized`);
      }
    } catch { showToast('Offline learning records will retry when the connection is stable.'); }
  });
  window.addEventListener('offline', updateNetworkStatus);

  updateSession();
  updateNetworkStatus();
  updatePasswordGuidance();
  showPage(state.currentUser ? 'home' : 'login');
  setupProgressiveWebApp({ installButton: elements.installButton, notify: showToast })
    .catch(() => { elements.installButton.hidden = true; });

  return {
    openDashboard,
    currentUser: () => state.currentUser,
    async recordAttempt(lessonKey, attempt) {
      const response = await submitOrQueueAttempt(lessonKey, { ...attempt, classId: state.activeClassId });
      if (response.queued) showToast('Attempt saved offline and queued for synchronization');
      else {
        state.latestResult = response.result;
        await refreshData();
      }
      return response;
    },
  };
}
