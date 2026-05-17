# Pogo Showcase Single Binary

## GitHub Actions build

The `Build static binary` workflow builds the same Linux x86_64 binary in
GitHub Actions.

- Run it manually from the Actions tab to download the binary as a workflow
  artifact.
- Push a `v*` tag, for example `v0.1.0`, to publish the binary and checksum as
  GitHub Release assets.

If any of the `y-l-g/*` repositories used by the build are private, configure a
`STATIC_BUILD_GITHUB_TOKEN` repository secret with read access to those
repositories. The workflow passes that token to BuildKit as a secret so it is not
baked into the image layers.

## Local build

Build from `pogoShowcase`:

```bash
GITHUB_TOKEN="$(gh auth token)" docker buildx build --load \
 --network=host \
 --progress=plain \
 --secret id=github-token,env=GITHUB_TOKEN \
 -t pogo-showcase-static \
 -f static-build.Dockerfile \
 ..
```

The embedded Laravel app is built from the local `pogoShowcase` checkout. The
Go/Caddy modules are resolved from GitHub `main` during the static build:
`pogo`, `queue`, `scheduler`, `websocket`, and `pogo-showcase/runtime`. Push
changes to those repositories before building if the binary must include them.

Compression with UPX is disabled by default. To explicitly try UPX compression:

```bash
GITHUB_TOKEN="$(gh auth token)" docker buildx build --load \
 --network=host \
 --progress=plain \
 --secret id=github-token,env=GITHUB_TOKEN \
 --build-arg COMPRESS=1 \
 -t pogo-showcase-static \
 -f static-build.Dockerfile \
 ..
```

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

The binary creates `./data` next to itself on first start. That directory contains the SQLite database, Laravel storage, logs, generated `APP_KEY`, and websocket secrets. Keep `./data/runtime.env` when replacing the binary so sessions and websocket authentication remain valid.
