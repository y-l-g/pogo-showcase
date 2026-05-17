# Pogo Showcase Single Binary

## GitHub Actions build

The `Build static binary` workflow builds the same Linux x86_64 binary in
GitHub Actions.

- Run it manually from the Actions tab to download the binary as a workflow
  artifact.
- Push a `v*` tag, for example `v0.1.0`, to publish the binary and checksum as
  GitHub Release assets.

The external Caddy modules are resolved through the normal Go module flow during
the Docker build.

## Fast preflight

Before pushing a tag or running the full static build, run:

```bash
bash scripts/check-static-binary-inputs.sh
php -l config/database.php
php artisan test
```

The preflight verifies that release-critical embedded app files are present,
tracked by Git, and not ignored. It also catches Caddyfile paths that would
depend on the process working directory instead of the embedded app path, and
worker scripts that call Laravel helpers before Composer autoload is available.

## Local build

Build from `pogoShowcase`:

```bash
GITHUB_TOKEN="$(gh auth token)" \
docker buildx build --load \
  --network=host \
  --progress=plain \
  --secret id=github-token,env=GITHUB_TOKEN \
  -t pogo-showcase-static \
  -f static-build.Dockerfile \
  .
```

The embedded Laravel app is built from the local `pogoShowcase` checkout. The
Go/Caddy modules are resolved from GitHub `main` during the static build:
`pogo`, `queue`, `scheduler`, `websocket`, and `pogo-showcase/runtime`. Push
changes to those repositories before building if the binary must include them.

The `github-token` build secret is used only by `static-php-cli` downloads to
avoid unauthenticated GitHub API rate limits. It is not used for Go module URL
rewrites and is not stored in the image.

UPX compression is disabled by default because this embedded binary is too large
for UPX in the current static builder. To experiment with UPX anyway, pass
`--build-arg NO_COMPRESS=`.

Copy the binary:

```bash
tmp="$(docker create pogo-showcase-static)"
docker cp "$tmp:/go/src/app/dist/pogo-showcase-linux-x86_64" ./pogo-showcase
docker rm "$tmp"
chmod +x ./pogo-showcase
```

Run locally without HTTPS:

```bash
SERVER_NAME=:8080 ./pogo-showcase php-server
```

Run on a VPS after DNS points to the server:

```bash
SERVER_NAME=pogo.y-l.fr ./pogo-showcase php-server
```

The binary creates `./data` next to itself on first start. That directory
contains the SQLite database, Laravel storage, logs, generated `APP_KEY`, and
websocket secrets. Keep `./data/runtime.env` when replacing the binary so
sessions and websocket authentication remain valid.

## Runtime secrets

Put production secrets in `data/runtime.env`. The binary loads this file on
startup, preserves custom keys when it refreshes generated secrets, and uses
normal process environment variables as overrides.

Example SMTP configuration:

```bash
cat >> data/runtime.env <<'EOF'
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@pogo.y-l.fr
MAIL_FROM_NAME="Pogo Showcase"
EOF
```

Restart the binary after editing `data/runtime.env`.
