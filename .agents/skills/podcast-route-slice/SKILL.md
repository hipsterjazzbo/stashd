---
description: Implement or review a Stashd podcast feed/episode route slice.
---

# Podcast Route Slice

Use for `GET /b/{broadcastToken}/feed.xml` and episode media routes.

## Constraints

- Tokens are path segments, not query params.
- Invalid tokens return non-revealing 404s.
- Revoked/rotated tokens fail.
- Non-podcast broadcasts do not expose feed behavior.
- Do not leak raw tokens in logs/activity/command/job metadata.
- Feed XML is a generated disposable artifact.
- Episode URLs must use item path tokens.

## Procedure

1. Inspect existing podcast token/feed classes and tests.
2. Inspect existing route/controller/resource conventions.
3. Confirm generated feed artifact location.
4. Implement one route slice only.
5. Add valid/invalid/revoked/non-podcast tests.
6. Run focused feature tests and lint.

## Out of scope unless requested

- Implementing media serving while doing feed route.
- Reworking feed builder.
- Changing token storage schema.
- Adding auth UI.
