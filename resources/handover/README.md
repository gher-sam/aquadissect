# AquaDissect source handover

This archive contains the selected application source at the release revision shown in the handover page. It contains no live database, accounts, credentials, uploaded files, logs, backups, manuscripts, Git history, or server deployment scripts. Educational package assets are included for authorized internal project development and remain subject to their package usage terms and faculty review requirements.

## Local setup

Use PHP 8.3 or later with SQLite, mbstring, XML and ZIP extensions, Composer, and a Node version compatible with Vite 8 (Node 22.12 or later is suitable).

1. Extract the ZIP to a new working directory.
2. Create the writable directories `storage/app/private`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/framework/testing`, `storage/logs`, and `bootstrap/cache`. Create an empty `database/database.sqlite` file for SQLite.
3. Copy `.env.example` to `.env`. Configure a local database if using MySQL instead of SQLite.
4. Run `composer install` and `npm ci`.
5. Run `php artisan key:generate`, `php artisan migrate`, and `php artisan db:seed` for LOCAL demonstration data only. Never use demonstration accounts in production.
6. Run `npm run build`, then `php artisan serve`.
7. Run `php artisan test` to check local behavior.

The source-handover archive builder itself requires a Git checkout and a committed revision; this downloaded ZIP intentionally contains no Git history. The learning application runs independently of the handover builder. The source handover page will be unavailable until a prepared release is installed in private storage.

Production deployment requires fresh credentials, an approved administrator provisioning process, secure web-server configuration, backup configuration, and separately provisioned content and account records. Do not copy production credentials into source files. The source package does not provide faculty approvals or research evidence.
