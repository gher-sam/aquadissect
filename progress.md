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
