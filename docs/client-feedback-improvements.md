# Client feedback improvements

Status: Implemented, awaiting final release verification and faculty content validation.
Authorized: November 2, 2026.

## Fish details, local names and anatomy revisions

Open Species content, then “Revise fish details and imagery”. Enter a new semantic version, public profile fields and local names with language, locality and source. Save creates a separate draft through the existing validated package importer. It does not modify the source package or automatically publish, activate or assign anything. Imported revisions use the data-only 2.5D viewer contract.

The student card and lesson header both open Fish details for the assigned version. Unknown fields are shown as not supplied; private/unknown profile fields are rejected. Descriptions, morphology, local names, taxonomy, safety notices and references are displayed as escaped text. The same versioned profile travels in the existing manifest/offline package.

The image revision fields accept PNG/JPEG/WebP and re-encode them as metadata-free WebP. Replacing external/internal imagery requires a complete anatomy/hotspot JSON file; missing lesson targets and invalid hotspot coordinates are rejected. Originals remain intact. Reviewer guidance explicitly includes organ placement, hotspots, local names and calibration. New imagery remains disabled for students until independent package review and administrator publication.

Actual corrected Rabbitfish artwork has not been fabricated. Faculty still needs to supply/annotate the reported incorrect view and approve species-appropriate organ relationships. The renderer now positions hotspots and the existing illustrative incision overlay within the fitted image rectangle, correcting drift caused by letterboxing. This engineering correction is not biological validation.

## Measurement

The layered viewer has a Measure control, draggable endpoints, keyboard arrow movement, reset, body/part selection and unit selection. Endpoint coordinates are normalized against the fitted source image, so resizing/letterboxing does not change the calculated distance.

Without an approved matching calibration it reports relative image distance only. Each calibration records reference endpoints, known mm length, source dimensions, exact image checksum, source, basis and supported scope. A body-only calibration cannot produce physical values for anatomical parts. Illustrations always show an illustrative-estimate notice. Replacing an image clears its old calibration; calibrate a subsequent draft version using the new immutable image. No calibration values are seeded into real packages by this release.

Physical units require a current independent approval or a published reviewed package. The ruler does not turn a generated illustration into a real measured specimen, and it does not silently copy values into manually entered notebook observations.

## Pretest/posttest scores

Class sections contains the pretest/posttest panel. Authorized section instructors and administrators prepare a draft test pair using two JSON question files. Each file is an array of objects with id, prompt, options and answer; each answer must equal one distinct option. The initial release requires equal item totals.

Students open this panel through **My class**. An approved pair remains hidden
until administrator activation; the staff panel now states that pending action
explicitly. See [classroom quiz access](classroom-quiz-access.md) for release and
shared Wi-Fi setup steps. Online browser UUID generation supports ordinary LAN
HTTP addresses through cryptographic random bytes, while offline downloads still
require a supported secure origin.

An authorized independent reviewer opens the forms and answer keys and records validation evidence and policy confirmations. The author cannot approve the instrument. An administrator then activates the approved pair only when the selected lesson is published and assigned to an active section. No formal instrument or faculty approval is seeded on production.

Students receive question prompts/options without answer keys. Server grading records answers, score/total, instrument, student, section/package via the study, phase, UUID and submission time. Each student has one retained scored attempt per phase. The posttest requires the recorded pretest and a formative completion for that same section/package. Assign the pretest before content use according to the approved study procedure; this release does not retroactively block existing learners or claim that an old formative attempt was a baseline.

The panel and CSV show the pre/post scores, raw-point difference and percentage-point difference. Missing attempts remain missing, actual zero is preserved, and decreases remain negative. A score difference alone is not a causal learning-effectiveness result. Disabled assessments remain visible to learners who have attempts. Authorized staff can void an attempt with a reason, retaining its score, answers and audit history. A void does not grant an unapproved retake; a new approved instrument version is required for a replacement scored pair.

Formal submissions require an online session. UUID retries are idempotent, and database uniqueness prevents multiple scored attempts for a phase. Concurrent record-busy failures are retryable with the same UUID. Form definitions are immutable; key access, results and CSV remain role/section scoped. New assessment tables participate in delayed cloning.

## Preservation and rollout

Only additive assessment tables are introduced. Their rollback deliberately retains academic data. Existing accounts, credentials, enrollments, lesson packages, assignments, formative results and notebook drafts are not converted or deleted. Service-worker shell revisions refresh the interface separately from saved notebook and learning-package data.

The feature code can be deployed before faculty inputs exist. Scientific profiles/media/calibrations remain behind the existing package lifecycle, and formal assessments remain disabled drafts until independent approval and administrator activation. No real student or study data is used for engineering fixtures.

## Engineering evidence

Focused feature coverage includes revision immutability and retained record counts, unknown/private field rejection, invalid calibration, independent approval and activation, student/section boundaries, private answer keys, server grading, retry idempotency, missing/zero/negative score handling, void/disabled history, revised imagery, calibration gating and delayed-clone retention.

JavaScript checks cover fitted-image coordinate transforms, scale invariance, unapproved/stale calibration fallback, body/part scope and escaped legacy/profile rendering. Synthetic browser checks and final full-suite/release outcomes are recorded with the release evidence; they are separate from faculty validation, physical-device acceptance and real-student use.
