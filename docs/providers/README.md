# Providers

Stashd providers resolve stash inputs, discover media items, enrich metadata, and (when enabled) download media through typed strategy handlers.

| Capability | Interface / adapter | Phase |
|---|---|---|
| Discovery | `DiscoveryStrategyHandler` | 3A+ |
| Metadata | `MetadataStrategyHandler` | 3B+ |
| Download | `DownloadStrategyHandler` / `YtdlpDownloadAdapter` | 3B boundary, 4B implementation |

`ProviderStrategySelector` picks the lowest-cost available strategy per purpose. Last-resort strategies (e.g. ytdlp) are excluded unless explicitly allowed.

## Fake provider

Key: `fake`

URIs: `fake://channel/{name}`, `fake://playlist/{name}`, `fake://item/{id}`

| Strategy | Key | Cost |
|---|---|---|
| Discovery | `fake.feed` | Low |

Used for tests, local development, and Docker smoke. Downloads use `FakeDownloader` (never ytdlphp).

Fixtures: `tests/fixtures/providers/fake/`

## YouTube provider

Key: `youtube`

Supports channel handles, `/channel/UC…`, playlists, watch URLs, and `youtu.be` links.

| Strategy | Key | Cost | Notes |
|---|---|---|---|
| Discovery | `youtube.rss` | Low | No API key; RSS/Atom fixtures in CI |
| Metadata | `youtube.data_api` | Medium | Requires `YOUTUBE_DATA_API_KEY` |
| Download | `youtube.ytdlp` | Last resort | Real downloads on by default outside tests (`STASHD_REAL_DOWNLOADS_ENABLED`) + yt-dlp |

### Discovery (RSS)

Strategy key: `youtube.rss`

Uses public RSS feeds where possible. CI tests bind `FixtureProviderHttpClient` to fixture HTTP responses under `tests/fixtures/providers/youtube/http/`.

### Metadata (Data API)

Strategy key: `youtube.data_api`

Optional enrichment via YouTube Data API when `YOUTUBE_DATA_API_KEY` is configured. Skipped automatically when the key is absent.

### Download (ytdlp via ytdlphp)

Strategy key: `youtube.ytdlp`

Package: [`hazel/ytdlphp`](https://github.com/hipsterjazzbo/ytdlphp) `^1.0.2`, resolved from its VCS repository (see `composer.json`). Update it with `composer update hazel/ytdlphp`; no local sibling checkout or path repository is required.

All yt-dlp interaction **must** go through ytdlphp (`Ytdlphp\YtDlp`, `Ytdlphp\Options`) behind `YtdlpGateway`. Stashd must not call `shell_exec`, `exec`, `proc_open`, or Symfony/Tempest Process for yt-dlp directly.

Phase 4B ships `YtdlphpDownloadAdapter` + `YtdlpDownloader`:

- Provider strategy probe reports binary/version unless `STASHD_REAL_DOWNLOADS_ENABLED=0` (real downloads are on by default outside `ENVIRONMENT=testing`)
- Downloads: `RoutingDownloader` → `YtdlpDownloader` → `YtdlpGateway` → ytdlphp → temp staging → existing `DownloadExecutor` Vault ingest
- Normal CI uses `StubYtdlpGateway` (no network)
- **Live download progress**: `YtDlp::download()` accepts an optional `$onProgress` callback (ytdlphp's own feature, added alongside this) — when given, it runs the process via `--progress-template` + `--newline` instead of the default blocking call, parsing yt-dlp's own progress fields (`downloaded_bytes`/`total_bytes`/`eta`/`speed`) into `Ytdlphp\DownloadProgress`. `DownloaderInterface::download()`, `YtdlpGateway::download()`, and `DownloadMediaItem::execute()` all thread this callback through; `DownloadJobHandler` forwards it into `JobProgressUpdate::ofPercent()` (throttled to ~1/sec, final update always forwarded) and calls `$context->heartbeat($job)` on the same cadence — the first real per-second heartbeat a download job has ever had, since `executor->execute()` used to block with no callback at all. Percent isn't monotonic across a single download: yt-dlp reports progress per stream, so a merged video+audio download can drop back down when it moves from one stream to the next.

## Download service

All stash downloads go through `App\Domain\Download\DownloaderInterface`:

| Implementation | When |
|---|---|
| `FakeDownloader` | `providerKey=fake` (tests, dev, Docker smoke) |
| `YtdlpDownloader` | Non-fake providers, on by default outside tests (`STASHD_REAL_DOWNLOADS_ENABLED`) |
| `RoutingDownloader` | Default binding; selects the above |

- Command: `item.download` → temp staging → Vault ingest → asset rows
- Vault originals are not overwritten by default; `force=true` returns `download_force_not_supported`
- Opt-in live tests: `STASHD_LIVE_DOWNLOAD_TESTS=1`

See `docs/storage/README.md` for idempotency, drift detection, and sidecar JSON rules.

## Fixtures

HTTP fixtures for CI live under:

```text
tests/fixtures/providers/youtube/http/
tests/fixtures/providers/fake/
```

Map URLs to fixture bodies in `map.json`. Tests bind `FixtureProviderHttpClient` when `ENVIRONMENT=testing`.

Optional live provider tests:

```env
STASHD_LIVE_PROVIDER_TESTS=1
STASHD_LIVE_DOWNLOAD_TESTS=1
```

## End-to-end flow (preflight → stash)

```text
POST /api/v1/commands  type=stash.preflight  source_uri=<url>
  → job preflight
  → commands.result

GET /api/v1/stashes/preflight/{commandId}/review

POST /api/v1/commands  type=stash.create_from_preflight
  → stash, stash_input, media_items, media_item_sources, stash_items
```

Media items deduplicate globally by `(providerKey, providerItemId)`.

Downloads (when enabled):

```text
POST /api/v1/commands  type=item.download
  → temp staging → Vault → assets ready
```

## Typed domain boundaries

Per the engineering spec, provider domain types are typed internally; raw strings appear only at HTTP/DB/JSON edges.

| Type | Role |
|---|---|
| `StashdUri` | Wraps `Tempest\Support\Uri\Uri` — parse, fake URIs, path/query helpers |
| `ProviderDates` | Parses/constructs `Tempest\DateTime\DateTime` (`tryParse()`, `utc()`) |
| `YouTubeUris` | Centralized watch/feed/oembed/Data API URL builders |
| `DiscoveredItem` / `ResolvedInput` | Hold `StashdUri` + `DateTime`; serializers emit `toString()` / RFC3339 `Z` |
| `Tempest\Support\str()` | String helpers (not raw PHP string functions) |

Do not pass raw URL or date strings through provider strategy handlers when a typed wrapper exists.
