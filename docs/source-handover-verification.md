# Source handover engineering verification

September 27, 2026. Implemented; formal user validation remains pending.

- Active administrators alone can access the page, manifest, previews, individual files, and ZIP download. Guests, students, instructors, and pending administrators were denied in automated tests.
- All browsing and downloads use the same prepared archive manifest. Path traversal, hidden environment files, storage paths, missing files, binary previews, and corrupt ZIP downloads are rejected.
- Source comes from an immutable committed tree with an explicit path and extension allowlist; symlinks are omitted. The generated environment example contains placeholders only. Common credential patterns block preparation, but this does not replace review of new source content before release.
- The local release archive inspection found 231 files, approximately 20.3 MB, with setup instructions and lockfiles; no database, manuscript, log, upload, dependency directory, or private-key files were included.
- Downloads record account ID, revision, target, and request time in private storage. The log records requests, not proof a recipient saved the complete download. No personal names are recorded by this feature.
- The production Composer installation prepares the archive; the explicit deployment command also supports preparation. The web request never runs Git or walks the live directory.
- Build passed. Full Laravel suite passed 53 tests and 669 assertions. Browser inspection confirmed student denial and administrator release/tree rendering. Further live verification is reported with the release.
- No new role, database migration, clone mutation, GitHub account requirement, or external download service is introduced. Private source responses use no-store and bypass offline cache fallback.
