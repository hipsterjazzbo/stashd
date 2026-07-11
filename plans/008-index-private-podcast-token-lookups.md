# Plan 008: Index private podcast-token lookups

> **Executor instructions**: Preserve non-revealing 404 behavior, token rotation, encryption at rest, and final constant-time plaintext confirmation. Never store raw tokens or write them to tests/logs.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Broadcasts/Podcasts app/System/Secret app/Database tests/Feature/Phase5CPodcast*`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: perf
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Public podcast feed and episode requests currently decrypt every candidate token. Each decrypt updates secret `lastUsedAt`, turning an unauthenticated read path into O(N) SQLite writes. Add an indexed deterministic blind lookup digest so routes find one candidate before decrypting and comparing it.

## Current state

- `app/Broadcasts/Podcasts/PodcastTokenService.php:51-65` loops over broadcasts and compares decrypted feed tokens.
- `PodcastTokenService.php:104-120` loops over every broadcast item for episode tokens.
- `app/System/Secret/SecretsService.php:53-65` decrypts then writes `lastUsedAt` for each `get()`.
- Public controllers are `app/Broadcasts/PodcastFeedController.php` and `PodcastEpisodeController.php`; their unknown/revoked/cross-broadcast responses are intentionally indistinguishable.
- Token route tests are `tests/Feature/Phase5CPodcastFeedRouteTest.php` and `Phase5CPodcastEpisodeRouteTest.php`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Podcast routes | `composer test:feature -- --filter=Phase5CPodcast` | pass |
| Full suite | `composer test` | pass |
| Analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: secret/token persistence schema and repositories, podcast token service, forward migration/backfill, and podcast route tests.

**Out of scope**: changing URL shapes, weakening encryption, exposing token previews, changing feed/episode resource semantics, or redesigning SecretsService globally without evidence.

## Steps

### Step 1: Design the blind index

Derive a deterministic keyed digest from each generated raw token using an existing application secret/key material or a specifically documented server secret. Store only the digest in an indexed nullable column associated with the secret/token record. Raw tokens remain encrypted exactly as today; confirm the decrypted candidate with `hash_equals`.

**Verify**: unit tests show equal raw tokens map to equal digest, different tokens do not, and no raw token is persisted in the lookup field.

### Step 2: Add a forward migration and backfill path

Add an index and backfill existing active token records safely. Since existing encrypted values cannot be queried directly, use a bounded one-time application migration/backfill that decrypts each record, derives the digest, and writes it. Decide handling for revoked/malformed secrets explicitly.

**Verify**: migration test covers legacy active and revoked records and an idempotent second boot.

### Step 3: Replace scans with scoped indexed lookup

Resolve the feed token by digest, then confirm decrypted token and podcast type/revocation. Resolve episode token by digest plus the already-resolved broadcast scope. Ensure mismatches return the same non-revealing null/404 result.

**Verify**: feed, revoked, cross-broadcast, and extension-mismatch tests remain green; add a repository test proving lookup is scoped.

### Step 4: Limit read-path writes

Ensure successful public resolution updates only the matched secret's usage metadata, not every candidate. Preserve any audit semantics deliberately.

**Verify**: focused test/assertion shows one matched secret update per valid request and no candidate scan.

## Done criteria

- [ ] Public token lookup is indexed and decrypts at most the matched candidate.
- [ ] Raw tokens remain encrypted at rest and absent from logs/resources/migrations.
- [ ] Rotation and invalid-token routes retain non-revealing behavior.
- [ ] Existing records backfill safely and idempotently.
- [ ] Podcast tests, full suite, and PHPStan pass.

## STOP conditions

- No stable server secret can safely key a blind index across deployments/backups.
- Backfill would require exposing raw token values outside SecretsService.
- Existing token rows cannot be distinguished from unrelated secret types safely.

## Maintenance notes

Keep blind-index derivation inside the token/secret boundary. Rotation must always write a new digest and revoke the old record; never reuse a digest across token types without an explicit domain separator.
