# AquaDissect UI system: Attex adaptation

## Scope and implementation status

October 6, 2026. **Implemented but awaiting validation.** Local engineering checks
are recorded separately in [verification](attex-ui-verification.md). Deployment
is pending explicit authorization. The [86-page study](attex-ui-study.md) separates
visible examples, verified interactions, inferred intent, and unsupported modules.

This is a Laravel 13 / Blade / Vite 8 application with vanilla JavaScript,
Bootstrap dropdown behavior, locally bundled Figtree and Lucide, and existing
layered/Three.js learning viewers. The adaptation extends these foundations;
it does not introduce React/MUI, external theme assets or commercial modules.

## Shared visual rules

| Role | Value | Use |
|---|---|---|
| Primary | `#3e60d5` | Main actions and recorded progress |
| Primary deep | `#283d85` | Headings and emphasis |
| Primary soft | `#edf1ff` | Selected/hover surfaces |
| Sidebar | `#212529` | Role-scoped navigation |
| Active navigation | `#334778`, border `#8aafff` | Current destination |
| Canvas | `#f2f2f7` | Workspace background |
| Surface | `#ffffff` | Cards, forms, dialogs |
| Text | `#343a40` | Body and controls |
| Supporting text | `#596579` | Help and secondary information |
| Border | `#e2e6ed` | Quiet separation |

No gradients. Preserve semantic status colors with written labels. Decorative
Lucide icons are hidden from assistive technology; actionable icon buttons retain
accessible names. The missing `CircleDashed` icon is now registered.

Figtree remains locally bundled. Existing typography defines 14px body/controls,
20px page headings, 18px section headings and 24px metrics. No global shrinking of
the anatomy viewer's instructional content. Cards use 5–6px corners, 18px desktop
padding, 14px mobile padding and 16px separation. Desktop content gutters are
24px; mobile gutters are 12px. Touch actions generally retain a 44px target.

## Navigation and search

- Role-visible navigation groups have explicit expanded/collapsed state. Optional
  local preferences contain only group state, no account or learning records.
- The active destination's group opens on page activation. The desktop icon rail
  exposes destination names and titles; it does not hide links with group state.
- Existing mobile off-canvas navigation retains its scrim, background `inert`,
  Tab trap, Escape, focus return and resize cleanup.
- Breadcrumbs are a labelled navigation region with a current-page label.
- Search matches permitted pages, help topics and the current account's tutorial
  catalog. Arrow Down/Up choose links, Enter opens the first result, Escape closes
  suggestions and returns focus. Search does not query private learner records.
- No language picker, fullscreen toggle or theme configurator is added without a
  supported user task. Existing tutorial/media color-scheme handling is retained.

## Reusable components and states

| Pattern | Implementation |
|---|---|
| Card | Shared compact border/padding; existing semantic headings |
| Navigation disclosure | Button, `aria-controls`, `aria-expanded`, hidden links |
| Loading | Workspace skeleton, written status and `aria-busy` |
| Empty | Explicit no-record/no-score language; no invented numbers |
| Error | Retry guidance and persistent feedback; previous report retained |
| Success | Announced report count; recovered request clears old failure toast |
| Table | Scoped search, clear, sort, pagination, details, columns, row spacing |
| Table scroll | Focusable labelled horizontal region contained by the card |
| Modal | Native dialog; Escape; calendar opener restoration and backdrop close |
| Form | Existing labelled fields, server constraints and explicit user submit |
| Media | Existing play/pause/replay/captions/hotspots and provenance workflow |
| Error page | Shared Figtree layout for 403, 404, 419 and 500; safe destinations |

The table initializer is idempotent. Table view preferences survive an existing
root's record refresh. Record identity and Actions columns cannot be hidden.
Detail/empty row spans follow visible columns. Bulk mutations and spreadsheet
editing are not added because no corresponding safe project workflow exists.

## Reports and actual data

Existing `/api/reports` retains account, role, section, date and activity scopes.
New 7/30-day shortcuts and reset controls use Philippine calendar dates. The
server validates date ranges. Latest-request ordering prevents stale responses
replacing newer filters. CSV remains server-generated and permission-filtered;
its link is unavailable until the matching report succeeds.

The activity bars show **counts of attributed records in the current filtered
report**, not unique students. Each bar has a text label, count, accessible meter,
and an explicit activity drill-down button. Historical unattributed rows remain
separate. Aggregate scores include available scored lesson attempts only. Zero
points with an available total display 0%; absent scores display **Not recorded**
without a zero meter. No inferred growth, comparisons, analytics integration,
learning effectiveness or validation statistic is introduced.

## Permissions preserved

| Role | Existing scope retained |
|---|---|
| Student | Own enrolled class, published/assigned learning and own results |
| Teacher | Assigned sections, activities, justified exceptions and content workflow |
| Program Head | All MB 304L read/report and independent review; no account/activity/override/media/publication/source management |
| Administrator | Existing account/section/content administration and publication |

The client does not grant access. Existing controllers and services remain the
authority. No authentication, SSO, migration, class assignment, review rule or
durable learning record is changed by this UI increment.

## Motion and accessibility limits

Shared state transitions are 160ms; workspace entry is 180ms with a 4px offset.
The interface layer disables portal animations, transitions and smooth scrolling
under reduced motion. Existing viewers retain their own motion fallbacks. Focus
is visible, search/dialog controls work by keyboard, tables have captions and
sort state, and loading/error feedback is announced. These engineering measures
are not a claim of a completed screen-reader or accessibility certification.

Reference drag gestures, every chart tooltip/download, every hover/loading state,
external media playback and backend persistence were not exhaustively tested.
Real assistive-technology sessions and reduced-motion device testing remain
follow-up validation. The detailed study lists each page's specific limits.
