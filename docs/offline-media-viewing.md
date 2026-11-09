# Offline lesson media

Status: Implemented. Engineering checks are separate from
faculty content approval, research participation and learning-effectiveness evidence.

## Student workflow

1. Sign in to the HTTPS website, open **Offline access**, and choose the study
   section when more than one applies.
2. Choose **Prepare for offline viewing** for Safety & Tools and the assigned
   species lesson. Wait for the verified copy to appear in the prepared list.
3. Without a connection, reopen AquaDissect in the same browser profile and choose
   the matching section. Images and clips, playback controls and lesson interactions
   remain available for prepared, previously authorized versions.
4. Sign-out closes media in open tabs and locks the account's retained copies.
   Connect and confirm the same account to reuse them. Another account does not
   inherit the prepared copies.
5. **Remove prepared copy** removes only that browser copy. Learning records,
   queued work and notebook drafts remain separate. Reopen online after reconnecting
   to refresh availability and synchronize the original account's queued work.

Offline preparation requires a secure browser context. Plain HTTP classroom
addresses retain connected lesson viewing; they cannot prepare service-worker
copies on student devices. HTTPS and local loopback development are supported.

## Controls and limits

Student lesson media suppresses dragging and context-menu saving where supported.
Videos with native controls receive accessible playback, replay, seeking, mute,
available captions and fullscreen controls without a file-save action. Existing
custom swimming controls, keyboard interactions, measurement, references and
notebook exports remain available. Authorized staff source/package exports retain
their existing role checks.
The player also retains native video fullscreen where the browser supplies that
instead of the element fullscreen API.

This is a deterrent to casual saving, not DRM. Browser storage inspection,
screenshots, recordings and deliberate extraction remain possible. Media already
distributed through the public repository remains public. No encryption, extra
PIN, watermark or timed offline expiry is introduced. Offline access uses the last
confirmed authorization until sign-out or reconnection; remote revocation cannot
be immediate on a disconnected device.

## Application boundaries

- Existing session, manifest and media routes are retained. Runtime media URLs use
  `mediaPolicy=2`; protected responses and session checks use `private, no-store`.
  Package content, checksums, provenance and publication state are not rewritten.
- The worker verifies online identity through `/api/session`. Prepared manifests
  and assets are scoped by account, section and content record/version, using exact
  URLs. Cached responses require a matching registered client and an unlocked
  persisted account context. Other APIs and staff exports do not search media caches.
- Sign-out and account changes invalidate viewing contexts across tabs. A delayed
  identity check or preparation cannot undo a newer lock. Explicit authorization
  failures block affected copies instead of falling back to stale media.
  A permission-denial revision also prevents in-flight preparation or an older
  positive permission response from restoring a denied copy. Delayed failures
  block the latest cache generation without restoring an obsolete cache index.
- Preparation verifies all asset hashes in a separate cache generation, then
  commits its index atomically. Interrupted, corrupt or quota-limited preparation
  retains the earlier verified copy. Offline byte ranges support video seeking.
- The client media index uses a separate `aquadissect-offline-media-v2` IndexedDB
  database scoped to the application path. The original attempt/progress queue
  database and notebook storage are retained. No academic database migration is
  introduced.
- The worker removes old unowned `aquadissect-package-*` and
  `aquadissect-module-*` caches. Those media copies require one fresh preparation
  while connected; queued progress, notebooks and server records are not removed.

## Engineering verification

Automated scenarios cover account/section isolation, retained-copy locking,
worker restart, delayed responses, interrupted preparation, corrupt media, storage
failure, explicit permission denials, video ranges, legacy-cache cleanup and
message scope. HTTP tests cover non-cacheable responses, policy/section URLs and
retained student/staff boundaries. Browser verification uses synthetic accounts
and separately stored lesson files; no live approval or real student activity is
created for testing. Release and live-preservation evidence is retained privately.

Synthetic desktop Chrome and Firefox checks verified offline browser reopening,
images, clip playback and seeking, mobile layouts, multi-tab sign-out, persistent
locking after a browser restart, account switching and original-account progress
synchronization. A disconnected server with Wi-Fi still connected remains a
locked view during pending sign-out; retrying does not loop through cached pages.
Plain HTTP classroom viewing and the student/staff export boundaries were also
checked. Mobile Chrome touch emulation also verified offline images and video,
seeking, replay, fullscreen and sign-out. A capability shim checks the native
video fullscreen fallback; it is not a Safari-device test. Physical phones were
not used for these engineering checks.

References: [MDN service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers),
[MDN Cache](https://developer.mozilla.org/en-US/docs/Web/API/Cache),
[OWASP browser storage testing](https://owasp.github.io/www-project-web-security-testing-guide/stable/4-Web_Application_Security_Testing/11-Client-side_Testing/12-Testing_Browser_Storage).
