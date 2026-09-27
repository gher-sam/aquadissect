# Workspace redesign engineering verification

Date: September 27, 2026. Status: Implemented but awaiting research/user validation.

## Scope

Individual page views and named routes; Attex-inspired shared shell; responsive drawer and desktop collapse; searchable record tables; navigation/help search; class calendar; persistent notifications; operational reports and CSV; neutral offline navigation and account-bound queues.

## Automated checks

- Production Vite build succeeds. Existing large Three.js bundle warning remains; anatomy engines were retained.
- 50 Laravel feature tests, 612 assertions pass locally, including existing activation, roster, instructor assignment, package review/publication, assessment, safety-module, and clone regressions.
- New checks cover guest and role-specific page access, isolated panel rendering, calendar authorization/UTC conversion/update/cancellation, notification recipients/deduplication/read privacy, access removal, instructor approval, report ownership/date scope/CSV formula escaping, and delayed-clone event/read-state replay.
- New migrations are additive. The standard deployment script migrates both primary and delayed SQLite clone schemas. Rollback must retain new records rather than dropping tables.

## Browser checks performed locally

- Administrator dashboard, People & sections, expandable class table, content-version table, calendar event creation/cancellation/list, and reports with separate historical table.
- Instructor dashboard, activity-table expansion and successful due-date save; resulting due-date entry on calendar; browser Back restores the activity URL.
- Learner sign-in, role-specific navigation, notification delivery and mark-all-read returning unread count to zero; verified package download.
- Server-stopped reload opened the neutral offline workspace and the downloaded Tilapia anatomy experience. Staff HTML is excluded from navigation caches; prior shell caches are retired.
- Phone calendar at 375px: list default and no document overflow. Tablet at 768px: no document overflow, drawer positioning confirmed. Desktop collapse measured 76px and retained accessible navigation labels.
- Browser automation stalled in a native confirmation dialog during QA. The disposable test event was cancelled, the stale tab was closed, and checks resumed in a clean tab. Calendar cancellation now uses an inline confirmation instead.

## Evidence boundaries and follow-up

These are engineering checks, not faculty validation, respondent evaluation, or proof of learning effectiveness. Comprehensive assistive-technology testing and a full real-device offline assessment/synchronization study remain awaiting validation. Older offline queues without an owner are retained but not silently assigned to a subsequently signed-in account.

Production release and live-route/asset verification are reported in the release conversation; this document does not invent deployment or research results.
