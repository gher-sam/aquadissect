# Student sign-in and password resets

The current October 25, 2026 decision uses the student ID as both the username and
initial temporary password. Email, OTP and private setup codes are deferred.

## First-time student

1. Open Sign in and enter the student ID in both the username and password fields.
2. On Choose a new password, enter and confirm a different private password.
3. Save it to activate the same roster account, then continue to Home and orientation.
4. For later sign-ins, use the student ID and the new private password.

The new password must have at least eight characters with a letter and number and
must differ from the student ID/temporary password. Course pages, content downloads,
activity submissions, reports and course APIs remain blocked until the change is
saved. The sign-in response contains no academic data while a change is required.
Google sign-in also respects this requirement. This is not public registration.

## Administrator and teacher support

Administrators use People & sections. Teachers open Class sections and expand
View students, safety status, and progress. Reset password and Password history
appear for authorized staff. Choose a reset reason, review the named student, and
confirm. The student then signs in with their ID as the temporary password and
must choose another private password. Previous online sessions lose course access.

Administrators can reset student accounts. An instructor must have an active
MB 304L section with that student actively enrolled. Program Heads, students and
unassigned instructors cannot reset or inspect password history. A reset retains
account identity, enrollment, results, class assignments and Google identity links.

Audit history records initial password preparation, staff resets and successful
password changes, with actor/target account IDs, actor role, time and a controlled
reset reason. It never stores plaintext passwords. The interface shows the most
recent 50 events; older audit records remain in the database. Password values are
stored only as hashes. Temporary setup records are invalidated on staff reset.

## Rollout

Enable STUDENT_ID_INITIAL_PASSWORD_ENABLED=true. Keep STUDENT_PASSWORD_SETUP_ENABLED,
STUDENT_SETUP_ENABLED and STUDENT_SETUP_DELIVERY_VERIFIED false for this workflow.
The older private-code and email implementations are retained, disabled.

New roster imports initialize only newly created students. Reimporting an existing
student or enrolling an active student in another section does not reset credentials.
Existing active accounts and staff accounts are not initialized automatically.

For the already approved pending roster, preview with:

    php artisan students:initialize-passwords --actor=<active-admin-id> --academic-year=2026-2027 --semester=1 --section=A --section=B

After checking the candidate count, repeat with --apply. This scoped operation
changes only pending students without a previous required password change. It
preserves pending status until the student chooses a new password and records an
initialization audit event for each account. Repeating it does not reset accounts
already initialized or active. Command output contains counts, never passwords.

The additive users migration applies to production and the delayed clone before
replicated user/audit changes are applied. Session password revisions invalidate
older online access after reset or password change. An already downloaded offline
copy cannot be remotely erased while a device stays disconnected; server access
and synchronization require a valid current session when it reconnects.

The Rabbitfish and Safety & Tools review, publication, section assignments, safety
prerequisite and research-consent requirements remain separate from account access.

## Engineering verification - October 25, 2026

Status: **Implemented but awaiting validation**. The full suite passed 145 PHP tests
and 2,202 assertions, plus all 17 JavaScript tests and the production build. Tests
cover mandatory first-password replacement, denied course/API access beforehand,
native form completion, password rules, scoped administrator/instructor resets,
audit history, forbidden roles and unrelated/archived sections, old-session
revocation, concurrent reset protection, idempotent scoped initialization, retained
active credentials and the complete orientation/safety/Rabbitfish/results journey.
Both active and pending Google-linked students remain subject to required changes.
Faculty approvals in course tests are synthetic and isolated from production.

Desktop and 390 x 844 mobile synthetic browser accounts signed in with their student
ID as the temporary password, reached the mandatory change page, saved a new
password and reached orientation. The mobile form had no horizontal overflow.
The assigned-teacher browser flow completed a reviewed reset, displayed the audit
events and caused the student's older browser session to return to sign-in.
No browser errors were observed. Evidence is retained under
`C:/Temp/aquadissect-otp-20261025/output/playwright/initial-*`. These engineering
checks do not establish physical-device performance, real-user acceptance or
learning effectiveness. No real student password appears in source or QA evidence.
