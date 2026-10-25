# Student email setup

This addition lets a student claim an existing pending roster account without an
email. It does not create accounts, enrollments, approvals or research consent.

## Administrator workflow

In People & sections, open **Prepare private student setup codes**. Choose the
correct year/semester/section and explicitly select eligible students. Review the
selection, prepare codes, and deliver each code only to its matching institutional
ID through the existing private workflow. Codes appear only in that response.
Generating replacements invalidates prior setup codes and incomplete setup sessions.
Existing account emails, credentials, enrollments and results are not changed.

## Student workflow

On Activate your account: enter student ID and private setup code, enter an email
you can open, request the six-digit inbox code, verify it, and create/confirm a
password. Sign in normally afterward and open Home → Start orientation.

Setup codes expire after seven days. OTPs expire after ten minutes, permit five
attempts, and can be requested once per minute up to five times per hour per
student. Only the newest OTP works. Changing the email requires new verification.
Finish password setup within five minutes of verification and within the overall
30-minute claiming session. Cancellation leaves the account pending and permits
starting again with the same unconsumed setup code.

Existing email/password activation codes and Google access remain available.
Students with an already recorded email use those existing workflows; this is not
an email replacement or account-recovery feature.

## Delivery configuration and activation gate

Use the existing provider's authenticated SMTP settings in the private production
environment: MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME,
MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME. Do not put real credentials in
source, reports, command output or screenshots. Configure encryption according to
the provider, using TLS and a valid server certificate. The sender must be authorized
by that provider; configure any required domain authentication with its owner.

Keep STUDENT_SETUP_ENABLED=false and STUDENT_SETUP_DELIVERY_VERIFIED=false during
setup. `php artisan student-setup:status` prints readiness booleans only. With
authenticated SMTP configured, `php artisan student-setup:test-email <recipient>`
sends a requested test without changing student records or enabling the feature.
The operator must confirm actual inbox receipt; transport acceptance is insufficient.
Only then set both flags true and refresh the production configuration cache.

The feature fails closed for missing SMTP authentication, unverified delivery or
log/array/failover transports. It never treats log output as successful delivery.
Disabling either flag stops student setup while retaining existing sign-in and
activation. Do not remove live account or enrollment data to roll back application
code; retain the additive tables until the retention decision is reviewed.

## Interfaces and retained data

- Administrator POST `/api/admin/student-setup-codes`, body `userIds`, returns
  one-time private codes and expiries for the selected eligible accounts.
- Guest `/api/student-setup/status`, `/claim`, `/email`, `/verify`, `/complete`,
  `/cancel` use the same protected application session and CSRF mechanism. Only
  status is GET; mutations are POST. Messages and status responses expose no hashes.
- Private setup and challenge tables are separate from legacy activation tokens.
  Hashes protect setup and OTP secrets; temporary email data is encrypted. No
  setup/challenge rows enter the delayed clone, shared learning reports or offline
  application cache. Expired temporary rows are pruned hourly.
- Successful completion atomically saves the verified email, hashed password,
  activation status, setup-code consumption and role-only audit record. Existing
  model events replicate the durable user/audit changes through the normal outbox.
  A locked account fingerprint rejects changes to the identity, credentials or
  access state made during setup, including a pending account's password reset.

## Engineering verification - October 25, 2026

Status: **Implemented but awaiting validation**. Automated tests cover successful
activation, administrator authorization, atomic issuance, hashed/encrypted state,
browser binding, cancellation, expired/replaced/consumed codes, five wrong attempts,
resend invalidation/cooldown/hourly limits, duplicate email, failed delivery and
account changes during setup. Existing activation, Google access, enrollment,
results and content prerequisite tests are retained in the full suite.

Synthetic browser checks used an isolated SQLite database and local SMTP capture,
not real student records or a real inbox. Administrator issuance and the complete
student workflow passed at desktop and 390 x 844 mobile viewport sizes. A synthetic
student signed in afterward and saw its retained class and Start orientation.
Mobile completion had no horizontal overflow or browser errors. Evidence is retained
in the isolated QA workspace `C:/Temp/aquadissect-otp-20261025/output/playwright/`.
These checks do not establish physical-device performance, real-user acceptance,
faculty validation, research consent or learning effectiveness.

## Current operational prerequisite

The selected sending service is Google email. Use smtp.gmail.com, port 465,
MAIL_SCHEME=smtps, and the authorized Google sender as both MAIL_USERNAME and
MAIL_FROM_ADDRESS. MAIL_PASSWORD must be a private Google app password, never the
normal Google password. App-password availability depends on the account's
2-Step Verification and organization policies. Existing Google sign-in credentials
and openid/email/profile permissions do not authorize Gmail sending and remain
unchanged. No hosting email plan is needed for this route.

Google account sign-in and security credential creation require the account
owner's direct participation. Configure the app credential privately and confirm
actual arrival in the designated test inbox before setting either activation flag.

The inspected live configuration uses the log mailer and has no SMTP host or
credentials. A mail-server plugin is installed, but its domain database is absent
and the Dovecot service is failed. This does not establish an operational sending
service for AquaDissect. No SMTP credentials, mailbox, domain mail records or
delivery confirmation have been invented. Keep the student flow disabled pending
working authorized mail-service access and an actual inbox delivery check.
