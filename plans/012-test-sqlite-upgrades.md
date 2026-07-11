# Plan 012: Test SQLite upgrades from supported schemas

> **Executor instructions**: Fixtures must be non-secret, minimal, versioned, and copied before mutation. Never modify a committed legacy database fixture in place.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Database app/System/Boot/MigrationRunner.php tests/Feature/BootstrapAndHealthTest.php tests/docker/smoke.sh`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: tests
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Fresh boot and Docker smoke tests create empty data directories. Existing migration tests remove one migration record and invoke a private backup helper, which does not prove a populated database from a prior schema upgrades correctly. Add a compact supported-version fixture or schema builder that validates backup, migration chain, preserved representative data, and idempotent next boot.

## Current state

- `tests/docker/smoke.sh:61` creates empty temporary data/media directories.
- `tests/Feature/BootstrapAndHealthTest.php:92-124` tests missing migration records and invokes `MigrationRunner::backupIfExists()` via reflection.
- `app/System/Boot/MigrationRunner.php:26-33` backs up an existing database when pending migrations exist, then invokes Tempest migrations.
- The product promises SQLite migration backups; no previous-release fixture directory currently exists.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Migration test | `composer test:feature -- --filter=BootstrapAndHealthTest` | pass |
| Full suite | `composer test` | pass |
| Docker smoke | `composer test:docker-smoke` | pass where Docker is available |

## Scope

**In scope**: non-secret SQLite test fixture/builder, boot/migration tests, and narrowly related test helpers.

**Out of scope**: migration production code changes unless the test exposes a specific defect, backup retention redesign, database restore UI, and Vault backup implementation.

## Steps

### Step 1: Define supported upgrade boundary

Choose the oldest schema/release the project claims to support, based on migration history and release policy. Record fixture provenance in a nearby test README/comment without embedding production paths or secrets.

**Verify**: reviewer can identify the exact migration state represented by the fixture.

### Step 2: Create a minimal legacy database

Commit a small SQLite fixture or deterministic builder with migration records and representative rows across critical tables: owner/token metadata without raw secrets, stash/input/media membership, command/job, and asset/broadcast data as applicable. Copy it to a test temp path before boot.

**Verify**: fixture is non-empty, contains no raw credentials/tokens, and is unchanged after the test.

### Step 3: Boot current code against the copy

Assert a pre-migration backup exists, all migrations finish, representative records preserve identity/relationships, expected new columns/indexes exist, and a second boot produces no additional migration work or backup.

**Verify**: focused test fails if migration record ordering, backup, or row preservation regresses.

### Step 4: Keep fresh-install smoke separate

Do not overload `tests/docker/smoke.sh` with legacy fixture setup unless its current container path is the only way to reproduce boot behavior. Prefer fast feature coverage plus existing fresh-container smoke.

**Verify**: `composer test` -> pass; run Docker smoke where available.

## Done criteria

- [ ] A supported legacy schema/data path upgrades in tests.
- [ ] Test proves pre-migration backup, migration completion, data preservation, and idempotent second boot.
- [ ] Fixtures contain no secrets and are copied before mutation.
- [ ] Fresh-install smoke remains passing.

## STOP conditions

- There is no documented supported-version boundary to encode.
- A real legacy fixture would contain sensitive user material and cannot be minimized.
- Tempest migration boot cannot be isolated against a fixture copy.

## Maintenance notes

Add/refresh fixtures only at declared compatibility boundaries, not for every migration. When dropping old support, explicitly retire the corresponding fixture and document why.
