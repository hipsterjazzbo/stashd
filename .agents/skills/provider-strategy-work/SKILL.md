---
description: Work on Stashd provider/discovery/download strategy boundaries.
---

# Provider Strategy Work

## Boundaries

- Discovery, metadata fetch, and download strategy are separate.
- ytdlphp is the only download/process boundary.
- Provider quirks stay in provider/download code.
- Vault and Broadcast code should not know YouTube-specific details.
- Tests use fixtures/fakes by default; live provider tests are opt-in.

## Procedure

1. Inspect relevant provider strategies and URI/input models.
2. Keep raw URL strings from leaking across domain boundaries when a value object exists.
3. Preserve raw metadata snapshots with secret redaction.
4. Use fake provider fixtures for tests.
5. Add stable error codes for expected provider/download failures.
