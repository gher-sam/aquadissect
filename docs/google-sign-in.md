# Google sign-in

## Account access

- Existing active accounts: select **Continue with Google** using the email already on the account record, or continue with the existing password.
- Students: the administrator first provisions the roster account and enrollment. On **Activate student account**, use the matching verified Google email. Google activation creates no password. The existing one-time-code activation remains available for students who prefer password access.
- New instructors: connect Google on **Request instructor access**, confirm the name and employee ID, accept the educational-use agreement, and submit. The request stays pending until administrator approval. After approval, use Google to sign in.
- Program Heads and administrators: no public registration. Existing active accounts can connect their matching verified Google email.
- If an email does not match, ask the administrator to correct the verified account record. Connecting Google never changes an account role, enrollment, consent, or learning result.

Google sign-in needs an internet connection. Google verification is account access, not approval of academic credentials or research participation.

## Server configuration

Keep `GOOGLE_AUTH_ENABLED=false` until the OAuth client is configured and the production callback has been checked. Password access and existing registration remain available while disabled.

Create a Google OAuth web application client with production callback:

`https://aquadissect.com/auth/google/callback`

Use a separate local callback if local testing is required. Do not reuse another application's client or branding. Configure only `openid`, `email`, and `profile`; the application requests no Drive, Gmail, or Calendar access.

Put `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` only in the private server environment. Never commit credentials, paste secrets into chat, or put them in browser code. Complete the Google branding/audience configuration appropriate to the intended users. Basic profile sign-in and institutional eligibility are separate concerns.

The public privacy policy is at `https://aquadissect.com/privacy`, linked from sign-in. Use that same address in Google branding. The policy describes current implementation behavior; institution-specific retention requirements must be confirmed by the responsible administrator.

Production Google access was enabled at the user's explicit request on October 2, 2026. The live button and Google account chooser were verified; a completed matching-account callback remains to be checked. For future rollouts, check a designated matching account before enabling access. Before creating a persistent OAuth credential, obtain the requesting user's confirmation at the final creation action.

## Stored records and rollback

`external_identities` retains the provider, stable subject, account ID, verified email and timestamps. OAuth access/refresh tokens and avatars are not retained. Every identity mutation joins the normal 12-hour delayed clone. Existing passwords and academic records remain intact; roster activation clears the obsolete activation code and records the activation method.

OAuth callbacks validate session state. Instructor verification claims expire after ten minutes and are consumed on successful submission. The verified claim supplies the email; client fields cannot choose another email or role.

Disable the feature flag to withdraw Google access. Retain identity records and nullable passwords during rollback. Google-only accounts require Google to be restored or an explicitly authorized support process; do not fabricate password hashes or expose reset credentials.

The previously delivered user guide predates Google access. This document is its account-access supplement; it does not claim the original screenshots show the new feature.
