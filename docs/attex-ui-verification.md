# Attex adaptation: engineering verification

October 6, 2026. **Implemented but awaiting validation. Deployment authorized
through `/serve_update`; live verification pending at release preparation.**

## Repository audit

Read the project instructions, complete canonical study alignment, route catalog,
portal bootstrap, role checks, report controller, shared shell, table behavior,
workspace interactions, typography and stylesheet order. The starting working
tree had untracked `outputs/`; those existing outputs were preserved. New study
and QA files have a specific ignore entry to keep the demonstration database and
evidence out of the standard source release. No live academic records were used
or changed. No migration, authentication change or new business module was added.

## Reference coverage

All **86/86** requested URLs have an individual retained observation and desktop
and mobile DOM snapshot. Actual URLs match the checklist; all mobile DOM
measurements record 390 × 844. Representative screenshots cover dashboards,
calendar, chat, email, tasks, Kanban, files, profile, maintenance, authentication,
component groups, uploads, tables and charts. The full per-page checklist records
visible components, intended actions, tested behavior, mapping and limits.

Representative interaction checks include calendar Week and dialog Close,
chat contact selection, mail checkbox selection, Profile Settings, table sorting
and pagination, dialog/drawer Escape, chart toolbar/range clicks and export menu,
and Analytics date shortcut observation. Task search did not filter the observed
demo. Analytics metrics stayed unchanged after 7d. Ecommerce displayed a sales
error. These limits are retained rather than treating every demo label as working.

## Application desktop/mobile inspection

The named isolated SQLite QA database contains seeded **demonstration data** and
role-only labels. Two marked demonstration activities provide a real recorded
zero score and a nonzero score for checking report calculations. These are test
records, not research evidence. Delayed cloning was disabled for this named local
preview; normal clone behavior remains covered by the PHP suite.

Initial inspection covered 34 page/role combinations at both sizes. Final
representative captures confirm loaded Figtree, 20px workspace headings and no
document horizontal overflow for the pages below. Desktop sizes are retained in
each JSON record; final mobile captures are explicitly 390 × 844. Earlier snapshots
of other existing pages are retained as initial-pass evidence.

| Role | Final representative desktop and mobile pages |
|---|---|
| Student | Dashboard, Reports, Safety & Tools, Help |
| Teacher | Dashboard, Reports, Class activities |
| Program Head | Dashboard, Class sections, Species content, Safety & Tools, Reports, Calendar, Help |
| Administrator | Dashboard, Reports, People & sections |

Initial passes additionally inspected lessons, offline access, notifications,
account roles, media prompts and administrator Source Structure. Screenshots use
demonstration names only. Reference screenshots contain public theme example
identities. No requesting individual's name or credential is added to evidence.

## Verified interactions

- Role-scoped navigation appeared for all four accounts. A direct Program Head
  visit to People & sections returned the access-denied page.
- Navigation groups collapse and expose their state. Active destinations reopen
  the relevant group. Search Arrow Down/Up moves focus to matching links; Up from
  the input selects the last result; Escape returns focus to the search input.
- The mobile drawer opened, exposed navigation, and closed with Escape while
  returning focus to its menu button. Existing focus-trap logic was inspected.
- Report presets set Philippine-calendar dates. Activity drill-down reduced the
  report from two demonstration records to one. Reset restored both records.
- The recorded 0/5 activity displayed **0%**. An empty safety report displayed
  **Not recorded** without a zero score meter.
- The teacher dashboard labels visible draft/review versions without implying
  ownership, and excludes archived versions. Missing package sizes now display
  **Not recorded** rather than zero; this fallback was source-reviewed.
- Table search filtered two rows to one; clear restored both. Hiding Section
  removed that column while retaining identity and action constraints. Existing
  sort/pagination/detail behavior is preserved by the reusable initializer.
- An invalid date range returned server validation feedback. Previous results
  remained available; CSV was disabled. Reset reloaded valid results and enabled
  the matching server-generated CSV link. The subsequent notification replacement
  with success feedback was source-reviewed, not separately browser-tested.
- The calendar event dialog opened without saving anything. Escape closed it and
  restored focus to New event. Backdrop handling is implemented and source-reviewed;
  the backdrop gesture itself was not separately exercised in this pass.

The initial preview used the repository's subdirectory asset base and produced
missing local font/chunk requests. Rebuilding with a root asset base for this
isolated preview resolved that configuration mismatch; final font checks returned
loaded Figtree. A stalled preview process and failed blank tab were replaced/closed,
and viewport measurements were rechecked against the correct preview tab. These
were local verification issues; no production configuration was changed.

## Checks

| Check | Result |
|---|---|
| Initial PHP suite | 76 tests, 1,025 assertions passed |
| Final sequential PHP suite | **79 tests, 1,039 assertions passed**, exit 0 |
| New error-page tests | 3 tests, 14 assertions; safe guest/account destinations, denied management, private diagnostics, session guidance |
| Production frontend build | Passed after final source changes |
| JavaScript syntax | Shared interface, workspace and table files passed |
| PHP syntax | Changed Blade views and error tests passed |
| Route inspection | Existing protected reports route retained; no new production endpoints |
| Whitespace | `git diff --check` passed |
| Gradients | No gradient declarations found in application CSS |

An overlapping test rerun conflicted with shared versioned-storage and ZIP test
fixtures, producing three errors. Both overlapping runs were stopped/finished;
the subsequent sequential full suite passed all 79 tests. The frontend build
retains the existing warning about the large Three.js/OrbitControls chunk.

## Remaining limits

- No deployment, production role mutation, publication, or live record testing.
- No complete real screen-reader session or reduced-motion device emulation;
  accessible DOM/keyboard checks and motion source review were performed.
- No physical-device/tablet matrix, every hover/loading state, exhaustive focus
  ordering of every existing form, or every possible data configuration.
- Reference drag movement, persistence, tooltip values, exact chart zoom ranges,
  completed downloads, external player behavior and authentication backends are
  not claimed as verified. Each reference page repeats its individual limits.
- No new chat/mail/calendar integration, paid plans, rich-text editor, Kanban
  mutations or specialized statistical charts. Current workflow support governs
  the pattern mapping.
- Real Google SSO callback testing is outside this UI increment; authentication
  code and credentials are preserved.

These checks establish engineering behavior in a demonstration environment. They
do not establish faculty validation, preparedness gains or learning effectiveness.

## Study deliverable verification

The packaged HTML contains 86 page entries. Search for Candlestick returned one
entry; the Authentication group returned 12 entries and matching index links.
Representative Analytics images loaded successfully. The report has no document
horizontal overflow at 390 × 844. The package excludes the QA database, account
credentials, scripts and server logs.
