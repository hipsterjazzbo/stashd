# Plan 002: Atomically park retryable jobs

> **Executor instructions**: Follow each step and its verification. Stop and report on any STOP condition. Update this plan's row in `plans/README.md` only after every done criterion passes.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Jobs/JobWorkerService.php app/Jobs/JobRepository.php tests/Feature/DownloadRetryBackoffTest.php`

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: bug
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Retryable download failures must become scheduled pending jobs without an observable broken intermediate state. Today a job is saved as `processing` with a null heartbeat before a second save transitions it to `pending`; a process exit between those writes leaves a job stale recovery intentionally cannot see. The repair must make the state change and cleanup fields one durable operation while retaining the current retry backoff and worker-lane claim semantics.

## Current state

- `app/Jobs/JobWorkerService.php:157-172` currently performs:

  ```php
  $job->heartbeatAt = null;
  $job->ownerToken = null;
  $job->scheduledAt = DateTime::now(...)->plusSeconds(...);
  $this->jobs->save($job);
  $this->transitions->transitionJob($job, JobState::Pending);
  ```

- `app/Jobs/JobRepository.php:124-128` only recovers `processing` jobs with `heartbeatAt IS NOT NULL`.
- `JobRepository::claimNextPending()` is the local concurrency exemplar: it uses a guarded single SQL `UPDATE` and checks `rowCount()`.
- Existing behavior tests are in `tests/Feature/DownloadRetryBackoffTest.php`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Targeted test | `composer test:feature -- --filter=DownloadRetryBackoffTest` | pass |
| Full PHP test | `composer test` | pass |
| Static analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: `app/Jobs/JobWorkerService.php`, `app/Jobs/JobRepository.php`, `tests/Feature/DownloadRetryBackoffTest.php`, and any focused job-repository test created under `tests/Feature/`.

**Out of scope**: changing retry intervals, stale/hard-stall thresholds, job lane assignment, command-state behavior, or other job handlers.

## Steps

### Step 1: Add one guarded retry transition repository operation

Add a repository method that performs one SQL update guarded by job ID and `state = processing`. It must set `state = pending`, clear `startedAt`, `heartbeatAt`, and `ownerToken`, assign `lastError` and the computed `scheduledAt`, and update the in-memory record only after the database update succeeds. Match the parameterized SQL and `rowCount()` pattern in `claimNextPending()`.

**Verify**: a focused repository/feature test proves the persisted row is never `processing` with a null heartbeat after this operation.

### Step 2: Use the operation from retry handling

Replace the two-save block in `JobWorkerService::retryJob()` with the new operation. Keep the existing backoff index calculation and return early unless the in-memory job is processing.

**Verify**: `composer test:feature -- --filter=DownloadRetryBackoffTest` -> pass; existing retry test still observes `pending` and a future `scheduledAt`.

### Step 3: Characterize the interruption boundary

Add a test that exercises the repository operation directly and asserts every persisted retryable state has either `pending` plus cleared execution fields, or remains a valid processing record with a heartbeat. Do not test private methods by reflection if the repository API can express the behavior.

**Verify**: `composer test && composer test:static` -> both pass.

## Done criteria

- [ ] Retrying persists one atomic processing-to-pending transition.
- [ ] No repository path writes a retryable job as `processing` with null heartbeat.
- [ ] Existing retry-backoff and worker claim tests pass.
- [ ] `composer test` and `composer test:static` exit 0.

## STOP conditions

- Tempest's connection API cannot execute a parameterized guarded update in a testable way.
- The change requires altering stale recovery semantics or retry delays.
- Existing job state-transition tests prove the proposed transition is invalid.

## Maintenance notes

Future retryable job types must use this repository operation, never a save followed by `StateTransitionService`. Review any new direct writes to `heartbeatAt`, `ownerToken`, or `scheduledAt` for the same partial-state hazard.
