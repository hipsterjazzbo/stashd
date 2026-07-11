# Plan 009: Index persistent job workloads

> **Executor instructions**: Add forward-only indexes based on observed queries. Do not alter applied foundation migrations or claim semantics.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Database app/Jobs tests/Feature`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: perf
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Workers poll the persistent `jobs` table continuously. The original schema has no job secondary indexes, while claims filter pending state/schedule/lane and sort by priority/creation; stale recovery filters processing heartbeat; history filters entity/intent. As history grows, these become scans and temporary sorts. Add only query-supported compound indexes through a new migration and prove SQLite uses them.

## Current state

- `app/Database/CreateFoundationSchema.php:78-101` creates `jobs` with no secondary indexes.
- `app/Jobs/JobRepository.php:83-120` claims by `state`, `scheduledAt`, optional `intent`, `priority`, and `createdAt`.
- `JobRepository.php:124-128` selects stale processing jobs by `state` and `heartbeatAt`.
- `JobRepository.php:191` and related history methods filter entity/intent and order by creation.
- Existing migrations are forward-only; see recent migration classes under `app/Database/`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Schema/job tests | `composer test:feature -- --filter='DomainSchemaTest|Phase2ExecutionTest'` | pass |
| Full suite | `composer test` | pass |
| Analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: one new migration, job-repository query tests/fixtures, and schema tests.

**Out of scope**: rewriting JobRepository, changing job fields/order semantics, deleting old jobs, or changing SQLite configuration.

## Steps

### Step 1: Capture representative query plans

On a fixture database with enough jobs to exercise the predicates, run `EXPLAIN QUERY PLAN` for pending claim (with and without lane), stale recovery, and entity download history. Record the exact existing predicates before choosing indexes.

**Verify**: test/helper asserts plans avoid full table scans and temporary sorting where an index can cover the query.

### Step 2: Add a forward migration with workload-specific indexes

Add compound indexes matching the actual query order, likely separate indexes for pending claim, stale recovery, and entity history. Do not edit `CreateFoundationSchema`; old databases must receive the indexes through normal migration boot.

**Verify**: fresh schema and migrated schema both contain the expected indexes.

### Step 3: Preserve queue behavior

Run claim/concurrency/stale-recovery behavior tests unchanged. Indexes are an optimization, not a reason to change the guarded update.

**Verify**: `composer test && composer test:static` -> pass.

## Done criteria

- [ ] A forward migration adds only query-supported job indexes.
- [ ] Representative `EXPLAIN QUERY PLAN` output avoids table scans for the targeted workload.
- [ ] Fresh installs and upgrades receive the same indexes.
- [ ] Job claim/stale/history behavior remains unchanged.
- [ ] Full tests and PHPStan pass.

## STOP conditions

- SQLite query plans show Tempest emits materially different SQL than expected.
- A proposed index does not improve the target plan or causes an unacceptable write trade-off.
- The migration framework cannot assert index presence on both fresh and upgraded databases.

## Maintenance notes

Review query plans whenever new worker lanes or job history endpoints add predicates. Avoid speculative indexes; every index increases write amplification on a busy SQLite database.
