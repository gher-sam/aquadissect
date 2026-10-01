<section class="portal-page" data-portal-panel="access">
  <div class="portal-page-title"><span class="portal-kicker">Quick reference</span><h1>Who can do what?</h1><p>Each role has a clear responsibility. Staff section assignments and server permissions control access.</p></div>
  <div class="workspace-panel roster-table-scroll" tabindex="0" role="region" aria-label="Account roles comparison">
    <table class="role-comparison"><caption>Account responsibilities</caption><thead><tr><th scope="col">Responsibility</th><th scope="col">Student</th><th scope="col">Instructor</th><th scope="col">Program Head</th><th scope="col">Administrator</th></tr></thead><tbody>
      <tr><th scope="row">Account access</th><td>Administrator-provided activation</td><td>Request approval</td><td>Administrator assigns an active staff account</td><td>Internally provisioned</td></tr>
      <tr><th scope="row">Lessons and safety</th><td>Assigned published content</td><td>Review and assign content</td><td>Preview published content and versions in review</td><td>Manage publication</td></tr>
      <tr><th scope="row">People and sections</th><td>View enrolled sections</td><td>View assigned sections</td><td>View all MB 304L sections</td><td>Provision and manage</td></tr>
      <tr><th scope="row">Class events</th><td>View enrolled sections</td><td>Manage assigned sections</td><td>View all sections</td><td>Manage all sections</td></tr>
      <tr><th scope="row">Learning reports</th><td>Own progress only</td><td>Assigned sections</td><td>All MB 304L sections</td><td>Institutional records</td></tr>
      <tr><th scope="row">Content decisions</th><td>No management access</td><td>Submit and independently review</td><td>Independently review; no publication</td><td>Publish after required approval</td></tr>
      <tr><th scope="row">Source Structure</th><td>No access</td><td>No access</td><td>No access</td><td>Authorized source access</td></tr>
    </tbody></table>
  </div>
  @if(auth()->user()?->isAdministrator())
    <section class="workspace-panel" id="staffRoleTools" aria-label="Staff role management"><p>Loading staff accounts…</p></section>
  @endif
  <p>Research consent is separate from account activation. Tutorial progress is separate from learning progress.</p>
  @if(\App\Support\GoogleAccess::ready())
  <p>Google verifies your email only. Students activate an administrator-provided account using the matching Google email or a one-time code. New instructors connect Google and submit an approval request. Existing active accounts can continue using their password. Google does not grant a role or enroll you in a class.</p>
  @endif
</section>
