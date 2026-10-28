#!/usr/bin/env bash
set -Eeuo pipefail
script="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/scripts/deployment/deploy-aquadissect.sh"
test_root="$(mktemp -d "${TMPDIR:-/tmp}/aquadissect-deploy-test-XXXXXX")"
export GIT_AUTHOR_NAME='Project Contributor' GIT_COMMITTER_NAME='Project Contributor'
export GIT_AUTHOR_EMAIL='project-contributor@users.noreply.github.com' GIT_COMMITTER_EMAIL='project-contributor@users.noreply.github.com'
export GIT_CONFIG_COUNT=2 GIT_CONFIG_KEY_0=commit.gpgsign GIT_CONFIG_VALUE_0=false GIT_CONFIG_KEY_1=core.autocrlf GIT_CONFIG_VALUE_1=false
mkdir -p "$test_root/bin"
cat >"$test_root/bin/php" <<'PHP'
#!/usr/bin/env bash
set -e
if [[ "$1" = "$AQD_COMPOSER_BIN" ]]; then
    [[ "${FAIL_BUILD:-0}" != 1 ]] || exit 45
    mkdir -p vendor
    cp VERSION vendor/runtime.txt
    echo '<?php' >vendor/autoload.php
elif [[ "$1" = -r ]]; then
    echo assets/app.js
elif [[ "$2" = down ]]; then
    mkdir -p storage/framework; touch storage/framework/down
elif [[ "$2" = up ]]; then
    rm -f storage/framework/down
elif [[ "$2" = db:seed ]]; then
    echo 'Unrelated deployment must not replay content setup' >&2
    exit 47
elif [[ "$2" = migrate && "${FAIL_ACTIVATION:-0}" = 1 && "$(cat VERSION)" = new ]]; then
    exit 46
fi
PHP
cat >"$test_root/bin/npm" <<'NPM'
#!/usr/bin/env bash
set -e
if [[ "$1" = run ]]; then
    mkdir -p public/build/assets
    printf '{"resources/js/app.js":{"file":"assets/app.js"}}' >public/build/manifest.json
    cp VERSION public/build/assets/app.js
fi
NPM
cat >"$test_root/bin/curl" <<'CURL'
#!/usr/bin/env bash
if [[ "${FAIL_ALL_HEALTH:-0}" = 1 ]]; then exit 22; fi
if [[ "${FAIL_HEALTH:-0}" = 1 && "$(cat "$AQD_SITE_DIR/VERSION")" = new ]]; then exit 22; fi
[[ ! -f "$AQD_SITE_DIR/storage/framework/down" ]]
CURL
printf '#!/usr/bin/env bash\nexit 0\n' >"$test_root/bin/chown"
if [[ "$(uname -s)" = MINGW* || "$(uname -s)" = MSYS* ]]; then
    # Git Bash cannot apply Linux directory ownership/modes. Runtime transition tests still use real files.
    cat >"$test_root/bin/install" <<'INSTALL'
#!/usr/bin/env bash
set -e
directory=0; targets=()
while [[ "$#" -gt 0 ]]; do
    case "$1" in -d) directory=1;; -m|-o|-g) shift;; *) targets+=("$1");; esac
    shift
done
if [[ "$directory" = 1 ]]; then mkdir -p "${targets[@]}"; else touch "${targets[-1]}"; fi
INSTALL
fi
chmod +x "$test_root/bin/"*
export PATH="$test_root/bin:$PATH"

for scenario in success build-failure activation-failure health-failure recovery-failure; do
    case_dir="$test_root/$scenario"
    trap 'echo "FAIL deployment scenario $scenario"; tail -15 "$case_dir/deploy.log" 2>/dev/null || true' ERR
    mkdir -p "$case_dir"
    git init --bare --quiet --initial-branch=main "$case_dir/origin.git"
    git init --quiet --initial-branch=main "$case_dir/source"
    (
        cd "$case_dir/source"
        printf 'vendor/\nnode_modules/\npublic/build/\nstorage/\n.env\n' >.gitignore
        echo old >VERSION; touch artisan
        mkdir -p bootstrap/cache; printf '*\n!.gitignore\n' >bootstrap/cache/.gitignore
        git add .; git commit --quiet -m 'Previous synthetic release'
        git remote add origin "$case_dir/origin.git"; git push --quiet origin main
    )
    git clone --quiet "$case_dir/origin.git" "$case_dir/site"
    old_sha="$(git -C "$case_dir/site" rev-parse HEAD)"
    (
        cd "$case_dir/source"; echo new >VERSION
        git add VERSION; git commit --quiet -m 'Candidate synthetic release'; git push --quiet origin main
    )
    new_sha="$(git -C "$case_dir/source" rev-parse HEAD)"
    mkdir -p "$case_dir/site/vendor" "$case_dir/site/public/build/assets" "$case_dir/site/storage/app/private"
    echo '<?php' >"$case_dir/site/vendor/autoload.php"
    echo old >"$case_dir/site/vendor/runtime.txt"
    echo old >"$case_dir/site/public/build/assets/app.js"
    echo '{"resources/js/app.js":{"file":"assets/app.js"}}' >"$case_dir/site/public/build/manifest.json"
    echo 'APP_ENV=testing' >"$case_dir/site/.env"
    echo '{"students":3,"enrollments":3,"results":7}' >"$case_dir/site/storage/records.json"
    touch "$case_dir/site/storage/app/private/aquadissect-clone.sqlite"
    printf '%s\n' "$old_sha" >"$case_dir/site/storage/app/deployed-revision.txt"
    before="$(sha256sum "$case_dir/site/storage/records.json" | cut -d ' ' -f 1)"
    export AQD_SITE_DIR="$case_dir/site" AQD_RELEASE_ROOT="$case_dir/releases" AQD_DEPLOY_LOG="$case_dir/deploy.log"
    export AQD_PHP_BIN="$test_root/bin/php" AQD_COMPOSER_BIN="$test_root/mock-composer"
    export AQD_HEALTH_ORIGIN=https://example.invalid AQD_HEALTH_HOST=example.invalid AQD_WEB_USER=www AQD_WEB_GROUP=www
    export FAIL_BUILD=0 FAIL_ACTIVATION=0 FAIL_HEALTH=0 FAIL_ALL_HEALTH=0
    case "$scenario" in
        build-failure) export FAIL_BUILD=1;;
        activation-failure) export FAIL_ACTIVATION=1;;
        health-failure) export FAIL_HEALTH=1;;
        recovery-failure) export FAIL_ALL_HEALTH=1;;
    esac
    status=0
    bash "$script" || status="$?"
    after="$(sha256sum "$case_dir/site/storage/records.json" | cut -d ' ' -f 1)"
    [[ "$before" = "$after" ]] || { echo 'Synthetic learner records changed'; exit 1; }
    if [[ "$scenario" = success ]]; then
        [[ "$status" -eq 0 && "$(git -C "$case_dir/site" rev-parse HEAD)" = "$new_sha" ]]
        [[ "$(cat "$case_dir/site/vendor/runtime.txt")" = new ]]
        [[ "$(cat "$case_dir/site/storage/app/deployed-revision.txt")" = "$new_sha" ]]
    else
        [[ "$status" -ne 0 && "$(git -C "$case_dir/site" rev-parse HEAD)" = "$old_sha" ]]
        [[ "$(cat "$case_dir/site/vendor/runtime.txt")" = old ]]
        [[ "$(cat "$case_dir/site/public/build/assets/app.js")" = old ]]
        [[ "$(cat "$case_dir/site/storage/app/deployed-revision.txt")" = "$old_sha" ]]
    fi
    if [[ "$scenario" = recovery-failure ]]; then [[ -f "$case_dir/site/storage/framework/down" ]];
    else [[ ! -f "$case_dir/site/storage/framework/down" ]]; fi
    echo "PASS deployment $scenario; synthetic accounts and records preserved"
done
