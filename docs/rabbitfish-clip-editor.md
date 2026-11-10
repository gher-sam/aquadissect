# Rabbitfish guided-clip editor

Status: Implemented but awaiting faculty validation. Software checks do not
establish anatomical accuracy, continuity, safe physical procedure or learning
effectiveness. Release verification is recorded separately below as available.

## Where faculty upload clips

1. Sign in as an administrator or instructor.
2. Open **Species content**, find the required Rabbitfish source version, and
   select **View details → Manage guided clips**.
3. Expand a step and select **Upload clip** or **Replace clip**. Choose an MP4 or
   WebM of no more than ten seconds and 80 MiB. Supply its caption, source,
   usage licence and AI-generation declaration, then choose **Check and attach
   clip**. Longer clips are rejected, not silently trimmed.
   **Preview clip** opens that step's video and caption separately.
4. Use **Add clip step** for another clip. Faculty enter the title, instructions
   and learner task. **Move up/Move down** positions clip steps while retaining
   every required anatomy/activity step in its original relative order. New clips
   start before the final knowledge check, which remains last.
5. **Save working draft** before leaving. Reopening the same source version with
   the same account resumes the private working draft for 24 hours. A failed
   upload retains the last saved copy; retry after correcting the reported issue.
6. Use **Preview sequence** to check clips and instructions in order. Confirm
   the specimen, tray, tools, handling and action continuity through faculty
   review. The software does not infer scientific correctness from the video.
7. Enter a unique new version, such as `1.3.1`, then choose **Save new draft
   version**. Close and refresh Species content. Send the saved version for
   independent faculty review. Administrator publication and section assignment
   remain separate actions; no existing assignment changes automatically.

Closing the editor saves complete edited step text first. Browser reload or
navigation warns about unsaved text. Upload-detail fields must be submitted
before leaving; the original files remain on the faculty member's device.
Cancel removes temporary playback files. Working drafts expire after 24 hours;
the scheduler removes expired storage every five minutes.

## Faculty wording helper

**Clarify wording** prepares a prompt for the faculty member's existing assistant.
It preserves actions, order, anatomical terms, measurements, tools, specimen
position and safety warnings, and asks the assistant to flag missing information.
Copy manually when the browser's clipboard API is unavailable on a classroom
HTTP installation. Paste the suggestion alongside the original instructions and
choose **Accept reviewed wording** only after comparison. This changes the local
editor text; save the working draft to retain it. **Keep current wording** leaves
the instructions unchanged. AquaDissect makes no external AI request and stores
no AI-provider key. Media AI disclosures remain independent of wording help.

## Storage, limits and operator setup

- Temporary state is encrypted and account/package scoped under private
  `guided-clip-drafts` storage. Uploaded names are replaced with generated names.
  Temporary material is excluded from source downloads, Git and academic clones.
- The server verifies and decodes each video with FFprobe and FFmpeg. Configure
  `AQUADISSECT_FFMPEG_BINARY` and `AQUADISSECT_FFPROBE_BINARY` when the binaries
  are unavailable on the server path. Playback copies are silent H.264 MP4,
  at most 1280×720 with aspect ratio retained, 24 fps and at most 3 MiB.
- Archives support 250 MiB compressed and 500 MiB expanded, retaining path,
  file-count, type and hash checks. Configure PHP upload/post limits above the
  250 MiB package size (including multipart overhead), HTTP body limits at least
  256 MiB, and PHP worker/proxy timeouts sufficient for conversion and verification
  (300 seconds is the production setting). Apache uses `LimitRequestBody` and
  `ProxyTimeout`; Nginx installations need matching `client_max_body_size` and
  FastCGI limits. The editor shows estimated cumulative package size.
- Editing and preview endpoints use the existing active-account, role, password,
  CSRF and source-content authorization. Program Heads review content but do not
  edit it. Each actor can access only their own temporary drafts.
- Saves create immutable new package IDs and record actor IDs, source/new versions
  and content hashes. Original packages, publication state, progress, results,
  quiz definitions and notebook drafts remain intact. The new uploader cannot
  approve or publish its own content.
- Prepared offline copies continue to use the existing account, section and
  package-version controls. Students must receive an approved, published and
  assigned version before preparing its clips. The existing saving deterrents
  and limits remain in force.

## Engineering checks

Synthetic PHP tests cover 72 clips, original record/file preservation, owner and
role restrictions, core-step order, invalid references, stale revisions, repeat
saves, failed commits, expiry/cancellation, media range responses and review gates.
The real video-processing test covers a ten-second clip, MP4/WebM, portrait and
non-square-pixel aspect ratios, audio/metadata removal, invalid input, excess
duration and unavailable processing tools. JavaScript tests cover clip movement
and explicit acceptance of suggested wording.

On November 10, 2026, isolated synthetic browser checks passed in desktop Chrome
and Firefox, including a 390 × 844 responsive viewport. They covered replacement,
excess-duration rejection with the previous clip retained, playback and seeking,
keyboard movement and focus, the final-quiz boundary, manual prompt copying,
explicit wording acceptance, ordered preview, version collisions and new-draft
saving. A synthetic independently reviewed and assigned version was prepared in
a supported loopback browser context, reopened with browser networking disabled,
and supported playback and seeking to five seconds; all three anatomy images
loaded offline.
These fixture reviews are engineering tests and confer no approval on live
content. Physical mobile-device validation remains separate from responsive
browser checks. Release availability requires the normal deployment and live
verification.
