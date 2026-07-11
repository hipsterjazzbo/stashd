# Plan 014: Add deterministic authenticated browser coverage

> **Executor instructions**: Use fixture-backed Fake provider flows only. Live YouTube, real downloads, and ffmpeg remain manual preship coverage and must not become PR dependencies.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- e2e playwright.config.ts package.json src/main.entrypoint.ts tests docker`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: tests
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

Routine Playwright currently checks only redirect/login form rendering. The primary authenticated UI, polling/Mercure progress handling, stashes, Vault, and broadcasts have no deterministic browser coverage; the comprehensive spec deliberately uses live media and is excluded. Add one reliable seeded happy path that catches browser-only regressions without making CI depend on external providers.

## Current state

- `e2e/smoke.spec.ts:3-18` contains two signed-out tests.
- `playwright.config.ts:4-7` excludes `e2e/manual/`.
- `e2e/manual/preship-smoke.spec.ts:3-7` requires live YouTube/ffmpeg and long runtimes.
- `src/main.entrypoint.ts` owns shared event subscription and UI command completion handling.
- Fake provider fixtures and authenticated API tests exist under `tests/fixtures/providers/fake/` and `tests/Feature/`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Routine browser suite | `npm run test:e2e` | pass without live flags |
| PHP setup/test support | `composer test` | pass |
| Container run if required | `composer test:docker-smoke` | pass |

## Scope

**In scope**: routine E2E spec/support, Playwright configuration/scripts, deterministic test-app startup/seeding glue if already supported by repository conventions, and CI invocation after Plan 001.

**Out of scope**: modifying the manual preship workflow, live provider credentials, real download/ffmpeg behavior, UI redesign, or exhaustive browser coverage of every page.

## Steps

### Step 1: Establish disposable deterministic test state

Choose the smallest existing supported mechanism to start a clean app and seed owner/fake-provider data. Prefer API setup/preflight/create calls over direct database writes so browser tests exercise real contracts. Ensure each test owns isolated data and can run in parallel or mark serial only with justification.

**Verify**: test startup needs no `STASHD_LIVE_*` flag, external token, or real provider request.

### Step 2: Cover one authenticated end-to-end workflow

Add a spec that creates/logs in the owner, starts from a Fake provider URL, completes preflight/create or add-input flow, waits for command terminal state using bounded assertions, opens the Stash and Vault views, and verifies one broadcast/command progress-visible state. Use stable DOM identifiers, not text tied to incidental copy.

**Verify**: `npm run test:e2e` passes twice consecutively without retries.

### Step 3: Make CI run routine E2E deliberately

Extend the Plan 001 workflow only after the deterministic start path is proven. Use a separate named job if it requires app/container lifecycle; cache browsers/dependencies where appropriate. Keep manual suite excluded.

**Verify**: Actions executes routine E2E on pull requests with no live provider configuration.

## Done criteria

- [ ] Routine E2E authenticates and exercises one Fake-provider stash workflow.
- [ ] It proves a command completion/progress UI path and authenticated navigation.
- [ ] It runs without live media, credentials, or external provider access.
- [ ] Manual preship live test remains opt-in and excluded.
- [ ] Routine E2E is a documented CI gate after reliability is proven.

## STOP conditions

- The app cannot be seeded/startable deterministically without production-only secrets.
- Mercure timing makes the test flaky after bounded polling fallback.
- Required CI browser dependencies demand Dockerfile/product-runtime changes.

## Maintenance notes

Keep the routine suite short and business-critical. Add focused browser tests only for regressions that API/feature tests cannot observe; leave broad live-media validation in the manual preship suite.
