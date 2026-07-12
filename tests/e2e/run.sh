#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
IMAGE="${STASHD_E2E_IMAGE:-stashd:e2e}"
NAME="stashd-e2e-$$"
TMP="$(mktemp -d)"
PUID="$(id -u)"
PGID="$(id -g)"

cleanup() {
    docker rm -f "$NAME" >/dev/null 2>&1 || true
    rm -rf "$TMP"
}
trap cleanup EXIT INT TERM

mkdir -p "$TMP/data" "$TMP/media"
docker build -t "$IMAGE" "$ROOT"
docker run -d --name "$NAME" -e STASHD_DATA_PATH=/data -e STASHD_MEDIA_PATH=/media \
    -e PUID="$PUID" -e PGID="$PGID" \
    -v "$TMP/data:/data" -v "$TMP/media:/media" -p 18475:8474 "$IMAGE" >/dev/null

until curl -fsS http://127.0.0.1:18475/health >/dev/null; do sleep 2; done

STASHD_BASE_URL=http://127.0.0.1:18475 npm run test:e2e
