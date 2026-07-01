---
description: Review or implement Stashd auth/token/secret changes with a leakage-focused checklist.
---

# Security / Token Review

Use for work touching auth, API tokens, podcast feed tokens, media-server secrets, provider credentials, command/job payloads, logs, or public routes.

## Checklist

- Raw secrets/tokens are not stored in plaintext DB columns.
- Raw secrets/tokens are not emitted in command result JSON.
- Raw secrets/tokens are not included in job payloads.
- Raw secrets/tokens are not included in activity metadata.
- Raw secrets/tokens are not logged or placed in exception messages.
- Public token routes are non-revealing on invalid input.
- Rotated/revoked tokens stop working.
- Token previews are intentionally limited.
- Internal filesystem paths are not leaked publicly unless designed and tested.

## Required tests

For token routes or token lifecycle changes, include valid, invalid, revoked/rotated, and non-leakage cases.

## Completion

Report the exact leakage surfaces checked.
