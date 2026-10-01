    <section class="portal-page auth-editorial-page auth-page login-concept" data-portal-panel="login">
      <form class="auth-card auth-card--login" id="loginForm">
        <span class="portal-kicker">Your learning workspace</span>
        <h1>Welcome back</h1>
        <p class="portal-muted">Sign in to continue your learning journey.</p>
        @include('partials.google-access', ['intent' => 'signin'])
        @if(\App\Support\GoogleAccess::ready())<div class="auth-divider"><span>Or use your existing password</span></div>@endif

        <label class="portal-field" for="loginEmail">
          <span>Email address</span>
          <span class="portal-input-control"><i data-lucide="mail" aria-hidden="true"></i><input id="loginEmail" name="email" type="email" placeholder="Enter your email" autocomplete="username" required value="{{ app()->environment('production') ? '' : 'student@aquadissect.local' }}"></span>
        </label>
        <label class="portal-field" for="loginPassword">
          <span>Password</span>
          <span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input id="loginPassword" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required value="{{ app()->environment('production') ? '' : 'demo123' }}"><button class="password-visibility" type="button" data-password-toggle="loginPassword" aria-controls="loginPassword" aria-pressed="false">Show</button></span>
        </label>
        <div class="auth-options">
          <label><input name="remember" type="checkbox"> Remember me</label>
          <button class="portal-link" type="button" id="forgotPassword">Forgot password?</button>
        </div>
        <p class="portal-message" id="loginMessage" role="alert" hidden></p>
        <button class="portal-primary portal-full auth-submit" type="submit"><span>Sign in</span><i data-lucide="arrow-right" aria-hidden="true"></i></button>
        <div class="login-account-options">
          <p>New to AquaDissect?</p>
          <div class="login-account-links">
            <button class="portal-link" type="button" data-portal-page="activate">Activate student account</button>
            <button class="portal-link" type="button" data-portal-page="register">Request instructor access</button>
          </div>
          <p class="portal-muted">{{ \App\Support\GoogleAccess::ready() ? 'Students: use your registered Google email or activation code.' : 'Students: use the activation code provided by your administrator.' }}</p>
        </div>
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
        <p class="auth-security-note"><i data-lucide="shield-check" aria-hidden="true"></i><span>Keep your account details private.</span></p>
        <a class="portal-link" href="{{ route('privacy') }}">Privacy policy</a>
      </form>
      <aside class="login-discovery" aria-labelledby="loginDiscoveryTitle">
        <div class="login-discovery-copy">
          <span class="portal-kicker">Virtual pre-laboratory</span>
          <h2 id="loginDiscoveryTitle"><span class="login-desktop-title">Explore anatomy.<br>Prepare with confidence.</span><span class="login-mobile-title">Observe. Identify. Prepare.</span></h2>
          <p class="login-desktop-description">Guided preparation for your next fish dissection.</p>
          <p class="login-mobile-description">Guided fish anatomy learning</p>
        </div>
        <figure class="login-discovery-figure">
          <img src="{{ rtrim(request()->getBaseUrl(), '/') }}/species/tilapia/natural.png" width="1536" height="1024" alt="Side-view educational illustration of a tilapia" decoding="async">
          <figcaption>Tilapia · External anatomy</figcaption>
        </figure>
        <p class="login-discovery-steps">Observe <span aria-hidden="true">/</span> Identify <span aria-hidden="true">/</span> Prepare</p>
        <p class="login-discovery-note">Supports faculty-supervised laboratory learning.</p>
      </aside>
    </section>
