---
description: Implement or review Stashd broadcast format work while preserving Vault/Broadcast boundaries.
---

# Broadcast Format Work

## Rules

- Broadcasts are generated views over Vault assets.
- Broadcast code must not mutate canonical Vault assets.
- Filesystem broadcasts are hardlink-first.
- Podcast broadcasts generate feeds/URLs, not media-server layouts.
- Trigger failures do not imply broadcast file invalidity.

## Procedure

1. Identify the target broadcast type/format.
2. Inspect the existing format and registry pattern.
3. Identify required assets and failure codes.
4. Keep output generation idempotent.
5. Add tests for generated output and drift/failure behavior.
6. Document user-visible behavior if it changed.
