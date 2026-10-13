    <section class="portal-page auth-editorial-page registration-page" data-portal-panel="activate">
      <article class="auth-specimen-card auth-specimen-card--internal" aria-label="Rabbitfish study focus">
        <div class="specimen-card-heading"><span><strong>Current study focus</strong><small>Rabbitfish · guided preparation</small></span><span class="specimen-number">Fig. 02</span></div>
        <figure class="specimen-figure">@include('partials.rabbitfish-focus')<figcaption>Begin with the account supplied by your administrator.</figcaption></figure>
      </article>
      <form class="registration-card auth-card--registration" id="activationForm">
        <div class="registration-heading"><button class="auth-back-button" type="button" data-portal-page="login"><i data-lucide="arrow-left" aria-hidden="true"></i><span>Back to sign in</span></button><span class="portal-kicker">Student access</span><h1>Activate your account</h1><p>{{ \App\Support\GoogleAccess::ready() ? 'The administrator must provide your account first. Activate with the matching Google email or use a one-time code below.' : 'Use the email and one-time code supplied by the administrator. Codes expire after three days.' }}</p></div>
        <p class="page-guidance">After activation: open Home → Start orientation → Finish guide. If you have no account or code, ask your administrator; student self-registration is not available.</p>
        <div class="registration-grid">
          <div class="portal-wide">@include('partials.google-access', ['intent' => 'activate', 'label' => 'Activate with Google'])</div>
          @if(\App\Support\GoogleAccess::ready())<p class="portal-muted portal-wide">Google activates an account already supplied by the administrator. Prefer a password? Use your one-time code below.</p>@endif
          <label class="portal-field portal-wide"><span>Account email</span><span class="portal-input-control"><i data-lucide="mail" aria-hidden="true"></i><input name="email" type="email" autocomplete="username" required></span></label>
          <label class="portal-field portal-wide"><span>One-time activation code</span><span class="portal-input-control"><i data-lucide="key-round" aria-hidden="true"></i><input name="code" autocomplete="one-time-code" minlength="48" maxlength="48" required></span></label>
          <label class="portal-field"><span>New password</span><span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input name="password" type="password" autocomplete="new-password" minlength="8" required></span></label>
          <label class="portal-field"><span>Confirm password</span><span class="portal-input-control"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input name="passwordConfirmation" type="password" autocomplete="new-password" minlength="8" required></span></label>
        </div>
        <p class="portal-message" id="activationMessage" role="alert" hidden></p>
        <div class="registration-actions"><button class="portal-secondary" type="button" data-portal-page="login">Cancel</button><button class="portal-primary auth-submit" type="submit">Activate account</button></div>
      </form>
    </section>
