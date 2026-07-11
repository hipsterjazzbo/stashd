# Plan 006: Make add-input commits atomic and retry-safe

> **Executor instructions**: Do not hold a SQLite transaction across discovery or nested command dispatch. Follow verification gates and STOP if the boundary cannot remain database-only.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Stashes/CreateStashFromDiscovery.php app/Jobs/Handlers/AddInputJobHandler.php tests/Feature/StashAddInputTest.php`

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: HIGH
- **Depends on**: `plans/003-make-command-dispatch-durable.md`
- **Category**: bug
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Adding an input discovers items, creates an input and its graph, and may schedule downloads. The current method inserts the input before iterating items; any later failure commits an incomplete graph and a retry conflicts with the unique input constraint. Discovery must remain outside the write transaction, while graph persistence becomes atomic and retry converges safely.

## Current state

- `app/Stashes/CreateStashFromDiscovery.php:66-70` performs provider discovery before persistence.
- Lines 84-198 create `stash_inputs`, media items, sources, and stash items in separate writes.
- Lines 200-206 dispatch child `item.download` commands after graph construction.
- `app/Database/CreateDomainSchema.php:139` makes `(stashId, providerKey, providerInputId)` unique.
- `app/Jobs/Handlers/AddInputJobHandler.php:46-82` invokes `commitInput()` and completes its parent command/job afterward.
- Follow test structure in `tests/Feature/StashAddInputTest.php` and transaction failure coverage in `tests/Feature/StashUpdateDeleteTest.php`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Focused tests | `composer test:feature -- --filter=StashAddInputTest` | pass |
| Full suite | `composer test` | pass |
| Static analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: add-input commit service, repositories needed for a shared transaction, add-input job handling if required for post-commit scheduling, and focused tests.

**Out of scope**: changing provider discovery, bulk backfill behavior, automatic-download policy, media deduplication rules, or public stash APIs.

## Steps

### Step 1: Separate discovery from graph persistence

Keep `DiscoverStashInput::execute()` before the transaction. Convert validated discovery output into persistence-ready values without writing rows. Do not call provider code or nested command dispatch while holding the transaction.

**Verify**: existing fake-provider add-input success tests remain green.

### Step 2: Persist the complete graph transactionally and idempotently

Within one SQLite transaction, create or resolve the stash input, media items, sources, and stash-item membership. Define how a retry finds a prior partially-created input from older releases and completes/repairs it rather than failing on the unique constraint. Preserve first-input name/icon backfill only when the transaction commits.

**Verify**: inject failure after input creation and midway through items; assert rollback or a subsequent retry produces exactly one complete graph.

### Step 3: Dispatch downloads after commit

After the graph transaction commits, dispatch automatic downloads using Plan 003's durable dispatch path. Ensure retries do not schedule duplicate downloads for existing active membership.

**Verify**: automatic-download tests show commands only exist after committed membership and are not duplicated by retry.

### Step 4: Cover recovery and regressions

Add tests for malformed discovery entries, a forced persistence failure, retry after that failure, reused media items, ignored items, and first-input metadata backfill.

**Verify**: `composer test && composer test:static` -> pass.

## Done criteria

- [ ] No incomplete input graph is committed after a persistence exception.
- [ ] Retrying converges to one input, source, membership, and intended commands.
- [ ] Provider discovery and event publication are outside the SQLite write transaction.
- [ ] Existing filtering/ignored-item and deduplication behavior remains intact.
- [ ] Full tests and PHPStan pass.

## STOP conditions

- Repairing historical partial inputs needs a user-facing reconciliation policy.
- Dispatching child downloads cannot occur after commit without breaking command ownership.
- Required transaction support would change shared repository semantics beyond this flow.

## Maintenance notes

Future multi-input import paths must reuse this graph-persistence boundary. Review additions to `commitInput()` for external I/O or new side effects inside the transaction.
