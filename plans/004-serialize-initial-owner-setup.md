# Plan 004: Serialize initial owner setup

> **Executor instructions**: Follow the plan exactly, verify each step, and stop rather than improvising. Update the index after completion.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Auth app/Database/CreateDomainSchema.php tests/Feature/AuthTest.php`

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: security
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Stashd is intentionally single-owner. Both `AuthService::setupAdmin()` and `UserRepository::createAdmin()` check user count before inserting, but neither serializes concurrent first setup requests and the schema only makes usernames unique. A losing concurrent setup request must receive the existing non-revealing setup-completed response; it must never create a second admin.

## Current state

- `app/Auth/AuthService.php:39-57` checks `UserRecord::count()` before calling `createAdmin()`.
- `app/Auth/UserRepository.php:33-52` repeats the count check then inserts independently.
- `app/Database/CreateDomainSchema.php:64-72` defines unique username/email, not a singleton-owner constraint.
- `app/Auth/AuthController.php:31-72` maps `SetupAlreadyCompleted` to its established response.
- `tests/Feature/AuthTest.php` contains first-setup and repeat-setup coverage.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Auth tests | `composer test:feature -- --filter=AuthTest` | pass |
| Full suite | `composer test` | pass |
| Analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: setup service/repository, a forward SQLite migration if required to encode the invariant, and auth feature tests.

**Out of scope**: multi-user support, login/session behavior, API token design, password policy, and global rate limiting.

## Steps

### Step 1: Choose a database-enforced singleton mechanism

Use a short SQLite write transaction with an immediate/appropriate lock or add a schema-backed singleton guard that works for existing databases. The invariant must be enforced at the persistence boundary, not only in the HTTP controller. Do not edit an already-applied migration; add a forward migration if schema changes are needed.

**Verify**: a direct repository-level test with separate connections cannot create two admins.

### Step 2: Preserve the setup API contract

Have the service translate the losing concurrent insert/guard outcome to `SetupAlreadyCompleted`. Keep the current owner setup success body and the generic completed response unchanged.

**Verify**: existing normal setup and repeated setup tests pass unchanged.

### Step 3: Add concurrent setup coverage

Create a deterministic test using two independently configured SQLite connections or a test seam that pauses after the initial count. Assert exactly one user exists and exactly one request succeeds.

**Verify**: `composer test:feature -- --filter=AuthTest && composer test:static` -> pass.

## Done criteria

- [ ] The single-owner invariant is enforced in SQLite, not merely by a pre-insert count.
- [ ] Concurrent setup creates exactly one admin.
- [ ] Losing setup requests retain the established completed/conflict response.
- [ ] Auth tests, full tests, and PHPStan pass.

## STOP conditions

- SQLite transaction semantics cannot be made deterministic across the Tempest connection layer.
- Enforcing the invariant requires destructive modification of existing user data.
- The repository already supports a legitimate multi-owner migration path.

## Maintenance notes

Any future multi-user design must deliberately replace this invariant and migrate existing single-owner data. Do not weaken it as an incidental prerequisite for another auth change.
