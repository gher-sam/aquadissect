import { request, appUrl } from './api.js';
import { escapeHtml as e } from './format.js';

export function setupStudentEmailFlow() {
  const root = document.querySelector('#studentEmailSetup');
  if (!root || root.dataset.initialized || window.AQUADISSECT_BOOTSTRAP?.user) return;
  root.dataset.initialized = 'true';
  let stage = 'claim', email = '', maskedEmail = '', busy = false;
  const field = (label, name, attrs = '') => `<label class="portal-field portal-wide">${label}<input name="${name}" ${attrs} required></label>`;
  function render(message = '') {
    let fields = '', label = '';
    if (stage === 'complete') {
      root.innerHTML = `<h2 tabindex="-1">Account activated</h2><p>Sign in, then open Home → Start orientation.</p><a class="portal-primary" href="${e(appUrl('/login'))}">Go to sign in</a>`;
      root.querySelector('h2').focus();
      return;
    }
    if (stage === 'unavailable') {
      root.innerHTML = '<p role="status">Student email setup is temporarily unavailable. Existing account access is still available below.</p>';
      return;
    }
    if (stage === 'claim') {
      fields = field('Student ID', 'identityNumber', 'autocomplete="username" maxlength="80"') + field('Private setup code', 'setupCode', 'autocomplete="off" maxlength="32" placeholder="ABCD-EFGH-JKLM"');
      label = 'Check my setup code';
    } else if (stage === 'email') {
      fields = `<p>Enter an email you can open now. Your account will keep its existing student ID and class enrollment.</p>${field('Your email', 'email', `type="email" autocomplete="email" maxlength="255" value="${e(email)}"`)}`;
      label = 'Send verification code';
    } else if (stage === 'otp') {
      fields = `<p>Check ${e(maskedEmail || 'your inbox')} for the six-digit code. Check Spam or Junk too. Only the newest code works, for 10 minutes.</p>${field('Email verification code', 'otp', 'inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6"')}<button class="portal-link" type="button" data-setup-email>Change email or request a new code</button><small>Wait 60 seconds between requests. Up to five codes can be requested per hour.</small>`;
      label = 'Verify my email';
    } else {
      fields = `<p>Email verified. Create a password within five minutes to finish setup.</p>${field('New password', 'password', 'type="password" autocomplete="new-password" minlength="8"')}${field('Confirm password', 'password_confirmation', 'type="password" autocomplete="new-password" minlength="8"')}<small>Use at least eight characters, including a letter and a number.</small><button class="portal-link" type="button" data-setup-email>Use a different email</button>`;
      label = 'Activate my account';
    }
    root.innerHTML = `<h2>First-time student setup</h2><p>Use the private setup code your administrator gave you. This activates your existing roster account.</p><form data-student-setup-form><div class="registration-grid">${fields}</div><p role="alert" data-setup-error ${message ? '' : 'hidden'}>${e(message)}</p><div class="registration-actions"><button type="button" class="portal-secondary" data-setup-cancel>Cancel setup</button><button type="submit" class="portal-primary">${label}</button></div></form>`;
  }
  function showError(message) {
    const target = root.querySelector('[data-setup-error]');
    if (target) { target.textContent = message; target.hidden = false; }
  }
  root.addEventListener('submit', async event => {
    if (!event.target.matches('[data-student-setup-form]')) return;
    event.preventDefault(); event.stopPropagation();
    if (busy) return;
    busy = true;
    const form = event.target, data = new FormData(form), button = event.submitter;
    if (button) button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    try {
      let result;
      if (stage === 'claim') result = await request('/api/student-setup/claim', { method: 'POST', body: { identityNumber: data.get('identityNumber'), setupCode: data.get('setupCode') } });
      else if (stage === 'email') {
        email = String(data.get('email')).trim().toLowerCase();
        result = await request('/api/student-setup/email', { method: 'POST', body: { email } });
      } else if (stage === 'otp') result = await request('/api/student-setup/verify', { method: 'POST', body: { otp: String(data.get('otp')).trim() } });
      else {
        if (data.get('password') !== data.get('password_confirmation')) throw new Error('The password confirmation does not match.');
        await request('/api/student-setup/complete', { method: 'POST', body: { password: data.get('password'), password_confirmation: data.get('password_confirmation') } });
        form.reset(); email = ''; stage = 'complete'; render(); return;
      }
      stage = result.stage; maskedEmail = result.maskedEmail || ''; render();
      root.querySelector('input')?.focus();
    } catch (error) { showError(error.message); }
    finally { busy = false; form.removeAttribute('aria-busy'); if (button) button.disabled = false; }
  });
  root.addEventListener('click', async event => {
    if (event.target.closest('[data-setup-email]') && !busy) { stage = 'email'; render(); root.querySelector('input')?.focus(); }
    if (!event.target.closest('[data-setup-cancel]') || busy) return;
    busy = true;
    try { await request('/api/student-setup/cancel', { method: 'POST' }); email = ''; stage = 'claim'; render(); }
    catch (error) { showError(error.message); }
    finally { busy = false; }
  });
  render();
  request('/api/student-setup/status').then(result => { stage = result.enabled ? result.stage : 'unavailable'; maskedEmail = result.maskedEmail || ''; render(); }).catch(error => showError(error.message));
}

export function setupStudentCodeBatch(root, classes, confirmAction) {
  const holder = root.querySelector('#studentSetupCodeBatch');
  if (!holder) return;
  const active = classes.filter(item => item.status === 'active');
  holder.innerHTML = `<p>Choose a section and the pending students who need to enter their own email. New setup codes replace any previous setup codes for the selected accounts.</p><p>Students can use these codes only after email delivery has been enabled. Give each student only their own code through your private delivery workflow.</p><form data-setup-code-batch><label class="portal-field">Class section<select name="section" required><option value="">Choose a section</option>${active.map(item => `<option value="${item.id}">${e(item.name)} · ${e(item.academicYear)} · semester ${e(item.semester)}</option>`).join('')}</select></label><div data-setup-students></div><p data-setup-count role="status">0 students selected</p><button type="submit" class="portal-primary" disabled>Prepare selected setup codes</button><p data-setup-batch-message role="status" hidden></p></form><div data-setup-codes-output></div>`;
  const form = holder.querySelector('form');
  function count() {
    const n = form.querySelectorAll('[name="userId"]:checked').length;
    form.querySelector('[data-setup-count]').textContent = `${n} students selected`;
    form.querySelector('[type="submit"]').disabled = n === 0;
  }
  form.addEventListener('change', event => {
    if (event.target.name === 'section') {
      const section = active.find(item => String(item.id) === event.target.value);
      const students = (section?.students || []).filter(student => student.accountStatus === 'pending' && !student.email);
      form.querySelector('[data-setup-students]').innerHTML = students.length ? `<label class="portal-check"><input type="checkbox" data-setup-select-all><span>Select all ${students.length} eligible students in this section</span></label>${students.map(student => `<label class="portal-check"><input type="checkbox" name="userId" value="${student.id}"><span>${e(student.name)} · ${e(student.identityNumber)}</span></label>`).join('')}` : '<p>No pending students without email in this section.</p>';
    }
    if (event.target.matches('[data-setup-select-all]')) form.querySelectorAll('[name="userId"]').forEach(input => { input.checked = event.target.checked; });
    count();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault(); event.stopPropagation();
    if (form.dataset.busy) return;
    const ids = [...form.querySelectorAll('[name="userId"]:checked')].map(input => Number(input.value));
    if (!ids.length) return;
    form.dataset.busy = 'true';
    const button = form.querySelector('[type="submit"]'), message = form.querySelector('[data-setup-batch-message]');
    button.disabled = true; message.hidden = true;
    try {
      if (!await confirmAction({ title: 'Prepare private student setup codes?', description: `Create new seven-day setup codes for ${ids.length} selected students. Their previous setup codes will stop working. Email addresses and account status will not change.`, confirmLabel: 'Prepare codes' })) return;
      const result = await request('/api/admin/student-setup-codes', { method: 'POST', body: { userIds: ids } });
      holder.querySelector('[data-setup-codes-output]').innerHTML = `<h3>Private setup codes</h3><p>Shown only in this response. Copy and deliver each code privately to its matching student ID. Do not put this list in a shared class report.</p><label class="portal-field">Student ID · setup code · expiry<textarea rows="${Math.min(12, result.codes.length + 1)}" readonly autocomplete="off">${e(result.codes.map(item => `${item.identityNumber}\t${item.code}\t${new Date(item.expiresAt).toLocaleString()}`).join('\n'))}</textarea></label>`;
    } catch (error) { message.textContent = error.message; message.hidden = false; }
    finally { delete form.dataset.busy; count(); }
  });
}
