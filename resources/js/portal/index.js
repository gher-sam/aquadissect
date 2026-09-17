import { request } from './api.js';
import { adminDashboard, adminRosterView, classCards, instructorDashboard, managerView, speciesLibrary, studentDashboard } from './dashboards.js';
import { escapeHtml, titleCase } from './format.js';
import { downloadedVersionFor, downloadPackage, isDownloaded, listDownloadedPackages, removeDownloadedPackage, submitOrQueueAttempt, synchronizeQueuedAttempts } from '../offlinePackages.js';
import { setupProgressiveWebApp } from '../pwa.js';

const bootstrap = window.AQUADISSECT_BOOTSTRAP || {};
const dashboardCopy = {
  student: ['Student workspace', 'Welcome back', 'Continue assigned lessons and monitor your progress by package version.'],
  instructor: ['Instructor workspace', 'Welcome', 'Use assigned MB 304L sections, assignments, drafts, and student results.'],
  admin: ['Administrator workspace', 'System overview', 'Validate packages, publish immutable versions, and monitor platform operations.'],
};
const pageLabels = {
  home: 'Dashboard', library: 'Species Library', classes: 'My Sections', roster: 'Roster & Sections', assignments: 'Assignments',
  downloads: 'Downloads', manager: 'Species Manager', access: 'User access', about: 'How it works',
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
    elements.systemStatus.querySelector('.dashboard-system-status-label').textContent = online ? 'System online' : 'Offline mode';
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
    elements.sidebarSpeciesCount.textContent = `${published} published`;
    portal.querySelectorAll('[data-nav-label]').forEach((label) => {
      label.textContent = pageLabels[label.dataset.navLabel] || label.textContent;
    });
  }

  function setSidebarOpen(open, { restoreFocus = true } = {}) {
    const mobile = window.matchMedia('(max-width: 780px)').matches;
    const drawerMode = mobile && portal.classList.contains('is-authenticated');
    const wasOpen = portal.classList.contains('is-sidebar-open');
    const shouldOpen = Boolean(open && drawerMode);
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
        <label class="portal-field">Published package<select name="speciesPackageId" required>${published.map((item) => `<option value="${item.id}">${escapeHtml(item.commonName)} · v${escapeHtml(item.version)}</option>`).join('')}</select></label>
        <label class="portal-field">Due date (optional)<input name="dueAt" type="date"></label>
        <button class="portal-primary" type="submit">Assign selected version</button>
        <small>An explicit reassignment upgrades a class even after a learner has started.</small>
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
    if (name === 'classes') {
      elements.classes.innerHTML = classCards(state.classes, state.currentUser?.role);
    }
    if (name === 'roster') elements.roster.innerHTML = adminRosterView(state.adminRoster, state.rosterPreview, state.activationCodes);
    if (name === 'assignments') elements.assignments.innerHTML = assignmentView();
    if (name === 'downloads') {
      elements.downloads.innerHTML = `<div class="download-summary"><strong>${listDownloadedPackages().length}</strong><span>package version(s) stored on this device</span></div><div class="species-library-grid">${speciesLibrary(state.species.filter((item) => item.status === 'published'), { offline: true })}</div>`;
      state.species.forEach((item) => {
        const button = elements.downloads.querySelector(`[data-offline-toggle="${item.id}"]`);
        if (button) {
          const downloadedVersion = downloadedVersionFor(item.slug);
          button.textContent = isDownloaded(item.id, item.version)
            ? 'Remove download'
            : downloadedVersion ? `Update offline copy · v${downloadedVersion} → v${item.version}` : 'Download for offline use';
        }
      });
    }
    if (name === 'manager') elements.manager.innerHTML = managerView(state.species, state.currentUser?.role);

    const activePanel = portal.querySelector(`[data-portal-panel="${name}"]`);
    import('../portalMotion.js')
      .then(({ revealPortalPage }) => revealPortalPage(activePanel))
      .catch(() => undefined);
  }

  function showPage(name) {
    if (!state.currentUser && !['login', 'register', 'activate', 'access', 'about'].includes(name)) name = 'login';
    if (name === 'roster' && state.currentUser?.role !== 'admin') name = state.currentUser ? 'home' : 'login';
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
    state.adminRoster = null;
    state.activationCodes = [];
    updateSession();
    showPage('home');
    showToast(`Signed in as ${titleCase(payload.user.role)}`);
  }

  async function refreshData() {
    const [speciesResponse, classesResponse] = await Promise.all([request('/api/species'), request('/api/classes')]);
    state.species = speciesResponse.species || [];
    state.classes = classesResponse.classes || [];
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
      const response = await request(`/api/species/${record.slug}/packages/${record.id}/manifest`);
      await onOpenPackage(response.package, record, { viewerType });
      portal.hidden = true;
      app.hidden = false;
      document.body.classList.add('lesson-is-open');
      window.scrollTo({ top: 0, behavior: 'instant' });
      window.dispatchEvent(new Event('resize'));
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
      showToast(`Package ${action} completed`);
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
    const offlineId = event.target.closest('[data-offline-toggle]')?.dataset.offlineToggle;
    if (offlineId) {
      const record = state.species.find((item) => String(item.id) === offlineId);
      try {
        if (isDownloaded(record.id, record.version)) {
          await removeDownloadedPackage(record.id);
          showToast('Offline package removed from this device');
        } else {
          event.target.disabled = true;
          event.target.textContent = 'Verifying download…';
          await downloadPackage(record);
          showToast('Package verified and ready offline');
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

  portal.addEventListener('click', handlePortalClick);
  portal.addEventListener('submit', handlePortalSubmit);
  elements.menuButton.addEventListener('click', () => setSidebarOpen(!portal.classList.contains('is-sidebar-open')));
  elements.sidebarScrim.addEventListener('click', () => setSidebarOpen(false));
  document.addEventListener('keydown', handleSidebarKeydown);
  window.addEventListener('resize', () => setSidebarOpen(false, { restoreFocus: false }));
  elements.registrationPassword.addEventListener('input', updatePasswordGuidance);
  requireElement(portal, '#forgotPassword').addEventListener('click', () => showToast('Contact your administrator for account support.'));
  window.addEventListener('online', async () => {
    updateNetworkStatus();
    try {
      const count = await synchronizeQueuedAttempts();
      if (count) showToast(`${count} offline attempt${count === 1 ? '' : 's'} synchronized`);
    } catch { showToast('Offline attempts will retry when the connection is stable.'); }
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
