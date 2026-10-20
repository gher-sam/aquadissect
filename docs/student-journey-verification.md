# Student journey verification — October 20, 2026

Status: **Implemented but awaiting faculty/research validation.**
Engineering verification uses synthetic accounts and a separate local SQLite
database. No live student, enrollment, approval, override or learning record was
changed for testing. Real Rabbitfish and Safety & Tools remain subject to their
existing independent review, publication, assignment and prerequisite gates.

## Verified path

| Stage | Engineering evidence |
|---|---|
| Provision and activate | API integration provisions a pending account, activates once, rejects reused codes, and signs in. Browser inspection checks activation fields and guidance without setting a real password. |
| Orientation | Browser completes five steps and shows Viewed. API verifies orientation has no lesson-result side effect and another account cannot see it. |
| Unavailable content | Before local QA publication, student Home explains no assigned lesson. API rejects draft/review module and Rabbitfish manifests. |
| Safety prerequisite | A published, assigned QA Rabbitfish lesson stays locked before safety completion. |
| Formative safety activity | Browser submits an early zero-score attempt. Feedback correctly requires remaining steps. API retains that zero and rejects a duplicate identifier used for another section. |
| Module download and offline resume | Browser downloads the illustrated v0.2.0 QA module, with downloader checksum checks, and opens images. Stopping the local server simulates a network outage. A queued step survives opening the offline shell and resumes at the first unfinished step. |
| Queued completion | Browser finishes required steps while the server is unavailable. Feedback says waiting for synchronization; anatomy remains locked. |
| Synchronization | Restoring the server and reopening the online page synchronizes one coalesced progress record; Safety & Tools shows Complete and the assigned Rabbitfish lesson opens. |
| Rabbitfish activities | Browser traverses orientation, spine safety, external targets, morphometric guidance, gills, incision path, body cavity, major organs, observation targets and the knowledge check. Required target lists provide an alternative to illustration hotspots. |
| Quiz and saved progress | Browser double-click submission produces a single saved 4/4 result. Reports show the lesson result and completed safety record. Sign-out/sign-in retains the score; API integration additionally checks retained orientation and safety completion. |

Synthetic review records in the named QA database test publication mechanics.
They are explicitly marked QA-only and are not actual faculty evidence.

## Corrections

- Safety activity feedback now follows confirmed completion, rather than claiming
  completion merely because an activity was submitted.
- The API exposes whether the formative safety activity was submitted, scoped to
  the learner, section and version. This supports finishing remaining steps later.
- Failed step saves restore the prior local step set. Saves are guarded while in
  flight; navigation during a save does not advance an unrelated step.
- Offline completion requires all exact required step IDs and an activity
  submission. Queued status stays distinct from server-confirmed completion.
- Sign-in refreshes the account identity used by offline queues. Context snapshots
  update after progress changes; staff catalogs are excluded from that snapshot.
- Client completion no longer unlocks every species or changes its section.
  Availability comes from the server after synchronization.
- Returning from the module refreshes the current Safety/Offline page.
- The offline shell cache advances to v13 so existing installations receive the
  corrected application shell while retained downloaded learning assets remain.
- Both safety and lesson retries reject an attempt identifier from another section.
- Quiz submission guards against double clicks. A failed dashboard refresh after
  a successful save no longer presents the learning submission as failed.
- Published Rabbitfish cards use a status-appropriate description rather than a
  retained draft publication notice. Source package content remains immutable.
  Safety Home guidance no longer hardcodes the older 15-item register.

## Checks

- Full PHP suite: **96 tests, 1,547 assertions passed**.
- JavaScript suite: **17 tests passed**.
- Production frontend build passed; existing large Three.js chunk warning remains.
- Changed PHP/JavaScript syntax, protected module routes and whitespace checked.
- Browser desktop and 390 × 844 mobile safety layout checked, with no document
  horizontal overflow at the inspected mobile step. Images loaded during the
  offline scenario. Representative screenshots are retained outside the release.
- Final mobile lesson card has loaded Figtree, no broken images or document
  overflow. The navigation drawer opens and Escape closes it, restoring focus
  to Open navigation. Final page console inspection captured no errors.

The first clean-checkout run lacked framework cache directories and a test key;
its preview environment also disabled cloning. These setup issues were corrected
before the successful sequential full run. The original checkout and an initial
release copy on its drive had damaged Git objects/files. A fresh verified copy on
another drive was used; unrelated original edits were preserved.

## Limits and follow-up

- No real Google account callback, physical device matrix, complete screen-reader
  session or new faculty/content validation was performed.
- Offline testing used local server loss, not a physical airplane-mode device.
  Full offline Rabbitfish media playback was not separately exercised.
- The failed-save rollback and dashboard-refresh failure recovery were
  source-reviewed; forced HTTP failure injection was not separately browser-tested.
- Observation notebook drafts and unfinished anatomy browsing/target selections
  are session-only. Notebook JSON/CSV downloads are the approved retention path;
  completed formative results, safety steps and tutorial progress are account-saved.
- Missing student emails, real activation delivery, faculty reviews, publication
  and section assignments still require authorized operational completion.
- Engineering verification does not establish preparedness gains or effectiveness.
