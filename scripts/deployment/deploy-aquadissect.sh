#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

# Root-owned operator configuration; defaults retain signed-webhook deployment.
SITE_DIR="${AQD_SITE_DIR:-/www/wwwroot/aquadissect.com}"
RELEASE_ROOT="${AQD_RELEASE_ROOT:-/www/aquadissect-releases}"
DEPLOY_KEY="${AQD_DEPLOY_KEY:-/root/.ssh/aquadissect_github_readonly}"
LOG_FILE="${AQD_DEPLOY_LOG:-/var/log/aquadissect-deploy.log}"
PHP_BIN="${AQD_PHP_BIN:-/www/server/php/83/bin/php}"
COMPOSER_BIN="${AQD_COMPOSER_BIN:-/usr/bin/composer}"
WEB_USER="${AQD_WEB_USER:-www}"
WEB_GROUP="${AQD_WEB_GROUP:-www}"
HEALTH_ORIGIN="${AQD_HEALTH_ORIGIN:-https://aquadissect.com}"
HEALTH_HOST="${AQD_HEALTH_HOST:-aquadissect.com}"
CLONE_DB_PATH="$SITE_DIR/storage/app/private/aquadissect-clone.sqlite"

exec >>"$LOG_FILE" 2>&1
printf '[%s] Starting AquaDissect deployment.\n' "$(date '+%Y-%m-%d %H:%M:%S')"
[[ "$SITE_DIR" = /* && "$RELEASE_ROOT" = /* && "$SITE_DIR" != / && "$RELEASE_ROOT" != / ]]
[[ -d "$SITE_DIR/.git" && -f "$SITE_DIR/.env" ]]
install -d -m 700 "$RELEASE_ROOT"
export GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes"
cd "$SITE_DIR"
git fetch --prune origin main
previous_sha="$(git rev-parse HEAD)"
target_sha="$(git rev-parse origin/main)"
release_dir="$(mktemp -d "$RELEASE_ROOT/release-${target_sha:0:12}-XXXXXX")"
candidate="$release_dir/candidate"
backup="$release_dir/previous"
install -d "$candidate" "$backup"
printf '%s\n' "$previous_sha" >"$backup/revision"
maintenance_enabled=0
code_changed=0
vendor_saved=0
build_saved=0

health_check() {
    local asset
    for path in up login build/manifest.json; do
        curl --fail --silent --show-error --max-time 15 --retry 2 --retry-all-errors \
            --resolve "$HEALTH_HOST:443:127.0.0.1" "$HEALTH_ORIGIN/$path" >/dev/null || return 1
    done
    asset="$("$PHP_BIN" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["resources/js/app.js"]["file"] ?? "";' "$SITE_DIR/public/build/manifest.json")" || return 1
    [[ -n "$asset" && "$asset" != *..* ]] || return 1
    curl --fail --silent --show-error --max-time 15 --retry 2 --retry-all-errors \
        --resolve "$HEALTH_HOST:443:127.0.0.1" "$HEALTH_ORIGIN/build/$asset" >/dev/null
}

recover_previous_release() {
    local failure="$?"
    trap - EXIT
    if [[ "$failure" -eq 0 ]]; then return; fi
    printf '[%s] Deployment failed; preserving data and recovering %s.\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$previous_sha"
    if [[ "$maintenance_enabled" -eq 0 ]]; then
        printf 'Candidate preparation failed before the live application changed.\n'
        exit "$failure"
    fi
    set +e
    "$PHP_BIN" artisan down --retry=60 --refresh=15
    recovery_ok=1
    if [[ "$code_changed" -eq 1 ]]; then git reset --hard "$previous_sha" || recovery_ok=0; fi
    if [[ "$vendor_saved" -eq 1 ]]; then
        [[ ! -d "$SITE_DIR/vendor" ]] || mv "$SITE_DIR/vendor" "$release_dir/failed-vendor" || recovery_ok=0
        mv "$backup/vendor" "$SITE_DIR/vendor" || recovery_ok=0
    fi
    if [[ "$build_saved" -eq 1 ]]; then
        [[ ! -d "$SITE_DIR/public/build" ]] || mv "$SITE_DIR/public/build" "$release_dir/failed-build" || recovery_ok=0
        mv "$backup/build" "$SITE_DIR/public/build" || recovery_ok=0
    fi
    if [[ -d "$backup/bootstrap-cache" ]]; then
        # Only compiled code caches are replaced. Database and learner storage remain intact.
        find "$SITE_DIR/bootstrap/cache" -maxdepth 1 -type f -name '*.php' -delete
        cp -a "$backup/bootstrap-cache/." "$SITE_DIR/bootstrap/cache/" || recovery_ok=0
    fi
    if [[ "$recovery_ok" -eq 1 ]]; then
        "$PHP_BIN" artisan source:build || recovery_ok=0
        "$PHP_BIN" artisan up || recovery_ok=0
        if [[ "$recovery_ok" -eq 1 ]] && health_check; then
            printf '%s\n' "$previous_sha" >"$SITE_DIR/storage/app/deployed-revision.txt"
            chown "$WEB_USER:$WEB_GROUP" "$SITE_DIR/storage/app/deployed-revision.txt"
            chmod 640 "$SITE_DIR/storage/app/deployed-revision.txt"
            printf 'Previous release restored and health checks passed: %s. No database rollback performed.\n' "$previous_sha"
            exit "$failure"
        fi
    fi
    "$PHP_BIN" artisan down --retry=60 --refresh=15
    printf 'ERROR: Recovery failed health checks. Maintenance remains enabled; operator action is required.\n'
    exit "$failure"
}
trap recover_previous_release EXIT

# Build outside the live checkout. A preparation failure leaves the site available.
git archive "$target_sha" | tar -x -C "$candidate"
ln -s "$SITE_DIR/.env" "$candidate/.env"
install -d "$candidate/bootstrap/cache" "$candidate/storage/framework/cache/data" \
    "$candidate/storage/framework/sessions" "$candidate/storage/framework/views" "$candidate/storage/logs"
(
    cd "$candidate"
    "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction
    npm ci --no-audit --no-fund
    npm run build
)
[[ -f "$candidate/vendor/autoload.php" && -s "$candidate/public/build/manifest.json" ]]
cp -a "$SITE_DIR/bootstrap/cache" "$backup/bootstrap-cache"
maintenance_enabled=1
"$PHP_BIN" artisan down --retry=60 --refresh=15
code_changed=1
git reset --hard "$target_sha"
if [[ -d "$SITE_DIR/vendor" ]]; then mv "$SITE_DIR/vendor" "$backup/vendor"; vendor_saved=1; fi
mv "$candidate/vendor" "$SITE_DIR/vendor"
if [[ -d "$SITE_DIR/public/build" ]]; then mv "$SITE_DIR/public/build" "$backup/build"; build_saved=1; fi
mv "$candidate/public/build" "$SITE_DIR/public/build"
install -d storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs bootstrap/cache
find "$SITE_DIR/bootstrap/cache" -maxdepth 1 -type f -name '*.php' -delete
"$PHP_BIN" artisan package:discover
"$PHP_BIN" artisan source:build
"$PHP_BIN" artisan migrate --force
# Do not replay content setup during an unrelated code release. These seeders can
# update existing class/module metadata even when no new bundle is being shipped.
if ! git diff --quiet "$previous_sha" "$target_sha" -- resources/species-packs ':(exclude)resources/species-packs/rabbitfish/1.2.0' ':(exclude)resources/species-packs/rabbitfish/1.3.0'; then
    "$PHP_BIN" artisan db:seed --class='Database\Seeders\SpeciesCatalogSeeder' --force
fi
if ! git diff --quiet "$previous_sha" "$target_sha" -- database/seeders/RabbitfishGuidedClipsSeeder.php resources/species-packs/rabbitfish/1.2.0 resources/species-packs/rabbitfish/1.3.0; then
    "$PHP_BIN" artisan db:seed --class='Database\Seeders\RabbitfishGuidedClipsSeeder' --force
fi
if ! git diff --quiet "$previous_sha" "$target_sha" -- database/seeders/LearningModuleSeeder.php resources/learning-modules; then
    "$PHP_BIN" artisan db:seed --class='Database\Seeders\LearningModuleSeeder' --force
fi
clone_initialized=0
if [[ ! -f "$CLONE_DB_PATH" ]]; then
    install -o "$WEB_USER" -g "$WEB_GROUP" -m 640 /dev/null "$CLONE_DB_PATH"
    clone_initialized=1
fi
"$PHP_BIN" artisan migrate --database=sqlite_clone --force
if [[ "$clone_initialized" -eq 1 ]]; then "$PHP_BIN" artisan clone:refresh --yes; fi
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
chmod 640 "$SITE_DIR/.env"
chown root:"$WEB_GROUP" "$SITE_DIR/.env"
chown -R "$WEB_USER:$WEB_GROUP" "$SITE_DIR/storage" "$SITE_DIR/bootstrap/cache" "$SITE_DIR/public/build"
for clone_file in "$CLONE_DB_PATH" "$CLONE_DB_PATH-wal" "$CLONE_DB_PATH-shm"; do
    if [[ -f "$clone_file" ]]; then chmod 640 "$clone_file"; fi
done
for private_dir in "$SITE_DIR/storage/app/private/source-handover" "$SITE_DIR/storage/app/deploy"; do
    if [[ -d "$private_dir" ]]; then
        chmod 750 "$private_dir"
        find "$private_dir" -maxdepth 1 -type f -exec chmod 640 {} +
    fi
done
"$PHP_BIN" artisan up
health_check
printf '%s\n' "$target_sha" >storage/app/deployed-revision.txt
chown "$WEB_USER:$WEB_GROUP" storage/app/deployed-revision.txt
chmod 640 storage/app/deployed-revision.txt
maintenance_enabled=0
trap - EXIT
printf '[%s] Deployment completed and health checks passed at %s. Previous runtime retained at %s.\n' \
    "$(date '+%Y-%m-%d %H:%M:%S')" "$target_sha" "$backup"
