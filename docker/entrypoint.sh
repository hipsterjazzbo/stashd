#!/bin/sh
set -eu

APP_DIR="/var/www/html"
DATA_DIR="${STASHD_DATA_PATH:-/data}"
MEDIA_DIR="${STASHD_MEDIA_PATH:-/media}"
PUID="${PUID:-1000}"
PGID="${PGID:-1000}"

log() {
    printf 'stashd: %s\n' "$*"
}

remap_app_user() {
    [ "$(id -u)" -eq 0 ] || return 0

    if ! getent group stashd >/dev/null 2>&1; then
        groupadd -o -g "${PGID}" stashd
    else
        groupmod -o -g "${PGID}" stashd 2>/dev/null || true
    fi

    if ! getent passwd stashd >/dev/null 2>&1; then
        useradd -o -u "${PUID}" -g stashd -d "${APP_DIR}" -s /usr/sbin/nologin stashd
    else
        usermod -o -u "${PUID}" -g stashd stashd 2>/dev/null || true
    fi
}

run_app() {
    if [ "$(id -u)" -eq 0 ]; then
        gosu stashd:"${PGID}" "$@"
    else
        "$@"
    fi
}

ensure_writable() {
    for dir in "$DATA_DIR" "$MEDIA_DIR"; do
        mkdir -p "$dir"
        if [ "$(id -u)" -eq 0 ]; then
            chown -R "${PUID}:${PGID}" "$dir" || true
        fi
    done
}

ensure_signing_key() {
    if [ -n "${SIGNING_KEY:-}" ]; then
        log "using operator-supplied SIGNING_KEY"
        return 0
    fi

    # A symlink into the bind-mounted $DATA_DIR is deliberately avoided here:
    # some container security profiles (confirmed via AppArmor's docker-default
    # on this host) deny non-root traversal of a symlink that crosses from the
    # image's own filesystem into a bind-mounted volume, even though root can
    # follow it fine. Copying the file instead never crosses that boundary.
    persisted_env="$DATA_DIR/.env"

    if [ -f "$persisted_env" ]; then
        cp "$persisted_env" "$APP_DIR/.env" || true
        if [ "$(id -u)" -eq 0 ]; then
            chown "${PUID}:${PGID}" "$APP_DIR/.env" || true
        fi
    fi

    run_app php tempest key:generate --no-override

    cp "$APP_DIR/.env" "$persisted_env"
    if [ "$(id -u)" -eq 0 ]; then
        chown "${PUID}:${PGID}" "$persisted_env" || true
    fi
}

prepare_runtime() {
    cd "$APP_DIR"
    ensure_writable
    ensure_signing_key
    export STASHD_DATA_PATH="$DATA_DIR"
    export STASHD_MEDIA_PATH="$MEDIA_DIR"
    export TEMPEST_INTERNAL_STORAGE="${DATA_DIR}/.tempest"
    run_app php tempest stashd:boot
}

ROLE="${1:-all}"

if [ "$(id -u)" -eq 0 ]; then
    remap_app_user
fi

cd "$APP_DIR"

export_runtime_env() {
    export STASHD_DATA_PATH="$DATA_DIR"
    export STASHD_MEDIA_PATH="$MEDIA_DIR"
    export TEMPEST_INTERNAL_STORAGE="${DATA_DIR}/.tempest"
}

case "$ROLE" in
    all)
        prepare_runtime
        log "starting supervisord"
        exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
        ;;
    serve)
        export_runtime_env
        run_app ./rr serve -c .rr.yaml
        ;;
    worker)
        export_runtime_env
        run_app php tempest stashd worker
        ;;
    scheduler)
        export_runtime_env
        run_app php tempest stashd scheduler
        ;;
    boot)
        prepare_runtime
        ;;
    *)
        log "unknown role: ${ROLE}" >&2
        exit 1
        ;;
esac
