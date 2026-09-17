# AquaDissect Laravel application

AquaDissect is a Laravel 13, package-driven aquatic-vertebrate learning platform. Tilapia 1.0.0 is the published flagship package, with layered 2.5D imagery and its existing procedural 3D viewer. Goldspotted Rabbitfish (*Siganus guttatus*) 1.0.0 is included as a complete 2.5D draft for administrator and subject-matter review and is not available to students until it is published.

## Technology

- Laravel 13 with Blade and Laravel sessions
- Vite 8, a generic package-driven lesson runtime, and lazy-loaded Three.js viewers
- Laragon MySQL as the primary application database
- SQLite as a delayed, local clone of durable domain data
- One reusable MB 304L course, term-and-section offerings, versioned `.aqpack` assignments, offline downloads, and idempotent formative attempts

## Included roles

- Student: activates an administrator-provided account, opens assigned published packages, and sees version-pinned results
- Instructor: uses administrator-assigned MB 304L sections, manages species assignments, imports drafts, and reviews section progress
- Administrator: provisions students and sections, assigns instructors, reviews roster conflicts, manages approvals, and controls package publication

## Code organization

```text
app/
├── Http/Controllers      Catalog, lifecycle, assignment, attempt, and access endpoints
├── Http/Requests         Validation and input mapping
├── Models                Species, packages, classes, assignments, results, and clone metadata
├── Services              Package validation/storage, lifecycle, catalog, automatic setup, and clone services
└── Support/PortalData.php Shared role-aware portal bootstrap payload

resources/
├── css                   Portal, lesson, and image-viewer styles
├── js/app.js             Lesson controller and application entry point
├── js/layeredAnatomyViewer.js Required package-defined 2.5D viewer and accessible hotspots
├── js/viewerFactory.js   Built-in 2.5D, Tilapia procedural 3D, or data-only GLB selection
├── js/offlinePackages.js Verified package caches and IndexedDB attempt queue
├── js/tilapia3DViewer.js Lazy-loaded Tilapia renderer and accessibility hooks
├── js/tilapiaModel.js    Procedural exterior and segmented anatomy geometry
├── js/portal             API client, dashboard views, formatting, and portal controller
├── species-packs         Bundled immutable Tilapia and Rabbitfish package sources
├── media/tilapia         Original source and anatomical reference media
└── views/partials        Server-rendered portal markup
```

## Laragon use

For this configured checkout, start Laragon's Apache and MySQL services and open:

```text
http://localhost/tilapia/
```

No terminal command or `php artisan serve` process is needed for normal use. In the local environment, the first web request safely applies pending schema migrations to MySQL and SQLite under a setup lock and installs any missing bundled catalog records. Data mutations still follow the 12-hour clone delay.

The following commands are only for a new development checkout whose dependencies and application key have not yet been prepared:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

The Laragon Apache alias in `C:\laragon\etc\apache2\alias\tilapia.conf` points that address directly to Laravel's `public` directory. No `php artisan serve` command is required for normal use. The project-root and public `.htaccess` files prevent directory browsing, protect private application files, and forward application routes to Laravel.

In the required layered viewer, scroll over the specimen or use the anatomy-depth slider to move from natural appearance through external morphology to internal anatomy. Hotspots, the structure list, labels, reset, and full-screen controls are keyboard accessible. Tilapia also offers its lazy-loaded 3D mode. All visuals are educational representations; instructor-approved content and real-specimen observation remain authoritative.

## Species package format

An `.aqpack` is a ZIP-based, data-only archive with `manifest.json`, anatomy/lesson/quiz JSON, and mandatory natural, external, and internal PNG, JPEG, or WebP images. A data-only GLB model is optional. Uploaded packages cannot contain PHP, JavaScript, HTML, SVG, executable viewer adapters, absolute/traversal paths, duplicate entries, undeclared files, or invalid hashes. Uploads are limited to 100 MB and 250 MB after expansion, validated in private quarantine, and moved atomically into immutable versioned storage.

Instructors can import and submit drafts. Administrators control publishing, active-version selection, archiving, and restoration. Publishing a new version upgrades only assignments without a started attempt; started work remains pinned until an instructor explicitly reassigns it.

## Accounts and MB 304L sections

The administrator reuses one MB 304L course and creates a distinct offering for each academic year, semester, and section. Instructors cannot create offerings or accept student accounts. Public registration is limited to instructor requests, which still require administrator approval. Students receive accounts from the administrator and activate them with a single-use code that expires after three days. The code is returned once to the administrator, who must deliver it privately; it is not emailed by the application. Research consent is a separate process.

On **Roster & Sections**, the administrator can add a student manually or preview a CSV with the exact columns `first_name,last_name,email,identity_number`. A CSV import is all-or-nothing: conflicting IDs, emails, names, or duplicate rows must be resolved first. Reimporting the same roster reuses accounts and active enrollments. Administrators may assign instructors and archive or restore offerings without deleting results. Existing class records are labeled as legacy offerings; past attempts are not assigned to a section without reliable evidence. New attempts record their section.

## Offline learning

The Downloads page stores a version-specific application shell, content manifest, package assets, fonts, and any supported 3D viewer modules. Every declared package file is SHA-256 verified before the new cache replaces an older version. Formative attempts made without a connection use a client UUID in IndexedDB and synchronize idempotently after reconnection. Non-formative assessments are rejected offline.

## Delayed SQLite clone

The application reads and writes its live data in the Laragon MySQL database named `aquadissect`. Create, update, and delete operations for users, courses, section offerings, instructor links, enrollments, activation-token hashes, roster audit events, species/package metadata, assignments, and lesson results produce an outbox change in the same MySQL transaction. Schema migrations apply to both databases immediately, but the SQLite copy receives data mutations only when they are at least 12 hours old. Package binary media remains in private versioned Laravel storage and is not duplicated into SQLite.

Runtime infrastructure tables (`sessions`, cache, and queues) remain MySQL-only. They are intentionally excluded because they are temporary application state rather than durable learning records.

Useful operations:

```powershell
php artisan clone:status
php artisan clone:apply
php artisan clone:refresh --yes
php artisan schedule:list
```

`clone:apply` never bypasses the delay. Laravel checks it every minute, so a change is copied on the first scheduler run after it becomes 12 hours old. Keep `php artisan schedule:work` running locally, use `composer run dev` (which now includes it), or configure the production scheduler to run `php artisan schedule:run` every minute. `clone:refresh --yes` is an explicit administrative snapshot operation for setup or recovery and replaces only the configured domain tables.

The SQLite file is `database/database.sqlite`. The pre-migration copy is retained at `database/backups/database-before-mysql-20260828.sqlite`.

The current workstation was offline during the Composer scaffold. It therefore runs against the complete local Laravel 13.18.1 dependency tree through the `vendor` directory junction and uses `bootstrap/autoload.php` to keep AquaDissect namespaces local. When Packagist access is available, first verify that `vendor` is still a junction, remove only that junction, and then run `composer update` to create a normal project-owned vendor directory. Confirm the result with `php artisan --version` and rerun the tests.

## Demonstration accounts

The database seeder creates these accounts. All use password `demo123`:

- `student@aquadissect.local`
- `instructor@aquadissect.local`
- `admin@aquadissect.local`

These credentials are for local review only and must not be deployed unchanged.

## Verification

```powershell
npm run build
php artisan test
```

The feature suite covers role-aware catalogs, package validation and lifecycle rules, account activation, roster import and conflict handling, course/section authorization, server scoring and attempt deduplication, and the 12-hour relational clone graph.

## Production deployment

Production is served from `https://aquadissect.com` by the Hostinger VPS and
aaPanel. The private GitHub repository is the source of truth. A signed GitHub
webhook queues each `main` push for the root-owned deployment script on the VPS;
the webhook secret is retained only in GitHub and the server's private runtime
storage.

The production server keeps `.env`, uploaded package data, the delayed SQLite
clone, logs, sessions, and cache files outside Git. Deployment installs locked
Composer and npm dependencies, builds Vite assets, applies migrations, refreshes
the bundled species catalog, caches Laravel configuration/routes/views, and
returns the application from maintenance mode. `DatabaseSeeder` is deliberately
not run in production because its demonstration accounts use local-only
credentials.

## Archive

Historical Vite sources, the old production bundle, visual-test artifacts, and the incomplete offline vendor copy are consolidated under `archive/`. The directory is intentionally excluded from version control and is not used by the Laravel application.
