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
      <button type="button" data-portal-page="classes" data-role-nav="student,instructor,admin"><i class="portal-nav-icon" data-lucide="school"></i><span data-nav-label="classes">My Sections</span></button>
      <button type="button" data-portal-page="roster" data-role-nav="admin"><i class="portal-nav-icon" data-lucide="users-round"></i><span data-nav-label="roster">Roster &amp; Sections</span></button>
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

    <section class="portal-page auth-editorial-page auth-page" data-portal-panel="login">
      <article class="auth-specimen-card" aria-labelledby="loginSpecimenTitle">
        <div class="specimen-card-heading">
          <span><strong>Lateral view</strong><small>Tilapia · external morphology</small></span>
          <span class="specimen-number">Fig. 01</span>
        </div>
        <figure class="specimen-figure">
          <img src="{{ rtrim(request()->getBaseUrl(), '/') }}/species/tilapia/external-anatomy.png" alt="Educational illustration showing the labeled external anatomy of a tilapia">
          <figcaption id="loginSpecimenTitle">Observe the specimen before entering the guided laboratory workspace.</figcaption>
        </figure>
        <div class="specimen-scale" aria-hidden="true"><span>0</span><i></i><i></i><i></i><i></i><span>20 cm</span></div>
        <div class="specimen-footer">
          <p>External structures support orientation, movement, protection, and environmental awareness.</p>
          <span>Observe · Identify · Prepare</span>
        </div>
      </article>

      <form class="auth-card auth-card--login" id="loginForm">
        <div class="auth-card-brand" aria-hidden="true"><span></span></div>
        <span class="portal-kicker">Marine Biology Learning Portal</span>
        <h1>Sign in to continue</h1>
        <p class="portal-muted">Access your assigned species, guided activities, saved progress, and learning resources.</p>

        <label class="portal-field" for="loginEmail">
          <span>Email address</span>
          <span class="portal-input-control"><i data-lucide="mail" aria-hidden="true"></i><input id="loginEmail" name="email" type="email" autocomplete="username" required value="{{ app()->environment('production') ? '' : 'student@aquadissect.local' }}"></span>
        </label>
        <label class="portal-field" for="loginPassword">
          <span>Password</span>
          <span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input id="loginPassword" name="password" type="password" autocomplete="current-password" required value="{{ app()->environment('production') ? '' : 'demo123' }}"><button class="password-visibility" type="button" data-password-toggle="loginPassword" aria-controls="loginPassword" aria-pressed="false">Show</button></span>
        </label>
        <div class="auth-options">
          <label><input name="remember" type="checkbox"> Remember me</label>
          <button class="portal-link" type="button" id="forgotPassword">Forgot password?</button>
        </div>
        <p class="portal-message" id="loginMessage" role="alert" hidden></p>
        <button class="portal-primary portal-full auth-submit" type="submit"><span>Sign in</span><i data-lucide="arrow-right" aria-hidden="true"></i></button>
        <div class="auth-divider"><span>First time here?</span></div>
        <button class="portal-secondary portal-full" type="button" data-portal-page="activate">Activate student account</button>
        <button class="portal-link portal-full" type="button" data-portal-page="register">Request instructor access</button>
        <p class="portal-muted">Student accounts are created by the administrator. Ask for your one-time activation code.</p>
        @unless(app()->environment('production'))
        <details class="demo-access">
          <summary>Open local demonstration access</summary>
          <div>
            <button type="button" data-demo-role="student" data-demo-email="student@aquadissect.local" data-demo-password="demo123">Student</button>
            <button type="button" data-demo-role="instructor" data-demo-email="instructor@aquadissect.local" data-demo-password="demo123">Instructor</button>
            <button type="button" data-demo-role="admin" data-demo-email="admin@aquadissect.local" data-demo-password="demo123">Administrator</button>
          </div>
          <small>Demonstration accounts use password <strong>demo123</strong>.</small>
        </details>
        @endunless
        <p class="auth-security-note"><i data-lucide="shield-check" aria-hidden="true"></i><span>Laravel sessions and hashed passwords protect account access.</span></p>
      </form>
    </section>

    <section class="portal-page auth-editorial-page registration-page" data-portal-panel="register" hidden>
      <article class="auth-specimen-card auth-specimen-card--internal" aria-labelledby="registrationSpecimenTitle">
        <div class="specimen-card-heading">
          <span><strong>Internal anatomy</strong><small>Tilapia · sagittal educational view</small></span>
          <span class="specimen-number">Fig. 02</span>
        </div>
        <figure class="specimen-figure">
          <img src="{{ rtrim(request()->getBaseUrl(), '/') }}/species/tilapia/internal-anatomy.png" alt="Educational illustration showing the labeled internal anatomy of a tilapia">
          <figcaption id="registrationSpecimenTitle">A guided preparation environment for recognizing structures and following procedures.</figcaption>
        </figure>
        <div class="specimen-scale" aria-hidden="true"><span>0</span><i></i><i></i><i></i><i></i><span>20 cm</span></div>
        <div class="specimen-footer">
          <p>Educational illustrations support preparation; faculty-approved content and supervised observation remain authoritative.</p>
          <span>Explore · Understand · Document</span>
        </div>
      </article>

      <form class="registration-card auth-card--registration" id="registrationForm">
        <div class="registration-heading">
          <button class="auth-back-button" type="button" data-portal-page="login"><i data-lucide="arrow-left" aria-hidden="true"></i><span>Back to sign in</span></button>
          <span class="portal-kicker">Faculty account request</span>
          <h1>Request instructor access</h1>
          <p>Instructor requests require administrator approval. Students receive accounts from the administrator.</p>
        </div>

        <ol class="registration-progress" aria-label="Registration process">
          <li class="is-active"><span>1</span><small>Account</small></li>
          <li><span>2</span><small>Academic access</small></li>
          <li><span>3</span><small>Review</small></li>
        </ol>

        <div class="registration-grid">
          <label class="portal-field" for="registrationFirstName"><span>First name</span><span class="portal-input-control"><i data-lucide="user" aria-hidden="true"></i><input id="registrationFirstName" name="firstName" required autocomplete="given-name"></span></label>
          <label class="portal-field" for="registrationLastName"><span>Last name</span><span class="portal-input-control"><i data-lucide="user" aria-hidden="true"></i><input id="registrationLastName" name="lastName" required autocomplete="family-name"></span></label>
          <label class="portal-field portal-wide" for="registrationEmail"><span>Institutional email</span><span class="portal-input-control"><i data-lucide="mail" aria-hidden="true"></i><input id="registrationEmail" name="email" type="email" required autocomplete="username"></span><small>Use the email associated with your institution or class record.</small></label>
          <label class="portal-field" for="registrationIdentity"><span>Employee ID</span><span class="portal-input-control"><i data-lucide="badge" aria-hidden="true"></i><input id="registrationIdentity" name="identityNumber" required autocomplete="off"></span></label>
          <label class="portal-field" for="registrationPassword"><span>Password</span><span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input id="registrationPassword" name="password" type="password" required minlength="8" autocomplete="new-password"><button class="password-visibility" type="button" data-password-toggle="registrationPassword" aria-controls="registrationPassword" aria-pressed="false">Show</button></span></label>
          <label class="portal-field" for="registrationPasswordConfirmation"><span>Confirm password</span><span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input id="registrationPasswordConfirmation" name="passwordConfirmation" type="password" required minlength="8" autocomplete="new-password"><button class="password-visibility" type="button" data-password-toggle="registrationPasswordConfirmation" aria-controls="registrationPasswordConfirmation" aria-pressed="false">Show</button></span></label>
          <ul class="password-requirements portal-wide" id="passwordRequirements" aria-live="polite">
            <li data-password-rule="length"><i data-lucide="circle" aria-hidden="true"></i>At least 8 characters</li>
            <li data-password-rule="letter"><i data-lucide="circle" aria-hidden="true"></i>Includes a letter</li>
            <li data-password-rule="number"><i data-lucide="circle" aria-hidden="true"></i>Includes a number</li>
          </ul>
          <label class="portal-check portal-wide"><input name="agreement" type="checkbox" required><span>I agree to use AquaDissect only for approved educational activities and to follow laboratory safety requirements.</span></label>
        </div>
        <div class="registration-note"><strong>Instructor access:</strong> the request remains pending until an administrator verifies and approves it.</div>
        <p class="portal-message" id="registrationMessage" role="alert" hidden></p>
        <div class="registration-actions">
          <button class="portal-secondary" type="button" data-portal-page="login">Cancel</button>
          <button class="portal-primary auth-submit" type="submit"><span>Submit registration</span><i data-lucide="arrow-right" aria-hidden="true"></i></button>
        </div>
      </form>
    </section>

    <section class="portal-page auth-editorial-page registration-page" data-portal-panel="activate" hidden>
      <article class="auth-specimen-card auth-specimen-card--internal" aria-label="Tilapia internal anatomy illustration">
        <div class="specimen-card-heading"><span><strong>Internal anatomy</strong><small>Tilapia · educational view</small></span><span class="specimen-number">Fig. 02</span></div>
        <figure class="specimen-figure"><img src="{{ rtrim(request()->getBaseUrl(), '/') }}/species/tilapia/internal-anatomy.png" alt="Educational illustration of tilapia internal anatomy"><figcaption>Begin with the account supplied by your administrator.</figcaption></figure>
      </article>
      <form class="registration-card auth-card--registration" id="activationForm">
        <div class="registration-heading"><button class="auth-back-button" type="button" data-portal-page="login"><i data-lucide="arrow-left" aria-hidden="true"></i><span>Back to sign in</span></button><span class="portal-kicker">Student access</span><h1>Activate your account</h1><p>Use the email and one-time code supplied by the administrator. Codes expire after three days.</p></div>
        <div class="registration-grid">
          <label class="portal-field portal-wide"><span>Institutional email</span><span class="portal-input-control"><i data-lucide="mail" aria-hidden="true"></i><input name="email" type="email" autocomplete="username" required></span></label>
          <label class="portal-field portal-wide"><span>One-time activation code</span><span class="portal-input-control"><i data-lucide="key-round" aria-hidden="true"></i><input name="code" autocomplete="one-time-code" minlength="48" maxlength="48" required></span></label>
          <label class="portal-field"><span>New password</span><span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input name="password" type="password" autocomplete="new-password" minlength="8" required></span></label>
          <label class="portal-field"><span>Confirm password</span><span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input name="passwordConfirmation" type="password" autocomplete="new-password" minlength="8" required></span></label>
        </div>
        <p class="portal-message" id="activationMessage" role="alert" hidden></p>
        <div class="registration-actions"><button class="portal-secondary" type="button" data-portal-page="login">Cancel</button><button class="portal-primary auth-submit" type="submit">Activate account</button></div>
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
      <div class="portal-page-title"><span class="portal-kicker">MB 304L offerings</span><h1>My Sections</h1><p>One course is reused across semesters while each section keeps its own roster, assignments, and results.</p></div>
      <div id="classesContent"></div>
    </section>

    <section class="portal-page" data-portal-panel="roster" hidden>
      <div class="portal-page-title"><span class="portal-kicker">Administrator workspace</span><h1>Roster &amp; Sections</h1><p>Create term sections, provision students, assign instructors, and review conflicts before import.</p></div>
      <div id="rosterContent"></div>
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
        <article class="access-card"><span class="access-icon" aria-hidden="true">S</span><h2>Student</h2><ul><li>Activate an administrator-provided account</li><li>Open assigned species and lessons</li><li>Complete guided activities and quizzes</li><li>See only personal progress and feedback</li></ul></article>
        <article class="access-card"><span class="access-icon" aria-hidden="true">I</span><h2>Instructor</h2><ul><li>Use administrator-assigned MB 304L sections</li><li>Assign approved learning activities</li><li>Review section rosters and formative results</li><li>Submit content for administrator approval</li></ul></article>
        <article class="access-card"><span class="access-icon" aria-hidden="true">A</span><h2>Administrator</h2><ul><li>Provision student accounts and sections</li><li>Assign instructors and review roster conflicts</li><li>Approve instructor requests</li><li>Publish validated species packages</li><li>Review roster and content audit records</li></ul></article>
      </div>
      <div class="access-policy"><strong>Account policy:</strong> administrators create student accounts and provide one-time activation codes; instructors request approval; administrator accounts are created internally. Research consent is separate from account activation.</div>
    </section>

    <section class="portal-page" data-portal-panel="about" hidden>
      <div class="portal-page-title"><span class="portal-kicker">System overview</span><h1>How AquaDissect works</h1><p>A browser-based learning system for selected aquatic organisms.</p></div>
      <div class="flow-grid">
        <article><span>01</span><h2>Access</h2><p>Activate an administrator-provided student account or request instructor access, then sign in.</p></article>
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
