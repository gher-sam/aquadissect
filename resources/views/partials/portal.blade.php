<div id="aquaPortal">
  <header class="portal-header" id="portalSidebar">
    <button class="portal-brand" type="button" data-portal-page="home" aria-label="AquaDissect home">
      <span class="portal-brand-mark" aria-hidden="true">A</span>
      <span><strong>AquaDissect</strong><small>Guided aquatic anatomy learning</small></span>
    </button>
    <span class="portal-sidebar-label">Workspace</span>
    <nav class="portal-nav" aria-label="Primary navigation">
      <button type="button" data-portal-page="home"><i class="portal-nav-icon" data-lucide="layout-dashboard"></i><span data-nav-label="home">Home</span></button>
      <button type="button" data-portal-page="library" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="fish"></i><span data-nav-label="library">Species Library</span></button>
      <button type="button" data-portal-page="classes" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="school"></i><span data-nav-label="classes">My Classes</span></button>
      <button type="button" data-portal-page="assignments" data-role-nav="student,instructor"><i class="portal-nav-icon" data-lucide="clipboard-list"></i><span data-nav-label="assignments">Assignments</span></button>
      <button type="button" data-portal-page="downloads" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="download"></i><span data-nav-label="downloads">Downloads</span></button>
      <button type="button" data-portal-page="manager" data-role-nav="instructor,admin"><i class="portal-nav-icon" data-lucide="package-open"></i><span data-nav-label="manager">Species Manager</span></button>
      <button type="button" data-portal-page="access"><i class="portal-nav-icon" data-lucide="users"></i><span data-nav-label="access">User access</span></button>
      <button type="button" data-portal-page="about"><i class="portal-nav-icon" data-lucide="book-open"></i><span data-nav-label="about">About</span></button>
    </nav>
    <div class="portal-sidebar-note">
      <span class="sidebar-note-icon" aria-hidden="true"><i data-lucide="fish"></i></span>
      <span><small>Platform library</small><strong id="sidebarSpeciesCount">1 published</strong></span>
      <span class="sidebar-status">Multi-species</span>
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

  <main class="portal-main" id="portal-main">
    <div class="dashboard-topbar">
      <button class="dashboard-menu-button" id="dashboardMenuButton" type="button" aria-label="Open navigation" aria-controls="portalSidebar" aria-expanded="false"><i data-lucide="menu"></i></button>
      <div class="dashboard-breadcrumb"><span id="dashboardRoleLabel">AquaDissect</span><strong id="dashboardPageLabel">Overview</strong></div>
      <button class="dashboard-install-button" id="installAppButton" type="button" hidden><i data-lucide="download"></i><span>Install app</span></button>
      <div class="dashboard-system-status" id="dashboardSystemStatus"><span aria-hidden="true"></span><span class="dashboard-system-status-label">System online</span></div>
    </div>

    <section class="portal-page auth-page" data-portal-panel="login">
      <div class="auth-introduction">
        <span class="portal-kicker">Interactive aquatic anatomy</span>
        <h1>Learn structures.<br>Follow procedures.<br>Build confidence.</h1>
        <p>AquaDissect combines guided laboratory activities, interactive anatomy, assessments, saved progress, and instructor-reviewed reports.</p>
        <div class="auth-highlights" aria-label="Application highlights">
          <span>Review-controlled anatomy content</span>
          <span>Role-based learning access</span>
          <span>Progress and laboratory reports</span>
        </div>
      </div>

      <form class="auth-card" id="loginForm">
        <span class="portal-kicker">Secure account access</span>
        <h2>Sign in</h2>
        <p class="portal-muted">Use an approved institutional account.</p>
        <label class="portal-field">Email address<input name="email" type="email" autocomplete="username" required value="{{ app()->environment('production') ? '' : 'student@aquadissect.local' }}"></label>
        <label class="portal-field">Password<input name="password" type="password" autocomplete="current-password" required value="{{ app()->environment('production') ? '' : 'demo123' }}"></label>
        <div class="auth-options">
          <label><input name="remember" type="checkbox"> Remember me</label>
          <button class="portal-link" type="button" id="forgotPassword">Forgot password?</button>
        </div>
        <p class="portal-message" id="loginMessage" role="alert" hidden></p>
        <button class="portal-primary portal-full" type="submit">Sign in</button>
        <button class="portal-secondary portal-full" type="button" data-portal-page="register">Create a student or instructor account</button>
        @unless(app()->environment('production'))
        <div class="demo-access">
          <span>Preview a role</span>
          <div>
            <button type="button" data-demo-role="student" data-demo-email="student@aquadissect.local" data-demo-password="demo123">Student</button>
            <button type="button" data-demo-role="instructor" data-demo-email="instructor@aquadissect.local" data-demo-password="demo123">Instructor</button>
            <button type="button" data-demo-role="admin" data-demo-email="admin@aquadissect.local" data-demo-password="demo123">Administrator</button>
          </div>
          <small>Seeded demonstration accounts use password <strong>demo123</strong>.</small>
          <small>Authentication uses Laravel sessions and hashed database passwords.</small>
        </div>
        @endunless
      </form>
    </section>

    <section class="portal-page" data-portal-panel="register" hidden>
      <div class="portal-page-title">
        <span class="portal-kicker">Account request</span>
        <h1>Create your AquaDissect account</h1>
        <p>Student accounts require a valid class code. Instructor access requires administrator approval.</p>
      </div>
      <form class="registration-card" id="registrationForm">
        <div class="registration-grid">
          <label class="portal-field">First name<input name="firstName" required autocomplete="given-name"></label>
          <label class="portal-field">Last name<input name="lastName" required autocomplete="family-name"></label>
          <label class="portal-field portal-wide">Institutional email<input name="email" type="email" required autocomplete="email"></label>
          <label class="portal-field">Student or employee ID<input name="identityNumber" required></label>
          <label class="portal-field">Account type<select name="role" id="registrationRole"><option value="student">Student</option><option value="instructor">Instructor</option></select></label>
          <label class="portal-field" id="classCodeField">Class enrollment code<input name="classCode" value="FISHBIO-101"></label>
          <label class="portal-field">Password<input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
          <label class="portal-check portal-wide"><input name="agreement" type="checkbox" required><span>I agree to use the system only for approved educational activities and follow laboratory safety requirements.</span></label>
        </div>
        <div class="registration-note" id="registrationNote"><strong>Student activation:</strong> students are activated after the server validates the registration fields and class code.</div>
        <p class="portal-message" id="registrationMessage" role="alert" hidden></p>
        <div class="registration-actions">
          <button class="portal-secondary" type="button" data-portal-page="login">Back to sign in</button>
          <button class="portal-primary" type="submit">Submit registration</button>
        </div>
      </form>
    </section>

    <section class="portal-page" data-portal-panel="home" hidden>
      <div class="dashboard-hero">
        <div>
          <span class="portal-kicker" id="dashboardKicker">Student workspace</span>
          <h1 id="dashboardGreeting">Welcome to AquaDissect</h1>
          <p id="dashboardSummary">Continue your assigned learning activities.</p>
        </div>
      </div>
      <div id="dashboardContent"></div>
    </section>

    <section class="portal-page" data-portal-panel="library" hidden>
      <div class="portal-page-title"><span class="portal-kicker">Approved and preview content</span><h1>Species Library</h1><p>Open assigned published lessons or preview draft packages when your role allows it.</p></div>
      <div id="speciesLibraryContent" class="species-library-grid"></div>
    </section>

    <section class="portal-page" data-portal-panel="classes" hidden>
      <div class="portal-page-title"><span class="portal-kicker">Learning groups</span><h1>My Classes</h1><p>Classes connect instructors, enrolled students, and version-pinned species assignments.</p></div>
      <div id="classesContent"></div>
    </section>

    <section class="portal-page" data-portal-panel="assignments" hidden>
      <div class="portal-page-title"><span class="portal-kicker">Version-aware learning</span><h1>Assignments</h1><p>Published species stay pinned to the assigned version after a student begins.</p></div>
      <div id="assignmentsContent"></div>
    </section>

    <section class="portal-page" data-portal-panel="downloads" hidden>
      <div class="portal-page-title"><span class="portal-kicker">Resilient learning</span><h1>Downloads</h1><p>Keep selected lessons on this device for formative learning when the connection is unavailable.</p></div>
      <div id="downloadsContent"></div>
    </section>

    <section class="portal-page" data-portal-panel="manager" hidden>
      <div class="portal-page-title"><span class="portal-kicker">Controlled content lifecycle</span><h1>Species Manager</h1><p>Import data-only .aqpack files, review immutable versions, and publish approved content.</p></div>
      <div id="speciesManagerContent"></div>
    </section>

    <section class="portal-page" data-portal-panel="access" hidden>
      <div class="portal-page-title"><span class="portal-kicker">Permission model</span><h1>Role-based user access</h1><p>Access is limited by responsibility, class assignment, and content approval status.</p></div>
      <div class="access-grid">
        <article class="access-card"><span class="access-icon" aria-hidden="true">S</span><h2>Student</h2><ul><li>Join approved classes</li><li>Open assigned species and lessons</li><li>Complete guided activities and quizzes</li><li>See only personal progress and feedback</li><li>Generate personal laboratory reports</li></ul></article>
        <article class="access-card"><span class="access-icon" aria-hidden="true">I</span><h2>Instructor</h2><ul><li>Manage assigned classes</li><li>Assign approved learning activities</li><li>Review student progress and reports</li><li>Provide feedback and approve submissions</li><li>Submit content for administrator approval</li></ul></article>
        <article class="access-card"><span class="access-icon" aria-hidden="true">A</span><h2>Administrator</h2><ul><li>Approve instructor requests</li><li>Manage accounts, roles, and access status</li><li>Publish validated species packages</li><li>Control lessons and answer keys</li><li>Review system and content audit records</li></ul></article>
      </div>
      <div class="access-policy"><strong>Account policy:</strong> students use verified school identities and enrollment codes; instructors require administrator approval; administrator accounts are created internally and never through public registration.</div>
    </section>

    <section class="portal-page" data-portal-panel="about" hidden>
      <div class="portal-page-title"><span class="portal-kicker">System overview</span><h1>How AquaDissect works</h1><p>A browser-based learning system for selected aquatic organisms.</p></div>
      <div class="flow-grid">
        <article><span>01</span><h2>Access</h2><p>Register, verify identity, sign in, and enter an approved role workspace.</p></article>
        <article><span>02</span><h2>Select</h2><p>Choose an instructor-assigned species and learning activity.</p></article>
        <article><span>03</span><h2>Explore</h2><p>Inspect external and internal anatomy with interactive labels.</p></article>
        <article><span>04</span><h2>Practice</h2><p>Follow the guided dissection sequence and safety reminders.</p></article>
        <article><span>05</span><h2>Identify</h2><p>Complete structure checks and receive bounded feedback.</p></article>
        <article><span>06</span><h2>Report</h2><p>Save results, generate a laboratory report, and receive instructor review.</p></article>
      </div>
    </section>
  </main>

  <div class="portal-toast" id="portalToast" role="status" aria-live="polite"></div>
</div>
