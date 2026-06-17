# Stashd Hermes Agent Reference

This is the long reference/context file for Hermes Agent. Keep `.hermes.md` and `AGENTS.md` short; use this file when Hermes needs project memory.

## 1. User / collaboration style

Hazel Fidecaro is building Stashd.

Preferred assistant style:

- informal, collaborative, direct
- honest about uncertainty
- opinionated when taste/architecture matters
- practical rather than hypey
- willing to push back
- concise by default, deeper when useful

Hazel values:

- readable code
- standards/compliance
- good UX/UI
- beautiful, tasteful product design
- project-specific guidance
- small reviewable patches
- not cargo-culting frameworks or patterns

Avoid:

- useless PHPDoc
- JavaBean naming for no reason
- Laravel-shaped answers for a Tempest app
- generic enterprise abstraction
- broad rewrites without tests
- pretending local 14B models are frontier coding agents

## 2. Project identity

Project:

```text
Stashd
```

Repo:

```text
https://github.com/hipsterjazzbo/stashd
```

Product idea:

```text
Stashd archives online media locally and rebroadcasts it into self-hosted media surfaces like Plex, Jellyfin, and private podcast feeds.
```

Core model:

```text
Stash -> Vault -> Broadcasts
```

Meaning:

- Stash: the user-facing thing the user wants to keep.
- Vault: the canonical local archive of media assets.
- Broadcast: a disposable generated view over Vault assets.

Product promise:

```text
Copy -> paste -> docker compose up -> works.
```

Brand:

```text
stashd_
Because the internet forgets.
```

UI/brand direction:

- warm dark/amber
- minimalist
- Glance-inspired
- self-hosted but polished
- not cold enterprise SaaS

## 3. Stack and conventions

Stashd uses:

- PHP 8.5+
- Tempest
- RoadRunner
- SQLite
- Docker

Do not assume:

- Laravel
- PostgreSQL
- Redis/queue infrastructure not already present
- Kubernetes/cloud-first deployment

Database/API convention:

```text
SQLite columns: camelCase
Tempest record properties: camelCase
API JSON: snake_case
```

Long-running operations:

```text
HTTP request -> command -> job -> lifecycle/service
```

Controllers should validate/adapt requests and return responses. They should not perform long-running downloads, rebuilds, media-server triggers, or feed generation synchronously unless explicitly designed as a lightweight public read route.

## 4. Feature-first app layout

The codebase has been refactored to feature-first layout under `app/`.

Important folders:

```text
app/Auth/
app/Broadcasts/
app/Commands/
app/Console/
app/Database/
app/Downloads/
app/Http/
app/Jobs/
app/MediaServers/
app/Providers/
app/Stashes/
app/System/
app/Vault/
app/Support/
app/Config/
```

Old broad layers such as `app/Domain`, `app/Services`, `app/Infrastructure`, `app/Controllers`, and `app/Bootstrap` were removed.

Deferred/known cleanup candidates:

- `ActivityEventService`
- API resource/presenter shape
- any remaining generic names that hide feature ownership

## 5. Non-negotiable architecture rules

### Vault and broadcasts

- Vault is canonical.
- Broadcasts are disposable and regeneratable.
- Broadcast formats must not mutate Vault assets.
- Filesystem broadcast publishing is hardlink-first.
- Do not silently copy when hardlinking was expected.
- Podcast broadcasts generate tokenized HTTP feed views, not filesystem series layouts.

### Secrets/tokens

- Secrets go through `SecretsService`.
- State changes go through `StateTransitionService`.
- Raw tokens must not be logged, emitted in activity metadata, stored in job payloads, stored in command request payloads, or stored in command result JSON.
- Raw tokens should not be stored in plaintext DB columns.
- Safe previews are okay.
- Encrypted secrets are recoverable where authenticated display requires it.

### Downloads

- ytdlphp is the external downloader boundary.
- Do not add direct shell/process calls outside the chosen ytdlphp integration.
- Download implementations should produce staged files; ingest/checksum/state transitions live in Stashd code.

### API resources

Do not auto-serialize database records directly into public API JSON.

Prefer:

```text
Record -> explicit Resource DTO -> toArray()
```

DTO boilerplate can be reduced with shared helpers, but public/security-sensitive shapes must remain intentional.

## 6. Current implementation status

Completed foundations through Phase 5B include:

- core schema/auth
- commands/jobs/SSE
- provider layer
- YouTube discovery/metadata boundary
- fake/real downloads
- Vault
- Jellyfin/Plex filesystem broadcasts
- media-server triggers
- Docker smoke/build workflow

### Phase 5C Slice 1: podcast token foundation

Completed.

Added/implemented:

- `app/Broadcasts/Podcasts/PodcastTokenService.php`
- `PodcastTokenRotationResult.php`
- `PodcastEpisodeUrlBuilder.php`
- podcast feed token handling via `broadcasts.tokenSecretId` and `broadcasts.tokenPreview`
- podcast item token columns via migration
- authenticated API feed URL display
- `broadcast.rotate_token` for podcast broadcasts

Token behavior:

- feed tokens generated with strong randomness
- tokens encrypted through `SecretsService`
- raw tokens recoverable for authenticated copy/display
- authenticated API may return full `feed_url`
- non-podcast broadcasts do not expose feed fields
- raw tokens kept out of logs/activity/job payloads/command result JSON

Known tests from report:

- targeted Phase 5C token tests passed
- full suite passed with existing warnings/deprecations

### Phase 5C Slice 2: podcast feed builder and formats

Completed.

Added:

```text
app/Broadcasts/Formats/AudioPodcastBroadcast.php
app/Broadcasts/Formats/VideoPodcastBroadcast.php
app/Broadcasts/Podcasts/PodcastBroadcastFormat.php
app/Broadcasts/Podcasts/PodcastFeedBuilder.php
app/Broadcasts/Podcasts/PodcastFeedMetadata.php
app/Broadcasts/Podcasts/PodcastEpisode.php
app/Broadcasts/Podcasts/PodcastGuid.php
app/Broadcasts/Podcasts/PodcastMimeType.php
app/Broadcasts/Podcasts/PodcastAssetSelector.php
app/Broadcasts/Podcasts/PodcastAssetSelection.php
```

Modified:

```text
BroadcastTypeRegistry
BroadcastSidecarKind
docs/TODO.md
docs/broadcasts/README.md
```

Behavior:

- `audio_podcast` and `video_podcast` formats registered
- `broadcast.rebuild` writes deterministic `feed.xml` to `/media/broadcasts/{broadcastId}/feed.xml`
- feed XML includes channel metadata, item metadata, stable GUIDs, pub dates, enclosure URL/length/MIME
- enclosure URLs use path tokens:
  `/b/{broadcastToken}/items/{itemToken}/episode.{ext}`
- no media files are hardlinked/copied for podcast broadcasts
- raw tokens are not stored in broadcast item DB URL/path fields or lifecycle result metadata
- audio podcast requires ready audio assets and records `podcast_audio_asset_unavailable`
- video podcast requires conservative ready video assets and records `podcast_video_asset_unavailable`

Reported gates:

- targeted podcast tests passed
- `composer lint` passed
- `composer test` passed: `191 passed`, `1 skipped`, existing `2 deprecated`, `1 warning`

Unrelated worktree changes reported at that time:

```text
docs/Stashd-Engineering-Specification.md
stashd-code-review.tar.gz
ide-index-mcp.skill
```

If those still appear in `git status`, do not touch them unless Hazel asks.

## 7. Likely next slices

### Phase 5C Slice 3: public tokenized podcast feed route

Goal:

```text
GET /b/{broadcastToken}/feed.xml
```

Behavior:

- unauthenticated public route
- path token, not query token
- validates broadcast token
- finds matching podcast broadcast
- returns generated feed XML
- invalid/revoked token returns non-revealing 404
- non-podcast token returns non-revealing 404/safe invalid response
- missing generated feed returns safe 404/409 without filesystem path leakage
- does not regenerate feed synchronously
- does not implement media serving yet

Security:

- do not log raw tokens
- do not put raw tokens in activity/errors/job payloads
- old feed URL must stop working after token rotation

Suggested tests:

- valid token returns feed XML
- auth not required
- XML/RSS content type
- invalid token 404
- old token after rotation 404
- new token after rotation works
- non-podcast broadcast token does not return feed
- missing feed safe response without filesystem path leakage
- raw token not in activity/errors
- path token only, not query token

### Phase 5C Slice 4: public episode media route

Goal:

```text
GET /b/{broadcastToken}/items/{itemToken}/episode.{ext}
```

Behavior:

- unauthenticated route
- validates broadcast token and item token
- serves selected Vault asset through safe public response
- supports correct content type
- likely Range requests if required by podcast apps, but can be sliced separately
- invalid token returns non-revealing 404
- no transcode/remux in this slice unless explicitly scheduled

### Future work

- transcode/remux for podcast compatibility
- podcast artwork
- OpenAPI docs
- UI for podcast feed copy/display
- Docker smoke covering feed + episode fetch

## 8. Docker smoke/build notes

Docker smoke cleanup completed.

Root cause previously fixed:

- PHP 8.5 bundles `uri`
- Dockerfile had tried `docker-php-ext-install uri`
- this caused `cp: cannot stat 'modules/*'`

Current intended smoke commands:

```bash
composer test:docker-smoke:no-build
# or
STASHD_SMOKE_SKIP_BUILD=1 tests/docker/smoke.sh
```

Run full Docker smoke only when Docker/runtime behavior changes or Hazel asks.

## 9. Local model usage rules

Hermes Agent on local Ollama/3060 is a constrained helper.

Use it for:

- docblocks
- selected-file review
- narrow tests
- small refactors
- explaining code
- finding obvious token/path leaks
- preparing prompts for stronger agents

Do not use it for large autonomous code changes unless Hazel explicitly accepts the risk.

Before editing, Hermes should ask itself:

```text
Can this be done in 1-4 files?
Can I name the exact tests?
Does it touch routes, secrets, tokens, DB schema, command strings, or job intents?
Are there unrelated worktree changes?
Would a failed patch be easy to revert?
```

If not, produce an audit/plan instead of editing.

## 10. Reusable prompts

### Selected-file review

```text
Review the selected files for Stashd-specific issues.

Focus on:
- obvious bugs
- misleading names
- missing focused tests
- token/secret/path leaks
- controller/business-logic boundary violations
- Vault vs broadcast boundary mistakes

Do not suggest broad rewrites.
Do not suggest adding `get` prefixes unless materially clearer.
Do not suggest PHPDoc unless it explains why a class exists or a non-obvious boundary.
Classify each suggestion as:
- must fix
- nice to have
- probably not worth changing
```

### Purpose-focused class docblocks

```text
Add purpose-focused class docblocks to the selected files only.

Each docblock should explain:
1. what the class is
2. why it exists
3. what boundary/responsibility it owns
4. what it deliberately does not do, if that prevents misuse

Do not add @param or @return tags.
Do not add method docblocks unless behavior is non-obvious or security-sensitive.
Do not change behavior.
Do not rename anything.
```

### Narrow test task

```text
Add focused tests for the selected helper/service.

Keep the patch small.
Do not change production behavior unless a test reveals a real bug.
Use existing project test style.
Run the most targeted test command first, then report whether full `composer test` is needed.
```

### Patch safety preflight

```text
Before editing, inspect git status and report unrelated changes.
Then summarize the smallest safe patch scope and the tests that prove it.
Do not modify files until the scope is clear.
```
