    <section class="portal-page auth-editorial-page registration-page" data-portal-panel="register">
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

        <p class="registration-process-note"><strong>One form</strong> Complete your account and academic details, then submit them for administrator review.</p>

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
