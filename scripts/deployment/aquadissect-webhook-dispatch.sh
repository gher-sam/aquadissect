#!/usr/bin/env bash

set -Eeuo pipefail

SITE_DIR="/www/wwwroot/aquadissect.com"
TRIGGER_FILE="$SITE_DIR/storage/app/deploy/github-webhook-trigger.json"
PROCESSED_FILE="$SITE_DIR/storage/app/deploy/github-webhook-trigger.last"
DEPLOY_LOG="/var/log/aquadissect-deploy.log"
LOCK_FILE="/var/lock/aquadissect-deploy.lock"

if [[ ! -f "$TRIGGER_FILE" ]]; then
    exit 0
fi

mkdir -p "$(dirname "$PROCESSED_FILE")"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    exit 0
fi

trigger_hash="$(sha256sum "$TRIGGER_FILE" | awk '{print $1}')"
last_hash="$(sed -n '1p' "$PROCESSED_FILE" 2>/dev/null || true)"
if [[ "$trigger_hash" == "$last_hash" ]]; then
    exit 0
fi

printf '[%s] Signed GitHub push queued (%s).\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$trigger_hash" >>"$DEPLOY_LOG"

if /usr/local/sbin/deploy-aquadissect; then
    printf '%s\n' "$trigger_hash" >"$PROCESSED_FILE"
    chmod 640 "$PROCESSED_FILE"
    chown www:www "$PROCESSED_FILE"
    exit 0
fi

printf '[%s] Queued deployment failed; it will be retried.\n' "$(date '+%Y-%m-%d %H:%M:%S')" >>"$DEPLOY_LOG"
exit 1
