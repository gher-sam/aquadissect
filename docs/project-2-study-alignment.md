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
