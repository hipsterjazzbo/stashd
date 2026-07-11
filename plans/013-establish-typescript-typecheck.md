# Plan 013: Establish a TypeScript typecheck gate

> **Executor instructions**: Establish a clean explicit baseline; do not suppress diagnostics with broad `any`, `@ts-ignore`, or a blanket non-strict configuration.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- package.json vite.config.ts src/main.entrypoint.ts tsconfig.json .github/workflows/docker-image.yml`

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED
- **Depends on**: `plans/001-gate-image-publication-on-verification.md`
- **Category**: dx
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

The frontend is a 2,402-line TypeScript entrypoint handling UI state and Mercure events, but `package.json` has no typecheck script or `tsconfig.json`. Vite transpilation is not a reproducible type gate. An audit command already reports `src/main.entrypoint.ts:1861` calling `trim()` on `unknown`; this plan fixes the baseline and makes future mistakes fail locally and in CI.

## Current state

- `package.json:5-9` has `dev`, `build`, and Playwright scripts only.
- No `tsconfig*.json` exists.
- `src/main.entrypoint.ts:1861` has the current compiler diagnostic.
- The CI quality job is introduced by Plan 001; this plan may add typecheck there only after the local command is clean.
- Frontend build conventions are `vite.config.ts` and `src/main.entrypoint.ts`.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Typecheck | `npm run typecheck` | exit 0, no diagnostics |
| Build | `npm run build` | exit 0 |
| E2E (when configured) | `npm run test:e2e` | existing suite passes |

## Scope

**In scope**: `tsconfig.json`, `package.json`, minimal source typing needed for a clean baseline, and Plan 001's workflow only to invoke the new script.

**Out of scope**: frontend redesign, converting all JavaScript patterns, new UI features, Playwright expansion, or relaxing compiler diagnostics through blanket suppression.

## Steps

### Step 1: Add browser-oriented compiler configuration

Create `tsconfig.json` for the Vite browser entrypoint using modern ECMAScript, DOM libraries, module resolution compatible with Vite, and explicit included source/config files. Start with defensible strictness; if full strict mode exposes a large unrelated debt surface, document and choose a staged setting rather than hiding individual errors.

**Verify**: `npx tsc --noEmit` reports the current diagnostics reproducibly.

### Step 2: Fix the current unknown boundary

At `main.entrypoint.ts:1861`, narrow the value before calling `trim()` using the repository's existing request/response parsing style. Do not cast unknown to string without validation.

**Verify**: add or adapt a focused frontend/unit test if the project has a suitable harness; otherwise prove the guarded code path through typecheck and existing browser coverage.

### Step 3: Add script and CI gate

Add `typecheck` as `tsc --noEmit` in `package.json`. Add `npm run typecheck` to Plan 001's quality job after dependency installation, using lockfile-based npm install semantics. Keep the PHP and Docker gates intact.

**Verify**: `npm run typecheck && npm run build` -> exit 0.

## Done criteria

- [ ] `tsconfig.json` defines an explicit browser/Vite typechecking baseline.
- [ ] `npm run typecheck` exits 0.
- [ ] The known `unknown.trim()` issue is fixed by runtime narrowing.
- [ ] CI invokes typecheck after frontend dependencies are installed.
- [ ] Build and existing browser checks remain green.

## STOP conditions

- TypeScript version/configuration cannot typecheck Vite plugin files without a separate config.
- A clean baseline needs widespread source rewrites beyond this focused plan.
- CI lacks a reliable Node setup/cache path after Plan 001.

## Maintenance notes

Keep strictness intentional and ratchet upward. Every new browser entrypoint must be included in the TypeScript project rather than relying on Vite transpilation alone.
