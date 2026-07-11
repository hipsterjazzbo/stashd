# Plan 010: Batch broadcast context loading

> **Executor instructions**: Preserve readiness semantics exactly: absent media, non-ready media, missing/non-ready/pathless Vault original, and active/ignored stash items are distinct states.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Broadcasts/BroadcastContextFactory.php app/Broadcasts/BroadcastController.php app/Stashes/StashItemRepository.php app/Vault/AssetRepository.php tests`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: perf
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

`StashItemRepository::listForStash()` already eager-loads media items, but `BroadcastContextFactory` queries each media item again and then queries its Vault original one at a time. Broadcast list mapping invokes impact per broadcast, multiplying the cost for a large stash. Reuse loaded records, bulk-load assets, and share the per-stash context where response semantics permit.

## Current state

- `app/Stashes/StashItemRepository.php:72-84` uses `->with('mediaItem')`.
- `app/Broadcasts/BroadcastContextFactory.php:39-70` calls `MediaItemRepository::find()` and `AssetRepository::findByMediaItemAndRole()` inside a loop.
- `app/Broadcasts/BroadcastController.php:407-414` calls lifecycle impact while mapping each broadcast.
- `AssetRepository::totalSizeBytesByMediaItem()` is a batching style exemplar.
- Broadcast feature tests cover policy, destinations, season mapping, and plugin listing.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Broadcast tests | `composer test:feature -- --filter='Broadcast.*Test'` | pass |
| Full suite | `composer test` | pass |
| Analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: broadcast context factory/controller, asset repository batch query, and focused broadcast tests.

**Out of scope**: changing API response JSON, pagination policy, broadcast plugin interfaces, or database schema unless a proven index is separately needed.

## Steps

### Step 1: Reuse eager-loaded media items

Confirm Tempest relation access on `StashItemRecord` and use it when loaded, retaining the current null/missing fallback. Do not assume every caller has eager-loaded the relation unless the factory API makes that contract explicit.

**Verify**: unit/feature test covers missing relation/media behavior equivalently to current logic.

### Step 2: Add a batch Vault-original lookup

Add a repository method accepting media-item IDs and returning ready Vault originals keyed by ID. It must not substitute other asset roles or accept pathless/non-ready assets.

**Verify**: test empty input, multiple ready assets, non-ready assets, and missing paths.

### Step 3: Eliminate repeated list-impact context construction

Inspect lifecycle `impact()` inputs and restructure the stash broadcast-list path to compute shared stash facts once where all broadcasts use the same stash. Preserve each broadcast's plugin-specific output and item list.

**Verify**: response snapshots/assertions retain exact keys and counts; query-count instrumentation or a fake repository proves bounded queries.

### Step 4: Run regression suite

**Verify**: `composer test && composer test:static` -> pass.

## Done criteria

- [ ] Context building does not query media and Vault asset rows per stash item.
- [ ] List mapping avoids rebuilding identical stash context per broadcast.
- [ ] Readiness and ignored-item behavior is unchanged.
- [ ] Public resource shape is unchanged.
- [ ] Full tests and PHPStan pass.

## STOP conditions

- Tempest eager-loaded relations cannot be distinguished from absent relations safely.
- Plugin impact requires mutable context per broadcast in a way that prevents sharing.
- Batching changes asset selection order or broadcast validity.

## Maintenance notes

Future context additions must be loaded in batches. Add a query-count regression test when a repository instrumentation pattern is available.
