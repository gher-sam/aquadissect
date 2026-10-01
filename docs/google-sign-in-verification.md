# Google sign-in engineering checks

October 2, 2026. Status: **Implemented but awaiting provider configuration and live Google callback verification**. These checks do not establish academic eligibility, research consent, faculty validation, or learning effectiveness.

## Completed checks

- Full PHP suite: 76 tests, 1,025 assertions, passing.
- Production frontend build: passing. The existing optional 3D chunk-size warning remains.
- New PHP files and changed JavaScript syntax, authenticated route inspection, and whitespace checks: passing.
- Existing active accounts retain password access; all four roles can connect a verified matching Google account without a role change.
- Roster activation preserves enrollments, assignments and results, removes the obsolete activation token, and records the method. New instructor requests remain pending and cannot choose an elevated role or another email.
- Invalid state, expiry, cancellation, provider failure, unverified email, account collisions, duplicate submission, and rate limits do not create unauthorized access.
- Google-only accounts have no password fallback. Feature-disabled mode preserves existing password and instructor-request behavior.
- Existing records survive the SQLite password-column rebuild. Foreign-key checks are suspended only for that rebuild, outside the migration transaction, then restored.
- Identity create/update records replay after 12 hours; full clone refresh includes identities and nullable passwords.
- Rendered sign-in, activation and instructor request/detail previews checked in Chrome at desktop, 768px tablet and 390px mobile sizes. Figtree, solid colors, no horizontal overflow. Google link is labeled, keyboard reachable, and has a 52px rendered target. The verified email field is read-only; password fields are absent in the Google instructor flow. No console errors were recorded in these previews.
- OAuth paths bypass shared service-worker fallback. Signing in remains an online operation. No new animation was added; existing reduced-motion behavior is retained.

## Release boundary

The code can be deployed with `GOOGLE_AUTH_ENABLED=false`. Google buttons and mandatory Google registration appear only after the provider is configured and enabled. Production credentials must remain outside source control.

Google setup is assigned to the user-selected owner account. The owner supplied an existing OAuth web-client credential. It is stored only in private production configuration, with a root-only configuration backup. The callback matches the production HTTPS address. Client and secret presence were verified without printing their values. Google access remains disabled pending Google audience publication and a real matching-account callback check.

Production configuration checks retained 8 users, 5 enrollments and 4 lesson results, with zero case-insensitive email conflicts. A public privacy page was added to explain the implemented data handling and supply Google's required policy destination. This is a service disclosure, not institutional ethics approval or content validation.

The ordinary local MySQL service was unavailable during browser review. UI previews were rendered by the application with an isolated, nonfunctional provider configuration; account behavior was exercised in the database-backed test suite. These previews do not constitute a real Google callback test.

Before enabling Google access, verify production email conflicts, the configured HTTPS callback and a designated matching account, then confirm sign-in and role access without changing live academic records. Preserve existing signed-in sessions during verification.
