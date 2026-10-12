# Rabbitfish preparation enhancement — October 12, 2026

Status: Implemented but awaiting faculty validation. October 12 release preflight passed; production verification is recorded separately below.

## Changes

- Rabbitfish (danggit) is the single development priority, recorded in the canonical alignment record. Exact species and comparison-outcome adaptation remain pending faculty confirmation.
- Rabbitfish package previews/lessons gain an accessible observation notebook: 16 Table 1 mm fields, four classifications, external-feature states, independent fin/spine counts, internal observations and preparation reflections.
- Notebook entries stay in the current page session. They survive dialog closing and reopening, but clear on dashboard return, package change, account transition or reload. JSON/CSV downloads are personal worksheet copies, not academic submissions.
- Missing measurements retain unknown/not observed/not applicable and null; lengths must be positive finite values. Whole observed counts may be zero. Switching to a missing state discards stale numeric values.
- Uncalibrated virtual illustrations cannot export measured lengths. The record states the physical/reference/virtual basis. Scientific identity and feature applicability are not automatically confirmed.
- CSV escapes quotes and spreadsheet formula prefixes. No account identity, credentials, new database records or academic grading are added.
- Existing immutable packages, safety notice, permission rules, review/publication gates, historical assignments/results and quiz submission contracts remain intact. Spine geometry is unchanged pending an annotated faculty target.

## Engineering evidence

- Six Node data/export regression tests passed: 16 unique fields; missing versus zero; invalid measurements; integer meristics; safe CSV; source/version/observation retention.
- Production Vite build passed. Existing large optional 3D chunk warning remains.
- Related PHP MultiSpeciesPlatformTest: 16 tests, 110 assertions passed in the initial focused run. A later diagnostic focused run reproduced the two storage-move failures (14/16 passed); the initial result does not override the failing full suite.
- Full PHP suite: 79 tests, 77 passed, 2 failed, 1031 assertions. Failures: valid new-package import and swimming-video package import; both report “The validated package could not be moved into versioned storage” in unchanged SpeciesPackageImporter.php. A rerun with separate LARAVEL_STORAGE_PATH reproduced both failures. These earlier failures held the initial release. On the explicit /serve_update follow-up, a fresh full suite passed all 79 tests and 1039 assertions. No importer logic was changed; the earlier storage failures were intermittent and are retained here as evidence. The standard release script reruns checks before pushing.
- npm production dependency audit initially reported zero vulnerabilities. The full audit found one development-only source-map-js issue; the lockfile was patched from 1.2.1 to 1.2.2. The full audit now reports zero vulnerabilities.
- Playwright exercised the actual notebook module in a local Vite component fixture, not a signed-in production page. Desktop: JSON download, virtual measurement rejection, missing-value clearing, cancel/confirm reset and focus restoration passed. Mobile 390×844: no horizontal notebook overflow; 16px entry text; Figtree loaded; reduced motion enabled. CSV download, Escape, dialog-close note retention, leave-lesson clearing and expanding/focusing invalid fields inside collapsed sections passed.
- This is engineering verification. No independent faculty approval, research-instrument validation, learner evaluation or effectiveness is claimed. Authenticated production/offline flows were not reverified. Browser artifacts and component fixture are private output, excluded from release.

## Remaining work

Confirm exact intended rabbitfish identity, accepted spine view, laboratory comparison adaptation, field definitions, actual-specimen references and worksheet/LMS mapping with faculty. Monitor the intermittent local package-import storage failures; the successful full-suite run permits the standard checked release. No native installer or new research assessment is introduced.
