#!/usr/bin/env bash

set -Eeuo pipefail

SITE_DIR="/www/wwwroot/aquadissect.com"
DEPLOY_KEY="/root/.ssh/aquadissect_github_readonly"
LOG_FILE="/var/log/aquadissect-deploy.log"
PHP_BIN="/www/server/php/83/bin/php"
COMPOSER_BIN="/usr/bin/composer"
WEB_USER="www"
WEB_GROUP="www"
CLONE_DB_PATH="$SITE_DIR/storage/app/private/aquadissect-clone.sqlite"

exec >>"$LOG_FILE" 2>&1
printf '[%s] Starting AquaDissect deployment.\n' "$(date '+%Y-%m-%d %H:%M:%S')"

if [[ ! -d "$SITE_DIR/.git" ]]; then
    printf 'ERROR: %s is not an initialized Git checkout.\n' "$SITE_DIR"
    exit 1
fi

if [[ ! -f "$SITE_DIR/.env" ]]; then
    printf 'ERROR: production .env is missing.\n'
    exit 1
fi

export GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes"

cd "$SITE_DIR"

maintenance_enabled=0
restore_service() {
    if [[ "$maintenance_enabled" -eq 1 && -f artisan ]]; then
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
    fi
}
trap restore_service EXIT

git fetch --prune origin main

if [[ -f artisan && -f vendor/autoload.php ]]; then
    "$PHP_BIN" artisan down --retry=60 --refresh=15 || true
    maintenance_enabled=1
fi

git reset --hard origin/main
git clean -fd \
    -e .env \
    -e .user.ini \
    -e .well-known \
    -e storage

install -d \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

"$PHP_BIN" "$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

npm ci --no-audit --no-fund
npm run build

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --class='Database\Seeders\SpeciesCatalogSeeder' --force

clone_initialized=0
if [[ ! -f "$CLONE_DB_PATH" ]]; then
    install -o "$WEB_USER" -g "$WEB_GROUP" -m 640 /dev/null "$CLONE_DB_PATH"
    clone_initialized=1
fi

# Schema changes are immediate for both databases. Domain mutations remain
# subject to the delayed-clone outbox after the initial recovery snapshot.
"$PHP_BIN" artisan migrate --database=sqlite_clone --force
if [[ "$clone_initialized" -eq 1 ]]; then
    "$PHP_BIN" artisan clone:refresh --yes
fi
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

find "$SITE_DIR" -xdev ! -path "$SITE_DIR/public/.user.ini" -exec chown root:root {} +
find "$SITE_DIR" -xdev -type d -exec chmod 755 {} +
find "$SITE_DIR" -xdev -type f ! -path "$SITE_DIR/public/.user.ini" -exec chmod 644 {} +
chmod 600 "$DEPLOY_KEY"
chmod 640 "$SITE_DIR/.env"
chown root:"$WEB_GROUP" "$SITE_DIR/.env"
chown -R "$WEB_USER:$WEB_GROUP" \
    "$SITE_DIR/storage" \
    "$SITE_DIR/bootstrap/cache" \
    "$SITE_DIR/public/build"

# Webhook state is private but must remain readable and writable by PHP.
if [[ -d "$SITE_DIR/storage/app/deploy" ]]; then
    chmod 750 "$SITE_DIR/storage/app/deploy"
    find "$SITE_DIR/storage/app/deploy" -maxdepth 1 -type f -exec chmod 640 {} +
fi

release_sha="$(git rev-parse HEAD)"
printf '%s\n' "$release_sha" > storage/app/deployed-revision.txt
chown "$WEB_USER:$WEB_GROUP" storage/app/deployed-revision.txt
chmod 640 storage/app/deployed-revision.txt

"$PHP_BIN" artisan up || true
maintenance_enabled=0
trap - EXIT

printf '[%s] Deployment completed at %s.\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$release_sha"
