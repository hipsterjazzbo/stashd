# Plan 007: Throttle login attempts

> **Executor instructions**: Build defensive, bounded server-side throttling. Do not log passwords, raw tokens, or full client addresses in activity metadata.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Auth app/Http app/Database tests/Feature/AuthTest.php`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: `plans/004-serialize-initial-owner-setup.md`
- **Category**: security
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

`/api/v1/auth/login` is deliberately public but currently has unlimited username lookup and password verification. Default deployment publishes port 8474, so reachable clients can make unlimited guesses or consume CPU. Add bounded, expiry-based throttling keyed by normalized username and a trustworthy client identity, retaining generic invalid-credential responses.

## Current state

- `app/Http/Middleware/RequireAuthMiddleware.php:18-22` lists login as public.
- `app/Auth/AuthService.php:60-74` performs lookup and `password_verify()` with no attempt accounting.
- `docker-compose.yml:5` publishes the default HTTP port.
- `app/Http` already owns request/middleware concerns; controllers should validate/adapt only.
- Auth behavior is covered by `tests/Feature/AuthTest.php`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Auth tests | `composer test:feature -- --filter=AuthTest` | pass |
| Full suite | `composer test` | pass |
| Analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: a bounded SQLite-backed or in-memory-with-expiry login limiter appropriate to classic FrankenPHP, login service/controller/middleware integration, migration if persistence needs it, and auth tests.

**Out of scope**: CAPTCHA, third-party rate-limiter services, account lockout emails, changing password hashes, or general API rate limiting.

## Steps

### Step 1: Define client identity and limit semantics

Use normalized username plus a trustworthy client-address resolver. Honor forwarded headers only under the repository's existing reverse-proxy trust policy; otherwise use the direct peer. Define threshold, rolling/fixed window, bounded exponential delay, reset on successful login, and an expiry/pruning strategy. Keep failure output generic.

**Verify**: unit tests cover normalization, expiry, and reset without raw values in assertions/logs.

### Step 2: Enforce before expensive verification

Integrate the limiter so blocked attempts do not call password verification. Record failed attempts for unknown and known usernames consistently enough not to reveal account existence. Return the existing error envelope with an appropriate stable status/message contract.

**Verify**: feature tests demonstrate threshold blocking, successful-login reset, expiry recovery, and identical invalid responses.

### Step 3: Validate proxy behavior and bounded storage

Test direct versus trusted forwarded client address behavior and confirm stale limiter records are bounded/pruned. Do not add activity events containing identifiers that expand sensitive logging.

**Verify**: `composer test && composer test:static` -> pass.

## Done criteria

- [ ] Repeated public login failures are throttled server-side.
- [ ] Successful login resets the relevant failure record.
- [ ] Unknown usernames do not receive distinguishable behavior.
- [ ] Storage/memory remains bounded through expiry/pruning.
- [ ] Full tests and PHPStan pass.

## STOP conditions

- The app has no reliable client-address signal behind supported reverse proxies.
- The only practical implementation requires a required external service.
- Existing product requirements mandate no login delay/limit.

## Maintenance notes

Rate-limit keys are security-sensitive metadata. Keep them short-lived, never expose them through the API, and revisit proxy trust when deployment documentation changes.
