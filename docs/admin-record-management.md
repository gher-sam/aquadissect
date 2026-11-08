# Administrator bulk record management

Open **Manage records** as an active administrator. Choose student accounts,
student enrollments, class sections, species lesson versions, or Safety & Tools
versions. Search and filter the list, select records on the current page, choose
an action, and enter an administrative reason. Changing pages or filters clears
the selection. Each batch is limited to 100 records.

**Review selection** shows the exact records, their impact, and any protection
that prevents the action. Nothing changes during review. Every selected record
must qualify; a blocked record prevents the entire batch. Permanent deletion
requires typing `DELETE` on the review screen.

## Archive and restore

- Student archiving retains identity, password hashes, enrollments, results and
  history. It blocks online access and invalidates existing password sessions.
  Restore returns the previous active or pending state without resetting a password.
- Section and enrollment archiving changes availability while retaining all
  existing records. Restore returns their previous state.
- Species lesson versions remain retained when archived. Restore returns content
  to draft or review, never directly to published/current or newly assigned.
- Unpublished safety versions use `withdrawn`, displayed as **Archived draft**.
  This avoids granting the access that archived published safety content may
  retain for existing assignments. Restoring published safety content requires
  a new independent faculty decision; old approvals cannot be reused. Published
  content remains immutable after restoration.
- Previously downloaded offline materials follow existing device-retention rules.
  Reconnecting applies current online permissions; archiving does not erase
  saved learning queues.

## Permanent deletion boundaries

Only unused records qualify. Activated students, linked enrollments, assignments,
learning results, assessment attempts, reviews, and other retained history prevent
deletion. Enrollment checks include unattributed historical results and paired
assessment attempts. Published or previously reviewed content cannot be deleted.

An unused draft may include its own preparation assets and import event. Only
those owned setup records may be removed with it; their setup-event audit is
preserved in the administrator batch audit. The same applies to creation-only
audit entries for otherwise unused student accounts and sections.

Uploaded source files remain private for recovery after draft-record deletion.
Deleted content version identifiers remain reserved; import a new version number
instead of reusing an existing storage directory. File purging is outside this
feature.

## Integrity and audit

Review tokens expire after five minutes and are encrypted and bound to the
administrator and browser session. Records and linked activity are checked again
under database locks before a transaction applies the entire selection. Changed
or newly linked records require another review. Request UUIDs make retries
idempotent; retries cannot repeat an action or substitute another selection.

Completed batch audits record the actor ID, selected IDs, action, reason, time,
before/after status and retained setup events. Passwords, setup codes and OTP
values are excluded. Keep private credentials and personal details out of the
reason field. Audit and archive-state tables join the existing delayed clone;
its delay is unchanged. Additive migrations and application rollback retain
administrative and academic records.

The feature does not archive or delete existing records automatically. Faculty
approval, publication, assessment activation, research consent and research data
retention decisions remain separate. Engineering verification does not establish
research or faculty acceptance.
