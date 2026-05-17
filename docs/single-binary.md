# Pogo Showcase Single Binary

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
