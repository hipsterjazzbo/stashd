# Plan 015: Reconcile runtime documentation

> **Executor instructions**: Update current canonical guidance, not historical migration plans. Preserve historical RoadRunner references where they are clearly labeled as history.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- docs/Stashd-Engineering-Specification.md docs/agent-context.md docs/providers/README.md docs/runtime/frankenphp.md README.md`

## Status

- **Priority**: P3
- **Effort**: M
- **Risk**: LOW
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: docs
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Canonical specification and agent context still state that RoadRunner provides HTTP and worker runtime, while the shipped runtime is FrankenPHP classic with supervisord-managed worker/scheduler processes and Mercure. Provider documentation also describes an obsolete local-path ytdlphp dependency, despite the manifest requiring tagged `^1.0.2`. Contradictory operational guidance is harmful because it steers maintainers toward deleted code and invalid dependency maintenance.

## Current state

- `docs/Stashd-Engineering-Specification.md:183-197` names RoadRunner as the runtime and assigns worker supervision to it.
- `docs/agent-context.md:366-382` repeats that RoadRunner handles HTTP/workers.
- `docs/runtime/frankenphp.md:1-47` is the current authoritative runtime document: FrankenPHP classic, Caddy/Mercure, and independent supervised worker lanes.
- `docs/providers/README.md:51-57` says ytdlphp is a local Composer path dependency, but `composer.json:10-18` requires `hazel/ytdlphp:^1.0.2` via VCS repository.
- No root `README.md` currently exists.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Find stale current guidance | `rg -n 'RoadRunner|\.rr\.yaml|local Composer path|without a tagged release' docs AGENTS.md README.md` | only historical/explicitly labeled references remain |
| API docs test | `composer test:unit -- --filter=OpenApiSpecTest` | pass |
| Markdown review | `git diff --check` | exit 0 |

## Scope

**In scope**: canonical engineering specification, agent context, provider README, a new concise root README, and links to existing runtime/verification docs.

**Out of scope**: historical documents under `docs/plans/`, source comments that accurately describe migration history, changing runtime code, Docker configuration, Composer dependencies, or product branding.

## Steps

### Step 1: Establish current source of truth

Treat `docs/runtime/frankenphp.md`, current Docker files, and `composer.json` as the runtime/dependency facts. Update current-state sections of the specification and agent context to say FrankenPHP classic serves HTTP; supervisord runs worker lanes/scheduler; Mercure provides real-time updates; SQLite remains v1 database.

**Verify**: each updated runtime claim has a current code/config/document source.

### Step 2: Correct ytdlphp maintenance guidance

Replace obsolete local-path/copy-mode instructions with the actual Composer requirement/repository/update workflow. Preserve the architectural rule that ytdlphp is the only yt-dlp process boundary.

**Verify**: `composer.json` and docs agree on package name/version source; no local absolute development path remains in current instructions.

### Step 3: Add a concise operator README

Create root `README.md` as a navigation/start page, not a duplicate specification. Include product sentence, Docker-first quick start, ports/volumes only when verified from current configuration, verification commands, upgrade pointer, and links to runtime, provider, storage, broadcast, and architecture docs.

**Verify**: every command/link targets an existing file or current configuration; no secret/example credential is added.

### Step 4: Preserve historical references deliberately

Do not bulk-replace RoadRunner in historical plans. Where a current agent template points at deleted `.rr.yaml`, update it; leave explicitly historical migration rationale intact.

**Verify**: stale-reference search returns only clearly historical context; `git diff --check` and OpenAPI test pass.

## Done criteria

- [ ] Canonical current docs consistently describe FrankenPHP classic, supervisord workers, and Mercure.
- [ ] ytdlphp install/update instructions match `composer.json`.
- [ ] Root README provides a verified current entry point without duplicating architecture docs.
- [ ] Historical RoadRunner plans remain historical rather than silently rewritten.
- [ ] Stale-current-guidance search and documentation checks pass.

## STOP conditions

- Current Docker/runtime configuration conflicts with `docs/runtime/frankenphp.md` and cannot be resolved from code.
- Required quick-start values are not represented in committed configuration.
- Correcting a document would invalidate a published support/compatibility promise.

## Maintenance notes

When runtime or Composer boundary changes, update the root README, agent context, and canonical spec in the same change. Keep detailed migration history under `docs/plans/`, not in operator instructions.
