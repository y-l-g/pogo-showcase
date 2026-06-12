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

if ! rg -q -- '--with github\.com/y-l-g/pogo/module@main' static-build.Dockerfile; then
    printf 'static-build.Dockerfile must resolve the async module from GitHub with github.com/y-l-g/pogo/module@main.\n' >&2
    failed=1
fi

if rg -n 'github\.com/y-l-g/pogo/module=|async-module' static-build.Dockerfile .github/workflows/static-binary.yaml docs/single-binary.md >&2; then
    printf 'Static binary builds must not use a local async module path or BuildKit context.\n' >&2
    failed=1
fi

if rg -n '(^|[[:space:]])(file|worker|auth_script)[[:space:]]+public/|root[[:space:]]+\*[[:space:]]+public($|[[:space:]])|dir[[:space:]]+\.$' Caddyfile >&2; then
    printf 'Caddyfile contains cwd-sensitive static binary paths. Use {$POGO_SHOWCASE_APP_PATH:.}/... instead.\n' >&2
    failed=1
fi

if awk '
    /^[[:space:]]*pogo_queue[[:space:]]*\{/ {
        in_queue = 1
        depth = 1
        next
    }
    in_queue {
        line = $0
        opens = gsub(/\{/, "{", line)
        line = $0
        closes = gsub(/\}/, "}", line)

        if ($0 ~ /^[[:space:]]*name[[:space:]]+/) {
            printf "%s:%d:%s\n", FILENAME, FNR, $0
            found = 1
        }

        depth += opens - closes
        if (depth <= 0) {
            in_queue = 0
        }
    }
    END {
        exit found ? 0 : 1
    }
' Caddyfile >&2; then
    printf 'Caddyfile pogo_queue uses unsupported "name"; configure "queues" instead.\n' >&2
    failed=1
fi

if ! awk '
    /^[[:space:]]*pogo_queue[[:space:]]*\{/ {
        in_queue = 1
        depth = 1
        next
    }
    in_queue {
        line = $0
        opens = gsub(/\{/, "{", line)
        line = $0
        closes = gsub(/\}/, "}", line)

        if ($0 ~ /^[[:space:]]*backend[[:space:]]+memory[[:space:]]*\{/) {
            found = 1
        }

        depth += opens - closes
        if (depth <= 0) {
            in_queue = 0
        }
    }
    END {
        exit found ? 0 : 1
    }
' Caddyfile; then
    printf 'Caddyfile pogo_queue must declare backend memory for the static showcase binary.\n' >&2
    failed=1
fi

if rg -n 'Env::get|Request::server' public/*worker.php >&2; then
    printf 'Worker scripts must not call Laravel Env/Request before autoload. Use $_SERVER/$_ENV/getenv instead.\n' >&2
    failed=1
fi

if ! php <<'PHP'
<?php
$lock = json_decode(file_get_contents('composer.lock'), true, 512, JSON_THROW_ON_ERROR);
foreach ($lock['packages'] ?? [] as $candidate) {
    if (($candidate['name'] ?? null) === 'pogo/async') {
        fwrite(STDERR, "composer.lock must not contain pogo/async; the async module is built from GitHub by static-build.Dockerfile.\n");
        exit(1);
    }
}

exit(0);
PHP
then
    failed=1
fi

if [[ "${failed}" -ne 0 ]]; then
    exit 1
fi

printf 'Static binary input preflight passed.\n'
