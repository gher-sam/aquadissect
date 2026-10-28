# Notebook recovery and student checklist

October 28, 2026. Engineering increment approved by the user. This extends the
previous session-only worksheet with device draft recovery. It adds no academic
database table, account provisioning, lesson publication or research collection.

## Behavior

- A separate IndexedDB store keys drafts by signed-in account, selected section,
  package identity and version. Changes save after a short delay; success is shown
  only after the write transaction completes. Incomplete fields remain drafts.
- Opening the same lesson restores its own draft. Signing out clears the visible
  form immediately. Other accounts do not restore it through the application.
- Drafts remain on the current device and are not sent to staff or the server.
  Browser-profile/device access is outside the account UI boundary. Privacy guidance
  advises trusted devices, no personal identifiers and downloaded backups.
- JSON draft backups retain incomplete fields and contain no account identifiers.
  Imports validate size, schema, lesson/version identity, field names, states and
  text limits before replacing entries with explicit confirmation. Earlier version-1
  exports remain importable with numerical and missing-value validation.
- CSV exports retain their existing validation and spreadsheet-formula protection.
  Virtual illustrations still supply no calibrated measurements. Unknown, absent,
  not observed, not applicable and observed zero remain distinct.
- Save failure keeps entries visible with retry and download options. Unsaved exit
  warnings prevent accidental navigation. Explicit Clear draft removes the current
  stored copy. Atomic revision checks reject a stale tab's overwrite or deletion.
- The student checklist reports account/password setup, the current orientation
  guide version, and safety/Rabbitfish status for each active enrolled section.
  Server prerequisite status remains authoritative. Exceptions are not represented
  as safety completion; queued progress is not confirmed completion. Offline or
  stale information needs online confirmation. No new prerequisite is imposed.

## Verification

52 JavaScript checks pass, including owner/section/package/version separation,
incomplete drafts, observed zero, earlier export import, malformed/unmatched files,
invalid values, section-specific locks, exceptions, pending synchronization,
orientation versions and escaped labels. A PHP integration check verifies that the
student receives only their own prerequisite status, an exception remains distinct
from completion, and viewing the checklist leaves academic records unchanged.

Synthetic browser evidence is retained privately under `output/playwright/notebook-*`.
Checks exercised actual IndexedDB saving, return-to-dashboard and reload recovery,
offline editing, JSON download, explicit draft removal, invalid import preservation,
and JSON recovery. A 390 by 844 viewport showed no horizontal notebook overflow.
Injected storage failure produced visible feedback and a leave warning; cancellation
retained entries, retry saved them, and the warning cleared after confirmation.
Two real tabs rejected a stale edit without replacing the saved draft. Signing out
from a third tab cleared both notebook forms; a second synthetic account opened an
empty notebook while the original saved draft remained intact.

The complete PHP/JavaScript/build/recovery release gate, production health checks,
deployed revision and private before/after record comparison must pass before this
increment is reported as live. No real-student or physical-device acceptance,
faculty content validation, research-instrument validation or learning-effectiveness
claim is established by these engineering checks.
