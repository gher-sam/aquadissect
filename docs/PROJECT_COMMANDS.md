# AquaDissect Project Commands

## Purpose

This document is the canonical slash-command contract for the **AquaDissect** repository.

For each recognized slash command, follow the documented workflow, verify findings against the application code, and report the result concisely.

---

## Core Rules

### 1) Execute recognized commands directly
Treat recognized slash commands as high-priority intent signals. Do not prompt for unnecessary clarification when the command already defines the task clearly.

### 2) Parse arguments after `:`
If a command contains a colon, treat the text after `:` as the command argument.

Examples:
- `/smoke-page:tilapia`
- `/explain:app/Http/Controllers/DissectionController.php`
- `/trace:student-quiz-attempt`
- `/fix:layer opacity issue in 2.5d viewer`

### 3) Support multiple targets when practical
If an argument contains multiple comma-separated targets, analyze all of them when the command semantics allow it (e.g., `/explain:app/Models/Species.php,app/Models/Organ.php`).

### 4) Prefer practical engineering output
Structure responses using clear, actionable engineering sections:
- **Summary**
- **Relevant Files**
- **Logic Flow**
- **Issues Found**
- **Likely Cause**
- **Recommended Fix**
- **Risk Level**
- **Test Scenarios**
- **Impact**

### 5) Stay grounded in the codebase
Do not guess when the repository does not support a claim. State uncertainty or missing evidence explicitly. Adhere strictly to the project rules in `AGENTS.md` and [project-2-study-alignment.md](project-2-study-alignment.md).

### 6) `/help` must always work
`/help` lists all supported commands, their purpose, and short usage examples.

### 7) Credential and privacy safety
When a command touches credentials, deployment configuration, or secrets:
- Mask values by default.
- Never print full passwords, tokens, webhook secrets, or private keys.
- Adhere strictly to privacy rules: do not output or record personal names; use role-only designations (`Project Contributor`, `Technical Reviewer`, `Faculty Reviewer`).

---

## Supported Commands

### `/help`
Show all supported commands and syntax.
```text
/help
```

### `/understand`
Study the codebase and summarize system architecture, main modules (anatomy engine, 2.5D layer renderer, user accounts, quiz engine, clone database sync), data flow, and coding conventions.
```text
/understand
```

### `/map`
Create a quick map of the project structure and explain what each major folder does.
```text
/map
```

### `/explain:{file|class|function}`
Explain a file, class, or function in clear engineering language.
```text
/explain:app/Http/Controllers/DissectionController.php
/explain:resources/js/dissection/AnatomyViewer.js
```

### `/trace:{feature}`
Trace how a feature works end-to-end across frontend, Laravel controllers, models, database, and state.
```text
/trace:tilapia dissection flow
/trace:student account activation
/trace:quiz submission and grading
```

### `/impact:{file|feature}`
Check what parts of the application may break if a file or feature is modified.
```text
/impact:resources/js/dissection/LayerController.js
/impact:app/Models/SpeciesOffering.php
```

### `/find-bugs`
Scan the codebase for bugs, logic errors, broken routes, edge cases, failing flows, and runtime issues. Report findings only.
```text
/find-bugs
```

### `/fix-bugs`
Find bugs, fix them safely, explain each fix, and list changed files.
```text
/fix-bugs
```

### `/fix:{issue}`
Fix one specific reported issue instead of scanning the whole codebase.
```text
/fix:rabbitfish hotspot coordinates offset on mobile
```

### `/review`
Review the current branch or git diff for bugs, code quality, security vulnerabilities, and missed edge cases.
```text
/review
```

### `/refactor:{target}`
Refactor a specific file or module for clarity and maintainability without altering existing behavior.
```text
/refactor:app/Services/QuizService.php
```

### `/test`
Run or write relevant automated tests for current changes using PHPUnit/Pest.
```text
/test
/test:SpeciesCatalogTest
```

### `/test-page:{name}`
Run focused UI, component, and interaction checks for a specific view or page.
```text
/test-page:dissection-tilapia
/test-page:safety-orientation
```

### `/smoke-all`
Using browser automation (Playwright/Dusk), visit every major page (landing, login, student dashboard, instructor view, Tilapia viewer, Rabbitfish viewer, safety orientation, assessment), checking for console errors, broken UI, failed network requests, and navigation errors.
```text
/smoke-all
```

### `/smoke-page:{name}`
Using browser automation, test only the specified page, including canvas rendering, hotspot clicks, form submissions, and visible errors.
```text
/smoke-page:tilapia
/smoke-page:login
```

### `/repro:{issue}`
Attempt to reproduce a reported bug and identify the root cause.
```text
/repro:hotspot modal fails to open on touch devices
```

### `/lint-fix`
Run code style and lint fixes using Laravel Pint and project linters.
```text
/lint-fix
```

### `/type-fix`
Resolve TypeScript, JavaScript type doc, or PHP strict type errors.
```text
/type-fix
```

### `/perf-check`
Inspect 2.5D layer asset load times, Three.js / Canvas rendering frame rates, asset sizes, and database query performance.
```text
/perf-check
```

### `/security-check`
Check for authentication/authorization bypasses, role verification (Admin, Instructor, Student), CSRF protection, input sanitization, SQL injection risks, and secret exposure.
```text
/security-check
```

### `/api-check`
Inspect Laravel API and web routes, request validation, JSON resource formatting, and error handling.
```text
/api-check
```

### `/db-check`
Inspect Laravel migrations, SQLite primary and clone schemas, delayed-clone outbox table, seeders, and species catalog records.
```text
/db-check
```

### `/logs-check`
Inspect `storage/logs/laravel.log` and deployment logs to diagnose recent exceptions and errors.
```text
/logs-check
```

### `/docs-update`
Update documentation, setup instructions, and study alignment records based on recent code changes.
```text
/docs-update
```

### `/changelog`
Generate a clean summary of what changed for release notes or sprint handoff.
```text
/changelog
```

### `/commit`
Prepare a clean, standardized git commit message based on the current staged diff.
```text
/commit
```

### `/checkpoint`
Create a git checkpoint commit to preserve the current workspace state.
```text
/checkpoint: before rabbitfish layer integration
```

### `/pr-summary`
Generate a pull request title and description from the current branch diff.
```text
/pr-summary
```

### `/deploy-update`
Prepare the updated codebase for deployment to GitHub (`origin/main`), validating that changes are ready to trigger downstream deployment to `aquadissect.com`.
```text
/deploy-update
```

### `/live-check`
Verify that the latest intended update is live across the release path:
1. Local git commit
2. GitHub `origin/main` (`gher-sam/aquadissect`)
3. aaPanel webhook deployment trigger (`storage/app/deploy/github-webhook-trigger.json`)
4. Server file updates and live application responses at `https://aquadissect.com`

*Also accepts `/live_check` as a compatibility alias.*
```text
/live-check
```

### `/live-resolve`
Diagnose and resolve a failed, out-of-sync, or stale live deployment. Check GitHub push status, webhook secret/trigger files, server logs (`/var/log/aquadissect-deploy.log`), and rerun `/serve_update` or provide targeted SSH recovery steps.

*Also accepts `/live_resolve` as a compatibility alias.*
```text
/live-resolve
```

### `/serve_update`
Release alias for the repository's automated publish command:
```powershell
powershell -ExecutionPolicy Bypass -File scripts/serve-update.ps1
```

The release script stages validated changes, checks PHP syntax and whitespace, creates a release commit, and pushes to GitHub `origin/main`, triggering the live aaPanel webhook deployment at `aquadissect.com`.

**Modes**:
- `/serve_update: ON` *(Default)*: After completing and verifying an intentional codebase change, automatically run the standard release path in the same task rather than leaving changes local.
- `/serve_update: OFF`: Keep changes local after implementation and verification until explicitly requested to publish.
- `/serve_update: AUTO`: Automatically publish low-risk changes (documentation, UI text, minor CSS/bug fixes), but pause for review on high-risk changes (auth, database schema, production clone sync, deployment scripts).

*Also accepts `/serve_update` without arguments to trigger an immediate release check and push.*

Example:
```text
/serve_update
/serve_update: ON
/serve_update: OFF
/serve_update: AUTO
```

---

## AquaDissect Virtual Laboratory Commands

These commands target the MB 304L virtual dissection modules, anatomical structures, and laboratory learning sequences:

### `/species-check:{species}`
Validate anatomical packages, 2.5D layer images, hotspot coordinate data, organ function descriptions, and JSON catalogs for a given specimen (`tilapia`, `rabbitfish`, or `scad`).
```text
/species-check:tilapia
/species-check:rabbitfish
/species-check:scad
```

### `/tool-check`
Inspect the laboratory tools and safety module: verify tool definitions, safety PPE guides, animation playback configurations, and tool-selection formative activity assets.
```text
/tool-check
```

### `/quiz-check:{species}`
Audit the formative anatomy assessment questions, answer keys, explanation texts, and scoring logic for the specified species.
```text
/quiz-check:tilapia
```

### `/curriculum-check`
Verify application alignment with MB 304L Marine Zoology II Vertebrates Laboratory sequence (pretest $\rightarrow$ safety $\rightarrow$ tools $\rightarrow$ external $\rightarrow$ incision $\rightarrow$ internal 2.5D $\rightarrow$ posttest) as specified in [project-2-study-alignment.md](project-2-study-alignment.md).
```text
/curriculum-check
```

---

## Command Style Guide

- Use verb-first commands (e.g., `/find-bugs`, `/live-check`).
- Use lowercase names with hyphen separators (`/test-page`, `/smoke-all`).
- Use `:{arg}` for arguments (e.g., `/fix:hotspot coordinate mismatch`).
- Support legacy or compatibility aliases where noted (e.g., `/live_check`, `/serve_update`).
