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
* Phase 5C Slice 3: public tokenized podcast feed route (`GET /b/{broadcastToken}/feed.xml`).
* Phase 5C Slice 4: public episode media route (`GET /b/{broadcastToken}/items/{itemToken}/episode.{ext}`), including single-range `Range` request support.
* Phase 5C is otherwise complete; Docker smoke now covers the podcast feed/episode/Range routes. Transcode/remux broadcast policies are deferred as a separate future initiative (requires explicit sign-off — see "Ask first").
* Docker smoke/build cleanup.

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

Use Tempest-native facilities by default when they fit the domain.

Prefer Tempest's first-party mechanisms over custom stashd code for framework-level concerns such as:

```text
database relations and eager loading
mapper casters/serializers
DateTime/Duration values
#[SerializeAs] embedded DTO properties
#[Hidden] / #[Encrypted] model-property annotations
discovery
validation
query builders
```

Custom code should exist because stashd has a real domain, security, storage, or product requirement — not because we skipped checking whether Tempest already provides the mechanism.

Examples:

* Prefer Tempest `DateTime` over custom timestamp helpers.
* Prefer `#[SerializeAs]` for stable embedded JSON values over manual `json_encode()` / `json_decode()`.
* Prefer `#[Hidden]` for sensitive model-property guardrails over relying only on discipline.
* Prefer Tempest relations, `with(...)`, and relation-scoped queries where they cleanly express structural data access and reduce N+1 queries.
* Keep explicit stashd services/repositories where they enforce auth, secrets, storage drift handling, token safety, command/job boundaries, provider behavior, or other domain policy.

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

This project's PHP, Composer, and vendor binaries only exist correctly inside lerd's containers (correct PHP version/extensions, `.env` service hostnames like `lerd-mysql`, the actual mounted SQLite file). Never invoke `php`, `composer`, or `vendor/bin/*` directly on the host — always go through the lerd MCP `exec` tool (`action: "composer"` for Composer scripts, `action: "console"` for Tempest console commands, `action: "vendor_run"` for pint/pest/phpstan/rector after `vendor_bins`). See the Lerd section below for the full tool surface.

Use Composer scripts where available (run via `exec` `action: "composer"`, e.g. `args: ["test"]`):

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

### Live UI / browser testing

Playwright (`@playwright/test`) is set up host-side for driving a real browser against the running dev app — `playwright.config.ts` + `e2e/`, run via `npm run test:e2e`. `baseURL` defaults to `https://stashd.test` (override with `STASHD_BASE_URL`); `ignoreHTTPSErrors` is on for the mkcert dev cert.

This does **not** go through lerd's `pest:browser` feature (`pestphp/pest-plugin-browser` + musl chromium baked into the shared FPM image) — that mechanism only applies to sites running on lerd's shared Alpine FPM image. stashd is a custom-container site (its own Debian/glibc `Containerfile`, served by RoadRunner), so Playwright's bundled (glibc) Chromium runs directly on the host instead.

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

<!-- lerd:begin -->
## Lerd — Laravel Local Dev Environment

This project runs on **lerd**, a Podman-based Laravel development environment. The `lerd` MCP server is available — use it to manage the environment without leaving the chat.

The MCP surface is **eleven grouped tools**, each driven by an `action` argument: `site`, `service`, `db`, `env`, `runtime`, `worker`, `exec`, `framework`, `diag`, `logs`, `worktree`. Always pass `action`. Most actions also accept an optional `path` that defaults to the directory the assistant was opened in (then `LERD_SITE_PATH` if set), so you can usually omit it. Start by calling `site` with `action: "list"` to discover sites.

### Architecture

- PHP runs in Podman containers named `lerd-php<version>-fpm` (e.g. `lerd-php84-fpm`); each container includes composer and node/npm; the PHP version is resolved from `.lerd.yaml` → `.php-version` → `composer.json` `require.php` constraint (matched against installed versions) → global default
- Nginx routes `*.test` domains to the correct PHP-FPM container
- Services (MySQL, Redis, PostgreSQL, etc.) and custom services run as Podman containers via systemd quadlets
- Node.js versions are managed by fnm; per-project version is set via a `.node-version` file
- Framework workers (queue, schedule, reverb, horizon, messenger, vite, etc.) run as systemd user services named `lerd-<worker>-<sitename>`; commands are defined per-framework in YAML; Laravel Horizon is auto-detected from `composer.json` and replaces the queue toggle when installed; Laravel ships with a `vite` host worker that runs `npm run dev` on the host via fnm for HMR (it runs `bun run dev` instead when the project uses bun or Node is unmanaged and bun is installed); workers and setup commands support optional `check` (`file` or `composer`) for conditional visibility; workers with `conflicts_with` auto-stop conflicting workers on start. Per-worker flags: `host: true` (run on host via fnm instead of in FPM container — HMR-sensitive Node tools), `per_worktree: true` (worker runs independently per worktree under `lerd-<worker>-<site>-<branch>`), `replaces_build: true` (worker provides asset manifest while running, so a worktree add skips the static `npm run build` step when this worker is opted in)
- Custom workers can be added per-project (`.lerd.yaml` `custom_workers`) or globally (`~/.config/lerd/frameworks/<name>.yaml`); use the `worker` tool's `add`/`remove` actions — both survive framework store updates
- Framework setup commands (one-off bootstrap steps like migrations, storage links) are defined in the framework YAML and shown by `framework` `action: "setup"`; Laravel has built-in storage:link/migrate/db:seed; custom frameworks can define their own
- Service version placeholders (`{{mysql_version}}`, `{{postgres_version}}`, `{{redis_version}}`, `{{meilisearch_version}}`) are available in framework env vars and resolved from the service image tag at env-setup time
- **Custom containers**: non-PHP sites (Node.js, Python, Go, etc.) can define a `Containerfile.lerd` and a `container:` section in `.lerd.yaml` with a port; lerd builds a per-project image, runs it as `lerd-custom-<sitename>`, and nginx reverse-proxies to it; the project directory is volume-mounted at its host path with `--workdir` set automatically — do NOT add `WORKDIR` or `COPY` to the Containerfile; workers exec into the custom container; services are accessible by name on the shared `lerd` Podman network; **hot-reload file watchers must use polling on macOS** (inotify does not fire across Podman Machine's virtiofs mount) — nodemon: `--legacy-watch`, Vite: `server.watch.usePolling: true`, webpack: `watchOptions: { poll: 1000 }`
- **Custom-image PHP sites (custom-FPM)**: a PHP project can define a `Containerfile.lerd` (must build `FROM lerd-php<ver>-fpm:local`) plus a `container:` section with **no port**; lerd builds a per-site image (`lerd-custom-<site>:local`), runs a dedicated FPM container `lerd-cfpm-<site>`, and serves it by fastcgi instead of the shared `lerd-php<ver>-fpm`. It is a normal PHP site otherwise (xdebug, dumps, profiler, `lerd shell`, `php`/`artisan`/`composer`/`tinker`, queue/horizon all run in the per-site container). The PHP version is fixed by the `FROM` line (the UI version selector is read-only); `lerd rebuild` rebuilds the image. Same key as custom containers, the port is the discriminator: with a port it is a reverse-proxied non-PHP app, without a port it is a fastcgi PHP image. `runtime` for these reports `fpm-custom`.
- Git worktrees automatically get a `<branch>.<site>.test` subdomain (deep `*.<branch>.<site>.test` wildcard cert + nginx `server_name` on secured sites); `vendor/`, `node_modules/`, `.env` are seeded from the main checkout. `.lerd.yaml` `env_overrides` declares templated env vars (`{{domain}}`, `{{scheme}}`, `{{site}}`) layered on the default `APP_URL` rewrite — for multi-tenant apps (per-branch cookies, signed-URL hosts, tenant routing)

### DNS modes

Lerd has two install-time DNS modes recorded in `~/.config/lerd/config.yaml`:
- **Managed (default)**: `dns.enabled: true`, `dns.tld: test`. Sites at `*.test` via lerd-dns + mkcert; `site` `tls_enable` works.
- **Disabled**: `dns.enabled: false`, `dns.tld: localhost`. Sites at `*.localhost` via RFC 6761; no mkcert CA, TLS toggling unavailable.

Read `diag` `action: "status"` for `dns.tld` and `dns.enabled` instead of assuming `.test`; do not propose `tls_enable` when `dns.enabled` is false.

### MCP tools

Eleven grouped tools, each selecting behaviour via `action`.

#### `site` — sites and their configuration
Actions: `list` (discover sites — CALL FIRST), `link`, `unlink`, `domain_add`, `domain_remove`, `group_assign`, `group_unassign`, `group_label`, `group_db`, `group_list`, `tls_enable`, `tls_disable`, `php`, `node`, `pause`, `unpause`, `restart`, `rebuild`, `runtime`, `nginx_read`, `nginx_write`, `nginx_reset`, `park`, `unpark`.
- `link` registers a directory; non-PHP sites need `.lerd.yaml` `container.port` + a Containerfile first, or they register as PHP (wrong)
- `domain_*` take a domain without the `.test` TLD; you can't remove the last domain
- `group_*` nest a secondary site under a main's subdomain (one level deep): they identify the secondary by `path` (defaults to cwd), not by `site`; `group_assign` with `main` + `label` (+ optional `share_db`), `group_db` = share|separate
- `php`/`node` take `version`; pass `branch` to pin the override on a worktree's checkout
- `runtime` switches `fpm` ↔ `frankenphp` (`worker: true` enables frankenphp worker mode)
- `nginx_write` saves a custom override (runs `nginx -t`, backs up, reloads); `branch` targets a worktree
- `park` registers a parent dir and auto-registers every PHP project under it; `unpark` reverses it (project files kept)

#### `service` — built-in & custom services
Actions: `start`, `stop`, `restart`, `pin`, `unpin`, `update`, `rollback`, `migrate`, `remove`, `reinstall`, `add`, `expose`, `env`, `config_read`, `config_write`, `config_restore`, `config_reset`, `config_list_backups`, `preset_list`, `preset_install`, `check_updates`.
- `update` pulls a newer image (safe, in-strategy); `migrate` dumps + restores across a cross-strategy upgrade; `reinstall` with `reset_data: true` wipes data and reprovisions; `remove` with `remove_data: true` renames the data dir aside
- `stop` marks the service paused — `lerd start` skips it until started again; `pin` keeps it always running
- `add` registers a custom OCI service (`depends_on` wires dependencies, `init: true` for mysql/mariadb); prefer `preset_install` for anything in `preset_list` (phpmyadmin, pgadmin, mongo, mongo-express, selenium, stripe-mock, mysql, mariadb…)
- `env` returns the recommended `.env` connection keys; `expose` publishes an extra port
- `config_*` read/write/restore/reset a service's runtime tuning override

#### `db` — databases
Actions: `set`, `move`, `create`, `export`, `import`, `snapshot`, `snapshots`, `restore`, `snapshot_delete`.
- `set` picks the project DB (`database`: sqlite, mysql, postgres, or a family alternate like mariadb / postgres-pgvector / mysql-5-7); persists to `.lerd.yaml`, rewrites `DB_` keys, starts the service, creates the DB + `_testing`
- `move` migrates sites between two installed same-family services (`from`/`to`, `sites: [...]` or `all: true`) and repoints each `.env`; source data is left intact
- `create`/`export`/`import` auto-detect service and database; pass `service` to override
- `snapshot`/`snapshots`/`restore`/`snapshot_delete` are named, restorable snapshots (MySQL/MariaDB/PostgreSQL); `restore` is destructive; `all_databases` covers the whole service

#### `env` — .env management
Actions: `setup`, `check`, `override`.
- `setup` configures services, DBs, APP_KEY and APP_URL; on a fresh Laravel clone call `db` `set` first to move off sqlite, then `env setup`, then ALWAYS `framework setup` or migrations never run
- `check` compares `.env` against `.env.example`
- `override` manages the personal, gitignored `.env.lerd_override` (its `set` KEY=VALUE win over lerd defaults; `LERD_EXTERNAL_SERVICES=<svc,svc>` marks vars lerd writes but won't start)

#### `runtime` — PHP/Node versions & extensions
Actions: `versions`, `node_install`, `node_uninstall`, `php_list`, `ext_list`, `ext_add`, `ext_remove`.
- `ext_add`/`ext_remove` rebuild the FPM image and restart the container (slow); `ext_add` accepts `apk_deps` for extra Alpine build packages
- **extra Alpine packages**: `lerd php:pkg add/remove/list <packages> [--php version]` (CLI) bakes runtime apk packages (CLI tools, libs) into the FPM image, saved in config under `php.packages` and re-applied on every rebuild, so they survive `php:rebuild` and base image updates. Layered onto the shared image, not the published base.
- **Pest browser testing**: `lerd pest:browser install [version]` (CLI) sets up `pestphp/pest-plugin-browser` to run inside the shared FPM container. Pest drives Playwright locally and its bundled Chromium is glibc (can't run on the container's musl), so lerd bakes Alpine's musl `chromium` into the FPM image (via the `php:pkg` package mechanism, saved under `php.packages`), persists the Playwright registry in a `/root/.cache/ms-playwright` volume, and shims Playwright's browser to exec the system chromium with `--no-sandbox`. Chromium only (no musl Firefox/WebKit); current PHP versions only, not the legacy 7.4/8.0 tier (rejected up front). `lerd pest:browser doctor [version]` checks the setup; `lerd pest:browser remove [version]` un-bakes chromium and rebuilds (the cache volume is left intact). install fails fast if the `playwright` npm package is missing, before any rebuild. Re-run install after bumping the project's Playwright version. Tests then run via the normal `lerd test`/`lerd pest`.
- **bun**: lerd never installs or version-manages bun (user installs it; `bun upgrade` self-updates). On the host, JS install/dev/build run through bun when the project is a bun project (`bun.lockb`/`bun.lock`/`bunfig.toml`/`packageManager: bun`) or when Node is unmanaged and no system Node exists but bun is present. CLI-only host toggles: `lerd node:manage` / `lerd node:unmanage` opt into/out of fnm-managed Node and regenerate host workers (bun ↔ fnm); `lerd js:runtime [bun|node|auto]` pins a single site's JS runtime in `.lerd.yaml` (the dashboard's bun/Node toggle). For an in-container bun (for `lerd shell`): `lerd php:bun install` drops a musl bun into a persistent `/root/.bun` volume that survives image rebuilds, `lerd php:bun update` upgrades it in place, `lerd php:bun version` reports it; auto-installed on `lerd link`/`setup` when host bun is present. These are CLI/host operations, not container exec actions.

#### `worker` — background workers
Actions: `list` (CALL FIRST), `start`, `stop`, `add`, `remove`, `health`, `heal`, `mode_get`, `mode_set`, and the framework workers `queue_start`, `queue_stop`, `horizon_start`, `horizon_stop`, `reverb_start`, `reverb_stop`, `schedule_start`, `schedule_stop`, `stripe_start`, `stripe_stop`, `stripe_config`.
- call `list` to discover a site's workers before `start`; pass `branch` to target a per-worktree unit
- use `horizon_*` instead of `queue_*` when laravel/horizon is installed (mutually exclusive); `queue_start` needs Redis running when `QUEUE_CONNECTION=redis`
- `add` saves a custom worker to `.lerd.yaml` (or the user overlay with `global: true`); does not auto-start
- `health` lists failed units (read-only); `heal` resets and restarts them (`unit` for one, omit for all); `mode_get` reports the macOS worker runtime, `mode_set` switches it (`mode`: exec|container)
- Stripe secret is read from `.env` (STRIPE_SECRET / STRIPE_SECRET_KEY / STRIPE_API_KEY); `stripe_config` sets webhook_path / secret_env_key in `.lerd.yaml`
- **Auto-reload (CLI-only)**: `lerd horizon:reload [on|off]` and `lerd octane:reload [on|off]` (FrankenPHP worker mode) restart workers on file changes; both need the project's `chokidar` npm package
- **Idle-suspend (CLI-only)**: `lerd idle on/off` toggles activity-driven suspension globally; suspended workers stop after the idle timeout (`lerd idle timeout <dur>`) and resume on the next request/CLI/MCP/file-save. `lerd idle pin/unpin <site>` exempts a site; `lerd idle status` reports policy and last-active. A worker shown as suspended is healthy, not failed, so do not `heal` it

#### `exec` — run tooling in the PHP-FPM container
Actions: `artisan` (Laravel), `console` (other frameworks), `composer`, `vendor_bins`, `vendor_run`, `commands_list`, `commands_run`, `command_add`, `command_remove`.
- `artisan`/`console`/`composer` take `args` (array); tinker must use `--execute=<code>` for non-interactive use
- `vendor_run` is the right way to run project tooling (pest, phpunit, pint, phpstan, rector) — call `vendor_bins` first to discover what's installed, then `vendor_run` with `bin` + `args`; prefer it over `composer exec`
- `commands_*`/`command_*` list, run, add and remove the on-demand commands in a site's `.lerd.yaml` `commands:` block; `commands_run` needs `force: true` for confirm-gated commands

#### `framework` — framework definitions & scaffolding
Actions: `list`, `add`, `remove`, `search`, `install`, `project_new`, `setup`.
- `add` with `name: "laravel"` merges custom workers/setup into the built-in framework
- `search`/`install` use the community store (install auto-detects version from `composer.lock`)
- `project_new` scaffolds a new project (requires absolute `path`, default framework laravel); follow with `site` `link` + `env` `setup`
- `setup` runs the framework's post-install steps (migrations, storage:link…) — MANDATORY after `env setup` on new/cloned projects; idempotent

#### `diag` — diagnostics & observability
Actions: `status`, `doctor`, `which`, `check`, `dns_diagnose`, `bug_report`, `analyze_queries`, `dumps_recent`, `dumps_status`, `dumps_clear`, `dumps_toggle`, `profiler_toggle`, `profiler_status`, `profiler_clear`, `xdebug_on`, `xdebug_off`, `xdebug_status`.
- `status` (DNS/nginx/FPM/watcher health) and `doctor` (full JSON diagnostic) are the first stops when something is broken; `dns_diagnose` walks the DNS chain
- reading logs lives in the `logs` tool (below), not here
- `which` shows resolved PHP/Node/docroot/nginx for a site; `check` validates `.lerd.yaml`
- debug bridge loop: `dumps_toggle` (enable) → `dumps_clear` → hit the page → `analyze_queries` (N+1 / slow-query report with file:line) or `dumps_recent` (filter by site/branch/ctx/kind/since/limit)
- `profiler_*` toggle the global SPX profiler and surface the flame-graph UI; `xdebug_*` control Xdebug on port 9003 (`mode` defaults to debug)
- `bug_report` writes an anonymised diagnostic report for a GitHub issue

#### `logs` — read logs from any source, filtered
Actions: `sources`, `fetch`. Debug without opening files by hand.
- `sources` lists every queryable source for the site plus shared infra: `app:<file>` (framework log files), `fpm`, `worker:<name>` (queue/horizon/schedule/custom), and the globals `nginx`, `dns`, `watcher`, `ui`, services, `php<ver>`. Call it first to learn the names
- `fetch source=<name>` reads one source. Filter with `grep` (regex, falls back to literal substring), `since`/`until` (relative like `15m`/`1h`/`2h30m`, or a timestamp), `level` (app logs only: error/warning/info/debug), and `lines` (default 50)
- streaming is polling: every `fetch` returns an opaque `cursor`; call again with `since=<cursor>` (or `cursor=<cursor>`) to get only the new lines. The cursor format differs per backend, so treat it as opaque and echo it back
- entries come back chronological (oldest first). Raw logs with no timestamps ignore `since`/`level` and just return the last N; a not-running container returns partial output, not an error

#### `worktree` — git worktrees
Actions: `list`, `add`, `remove`, `db_isolate`, `db_share`.
- `add` installs deps and offers an asset-worker / npm-build prompt; secured sites get `*.<branch>.<site>.test` wildcard cert SANs + nginx `server_name` automatically
- `db_isolate` gives a worktree its own database (seed via `source`: empty|main|<branch>); `db_share` points it back at the main; `remove` keeps an isolated DB unless `keep_db: false`

### Key conventions

- Pass `action` on every tool; `path` is optional on most and defaults to the directory the assistant was opened in
- Discover before acting: `site` `list` for sites, `worker` `list` for a site's workers, `service` `preset_list` before `preset_install`, `exec` `vendor_bins` before `vendor_run`
- On a fresh Laravel clone (DB_CONNECTION=sqlite), call `db` `set` before `env` `setup` to choose a database deliberately, then run `framework` `setup`
- **Domain conflicts on link**: the parked-directory watcher filters out a domain another site already owns and prints `[WARN] domain "X" already used by site "Y" — skipped`, registering the site with surviving domains (falling back to `<dirname>.<tld>`); `.lerd.yaml` is not modified. The `site` `link` and `site` `domain_add` actions instead hard-error on conflicts so you can react — read the error for the owning site name
- **Custom APP_URL**: `env` `setup` writes `<scheme>://<primary-domain>`; override via `app_url` in `.lerd.yaml` (committed) or the per-machine `sites.yaml` entry, then re-run `env setup`
- Built-in service hosts follow `lerd-<name>` (e.g. `lerd-mysql`, `lerd-redis`, `lerd-postgres`); default DB credentials are username `root`, password `lerd`
- **Custom container sites** (Node.js, Python, Go, …) — mandatory order: (1) write a Containerfile (default `Containerfile.lerd`); (2) write `.lerd.yaml` with `container: {port: <N>}` (plus optional `domains`, `services`, `secured`); (3) configure the project's `.env` with service hosts (`lerd-mysql`, etc.) and start needed services via `service` `start`; (4) call `site` `link`. Never link before steps 1–3 or the site registers as PHP-FPM; if that happens, `site` `unlink`, write the files, then link again
- Worker unit names follow `lerd-<worker>-<site>` (per-worktree: `lerd-<worker>-<site>-<branch>`)

<!-- lerd:end -->

IMPORTANT: When applicable, prefer using phpstorm-index MCP tools for code navigation and refactoring.
