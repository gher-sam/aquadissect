import { request } from './api.js';
import { escapeHtml as e } from './format.js';

export function setupStudentPasswordActions(root, confirmAction, showMessage) {
  root.addEventListener('click', async event => {
    const reset = event.target.closest('[data-reset-student-password]');
    const history = event.target.closest('[data-student-password-history]');
    if (!reset && !history) return;
    const panel = event.target.closest('[data-password-support]');
    if (!panel || panel.dataset.busy) return;
    panel.dataset.busy = 'true';
    const classId = panel.dataset.passwordClass ? Number(panel.dataset.passwordClass) : null;
    const endpoint = `/api/staff/students/${panel.dataset.passwordSupport}`;
    try {
      if (reset) {
        let form = panel.querySelector('[data-password-reset-form]');
        if (!form) {
          const container = document.createElement('div');
          container.innerHTML = `<form data-password-reset-form><label class="portal-field">Reason<select name="reason" required><option value="student_request">Student requested a reset</option><option value="forgot_password">Student forgot password</option><option value="first_sign_in_support">First sign-in support</option></select></label><p>This restores the student ID as the temporary password. The student must choose a new password before opening the course. Previous sessions lose course access. The reset is recorded in the audit log.</p><button class="portal-secondary" type="submit">Review reset</button><button class="portal-link" type="button" data-cancel-password-reset>Cancel</button></form>`;
          panel.append(container);
          form = container.querySelector('form');
          form.addEventListener('submit', async submitEvent => {
            submitEvent.preventDefault(); submitEvent.stopPropagation();
            if (form.dataset.busy) return;
            form.dataset.busy = 'true';
            try {
              if (!await confirmAction({ title: 'Reset student password?', description: `Reset password access for ${reset.dataset.passwordName}. The student must sign in again and choose a new private password. This action is audited.`, confirmLabel: 'Reset password' })) return;
              const result = await request(`${endpoint}/reset-password`, { method: 'POST', body: { classId, reason: form.elements.reason.value } });
              showMessage(result.message);
              container.remove();
              const output = panel.querySelector('[data-password-history]'); output.hidden = true;
            } catch (error) { showMessage(error.message, { tone: 'error', persistent: true }); }
            finally { delete form.dataset.busy; }
          });
          container.querySelector('[data-cancel-password-reset]').addEventListener('click', () => container.remove());
        }
        form.querySelector('select').focus();
      } else {
        const output = panel.querySelector('[data-password-history]');
        const result = await request(`${endpoint}/password-history${classId ? `?classId=${classId}` : ''}`);
        const actions = { student_initial_password_set: 'Initial password prepared', student_password_reset: 'Password reset', student_password_changed: 'New password saved' };
        const reasons = { forgot_password: 'Student forgot password', student_request: 'Student requested a reset', first_sign_in_support: 'First sign-in support' };
        output.innerHTML = `<h4>Password history</h4><p>Most recent 50 events. Passwords are never shown.</p>${result.events.length ? `<ul>${result.events.map(item => `<li>${e(actions[item.action] || item.action)} · ${e(item.actorRole.replaceAll('_', ' '))} #${e(item.actorId)} · ${e(new Date(item.at).toLocaleString())}${item.reason ? ` · ${e(reasons[item.reason] || item.reason)}` : ''}</li>`).join('')}</ul>` : '<p>No password events recorded.</p>'}`;
        output.hidden = false;
      }
    } catch (error) { showMessage(error.message, { tone: 'error', persistent: true }); }
    finally { delete panel.dataset.busy; }
  });
}
