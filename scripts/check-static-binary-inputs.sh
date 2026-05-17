#!/usr/bin/env bash
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

required_files=(
    artisan
    Caddyfile
    composer.json
    composer.lock
    public/index.php
    public/frankenphp-worker.php
    public/pogo-worker.php
    public/queue-worker.php
    public/scheduler-worker.php
    public/websocket-worker.php
)

failed=0

for file in "${required_files[@]}"; do
    if [[ ! -f "${file}" ]]; then
        printf 'Missing required static binary input: %s\n' "${file}" >&2
        failed=1
        continue
    fi

    if git check-ignore -q "${file}"; then
        printf 'Required static binary input is ignored by git: %s\n' "${file}" >&2
        git check-ignore -v "${file}" >&2 || true
        failed=1
    fi

    if ! git ls-files --error-unmatch "${file}" >/dev/null 2>&1; then
        printf 'Required static binary input is not tracked by git: %s\n' "${file}" >&2
        failed=1
    fi
done

if rg -n '(^|[[:space:]])(file|worker|auth_script)[[:space:]]+public/|root[[:space:]]+\*[[:space:]]+public($|[[:space:]])|dir[[:space:]]+\.$' Caddyfile >&2; then
    printf 'Caddyfile contains cwd-sensitive static binary paths. Use {$POGO_SHOWCASE_APP_PATH:.}/... instead.\n' >&2
    failed=1
fi

if rg -n 'Env::get|Request::server' public/*worker.php >&2; then
    printf 'Worker scripts must not call Laravel Env/Request before autoload. Use $_SERVER/$_ENV/getenv instead.\n' >&2
    failed=1
fi

if [[ "${failed}" -ne 0 ]]; then
    exit 1
fi

printf 'Static binary input preflight passed.\n'
