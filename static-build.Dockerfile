# syntax=docker/dockerfile:1

FROM --platform=linux/amd64 dunglas/frankenphp:static-builder-gnu

ARG PHP_VERSION=8.5.6
ARG COMPRESS=0
ARG PHP_EXTENSIONS="bcmath,ctype,curl,dom,fileinfo,filter,iconv,intl,mbstring,opcache,openssl,pcntl,pdo,pdo_sqlite,phar,posix,session,simplexml,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,zip,zlib"
ARG XCADDY_ARGS="--with github.com/dunglas/caddy-cbrotli --with github.com/dunglas/mercure/caddy --with github.com/dunglas/vulcain/caddy --with github.com/y-l-g/pogo/module=/src/pogo/module --with github.com/y-l-g/queue/module=/src/queue/module --with github.com/y-l-g/scheduler/module=/src/scheduler/module --with github.com/y-l-g/websocket/module=/src/websocket/module --with github.com/y-l-g/pogo-showcase/runtime/module=/src/pogo-showcase/runtime/module"

ENV PHP_VERSION=${PHP_VERSION}
ENV PHP_EXTENSIONS=${PHP_EXTENSIONS}
ENV XCADDY_ARGS=${XCADDY_ARGS}
ENV COMPRESS=${COMPRESS}

WORKDIR /go/src/app
COPY frankenphp-main/ ./

COPY pogo/ /src/pogo/
COPY queue/ /src/queue/
COPY scheduler/ /src/scheduler/
COPY websocket/ /src/websocket/
COPY pogoShowcase/runtime/ /src/pogo-showcase/runtime/

WORKDIR /go/src/app/dist/app
COPY pogoShowcase/ ./
COPY queue/packages/laravel/ ../queue/packages/laravel/

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
		public/storage \
	&& cp .env.example .env \
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

WORKDIR /go/src/app
RUN EMBED=dist/app/ ./build-static.sh \
	&& cp dist/frankenphp-linux-x86_64 dist/pogo-showcase-linux-x86_64
