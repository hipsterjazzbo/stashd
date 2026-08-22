#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
IMAGE=${BWRAP_JELLYFIN_IMAGE:-stashd-bwrap-jellyfin:local}
build_flags=''
if [ "${BWRAP_JELLYFIN_NO_CACHE:-0}" = '1' ]; then build_flags='--no-cache'; fi
podman build $build_flags --quiet -t "$IMAGE" "$ROOT" >/dev/null

run_case() {
    mode="$1"
    podman run --rm --user 1000:1000 --cap-drop=ALL --security-opt=no-new-privileges \
        -e SPIKE_RUNNER=runner/jellyfin-run.php -e SPIKE_JELLYFIN_MODE="$mode" "$IMAGE"
}

happy=$(run_case lifecycle)
bad_auth=$(run_case bad-auth || true)
refresh_fail=$(run_case refresh-fail || true)
unauthorized=$(run_case unauthorized-destination || true)

printf '%s\n' "$happy" "$bad_auth" "$refresh_fail" "$unauthorized" | php -r '
$lines = array_values(array_filter(explode("\n", stream_get_contents(STDIN))));
$decode = static fn (string $value): array => json_decode($value, true, flags: JSON_THROW_ON_ERROR);
$assert = static function (bool $ok, string $message): void { if (! $ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } };
$happy = $decode($lines[0]);
$plugin = $happy["plugin"];
$result = $plugin["result"];
$assert($result["choices"] === [["value" => "abc", "label" => "TV"], ["value" => "def", "label" => "Movies"]], "generic library choices incorrect");
$assert($result["publication"]["files"][0]["relative_path"] === "TV/Season 01/S01E01 - Native PHP Demo.mp4", "publication layout incorrect");
$assert($result["finalized"]["refresh"] === "complete", "finalization did not complete");
$assert($happy["staging"] === "materialized authoritative file\n", "materialization was not observed");
$assert($happy["requests"][0]["method"] === "GET" && $happy["requests"][1]["method"] === "POST", "request lifecycle incorrect");
$assert($happy["requests"][1]["url"] === "http://fixture.jellyfin/Library/Refresh", "refresh URL incorrect");
$assert($happy["requests"][0]["raw_credential_seen"] === false, "raw credential reached plugin");
$assert($happy["requests"][0]["credential_injected"] === true, "broker did not inject the approved credential");
$sandbox = $plugin["sandbox"];
$assert($sandbox["vault"] === false && $sandbox["app"] === false && $sandbox["data"] === false, "outer files leaked");
$assert($sandbox["proc"] === false && $sandbox["env_database"] === null && $sandbox["env_encryption"] === null, "process/environment leaked");
$assert($sandbox["plugin_mutation_bytes"] === false && $sandbox["staging_bytes"] !== false && $sandbox["tmp_bytes"] !== false, "sandbox mounts incorrect");
$assert($sandbox["direct_network"] === false, "direct network succeeded");
$bad = $decode($lines[1]);
$assert(($bad["plugin"]["event"] ?? null) === "error", "invalid auth did not fail");
$fail = $decode($lines[2]);
$assert(($fail["plugin"]["event"] ?? null) === "error" && $fail["staging"] === "materialized authoritative file\n", "refresh failure semantics incorrect");
$denied = $decode($lines[3]);
$assert(($denied["plugin"]["event"] ?? null) === "error", "unauthorized destination succeeded");
echo "bubblewrap native PHP Jellyfin lifecycle: PASS\n";
'
