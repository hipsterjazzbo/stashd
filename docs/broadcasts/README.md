# Broadcasts (Phase 5A + 5B + 5C)

Broadcasts are **disposable, regeneratable views** of a stash. They do not own canonical media — the Vault remains the source of truth.

## Phase 5A (complete)

- Generic broadcast lifecycle (`plan` → `publish` → `verify` → `prune`)
- `filesystem_series` broadcast type (hardlinked media, no NFO sidecars)
- Async commands: `broadcast.plan`, `broadcast.rebuild`, `broadcast.verify`, `broadcast.prune`

## Phase 5B (complete)

- `jellyfin_series` and `plex_series` broadcast types (distinct type keys/settings)
- Media-server-friendly layout with `SxxExxx - Title.ext` episode names
- Minimal deterministic NFO sidecars (`tvshow.nfo`, per-episode `.nfo`)
- Optional poster hardlink when Vault thumbnail exists and hardlink succeeds (skipped otherwise)
- Scan triggers via Jellyfin/Plex HTTP clients (see `docs/media-servers/README.md`)
- `broadcast.trigger` command + optional `auto_trigger_scan` after rebuild

Not implemented:

- Public podcast feed/media routes (Phase 5C follow-up)
- Transcode/remux
- Silent copy when hardlinks fail
- Broad Plex/Jellyfin PHP client libraries

## Phase 5C (in progress)

- `audio_podcast` and `video_podcast` broadcast types are registered.
- Podcast broadcast/feed tokens and podcast item tokens are stored through `SecretsService`.
- Authenticated API responses can show the full private feed URL for podcast broadcasts.
- `broadcast.rotate_token` rotates the private feed token without rotating item tokens.
- `broadcast.rebuild` for podcast formats writes a deterministic generated feed:

```text
/media/broadcasts/{broadcastId}/feed.xml
```

Podcast feeds are tokenized HTTP distribution surfaces, not Plex/Jellyfin filesystem views. Podcast formats do **not** use `AbstractSeriesBroadcastType`.

Public feed and media serving routes are still pending:

```text
GET /b/{broadcastToken}/feed.xml
GET /b/{broadcastToken}/items/{itemToken}/episode.{ext}
```

Generated enclosure URLs already use that path-token shape. The routes themselves will be wired in a later slice.

## Layout

### `filesystem_series`

```text
/media/broadcasts/{broadcastId}/
  Season 01/
    001 - Episode-title.ext
    002 - Another-title.ext
```

### `jellyfin_series` / `plex_series`

```text
/media/broadcasts/{broadcastId}/
  tvshow.nfo
  poster.jpg                    # optional hardlink when available
  Season 01/
    S01E001 - Episode-title.ext
    S01E001 - Episode-title.nfo
    S01E002 - Another-title.ext
```

### `audio_podcast` / `video_podcast`

```text
/media/broadcasts/{broadcastId}/
  feed.xml
```

- Podcast rebuilds do not hardlink/copy media files into the broadcast directory.
- Enclosure URLs point at future tokenized media routes.
- Raw broadcast/item tokens are recoverable only through encrypted secrets, and appear only in intended feed/enclosure URLs.
- Audio podcast feeds require ready audio Vault assets.
- Video podcast feeds require ready video Vault assets with conservative podcast-friendly MIME/container support.
- Full transcode/remux and media probing remain future work.

- Path identity uses broadcast ID + stash item ordering (season/episode/position fallbacks)
- Filenames use readable titles with broadcast-safe sanitization (spaces preserved in folder/episode names)
- Generated paths may change on rebuild; Vault paths never change

## Hardlink-first policy

1. Prefer `link()` from Vault original → broadcast path
2. Verify inode match after linking (where the platform exposes stable inode info)
3. If hardlinks are unavailable → stable error `broadcast_hardlink_unavailable`
4. **No silent copy fallback**
5. Symlink/copy require explicit future policy (not implemented)

Cross-root hardlink support is probed at boot (`vault_broadcast_hardlink` in system health).

## Lifecycle

| Step | Command | Writes disk? |
|---|---|---|
| Plan | `broadcast.plan` | No — computes intended files + sidecars |
| Rebuild | `broadcast.rebuild` | Yes — plan + publish + verify (+ optional auto scan trigger) |
| Verify | `broadcast.verify` | No — checks files + hardlink targets + sidecars |
| Prune | `broadcast.prune` | Yes — removes stale generated files only |
| Trigger | `broadcast.trigger` | No — media-server scan only; failures do not invalidate files |

**Trigger failures do not mark broadcast publish failed** when generated files remain valid. Trigger runs are recorded separately on `broadcast_trigger_runs`.

## Staleness

A broadcast or item becomes `stale` when:

- Source Vault asset is missing or not ready
- Media item is not `ready`
- Stash item is not `active`
- Generated file is missing
- Hardlink target is invalid (inode mismatch)
- Expected NFO sidecar is missing (jellyfin/plex types)

Not yet implemented: settings-change detection, artwork/subtitle drift beyond optional poster, season mapping changes.

## API

```text
GET  /api/v1/stashes/{stashId}/broadcasts
POST /api/v1/stashes/{stashId}/broadcasts   (type: filesystem_series | jellyfin_series | plex_series | audio_podcast | video_podcast)
GET  /api/v1/broadcasts/{broadcastId}
GET  /api/v1/broadcasts/{broadcastId}/items
POST /api/v1/commands  (broadcast.plan|rebuild|verify|prune|trigger)
```

Media server connections: see `docs/media-servers/README.md`.

JSON uses snake_case. SQLite columns remain camelCase. Broadcast `settings` JSON is stored snake_case.

## Assets

Broadcast hardlinks are stored as `assets` rows with:

- `role`: `hardlink`
- `broadcastId`, `broadcastItemId`
- `derivedFromAssetId` → Vault original

Vault originals are never modified or deleted by broadcast operations.

## Activity events

- `broadcast.planned`
- `broadcast.rebuild_started`
- `broadcast.published`
- `broadcast.verified`
- `broadcast.stale`
- `broadcast.failed`
- `broadcast.pruned`
- `broadcast.trigger_succeeded` (Phase 5B)
- `broadcast.trigger_failed` (Phase 5B)
