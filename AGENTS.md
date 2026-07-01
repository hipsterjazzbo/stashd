# AGENTS.md

## Purpose

This is the canonical instruction file for AI coding agents working on **Stashd**.

Read it before planning or editing. Keep it concise and current; do not turn it into a second engineering specification.

This file is intended to work for Claude Code, Hermes/Ollama, Aider, Codex/Cursor-style agents, and future local or remote coding assistants.

- Claude Code: `CLAUDE.md` points here.
- Hermes/Ollama: `.hermes.md` points here and to the longer Hermes docs.
- Long reusable procedures belong in `.claude/skills/`.
- Long reference notes belong in `docs/ai/` or existing architecture docs.

## Project identity

**Stashd** is self-hosted media preservation infrastructure.

It saves online video/audio sources into a local **Vault** and exposes generated views through **Broadcasts** such as Jellyfin libraries, Plex libraries, and private podcast feeds.

Core model:

```text
Stash → Vault → Broadcasts
```

The product should feel like quietly competent homelab infrastructure: fast, reliable, private, understandable, and boring to run.

Stashd is not a YouTube frontend, not a recommendation engine, and not a media player.

## Current stack

- PHP `^8.5`
- Tempest Framework `^3.0`
- RoadRunner application server / worker runtime
- SQLite for v1
- Pest for tests
- Laravel Pint with PSR-12 formatting
- `hazel/ytdlphp` wrapping `yt-dlp`
- Docker-first default deployment
- Default HTTP port: `8474`

## Repo map

Important directories:

```text
app/Auth           Single-owner auth, users, API tokens
app/Broadcasts     Generated views: podcasts, Jellyfin/Plex, triggers
app/Commands       Command records, dispatch, command handlers
app/Config         Runtime/application configuration
app/Console        CLI/runtime entry points
app/Database       SQLite migrations/database helpers
app/Downloads      Download orchestration and ytdlphp adapters
app/Http           HTTP/API concerns, resources, middleware
app/Jobs           Persistent job model and execution
app/MediaServers   Plex/Jellyfin integration
app/Providers      Provider strategies, URI handling, YouTube/Fake providers
app/Stashes        Stash creation, preflight, stash inputs/items
app/Support        Cross-cutting app support
app/System         Health/system/runtime concerns
app/Vault          Canonical media items and assets
tests/Unit         Pure unit tests
tests/Feature      HTTP/application behaviour tests
tests/docker       Docker smoke tests
docs/              Product, architecture, provider, storage, runtime, AI docs
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
docs/agent-context.md
docs/architecture/
docs/providers/
docs/storage/
docs/runtime/
docs/broadcasts/
docs/media-servers/
docs/foundation/
docs/ai/
```

Do not duplicate these docs in code comments or routine summaries. Link to or update them when a decision changes.

If present, `docs/ai/Stashd-Hermes-Agent-Reference.md` is long-form agent memory. Read only the relevant sections; do not load it reflexively for every small task.

## Operating principles

Prioritize:

1. Correct behavior.
2. Small, reviewable diffs.
3. Clear domain language.
4. Readable modern PHP.
5. Tempest-native patterns already present in the repo.
6. Tests for behavior changes.
7. Secret-safe logs, activity, jobs, command metadata, and public outputs.
8. Calm, dense, accessible UI/UX.
9. Docker runtime reliability.

Avoid:

- Clever abstractions.
- Large speculative rewrites.
- Framework cargo-culting from Laravel, Symfony, Rails, or Node apps.
- Vague names such as `Manager`, `Helper`, `Util`, `Processor`, `Data`, `Info`, or `Thing`.
- Generic methods named after the product, such as `stashd()`.
- Direct shelling out to `yt-dlp` from domain code.
- Raw URL strings leaking through domain internals when a URI/value object exists.
- Public-by-default behavior.
- Hidden filesystem or storage side effects.

## Non-negotiable architecture rules

- Feature-first layout under `app/`.
- Controllers validate/adapt HTTP only; no long-running synchronous controller work.
- Long-running work goes through commands/jobs.
- State changes go through `StateTransitionService`.
- Secrets go through `SecretsService`.
- Vault is canonical.
- Broadcasts are disposable/regeneratable views over Vault assets.
- Filesystem broadcasts are hardlink-first; do not silently copy when hardlinking is expected.
- Trigger failures are separate from broadcast file validity.
- ytdlphp is the only download/process boundary.
- SQLite DB columns use camelCase to match Tempest record properties.
- Public API JSON uses snake_case.
- Public/security-sensitive responses use explicit Resource DTOs/arrays; do not auto-serialize Tempest records directly.

## Security rules

Never expose or log:

- raw broadcast tokens
- raw item tokens
- secret ciphertext
- password hashes
- provider credentials
- media-server tokens
- internal filesystem paths in public XML/API unless intentionally designed and tested
- raw command/job payloads if they contain secrets/tokens

Podcast feed URLs use path tokens, not query tokens.

Invalid public-token routes should use non-revealing responses. Do not leak whether a broadcast/item exists.

## Agent modes and scope

### Claude Code / frontier coding agents

Use for broader but bounded slices, security-sensitive route/token work, lifecycle changes across controllers/commands/jobs/services/tests/docs, and larger refactors with a clear migration plan.

Even then:

- inspect local patterns first
- plan before broad edits
- keep diffs small
- stop when scope expands

### Hermes / local Ollama agents

Hermes/local models are useful but resource-constrained. Treat them as careful focused assistants, not autonomous architects.

Good tasks:

- selected-file review
- purpose-focused class docblocks
- small unit tests
- small DTO/resource cleanup
- naming/cohesion feedback
- explaining unfamiliar code
- narrow patches touching roughly 1-4 files

For broad/security-sensitive tasks, produce an audit/plan first rather than editing.

### Aider / terminal patch agents

Use for narrow git-aware patches against explicitly supplied files. Do not point a local model at the whole repo and ask for broad implementation.

## Before editing

For non-trivial tasks:

1. Check `git status --short`.
2. Identify unrelated worktree changes and leave them alone.
3. Inspect relevant existing files before inventing a pattern.
4. Summarize the existing local pattern.
5. Propose a short plan and likely files to change.
6. List checks/tests to run.
7. Wait for approval if the task is broad, risky, architectural, or security-sensitive.

For small obvious fixes, proceed directly but keep the diff minimal.

## After editing

Before claiming completion:

1. Run the narrowest relevant check first.
2. Run broader checks when the change justifies it.
3. Summarize files changed and why.
4. Explain how the change fits existing architecture.
5. List commands actually run.
6. State anything not run and why.
7. Note risks or follow-up work.

Never claim tests passed unless they were actually run and passed.

## Common commands

Use Composer scripts where available:

```bash
composer lint
composer format
composer test
composer test:unit
composer test:feature
composer test:parallel
composer test:docker-smoke
composer test:docker-smoke:no-build
```

For noisy output, prefer:

```bash
composer test 2>&1 | scripts/claude/trim-test-output.sh
```

Docker/runtime changes require inspecting:

```text
Dockerfile
docker-compose.yml
.rr.yaml
docker/
.env.example
tests/docker/
```

A build that passes unit tests but cannot boot reliably under Docker is not releasable.
