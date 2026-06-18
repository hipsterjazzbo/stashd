FROM docker.io/composer:2 AS composer

# Build the front-end assets (Vite + Tailwind + Alpine) into public/build/.
# The vite-plugin-tempest plugin normally shells out to `php tempest vite:config`
# for its settings; we feed it inline via TEMPEST_PLUGIN_CONFIGURATION_OVERRIDE
# so this stage stays pure Node (no PHP needed).
FROM docker.io/node:22-bookworm-slim AS assets
WORKDIR /app
ENV TEMPEST_PLUGIN_CONFIGURATION_OVERRIDE='{"build_directory":"build","bridge_file_name":"vite-tempest","manifest":"manifest.json","entrypoints":["src/main.entrypoint.ts"]}'
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.ts ./
COPY src ./src
RUN npm run build

FROM php:8.5-cli-bookworm AS base

ARG PUID=1000
ARG PGID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        gosu \
        supervisor \
        sqlite3 \
        libsqlite3-dev \
        libicu-dev \
        ffmpeg \
        curl \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_sqlite sockets intl \
    && php -m | grep -i '^uri$' >/dev/null \
    && php -r 'exit(extension_loaded("uri") ? 0 : 1);'

RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN groupadd -g "${PGID}" stashd \
    && useradd -u "${PUID}" -g stashd -d /var/www/html -s /usr/sbin/nologin stashd

COPY docker/supervisord.conf /etc/supervisor/conf.d/stashd.conf
COPY docker/entrypoint.sh /usr/local/bin/stashd-entrypoint
RUN chmod +x /usr/local/bin/stashd-entrypoint

ENV STASHD_HTTP_PORT=8474 \
    STASHD_YTDLP_BINARY=yt-dlp \
    STASHD_REAL_DOWNLOADS_ENABLED=0 \
    STASHD_DATA_PATH=/data \
    STASHD_MEDIA_PATH=/media \
    STASHD_PUBLIC_URL=http://localhost:8474

EXPOSE 8474

ENTRYPOINT ["stashd-entrypoint"]
CMD ["all"]

FROM base AS dev

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && pecl install xdebug pcov \
    && docker-php-ext-enable xdebug pcov \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php-dev.ini /usr/local/etc/php/conf.d/zz-stashd-dev.ini

ENV XDEBUG_MODE=off

COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-scripts

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN git config --global --add safe.directory /var/www/html \
    && composer dump-autoload --optimize \
    && php vendor/bin/tempest discovery:generate --no-interaction \
    && vendor/bin/rr get --no-config

RUN chown -R stashd:stashd /var/www/html

FROM base AS prod

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN git config --global --add safe.directory /var/www/html \
    && composer dump-autoload --optimize \
    && php vendor/bin/tempest discovery:generate --no-interaction \
    && vendor/bin/rr get --no-config \
    && rm -rf .tempest

RUN chown -R stashd:stashd /var/www/html
