# Plan 003: Make command dispatch durable

> **Executor instructions**: Follow each step and verification. Stop on any STOP condition; do not broaden scope. Update the index when complete.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Commands app/System/Activity app/Jobs tests/Feature/Phase2ExecutionTest.php`

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: bug
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Creating an accepted command, its job rows, and durable activity is one user-visible operation. Current independent writes can leave accepted commands that never have a job, while real-time events may describe data that later rolls back. Make the database portion transactional and publish only after commit, preserving the existing command-handler registry and response shape.

## Current state

- `app/Commands/CommandDispatchService.php:28-41` creates the command, emits `commandAccepted`, creates jobs, then publishes job-created events without a transaction.
- `app/Downloads/ItemDownloadCommandHandler.php:76-94` mutates/saves the command before creating its job.
- `app/System/Activity/ActivityEventService.php:24-34` writes activity through `emit()`; `ActivityEventRepository.php:24-58` inserts it into SQLite.
- `app/Stashes/StashRepository.php:171` contains the repository's transaction precedent for multi-write behavior.
- Existing command/job tests are `tests/Feature/Phase2ExecutionTest.php` and `tests/Feature/Phase2HardeningTest.php`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Focused tests | `composer test:feature -- --filter=Phase2ExecutionTest` | pass |
| Full suite | `composer test` | pass |
| Analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: command dispatch service/handlers necessary for the transaction boundary, the activity/event boundary, and focused Phase 2 feature tests.

**Out of scope**: introducing a general queue package, changing command API JSON, changing existing command types, or adding a distributed outbox system.

## Steps

### Step 1: Map all writes performed during dispatch

List each command handler's `createJobs()` writes, including command normalization writes. Establish a transaction boundary that includes command insertion, handler-owned command updates, job inserts, and durable activity insertion. Use the existing SQLite transaction pattern; do not hold a transaction across network or Mercure publishing.

**Verify**: add a focused test double/failure seam that can fail before commit and assert no accepted command/job/activity row survives.

### Step 2: Commit durable state before publishing

Refactor `CommandDispatchService::dispatch()` so activity persistence and job creation commit together. Publish `jobCreated` notifications only after the transaction commits. Preserve response extras and command/job IDs.

**Verify**: existing command dispatch test still sees accepted command, activity, and job; a forced pre-commit failure leaves none.

### Step 3: Add failure characterization tests

Cover command insert/activity failure and handler-job creation failure. Assert retrying after either failure cannot leave or return an accepted command with no intended job.

**Verify**: `composer test && composer test:static` -> pass.

## Done criteria

- [ ] Accepted commands never persist without their handler-created jobs.
- [ ] Durable activity is committed with command/job state.
- [ ] Real-time job-created notifications occur only after commit.
- [ ] Existing response/resource behavior remains unchanged.
- [ ] Full tests and PHPStan pass.

## STOP conditions

- A handler performs I/O or dispatches nested commands inside the proposed transaction.
- A transaction API cannot cover all current handler writes without changing public behavior.
- A failure test exposes pre-existing duplicate-side-effect behavior requiring broader idempotency design.

## Maintenance notes

New command handlers must keep `createJobs()` database-only. If later event delivery needs durability, add a dedicated outbox plan instead of quietly moving network publication into this transaction.
