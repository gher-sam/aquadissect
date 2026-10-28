# Offline and release reliability verification

October 28, 2026. This increment implements the four authorized reliability items.
It does not change enrollment, lesson publication, academic prerequisites, research
participation or completed learning records. It adds no database migration.

## Changes

- Sign-out removes local identity and catalog context immediately, even when the
  connection is unavailable. A local marker and a logout-only cookie prevent old
  context restoration. Reconnection invalidates the server session before returning
  to sign-in. This does not claim remote erasure of disconnected downloaded media.
- Queued lesson and safety records remain account-bound. Successful records alone
  are removed after server confirmation. Individual rejected records retain an
  error and attempt time; other valid work continues. Network, session, rate-limit
  and server errors stop the current run with the remaining work retained.
- A learner can inspect pending counts/errors and retry synchronization. Account
  guidance describes the enabled access policy and existing scoped reset history.
- The standard release script requires isolated PHP tests, JavaScript tests,
  deployment recovery scenarios and a production asset build. An additional cloud
  workflow was prepared privately but could not be published: the saved GitHub
  OAuth login lacks workflow permission. Required local release checks remain
  enforced; no cloud CI job is claimed as enabled.
- Server deployment builds a candidate outside the live checkout. Previous code,
  dependencies, compiled assets and bootstrap caches can be recovered on failure.
  Health checks cover the application, sign-in, manifest and main script. No database
  restore, reverse migration or learner-storage replacement occurs during recovery.
  Unchanged content setup is not replayed during an unrelated code release, avoiding
  incidental edits to existing class and module metadata.

## Evidence

The automated suite includes logout reconnection before private API access,
preserved credentials/enrollment/results, policy-specific guidance, queue ownership,
rejected-then-valid records, transient failures, and sign-out during synchronization.
Synthetic deployment cases cover success, build failure, activation failure, failed
health checks, and failed recovery. Fixture learner records must remain unchanged
in all five scenarios. Failed recovery must keep maintenance enabled.

Synthetic desktop browser checks exercise actual IndexedDB queues with one rejected
and one valid record in each store. Controlled HTTP 403/422 responses are deliberate
fixtures. They establish queue recovery behavior rather than faculty decisions or
real student outcomes. Offline sign-out and reconnect are checked separately.
Screenshots and UI evidence are retained privately under output/playwright/reliability-*.

Application verification passed 148 PHP tests, 28 JavaScript tests and the production
build in isolated QA. All five deployment scenarios also passed on Linux. Release
validation installs the declared lockfiles rather than trusting an existing local
dependency directory. It does not copy a production environment or use live data.

Desktop browser checks confirmed that both later valid records synchronize after
the deliberately rejected lesson/safety records; the two rejected records remain
visible and retryable. Offline sign-out cleared the current account and context,
retained a logout intent, and completed server sign-out on reconnect; the subsequent
session API returned HTTP 401. Expected HTTP fixture rejections are not uncaught
application errors. A 390 by 844 mobile browser viewport also confirmed the
retained-record list, no horizontal overflow, offline sign-out and HTTP 401 after
reconnection. The private before/after production comparison and actual
deployed revision must be checked before reporting this release live.

Real-user acceptance, physical-device evidence, independent faculty content
validation and research evaluation remain separate.
