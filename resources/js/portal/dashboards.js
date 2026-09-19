import { escapeHtml, titleCase } from './format.js';

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

function packageCard(item, { manager = false, offline = false, isAdmin = false } = {}) {
  const status = item.status === 'published' ? 'Published' : item.status === 'review' ? 'In review' : titleCase(item.status);
  const score = item.progress ? `${item.progress.score} / ${item.progress.total}` : 'Not attempted';
  const canOpen = (item.status === 'published' || manager) && !item.locked;
  const mainAction = canOpen
    ? `<button class="portal-primary" type="button" data-open-package="${item.id}">${item.progress ? 'Review lesson' : item.status === 'published' ? 'Open lesson' : 'Preview draft'}</button>`
    : '';
  const managerActions = manager ? `
    <div class="package-actions">
      ${item.status === 'draft' ? `<button class="portal-secondary" data-submit-package="${item.id}" type="button">Submit for review</button>` : ''}
      ${isAdmin && ['draft', 'review'].includes(item.status) ? `<button class="portal-secondary" data-publish-package="${item.id}" type="button">Publish</button>` : ''}
      ${isAdmin && item.status === 'published' && !item.isCurrent ? `<button class="portal-secondary" data-activate-package="${item.id}" type="button">Set active version</button>` : ''}
      ${isAdmin && item.status === 'published' ? `<button class="portal-secondary" data-archive-package="${item.id}" type="button">Archive</button>` : ''}
      ${isAdmin && item.status === 'archived' ? `<button class="portal-secondary" data-restore-package="${item.id}" type="button">Restore</button>` : ''}
      <a class="portal-secondary button-link" href="${item.downloadUrl}">Export .aqpack</a>
    </div>` : '';
  const offlineAction = offline ? `<button class="portal-secondary" type="button" data-offline-toggle="${item.id}" ${item.locked ? 'disabled' : ''}>${item.locked ? 'Prerequisite required' : ''}</button>` : '';

  return `
    <article class="species-card" data-package-card="${item.id}">
      <div class="species-card-image">${item.naturalImageUrl ? `<img src="${item.naturalImageUrl}" alt="${escapeHtml(item.commonName)} natural specimen illustration">` : '<span aria-hidden="true">A</span>'}</div>
      <div class="species-card-body">
        <div class="species-card-topline"><span class="status-badge ${item.status === 'published' ? 'is-online' : ''}">${status}</span><small>v${escapeHtml(item.version)}</small></div>
        <h2>${escapeHtml(item.commonName)}</h2>
        <p class="scientific-name"><em>${escapeHtml(item.scientificName || '')}</em></p>
        <p>${escapeHtml(item.description || '')}</p>
        <div class="lesson-meta"><span>${escapeHtml(item.duration || 'Guided activity')}</span>${item.hasVideo ? '<span class="is-featured">Swimming video</span>' : ''}<span>2.5D layers</span>${item.has3d ? '<span>Optional 3D</span>' : ''}</div>
        ${item.draftNotice ? `<div class="draft-notice">${escapeHtml(item.draftNotice)}</div>` : ''}
        ${item.locked ? `<div class="prerequisite-notice"><i data-lucide="lock-keyhole" aria-hidden="true"></i><span>${escapeHtml(item.lockReason || 'Complete Safety & Tools before opening this lesson.')}</span><button type="button" data-portal-page="safety">Open prerequisite</button></div>` : ''}
        <div class="package-score"><span>Progress</span><strong>${score}</strong></div>
        <div class="package-actions">${mainAction}${offlineAction}</div>
        ${managerActions}
      </div>
    </article>`;
}

function motionFeature(item) {
  if (item?.locked) {
    return `<section class="motion-feature" aria-labelledby="motionFeatureTitle"><div class="motion-feature-copy"><span class="motion-feature-kicker"><span aria-hidden="true"></span>Required preparation</span><h2 id="motionFeatureTitle">Complete Safety &amp; Tools first</h2><p>${escapeHtml(item.lockReason || 'Finish the assigned prerequisite before opening anatomy lessons.')}</p><div class="motion-feature-points"><span>8 required steps</span><span>15 tools and safety items</span><span>Unlimited formative retries</span></div><button class="portal-primary motion-feature-action" type="button" data-portal-page="safety">Open Safety &amp; Tools</button></div><div class="motion-feature-stage" aria-hidden="true"><span class="motion-orbit motion-orbit-one"></span><span class="motion-orbit motion-orbit-two"></span><i class="motion-feature-shield" data-lucide="shield-check"></i><span class="motion-feature-label">Prerequisite</span></div></section>`;
  }
  if (!item?.hasVideo && !item?.has3d) return '';

  if (!item.hasVideo) {
    return `
      <section class="motion-feature" aria-labelledby="motionFeatureTitle">
        <div class="motion-feature-copy">
          <span class="motion-feature-kicker"><span aria-hidden="true"></span>Interactive anatomy</span>
          <h2 id="motionFeatureTitle">${escapeHtml(item.commonName)} 3D Anatomy Lab</h2>
          <p>Control the viewing angle, focus on anatomy, and move into the exploded internal view.</p>
          <div class="motion-feature-points"><span>Manual camera</span><span>Guided anatomy focus</span></div>
          <button class="portal-primary motion-feature-action" type="button" data-open-package="${item.id}" data-viewer-mode="3d">Explore 3D anatomy</button>
        </div>
        <div class="motion-feature-stage" aria-hidden="true">
          <span class="motion-orbit motion-orbit-one"></span><span class="motion-orbit motion-orbit-two"></span>
          ${item.naturalImageUrl ? `<img class="motion-feature-fish" src="${item.naturalImageUrl}" alt="">` : '<span class="motion-feature-placeholder">A</span>'}
          <span class="motion-feature-label">No auto-rotation</span>
        </div>
      </section>`;
  }

  return `
    <section class="motion-feature" aria-labelledby="motionFeatureTitle">
      <div class="motion-feature-copy">
        <span class="motion-feature-kicker"><span aria-hidden="true"></span>Generated specimen video</span>
        <h2 id="motionFeatureTitle">${escapeHtml(item.commonName)} Swimming Study</h2>
        <p>Observe a realistic full-body Tilapia swimming freely in a fixed side view before beginning the interactive anatomy lesson.</p>
        <div class="motion-feature-points" aria-label="Viewer features">
          <span>Natural fin movement</span><span>Fixed camera</span><span>No rotation</span>
        </div>
        <div class="motion-feature-actions">
          <button class="portal-primary motion-feature-action" type="button" data-open-package="${item.id}" data-viewer-mode="video">Watch swimming video</button>
          ${item.has3d ? `<button class="portal-secondary motion-feature-secondary" type="button" data-open-package="${item.id}" data-viewer-mode="3d">Explore 3D anatomy</button>` : ''}
        </div>
      </div>
      <div class="motion-feature-stage">
        <span class="motion-orbit motion-orbit-one"></span><span class="motion-orbit motion-orbit-two"></span>
        <video class="motion-feature-video" src="${item.videoUrl}" poster="${item.naturalImageUrl || ''}" muted loop playsinline autoplay disablepictureinpicture controlslist="nodownload noplaybackrate noremoteplayback" preload="metadata" aria-hidden="true" tabindex="-1"></video>
        <span class="motion-feature-label">10-second observation</span>
      </div>
    </section>`;
}

export function speciesLibrary(packages, options = {}) {
  return packages.length
    ? packages.map((item) => packageCard(item, options)).join('')
    : '<div class="empty-state"><strong>No species are available</strong><span>Students see only published packages assigned by their instructor.</span></div>';
}

export function studentDashboard(packages) {
  const flagship = packages[0];
  if (!flagship) return '<div class="empty-state"><strong>No assigned species yet</strong><span>Your instructor can assign a published species package to your class.</span></div>';
  return `${motionFeature(flagship)}<div class="dashboard-layout"><div class="species-library-grid">${packageCard(flagship)}</div><aside class="status-card"><span class="portal-kicker">My learning</span><h2>Assignment status</h2><dl><div><dt>Assigned species</dt><dd>${packages.length}</dd></div><div><dt>Package version</dt><dd>${escapeHtml(flagship.version)}</dd></div><div><dt>Update status</dt><dd>${escapeHtml(flagship.updateStatus || 'Current version')}</dd></div><div><dt>Offline</dt><dd>Available</dd></div></dl><button class="portal-secondary" data-portal-page="downloads" type="button">Manage downloads</button></aside></div>`;
}

export function instructorDashboard(packages, classes) {
  const drafts = packages.filter((item) => item.status !== 'published').length;
  return `
    <div class="metric-grid">
      <article class="metric-card"><span class="metric-label">Assigned sections</span><strong>${classes.length}</strong><small>MB 304L offerings</small></article>
      <article class="metric-card"><span class="metric-label">Published library</span><strong>${packages.filter((item) => item.status === 'published').length}</strong><small>Assignable packages</small></article>
      <article class="metric-card"><span class="metric-label">My drafts</span><strong>${drafts}</strong><small>Draft or review packages</small></article>
      <article class="metric-card"><span class="metric-label">Clone delay</span><strong>12 h</strong><small>SQLite metadata replica</small></article>
    </div>
    <div class="module-grid">
      <article><span>01</span><h2>My Sections</h2><p>View only sections assigned by the administrator, including their rosters and progress.</p><button class="portal-secondary" data-portal-page="classes" type="button">Open sections</button></article>
      <article><span>02</span><h2>Assignments</h2><p>Assign a published version and explicitly upgrade it when learners have started.</p><button class="portal-secondary" data-portal-page="assignments" type="button">Manage assignments</button></article>
      <article><span>03</span><h2>Content drafts</h2><p>Import validated data-only packages and submit them for approval.</p><button class="portal-secondary" data-portal-page="manager" type="button">Open manager</button></article>
      <article><span>04</span><h2>Student results</h2><p>Review server-scored formative attempts by pinned package version.</p></article>
    </div>`;
}

export function adminDashboard(pendingUsers, packages, classes) {
  const rows = pendingUsers.map((account) => `<tr><td>${escapeHtml(account.firstName)} ${escapeHtml(account.lastName)}</td><td>${escapeHtml(account.email)}</td><td>${titleCase(account.role)}</td><td>Pending</td><td><button class="portal-secondary" type="button" data-approve-account="${escapeHtml(account.id)}">Approve</button></td></tr>`).join('');
  const storageUsage = packages.reduce((total, item) => total + Number(item.sizeBytes || 0), 0);
  return `
    <div class="metric-grid" aria-label="System summary">
      <article class="metric-card"><span class="metric-label">Pending approvals</span><strong>${pendingUsers.length}</strong><small>Instructor requests</small></article>
      <article class="metric-card"><span class="metric-label">Published packages</span><strong>${packages.filter((item) => item.status === 'published').length}</strong><small>Student-ready versions</small></article>
      <article class="metric-card"><span class="metric-label">Draft or review</span><strong>${packages.filter((item) => ['draft', 'review'].includes(item.status)).length}</strong><small>Awaiting lifecycle action</small></article>
      <article class="metric-card"><span class="metric-label">Sections</span><strong>${classes.length}</strong><small>One reusable MB 304L course</small></article>
      <article class="metric-card"><span class="metric-label">Package storage</span><strong>${formatBytes(storageUsage)}</strong><small>Private versioned content</small></article>
    </div>
    <div class="admin-dashboard-grid">
      <section class="admin-table-card"><div class="card-heading"><div><span class="portal-kicker">Account management</span><h2>Instructor requests</h2></div><button class="portal-secondary" data-portal-page="roster" type="button">Manage roster</button></div>
      ${rows ? `<div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows}</tbody></table></div>` : '<div class="empty-state"><strong>Approval queue is clear</strong><span>New instructor requests will appear here.</span></div>'}</section>
      <aside class="admin-operations-card"><span class="portal-kicker">Operational overview</span><h2>System status</h2><ul class="operation-list"><li><span><strong>Primary database</strong><small>Laragon MySQL</small></span><span class="status-badge is-online">Online</span></li><li><span><strong>Secondary clone</strong><small>Metadata after 12 hours</small></span><span class="status-badge">Scheduled</span></li><li><span><strong>Package storage</strong><small>${formatBytes(storageUsage)} of versioned content</small></span><span class="status-badge is-online">Ready</span></li></ul></aside>
    </div>`;
}

export function classCards(classes, role = 'student') {
  return classes.length ? `<div class="class-grid">${classes.map((item) => {
    const module = item.moduleAssignments?.[0];
    const moduleSummary = module ? `<div class="class-module-summary"><i data-lucide="shield-check" aria-hidden="true"></i><span><strong>${escapeHtml(module.title)}</strong><small>v${escapeHtml(module.version)} · ${module.required ? 'Required' : 'Optional'}</small></span></div>` : '<div class="class-module-summary"><i data-lucide="circle-dashed" aria-hidden="true"></i><span><strong>No safety module assigned</strong></span></div>';
    const studentActions = role === 'student' && item.status === 'active' ? item.assignments.map((assignment) => assignment.locked
      ? `<button class="portal-secondary" type="button" data-portal-page="safety"><i data-lucide="lock-keyhole" aria-hidden="true"></i> Complete Safety &amp; Tools to open ${escapeHtml(assignment.commonName || 'lesson')}</button>`
      : `<button class="portal-secondary" type="button" data-open-package="${assignment.packageId}" data-class-id="${item.id}">Open ${escapeHtml(assignment.commonName || 'lesson')} for this section</button>`).join('') : '';
    const overrides = role !== 'student' && module ? `<div class="override-panel"><form data-section-override="${item.id}" data-module-id="${module.moduleId}"><label class="portal-field">Section exception reason<input name="reason" minlength="10" required placeholder="Document why this section may proceed"></label><button class="portal-secondary" type="submit">Grant section exception</button></form>${(item.moduleOverrides || []).length ? `<ul>${item.moduleOverrides.map((override) => `<li><span>${override.studentId ? 'Learner exception' : 'Section exception'}: ${escapeHtml(override.reason)}</span><button class="portal-link" type="button" data-revoke-module-override="${override.id}">Revoke</button></li>`).join('')}</ul>` : '<small>No active prerequisite exceptions.</small>'}</div>` : '';
    return `<article class="class-card"><span class="portal-kicker">${escapeHtml(item.courseCode || 'MB-304L')} · ${escapeHtml(item.code)}</span><h2>${escapeHtml(item.name)}</h2><p>${escapeHtml(item.academicYear || 'Legacy')} · ${escapeHtml(item.semester || 'Unassigned')} semester · ${escapeHtml(item.status || 'active')}</p><p>${item.studentCount} enrolled learner${item.studentCount === 1 ? '' : 's'}</p>${moduleSummary}<ul>${item.assignments.map((assignment) => `<li><strong>${escapeHtml(assignment.commonName || 'Species')}</strong><span>v${escapeHtml(assignment.version || '')}${assignment.locked ? ' · Locked' : ''}</span></li>`).join('') || '<li><span>No species assigned</span></li>'}</ul>${studentActions}${role !== 'student' ? `<details class="roster-details"><summary>Roster, safety status, and formative progress</summary>${studentTable(item.students || [], item)}</details>${overrides}` : ''}</article>`;
  }).join('')}</div>` : '<div class="empty-state"><strong>No sections are linked to this account</strong><span>The administrator assigns instructors and enrolls students in MB 304L sections.</span></div>';
}

function studentTable(students, learningClass = null) {
  const module = learningClass?.moduleAssignments?.[0];
  return students.length ? `<div class="admin-table-wrap"><table><thead><tr><th>Student</th><th>Institutional ID</th><th>Account</th><th>Safety prerequisite</th><th>Attempts</th><th>Latest result</th></tr></thead><tbody>${students.map((item) => {
    const status = item.moduleStatus;
    const safety = status ? (status.satisfied ? (status.overridden ? 'Exception active' : 'Complete') : 'Required') : 'Not assigned';
    return `<tr><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.identityNumber || '—')}</td><td>${escapeHtml(item.accountStatus)}</td><td><span class="status-badge ${status?.satisfied ? 'is-online' : ''}">${escapeHtml(safety)}</span>${module && !status?.satisfied ? `<form class="learner-override-form" data-learner-override="${learningClass.id}" data-module-id="${module.moduleId}" data-student-id="${item.id}"><input name="reason" aria-label="Reason for ${escapeHtml(item.name)} exception" minlength="10" required placeholder="Document reason"><button class="portal-link" type="submit">Grant exception</button></form>` : ''}</td><td>${item.attemptCount}</td><td>${item.latestResult ? `${item.latestResult.score} / ${item.latestResult.total}` : '—'}</td></tr>`;
  }).join('')}</tbody></table></div>` : '<p>No students are enrolled in this section.</p>';
}

export function adminRosterView(data, preview = [], activationCodes = []) {
  if (!data) return '<div class="empty-state"><strong>Loading roster</strong><span>Fetching MB 304L sections and accounts.</span></div>';
  const active = data.classes.filter((item) => item.status === 'active');
  const sectionOptions = active.map((item) => `<option value="${item.id}">${escapeHtml(item.name)} · ${escapeHtml(item.academicYear || 'Legacy')} · ${escapeHtml(item.semester || 'Unassigned')}</option>`).join('');
  const instructorOptions = data.instructors.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join('');
  const issued = activationCodes.length ? `<div class="registration-note" role="status"><strong>One-time activation codes — copy now and deliver privately.</strong><p>These codes are shown only in this response and expire after three days.</p>${activationCodes.map((item) => `<p><strong>${escapeHtml(item.email)}</strong><br><code>${escapeHtml(item.code)}</code></p>`).join('')}</div>` : '';
  const report = data.report || { sections: [], attempts: 0, scorePercent: null, legacyUnattributedAttempts: 0 };
  const reportView = `<section class="admin-table-card"><div class="card-heading"><div><span class="portal-kicker">Formative activity only</span><h2>Section and course overview</h2></div></div><p>${report.attempts} section-attributed attempts · overall score ${report.scorePercent === null ? '—' : `${report.scorePercent}%`}. ${report.legacyUnattributedAttempts} legacy attempt(s) remain unattributed.</p><div class="admin-table-wrap"><table><thead><tr><th>Section</th><th>Year / semester</th><th>Students</th><th>Attempts</th><th>Score</th></tr></thead><tbody>${report.sections.map((item) => `<tr><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.academicYear || 'Legacy')} / ${escapeHtml(item.semester || '—')}</td><td>${item.students}</td><td>${item.attempts}</td><td>${item.scorePercent === null ? '—' : `${item.scorePercent}%`}</td></tr>`).join('')}</tbody></table></div></section>`;
  const previewTable = preview.length ? `<div class="admin-table-wrap"><table><thead><tr><th>Row</th><th>Student</th><th>Email</th><th>Status</th><th>Review</th></tr></thead><tbody>${preview.map((item) => `<tr><td>${item.row}</td><td>${escapeHtml(`${item.firstName} ${item.lastName}`)}</td><td>${escapeHtml(item.email)}</td><td>${escapeHtml(item.status)}</td><td>${escapeHtml(item.message)}</td></tr>`).join('')}</tbody></table></div>${preview.every((item) => !['invalid', 'conflict'].includes(item.status)) ? '<button class="portal-primary" type="button" data-import-reviewed-roster>Import reviewed roster</button>' : '<p>Resolve conflicts in the CSV before importing. No rows have been changed.</p>'}` : '';
  const offeringCards = data.classes.map((item) => `<article class="class-card"><span class="portal-kicker">${escapeHtml(item.academicYear || 'Legacy')} · ${escapeHtml(item.semester || 'Unassigned')} semester</span><h3>${escapeHtml(item.name)}</h3><p>${escapeHtml(item.code)} · ${item.studentCount} students · ${escapeHtml(item.status)}</p><p>Instructor: ${item.instructors.length ? item.instructors.map((person) => escapeHtml(person.name)).join(', ') : 'Not assigned'}</p>${item.status === 'active' ? `<form data-instructor-form="${item.id}" class="roster-inline-form"><label>Assign instructor<select name="instructorId" required>${instructorOptions || '<option value="">No approved instructors</option>'}</select></label><button class="portal-secondary" type="submit" ${instructorOptions ? '' : 'disabled'}>Assign</button></form><button class="portal-link" type="button" data-offering-status="archived" data-offering-id="${item.id}">Archive section</button>` : `<button class="portal-secondary" type="button" data-offering-status="active" data-offering-id="${item.id}">Restore section</button>`}<details class="roster-details"><summary>Students and results</summary>${studentTable(item.students || [], item)}${(item.students || []).filter((student) => student.accountStatus === 'pending').map((student) => `<button class="portal-secondary" type="button" data-issue-activation="${student.id}">Issue new code for ${escapeHtml(student.name)}</button>`).join('')}</details></article>`).join('');

  return `${issued}${reportView}<div class="roster-grid">
    <form id="offeringCreateForm" class="package-import-card"><span class="portal-kicker">One course · reusable offerings</span><h2>Add a section</h2><p>${escapeHtml(data.course.name)}</p><label class="portal-field">Academic year<input name="academicYear" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}" required></label><label class="portal-field">Semester<select name="semester"><option value="1">First</option><option value="2">Second</option><option value="summer">Summer</option></select></label><label class="portal-field">Section<input name="sectionCode" placeholder="A" required></label><button class="portal-primary" type="submit">Add section</button></form>
    <form id="studentCreateForm" class="package-import-card"><span class="portal-kicker">Administrator provisioning</span><h2>Add a student</h2><label class="portal-field">Section<select name="classId" required>${sectionOptions || '<option value="">Create a section first</option>'}</select></label><label class="portal-field">First name<input name="firstName" required></label><label class="portal-field">Last name<input name="lastName" required></label><label class="portal-field">Institutional email<input name="email" type="email" required></label><label class="portal-field">Institutional ID<input name="identityNumber" required></label><button class="portal-primary" type="submit" ${sectionOptions ? '' : 'disabled'}>Create or enroll</button></form>
    <form id="rosterCsvForm" class="package-import-card"><span class="portal-kicker">Reviewed bulk import</span><h2>Import a roster</h2><p>CSV columns: first_name,last_name,email,identity_number. Preview checks every row; conflicting files import nothing.</p><label class="portal-field">Section<select name="classId" required>${sectionOptions || '<option value="">Create a section first</option>'}</select></label><label class="portal-field">CSV file<input name="roster" type="file" accept=".csv,text/csv" required></label><button class="portal-secondary" type="submit" ${sectionOptions ? '' : 'disabled'}>Preview roster</button></form>
  </div><div class="roster-preview">${previewTable}</div><div class="portal-page-title"><span class="portal-kicker">MB 304L</span><h2>Existing sections</h2><p>Reuse a section within its semester; archive it afterward without deleting results.</p></div><div class="class-grid">${offeringCards || '<div class="empty-state">No sections yet.</div>'}</div>`;
}

export function managerView(packages, role) {
  const importForm = role === 'instructor' ? '<form id="packageImportForm" class="package-import-card"><span class="portal-kicker">Data-only import</span><h2>Import an .aqpack draft</h2><p>Archives are quarantined, fully validated, and limited to 100 MB.</p><input type="file" name="package" accept=".aqpack,.zip" required><button class="portal-primary" type="submit">Validate and import</button><p class="portal-message" data-import-message hidden></p></form>' : '';
  const storageSummary = role === 'admin'
    ? `<div class="manager-storage-summary"><strong>${formatBytes(packages.reduce((total, item) => total + Number(item.sizeBytes || 0), 0))}</strong><span>across ${packages.length} immutable package version${packages.length === 1 ? '' : 's'}</span><small>Archive or restore a version without deleting its history.</small></div>`
    : '';
  return `${storageSummary}${importForm}<div class="species-library-grid">${speciesLibrary(packages, { manager: true, isAdmin: role === 'admin' })}</div>`;
}
