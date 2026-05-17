# Pogo Showcase Single Binary

Build from the repository root:

```bash
GITHUB_TOKEN="$(gh auth token)" docker buildx build --load \
 --network=host \
 --progress=plain \
 --secret id=github-token,env=GITHUB_TOKEN \
 -t pogo-showcase-static \
 -f static-build.Dockerfile \
 ..

or without compression

GITHUB_TOKEN="$(gh auth token)" docker buildx build --load \
 --network=host \
 --progress=plain \
 --secret id=github-token,env=GITHUB_TOKEN \
 --build-arg COMPRESS= \
 -t pogo-showcase-static \
 -f static-build.Dockerfile \
 ..

docker cp "$(docker create --name pogo-showcase-static-tmp pogo-showcase-static):/go/src/app/dist/pogo-showcase-linux-x86_64" ./pogo-showcase
docker rm pogo-showcase-static-tmp
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
