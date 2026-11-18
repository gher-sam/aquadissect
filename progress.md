Original prompt: Generate a bright, interactive 3D Tilapia anatomy and virtual-dissection experience inspired by the supplied Facebook examples and Tilapia reference photo.

## 2026-09-07

- Inspected the current Laravel AquaDissect application and preserved its authentication, role dashboards, lesson progression, assessment, saved results, and educational content.
- Confirmed the current production lesson uses a layered 2.5D image viewer; an earlier procedural Three.js model exists only as an archived prototype source.
- Implementation goal: integrate a production Three.js viewer with a more realistic Tilapia silhouette, selectable structures, labels, incision/body-wall animation, exploded anatomy, orbit controls, mobile support, accessible controls, and deterministic test hooks.
- Added the Three.js dependency and integrated a new `Tilapia3DViewer` with orbit/zoom, raycast selection, CSS labels, bright studio lighting, a generated scale texture, full-screen mode, auto-rotation, exploded anatomy, and deterministic `render_game_to_text` / `advanceTime` hooks.
- Reconnected the current Laravel lesson controller to the 3D viewer without changing the role portal or lesson-result APIs.
- `npm run build` passes. The resulting JavaScript bundle is about 651 kB before gzip (169 kB gzip); Vite emits a non-blocking 500 kB chunk-size warning.
- Reworked the exterior from the rendered screenshots: deep-bodied Tilapia silhouette, tapered head and tail base, smaller eye, mouth and nostril detail, planar scale texture, muted operculum, naturalized fins, and caudal/dorsal/pectoral fin rays.
- Fixed a pre-existing responsive cascade bug that allowed the hidden portal dashboard to remain visible above the lesson.
- Full automated flow passed using an isolated SQLite QA database: WebGL picking selected `lateralLine`, labels/reset/auto-rotation worked, all eight stages completed in order, and the knowledge check finished at `3 / 3` with `Excellent observation`.
- Desktop QA at 1440x900 and mobile QA at 375x812 report no horizontal overflow, mutually exclusive portal/lesson visibility, 44px viewer controls, and no console or page errors. Headless Chromium did not expose browser full-screen state, so the full-screen control is implemented but not counted as a headless assertion.
- Visually inspected the natural specimen, external labels, incision path, opened body wall, exploded anatomy, mobile lesson, and completion dialog screenshots from the local interface review.
- Final verification: `npm run build` and all 7 backend tests (37 assertions) pass; unsupported embedded full-screen requests now fall back to an in-app message instead of producing an unhandled error.

## TODO

- Optional future fidelity upgrade: replace the procedural exterior with a licensed, instructor-reviewed GLB/GLTF scan while retaining the same viewer and lesson interfaces.

## 2026-11-16 — Optional anatomy practice

- Continued the existing Function finder/System sorter draft with private, server-confirmed XP and completion badges. Added a keyboard-accessible challenge selector, new-badge feedback, and separate unconfirmed-answer messages for connection failures.
- Preserved the guided lesson position, first-unfinished resume, one reward per challenge and section/package version, staff previews without rewards, and the existing content-publication and safety gates.
- Fixed focus restoration after a failed opening/retry and prevented a queued close event from canceling an immediately reopened practice dialog. Added read-only practice state and viewer time-step hooks for browser verification.
- Isolated verification passed 12 PHP practice checks (145 assertions), 10 JavaScript practice checks, the production asset build and 14 browser checks. Desktop/mobile screenshots were visually reviewed, including Rabbitfish staff preview. No uncaught page errors were recorded. The tests include deliberately losing a saved-answer response, retrying without duplicate XP, sign-out during a pending check and separate-account progress.
- Made the additive practice migration safe to reapply after its record-preserving rollback. Practice history also protects enrollment deletion and invalidates an earlier administrative preview; both regression checks pass.
- Integrated the practice patch with the current shared-repository source, preserving its classroom quiz, administrator record management, offline media and guided clip editor updates. The older local checkout and unrelated drafts were retained.

### Remaining verification

- Run the isolated required release suite and publish only the practice files; retain unrelated local changes.
- Faculty acceptance of the practice presentation and research validation remain pending. XP and badges are practice completion signals, not learning-effectiveness findings.

Current request: Please implement these improvements. Use accurate highlighting of the selected fish part instead of dots, and make sure more fish species can be added in the future. Crawl legitimate resources and ensure they are accurate.

## November 18 2026

- Read the supplied concern document, current viewer, package importer and study decisions.
- Research uses primary fisheries sources. General teleost diagrams and siganid references do not certify the exact species or every boundary in an illustration.
- Implement image-bound region outlines, honest unmapped states, reusable package annotations and an authoring workflow for future species.
- Preserve existing packages, publication approvals, learners and records. Keep Rabbitfish as the default catalog scope; additional species require explicit package inclusion and existing review/publication/assignment gates.
- Validate geometry, accessibility, responsive alignment, offline behavior and package boundaries with synthetic data before the normal release.

### Engineering verification, November 18–19, 2026

- Replaced served 2.5D dots with exact-image region paths and an honest unmapped state. Traced fin edges against the retained artwork and visually reviewed selected external and internal regions.
- Added reusable, data-only package region maps and draft outline editing. New species require explicit inclusion and retain independent review, administrator publication, assignment and safety gates. Replacing images clears old annotations.
- Passed all 115 JavaScript checks and the three new isolated backend checks (35 assertions). Browser checks cover guided progression, list/mouse/touch/keyboard selection, 320/375/768/1440 layouts, source scope and already-loaded selection during a connection interruption. Six authoring scenarios cover new-species import, separate revisions, unfinished outlines, image replacement and retracing. No uncaught page errors were recorded.
- Preserved original package content and all 54 recorded unrelated local file hashes. The retained manuscript decision is excluded from this software release. The existing game test client checks the actual served lesson and its text-state hook.
- A rendered new-species preview exposed an optional-field failure before publication. Fixed the absent learning-goals display and allowed declared internal targets early in a species-specific sequence. Three additional browser checks passed using an unknown species slug and arbitrary structure IDs; no quiz was submitted.
- Faculty confirmation of the artwork and anatomical boundaries remains pending. Kidney and vent are deliberately unmapped; source-reference and software checks do not establish biological validation or learning effectiveness. The normal isolated release suite runs before publication.

## November 19, 2026 — Individual anatomy review and viewer corrections

- Current request: find legitimate anatomy images, correct related bugs and review each fish part individually. The user selected one part at a time, beginning with kidney and vent, and retained S. guttatus-only kidney references.
- Checked primary fisheries and scholarly sources. The same-species kidney study concerns disease tissue; the clear SEAFDEC reproductive-system diagram depicts S. canaliculatus and is excluded under this decision. A verified whole-kidney boundary in the retained illustration remains unavailable.
- Reproduced partial image blending after part selection and the outline editor's failed-image lock. Corrected source-layer selection, added image retry without discarding completed edits, and bound references to their exact part IDs and labels. General references no longer appear as the kidney's specific evidence.
- Targeted validation passed seven region/reference JavaScript tests and three isolated backend tests with 45 assertions. Further interface and release results are retained separately. No faculty approval, new image trace, academic attempt or research outcome is inferred.
- Removed the generic incision curve whose fixed coordinates were unsupported by the unmapped landmark. The depth transition and existing progress rules remain. The structure counter now says reviewed, and source-scope notes retain readable supporting text on mobile.
- Part selection pauses layer playback, and the Play/Pause control now follows pauses from selection, manual movement, reset and motion preferences. The text-state hook reports the real layer-playback state for verification.
