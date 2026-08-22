#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
IMAGE=${BWRAP_SPIKE_IMAGE:-stashd-bwrap-spike:local}
CANARY=$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')

podman build --quiet -t "$IMAGE" "$ROOT" >/dev/null
output=$(podman run --rm \
    --user 1000:1000 \
    --cap-drop=ALL \
    --security-opt=no-new-privileges \
    -e SPIKE_VAULT_CANARY="$CANARY" \
    "$IMAGE")

printf '%s\n' "$output" | php -r '
$raw = stream_get_contents(STDIN);
$decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
$result = $decoded["result"];
$attempts = $result["attempts"];
$fail = static function (string $message): never { fwrite(STDERR, "FAIL: $message\n"); exit(1); };
$assert = static function (bool $condition, string $message) use ($fail): void { if (! $condition) { $fail($message); } };
$assert($attempts["/vault/DO_NOT_READ"] === "denied", "Vault canary was readable");
$assert($attempts["/app/secret.txt"] === "denied", "outer application filesystem was readable");
$assert($attempts["/data/database.env"] === "denied", "outer data filesystem was readable");
$assert($result["env"]["database"] === null && $result["env"]["encryption"] === null, "sensitive environment leaked");
$assert($result["plugin_mutation_bytes"] === false, "plugin mount is writable");
$assert($result["staging_bytes"] !== false && $result["tmp_bytes"] !== false, "writable mounts failed");
$assert($result["direct_network"] === "denied", "direct network succeeded");
$assert($result["broker"]["result"]["status"] === 200, "allowed broker request failed");
$assert($result["denied_broker"]["result"]["status"] === 403, "unauthorized broker request succeeded");
$assert($decoded["broker_requests"] === ["http://fixture.allowed/allowed", "http://fixture.forbidden/denied"], "broker protocol incomplete");
$assert($decoded["staging_content"] === "staged output\n", "staged output was not promoted to the runner");
echo "bubblewrap native plugin sandbox: PASS\n";
'
