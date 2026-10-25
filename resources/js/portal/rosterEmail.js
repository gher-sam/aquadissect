import { escapeHtml } from './format.js';

export function studentEmailAction(student) {
  if (student.accountStatus !== 'pending') return '<span class="portal-muted">No action needed</span>';
  if (student.email) return `<button class="portal-secondary" type="button" data-issue-activation="${student.id}" aria-label="Issue new activation code for ${escapeHtml(student.name)}">Issue activation code</button>`;
  return `<details class="workflow-disclosure"><summary>Add email for activation</summary>
    <form data-student-email="${student.id}" class="package-import-card package-import-card--nested">
      <p><strong>${escapeHtml(student.name)}</strong><br>Institutional ID: ${escapeHtml(student.identityNumber || '—')}</p>
      <input type="hidden" name="identityNumber" value="${escapeHtml(student.identityNumber || '')}">
      <label class="portal-field">Verified student email<input type="email" name="email" maxlength="255" autocomplete="off" required></label>
      <p>Check the address with the student. Saving leaves the account pending. Issue an activation code separately.</p>
      <button type="submit" class="portal-secondary">Review and save email</button>
      <p data-email-message role="status" hidden></p>
    </form></details>`;
}
