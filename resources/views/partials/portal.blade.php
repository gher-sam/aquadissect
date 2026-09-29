<div id="aquaPortal">
  <header class="portal-header" id="portalSidebar">
    <button class="portal-brand" type="button" data-portal-page="home" aria-label="AquaDissect home">
      <span class="portal-brand-mark" aria-hidden="true"><i data-lucide="waves"></i></span>
      <span><strong>AquaDissect</strong><small>Guided aquatic anatomy learning</small></span>
    </button>
    <nav class="portal-nav" aria-label="Primary navigation">
      <span class="portal-nav-section" data-role-nav="student,instructor,admin">Navigation</span>
      <button type="button" data-portal-page="home"><i class="portal-nav-icon" data-lucide="layout-dashboard"></i><span data-nav-label="home">Home</span></button>
      <span class="portal-nav-section" data-role-nav="student,instructor,admin">Learning</span>
      <button type="button" data-portal-page="library" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="fish"></i><span data-nav-label="library">Species lessons</span></button>
      <button type="button" data-portal-page="safety" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="shield-check"></i><span data-nav-label="safety">Safety &amp; Tools</span></button>
      <button type="button" data-portal-page="classes" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="school"></i><span data-nav-label="classes">Class sections</span></button>
      <button type="button" data-portal-page="assignments" data-role-nav="instructor"><i class="portal-nav-icon" data-lucide="clipboard-list"></i><span data-nav-label="assignments">Class activities</span></button>
      <button type="button" data-portal-page="downloads" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="download"></i><span data-nav-label="downloads">Offline access</span></button>
      <span class="portal-nav-section" data-role-nav="instructor,admin">Manage</span>
      <button type="button" data-portal-page="roster" data-role-nav="admin"><i class="portal-nav-icon" data-lucide="users-round"></i><span data-nav-label="roster">People &amp; sections</span></button>
      <button type="button" data-portal-page="manager" data-role-nav="instructor,admin"><i class="portal-nav-icon" data-lucide="package-open"></i><span data-nav-label="manager">Species content</span></button>
      <button type="button" data-portal-page="prompt-studio" data-role-nav="instructor,admin"><i class="portal-nav-icon" data-lucide="sparkles"></i><span data-nav-label="prompt-studio">Create media prompts</span></button>
      <span class="portal-nav-section" data-role-nav="student,instructor,admin">Workspace</span>
      <button type="button" data-portal-page="calendar" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="calendar"></i><span>Calendar</span></button>
      <button type="button" data-portal-page="notifications" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="bell"></i><span>Notifications</span></button>
      <button type="button" data-portal-page="reports" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="chart-no-axes-combined"></i><span>Reports</span></button>
      <span class="portal-nav-section portal-nav-section--support" data-role-nav="student,instructor,admin">Help</span>
      <button type="button" data-portal-page="source-handover" data-role-nav="admin"><i class="portal-nav-icon" data-lucide="download"></i><span>Source Structure</span></button>
      <button type="button" data-portal-page="access"><i class="portal-nav-icon" data-lucide="users"></i><span data-nav-label="access">Account roles</span></button>
      <button type="button" data-portal-page="about"><i class="portal-nav-icon" data-lucide="book-open"></i><span data-nav-label="about">Help &amp; guide</span></button>
    </nav>
    <div class="portal-sidebar-note">
      <span class="sidebar-note-icon" aria-hidden="true"><i data-lucide="fish"></i></span>
      <span><small>Lesson library</small><strong id="sidebarSpeciesCount">1 lesson ready</strong></span>
      <span class="sidebar-status">Ready</span>
    </div>
    <div class="portal-session" id="portalSession">
      <button class="portal-primary" id="portalSignIn" type="button" data-portal-page="login">Sign in</button>
      <div class="portal-user-card" id="portalUserCard" hidden>
        <span class="portal-avatar" id="portalUserInitials" aria-hidden="true">AS</span>
        <span class="session-copy"><strong id="portalUserName">AquaDissect user</strong><small id="portalUserRole">Account</small></span>
        <button class="portal-signout" type="button" data-portal-signout aria-label="Sign out" title="Sign out"><i data-lucide="log-out"></i></button>
      </div>
    </div>
  </header>

  <button class="portal-sidebar-scrim" id="portalSidebarScrim" type="button" aria-label="Close navigation" tabindex="-1"></button>

  <main class="portal-main" id="portal-main" tabindex="-1">
    <div class="dashboard-topbar">
      <button class="dashboard-menu-button" id="dashboardMenuButton" type="button" aria-label="Open navigation" aria-controls="portalSidebar" aria-expanded="false"><i data-lucide="menu"></i></button>
      <div class="dashboard-breadcrumb"><span id="dashboardRoleLabel">AquaDissect</span><strong id="dashboardPageLabel">Overview</strong></div>
      <div class="workspace-search"><label class="sr-only" for="workspaceSearch">Search pages and help</label><input id="workspaceSearch" type="search" placeholder="Search pages and help…" autocomplete="off" aria-controls="workspaceSearchResults"><div id="workspaceSearchResults" hidden></div></div>
      <a class="workspace-bell" href="{{ url('/notifications') }}" data-role-nav="student,instructor,admin" aria-label="Notifications"><i data-lucide="bell"></i><span id="notificationCount" hidden>0</span></a>
      <div class="workspace-account dropdown" data-role-nav="student,instructor,admin">
        <button class="btn dropdown-toggle" id="workspaceAccountButton" type="button" data-bs-toggle="dropdown" aria-expanded="false">Account</button>
        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="workspaceAccountButton">
          <a class="dropdown-item" href="{{ url('/account-roles') }}">Account roles</a>
          <a class="dropdown-item" href="{{ url('/help') }}">Help &amp; guide</a>
          <button class="dropdown-item" type="button" data-portal-signout>Sign out</button>
        </div>
      </div>
      <button class="dashboard-install-button" id="installAppButton" type="button" hidden><i data-lucide="download"></i><span>Install app</span></button>
      <div class="dashboard-system-status" id="dashboardSystemStatus" role="status" aria-live="polite"><span aria-hidden="true"></span><span class="dashboard-system-status-label">Online</span></div>
    </div>

    @include('pages.'. $page)

  </main>

  <dialog class="portal-confirm-dialog" id="portalConfirmDialog" aria-labelledby="portalConfirmTitle" aria-describedby="portalConfirmDescription">
    <form method="dialog">
      <span class="portal-confirm-dialog__icon" aria-hidden="true"><i data-lucide="circle-help"></i></span>
      <div>
        <span class="portal-kicker">Please review</span>
        <h2 id="portalConfirmTitle">Confirm this action</h2>
        <p id="portalConfirmDescription"></p>
      </div>
      <div class="portal-confirm-dialog__actions">
        <button class="portal-secondary" type="submit" value="cancel">Cancel</button>
        <button class="portal-primary" id="portalConfirmButton" type="submit" value="confirm">Confirm</button>
      </div>
    </form>
  </dialog>
  <div class="portal-toast" id="portalToast" role="status" aria-live="polite" aria-atomic="true"></div>
</div>
