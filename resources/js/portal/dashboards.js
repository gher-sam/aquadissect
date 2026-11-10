import { fishCarousel } from './fishAngles.js';
import { studentEmailAction } from './rosterEmail.js';
import { studentPasswordActions } from './studentPasswordView.js';
import { escapeHtml, titleCase } from './format.js';
import { rosterTable } from './rosterTables.js';
import { staffLessonVersions } from './speciesFocus.js';

function formatBytes(bytes = 0) {
  if (bytes < 1024) return `${bytes} B`;
  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let unit = units[0];
  for (let index = 1; value >= 1024 && index < units.length; index += 1) {
    value /= 1024;
    unit = units[index];
  }
  return `${value.toFixed(value >= 10 ? 1 : 2)} ${unit}`;
}

function formatDate(value, fallback = 'Date unavailable') {
  if (!value) return fallback;

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return fallback;

  try {
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(date);
  } catch {
    return fallback;
  }
}

function speciesReviewForm(item, currentUser) {
  if (item.status !== 'review' || item.approvalCurrent || String(item.importedBy) === String(currentUser?.id)) return '';

  return `<form class="module-review-form species-review-form" data-species-review="${item.id}">
    <h3>Independent faculty review</h3>
    <p>Check specimen identity, fish details, local names, organ placement, hotspot alignment, procedure and safety. Review each calibration source and its permitted measurement scope before approving.</p>
    <label class="portal-field">Decision<select name="decision" required><option value="approved">Approve for administrator publication</option><option value="changes_requested">Request changes</option></select></label>
    <label class="portal-field">Review notes<textarea name="notes" rows="3" minlength="10" maxlength="3000" required></textarea></label>
    <label class="portal-field">Evidence reference<input name="evidenceReference" maxlength="500" placeholder="Faculty record, reviewed reference, or approved procedure"></label>
    <label class="portal-check"><input name="curriculumAlignmentConfirmed" type="checkbox"><span>I confirmed the anatomy and learning sequence align with the intended MB 304L activity.</span></label>
    <label class="portal-check"><input name="safetyReviewConfirmed" type="checkbox"><span>I confirmed the handling and safety directions, including specimen-specific hazards.</span></label>
    <button class="portal-secondary" type="submit">Record faculty decision</button>
  </form>`;
}

function packageCard(item, { manager = false, offline = false, isAdmin = false, currentUser = null, versionCount = 0 } = {}) {
  const status = item.status === 'published' ? 'Published' : item.status === 'review' ? 'In review' : titleCase(item.status);
  const score = item.progress ? `${item.progress.score} / ${item.progress.total}` : 'Not started';
  const canOpen = (item.status === 'published' || manager) && !item.locked && !item.offlineUnavailable;
  const mainAction = canOpen
    ? `<button class="portal-primary" type="button" data-open-package="${item.id}">${item.progress ? 'Review lesson' : item.status === 'published' ? 'Open lesson' : 'Preview draft'}</button>`
    : '';
  const managerActions = manager ? `
    <div class="package-actions">
      ${item.slug === 'rabbitfish' && ['admin','instructor'].includes(currentUser?.role) && item.status !== 'archived' ? `<button class="portal-secondary" type="button" data-manage-clips="${item.id}">Manage guided clips</button>` : ''}
      ${['admin','instructor'].includes(currentUser?.role) ? '<button class="portal-secondary" type="button" data-revise-package="'+item.id+'" data-manifest-url="'+escapeHtml(item.manifestUrl)+'">Revise fish details and imagery</button>' : ''}
      ${currentUser?.role !== 'program_head' && item.status === 'draft' ? `<button class="portal-primary" data-submit-package="${item.id}" type="button">Send for faculty review</button>` : ''}
      ${isAdmin && item.status === 'review' && item.approvalCurrent ? `<button class="portal-primary" data-publish-package="${item.id}" type="button">Publish approved version</button>` : ''}
      ${isAdmin && item.status === 'published' && !item.isCurrent ? `<button class="portal-secondary" data-activate-package="${item.id}" type="button">Set active version</button>` : ''}
      ${isAdmin && item.status === 'published' ? `<button class="portal-secondary" data-archive-package="${item.id}" type="button">Archive</button>` : ''}
      ${isAdmin && item.status === 'archived' ? `<button class="portal-secondary" data-restore-package="${item.id}" type="button">Restore for fresh review</button>` : ''}
      ${currentUser?.role !== 'program_head' ? `<a class="portal-secondary button-link" href="${item.downloadUrl}">Export .aqpack</a>` : ''}
    </div>
    ${item.latestReview ? `<div class="module-review-summary"><strong>${escapeHtml(titleCase(item.latestReview.decision.replace('_', ' ')))}</strong><span>${escapeHtml(item.latestReview.reviewerRole)}</span><p>${escapeHtml(item.latestReview.notes)}</p>${item.latestReview.evidenceReference ? `<small>Evidence: ${escapeHtml(item.latestReview.evidenceReference)}</small>` : ''}</div>` : ''}
    ${item.status === 'review' && !item.approvalCurrent && String(item.importedBy) === String(currentUser?.id) ? '<div class="review-waiting-note"><strong>Waiting for an independent reviewer</strong><span>Another authorized faculty member or administrator must record the decision.</span></div>' : ''}
    ${speciesReviewForm(item, currentUser)}` : '';
  const offlineAction = offline ? `<button class="portal-secondary" type="button" data-offline-toggle="${item.id}" ${item.locked ? 'disabled' : ''}>${item.locked ? 'Prerequisite required' : ''}</button>` : '';

  return `
    <article class="species-card" data-package-card="${item.id}">
      <div class="species-card-image">${!manager && !offline && item.cardPreviewVideoUrl ? fishCarousel(item) : item.naturalImageUrl ? `<img src="${item.naturalImageUrl}" alt="${escapeHtml(item.commonName)} natural specimen illustration">` : '<span aria-hidden="true">A</span>'}</div>
      <div class="species-card-body">
        <div class="species-card-topline"><span class="status-badge ${item.status === 'published' ? 'is-online' : ''}">${status}</span><small>v${escapeHtml(item.version)}</small></div>
        ${item.slug === 'rabbitfish' ? '<span class="status-badge">Development focus · Rabbitfish</span>' : ''}
        <h2>${escapeHtml(item.commonName)}</h2>
        <p class="scientific-name"><em>${escapeHtml(item.scientificName || '')}</em></p>
        <p>${escapeHtml(item.description || '')}</p>
        ${!item.locked && !item.offlineUnavailable ? '<button class="portal-secondary" type="button" data-fish-manifest="'+escapeHtml(item.manifestUrl)+(item.availableClassId?(item.manifestUrl.includes('?')?'&':'?')+'classId='+item.availableClassId:'')+'">Fish details</button>' : ''}
        ${item.offlineUnavailable ? '<p class="offline-preparation-note">Prepare this lesson while connected before opening it offline.</p>' : ''}
        <div class="lesson-meta"><span>${escapeHtml(item.duration || 'Guided activity')}</span>${item.hasVideo ? '<span class="is-featured">Swimming video</span>' : ''}<span>2.5D layers</span>${item.has3d ? '<span>Optional 3D</span>' : ''}</div>
        ${item.draftNotice && item.status !== 'published' ? `<div class="draft-notice">${escapeHtml(item.draftNotice)}</div>` : ''}
        ${item.locked ? `<div class="prerequisite-notice"><i data-lucide="lock-keyhole" aria-hidden="true"></i><span>${escapeHtml(item.lockReason || 'Complete Safety & Tools before opening this lesson.')}</span><button type="button" data-portal-page="safety">Open prerequisite</button></div>` : ''}
        <div class="package-score"><span>Progress</span><strong>${score}</strong></div>
        <div class="package-actions">${mainAction}${offlineAction}</div>
        ${versionCount > 1 ? `<div class="package-actions"><button class="portal-secondary" type="button" data-portal-page="manager">Version history (${versionCount})</button><small>Other versions remain in Species content.</small></div>` : ''}
        ${managerActions}
      </div>
    </article>`;
}

function motionFeature(item) {
  if (item?.locked) {
    return `<section class="motion-feature" aria-labelledby="motionFeatureTitle"><div class="motion-feature-copy"><span class="motion-feature-kicker"><span aria-hidden="true"></span>Required preparation</span><h2 id="motionFeatureTitle">Complete Safety &amp; Tools first</h2><p>${escapeHtml(item.lockReason || 'Finish the assigned prerequisite before opening anatomy lessons.')}</p><div class="motion-feature-points"><span>Required safety steps</span><span>Tools and PPE</span><span>Unlimited formative retries</span></div><button class="portal-primary motion-feature-action" type="button" data-portal-page="safety">Open Safety &amp; Tools</button></div><div class="motion-feature-stage" aria-hidden="true"><span class="motion-orbit motion-orbit-one"></span><span class="motion-orbit motion-orbit-two"></span><i class="motion-feature-shield" data-lucide="shield-check"></i><span class="motion-feature-label">Prerequisite</span></div></section>`;
  }
  if (!item?.hasVideo && !item?.has3d) return '';

  if (!item.hasVideo) {
    return `
      <section class="motion-feature" aria-labelledby="motionFeatureTitle">
        <div class="motion-feature-copy">
          <span class="motion-feature-kicker"><span aria-hidden="true"></span>Recommended next activity</span>
          <h2 id="motionFeatureTitle">Explore ${escapeHtml(item.commonName)} anatomy</h2>
          <p>Move the specimen yourself, focus on one structure at a time, and open the internal view when you are ready.</p>
          <div class="motion-feature-points"><span>You control the view</span><span>Guided labels</span></div>
          <button class="portal-primary motion-feature-action" type="button" data-open-package="${item.id}" data-viewer-mode="3d">Open 3D view</button>
        </div>
        <div class="motion-feature-stage" aria-hidden="true">
          <span class="motion-orbit motion-orbit-one"></span><span class="motion-orbit motion-orbit-two"></span>
          ${item.naturalImageUrl ? `<img class="motion-feature-fish" src="${item.naturalImageUrl}" alt="">` : '<span class="motion-feature-placeholder">A</span>'}
          <span class="motion-feature-label">You control the view</span>
        </div>
      </section>`;
  }

  return `
    <section class="motion-feature" aria-labelledby="motionFeatureTitle">
      <div class="motion-feature-copy">
        <span class="motion-feature-kicker"><span aria-hidden="true"></span>Recommended first step</span>
        <h2 id="motionFeatureTitle">Watch ${escapeHtml(item.commonName)} swim</h2>
        <p>Begin with a short side-view observation, then open the anatomy viewer to explore the specimen.</p>
        <div class="motion-feature-points" aria-label="Viewer features">
          <span>Watch first</span><span>Fixed side view</span><span>Then explore anatomy</span>
        </div>
        <div class="motion-feature-actions">
          <button class="portal-primary motion-feature-action" type="button" data-open-package="${item.id}" data-viewer-mode="video">Watch specimen</button>
          ${item.has3d ? `<button class="portal-secondary motion-feature-secondary" type="button" data-open-package="${item.id}" data-viewer-mode="3d">Open 3D view</button>` : ''}
        </div>
      </div>
      <div class="motion-feature-stage">
        <span class="motion-orbit motion-orbit-one"></span><span class="motion-orbit motion-orbit-two"></span>
        <video class="motion-feature-video" src="${item.videoUrl}" poster="${item.naturalImageUrl || ''}" muted loop playsinline disablepictureinpicture controlslist="nodownload noplaybackrate noremoteplayback" preload="metadata" aria-hidden="true" tabindex="-1"></video>
        <span class="motion-feature-label">Short observation</span>
      </div>
    </section>`;
}

export function speciesLibrary(packages, options = {}) {
  const visible = options.singleSpecies && !options.manager ? staffLessonVersions(packages) : packages;
  return visible.length
    ? visible.map((item) => packageCard(item, { ...options, versionCount: options.singleSpecies && !options.manager ? packages.filter(version => version.slug === item.slug).length : 0 })).join('')
    : '<div class="empty-state"><strong>No rabbitfish lesson is available yet</strong><span>Rabbitfish must be independently reviewed, published and assigned before students can open it.</span></div>';
}

export { studentReadinessView as studentOrientationPath } from './studentReadiness.js';

export function studentDashboard(packages) {
  const flagship = packages[0];
  if (!flagship) return '<div class="empty-state"><strong>No laboratory lesson assigned yet</strong><span>You can complete orientation above now. Your instructor will announce later approved, assigned laboratory activities.</span></div>';
  return `${motionFeature(flagship)}<div class="dashboard-layout"><div class="species-library-grid">${packageCard(flagship)}</div><aside class="status-card"><span class="portal-kicker">My learning</span><h2>Learning progress</h2><dl><div><dt>Assigned species</dt><dd>${packages.length}</dd></div><div><dt>Lesson version</dt><dd>${escapeHtml(flagship.version)}</dd></div><div><dt>Lesson status</dt><dd>${escapeHtml(flagship.updateStatus || 'Ready')}</dd></div><div><dt>Offline access</dt><dd>Prepare in this browser</dd></div></dl><button class="portal-secondary" data-portal-page="downloads" type="button">Open offline access</button></aside></div>`;
}

export function instructorDashboard(packages, classes) {
  const drafts = packages.filter((item) => ['draft', 'review'].includes(item.status)).length;
  return `
    <div class="metric-grid">
      <article class="metric-card"><span class="metric-label">Assigned sections</span><strong>${classes.length}</strong><small>MB 304L offerings</small></article>
      <article class="metric-card"><span class="metric-label">Published library</span><strong>${packages.filter((item) => item.status === 'published').length}</strong><small>Assignable packages</small></article>
      <article class="metric-card"><span class="metric-label">Draft or review</span><strong>${drafts}</strong><small>Available content versions</small></article>
      <article class="metric-card"><span class="metric-label">Content process</span><strong>Review</strong><small>Approval required before publication</small></article>
    </div>
    <div class="module-grid">
      <article><span>01</span><h2>Class sections</h2><p>See the students, safety status, and progress in your assigned classes.</p><button class="portal-secondary" data-portal-page="classes" type="button">Open sections</button></article>
      <article><span>02</span><h2>Class activities</h2><p>Choose a published lesson for a class. Started lessons keep their saved version.</p><button class="portal-secondary" data-portal-page="assignments" type="button">Manage activities</button></article>
      <article><span>03</span><h2>Species content</h2><p>Preview drafts and send prepared learning content for review.</p><button class="portal-secondary" data-portal-page="manager" type="button">Open content</button></article>
      <article><span>04</span><h2>Student results</h2><p>Open a class section to review formative attempts and recorded feedback.</p><button class="portal-secondary" data-portal-page="classes" type="button">View class results</button></article>
    </div>`;
}

export function adminDashboard(pendingUsers, packages, classes) {
  const rows = pendingUsers.map((account) => `<tr><td>${escapeHtml(account.firstName)} ${escapeHtml(account.lastName)}</td><td>${escapeHtml(account.email)}</td><td>${titleCase(account.role)}</td><td>Pending</td><td><button class="portal-secondary" type="button" data-approve-account="${escapeHtml(account.id)}">Approve</button></td></tr>`).join('');
  const storageRecorded = packages.every((item) => item.sizeBytes !== null && item.sizeBytes !== undefined && item.sizeBytes !== '' && Number.isFinite(Number(item.sizeBytes)) && Number(item.sizeBytes) >= 0);
  const storageUsage = storageRecorded ? packages.reduce((total, item) => total + Number(item.sizeBytes), 0) : null;
  const storageLabel = storageUsage === null ? 'Not recorded' : formatBytes(storageUsage);
  return `
    <div class="metric-grid" aria-label="System summary">
      <article class="metric-card"><span class="metric-label">Pending approvals</span><strong>${pendingUsers.length}</strong><small>Instructor requests</small></article>
      <article class="metric-card"><span class="metric-label">Published packages</span><strong>${packages.filter((item) => item.status === 'published').length}</strong><small>Student-ready versions</small></article>
      <article class="metric-card"><span class="metric-label">Draft or review</span><strong>${packages.filter((item) => ['draft', 'review'].includes(item.status)).length}</strong><small>Awaiting lifecycle action</small></article>
      <article class="metric-card"><span class="metric-label">Sections</span><strong>${classes.length}</strong><small>One reusable MB 304L course</small></article>
      <article class="metric-card"><span class="metric-label">Package storage</span><strong>${storageLabel}</strong><small>Private versioned content</small></article>
    </div>
    <div class="admin-dashboard-grid">
      <section class="admin-table-card"><div class="card-heading"><div><span class="portal-kicker">Account management</span><h2>Instructor requests</h2></div><button class="portal-secondary" data-portal-page="roster" type="button">Manage roster</button></div>
      ${rows ? `<div class="admin-table-wrap"><table><caption>Pending instructor account requests</caption><thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>${rows}</tbody></table></div>` : '<div class="empty-state"><strong>Approval queue is clear</strong><span>New instructor requests will appear here.</span></div>'}</section>
      <aside class="admin-operations-card"><span class="portal-kicker">Operational overview</span><h2>System status</h2><ul class="operation-list"><li><span><strong>Main records</strong><small>Accounts, classes, and progress</small></span><span class="status-badge is-online">Available</span></li><li><span><strong>Safety copy</strong><small>Scheduled after 12 hours</small></span><span class="status-badge">Scheduled</span></li><li><span><strong>Learning files</strong><small>${storageUsage === null ? 'Size not recorded' : `${storageLabel} stored securely`}</small></span><span class="status-badge is-online">Ready</span></li></ul></aside>
    </div>`;
}

export function classCards(classes, role = 'student') {
  return classes.length ? `<div class="class-grid">${classes.map((item) => {
    const module = item.moduleAssignments?.[0];
    const moduleSummary = module ? `<div class="class-module-summary"><i data-lucide="shield-check" aria-hidden="true"></i><span><strong>${escapeHtml(module.title)}</strong><small>v${escapeHtml(module.version)} · ${module.required ? 'Required' : 'Optional'}</small></span></div>` : '<div class="class-module-summary"><i data-lucide="circle-dashed" aria-hidden="true"></i><span><strong>No safety module assigned</strong></span></div>';
    const studentActions = role === 'student' && item.status === 'active' ? item.assignments.map((assignment) => assignment.locked
      ? `<button class="portal-secondary" type="button" data-portal-page="safety"><i data-lucide="lock-keyhole" aria-hidden="true"></i> Complete Safety &amp; Tools to open ${escapeHtml(assignment.commonName || 'lesson')}</button>`
      : `<button class="portal-secondary" type="button" data-open-package="${assignment.packageId}" data-class-id="${item.id}">Open ${escapeHtml(assignment.commonName || 'lesson')} for this section</button>`).join('') : '';
    const overrides = ['instructor', 'admin'].includes(role) && module ? `<div class="override-panel"><form data-section-override="${item.id}" data-module-id="${module.moduleId}"><label class="portal-field">Section exception reason<input name="reason" minlength="10" required placeholder="Document why this section may proceed"></label><button class="portal-secondary" type="submit">Grant section exception</button></form>${(item.moduleOverrides || []).length ? `<ul>${item.moduleOverrides.map((override) => `<li><span>${override.studentId ? 'Learner exception' : 'Section exception'}: ${escapeHtml(override.reason)}</span><button class="portal-link" type="button" data-revoke-module-override="${override.id}">Revoke</button></li>`).join('')}</ul>` : '<small>No active prerequisite exceptions.</small>'}</div>` : '';
    return `<article class="class-card" data-class-card="${item.id}"><span class="portal-kicker">${escapeHtml(item.courseCode || 'MB-304L')} · ${escapeHtml(item.code)}</span><h2>${escapeHtml(item.name)}</h2><p>${escapeHtml(item.academicYear || 'Legacy')} · ${escapeHtml(item.semester || 'Unassigned')} semester · ${escapeHtml(item.status || 'active')}</p><p>${item.studentCount} enrolled learner${item.studentCount === 1 ? '' : 's'}</p>${moduleSummary}<ul>${item.assignments.map((assignment) => `<li><strong>${escapeHtml(assignment.commonName || 'Species')}</strong><span>v${escapeHtml(assignment.version || '')}${assignment.locked ? ' · Locked' : ''} · ${assignment.dueAt ? `Due ${escapeHtml(formatDate(assignment.dueAt))}` : 'No due date'}</span></li>`).join('') || '<li><span>No species assigned</span></li>'}</ul>${studentActions}${role !== 'student' ? `<details class="roster-details"><summary>View students, safety status, and progress</summary>${studentTable(item.students || [], item, role)}</details>${overrides}` : ''}</article>`;
  }).join('')}</div>` : '<div class="empty-state"><strong>No sections are linked to this account</strong><span>The administrator assigns instructors and enrolls students in MB 304L sections.</span></div>';
}

function studentTable(students, learningClass = null, role = 'admin') {
  const module = learningClass?.moduleAssignments?.[0];
  const support = ['admin', 'instructor'].includes(role) && learningClass?.passwordResetsEnabled;
  const sectionName = escapeHtml(learningClass?.name || 'this section');
  return students.length ? `<div class="admin-table-wrap"><table class="student-progress-table"><caption>Student progress for ${sectionName}</caption><thead><tr><th scope="col">Student</th><th scope="col">Institutional ID</th><th scope="col">Account</th><th scope="col">Safety prerequisite</th><th scope="col">Attempts</th><th scope="col">Latest result</th>${support ? '<th scope="col">Account support</th>' : ''}</tr></thead><tbody>${students.map((item) => {
    const status = item.moduleStatus;
    const safety = status ? (status.satisfied ? (status.overridden ? 'Exception active' : 'Complete') : 'Required') : 'Not assigned';
    return `<tr><td data-label="Student">${escapeHtml(item.name)}</td><td data-label="Institutional ID">${escapeHtml(item.identityNumber || '—')}</td><td data-label="Account">${escapeHtml(item.accountStatus)}</td><td data-label="Safety prerequisite"><span class="status-badge ${status?.satisfied ? 'is-online' : ''}">${escapeHtml(safety)}</span>${role !== 'program_head' && module && !status?.satisfied ? `<form class="learner-override-form" data-learner-override="${learningClass.id}" data-module-id="${module.moduleId}" data-student-id="${item.id}"><input name="reason" aria-label="Reason for ${escapeHtml(item.name)} exception" minlength="10" required placeholder="Document reason"><button class="portal-link" type="submit">Grant exception</button></form>` : ''}</td><td data-label="Attempts">${item.attemptCount}</td><td data-label="Latest result">${item.latestResult ? `${item.latestResult.score} / ${item.latestResult.total}` : '—'}</td>${support ? `<td data-label="Account support">${studentPasswordActions(item, learningClass.id)}</td>` : ''}</tr>`;
  }).join('')}</tbody></table></div>` : '<p>No students are enrolled in this section.</p>';
}

export function adminRosterView(data, preview = [], activationCodes = [], error = null, emailDeliveryUrl = '/admin/email-delivery') {
  if (!data && error) return `<div class="empty-state roster-load-error" role="alert"><strong>We could not load the people and sections</strong><span>${escapeHtml(error)}</span><button class="portal-secondary" type="button" data-retry-roster>Try again</button></div>`;
  if (!data) return '<div class="empty-state"><strong>Loading roster</strong><span>Fetching MB 304L sections and accounts.</span></div>';
  const active = data.classes.filter((item) => item.status === 'active');
  const sectionOptions = active.map((item) => `<option value="${item.id}">${escapeHtml(item.name)} · ${escapeHtml(item.academicYear || 'Legacy')} · ${escapeHtml(item.semester || 'Unassigned')}</option>`).join('');
  const instructorOptions = data.instructors.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join('');
  const issued = activationCodes.length ? `<div class="registration-note" role="status"><strong>One-time activation codes — copy now and deliver privately.</strong><p>These codes are shown only in this response and expire after three days.</p>${activationCodes.map((item) => `<p><strong>${escapeHtml(item.email)}</strong><br><code>${escapeHtml(item.code)}</code></p>`).join('')}</div>` : '';
  const report = data.report || { sections: [], attempts: 0, scorePercent: null, legacyUnattributedAttempts: 0 };
  const reportView = `<section class="admin-table-card"><div class="card-heading"><div><span class="portal-kicker">Formative activity only</span><h2>Section and course overview</h2></div></div><p>${report.attempts} section-attributed attempts · overall score ${report.scorePercent === null ? '—' : `${report.scorePercent}%`}. ${report.legacyUnattributedAttempts} legacy attempt(s) remain unattributed.</p><div class="admin-table-wrap"><table><caption>Formative results by section</caption><thead><tr><th scope="col">Section</th><th scope="col">Year / semester</th><th scope="col">Students</th><th scope="col">Attempts</th><th scope="col">Score</th></tr></thead><tbody>${report.sections.map((item) => `<tr><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.academicYear || 'Legacy')} / ${escapeHtml(item.semester || '—')}</td><td>${item.students}</td><td>${item.attempts}</td><td>${item.scorePercent === null ? '—' : `${item.scorePercent}%`}</td></tr>`).join('')}</tbody></table></div></section>`;
  const previewTable = preview.length ? `<div class="admin-table-wrap"><table><caption>Roster rows ready for review</caption><thead><tr><th scope="col">Row</th><th scope="col">Student</th><th scope="col">Email</th><th scope="col">Status</th><th scope="col">Review</th></tr></thead><tbody>${preview.map((item) => `<tr><td>${item.row}</td><td>${escapeHtml(`${item.firstName} ${item.lastName}`)}</td><td>${escapeHtml(item.email)}</td><td>${escapeHtml(item.status)}</td><td>${escapeHtml(item.message)}</td></tr>`).join('')}</tbody></table></div>${preview.every((item) => !['invalid', 'conflict'].includes(item.status)) ? '<button class="portal-primary" type="button" data-import-reviewed-roster>Import reviewed roster</button>' : '<p>Resolve conflicts in the CSV before importing. No rows have been changed.</p>'}` : '';
  const sectionRows = data.classes.map((item) => {
    const instructors = item.instructors.map((person) => person.name).join(', ') || 'Not assigned';
    return `<tr data-table-row data-class-card="${item.id}" data-search="${escapeHtml([item.name, item.code, item.academicYear, item.semester, item.status, instructors].join(' '))}"><td data-sort-value="${escapeHtml(item.name)}"><h3>${escapeHtml(item.name)}</h3><small>${escapeHtml(item.code)}</small></td><td>${escapeHtml(item.academicYear || 'Legacy')}</td><td>${escapeHtml(item.semester || 'Unassigned')}</td><td>${item.studentCount}</td><td>${escapeHtml(instructors)}</td><td><span class="roster-status ${item.status === 'active' ? 'is-active' : ''}">${escapeHtml(titleCase(item.status))}</span></td><td><button type="button" class="portal-secondary" data-table-expand aria-expanded="false" aria-controls="roster-section-${item.id}">Manage section</button></td></tr>
    <tr data-table-detail id="roster-section-${item.id}" hidden><td colspan="7"><div class="roster-section-detail" data-class-card="${item.id}"><h3>${escapeHtml(item.name)} — section details</h3>${item.status === 'active' ? `<form data-instructor-form="${item.id}" class="roster-inline-form"><label>Assign instructor<select name="instructorId" required>${instructorOptions || '<option value="">No approved instructors</option>'}</select></label><button class="portal-secondary" type="submit" ${instructorOptions ? '' : 'disabled'}>Assign</button></form><button class="portal-link" type="button" data-offering-status="archived" data-offering-id="${item.id}">Archive section</button>` : `<button class="portal-secondary" type="button" data-offering-status="active" data-offering-id="${item.id}">Restore section</button>`}${studentTable(item.students || [], item)}</div></td></tr>`;
  }).join('');
  const sectionsTable = rosterTable('sections', 'Sections', ['Section', 'Academic year', 'Semester', 'Students', 'Instructor', 'Status', 'Actions'], sectionRows);
  const people = new Map();
  data.classes.forEach((section) => (section.students || []).forEach((student) => {
    if (!people.has(student.id)) people.set(student.id, { ...student, sections: [] });
    people.get(student.id).sections.push(section.name);
  }));
  const peopleRows = [...people.values()].map((student) => `<tr data-table-row><td>${escapeHtml(student.name)}</td><td>${escapeHtml(student.identityNumber || '—')}</td><td>${escapeHtml(student.sections.join(', '))}</td><td><span class="roster-status ${student.accountStatus === 'active' ? 'is-active' : ''}">${escapeHtml(titleCase(student.accountStatus))}</span></td><td>${studentEmailAction(student, data.passwordSetupEnabled, data.initialPasswordEnabled)}</td></tr>`).join('');
  const peopleTable = rosterTable('students', 'Enrolled students', ['Student', 'Institutional ID', 'Sections', 'Account', 'Actions'], peopleRows);

  return `${issued}${sectionsTable}${peopleTable}${data.initialPasswordEnabled ? '<p>First-time students sign in with their student ID as the username and temporary password, then choose a new password. Use Reset password for account support; every reset is audited.</p>' : ''}<details class="workflow-disclosure" ${data.initialPasswordEnabled ? 'hidden' : ''}><summary>Prepare private student setup codes</summary>${data.passwordSetupEnabled ? '<p>Students use their student ID and choose their own password. Email verification is deferred.</p>' : `<p><a href="${escapeHtml(emailDeliveryUrl)}">Set up verification email delivery</a></p>`}<div id="studentSetupCodeBatch" class="package-import-card"></div></details><div class="roster-grid roster-create-tools">
    <details class="workflow-disclosure"><summary>Add a section</summary><form id="offeringCreateForm" class="package-import-card"><span class="workflow-step">Step 1</span><h2>Add a section</h2><p>${escapeHtml(data.course.name)}</p><label class="portal-field">Academic year<input name="academicYear" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}" required></label><label class="portal-field">Semester<select name="semester"><option value="1">First</option><option value="2">Second</option><option value="summer">Summer</option></select></label><label class="portal-field">Section name or code<input name="sectionCode" placeholder="A" required></label><button class="portal-primary" type="submit">Add section</button></form></details>
    <details class="workflow-disclosure"><summary>Add one student</summary><form id="studentCreateForm" class="package-import-card"><span class="workflow-step">Step 2</span><h2>Add one student</h2><label class="portal-field">Section<select name="classId" required>${sectionOptions || '<option value="">Create a section first</option>'}</select></label><label class="portal-field">First name<input name="firstName" required></label><label class="portal-field">Last name<input name="lastName" required></label><label class="portal-field">Email (optional)<input name="email" type="email"></label><label class="portal-field">Institutional ID<input name="identityNumber" required></label><button class="portal-primary" type="submit" ${sectionOptions ? '' : 'disabled'}>Create or enroll student</button></form></details>
    <details class="workflow-disclosure roster-bulk-disclosure"><summary><span><strong>Upload students from a file</strong><small>Select students and course sections before import</small></span></summary><div id="bulkRosterUpload"></div></details>
  </div><div class="roster-preview">${previewTable}</div>${reportView}`;
}

export function managerView(packages, role, currentUser) {
  const importForm = role === 'instructor' ? '<details class="workflow-disclosure workflow-disclosure--advanced"><summary><span><strong>Advanced: import a prepared lesson file</strong><small>For authorized .aqpack files</small></span></summary><form id="packageImportForm" class="package-import-card package-import-card--nested"><p id="packageImportHelp">Select a prepared AquaDissect lesson file. The system checks the file before creating a draft.</p><label class="portal-field" for="packageImportFile">AquaDissect lesson file<input id="packageImportFile" type="file" name="package" accept=".aqpack,.zip" aria-describedby="packageImportHelp" required></label><button class="portal-primary" type="submit">Check and import draft</button><p class="portal-message" data-import-message hidden></p></form></details>' : '';
  const storageSummary = role === 'admin'
    ? `<div class="manager-storage-summary"><strong>${formatBytes(packages.reduce((total, item) => total + Number(item.sizeBytes || 0), 0))}</strong><span>across ${packages.length} protected lesson version${packages.length === 1 ? '' : 's'}</span><small>Archive or restore a version while keeping its history.</small></div>`
    : '';
  const rabbitfish = packages.find((item) => item.slug === 'rabbitfish');
  const rabbitfishState = rabbitfish?.status === 'published' ? 'Published' : rabbitfish?.status === 'review' ? (rabbitfish.approvalCurrent ? 'Faculty approved; ready for administrator publication' : 'Awaiting independent faculty decision') : 'Draft ready to send for faculty review';
  const milestone = `<section class="species-milestone" aria-labelledby="speciesMilestoneTitle"><div><span class="portal-kicker">Current study focus</span><h2 id="speciesMilestoneTitle">Rabbitfish guided preparation</h2><p>Confirm the intended danggit species, anatomy and safety directions through independent faculty review. Students receive only approved, administrator-published versions.</p></div><ol><li class="${rabbitfish?.status === 'published' ? 'is-complete' : 'is-current'}"><strong>Rabbitfish</strong><span>${escapeHtml(rabbitfish ? rabbitfishState : 'No rabbitfish package available')}</span></li></ol></section>`;
  return `${milestone}${storageSummary}${importForm}<div class="species-library-grid">${speciesLibrary(packages, { manager: true, isAdmin: role === 'admin', currentUser })}</div>`;
}
