import { escapeHtml as e } from './format.js';

export function studentPasswordActions(student, classId = null) {
  const context = classId ? ` data-password-class="${Number(classId)}"` : '';
  return `<div class="password-support" data-password-support="${Number(student.id)}"${context}>
    ${student.mustChangePassword ? '<small>New password required at next sign-in.</small>' : ''}
    <button type="button" class="portal-secondary" data-reset-student-password data-password-name="${e(student.name)}">Reset password</button>
    <button type="button" class="portal-link" data-student-password-history>Password history</button>
    <div data-password-history hidden></div>
  </div>`;
}
