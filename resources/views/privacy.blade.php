<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="How AquaDissect uses account, Google sign-in and learning information.">
  <title>Privacy policy | AquaDissect</title>
  @vite('resources/css/privacy.css')
</head>
<body>
<main id="main">
  <nav aria-label="Page navigation"><strong>AquaDissect</strong><a href="{{ route('login') }}">Return to sign in</a></nav>
  <h1>Privacy policy</h1>
  <small>Updated October 2, 2026</small>
  <p>AquaDissect supports guided fish-anatomy learning and preparation for faculty-supervised laboratory activities. This page explains how the application handles account and learning information.</p>

  <h2>Information used by the application</h2>
  <ul>
    <li>Account details: name, registered email, student or employee identifier, role, account status and section enrollment. Existing password accounts store a password hash.</li>
    <li>Learning records: assigned activities, safety-module progress, anatomy attempts, formative scores, saved results and tutorial progress.</li>
    <li>Staff records: content submissions, media provenance, review decisions, publication history, documented exceptions, schedules and account-management history.</li>
    <li>Operational information: sign-in sessions, notifications, request logs and offline synchronization identifiers. The hosting server may record request information such as IP addresses and requested paths.</li>
  </ul>
  <p>These records support account access, class activities, saved progress, reports, content review and operation of the service. Research participation and consent are separate from account activation and tutorial or lesson completion.</p>

  <h2>When you choose Google sign-in</h2>
  <p>AquaDissect requests only your basic profile and verified email through Google’s <code>openid</code>, <code>email</code> and <code>profile</code> permissions. Your verified email is used to match an existing account, activate an administrator-provisioned student account, or prepare an instructor access request.</p>
  <p>The application stores the Google account identifier, verified email, verification time and link to your AquaDissect account. For a new instructor request, your profile name and email are held in a short-lived verification session; the details you confirm are saved when you submit the request. Google sign-in does not give the application access to Gmail, Drive, Calendar or your Google password. Google access tokens, refresh tokens and profile pictures are not retained by the application.</p>
  <p>Google processes sign-in under its own <a href="https://policies.google.com/privacy">privacy policy</a>. Google sign-in does not determine your academic role, enrollment, research consent or instructor approval.</p>

  <h2>Who can see records</h2>
  <p>Students can view their own account and learning records. Authorized instructors can view records for their assigned sections. Program Heads can view MB 304L section progress and reports and perform permitted content reviews. Administrators manage accounts, sections and publication. Authorized staff can export reports within their permitted scope.</p>
  <p>Information is stored on the application’s hosting infrastructure. The application does not send learning results to Google as part of sign-in. It has no advertising or sale-of-data function. Staff media-prompt tools produce copyable prompts; they do not automatically send your information to an AI service.</p>

  <h2>Storage, backups and offline access</h2>
  <p>Account and academic records are retained to preserve class history. A delayed SQLite copy receives durable records after 12 hours. This version has no automatic academic-record deletion schedule; ask the responsible administrator about the institution’s applicable retention requirements.</p>
  <p>Offline downloads place published learning media on your device. Queued progress is associated with your account and synchronized when a connection returns. Use a trusted device and keep your account details private.</p>

  <h2>Questions, corrections and removal requests</h2>
  <p>Contact your class or system administrator to correct account details or request access to, disconnection of, or removal of your information. Requests involving academic history and backups require administrator review; the application does not offer automatic account deletion or Google disconnection. Revoking AquaDissect’s access in your Google account stops that Google authorization but does not automatically erase existing AquaDissect records.</p>
  <p>Use the support email shown on Google’s AquaDissect consent screen for Google sign-in questions. Do not send passwords, activation codes or client secrets in a support request.</p>
</main>
</body>
</html>
