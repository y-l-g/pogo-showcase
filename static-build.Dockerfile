# syntax=docker/dockerfile:1

ARG STATIC_BUILDER_PLATFORM=linux/amd64

FROM serversideup/php:8.5.6-frankenphp-trixie AS app-builder

USER root

WORKDIR /workspace/app
COPY . ./

RUN rm -rf \
		node_modules \
		vendor \
		.git \
		.agents \
		.codex \
		.dev \
		tests \
		storage/logs/* \
		storage/framework/cache/data/* \
		storage/framework/sessions/* \
		storage/framework/views/* \
		bootstrap/cache/*.php \
		.env \
		public/build \
		public/hot \
		public/storage \
	&& cp .env.example .env \
	&& export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= WS_APP_SECRET=static-build-secret POGO_WEBHOOK_SECRET=static-build-webhook-secret \
	&& composer install \
		--ignore-platform-reqs \
		--no-dev \
		--classmap-authoritative \
		--optimize-autoloader \
		--prefer-dist \
		--no-interaction \
	&& php artisan package:discover --ansi \
	&& php artisan wayfinder:generate --with-form --ansi \
	&& php artisan optimize:clear --ansi

FROM node:25-bookworm-slim AS asset-builder

WORKDIR /workspace/app
COPY --from=app-builder /workspace/app/ ./

RUN npm ci --legacy-peer-deps \
	&& POGO_SKIP_WAYFINDER_VITE=1 npm run build \
	&& rm -rf node_modules public/hot

FROM --platform=${STATIC_BUILDER_PLATFORM} dunglas/frankenphp:static-builder-gnu

ARG CI=true
ARG FRANKENPHP_VERSION=1.12.3
ARG PHP_VERSION=8.5.6
ARG NO_COMPRESS=1
ARG PHP_EXTENSIONS="bcmath,ctype,curl,dom,fileinfo,filter,iconv,intl,mbstring,opcache,openssl,pcntl,pdo,pdo_sqlite,phar,posix,session,simplexml,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,zip,zlib"
ARG PHP_EXTENSION_LIBS="libavif,nghttp2,nghttp3,ngtcp2,watcher,bzip2,xz,zstd,libssh2,ldap"
ARG XCADDY_ARGS="--with github.com/dunglas/caddy-cbrotli --with github.com/dunglas/mercure/caddy --with github.com/dunglas/vulcain/caddy --with github.com/y-l-g/pogo/module@main --with github.com/y-l-g/queue/module@main --with github.com/y-l-g/scheduler/module@main --with github.com/y-l-g/websocket/module@main --with github.com/y-l-g/pogo-showcase/runtime/module=/go/src/app/dist/app/runtime/module"

ENV CI=${CI} \
	FRANKENPHP_VERSION=${FRANKENPHP_VERSION} \
	PHP_VERSION=${PHP_VERSION} \
	PHP_EXTENSIONS=${PHP_EXTENSIONS} \
	PHP_EXTENSION_LIBS=${PHP_EXTENSION_LIBS} \
	SPC_CMD_VAR_FRANKENPHP_XCADDY_MODULES=${XCADDY_ARGS} \
	NO_COMPRESS=${NO_COMPRESS}

WORKDIR /go/src/app/dist/app
COPY --from=asset-builder /workspace/app/ ./

WORKDIR /go/src/app
RUN --mount=type=secret,id=github-token,required=false \
	if [ -s /run/secrets/github-token ]; then \
		export GITHUB_TOKEN="$(cat /run/secrets/github-token)"; \
	fi \
	&& EMBED=dist/app/ ./build-static.sh \
	&& cp dist/frankenphp-linux-x86_64 dist/pogo-showcase-linux-x86_64
