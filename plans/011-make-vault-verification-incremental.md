# Plan 011: Make Vault verification incremental and heartbeat-aware

> **Executor instructions**: Preserve every asset/media state transition. Do not weaken checksum verification merely to improve throughput.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Vault app/Jobs/Handlers/VerifyVaultJobHandler.php tests/Feature/ItemDownloadTest.php`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: perf
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Full Vault verification currently loads all ready assets, serially reads complete files for checksum verification, and saves every healthy record. The job heartbeats only before the scan, so a legitimate large Vault can exceed the hard-stall period and be killed/retried. Iterate deterministically in bounded pages and report progress/heartbeats during work without missing assets.

## Current state

- `app/Vault/AssetRepository.php:98-103` returns all ready assets.
- `app/Vault/VerifyVaultAssets.php:24-62` loops every asset; lines 91-107 checksum and save healthy records.
- `app/Jobs/Handlers/VerifyVaultJobHandler.php:42-76` heartbeats once then calls `verifyAll()`.
- `JobWorkerService` considers a live owner stalled after 1800 seconds without heartbeat.
- Item/Vault verification behavior is tested in `tests/Feature/ItemDownloadTest.php` and `Phase4AHardeningTest.php`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Vault tests | `composer test:feature -- --filter='ItemDownloadTest|Phase4AHardeningTest'` | pass |
| Full suite | `composer test` | pass |
| Analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: ready-asset pagination API, verification service callback/result types, verify-vault job progress/heartbeat, and tests.

**Out of scope**: changing checksum algorithm, disabling full verification, introducing background services, or changing storage-root unavailable behavior.

## Steps

### Step 1: Add stable bounded traversal

Add an AssetRepository page/cursor method ordered by stable primary key. The traversal must tolerate a batch ending exactly on a boundary and must not load the full result set. Determine total count only if it is cheap and needed for progress.

**Verify**: repository tests traverse more assets than one page with no duplicates/skips.

### Step 2: Surface per-item/batch progress safely

Allow `VerifyVaultAssets::verifyAll()` to report progress after each asset or bounded batch. `VerifyVaultJobHandler` must call `JobHandlerContext::progress()` or heartbeat during the scan, including when file checksums are slow.

**Verify**: test callback calls and persisted heartbeat/progress occur before completion.

### Step 3: Avoid unnecessary healthy writes only if semantics permit

`lastVerifiedAt` currently changes on every healthy check. Preserve that contract unless product owners explicitly approve changing it; do not skip saves merely as a micro-optimization if it would make verification timestamps false.

**Verify**: existing missing, checksum-mismatch, restored, and storage-unavailable tests retain their state/results.

### Step 4: Run regressions

**Verify**: `composer test && composer test:static` -> pass.

## Done criteria

- [ ] Full verification is paged in stable bounded batches.
- [ ] Long scans update job heartbeat/progress before completion.
- [ ] Missing, stale, restored, checksum, and unavailable outcomes are unchanged.
- [ ] Full tests and PHPStan pass.

## STOP conditions

- Asset changes during traversal make stable paging unsafe without a snapshot policy.
- Progress publication causes excessive DB/Mercure load and needs an unplanned rate-limit design.
- Preserving `lastVerifiedAt` requires writes that negate the proposed optimization; retain correctness and report the limited performance gain.

## Maintenance notes

Any future whole-Vault maintenance job needs the same heartbeat/paging discipline. Keep page size configurable only if there is a measured operational need.
