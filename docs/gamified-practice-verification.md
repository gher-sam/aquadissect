# Gamified anatomy practice — November 16, 2026

Status: Implemented but awaiting faculty and research validation.

## Behavior

- Open a permitted species lesson, then choose **Practice challenges** below the
  guided lesson controls. The optional dialog preserves the current lesson step.
- Function finder matches an existing package description to a structure; System
  sorter matches a structure to its existing lesson system/role label. The small,
  deterministic set contains up to three challenges of each type.
- Select an answer, optionally show a hint, then check the answer. Wrong answers
  award nothing and permit retries. Every first correct completion awards 10 XP.
- Completion badges follow server-confirmed first completion, all function
  challenges, all system challenges, and the whole set. Reopening resumes the
  first unfinished challenge. Completed sets can be reviewed without earning again.
- The numbered challenge selector supports direct navigation and marks saved
  completions. Newly earned badges are announced with confirmed answer feedback.
- Connection failures show **Answer not confirmed**, independently of incorrect
  answers. Retrying an uncertain save reconciles the existing reward.
- Rewards are private and scoped by account, section, package and challenge key.
  Staff previews never save learner rewards. Signing out or leaving the lesson
  clears the dialog and aborts outstanding requests.
- Challenges require an online session. Failed/uncertain saves can be retried;
  the database uniqueness constraint prevents duplicate rewards. No offline XP is
  fabricated, queued, or presented as confirmed. Downloaded lesson study continues.

## Data and authorization

`practice_completions` is additive. Its unique scope is user, class, package and
challenge key. Submitted XP and scores are rejected; answers and points are
checked by the server. Published-package, section, enrollment, active-account,
password-change and safety checks remain in the existing request path.

The retained table participates in delayed-clone recording and recovery. Rollback
does not drop it, and reapplying the migration preserves its contents. Enrollment
deletion checks include practice history and invalidate an older administrative
preview when a learner earns a reward. Existing lesson results, paired assessments, notebook drafts,
grades, prerequisite state, packages and publication decisions are not updated.
Personalized practice responses use `Cache-Control: private, no-store`.

## Verification

Verified on November 16, 2026 with synthetic accounts and an isolated SQLite
database, after integrating with the current shared-repository source:

- 12 PHP practice checks passed (145 assertions), covering correct/incorrect
  answers, reward uniqueness, private account/section access, prerequisite and
  password gates, publication and enrollment changes, forged/stale submissions,
  staff previews, delayed-clone recovery, rollback/reapplication, and protection
  of enrollment history during administrative deletion.
- 10 JavaScript practice checks passed, covering resume/review, checking states,
  unconfirmed responses, challenge navigation, new badges, preview labels and
  escaped package content.
- Production asset build passed. The existing optional 3D chunk remains above
  Vite's 500 kB advisory limit.
- 14 browser checks passed at 1440 × 1000 and 375 × 812 with reduced motion,
  with no uncaught page errors. These exercised hints, wrong-answer retries,
  first rewards, all six challenges/four badges, immediate close/reopen,
  offline/reconnect, a server-saved answer whose response was deliberately lost,
  persisted reload/replay, focus recovery, sign-out during a pending check,
  separate-account progress, shared-cache exclusion and Rabbitfish staff preview.
- Desktop and phone screenshots were visually reviewed. The bundled game client
  also captured selectable Rabbitfish practice and its read-only state.

Browser evidence is retained privately under
`output/playwright/practice-20261116/`. The standard isolated release gate and
live deployment outcome are recorded separately; local checks alone do not
establish deployment completion. No production learner account was used for QA.

Faculty review of reused anatomy content and acceptance of the new practice
presentation remain required. Software checks are not research validation,
evidence of learning gains, or proof of practical-dissection competence.
