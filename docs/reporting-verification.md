# Reporting upgrade verification

Status: Implemented but awaiting validation. Engineering checks use synthetic
records and do not establish faculty acceptance or research outcomes.

## Behavior

The Reports page provides Overview, Learner Support, Assessments and Records
views. Academic year, semester, section, offering status, activity, dates and an
optional score-review threshold determine the authorized selection. Dates use
Asia/Manila and default to the last 30 days. Table search, sorting and pagination
do not change exported totals. Summary exports include analytics, chart source
tables and forecast basis. Detailed exports additionally include learner support
and every matching record, with unattributed history separated.

Completion and support use current active accounts, enrollments and assignments
at generation time. Date filters restrict saved records, trends and formative
score summaries; they do not reconstruct historical safety-progress snapshots.
One latest formative result per learner, section and package is counted within
the period. Zero remains numeric; missing scores remain missing. Formal paired
assessment means use non-voided matched pairs by study and instrument version,
retaining an earlier partner when the other phase is in the period. These
descriptive differences do not establish effectiveness.

Support reasons use actual overdue work, unmet safety prerequisites, seven days
without saved lesson/safety work, and an explicitly selected formative threshold.
No absence from research assessments is a support reason. Practice XP stays
private to the learner and separate from grades, safety and formal assessments.

## Forecast method

An authorized staff viewer selects one section and one assigned lesson or safety
activity. Exclude documented exceptions. Use the longest available whole-week
window from 14 through 28 complete days, excluding the partial current day, and
require at least five first completions on at least two days. Divide those
completions by window days for daily pace. Start the projection at current
completion and bound every point by the current assigned cohort. Slower/faster
lines use minimum/maximum observed weekly pace. Default horizon is 14 days;
7 and 30 days are available. A completed cohort or insufficient history has an
explicit explanatory state. Estimates depend on continued pace and do not
predict grades, establish competence or supply statistical confidence intervals.

## Institutional reference and formats

Artwork and contact information come from the official SLSU Bontoc Instruction
quality-forms page, May 2024, and its updated-header form
`QF-IN53 ACCOMPLISHMENT REPORT (MANCOM).docx`. See
`resources/reports/branding/README.md` for the exact source. The original is
retained privately and unchanged. AquaDissect reports use their own identity,
never that form's code, signatures or approval claims.

PDF and DOCX include rendered charts and repeated campus header/footer; XLSX
contains native charts, numeric values, text-preserved IDs, printable branding,
filters and frozen headings. CSV has UTF-8 branded text rows and formula-safe
text; XML has structured metadata and explicit missing cells. Document author
metadata uses AquaDissect and viewer role only.

XLSX includes native print-header/footer images and a text header/footer fallback.
LibreOffice rendering verifies the text fallback and cell/chart layouts; its PDF
conversion does not display Excel's native header/footer image objects. The
embedded image relationships are separately checked in the saved XLSX package.

## Engineering checks completed November 16, 2026

- Eleven reporting feature tests passed with 327 assertions: authorization,
  legacy compatibility, latest scores, missing/zero values, private practice,
  deadlines, optional and required safety assignments, exceptions, matched pairs,
  voids, forecast bounds, year/semester/archive scope, pagination and five formats.
- Six JavaScript report-view tests passed, including chart series, missing values,
  escaping and accessible chart-data views.
- Twelve synthetic browser scenarios passed at desktop and 375-pixel mobile
  widths, covering staff and learner views, charts, keyboard tabs, paging/search,
  support drill-through, forecast horizons, failed refresh, all five full-record
  downloads, privacy and program-head access. No page script errors were observed.
- Summary and Detailed exports were parsed. PDF and DOCX were rendered for layout
  review; XLSX cell/chart print layouts and native image relationships were checked.
  Additional selections covered all activity types and an empty result scope.
- Final required release checks and live revision/asset confirmation are retained
  in private release evidence. Publication is confirmed separately from feature
  implementation; a successful local check alone does not establish a live update.

## Access and release

The existing account/session and section authorization rules remain in force:
students see their own records, instructors their assigned sections, and admin
and program-head roles their permitted workspace. New read-only endpoints are
`/api/reports/analytics`, `/api/reports/records` and `/api/reports/export`.
Legacy `/api/reports` and its CSV contract remain available. Responses and
downloads are private with no-store headers. Generated temporary files are
removed after streaming; reporting creates no academic records or migrations.

Verification uses an isolated synthetic database. Retain private browser captures,
export parser checks, rendered document pages and release checks separately from
research evidence. Faculty acceptance, research validation and measured learning
effectiveness remain awaiting validation.
