#!/bin/sh
set -eu

CANARY="${SPIKE_VAULT_CANARY:-unset-canary}"
printf '%s\n' "$CANARY" > /outer-vault/DO_NOT_READ
export STASHD_DATABASE_URL='postgres://secret-user:secret-password@db/stashd'
export STASHD_ENCRYPTION_KEY='do-not-cross-the-boundary'

exec php "${SPIKE_RUNNER:-runner/run.php}"
