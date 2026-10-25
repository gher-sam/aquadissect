# Student ID and password access

The October 25, 2026 decision defers email setup. A student's existing institutional
ID is the username. Email and OTP are not required for this access route.

## Administrator

In People & sections, open **Prepare private student setup codes**, choose the
correct section, explicitly select eligible pending students without email, and
prepare their codes. Copy the one-time response and deliver each student's own
code privately. Replacement codes invalidate earlier codes and incomplete setup
sessions. No shared initial password is assigned and no roster is activated in bulk.
The Add one student form also accepts a blank email and retains pending status.

## Student

1. Open Activate student account.
2. Enter the student ID and private setup code supplied by the administrator.
3. Choose and confirm a password of at least eight characters with a letter and a
   number. Do not share it.
4. Sign in using the student ID and that password. Open Home → Start orientation.

Setup codes expire after seven days and are consumed only on successful activation.
The claiming browser session lasts 30 minutes. Cancellation leaves the account
pending and the code available for another attempt. Expired or replaced codes,
another browser session, or account changes during setup prevent completion.
Returning students sign in normally; existing account emails and Google access
remain available. A Google-only account without a password still uses Google.
Forgotten-password or missing-code cases go to the responsible administrator.

## Deployment and safeguards

Set STUDENT_PASSWORD_SETUP_ENABLED=true only after engineering checks and deployment.
Keep STUDENT_SETUP_ENABLED=false and STUDENT_SETUP_DELIVERY_VERIFIED=false while
email is deferred. Password setup does not call a mailer or change email or its
verification timestamp. Disabling password setup prevents new claims/completions;
existing active password accounts continue to sign in.

Password-only requests use `/api/student-password-setup/status`, `/claim`, `/complete`
and `/cancel`. The existing administrator code issuance is reused. The server stores
the chosen setup method and rejects switching an email session into a password-only
completion or vice versa. Existing hashes, session binding, expiry, account locks,
account-change checks and audit events remain in place. Login accepts `identifier`
for student ID or email, and preserves the earlier `email` request field for clients
using existing email/password access. Student ID lookup is case-insensitive and
does not enable staff ID login or bypass pending-account approval.

Activation updates the same account, consumes the setup code and records the
activation method in one transaction. IDs, enrollment, results and academic gates
are retained. Temporary setup state is excluded from the delayed clone and offline
cache; durable account/audit changes keep the existing delayed replication path.

Rabbitfish and Safety & Tools still require their independent faculty review,
administrator publication and section assignments. Account activation and
orientation do not grant research consent or complete the safety prerequisite.

## Engineering verification - October 25, 2026

Status: **Implemented but awaiting validation**. The full PHP suite passed 132 tests
and 1,993 assertions. This includes activation without email, case-insensitive
student ID sign-in, email/password compatibility, pending and Google-only account
rejection, code/session expiry and reuse, browser binding, cancellation, password
rules, changed accounts, method separation, optional-email provisioning and the
complete orientation → safety → Rabbitfish → saved-results journey. Faculty
approvals in the course test are explicitly synthetic and isolated from production.
No mail is sent by the password-only journey. All 17 JavaScript tests and the
production build passed; the existing optional 3D viewer size notice is unchanged.

Two synthetic pending accounts completed administrator code issuance, password
setup and student-ID sign-in in desktop and 390 x 844 mobile browser viewports.
Both reached their retained class and Start orientation; no browser errors or
mobile horizontal overflow were observed. Evidence is retained under
`C:/Temp/aquadissect-otp-20261025/output/playwright/password-*` in the isolated QA
workspace. This is not physical-device testing, real-student acceptance or proof
of learning effectiveness. The deployment preserves live accounts and records;
no real-student codes or shared passwords are embedded in source or QA evidence.
