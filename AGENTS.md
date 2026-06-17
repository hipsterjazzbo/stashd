# AGENTS.md

## Purpose

This file is the canonical instruction file for AI coding agents working on **stashd**.

Read it before planning or editing.

This file is intended to work for:

* Claude Code
* Hermes Agent
* Aider
* Cursor/Codex-style agents
* other local or remote coding assistants

For Claude Code, `CLAUDE.md` should point here.

For Hermes Agent, `.hermes.md` may point here, but this file should remain the canonical project instruction file. Keep Hermes-specific operational details short here; put longer Hermes/Ollama setup notes under `docs/ai/`.

Keep this file concise, specific, and maintained. Do not turn it into a second engineering spec or a full project memory dump.

## Project identity

**stashd** is a self-hosted media preservation service.

It saves online video/audio sources into a local **Vault** and exposes generated views through **Broadcasts** such as Jellyfin libraries, Plex libraries, and private podcast feeds.

The core product model is:

```text
Stash → Vault → Broadcasts
```

The product should feel like quietly competent self-hosted infrastructure: fast, reliable, private, understandable, and boring to run.

## Current stack

* PHP `^8.5`
* Tempest Framework `^3.0`
* RoadRunner application server
* SQLite for v1
* Pest for tests
* Laravel Pint with PSR-12 for formatting
* `hazel/ytdlphp` wrapping `yt-dlp`
* Docker-first default deployment
* Default HTTP port: `8474`

## Repo map

Important directories:

```text
app/Auth           Single-owner auth, users, API tokens
app/Broadcasts     Generated views such as podcast/media-server outputs
app/Commands       Command records and command dispatch
app/Config         Runtime/application configuration
app/Console        CLI/runtime entry points
app/Database       SQLite/migrations/database helpers
app/Downloads      Download orchestration and ytdlphp adapters
app/Http           HTTP/API concerns
app/Jobs           Persistent job model and execution
app/MediaServers   Plex/Jellyfin integration
app/Providers      Provider model, strategies, URI handling, YouTube/Fake providers
app/Stashes        Stash creation, preflight, stash inputs/items
app/Support        Cross-cutting app support
app/System         Health/system/runtime concerns
app/Vault          Canonical media items and assets
tests/Unit         Pure unit tests
tests/Feature      HTTP/application behaviour tests
tests/docker       Docker smoke tests
docs/              Product, architecture, provider, storage, runtime, and design docs
```

Always inspect existing code in the relevant domain before adding new patterns.

## Canonical docs

Use these docs as context when relevant:

```text
docs/Stashd-Engineering-Specification.md
docs/Stashd-Architecture-and-Vision-Updated.md
docs/Stashd-Branding-Plan.md
docs/Stashd-Browser-Extension-Spec.md
docs/TODO.md
docs/architecture/
docs/providers/
docs/storage/
docs/runtime/
docs/broadcasts/
docs/media-servers/
docs/foundation/
docs/ai/
```

Do not duplicate these docs in code comments or agent summaries. Link to or update them when a decision changes.

If present, `docs/ai/Stashd-Hermes-Agent-Reference.md` is long-form agent memory. Read only the relevant parts; do not load it reflexively for every small task.

If present, `docs/ai/Hermes-Agent-Ollama-Setup.md` documents Hazel's local Hermes/Ollama setup. It is operational context, not product architecture.

## Current project snapshot

This section is a lightweight orientation aid. Confirm against `docs/TODO.md` and the live code before implementing.

Completed major work:

* Feature-first refactor under `app/`.
* Phase 5A/5B foundations: commands/jobs/SSE, provider layer, fake/real downloads, Vault, Jellyfin/Plex filesystem broadcasts, media-server triggers.
* Phase 5C Slice 1: podcast token foundation and authenticated feed URL exposure.
* Phase 5C Slice 2: podcast feed builder plus `audio_podcast` and `video_podcast` broadcast formats.
* Docker smoke/build cleanup.

Likely upcoming Phase 5C slices:

1. Public tokenized podcast feed route: `GET /b/{broadcastToken}/feed.xml`.
2. Public episode media route: `GET /b/{broadcastToken}/items/{itemToken}/episode.{ext}`.
3. Docker smoke covering podcast feed + episode fetch.

Do not assume this snapshot is fresher than `docs/TODO.md` or recent commits.

## Operating principles

Prioritise:

1. Correct behaviour.
2. Small, reviewable diffs.
3. Clear domain language.
4. Readable modern PHP.
5. Tempest-native patterns already present in the repo.
6. Tests for behaviour changes.
7. Secret-safe logs, activity, jobs, and metadata.
8. Calm, dense, accessible UI/UX.

Avoid:

* Clever abstractions.
* Large speculative rewrites.
* Framework cargo-culting from Laravel, Symfony, Rails, or Node apps.
* Vague names such as `Manager`, `Helper`, `Util`, `Processor`, `Data`, `Info`, or `Thing`.
* Generic methods named after the product, such as `stashd()`.
* Direct shelling out to `yt-dlp` from domain code.
* Raw URL strings leaking through application/domain internals.
* Public-by-default behaviour.
* Hidden filesystem or storage side effects.

## Agent modes and scope

Different agents have different capability and context budgets. Match the task to the agent.

### Claude Code / frontier coding agents

Claude Code or similar frontier agents may handle broader slices, but should still keep diffs small, read relevant local patterns first, and stop when scope expands.

Use them for:

* multi-file feature slices
* security-sensitive route/token work
* lifecycle changes across controllers, commands, jobs, services, tests, and docs
* larger refactors with a clear migration plan

### Hermes Agent / local Ollama agents

Hermes Agent on Hazel's local Ollama setup is useful but resource-constrained. Treat it as a careful local teammate, not a frontier autonomous engineer.

Use Hermes/local models for:

* selected-file review
* purpose-focused class docblocks
* small unit tests
* small DTO/resource cleanups
* naming/cohesion feedback
* explaining unfamiliar code
* narrow patches touching roughly 1-4 files

For broad or security-sensitive tasks, Hermes should produce an audit/plan first rather than editing immediately.

Hermes-specific operational notes:

* Use the OpenAI-compatible Ollama endpoint: `http://<ollama-box-ip>:11434/v1`.
* Prefer `qwen-coder-14b-work` or `qwen-coder-14b-fast` if configured.
* Context is limited on the RTX 3060 12 GB setup; inspect targeted files instead of loading the whole repo.
* If a task touches secrets, tokens, routes, schema, auth, commands, jobs, or storage layout, stop and confirm the slice unless Hazel's instructions are explicit.

### Aider / terminal patch agents

Use Aider for narrow git-aware patches against explicitly supplied files. Do not point a local model at the whole repo and ask for a broad feature implementation.

## Before editing

For non-trivial tasks:

1. Read this file.
2. Inspect the relevant existing files.
3. Read `docs/agent-context.md` if the task touches product/domain/architecture/UI.
4. Summarise the existing local pattern.
5. Propose a short plan.
6. List likely files to change.
7. List checks/tests to run.
8. Wait for approval if the task is broad, risky, or architectural.

For small obvious fixes, proceed directly but keep the diff minimal.

## After editing

Before claiming completion:

1. Run the most relevant checks available.
2. Summarise what changed.
3. Explain why the change fits the existing architecture.
4. List tests/checks run.
5. State anything not run and why.
6. Note risks or follow-up work.

Never claim tests passed unless they were actually run and passed.

## Commands

Use Composer scripts where available.

```bash
composer test
composer test:unit
composer test:feature
composer test:docker-smoke
composer lint
composer format
```

For Docker/runtime work, inspect the relevant files first:

```text
Dockerfile
docker-compose.yml
.rr.yaml
docker/
.env.example
```

Prefer focused checks during implementation and broader checks before finishing.

## PHP style

Use strict, modern PHP.

Prefer:

* Explicit types.
* Small cohesive classes.
* Constructor property promotion when readable.
* Domain-specific names.
* Enums for bounded state.
* Immutable value objects where useful.
* Early returns when they clarify behaviour.
* Stable error codes for expected failures.

Avoid:

* Clever one-liners.
* Overly defensive code for impossible internal states.
* Boolean flag arguments when a clearer method or object would help.
* Large orchestration classes that hide domain concepts.
* Static utility dumping grounds.
* Unscoped array shapes where a value object or DTO would make intent clearer.

Follow the repo’s existing Pint configuration.

## Docblocks

Class docblocks are welcome when they explain **what the class is and why it exists**.

Good class docblocks should clarify:

* The domain concept represented.
* The boundary the class protects.
* The architectural reason it exists.
* Any important persistence, filesystem, provider, or security assumption.

Do not add docblocks that merely repeat the class name, property types, or obvious method signatures.

Prefer self-documenting code for simple methods.

## Naming rules

Names must carry domain meaning.

Good examples from the current direction:

```text
Stash
StashInput
StashItem
MediaItem
Vault
Asset
Broadcast
Provider
ProviderStrategy
ResolvedInput
StashdUri
DownloadPolicy
OrganizationMode
Preflight
Command
Job
ActivityEvent
StorageLocation
```

Use verbs for actions:

```text
resolveInput
discover
captureMetadata
selectStrategy
createStashFromPreflight
verifyAsset
publishBroadcast
```

Avoid vague verbs:

```text
handle
process
manage
do
run
stashd
```

These may be acceptable only when the surrounding type makes the meaning precise, such as a Tempest command handler method required by convention.

## Domain rules

Stashd is **media-item-first**.

* A **Stash** is user-facing organisation.
* A **StashInput** is the technical upstream source inside a stash.
* A **MediaItem** is the canonical provider item.
* The **Vault** owns canonical preserved assets.
* A **Broadcast** is a generated, rebuildable view.
* Broadcasts do not own canonical media.

A media item may appear in multiple stashes and broadcasts but should exist once in the Vault.

## State rules

Use consistent state language.

Prefer:

```text
pending
processing
ready
stale
failed
missing
disabled
ignored
```

Put specific detail in reason/error fields:

```text
last_error
failure_reason
missing_reason
removed_reason
ignored_reason
last_checked_at
```

Avoid introducing new state names unless they are genuinely needed.

Avoid `archived` as an internal state unless the product meaning is explicitly decided, because it can imply inactive, hidden, preserved, or stored.

## URI and provider rules

Use `App\Providers\StashdUri` for application/provider URI handling.

Raw URL strings should appear only at boundaries:

* HTTP/API request input
* JSON output
* database persistence
* logs/activity after secret-safe redaction
* external process/API calls

Do not pass raw strings through domain logic when a `StashdUri` or more specific value object can represent intent.

Providers are capability bundles, not downloaders.

Providers expose or coordinate capabilities such as:

* discovery
* metadata
* download
* availability
* authentication/session handling

Strategy choice should be driven by intent, cost, availability, and safety.

## YouTube rules

YouTube is the first provider, not the whole architecture.

For YouTube v1:

* Prefer cheap routine discovery.
* Use RSS for routine discovery where appropriate.
* Use YouTube Data API for backfill/metadata when configured.
* Use `yt-dlp` through `hazel/ytdlphp` for download.
* Use heavy `yt-dlp` inspection sparingly.
* Do not design code that locks future providers into YouTube concepts.

Supported URL thinking should include:

```text
youtube.com/watch?v=...
youtu.be/...
youtube.com/playlist?list=...
youtube.com/@handle
youtube.com/channel/...
youtube.com/c/...
youtube.com/user/...
youtube.com/shorts/...
```

## Download rules

All real downloads go through the download abstraction.

Do not shell out directly from controllers, providers, jobs, or domain services.

Downloads should be job-driven, recoverable, and safe to retry.

Do not enable real provider/download behaviour in tests by default. Use fakes and fixtures unless explicitly working on live-provider behaviour.

`STASHD_REAL_DOWNLOADS_ENABLED=0` is the safe default.

## Storage and filesystem rules

The database stores expected state.

The filesystem is verified reality.

Stashd must tolerate filesystem drift:

* missing files
* moved files
* unavailable roots
* read-only mounts
* failed hardlinks
* manual user changes

Do not crash or cascade-delete database state merely because a file is missing.

Distinguish:

```text
storage root unavailable
```

from:

```text
individual asset missing
```

Generated files should be atomic where practical:

```text
write temp file → flush/close → rename into final path
```

Broadcast media should prefer:

```text
hardlink → optional symlink → explicit copy → explicit remux/transcode
```

Never silently duplicate large media files.

## Broadcast rules

Broadcasts are generated views.

They should be:

```text
idempotent
rebuildable
verifiable
prunable
disposable
```

Deleting a broadcast folder must not destroy canonical Vault assets.

Broadcasts live under Stashes in v1. Do not add a top-level Broadcasts product area unless explicitly requested.

Trigger failures, such as Jellyfin/Plex scan failures, should not necessarily mark generated files as failed if publication succeeded.

## Podcast broadcast rules

Podcast broadcasts are tokenized HTTP feed views, not filesystem media-server layouts.

Current podcast concepts include:

```text
BroadcastType::AudioPodcast
BroadcastType::VideoPodcast
SecretType::BroadcastToken
CommandType::BroadcastRotateToken
broadcasts.tokenSecretId
broadcasts.tokenPreview
broadcast_items.tokenSecretId
broadcast_items.tokenPreview
```

Rules:

* Feed tokens and item tokens are path tokens, not query parameters.
* Authenticated API may expose a full copyable feed URL.
* Raw tokens must not appear in logs, activity metadata, job payloads, command request payloads, command result JSON, exceptions, or plaintext database columns.
* `tokenPreview` stores only a safe preview.
* Encrypted secrets store recoverable raw tokens when authenticated display requires it.
* `broadcast.rotate_token` must invalidate old feed URLs once public routes exist.
* Podcast feed generation may create disposable `feed.xml` artifacts under broadcast output storage.
* Podcast formats must not hardlink/copy/transcode media unless a later explicit media-serving/transcode slice adds that behaviour.
* Public feed/media routes must return non-revealing errors for invalid/revoked tokens.

## Auth and secrets

v1 is private by default.

Assume:

* single-owner UI login
* scoped API tokens
* private path-token broadcast URLs
* no public catalog
* no unauthenticated web UI

Never log or expose:

* API tokens
* broadcast feed tokens
* provider credentials
* cookies/sessions
* authorization headers
* private feed URLs
* raw secret values

Secrets must be redacted from:

```text
logs
activity events
job payloads
raw metadata snapshots
API errors
test snapshots
```

API tokens should be shown once and stored hash-only.

Reusable credentials should go through a Stashd-owned secrets service.

## API resource and serialization rules

Do not auto-serialize Tempest record objects directly to public API output.

Prefer:

```text
Record → explicit Resource DTO → toArray()
```

Avoid:

```text
Record → generic object mapper → public response
```

Boring internal DTOs may use shared serialization helpers. Public or security-sensitive response shapes should stay explicit.

When reducing repeated `toArray()` methods, preserve the API/security boundary:

* do not expose secret IDs unless intentionally internal
* do not expose ciphertext, password hashes, raw tokens, provider credentials, or internal filesystem paths
* use safe previews for secret-like values
* keep authenticated copy/display flows explicit

## API rules

Prefer REST-ish JSON under:

```text
/api/v1
```

Everything the UI can do should be possible through the API.

The UI should not depend on private UI-only backend routes for normal operations.

Long-running actions should be submitted as commands rather than hidden blocking actions.

Expected error responses should be stable, actionable, and secret-safe:

```json
{
  "error": {
    "code": "storage_hardlink_unavailable",
    "message": "Hardlinks are unavailable between Vault and Broadcasts.",
    "details": {}
  }
}
```

## Browser extension boundary

The browser extension is a small companion, not a second Stashd UI.

It should:

* send current supported page URLs to Stashd
* use the public API
* use limited-scope API tokens
* hand off complex decisions to the main web app
* avoid broad browser permissions
* avoid background browsing/history tracking

Stashd should own provider resolution, preflight, download policy, broadcast choices, and review flows.

## UI/UX rules

Stashd should feel like a NAS/system dashboard, not a streaming platform.

Prefer:

* dense information
* keyboard-friendly interactions
* searchable lists
* responsive layouts
* dark-mode-first design
* minimal animation
* clear status indicators
* calm technical presentation
* compact cards
* useful empty states
* actionable error states
* visible job/activity progress

Avoid:

* YouTube clone UI
* social feed patterns
* giant entertainment cards everywhere
* mystery spinners
* heavy gradients
* excessive animation
* growth-hacking SaaS polish
* visual noise

Brand direction:

```text
stashd_
Because the internet forgets.
```

Use warm espresso-charcoal/dark brown-black backgrounds, muted graphite-brown panels, warm cream text, muted amber accents, subtle borders, and monospace or semi-monospace typography where appropriate.

Use monospace generously for:

```text
job IDs
command IDs
storage paths
provider IDs
filenames
API tokens/previews
feed URLs
logs
diagnostics
progress output
technical tables
```

Do not make the UI gimmicky-terminal unless it remains readable and polished.

## Product copy

Copy should be trustworthy, preservation-focused, and slightly cheeky without becoming piracy-coded.

Good tone:

```text
Tucked safely away.
It’s in the Vault now.
Saved before it vanished.
Your archive. Your rules.
```

Avoid copy that implies piracy, evasion, scraping abuse, or replacing creator support.

Stashd must not simulate upstream views, watch time, likes, comments, or engagement.

## Testing rules

Behaviour changes should generally include tests.

Use the existing Pest suites:

```text
tests/Unit
tests/Feature
tests/docker
```

Prefer:

* unit tests for pure logic
* feature tests for HTTP/API behaviour
* filesystem integration tests for storage semantics
* fake provider tests for provider workflows
* fixture-based tests for external provider formats
* mocked downloader tests for download orchestration
* Docker smoke tests for packaging/runtime changes

Do not use live providers by default.

Do not require real network access in normal test runs.

## Agent efficiency

Claude Pro usage is limited. Do not waste context.

Do:

* inspect only relevant files first
* use `rg`/targeted reads before broad exploration
* keep plans short
* make one slice of change at a time
* avoid re-reading long docs unless needed
* summarise decisions into docs only when they should persist
* stop and ask when scope expands

Do not:

* wander the whole repo without reason
* rewrite stable code because it could be prettier
* run expensive checks repeatedly when a focused test is enough
* paste huge file contents into summaries
* duplicate the engineering spec in responses

For local Hermes/Ollama work:

* prefer targeted reads with `rg` and a few files at a time
* avoid broad autonomous edits
* prefer `/ask`/audit mode before `/code`-style edits when scope is unclear
* produce small patches that Hazel can review in PhpStorm
* escalate broad/security-sensitive implementation to a frontier agent or ask Hazel to narrow the task

## Git rules

Do not commit unless explicitly asked.

Do not rewrite history.

Do not stage unrelated files.

Before finishing, provide:

```text
Changed:
- ...

Checks:
- ...

Notes:
- ...
```

## Ask first

Ask before:

* adding dependencies
* changing database schema destructively
* deleting user/media data
* changing storage layout
* enabling copies/transcoding by default
* changing auth/session/token behaviour
* changing secret storage
* changing Docker/RoadRunner runtime behaviour
* introducing queues, caches, external services, or multi-container requirements
* making broad architecture changes
* changing product terminology
* making public access possible
