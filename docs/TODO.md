# Stashd implementation roadmap (foundation → v1)

## Code organization (complete)

- [x] Feature-first `app/` layout — see [docs/architecture/code-organization.md](architecture/code-organization.md)
- [x] Removed `app/Domain`, `app/Services`, `app/Infrastructure`, `app/Controllers`, `app/Bootstrap`
- [x] Consolidated broadcast + media-server command handlers; inlined broadcast lifecycle helpers
- [x] Renamed download/vault/stash/provider types (behavior, routes, API JSON unchanged)
- [x] Pest suite green after namespace migration (172 passed)

## Foundation (complete)

- [x] Tempest app boot + configuration
- [x] RoadRunner HTTP worker bridge
- [x] SQLite foundation schema (storage, commands, jobs, settings)
- [x] Storage root checks with hardlink/symlink probing
- [x] Fake provider for tests/dev
- [x] `/health` endpoint
- [x] Docker Compose + smoke test skeleton

## Phase 1 — Core domain persistence

**Status:** complete.

- [x] Add full v1 domain schema migration (`2026_06_17_create_domain_schema`)
  - Tables: `stashes`, `stash_inputs`, `stash_items`, `media_items`, `media_item_sources`, `raw_metadata_snapshots`, `assets`, `broadcasts`, `broadcast_items`, `broadcast_triggers`, `broadcast_trigger_runs`, `provider_accounts`, `media_server_connections`, `users`, `api_tokens`, `secrets`
  - Prefixed ULID primary keys, camelCase SQLite columns, inline SQLite foreign keys, indexes/uniques (incl. `unique(providerKey, providerItemId)` and `unique(stashId, mediaItemId)`)
  - Records/enums for all domain tables; repositories for stash, stash input, stash item, media item, broadcast (+ existing command/job/storage repos)
  - Migration/constraint/repository tests (`tests/Feature/DomainSchemaTest.php`)
- [x] Minimal `stash.preflight` command (fake provider only — no YouTube, no UI)
  - `POST /api/v1/stashes/preflight`, `GET /api/v1/stashes/preflight/{commandId}/review`
  - Creates command + job, stores preflight payload for a later review UI
  - Covered by `tests/Feature/StashPreflightTest.php` (requires auth after owner setup)
- [x] Explicit state transition service for command/job/stash/stash-input/stash-item/media-item entities
  - `StateTransitionService` + enum `allowedTransitions()` / `canTransitionTo()` rules
  - Covered by `tests/Unit/Services/State/StateTransitionRulesTest.php` and `tests/Feature/StateTransitionServiceTest.php`
- [x] Secrets service wrapper around Tempest signing key
  - `SecretsService` encrypt/decrypt/revoke/redact; `tests/Feature/SecretsServiceTest.php`
- [x] Single-owner auth (`users`, session login, API tokens, middleware)
  - `POST /api/v1/auth/setup`, login/logout, token CRUD, `RequireAuthMiddleware`
  - Public: `/health`, setup, login; protected API returns `setup_required` or `authentication_required`
  - Covered by `tests/Feature/AuthTest.php`

## Phase 2 — Command + job execution

**Status:** complete (hardening pass done).

- [x] Generic commands API (`POST /api/v1/commands`, `GET /api/v1/commands/{id}`, `GET /api/v1/jobs`, `GET /api/v1/jobs/{id}`)
  - Auth-protected, snake_case JSON in/out, validation, async dispatch (no sync long-running work)
  - `GET /api/v1/commands/{id}` returns command state, jobs array, and `result` when available
  - `POST /api/v1/stashes/preflight` delegates to the same command dispatch path
- [x] Command dispatcher + handlers (`stash.preflight`, `system.storage_check`)
- [x] Job handler registry + worker (`preflight`, `storage_check`, `boot`)
  - Claim pending jobs, heartbeat, progress, `lastError`, retry-safe stall recovery (120s)
- [x] Async fake-provider preflight (results in `commands.resultJson`)
- [x] Scheduler routine discovery (`stashd:scheduler-tick`, due `stash_inputs` with automatic sync)
- [x] Activity events (command/job lifecycle, preflight/storage completion, failures)
- [x] SSE notification channel (`GET /api/v1/events` polls `event_notifications`; publisher for `job.*` + `activity.created`)
  - Unauthenticated requests rejected; payloads secret-redacted; notifications are ephemeral (not source of truth)
- [x] API token prefix `stashd_pat_…` (distinct from entity IDs)
- [x] RoadRunner-style auth isolation (middleware `finally` clears `AuthContext`; bearer-only when `Authorization` header present; bearer precedence over session; revoked bearer rejected even with session)
- [x] `#[AllowApiClients]` route decorator skips `PreventCrossSiteRequestsMiddleware` for machine/API clients (curl, tokens)
- [x] RoadRunner bridge: no per-request `kernel->shutdown()`; `HttpRequestFailed` returns intended 4xx/5xx bodies
- [x] Caveat: owner `setup` authenticates in-process but session cookie is not reliably reusable on the next RR request — clients should call `login` (or use a bearer token) after setup
- [x] Covered by `tests/Feature/Phase2ExecutionTest.php`, `tests/Feature/Phase2HardeningTest.php`, updated `StashPreflightTest.php`

## Phase 3A — Provider layer (fake provider, no YouTube)

**Status:** complete.

- [x] Provider fixtures for CI (`tests/fixtures/providers/fake/`, `ProviderFixtureTest`)
- [x] Provider strategy selection by job intent + cost (`ProviderStrategySelector`; preflight picks cheapest discovery strategy)
- [x] Full fake-provider preflight → review → commit flow
  - Preflight stores full `discovered_items` in `commands.resultJson`
  - `GET /api/v1/stashes/preflight/{commandId}/review` exposes items for commit UI
- [x] Command type `stash.create_from_preflight`
  - Creates `stash`, `stash_input`, `media_items`, `media_item_sources`, `stash_items` from preflight results
  - Deduplicates `media_items` by `(providerKey, providerItemId)` at commit time
  - No downloads, vault writes, broadcasts, or YouTube
- [x] Covered by `tests/Unit/Services/Provider/ProviderStrategySelectorTest.php`, `tests/Feature/StashCreateFromPreflightTest.php`

## Phase 3B — YouTube provider

**Status:** complete (discovery + metadata boundary only — no downloads).

- [x] YouTube URL resolution (`@handle`, `/channel/UC…`, playlist, watch, youtu.be)
- [x] RSS discovery strategy (`youtube.rss`) — no API key, fixture-driven CI tests
- [x] Optional Data API metadata strategy (`youtube.data_api`) — skipped without `YOUTUBE_DATA_API_KEY`
- [x] ytdlphp download adapter boundary (`YouTubeYtdlpDownloadStrategy` + placeholder; no Vault/temp writes)
- [x] Provider capability split (`DiscoveryStrategyHandler`, `MetadataStrategyHandler`, `DownloadStrategyHandler`)
- [x] `ProviderStrategySelector` filters unavailable/last-resort strategies
- [x] Typed provider domain boundaries (per engineering spec — raw strings at I/O edges only)
  - `StashdUri` wraps `Tempest\Support\Uri\Uri` (`parse()`, `fake()`, path/query helpers)
  - `ProviderDates` parses/constructs `Tempest\DateTime\DateTime` (`tryParse()`, `utc()`)
  - `YouTubeUris` centralizes watch/feed/oembed/Data API URL construction
  - `DiscoveredItem` / `ResolvedInput` hold `StashdUri` + `DateTime`; serializers/repos emit `toString()` / `toRfc3339(useZ: true)` for JSON/DB
  - String manipulation via `Tempest\Support\str()` (trim, slug, path parsing) instead of raw PHP helpers
- [x] Wired into async `stash.preflight` + `stash.create_from_preflight` (media-item dedupe preserved)
- [x] Fixtures under `tests/fixtures/providers/youtube/`; no live YouTube in normal CI
- [x] Covered by `tests/Unit/Domain/Provider/YouTube/*`, `tests/Feature/YouTubeProviderTest.php`
- [x] Provider docs: `docs/providers/README.md` (includes typed-boundary conventions)
- [ ] Live provider tests behind `STASHD_LIVE_PROVIDER_TESTS=1` (optional)

## Phase 4A — Vault + downloads (fake downloader)

**Status:** complete + hardening pass (fake downloader only — no real ytdlphp).

- [x] `DownloaderInterface` + `DownloadRequest` / `DownloadResult` / `DownloadProbeResult`
- [x] `FakeDownloader` writes deterministic tiny files to temp (no shell/process calls)
- [x] `item.download` command + async download job (heartbeat, progress, activity, SSE)
- [x] Temp staging under `/media/temp/downloads/{jobId}` before any Vault write
- [x] Vault layout `{providerKey}/items/{providerItemId}/…` via `VaultPathBuilder` + `PathSanitizer`
- [x] `AtomicFileMover` (rename or copy+fsync); refuses to overwrite ready Vault originals
- [x] Asset rows for vault original, metadata JSON, source JSON, optional thumbnail
- [x] Media item state transitions through `StateTransitionService` (incl. `missing`)
- [x] Download policy enforcement (`metadata_only`, `manual_download`, `video`, `audio_only`)
- [x] Drift detection: `system.verify_vault`, `asset.verify` (missing vs checksum mismatch vs root unavailable)
- [x] API: `GET /api/v1/items/{id}`, `GET /api/v1/items/{id}/assets` (snake_case JSON)
- [x] Hardening: idempotency/retry rules, `force` → `download_force_not_supported`, batch ingest rollback, SHA-256 checksums, Vault sidecar JSON (`VaultSidecarBuilder`), path safety guards
- [x] Covered by `tests/Feature/ItemDownloadTest.php`, `tests/Feature/Phase4AHardeningTest.php`, `tests/Unit/Services/Download/*`, `tests/Unit/Services/Storage/PathSanitizerTest.php`, `tests/Unit/Services/Vault/*`
- [x] Docs: `docs/storage/README.md`, provider/downloader notes in `docs/providers/README.md`
- [x] Docker smoke: preflight → create-from-preflight → item.download → Vault file + asset ready

**Phase 4B gate:** do not wire real ytdlphp until 4A hardening is green.

## Phase 4B — Real ytdlphp downloads

**Status:** complete.

- [x] `composer require hazel/ytdlphp` ([hipsterjazzbo/ytdlphp](https://github.com/hipsterjazzbo/ytdlphp))
- [x] `YtdlpConfig` + env: `STASHD_YTDLP_BINARY`, `STASHD_YTDLP_TIMEOUT`, `STASHD_REAL_DOWNLOADS_ENABLED`
- [x] `YtdlpGateway` / `YtdlpGatewayImpl` — only Stashd boundary to ytdlphp (no direct process calls)
- [x] `StubYtdlpGateway` for tests/CI (no network)
- [x] `YtdlpDownloader` + `YtdlpOptionsBuilder` (video 1080p merge, audio mp3 128k)
- [x] `DelegatingDownloader` — fake provider → `FakeDownloader`; others → ytdlphp when enabled
- [x] `YouTubeYtdlpDownloadStrategy` for YouTube provider strategy registry
- [x] Phase 4A pipeline unchanged: temp → batch ingest → checksum → Vault → sidecars
- [x] Tests: `tests/Unit/Services/Download/YtdlpDownloaderTest.php`, `tests/Feature/YtdlpDownloadTest.php`
- [x] Opt-in live probe test: `STASHD_LIVE_DOWNLOAD_TESTS=1` (`tests/Feature/LiveYtdlpDownloadTest.php`)
- [x] Docker: yt-dlp + ffmpeg + PHP `uri` extension; smoke still uses fake provider
- [x] Docker build: PHP 8.5 bundled `uri` extension verified without `docker-php-ext-install uri`
- [x] Docs updated (`docs/providers/README.md`, `docs/storage/README.md`)

## Phase 5 — Broadcasts

### Phase 5A — Broadcast engine + hardlink-first filesystem publishing (complete)

- [x] `BroadcastFormat` interface + `BroadcastTypeRegistry`
- [x] Lifecycle service: `BroadcastLifecycleService` (plan/publish/verify/prune inlined)
- [x] `filesystem_series` broadcast type (hardlinked media under `/media/broadcasts/{broadcastId}/`)
- [x] Hardlink-first publish via `HardlinkPublisher` — no silent copy; `broadcast_hardlink_unavailable` on failure
- [x] Async commands: `broadcast.plan`, `broadcast.rebuild`, `broadcast.verify`, `broadcast.prune`
- [x] API: stash/broadcast list/create/show/items + commands via `POST /api/v1/commands`
- [x] Activity/SSE events for broadcast lifecycle
- [x] State transitions via `StateTransitionService` (broadcast + broadcast item)
- [x] Tests: `tests/Feature/Phase5ABroadcastTest.php`, `tests/Unit/Services/Broadcast/BroadcastEngineTest.php`
- [x] Docker smoke: create broadcast → rebuild → hardlink exists → restart → verify
- [x] Docs: `docs/broadcasts/README.md`, storage hardlink policy notes

### Phase 5B — Jellyfin/Plex filesystem broadcasts + scan triggers (complete)

- [x] `jellyfin_series` and `plex_series` broadcast types (shared series engine, distinct type keys)
- [x] Media-server-friendly layout (`Season 01/`, `S01E001 - Title.ext`) + minimal NFO sidecars
- [x] Hardlink-first publish unchanged; optional poster hardlink when safe
- [x] `media_server_connections` CRUD API + token storage via `SecretsService` (`token_secret_id` → `secrets.id`)
- [x] Small Stashd-owned HTTP clients: `JellyfinMediaServerClient`, `PlexMediaServerClient` (no broad PHP libraries)
- [x] Commands: `broadcast.trigger`, `media_server.test_connection`, `media_server.list_libraries`
- [x] Scan triggers separate from broadcast validity (`broadcast_trigger_runs`; failures do not invalidate files)
- [x] Activity/SSE: `broadcast.trigger_succeeded`, `broadcast.trigger_failed`, `media_server.test_completed`
- [x] Tests: `tests/Feature/Phase5BMediaServerTest.php`, fixture HTTP clients under `tests/fixtures/media_servers/http/`
- [x] Docker smoke: `jellyfin_series` rebuild + NFO sidecar check (fake — no real Jellyfin/Plex containers)
- [x] Docs: `docs/broadcasts/README.md`, `docs/media-servers/README.md`
- [ ] Optional live media-server tests: `STASHD_LIVE_MEDIA_SERVER_TESTS=1`

### Phase 5C — Private podcast broadcasts (complete — transcode/remux deferred)

- [x] Podcast feed and item token foundation with encrypted storage
- [x] Authenticated podcast feed URL exposure
- [x] `broadcast.rotate_token` for podcast broadcasts
- [x] `audio_podcast` and `video_podcast` broadcast format registration
- [x] Deterministic minimal RSS podcast feed generation to `/media/broadcasts/{broadcastId}/feed.xml`
- [x] Tokenized enclosure URL shape (`/b/{broadcastToken}/items/{itemToken}/episode.{ext}`) in generated feeds
- [x] Audio/video podcast asset selection from ready Vault assets with stable unavailable errors
- [x] Public `GET /b/{broadcastToken}/feed.xml` route
  - Unauthenticated by design (route opts out of `RequireAuthMiddleware`); anyone with the feed URL can read the feed
  - Resolves the raw feed token to its podcast broadcast via `PodcastTokenService::findPodcastBroadcastByFeedToken` (decrypt candidates, `hash_equals`)
  - Serves the generated `feed.xml`; never rebuilds synchronously
  - Non-revealing 404 for unknown/revoked/rotated-old tokens, non-podcast broadcasts, and missing feeds
  - Token rotation (`broadcast.rotate_token`) invalidates old feed URLs
  - Covered by `tests/Feature/Phase5CPodcastFeedRouteTest.php`
- [x] Public tokenized episode media route
  - `GET /b/{broadcastToken}/items/{itemToken}/episode.{ext}` (unauthenticated by design, same convention as the feed route)
  - Resolves the broadcast token via `PodcastTokenService::findPodcastBroadcastByFeedToken`, then the item token via the new `PodcastTokenService::findBroadcastItemByEpisodeToken` (decrypt candidates scoped to that broadcast's items, `hash_equals`)
  - Serves the Vault asset already selected by `PodcastAssetSelector` (audio for `audio_podcast`, video for `video_podcast`) — never an arbitrary filesystem path, never transcoded/remuxed
  - `{ext}` is a presentation hint only; a mismatch against the selected asset's own extension is a non-revealing 404
  - Non-revealing 404 for unknown/cross-broadcast/revoked tokens, non-podcast broadcasts, unavailable/unreadable assets, and extension mismatches
  - Covered by `tests/Feature/Phase5CPodcastEpisodeRouteTest.php`
- [x] Range request support for episode media
  - Single-range `Range: bytes=...` requests only (`N-M`, `N-`, `-N` suffix forms); multi-range and syntactically invalid ranges are treated as absent (full file, 200)
  - Parsed/validated by `PodcastEpisodeByteRange` (pure value object, no I/O); satisfiable ranges are read via chunked `fopen`/`fseek`/`fread`, never a full `file_get_contents()`
  - Satisfiable range → `206 Partial Content` with `Content-Range`/`Content-Length`; unsatisfiable (start beyond EOF) → `416 Range Not Satisfiable` with `Content-Range: bytes */{total}`
  - No-`Range` requests now also advertise `Accept-Ranges: bytes`
  - No resumable/`multipart/byteranges` streaming — out of scope for this slice
  - Covered by `tests/Feature/Phase5CPodcastEpisodeRouteTest.php`
- [ ] Transcode/remux broadcast policies (deferred — separate future initiative, not blocking Phase 5C; requires explicit sign-off per `AGENTS.md`'s "ask before enabling copies/transcoding by default")

## Phase 6 — API + UI

- [ ] OpenAPI-documented `/api/v1` resources
- [ ] Glance-inspired dashboard UI consuming public API only
- [ ] Job progress UI via SSE

## Phase 7 — Release hardening

- [x] Docker smoke: boot, port 8474, `/health`, storage layout, restart persistence (`tests/docker/smoke.sh`)
- [x] Docker smoke: Phase 2 schema tables (`activity_events`, `event_notifications`) on fresh boot
- [x] Docker smoke: supervisord worker + scheduler + roadrunner process health
- [x] Docker smoke: authenticated `/api/v1/system/health` with `vault_broadcast_hardlink` on bind mounts
- [x] Pest suite green (172 tests: … Phase 5B Jellyfin/Plex broadcasts + scan triggers)
- [x] Docker smoke: fake-provider preflight → create-from-preflight end-to-end
- [x] Docker smoke: fake-provider download → temp → Vault → asset ready (+ restart persistence)
- [x] Docker smoke: filesystem broadcast create → rebuild → verify after restart
- [x] Docker smoke: jellyfin_series broadcast rebuild + tvshow.nfo sidecar (fake provider, no live Jellyfin)
- [x] Docker smoke: audio_podcast rebuild + public feed.xml + public episode route (full fetch, `Range` request → 206, unknown item token → 404)
- [x] Docker smoke docs: first-run and no-build reuse workflow (`docs/runtime/docker-smoke.md`)
- [ ] Multi-arch image build
- [ ] Static analysis + filesystem integration tests
