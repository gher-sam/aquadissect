# Temporary rabbitfish-only presentation — October 12, 2026

Status: Implemented but awaiting faculty validation.

The active species catalog and current class-activity payloads display rabbitfish only. The frontend applies the same filter to old offline catalog and assignment context; it does not delete downloaded packages or queued attempts. Sign-in, student activation and instructor-request pages use a neutral rabbitfish study-focus card instead of tilapia illustrations. Unpublished rabbitfish anatomy media is not served on guest pages.

Packages, assignments, results and original content remain retained. Historical reporting preserves recorded species. Existing authorization for historical package endpoints is unchanged; this is presentation scope, not a purge or revocation of historical access. Restoring `species-packages.visible_slugs` to an empty array restores the retained catalog without reseeding. Independent faculty review, publication, assignment and safety gates remain required for student rabbitfish access.

Engineering validation: 83 PHP tests / 1073 assertions passed; 8 JavaScript tests passed; production build, PHP/JavaScript syntax and whitespace checks passed; full npm audit reports zero vulnerabilities. Four focused PHP scenarios check rabbitfish-only staff catalogs, preservation of assignments/packages, unavailable student draft content, guest-page imagery and reversible restoration. Two JavaScript scenarios check old offline context filtering without mutating retained arrays. Existing multi-species/safety regression suites explicitly disable temporary presentation scope to continue exercising retained platform behavior.

Production counts before release: 4 packages, 3 assignments, 4 results. Deployment and live browser evidence are retained privately under outputs, outside the release. Software checks do not establish faculty approval or research readiness.
