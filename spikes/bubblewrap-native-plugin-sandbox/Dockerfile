FROM php:8.5-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends bubblewrap ca-certificates procps \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home --uid 1000 --shell /bin/sh stashd

WORKDIR /spike
COPY . /spike
RUN mkdir -p /outer-vault /outer-app /outer-data \
    && printf 'outer-app-secret\n' > /outer-app/secret.txt \
    && printf 'outer-db-password=not-for-plugins\n' > /outer-data/database.env \
    && chown -R stashd:stashd /spike /outer-vault /outer-app /outer-data

USER stashd
ENTRYPOINT ["/spike/run.sh"]
