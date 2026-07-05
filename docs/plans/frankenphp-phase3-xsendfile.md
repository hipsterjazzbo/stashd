# FrankenPHP Migration — Phase 3: X-Accel-Redirect podcast episode serving

Phases 1–2 (classic mode, Mercure) shipped in PR #17. This is Phase 3 from
`docs/plans/frankenphp-migration.md`'s original scoped plan (that doc itself
was never committed — phase work landed directly instead — but its Phase 3
section is the source of truth for intent; this doc fills in current-repo
specifics).

## Goal

Replace `PodcastEpisodeController`'s hand-rolled Range/streaming logic with
`X-Accel-Redirect` to Caddy's `file_server`. Caddy handles Range/HEAD/If-Range
natively; PHP does auth + header selection only. This is where the historical
OOM-crash code path (`docs/plans` memory: `GeneratorFileStream`/streamed
1 MiB chunks) is deleted for podcast episodes specifically — the RoadRunner
version is already gone (Phase 1), but the app-level byte-range machinery
that used to feed it is still here and still hand-rolled.

## Current state (verified against post-pull main)

- `app/Broadcasts/PodcastEpisodeController.php`: does token auth + asset
  selection, then parses `Range` itself (`PodcastEpisodeByteRange`) and
  streams via a private `streamFile()` generator in 1 MiB chunks.
- `app/Broadcasts/Podcasts/PodcastEpisodeByteRange.php`: single-range RFC 7233
  parser (start-end, open-ended, suffix). Full delete target.
- `docker/Caddyfile`: has `php_server` + `mercure` block, no X-Accel wiring
  yet.
- `STASHD_MEDIA_PATH` — container path `/media` (`docker-compose.yml`,
  `docker-compose.dev.yml`), resolved into `StashdConfig::mediaPath` via
  `app/Config/stashd.config.php:20`. This is the root X-Accel-Mapping needs
  to expose internally.
- Tests: `tests/Feature/Phase5CPodcastEpisodeRouteTest.php` (859 lines) —
  large chunk is Range-header cases (mid/full/suffix/open/beyond-EOF/
  malformed/multi-range/bad-token). `tests/docker/smoke.sh` fetches an
  episode and checks body bytes + `Content-Length` + `Accept-Ranges` (no
  Range request in smoke today — Phase 3 adds one per the original plan).

## Work

1. **Caddyfile**: add `request_header X-Sendfile-Type x-accel-redirect`,
   an `X-Accel-Mapping` env-driven mapping from `STASHD_MEDIA_PATH` to an
   internal URI prefix (e.g. `/__media_internal/`), and an `intercept` block
   running `file_server` rooted at the mapped path, `internal` markable so
   it's unreachable directly.
2. **`PodcastEpisodeController::episode()`**: after selection + path/
   readability checks (keep as-is — token auth and non-revealing 404s are
   unchanged), return a bodyless `Ok` with `X-Accel-Redirect: /__media_internal/<relative path>`,
   `Content-Type`, `Content-Disposition`. Delete `streamFile()`, the
   `PodcastEpisodeByteRange` usage, HEAD special-casing (Caddy handles HEAD
   correctly against the redirected file), and the `RANGE_READ_CHUNK_BYTES`
   constant.
3. **Delete** `app/Broadcasts/Podcasts/PodcastEpisodeByteRange.php` and its
   dedicated tests (if any exist beyond the controller-level Range tests).
4. **Rewrite `Phase5CPodcastEpisodeRouteTest.php`**: assert auth behavior
   (unknown/cross-broadcast/revoked token → non-revealing 404, unchanged)
   and the emitted `X-Accel-Redirect`/`Content-Type`/`Content-Disposition`
   headers instead of streamed bytes and Range semantics — Range handling
   moves out of PHP's test surface entirely once Caddy owns it.
5. **`tests/docker/smoke.sh`**: add a real `curl -H "Range: bytes=0-1023"`
   against an episode URL asserting `206` + correct `Content-Range`,
   end-to-end through Caddy. This is the actual proof X-Accel-Redirect
   works, since the unit-level tests can no longer exercise Range at all.
6. **Local dev without Caddy in front** (lerd, `php tempest serve`): decide
   whether a fallback path is needed. Prefer none if lerd's local proxy can
   honor `X-Accel-Redirect` with a one-line internal location block;
   otherwise gate a direct-stream fallback behind an env check. Check
   lerd's site config before assuming either way.

## Non-goals / unchanged

- Token auth, non-revealing-404 behavior, path-token-not-query-token rule:
  untouched (`.claude/rules/security-tokens-secrets.md`).
- `PodcastEpisodeUrlBuilder`, feed XML generation: untouched.
- Video broadcasts / other media kinds using the same controller: same
  treatment, no kind-specific branching needed.

## Verification

- `composer test:static`, `composer test:feature -- --filter PodcastEpisode`.
- `composer test:docker-smoke` (or `:no-build` while iterating) — must show
  the new 206 Range assertion passing through actual Caddy, not PHP.
- Manual: play/seek a podcast episode in a real client against a Docker
  build.
