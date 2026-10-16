# Administrator bulk student upload

People & sections → Upload students from a file accepts CSV, TSV, XLSX, XLS,
Excel 2003 XML, flat JSON records and XML records with scalar fields/attributes.
Use the synthetic CSV/TSV/JSON/XML templates in the uploader. A CSV template can
also be opened in Excel and saved as XLSX/XLS, retaining student numbers as text.
PDFs, images, document files and nested data must first be exported as a flat table.

1. Upload an unencrypted file up to 5 MB. Workbooks are bounded to 20 sheets,
   64 columns, 1,100 physical rows (including up to 100 header rows), and 32 MB
   expanded XLSX content. The selected table may contain at most 1,000 students.
2. Choose the worksheet or XML record element, header row, and identity columns.
   Suggested mappings need administrator review. Email can be mapped or completed
   afterward. Student numbers must be text to preserve leading zeros and hyphens.
3. Search/filter program, year level or home section and explicitly select rows.
   Nothing starts selected. Assign existing active MB 304L offerings individually
   or to the selected group, using academic-year and semester filters.
4. Complete/check emails, review destinations and account/enrollment statuses,
   then choose **Import selected students**. Corrections require a new review.
   Cancel deletes the private draft without creating accounts or enrollments.

Program, year level and home section are temporary upload context, not stored
eligibility rules or promotion instructions. Second-year and irregular students
can enroll. Student identity persists across terms. Existing account fields,
passwords, activation state, historical enrollments and results are retained.
Conflicting account identities or another active section of the same course/term
block the batch. Existing active enrollments are skipped. Email is optional when importing the roster. Blank emails are stored as NULL,
not invented addresses. New accounts without email remain pending and receive no
activation token. Existing email-based sign-in and activation still require email.
Matching student numbers and names may reuse existing student accounts without
changing their email, credentials or activation state. A supplied email must still
match the same account. Unique existing IDs can suggest a recorded email for review.

Drafts are encrypted under `storage/app/private/roster-drafts`, bound to the
uploading administrator and expire after 30 minutes. Draft actions use exclusive
file locks; final import is tied to the reviewed revision. Commit rechecks current
accounts/offerings under transaction locks and rolls back the entire batch on
failure. Model changes and delayed-clone outbox entries share the existing
transaction. Import events retain actor, actual format, destination IDs and counts;
activation secrets stay in the existing private administrator delivery response.

The existing scheduler runs `roster:expire-uploads` every five minutes; upload
inspection also cleans expired drafts. Keep Laravel's existing `schedule:run`
cron enabled. Raw source files are not retained. Formula cells, XML entity/DOCTYPE
declarations, macros and external content are never executed. Excel imports use
PhpSpreadsheet; JSON and XML accept named scalar record fields only.

Legacy fixed-column `/api/admin/roster/preview` and `/api/admin/roster/import`
remain available, along with the single-student form. New administrator endpoints:

- `POST /api/admin/roster/uploads`: inspect and create a private draft.
- `POST /api/admin/roster/uploads/{draft}/map`: worksheet/header/mapping and revision.
- `POST /api/admin/roster/uploads/{draft}/review`: selected row IDs, completed emails,
  destination offering IDs and revision; returns a new review revision.
- `POST /api/admin/roster/uploads/{draft}/import`: reviewed revision only.
- `DELETE /api/admin/roster/uploads/{draft}`: cancel the owning administrator's draft.

Engineering verification uses synthetic records only. Tests cover all six formats,
Excel XML, worksheet/header mapping, Unicode, text IDs, optional and invalid emails, identity
conflicts, duplicate selections, separate destinations, later-year account reuse,
selection exclusion, preview/cancellation, administrator ownership/access, expiry,
stale revisions, changed enrollments/offerings, transaction rollback, repeated
submission and delayed cloning. Browser checks cover upload, mapping, filtering,
review and completion with an isolated SQLite database at desktop and 390 px mobile
widths. These are software checks, not faculty validation or research evaluation.

Private masterlists and real student records are excluded from source code and
test fixtures. Software QA uses synthetic preview and cancellation. Actual
production roster imports require the administrator's explicit selected-student
and destination instructions.
