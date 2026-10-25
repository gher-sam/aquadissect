# Student access readiness — October 25, 2026

## Live inspection

The existing authenticated administrator session showed Rabbitfish v1.1.0 and
v1.0.0 in review. Safety & Tools v0.2.0 had 39 tools, 38 assets, no validated tools
and no reviews. The prior v0.1.0 was also in review. No approval was submitted.

AY 2026–2027, first-semester Section A had 36 enrolled learners and Section B had
18. Both were active, with no assigned instructor, safety module or species.
Displayed Section A roster accounts were pending and needed email for activation.
This was not a full per-learner activation audit or a signed-in student lesson run.

## Corrections

- People & sections → Enrolled students → Add email for activation accepts a
  verified email for an existing pending student without an email. Review the
  institutional ID and address in the confirmation before saving. The account stays
  pending. Issue and deliver the one-time activation code separately, or use the
  matching verified Google account. Existing emails cannot be replaced here.
- The server checks administrator access, current account state, institutional ID,
  and duplicate emails. Account updates and the audit record share a transaction
  and the existing delayed-clone mechanism. Credentials, identity, enrollments and
  existing results are retained.
- Approved Safety publication shows explicit section choices including academic
  year and semester. No section is preselected. Publish without assignments or
  choose only the intended active MB 304L sections. The server rejects stale,
  duplicate, invalid and archived destinations before changing publication state.
  Unselected sections retain their assignments and records.

## Operational work still required

1. Verify missing student emails against the existing roster and fill them through
   the new workflow; preserve account IDs and do not create replacements.
2. Identify and assign the responsible existing instructor for current A and B.
3. Obtain independent faculty decisions for Rabbitfish and Safety, including
   anatomy, species identity, hazards and the controlled laboratory procedure.
4. Publish approved Safety with only the intended section selections, then publish
   and assign the approved Rabbitfish version to the correct sections.
5. Deliver activation details privately and verify sign-in and orientation.
6. With a designated student account, verify Safety steps and the formative
   submission, confirmed synchronization, Rabbitfish access and retained results.
   Verify offline and physical-device behavior separately when those are required.

Code deployment does not publish learning content, supply missing email values,
assign a faculty member, or establish scientific or research validation.

## Engineering verification

Status: **Implemented but awaiting faculty and operational validation.**

- Full isolated PHP suite: 100 tests, 1,593 assertions passed. The first run
  identified an outdated service-worker version assertion; the shell and assertion
  now both use v14 so installations receive the revised offline shell.
- All 17 JavaScript tests passed; production frontend build passed. The existing
  optional Three.js chunk-size warning remains.
- Vite was patched from 8.2.2 to 8.2.4 after the dependency audit identified
  development-server advisories. Standard clean installation and both full and
  production dependency audits passed with zero reported vulnerabilities.
- Local browser QA used synthetic records and a separate SQLite database: email
  review, cancellation, save, retained pending status and separate activation
  control; explicit unchecked section choices; selected-section confirmation and
  publication updating one section. No browser console errors were captured.
- The email confirmation was visually checked at 390 × 844. The inspected mobile
  page had no document-level horizontal overflow; roster tables retain their
  internal horizontal scrolling. Publication confirmation was visually checked
  at desktop size. These are browser checks, not physical-device validation.
- Backend tests cover authorization, duplicate emails, stale account state,
  retained credentials/enrollment, delayed-clone recording, subsequent activation,
  publication without assignments, invalid destinations and unchanged unselected
  assignments. The existing journey and pinned-progress checks also pass.

Production delivery and live interface verification are reported after release;
synthetic approvals used for QA are not real faculty evidence.
