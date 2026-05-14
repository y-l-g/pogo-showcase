FROM serversideup/php:8.5.6-frankenphp-trixie

USER root

RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc --fail https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && . /etc/os-release && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $VERSION_CODENAME-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update

RUN curl -fsSL https://deb.nodesource.com/setup_25.x -o nodesource_setup.sh \
    && bash nodesource_setup.sh \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs nano postgresql-client-18 libpq-dev \
    && ln -sf libpq.so.5.18 /usr/lib/x86_64-linux-gnu/libpq.so.5 \
    && ln -sf libpq.so.5.18 /usr/lib/x86_64-linux-gnu/libpq.so \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && rm nodesource_setup.sh

RUN install-php-extensions bcmath intl gd exif pdo_pgsql \
    && php -m | grep -E '^pdo_pgsql$' \
    && pg_dump --version

USER www-data

COPY --chown=www-data:www-data . .

RUN composer install -v \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative

RUN php artisan wayfinder:generate --with-form

RUN npm ci --legacy-peer-deps && npm run build:ssr
