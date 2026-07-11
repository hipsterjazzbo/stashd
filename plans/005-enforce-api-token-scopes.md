# Plan 005: Enforce API token scopes

> **Executor instructions**: Treat token compatibility as a security decision. Follow all STOP conditions and update the index only when every criterion passes.
>
> **Drift check**: `git diff --stat 6b5bb50..HEAD -- app/Auth app/Http tests/Feature/AuthTest.php docs/openapi.yaml`

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: HIGH
- **Depends on**: `plans/004-serialize-initial-owner-setup.md`
- **Category**: security
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

The token-creation API accepts and stores scopes, but request authentication resolves every valid token to the same owner user and routes do not inspect a grant. A token described as read-only therefore has destructive owner access. Implement a closed, documented scope vocabulary and enforce it at server-side route boundaries while defining explicit behavior for existing null/empty scopes.

## Current state

- `app/Auth/AuthController.php:132-165` accepts an optional `scopes` array when creating an API token.
- `app/Auth/ApiTokenRepository.php` persists `scopesJson`; `app/Auth/AuthService.php:106-125` finds a token, resolves only its user, and records usage.
- `app/Http/Middleware/RequireAuthMiddleware.php:48-61` places only `UserRecord` in `AuthContext`.
- Auth-protected routes span `app/Stashes`, `app/Broadcasts`, `app/MediaServers`, `app/Commands`, and `app/System`; public podcast routes explicitly opt out of auth and must remain unchanged.
- `tests/Feature/AuthTest.php` currently proves scopes are stored but not authorization behavior.

## Commands

| Purpose | Command | Expected result |
|---|---|---|
| Auth authorization tests | `composer test:feature -- --filter=AuthTest` | pass |
| Full suite | `composer test` | pass |
| OpenAPI test | `composer test:unit -- --filter=OpenApiSpecTest` | pass |
| Static analysis | `composer test:static` | exit 0 |

## Scope

**In scope**: auth grant/context types, token creation validation, authorization middleware/decorators, protected route declarations, auth tests, and OpenAPI documentation if request/response scope contract changes.

**Out of scope**: OAuth, multi-user roles, changing session-cookie privileges, public podcast token routes, or adding UI scope management beyond existing token creation input.

## Steps

### Step 1: Define the compatibility and vocabulary contract

Document a small closed list of scopes grouped by capability (for example read, stash-write, broadcast-write, settings/admin) using Stashd domain vocabulary. Decide and test legacy null/empty behavior explicitly; recommended compatibility is that legacy unscoped owner tokens retain full access until reissued, while newly requested scopes are validated and enforced. Do not silently treat arbitrary strings as grants.

**Verify**: focused unit tests reject unknown/non-string scopes and normalize valid scopes deterministically.

### Step 2: Carry an authenticated grant through requests

Introduce an explicit authenticated principal/grant in `AuthContext` that preserves whether authentication came from a session or API token and, for API tokens, its scopes. Keep bearer precedence and request-finally cleanup intact.

**Verify**: existing session and bearer-isolation tests pass; revoked/expired tokens remain rejected.

### Step 3: Enforce scopes at protected route groups

Add an established local mechanism (middleware or route attribute) that declares required scope(s) at controller/route boundaries. Assign requirements by operation: reads versus state-changing stash/broadcast/command/media-server/auth-token operations. Session authentication and legacy full-access tokens satisfy all requirements. Return a stable forbidden response without leaking resource existence.

**Verify**: add allow/deny tests for one read route, one stash mutation, one broadcast/media-server mutation, and token administration.

### Step 4: Update API contract and regressions

Ensure token resources expose no raw token beyond the existing one-time creation response. Update OpenAPI only where scopes are already public API input/output. Add a matrix test showing a scoped token cannot reach an unrelated privileged endpoint.

**Verify**: `composer test && composer test:static` -> pass.

## Done criteria

- [ ] Scope vocabulary is closed and server-validated.
- [ ] API-token grants are available to authorization, not discarded during authentication.
- [ ] Protected route groups enforce scopes server-side.
- [ ] Session and explicitly compatible legacy tokens retain documented behavior.
- [ ] Public podcast routes and secret redaction behavior are unchanged.
- [ ] Full tests, OpenAPI validation, and PHPStan pass.

## STOP conditions

- Existing token data contains non-empty scopes whose intended semantics cannot be inferred safely.
- A required scope assignment would change a documented machine-client integration without a compatibility decision.
- Scope enforcement requires changing the public podcast token route model.

## Maintenance notes

Every new protected endpoint needs an explicit scope decision in review. Avoid a catch-all administrator scope for ordinary writes; scope names should correspond to stable domain capabilities, not controller class names.
