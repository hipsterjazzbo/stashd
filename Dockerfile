FROM docker.io/composer:2 AS composer

FROM php:8.5-cli-bookworm

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
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_sqlite sockets intl uri \
    && curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
    && chmod a+rx /usr/local/bin/yt-dlp

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && php vendor/bin/tempest discovery:generate --no-interaction \
    && vendor/bin/rr get --no-config \
    && rm -rf .tempest \
    && groupadd -g 1000 stashd \
    && useradd -u 1000 -g stashd -d /var/www/html -s /usr/sbin/nologin stashd \
    && chown -R stashd:stashd /var/www/html

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
