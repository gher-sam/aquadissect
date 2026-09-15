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
  const canOpen = item.status === 'published' || manager;
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
  const offlineAction = offline ? `<button class="portal-secondary" type="button" data-offline-toggle="${item.id}"></button>` : '';

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
        <div class="package-score"><span>Progress</span><strong>${score}</strong></div>
        <div class="package-actions">${mainAction}${offlineAction}</div>
        ${managerActions}
      </div>
    </article>`;
}

function motionFeature(item) {
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
      <article class="metric-card"><span class="metric-label">Owned classes</span><strong>${classes.length}</strong><small>Instructor-scoped groups</small></article>
      <article class="metric-card"><span class="metric-label">Published library</span><strong>${packages.filter((item) => item.status === 'published').length}</strong><small>Assignable packages</small></article>
      <article class="metric-card"><span class="metric-label">My drafts</span><strong>${drafts}</strong><small>Draft or review packages</small></article>
      <article class="metric-card"><span class="metric-label">Clone delay</span><strong>12 h</strong><small>SQLite metadata replica</small></article>
    </div>
    <div class="module-grid">
      <article><span>01</span><h2>My Classes</h2><p>Manage only classes that list you as an instructor.</p><button class="portal-secondary" data-portal-page="classes" type="button">Open classes</button></article>
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
      <article class="metric-card"><span class="metric-label">Classes</span><strong>${classes.length}</strong><small>Normalized learning groups</small></article>
      <article class="metric-card"><span class="metric-label">Package storage</span><strong>${formatBytes(storageUsage)}</strong><small>Private versioned content</small></article>
    </div>
    <div class="admin-dashboard-grid">
      <section class="admin-table-card"><div class="card-heading"><div><span class="portal-kicker">Account management</span><h2>Registration requests</h2></div><button class="portal-secondary" data-portal-page="manager" type="button">Species Manager</button></div>
      ${rows ? `<div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows}</tbody></table></div>` : '<div class="empty-state"><strong>Approval queue is clear</strong><span>New instructor requests will appear here.</span></div>'}</section>
      <aside class="admin-operations-card"><span class="portal-kicker">Operational overview</span><h2>System status</h2><ul class="operation-list"><li><span><strong>Primary database</strong><small>Laragon MySQL</small></span><span class="status-badge is-online">Online</span></li><li><span><strong>Secondary clone</strong><small>Metadata after 12 hours</small></span><span class="status-badge">Scheduled</span></li><li><span><strong>Package storage</strong><small>${formatBytes(storageUsage)} of versioned content</small></span><span class="status-badge is-online">Ready</span></li></ul></aside>
    </div>`;
}

export function classCards(classes) {
  return classes.length ? `<div class="class-grid">${classes.map((item) => `<article class="class-card"><span class="portal-kicker">${escapeHtml(item.code)}</span><h2>${escapeHtml(item.name)}</h2><p>${item.studentCount} enrolled learner${item.studentCount === 1 ? '' : 's'}</p><ul>${item.assignments.map((assignment) => `<li><strong>${escapeHtml(assignment.commonName || 'Species')}</strong><span>v${escapeHtml(assignment.version || '')}</span></li>`).join('') || '<li><span>No species assigned</span></li>'}</ul></article>`).join('')}</div>` : '<div class="empty-state"><strong>No classes are linked to this account</strong><span>Instructor ownership and student enrollment determine visibility.</span></div>';
}

export function managerView(packages, role) {
  const importForm = role === 'instructor' ? '<form id="packageImportForm" class="package-import-card"><span class="portal-kicker">Data-only import</span><h2>Import an .aqpack draft</h2><p>Archives are quarantined, fully validated, and limited to 100 MB.</p><input type="file" name="package" accept=".aqpack,.zip" required><button class="portal-primary" type="submit">Validate and import</button><p class="portal-message" data-import-message hidden></p></form>' : '';
  const storageSummary = role === 'admin'
    ? `<div class="manager-storage-summary"><strong>${formatBytes(packages.reduce((total, item) => total + Number(item.sizeBytes || 0), 0))}</strong><span>across ${packages.length} immutable package version${packages.length === 1 ? '' : 's'}</span><small>Archive or restore a version without deleting its history.</small></div>`
    : '';
  return `${storageSummary}${importForm}<div class="species-library-grid">${speciesLibrary(packages, { manager: true, isAdmin: role === 'admin' })}</div>`;
}
