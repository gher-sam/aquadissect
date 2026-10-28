# AquaDissect Project 2 Study Alignment

Last confirmed: September 14, 2026

## Scope boundary

This decision record applies only to AquaDissect Project 2 work in the original AquaDissect Project 2 checkout, or to a task in which the user explicitly identifies AquaDissect Project 2. Do not transfer its participants, species, methodology, sprint structure, deadlines, evidence, or manuscript decisions to sibling or unrelated projects.

## Purpose and authority

This file consolidates the researcher's approved working decisions for AquaDissect. It is the context baseline for Project 2 work across the manuscript, application, evaluation instruments, sprint records, and defense preparation.

The decisions below express intended scope. They do not establish that pending development, faculty validation, ethics approval, data collection, or evaluation has occurred.

## Study identity and status

- AquaDissect remains a development-stage capstone prototype.
- The current manuscript title is retained for now.
- Institution: Southern Leyte State University Bontoc Campus, Marine Biology Department or the verified official unit name.
- Course: MB 304L Marine Zoology II Vertebrates Laboratory.
- Intended implementation: first semester, AY 2026-2027.
- General objective: To develop and evaluate AquaDissect as a syllabus-aligned virtual pre-laboratory system that prepares MB 304L students for actual fish dissection.
- Primary use: preparation before actual dissection.
- Secondary uses: a supplement during lessons and an alternative learning activity when specimens die or materials are unavailable.
- AquaDissect is not a permanent replacement for faculty-supervised physical dissection.

## Central problem

The study addresses limited specimens, ethical concerns, insufficient preparatory time, difficulty identifying organs, costly materials, and limited opportunities for physical dissection.

Intended learning outcomes are improved organ identification, understanding of organ functions and systems, knowledge of proper dissection procedures, confidence and readiness, and assessment performance. These remain intended outcomes until supported by collected evidence.

## Participants and stakeholders

- Research student participants: all eligible MB 304L students in every section offered during the implementation semester, using total enumeration.
- Expected minimum: at least 20 students per section. The actual number of sections, enrollment, eligible participants, and completed responses remains to be documented.
- Other evaluators: the Marine Biology program chair or head and MB 304L faculty.
- Student and expert software-quality results must be analyzed separately.
- The exact official program-outcome wording remains pending.

## Application roles

The application uses four roles following the September 29, 2026 tutorial and oversight decision:

- Administrator: authorized account, section, publication, and system administrator.
- Program Head: designated academic oversight role with read-only access to all MB 304L sections and reports, and independent faculty content review. This role cannot publish content or manage accounts, class activities, overrides, or source downloads.
- Instructor: faculty member handling MB 304L.
- Student: enrolled MB 304L learner.

Separate Super Administrator and Staff roles are deferred unless formally required. Application roles must not be confused with research respondents or adapted Scrum responsibilities.

September 29, 2026: Administrators may explicitly change an active Instructor account to Program Head or restore Instructor access. Existing section assignments and historical records remain intact; instructor management privileges do not apply while Program Head access is active. Role changes retain actor, previous and new role, and timestamps. No accounts are converted automatically.

Role-specific Start here cards, searchable task guides, and optional control-highlighting walkthroughs were approved for all four roles. Tutorial progress is saved by account and guide version, independently of lesson completion, research participation, and learning outcomes. Tutorials explain real workflows but do not submit forms or perform academic actions automatically. Status: Implemented but awaiting validation. Engineering checks are recorded in docs/tutorials-verification.md; no research or faculty validation is implied.

## Account and section-management decision

October 2, 2026: Google sign-in was approved alongside existing password access. A verified matching Google email may connect an existing account. Students claim administrator-provisioned roster accounts; no public student registration is introduced. New instructor requests require Google verification when the feature is enabled and remain pending administrator approval. Google-created and Google-activated accounts use Google without a local password; existing active password accounts retain password access. Google does not assign roles, enrollments, academic permissions, or research consent. Administrator and Program Head access remain internally managed. Status: Implemented and enabled on production at the user's explicit request on October 2, 2026. The live Google button and account chooser for aquadissect.com were verified. A completed matching-account callback and signed-in role flow remain awaiting live verification. Engineering checks are recorded in docs/google-sign-in-verification.md; no academic or research validation is implied.

Confirmed September 17, 2026: AquaDissect uses one reusable MB 304L course with distinct section offerings for each academic year and semester. Authorized administrators provision and enroll student accounts from a verified roster, assign instructors to existing offerings, and archive completed offerings without deleting results. Students do not publicly self-register or require instructor acceptance; they activate administrator-provided accounts with a one-time code. Instructors may request account approval but do not create offerings or admit students. Account activation is separate from informed consent for research participation.

Legacy class records and lesson results must be retained. Existing results without reliable historical section evidence remain unattributed rather than being assigned to a section by inference. New attempts record their offering. The adviser-provided ERD has not yet been supplied and must be reviewed against this confirmed workflow when available; it is not evidence that implementation or validation is complete.

## Species scope and current implementation truth

October 12, 2026: The user confirmed Rabbitfish (danggit) as the single priority specimen and authorized application enhancements. This replaces the earlier three-species development priority. Existing Tilapia and other historical packages, assignments and results remain intact. Seahorse and further species are deferred. Faculty confirmation of the exact rabbitfish identity and adaptations to the multi-specimen laboratory comparison outcomes remains pending; the current draft uses Goldspotted Rabbitfish (`Siganus guttatus`). No faculty approval or research readiness is implied.

- Tilapia is the published flagship package with layered 2.5D anatomy, accessible hotspots, guided learning, quiz functionality, and an optional procedural 3D viewer.
- Goldspotted Rabbitfish (`Siganus guttatus`) version `1.1.0` is an implemented 2.5D package with a locally authored swimming reference in the staff-only faculty-review stage. No faculty approval or publication is recorded. Version `1.0.0` remains an unchanged historical package source.
- Scad has not yet been verified as implemented.
- The exact scientific identities of Tilapia and Scad require MB 304L faculty confirmation.
- Additional species are deferred until after the defense.

Implementation status recorded September 20, 2026:

- A species-package version must receive a current independent faculty approval before an administrator can publish it. The uploader cannot approve the same version.
- The review record retains the decision, notes, evidence reference, curriculum-alignment confirmation, safety confirmation, reviewer role, actor, review round, reviewed-content fingerprint, and timestamps.
- Restoring an archived species-package version returns it to faculty review and requires a new approval round before republication.
- Rabbitfish version `1.1.0` adds a six-second, silent side-view swimming reference derived locally from the existing package illustration. No AI service, generation API, or external media was used. The media and anatomy content remain awaiting independent faculty review.
- Rabbitfish is unavailable to students while it remains in review. Scad development remains pending until MB 304L faculty confirm its exact species identity.

## Required learner experience

The approved sequence is:

1. Orientation and informed consent for research participation.
2. Pretest and pre-use preparedness scale.
3. Laboratory safety and PPE orientation.
4. Animated laboratory-tool familiarization.
5. Tool-selection formative activity.
6. Specimen orientation and handling.
7. External-anatomy exploration.
8. Incision-line demonstration.
9. Safe opening of the body cavity.
10. Internal-organ exploration using 2.5D hotspots.
11. Organ-function and body-system explanations.
12. Guided dissection-procedure activity.
13. Formative anatomy assessment.
14. Posttest and post-use preparedness scale.
15. Faculty-supervised physical dissection.
16. Practical-performance assessment.
17. Student ISO/IEC 25010 evaluation.
18. Faculty and program-chair curriculum and software-quality evaluation.

Each species uses the same interaction structure but species-specific images, anatomy, descriptions, and cautions.

## Laboratory tools module

Subject to faculty and laboratory SOP confirmation, images and animations should cover the dissecting tray, scalpel and disposable blade, dissecting scissors, forceps, blunt and pointed probes, dissecting pins, ruler or measuring board, specimen container, laboratory coat or apron, nitrile gloves, safety goggles, absorbent material, biological-waste container, and sharps container.

Each tool should provide its name, purpose, proper handling, applicable step, and safety precaution. Animations should support play, pause, replay, captions, keyboard operation, and reduced-motion preferences. The activity is formative unless separately validated as a research instrument.

Enhancement decisions confirmed September 19, 2026:

- The first shared learning-module enhancement is the versioned `Safety and Laboratory Tools` module.
- Its draft uses interactive 2.5D illustrations and may include faculty-reviewed short clips.
- Student completion requires finishing every required content step and submitting the formative tool-selection activity once; its score does not gate completion and retries remain available.
- After a reviewed version is published and assigned, it is a prerequisite for species anatomy lessons. Instructors may grant a documented, reversible exception to a section or individual learner.
- Draft content and media remain staff-only until an independent faculty review is recorded and an administrator publishes the version.
- A staff-only Media Prompt Studio prepares copyable prompts for draft illustrations and videos. The studio runs without an external media service or API key. Generated media must retain an AI-generation declaration, source and licence information, and review status.
- These implementation decisions do not establish content validation, research-instrument validation, research participation, or learning effectiveness.

## Development methodology

Use the wording: `An Agile software development approach using an adapted Scrum framework.`

The approved proposed structure contains seven sprints:

1. Requirements and syllabus alignment.
2. System architecture, accounts, roles, and classes.
3. Species-package framework and offline functionality.
4. Tilapia interactive anatomy package.
5. Rabbitfish and Scad packages.
6. Laboratory-tool animations and assessment features.
7. Integration, testing, corrections, and stable release.

Seven is not a Scrum requirement. The final number, dates, contents, and completion statuses must be reconciled with actual development history. Do not fabricate timeboxes or divide work artificially.

Adapted Scrum responsibilities:

- A designated proponent manages and prioritizes the Product Backlog.
- The project manager facilitates sprint planning, monitoring, reviews, and retrospectives.
- The proponents perform the development work.
- The program chair or designated MB 304L faculty serves as client and subject-matter representative.
- The adviser and technical reviewers provide recommendations and review evidence.

Each sprint should retain its goal, dates, user stories, sprint backlog, owners, acceptance criteria, consultation and correction records, tests, evidence, review, retrospective, and unresolved items. Use stable issue IDs such as `AQD-S4-003` across the related records. Mark an item `Done` only when its acceptance criteria, integration, tests, required review, corrections, and evidence are satisfied.

## Research design and questions

Use a developmental-evaluative design with a one-group pretest-posttest component.

Recommended aligned questions:

1. What is the level of students' knowledge before and after using AquaDissect?
2. Is there a statistically significant difference between students' pretest and posttest knowledge scores?
3. Is there a statistically significant difference in perceived preparedness before and after using AquaDissect?
4. What is the level of practical fish-dissection performance after using AquaDissect?
5. What is the evaluated software quality of AquaDissect under the selected ISO/IEC 25010 criteria?
6. To what extent is AquaDissect aligned with MB 304L objectives and laboratory activities?

Do not describe practical performance as a before-and-after improvement unless it is measured twice or a valid comparison design is introduced. Under the approved design, it is a post-use performance level.

## Instruments and analysis

- Knowledge: a validated 25-30-item pretest and posttest based on one Table of Specifications; equivalent forms when feasible.
- Preparedness: a short pre-use and post-use confidence/readiness scale, analyzed separately from knowledge.
- Practical performance: a four-point analytic rubric for preparation and safety, tool handling, procedure, organ identification, specimen handling, and cleanup.
- Software quality: an adviser- or institution-approved ISO/IEC 25010 instrument, with the exact edition stated.
- Curriculum alignment: faculty review of species, content, procedures, activities, assessments, and syllabus objectives.
- Validation: at least three qualified validators, with pilot testing outside the final respondent group when feasible.
- Data linkage: anonymous participant codes; the identity key stored separately with restricted access.
- Analysis: descriptive statistics, an appropriate paired test, an effect size, a nonparametric alternative if assumptions fail, and separate student/expert ISO reporting.

ISO/IEC 25010 findings indicate evaluated software quality; they do not independently demonstrate improved learning.

## Ethics and evidence boundary

Obtain applicable institutional, departmental, faculty, and ethics permissions before data collection. Use informed consent, assent and parental consent when required, voluntary participation, protection from grade-related coercion, confidentiality, secure storage, anonymized reporting, and permission for photographs or recordings.

The requesting individual's personal name must not be stored in the application, source code or comments, databases, manifests, manuscripts, supporting forms, generated documents, media, screenshots, archives, document properties, revision records, or other Project 2 artifacts. Use role-only labels when attribution is necessary, and scrub identifying metadata before retaining or distributing an artifact.

Retain `Unknown`, `Not done`, `Not available`, or `Pending` when evidence is missing. Never invent validators, approvals, respondent counts, sprint acceptance, statistical results, signatures, consultation entries, or effectiveness claims.

## Chapter alignment

- Chapter I: problem, context, objective, research questions, significance, scope, delimitations, and definitions.
- Chapter II: physical-dissection constraints, ethics, virtual pre-laboratory learning, multimedia and interactivity, Cognitive Theory of Multimedia Learning, experiential learning, assessment and feedback, readiness, curriculum alignment, software quality, ISO/IEC 25010, synthesis, and gap.
- Chapter III: developmental-evaluative design, Agile with adapted Scrum, participants and sampling, instruments and validation, procedures, analysis, ethics, and development process.
- Chapter IV: a comprehensive sprint-by-sprint synthesis of goals, stories, implemented increments, tests, reviews, corrections, and evidence, followed separately by actual evaluation results and discussion.
- Chapter V: conclusions limited to actual findings, limitations, supervised-use recommendations, and future work.
- Appendices: detailed backlogs, sprint records, consultation logs, technical and documentation correction logs, tests, validation records, instruments, ethics materials, screenshots, and traceability evidence.

Use a feature-status matrix with: `Implemented and validated`, `Implemented but awaiting validation`, `In development`, `Planned`, `Deferred`, and `Not available`. Programming completion, content validation, and respondent evaluation are separate states.

## Defense scope and evidence

The demonstration should cover administrator, instructor, and student workflows; class assignment; laboratory tools and safety; Rabbitfish 2.5D hotspots after independent faculty approval, with historical lessons retained; guided procedure; formative activities; quizzes and results; offline operation; and progress reporting.

Prepare a stable live demonstration and an offline screenshot or recorded fallback. Do not deploy unchanged demonstration passwords.

The evidence package should include a stable release, backups, product and sprint backlogs, seven evidence-based sprint summaries, consultation and correction logs, requirements traceability matrix, test results, faculty content validation, instrument validation, ethics and consent documents, anonymized data when collected, statistical outputs, screenshots, demonstration recording, user manual, deployment guide, and contribution record.

## Defense schedule

The defense is in the third week of October 2026. The exact date remains unconfirmed. Treat October 12 as the earliest plausible date and October 11 as the internal readiness deadline.

- September 14-16: confirm the exact date, population, scientific names, program outcomes, reviewers, validators, approvals, and actual development history.
- September 17-27: complete Scad, validate and publish Rabbitfish, implement the tools and animations, fix critical issues, validate instruments, and populate real sprint evidence.
- September 28-October 2: complete content validation, pilot and revise instruments, perform integration/mobile/accessibility/offline testing, and freeze the research release.
- October 3-7: conduct approved pretest, AquaDissect use, posttest, physical-dissection assessment, and ISO/IEC 25010 evaluation.
- October 8-10: analyze data, complete Chapters IV-V, finalize appendices, slides, demonstration, and evidence folders.
- October 11: freeze the manuscript and application, perform the final mock defense, and permit only critical corrections.

If approvals, validated instruments, or a stable application are unavailable by October 2, do not fabricate or rush formal results. Present the system accurately as development-stage or obtain an approved schedule adjustment.

## Unresolved facts requiring confirmation

- Exact defense date.
- Exact current manuscript title wording.
- Official department/unit name and program-outcome wording.
- Scientific names of Tilapia and Scad.
- Number of sections, enrollment, and eligible participants.
- Proponent names and actual Scrum responsibilities.
- Actual sprint dates, reviews, and evidence.
- Validators and approval status.
- Ethics and consent status.
- Completion feasibility for Scad and animated tools.
- Final research-release version and deployment environment.


## Approved workspace additions - September 27, 2026

September 30, 2026: The researcher requested a consistent, compact Attex-inspired typography scale across application pages, beyond Tutorials. Use Figtree, solid colors without gradients, 20px workspace page titles, 16–18px section headings, 14px general interface text, and 12–13px supporting text. Preserve readable 16px lesson explanations and mobile text-entry fields, keyboard focus, and usable interaction targets. This is a presentation decision and does not change academic workflows or establish validation.

The researcher approved an Attex-inspired redesign, separate page URLs, a class calendar, in-app notifications, navigation/help search, and operational learning reports. These support the existing MB 304L workflow and do not change research participants, roles, content-review requirements, or evidence standards.

Status: **Implemented but awaiting validation**. Local engineering checks are recorded separately in `docs/workspace-redesign-verification.md`; they are not faculty content validation, instrument validation, respondent evaluation, or proof of effectiveness.

- Shared light interface, role-aware sidebar, responsive drawer, compact headings, individual Blade pages, and searchable record tables.
- Calendar combines assignment dates with single class events. Authorized staff manage their sections; students view enrolled sections. UTC storage and Philippine-time display; cancellation retains records.
- Recipient-scoped notifications for new activity/event changes, instructor approval, and review/publication events. No historical backfill, email, push, or scheduled reminders.
- Reports use recorded lesson results and safety progress. Role/section/date/activity filters and CSV preserve scope; unattributed historical records remain separate from class aggregates.
- Offline navigation uses a neutral shell and account-bound queued progress. Private staff pages are excluded from shared navigation caching.
- Additive calendar/notification migrations and delayed-clone integration preserve existing data. Application rollback should retain these tables and records.

Deferred: recurring events, external calendar synchronization, email/push, scheduled reminders, editable RBAC, and unrelated template applications. Software verification remains separate from research evaluation and manuscript completion.

September 27, 2026: Bootstrap 5.3 integration was approved and implemented as a
shared component foundation, with the Account dropdown using Bootstrap behavior.
Existing application components and anatomy interactions remain in place. Status:
**Implemented but awaiting validation**; this technical integration does not
establish faculty validation or research outcomes.

## Approved source handover addition

The administrator-only source handover was approved on September 27, 2026.
It provides a read-only file tree, text previews, individual downloads, and a
prepared release ZIP through the application. It does not grant developer access
to learner records or introduce another role. Source packages exclude credentials,
live records, uploads, manuscripts, and private operational artifacts. Downloads
are logged by account ID and revision. Engineering implementation and verification
are distinct from faculty or research validation.

## October 6, 2026: Attex UI study and adaptation

The approved UI work studies the 86 requested Attex reference pages, including
Analytics and Ecommerce, and maps patterns to existing AquaDissect features.
Retain Figtree, compact readable type, solid blue colors, no gradients, accessible
controls, reduced motion, and role-scoped navigation. Commercial theme examples
are demonstration data and do not establish a requirement for chat, email,
invoices, billing, or specialized charts. Use recorded learning data and preserve
missing values separately from zero. Engineering checks do not establish
learning effectiveness or faculty validation.

The latest task explicitly requires deployment authorization. These changes are
**Implemented but awaiting validation**, with local engineering evidence recorded
in `docs/attex-ui-verification.md`. Publication was explicitly authorized through
`/serve_update` on October 6, 2026; deployed verification is required before claiming
that the interface changes are live.


## October 12, 2026: Rabbitfish observation preparation

A session-only observation notebook supports the consultation follow-up: 16 Table 1 mm measurement fields, classification and external-feature states, independent fin/spine counts, internal observations and reflective preparation. It is an unreviewed worksheet aid, separate from lesson scoring and research instruments. It provides JSON/CSV downloads, has no account identifiers or server persistence, and clears when leaving the lesson or changing packages. Virtual images have no calibrated measurement scale. Unknown, not observed, not applicable and actual observed values remain distinct. Faculty must confirm field definitions, species applicability, identity, the requested spine geometry, and physical worksheet/LMS mapping. Existing immutable content packages and publication gates are unchanged. Status: Implemented but awaiting validation.

## October 12, 2026: Temporarily show rabbitfish only

The user requested removal of other species for now. The active catalog, current class-activity presentation and cached offline catalog show rabbitfish only. Tilapia and other species packages, assignments, downloads and historical results remain retained; historical reports keep their actual recorded species. This is a reversible presentation scope in `species-packages.visible_slugs`, not destructive cleanup or automatic publication. Guest sign-in/activation/instructor-request illustrations use a neutral rabbitfish study-focus card, without exposing unpublished draft anatomy media. Student availability still requires independent review, administrator publication, class assignment and the existing safety prerequisite.

## October 13, 2026: First student session on October 14

The user confirmed account activation and orientation as the intended first session, rather than opening unapproved anatomy content. A five-step account-scoped student orientation guide and Home checklist explain account access, class enrollment checks, the application purpose, content availability and saved guide viewing. Orientation does not require a published anatomy/safety assignment and does not constitute safety completion, a formative attempt, research consent or participant evaluation. Administrators must provision the verified roster and deliver activation details privately through their existing workflow. The exact roster and successful activation by tomorrow’s participants are not yet evidenced. Rabbitfish and safety review/publication gates are unchanged.

October 13: Staff Species lessons now shows one card per species, preferring the current published version or otherwise the newest non-archived version. A Version history link opens Species content, where all authorized retained versions remain listed. Student lists continue to expose their separately assigned versions so historical/pinned section work is preserved. This is a display correction, with no package deletion or change to review/publication state.

## October 14, 2026: Rabbitfish lesson-card video

The Rabbitfish lesson card uses an AI-generated looping preview in place of the illustration. The preview uses existing package access checks and includes playback controls, reduced-motion support and an image fallback. It does not modify immutable lesson packages, establish scientific accuracy or replace the locally authored lesson reference. Review and publication gates remain unchanged. Status: Implemented but awaiting faculty validation.

## October 15, 2026: Animated cutout presentation

The user selected an animated cutout for the Rabbitfish card. The existing AI-generated swimming preview is processed into a transparent WebM loop at 24 fps, with a small keyboard-accessible Play/Pause button instead of the native video-player toolbar. Reduced-motion autoplay suppression, illustration fallback, AI review label and existing package access checks remain in place. Original video and immutable lesson packages are preserved. Status: Implemented but awaiting faculty validation.
October 15 HD update: The Rabbitfish lesson-card cutout now uses the updated AI-generated swimming animation, exported at 1280 x 720, 24 fps and 10 seconds with transparency and no audio track. The source animation is preserved separately. The existing Play/Pause control, reduced-motion behavior, image fallback, AI review label and package authorization continue to apply. This presentation update remains awaiting faculty validation.

October 15 card presentation revision: At the user's request, the visible card caption now reads "Swimming preview · awaiting faculty review" without the generation wording, and the fish preview is larger on desktop and mobile. Earlier source/provenance records are retained; the visible faculty-review status, authorization, playback controls and reduced-motion behavior continue to apply.

October 15 angle carousel: The user supplied a multi-view specimen sheet and requested a carousel for viewing the fish from different angles. The Rabbitfish lesson card now includes ten distinct supplied views plus the retained swimming preview, with previous/next buttons, direct view selection and keyboard navigation. The duplicate ventral panel is omitted. The source sheet is retained intact; the interface selects its regions and keys the green backdrop in canvas at display time. These are discrete supplied perspectives, not continuous 3D rotation or validated anatomical references. Existing staff/package authorization, faculty-review caption, reduced-motion playback behavior and publication/assignment gates continue to apply. Status: Implemented but awaiting faculty validation.


## October 16, 2026: Guided administrator student upload

The user approved CSV, XLSX, XLS, XML, JSON and TSV uploads with private expiring drafts, column mapping, explicit row selection, email completion and multiple existing course-section destinations. Student identity persists across academic years; year level, program and home section are optional upload context, never eligibility or automatic promotion rules. Second-year and irregular students may take MB 304L. Existing account details, credentials, activation state, historical enrollments and results must be retained. The supplied masterlist and real student records must not be imported during engineering QA, embedded in source or used as fixtures. Authorized administrative imports follow the user's separate roster and destination instructions. Only explicit reviewed import creates accounts or enrollments; preview and cancellation do not. This engineering addition does not change research sampling, consent or faculty validation requirements.


October 16 roster follow-up: The user authorized Section A and Section B for AY 2026-2027, first semester, and student import without emails for now. Bulk import accepts blank emails as NULL and retains pending account status, with no activation token until email is available. Existing email sign-in, activation verification, credentials and historical records remain intact. Real roster import is an authorized administrative data action, separate from synthetic engineering QA and research participation. Private student data is never embedded in source or tests.

## October 20, 2026: Student journey engineering verification

The user requested end-to-end student workflow checks and corrections. Test
activation, orientation, safety completion, Rabbitfish activities, offline
synchronization and saved results with synthetic accounts in an isolated database.
Retain existing faculty review/publication gates and live academic records.
Corrections distinguish queued work from confirmed completion, preserve account
and section scope, and report actual saved outcomes accurately. Observation
notebooks remain session drafts with downloads; this increment does not introduce
durable anatomy browsing-state storage. Status: Implemented but awaiting faculty
and research validation. Engineering evidence and limits are recorded in
`docs/student-journey-verification.md`.

## October 25, 2026: Student access readiness corrections

The user authorized the recommendations following a live readiness inspection.
Administrators may add a verified email only to an existing pending student account
that has no email. The action records the administrator and student IDs, retains
identity, credentials, enrollment and results, and does not activate the account,
verify the address, or issue a code automatically. Existing account emails are not
replaced through this workflow. Actual email values and instructor assignments
still require verified operational input.

Safety & Tools publication now requires an explicit section selection. An empty
selection publishes without changing assignments; selected active MB 304L sections
receive the required version, while other sections remain unchanged. This replaces
automatic assignment to every active MB 304L section. Existing started progress,
results and legacy exceptions are retained. Independent faculty review, current
approval and administrator publication remain required; this work supplies no
faculty approval, research validation or learner-effectiveness evidence.

## October 25, 2026: Student-entered email and inbox verification

The user approved student ID plus a private setup code, followed by entry of the
student's own email, a six-digit inbox OTP, and password setup. This claims an
existing administrator-provisioned pending roster account without email; it is
not public registration and does not change enrollment, academic roles, research
consent, content review or lesson prerequisites. Administrator email entry remains
a support fallback. Existing Google and legacy activation-code access is retained.

Administrators prepare codes only for explicitly selected eligible students and
deliver each privately. Setup codes last seven days and are consumed on successful
activation. Inbox codes last ten minutes, allow five attempts, and have a 60-second
resend cooldown and five-send hourly limit per student. Verification and password
completion are bound to the claiming browser session; email and activation status
are saved only at successful completion. Temporary email data is encrypted and
setup/OTP hashes are excluded from the delayed academic-data clone and reports.

The existing mail service is to be configured privately. Student email setup stays
disabled until authenticated SMTP configuration and actual designated-inbox delivery
are confirmed. A provider accepting a message, local mail capture, synthetic tests
or deploying code does not establish real inbox delivery. The user subsequently
selected Google email as the sending service; the hosting-email purchase path is
not required. Gmail SMTP must use an authorized sender and its private app
credential; existing Google sign-in scopes remain unchanged. The user subsequently
requested proceeding without enabling 2-Step Verification on the sender. An
administrator-only OAuth connection with the Gmail send-only permission is the
approved alternative; it does not add Gmail permissions to student sign-in.
The private encrypted sender refresh credential is excluded from academic clones,
source downloads and public browser storage. Google may independently require
2-Step Verification for the Cloud account configuring the API. No Google security
setting is bypassed or changed automatically. Status: Implemented
but awaiting validation. Synthetic engineering checks cover activation and failure
paths; actual Gmail inbox delivery, real-student use and physical-device checks
remain pending. The feature stays disabled until the inbox-delivery gate passes.

## October 25, 2026: Defer email and use student ID with password

The user deferred email configuration and email OTP for now. Students may access
their existing roster account with student ID as the username and a private
password. A first-time pending student without email uses the administrator's
private seven-day setup code to claim the account and choose a password directly.
Email entry and inbox verification are not required in this mode, and no address
or email-verification timestamp is invented. This supersedes email verification as
an immediate student-access prerequisite, while retaining the disabled email
implementation for a future separately authorized rollout.

Student setup remains restricted to existing eligible pending roster accounts;
there is no public registration, shared default password or automatic activation
of the imported roster. Codes and browser sessions remain expiring and single-use
on completion. Email and password-only setup sessions cannot be substituted for
each other. Successful setup retains the account ID, enrollment, learning records
and academic permissions. Existing email/password and Google access remain valid.
Single-student administrator provisioning also permits an omitted email, consistent
with the existing bulk-roster workflow. Gmail work is deferred; its student OTP
flags stay off. Content review, publication, section assignment, safety prerequisites
and research consent remain separate. Status: Implemented but awaiting validation.
Isolated engineering checks cover both account-activation methods and the complete
student ID/password course journey, with synthetic desktop/mobile browser evidence
recorded in `docs/student-password-access.md`. Real-student use, physical-device
checks and faculty/research validation remain separate and pending.

## October 25, 2026: Student ID initial password and audited resets

The user corrected the first-time workflow: the student ID is both the username
and initial temporary password. The student must save a different private password
before opening course pages, content, reports or activities. Email, OTP and private
setup codes are not prerequisites for this route. This supersedes the immediately
preceding private-code-first decision. Existing active passwords remain unchanged
unless an authorized staff member explicitly resets that student.

New administrator-provisioned roster accounts receive a hashed student-ID initial
password when this policy is enabled. The existing pending students in the approved
current Section A and Section B offerings may be initialized through a scoped,
audited, idempotent operation; accounts remain pending until their required password
change completes. Existing identities, enrollment, Google links and learning records
are retained. No plaintext password is stored in audit events, source or reports.

An active administrator may reset a student. An active instructor may reset only
a student actively enrolled in that instructor's active MB 304L section. Program
Heads and students cannot reset accounts. A reset restores the student-ID temporary
password, requires another password change, invalidates older online sessions and
retains academic records. Audit events retain actor/target IDs, actor role, a
controlled reason and timestamps. Authorized staff can inspect scoped password
history. Email and code features remain disabled for the current rollout. Google
sign-in does not bypass the mandatory password change. Faculty review/publication,
section assignment, lesson prerequisites and research consent remain unchanged.
Status: Implemented but awaiting validation. Engineering evidence is recorded in
`docs/student-password-access.md`; real-student acceptance, physical-device checks
and faculty/research validation remain separate and pending.

## October 28, 2026: Offline access and release reliability

The user authorized offline sign-out recovery, per-record offline synchronization,
current account guidance, and mandatory release checks with deployment health
verification and code/runtime recovery. Local sign-out immediately removes the
learner context and blocks its restoration while server logout remains pending.
Reconnection completes server logout before course access resumes. Queued learning
records remain on the device under their original owner; rejected records remain
visible and retryable without blocking unrelated valid work. Session, connection,
rate-limit and server failures defer further requests rather than discarding work.

Guidance follows the enabled account mode. The current mode remains student ID as
username and initial password, mandatory password replacement, and audited resets
by administrators or assigned active-section teachers. Email/OTP remains deferred.
Release validation uses synthetic in-memory research-free test data. Deployment
prepares dependencies/assets outside the live checkout, verifies application and
asset health, and retains the previous runtime for recovery. Code/runtime recovery
does not roll back the database, student identities, enrollments or learning records.
If recovery fails health checks, maintenance remains enabled for operator action.

This addition does not introduce saved observation notebooks, new publication
decisions, research instruments, consent collection or research outcomes. Status:
Implemented but awaiting validation. Engineering evidence and the deployment
verification outcome are recorded in `docs/offline-reliability-verification.md`.

## October 28, 2026: Notebook draft recovery and student next steps

The user approved notebook autosave, same-account recovery, validated JSON import,
save-failure warnings, and a student checklist. This supersedes the session-only
notebook retention decision for this increment. Device drafts are keyed by account,
section, package identity and version. They remain separate from submitted work,
grades, research instruments and learning completion. JSON backups exclude account
identifiers; incomplete draft backups are supported alongside earlier exports.
Unknown, not observed, not applicable and observed zero remain distinct.

Sign-out immediately closes the notebook and its visible contents. Another account
cannot restore the draft through the application. Local browser-profile access is
outside that account boundary; use a trusted device, avoid personal identifiers,
and retain downloadable backups. No server notebook persistence, new research
collection, email requirement or academic-record migration is introduced. Storage
failure leaves entries visible with retry/download options and a leave warning.
Conflicting changes from another tab do not silently replace the saved copy.

The student checklist uses confirmed account, guide-version, enrollment and existing
prerequisite status for each active section. It distinguishes documented exceptions,
pending synchronization, missing assignments and available assigned versions.
Cached/offline information is labeled as needing online confirmation. The checklist
does not add a new access gate or bypass faculty review, publication or prerequisites.
Status: Implemented but awaiting validation. Engineering evidence is recorded in
`docs/notebook-readiness-verification.md`; faculty, real-student and research
validation remain separate.
