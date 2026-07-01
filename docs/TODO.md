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
- [x] Conservative description-based podcast funding-link detection
  - `PodcastFundingLinkDetector` scans included episode/media item descriptions for Patreon, Ko-fi, GitHub Sponsors, and Buy Me a Coffee links only
  - Manual `settings['funding_url']` always wins; detection only runs when it is absent/blank
  - Only descriptions of items actually included in the rebuilt feed are scanned (hidden/failed/excluded items are not)
  - Covered by `tests/Unit/Broadcasts/Podcasts/PodcastFundingLinkDetectorTest.php` and `tests/Feature/Phase5CPodcastFeedTest.php`
  - Deferred: channel/about-page scraping, YouTube membership, Nebula, creator-website, merch-store, and Substack/Open Collective detection, and the "first plausible link" fallback from the engineering spec's full priority list
- [x] Unified `audio_podcast`/`video_podcast` into a single `BroadcastType::Podcast` with a per-broadcast `settings.media_kind` (`audio`|`video`, defaults to audio) — `AudioPodcastBroadcast`/`VideoPodcastBroadcast` (two ~25-line subclasses differing only in which selector method/error code they used) deleted; `PodcastBroadcastFormat` is now the sole concrete implementation. Every downstream `in_array($type, [AudioPodcast, VideoPodcast])`/`type = ? OR type = ?` collapsed to one check. No DB migration — no existing users/data.
- [x] Audio transcode fallback: a podcast configured for audio with only a ready video Vault original (e.g. a `video`-policy stash) no longer fails outright — `PodcastTranscodeFallback` automatically queues an ffmpeg-generated MP3 audio asset (`AssetRole::PodcastAudio`, 128kbps stereo, the spec's stated v1 default) and the affected broadcast auto-rebuilds once it's ready. New `app/Transcoding/` domain + `FfmpegGateway` boundary (mirrors `YtdlpGateway`, built directly on `Tempest\Process` since ffmpeg has no `ytdlphp`-equivalent wrapper). Dispatched as a real command (`CommandType::AssetTranscodePodcastAudio`), not a bare job, matching the scheduler's own precedent. See `docs/broadcasts/README.md`'s "Audio transcode fallback" section for the full design.
  - Finished wiring `progressRate`/`progressEtaSeconds` — `JobRecord` columns that existed but were never set by `JobWorkerService::progress()` nor serialized by `EventPublisher::jobProgress()`/`JobResource`. New `JobProgressUpdate` value object (`ofSteps()`/`ofPercent()`) replaces the old `progress(current, total, label)` signature everywhere (7 handler files migrated); this is the first job needing true percent+ETA progress rather than discrete steps.
  - Covered by `tests/Feature/PodcastTranscodeFallbackTest.php`, `tests/Feature/TranscodePodcastAudioAssetTest.php`, the fallback scenario in `tests/Feature/Phase5CPodcastFeedTest.php`, and `tests/Unit/Domain/Jobs/JobProgressUpdateTest.php`. Opt-in live test: `STASHD_LIVE_FFMPEG_TESTS=1` (`tests/Feature/LiveFfmpegTranscodeTest.php`) — confirmed ffmpeg is present in the runtime container, but live-toggling this specific opt-in flag wasn't exercised end-to-end in the session that built this.
- [ ] Video transcode/remux broadcast policies (still deferred — separate future initiative, a v1 non-goal; requires explicit sign-off per `AGENTS.md`'s "ask before enabling copies/transcoding by default")
  - `stashes.videoQualityProfileId`/`audioQualityProfileId` columns exist but are unused — only meaningful once this work exists
  - `BroadcastPlan::estimatedCopyBytes` stays hardcoded at `0` until generated-media (podcast audio/video) work exists; `0` is already correct for hardlink-only broadcast types in the meantime

### Phase 5D — Broadcast plugin architecture migration (complete, in-tree; Composer packaging deferred)

Full design/status doc: `docs/architecture/Broadcast-Plugin-Architecture-Plan.md`.

- [x] `BroadcastType` enum, `BroadcastTypeRegistry`, `BroadcastFormat` interface, `AbstractSeriesBroadcastType`, `app/Broadcasts/Formats/` deleted; replaced by `BroadcastPlugin` interface + `#[StashdBroadcast]` attribute discovery (`BroadcastPluginDiscoverer` → `BroadcastPluginRegistry`) — broadcast keys are now plain strings (`filesystem`, `jellyfin`, `plex`, `podcast`), not enum cases
  - Plugins stay in-tree under `app/Broadcasts/Plugins/` (`AbstractSeriesBroadcastPlugin` + `Filesystem`/`Jellyfin`/`Plex`/`PodcastBroadcastPlugin`) — the plan's `stashd/plugin-podcast`/`stashd/plugin-media-server` Composer packages are deferred, not scheduled, per `AGENTS.md`'s "v1 ships no plugin runtime"
- [x] `GET /api/v1/broadcast-plugins` — lists discovered plugins (key/label/description/supported_file_kinds/ui_controls); the stash-detail create-broadcast dropdown and the season-mapping series check (`BroadcastController::isSeriesBroadcast()`) both now derive from this instead of a hardcoded type list
- [x] Fixed the migration's incomplete follow-through, caught via full-suite regression (98 → 0 failures across several sessions): stale `filesystem_series`/`jellyfin_series`/`plex_series` keys left in the frontend and three test files after the key rename; `BroadcastLifecycleService`'s six lifecycle methods retyped to `string` without updating their only callers (still `PrefixedUlid`); `BroadcastTriggerService::execute()` similarly mismatched; `AssetRepository`/`BroadcastItemRepository` left strictly `PrefixedUlid`-typed while the new plugin code calls them with plain strings; a missing `PrefixedUlid` import in `BroadcastContextFactory`; and stray `->toString()` calls on already-string values in `AbstractSeriesBroadcastPlugin`
- [x] Deleted the gutted `BroadcastTypeTest.php` stub (`expect(true)->toBeTrue()` placeholders); the policy matrix it once covered is fully exercised by `BroadcastPolicyMismatchTest`

## Phase 6 — API + UI

**Status:** complete. Slices 1-6 shipped (toolchain/shell, auth, Dashboard/Activity,
Stashes/Vault, Create Stash + Settings, and the Slice 6 refinement/multi-input pass below).

### Slice 1 — Front-end toolchain + dashboard shell (complete — `50158bb`)

- [x] Vite + Tailwind v4 + Alpine.js toolchain (`package.json`, `vite.config.ts`, `src/main.entrypoint.{ts,css}`)
- [x] `.rr.yaml` `http.static` serving `public/`; `num_workers` 2 → 4 for SSE headroom (since raised again, see below)
- [x] Dockerfile Node build stage producing `public/build/`
- [x] `app/Http/Ui/Views/x-stashd-layout.view.php` — wordmark, top nav, inline favicon tile

### Slice 2 — Auth: login page + session cookie + logout (complete — `24105cb`)

- [x] `__web_session__` rotating token + `stashd_session` HttpOnly cookie (`app/Auth/AuthService.php`, `AuthController.php`)
- [x] Dropped the fallback to Tempest's native Authenticator/Session — it's a container singleton living for the life of a RoadRunner worker, so one user's session was leaking into every other request that worker served afterward
- [x] `app/Http/Ui/UiController.php` + view shells for every nav destination, all opting out of `RequireAuthMiddleware` via `without:`

### Slice 3 — Dashboard + Activity (read-only, live) (complete — `2dcebbd`)

- [x] Dashboard (`GET /`): status cards from `GET /api/v1/system/health`, jobs, stash/vault counts
- [x] Activity (`GET /activity`): live feed from `GET /api/v1/events` (SSE) via Alpine + native `EventSource`
- [x] Shared `apiFetch()` / status-badge / formatter helpers in `src/main.entrypoint.ts`

### Slice 4 — Stashes + Vault (read + drill-down) (complete — `03b6265`)

- [x] `GET /api/v1/stashes`, `/{id}`, `/{id}/items`, `/{id}/inputs`, `GET /api/v1/items` (closed a documented spec §27 gap — these endpoints didn't exist before this slice)
- [x] Stash detail: inputs/items/broadcasts, rebuild/verify/prune/rotate_token actions, copyable podcast feed URL
- [x] Vault detail: assets list; `Asset.derivedFromAssetId` exposed as an honest partial "Explain Generated Files" (see below — `canRegenerate`/`safeToDelete` still not modelled)

### Slice 5 — Create Stash flow + Settings + broadcast creation (complete)

- [x] Create Stash (`GET /stashes/new`): paste-URL → `stash.preflight` → review (item count, total duration) → configure (name, download policy prominent; sync mode/organization mode/slug/description under a collapsed "advanced options" disclosure) → `stash.create_from_preflight` → redirect to the new stash. No disk-size/impact-warning estimates — backend doesn't compute them yet (spec-only).
- [x] Settings (`GET /settings`): API tokens (create-once display, list, revoke), media-server connections (create/list/test/delete), and read-only system/storage info, all against already-existing endpoints — no backend changes needed.
- [x] Broadcast creation: Stash detail gained a minimal "new broadcast" form (type + name) — there was previously no UI anywhere to create one, only to act on existing ones. Found during this slice's planning, not in the original scope.

### Slice 6 – Refinement, correctness bugs & multi-input reframe (complete)

Full task breakdown (T1-T20) in `docs/plans/phase-6-slice-6/plan.md`; `docs/plans/phase-6-slice-6/tasks/` has one prompt per task.

- [x] Stashes need an edit page — `PATCH`/`DELETE /api/v1/stashes/{id}` + a delete-impact preview (shared vs. orphaned items); Edit/Delete UI on both the stash list and the detail header rather than a dedicated page (T4, `8fbbcba`)
- [x] Detailed item list + live processing status — items endpoint returns each item's media-item summary (thumbnail, title, duration, content type) and on-disk asset size in one call; ignored items shown distinctly with their filter reason; an actively downloading item shows a live progress bar (T11, `67a4aa1`)
- [x] Broadcast/metadata-only mismatch — generalized to `BroadcastType::isSatisfiedByDownloadPolicy()` (`metadata_only` satisfies no media broadcast; `audio_only` doesn't satisfy `video_podcast`); warns and offers an inline policy picker, never blocks (T10, `31c8c92`)
- [x] YouTube 15-item cap — `YouTubeDataApiDiscoveryStrategy` pages the Data API for the full channel once a key is configured, tagging each item's video type (regular/short/live/premiere); RSS behaviour unchanged without a key (T5, `10b06e8`)
  - [x] Settings section for the YouTube API key — `SecretsService`-backed with an env fallback; set/replace without a restart; key never echoed back (T6, `10b06e8`)
- [x] Activity page closing payload disclosures on refresh — `activityComponent` now tracks expanded-event ids explicitly instead of relying on `<details>`'s native open state, which the wholesale SSE re-render was discarding (T15, `747318e`)
- [x] Stash name defaults to channel/playlist name — channel-handle resolution returns the real owner's display name (same fix as the wrong-channel bug below) (T2, `8fbbcba`)
- [x] Slug ordinal-suffix fallback — duplicate slugs now yield `foo`, `foo-1`, `foo-2`, … instead of erroring (T3, `8fbbcba`)
- [x] Animated background-activity indicator — one shared pulse-dot class applied everywhere something is happening now (loading states, in-flight buttons, active job rows, SSE "connecting…") (T14, `e0eaf78`)
- [x] Downloads not firing on stash creation — eligible stash items now auto-enqueue `item.download` at the end of input-commit when the stash's download policy allows it (T1, `8fbbcba`)
- [x] No way to delete a stash — see edit/delete above (T4, `8fbbcba`)
- [x] Channel avatar as stash icon — `icon_uri` is populated from the resolved input's avatar at commit and rendered in the stashes list and detail header (T16, `e0eaf78`)
- [x] Dashboard refinement — Recent Jobs table removed; new `GET /api/v1/activity`-backed media-activity summary; per-location + total disk usage surfaced; real Stashes & Vault counts (T13, `67bf001`)
- [x] Multi-input stash workflow — atomic creation split into `POST /stashes` (empty) + `POST /stashes/{id}/inputs` (add input); `stash.create_from_preflight` retired in favour of a stash-agnostic preflight + per-stash commit; a stash can now hold inputs from multiple sources with dedup preserved (T8/T9, `68024de`/`eae9171`); optional input → broadcast season mapping lives in `BroadcastRecord.settingsJson` (T20, `ca11dd7`)
- [x] Vault/media-item detail page was sparse — added thumbnail, full metadata, "in these stashes"/"in these broadcasts" membership (new `GET /api/v1/items/{id}/stashes` + `/broadcasts`), download/processing status (T12, `613475b`)
- [x] HUGE ISSUE: wrong-channel discovery — `YouTubeChannelIdResolver` now resolves the owner id via canonical link / `og:url` / header `externalId` only, with no first-`channelId` fallback; better to show no identity than a wrong one (T2, `8fbbcba`)
- [x] Create-stash flow missing shorts/live-vod toggle — two filter tiers, both per-input: a universal title-regex include/exclude, plus provider-declared options (YouTube channels: `include_shorts`/`include_live`); excluded items land in `ignored` state with a specific reason, retained rather than deleted (T7, `e0286ab`)
- [x] OpenAPI-documented `/api/v1` resources — `docs/openapi.yaml`, written from actual `Resource::toArray()` output; a Pest test asserts every discovered route has a matching `paths` entry so it can't silently drift (T17, `e60d2ee`)
- [x] True low-latency SSE streaming over RoadRunner — `GeneratorEventStream` adapts the poll-loop generator to a PSR-7 `StreamInterface` (`PSR7Worker::chunkSize`), so events deliver incrementally instead of bursting after `MAX_ITERATIONS` (T18, `18f6daa`)
  - **Worker-pinning itself is not eliminated.** RoadRunner is one-process-one-request, so streaming changes *when* bytes reach the client, not *whether the worker is free* for the duration — that part of the original acceptance bar was never achievable via streaming alone. The open-tab pool-exhaustion problem below is instead now *bounded*: a `sse_connections` table caps concurrent `/api/v1/events` connections at 4 of 8 workers; rejected connections get a `retry-after` SSE message and `EventSource`'s own reconnect handles the rest.
  - **Known sharp edge, hit twice (superseded by the connection cap above):** because the worker is held for the whole loop regardless of client disconnects (confirmed via a logged `broken pipe` at `elapsed: 30148ms`), every page that subscribes to `/api/v1/events` tied up one RoadRunner worker for ~`MAX_ITERATIONS` seconds out of every `MAX_ITERATIONS + ~3`s reconnect cycle, for as long as that page stayed open. `.rr.yaml`'s `pool.num_workers` was bumped once (2 → 4) for this; Slice 4 adding a third SSE-subscribing page (Stash detail) used up that headroom, and with all workers busy, new page navigations (including the auth check) had no worker free to run on — intermittently bouncing users to `/login` in a way that looked like a recurrence of the SQLite `busy_timeout` bug (`b841ea7`) but was actually worker-pool exhaustion, a different mechanism with the same symptom. Mitigated 2026-06-20 by dropping `MAX_ITERATIONS` 30 → 10 and raising `num_workers` 4 → 8; the hard cap above is the real fix for the exhaustion scenario.
  - Slice 5's bounded `awaitSseTerminal` consumer (Create Stash wizard) was unaffected either way — a short, explicitly-`.close()`d subscription, not a perpetual one.
- [x] "Explain Generated Files" asset metadata — `AssetRegenerationGuidance` classifies assets as source vs. generated from the existing `broadcastId`/`derivedFromAssetId` columns (no new schema); `canRegenerate`/`safeToDelete`/`generatedBy` are now modelled and shown in the Vault item detail UI (T19, `35d88f4`)
- [x] Stale branding doc pointer — fixed 2026-06-20: `AGENTS.md` and `x-stashd-layout.view.php`'s doc comment both already named the right canonical path (`docs/Stashd-Branding-Plan.md`); the staleness was in that file's *content*, which still described pre-rebrand terminology (Mirror/Collection/Destination — explicitly disavowed by the current direction). Promoted the current content from `docs/stashd-design-assets-phase6/docs/Stashd-Branding-Plan-2026-06-16.md` into the canonical path; the dated copy and its asset bundle are left untouched as the historical design-handoff record (per `ASSET-MANIFEST.md`). Carried over from the Slice 1 plan's sign-off list.

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
- [x] Docker entrypoint: auto-generate and persist `SIGNING_KEY` under `/data/.env` on first boot, symlinked into the app root (`docker/entrypoint.sh` `ensure_signing_key`); operator-supplied `SIGNING_KEY` env var takes precedence and skips generation; verified unchanged across restart, full container recreation, and the override path (`tests/docker/smoke.sh`)
- [ ] Multi-arch image build
- [ ] Static analysis + filesystem integration tests
- [ ] Tech debt: unused `JobIntent`/`CommandType` enum cases (`RoutineDiscovery`, `MetadataCapture`, `MetadataRefresh`, `Repair`, `Enrich` / `StashSync`, `StashBackfill`, `ItemRefreshMetadata`, `SystemPruneTemp`) — scaffolding from an earlier design pass; the underlying capabilities already work via other plumbing (scheduler + `Preflight` job, provider strategy pattern). `JobIntent::InitialBackfill` is no longer in this list — Phase 6 Slice 6's add-input pipeline wired it up to drive full-channel discovery at commit (`eae9171`). Revisit the rest: remove or wire up.
- [ ] Tech debt: `RawMetadataSnapshotRecord` table/record exists but is never instantiated — scaffolding for a not-yet-scoped provenance feature.
- [ ] Max concurrent downloads is not explicitly enforced in code — naturally satisfied today by the single serial `worker` process; revisit if workers are ever scaled beyond one.
- [ ] Tech debt: no full-channel discovery fallback when no YouTube Data API key is configured —
  `YouTubeProvider::discoveryStrategies()` only registers RSS (`StrategyCost::Low`, ~15-item RSS-feed
  ceiling) and Data API (`StrategyCost::Medium`, gated on `hasKey()`); without a key, RSS is the only
  option `ProviderStrategySelector` can ever pick. yt-dlp can already enumerate a full channel
  (`YtDlp::extractAll()`/`extractPlaylist()` in `vendor/hazel/ytdlphp`) but `App\Downloads\Ytdlp\YtdlpGateway`
  only exposes `extractInfo()` (single video) — would need a new gateway method plus a
  `DiscoveryStrategyHandler` implementation (mirror `YouTubeRssDiscoveryStrategy`), registered with a
  cost above RSS's `Low` and gated on yt-dlp binary availability (mirror
  `YouTubeYtdlpDownloadStrategy::isAvailable()`'s `realDownloadsEnabled() && probe()->available` check).
  `StrategySelectionOptions::preferHighestCapability` is already `true` for `JobIntent::InitialBackfill`
  (`app/Stashes/DiscoverStashInput.php`), so a higher-cost strategy would be auto-preferred for
  full-backfill commits with zero selector changes. No existing fixtures for ytdlp-shaped discovery
  output (existing ytdlp tests stub `VideoInfo` objects directly, not fixture files) — would need new ones.

## Phase 8 — Stronger typing & Tempest-native refactor (ongoing, not gating v1)

Driven by AGENTS.md's "use Tempest-native facilities by default" preference. Full ready-to-run
prompts for each unstarted slice below live in `docs/plans/stronger-record-types.md`; the relations
spike is `docs/plans/tempest-relations-review.md`. Both docs stay as deep reference — this section is
the live status tracker, so "what's left" never again needs a tour of `docs/plans/`.

- [x] Timestamp properties → `Tempest\DateTime\DateTime` — `RecordTimestamps` removed entirely;
  callers (auth expiry, NFO dates, job stale checks, scheduler `nextCheckAt`, podcast feed dates)
  converted off `strtotime()`/`substr()`/`gmdate()` arithmetic (`379f607`)
- [x] Stable JSON properties (first batch) — `ApiTokenRecord::$scopesJson` → `ApiTokenScopes`
  (`a1c16c7`); `MediaServerConnectionRecord::$settingsJson` → `MediaServerLibrarySelection`
  (`1591102`); `BroadcastTriggerRecord::$settingsJson` → `MediaServerScanTriggerSettings`
  (`36980eb`)
  - Intentionally deferred, not outstanding (polymorphic by type, not a stable shape):
    `CommandRecord::$optionsJson`/`$resultJson`, `JobRecord::$payloadJson`,
    `ActivityEventRecord::$metadataJson`, `EventNotificationRecord::$payloadJson`,
    `RawMetadataSnapshotRecord::$rawJson`, `SecretRecord::$metadataJson`
- [ ] Entity Identity References — typed ID value objects (`StashId`, `MediaItemId`, `BroadcastId`, …)
  replacing raw `PrefixedUlid`/string at boundaries; pass loaded records instead of IDs where the
  caller already has them, keep raw strings only at HTTP/DB/serialization boundaries. Split by
  domain (auth / stashes-vault / broadcasts / jobs-commands / system-storage-activity) if too large
  for one pass.
- [ ] URL & Filesystem Path Values — rename `StashdUri` → `StashdUrl`, move `fake://` support out of
  production URL handling into fake-provider-only URL classes, add YouTube-specific URL classes
  (`YouTubeChannelUrl`/`YouTubeVideoUrl`/`YouTubePlaylistUrl`) behind a marshaller, and introduce
  filesystem path value objects kept separate from URLs (vault/broadcast/storage/temp-download paths)
- [ ] Sensitive Record Properties — `#[Hidden]` guardrails on `UserRecord::$passwordHash`,
  `ApiTokenRecord::$tokenHash`, `SecretRecord::$encryptedValue`/`$nonce`, `*::$tokenSecretId`
  columns, etc. — a guardrail against accidental leaks, not a replacement for explicit Resource DTOs
- [ ] Semantic Scalar Values — `Tempest\DateTime\Duration` for `durationSeconds`/`progressEtaSeconds`,
  a `ByteSize` value object for `sizeBytes`/`freeBytes`/`totalBytes`, enums for bounded strings like
  `StorageLocationRecord::$role`
- [ ] Later Encryption Annotation Review — investigate `#[Encrypted]` as a future `SecretsService`
  simplification; deliberately not combined with the `#[Hidden]` slice above
- [ ] Later Naming Review — audit whether `*Record` should remain the persistence-marker suffix
  everywhere, after the stronger-typing slices land
- [ ] Later Discovery Review — investigate Tempest custom discovery for provider / command-handler /
  job-handler / broadcast-format / media-server-client registries, replacing today's manually-curated
  initializer registries
- [ ] Tempest relations audit (`docs/plans/tempest-relations-review.md`) — spike on whether
  `BelongsTo`/`HasMany`/`with(...)`/`$record->query('relation')` should replace explicit repository
  loading anywhere (stash/stash-item, broadcast/broadcast-item, vault assets, jobs/commands,
  auth/tokens); audit and recommendation only, no relations adopted yet

## Future companion apps (not started, not part of this v1 roadmap)

- [ ] Browser extension — full draft spec at `docs/Stashd-Browser-Extension-Spec.md` ("click Stashd
  → preflight/create against the same public API the web UI uses"); no code exists yet, no v1
  roadmap phase assigned. Slice 6 confirmed the web UI deliberately dogfoods the same `/api/v1`
  endpoints this spec calls for, so the API side is unintentionally ready whenever this gets picked up.
