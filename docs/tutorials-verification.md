# Start here tutorials and Program Head oversight

Implementation status: **Implemented but awaiting validation**. Engineering verification does not establish faculty validation or learning effectiveness.

## Delivered behavior

- Nineteen versioned, role-specific guides: five Student, five Instructor, four Program Head, five Administrator.
- Dashboard invitation, searchable written instructions, optional walkthroughs, account-saved resume, replay, and recoverable save errors.
- Tutorials only write tutorial-viewing progress. Users perform academic and administrative actions explicitly.
- Program Heads inspect MB 304L sections and operational reports and independently review submitted content. Publication, source access, accounts, overrides, and teaching mutations remain restricted.
- Administrators explicitly change eligible active Instructor accounts to Program Head or restore Instructor access. Assignments remain retained and changes are audited.
- Additive tutorial-progress and role-event tables participate in the existing 12-hour clone. No existing accounts are converted.

## Engineering evidence

- Full PHPUnit suite: 59 tests, 863 assertions passed, including the final active-account checks.
- Dedicated feature suite covers all role catalogs, invalid guide/version/step rejection, account isolation, repeated progress updates, independent review, forbidden mutations, role history, assignment retention, and delayed clone replication.
- Production frontend build passed. Existing large 3D-viewer chunk warning remains.
- All 19 walkthroughs exercised through the browser against an isolated SQLite database with disposable role accounts, including unavailable control guidance and cross-page navigation. One administrator guide also resumed from its saved last step.
- Browser checks verified search, saved state after navigation, replay, keyboard Escape and focus return, dynamic form highlighting, simulated save failure and successful retry, and no document overflow at desktop, 390-pixel mobile, and 768-pixel tablet widths.
- Accessibility labels were inspected through the browser accessibility/DOM representation. Reduced-motion behavior is implemented with media queries; a dedicated screen-reader session and OS-level reduced-motion test were not performed.
- Production authenticated role checks require an available authorized live session. Local role testing does not substitute for that check.

## Release and recovery

Publish using the repository's standard `/serve_update` path after checks. Keep the additive tables and role history when rolling back UI changes. Before reverting code that understands `program_head`, restore affected accounts through the audited role-management action; old code does not recognize the new role. Do not drop records or infer past section attribution.
