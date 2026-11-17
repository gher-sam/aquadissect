# Reef Quest interface verification

Status: Implemented but awaiting validation. User-approved design: Reef Quest,
November 17, 2026. Engineering checks are separate from faculty and research
validation.

The interface uses solid blue, teal, coral, gold and violet, with navy text,
pale backgrounds and Figtree. Student Home shows a section and lesson selector,
the assigned learning trail, private saved practice XP and the badge collection.
Staff workspaces share the palette without changing their academic workflows.

Motion is optional and follows the device reduced-motion preference. New reward
feedback requires a successful server response. The existing authorization,
version, safety and reward boundaries remain in force. No database migration or
new reward rule is required. Reports share semantic chart colors across browser,
PDF, DOCX and native XLSX while retaining SLSU branding in all five formats.

Verification used a separate synthetic database with three assigned sections,
including an incomplete required safety prerequisite, and a second learner with
a different private collection. No live academic records were read or changed.

- All 109 JavaScript checks passed, including context filtering, escaping,
  reduced-motion precedence and confirmed reward feedback.
- Twenty-one main browser scenarios passed: section selection and safety locks,
  hints and retries, server-confirmed rewards, all six challenges and four
  badges, repeat protection, retained collections, late-response isolation,
  offline clearing, motion persistence, sign-out and another learner's privacy,
  mobile layouts, forecasts, all downloads and all three staff roles. No page
  script errors were observed.
- Three additional keyboard-navigation checks passed at 320, 375 and 768 pixels.
  Two focused motion checks confirmed cancellation without replay and preserved
  manual instructional video playback using a retained engineering media fixture.
- CSV retained all 51 filtered lesson records. XML parsed with its institutional
  footer. XLSX retained four native charts, their semantic colors and printable
  header/footer image relationships on every sheet. PDF and DOCX retained the
  same learner occurrences and the SLSU branding.
- The 20-page PDF and 17-page Word export were rendered and visually reviewed.
  A 20-page LibreOffice workbook rendering was reviewed for table and chart
  layout; native Excel header/footer image relationships were checked in the
  workbook itself. An additional native Microsoft Excel print check confirmed
  the three institutional header/footer images on every one of its 20 pages.
  Line and marker colors are explicit, and forecast projections use dashed lines
  so native Excel cannot replace them with its default series colors.
- PHP formatting checks passed. The standard release script requires the full
  isolated PHP, JavaScript, production-build and deployment-recovery checks
  before committing and publishing.

Private captures, generated exports, parsed checks, release output and live
asset verification are retained under the ignored task evidence directory.
Publication is confirmed separately from local implementation. These checks do
not establish faculty acceptance, biological validation or learning improvement.
