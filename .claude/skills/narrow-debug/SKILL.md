---
description: Debug one failing Stashd test, command, route, or runtime symptom without expanding scope.
---

# Narrow Debug

Debug the first relevant failure, not every possible failure.

## Procedure

1. Capture the exact failing command or symptom.
2. Run the narrowest reproduction.
3. Trim noisy output using `scripts/claude/trim-test-output.sh` when helpful.
4. Inspect only files directly on the failing path.
5. Form one hypothesis at a time.
6. Patch minimally.
7. Re-run the narrow reproduction.
8. Only then broaden checks if warranted.

## Avoid

- Broad rewrites while debugging.
- Fixing adjacent style issues.
- Pasting full stack traces/logs when the key frame is enough.
- Touching Docker/runtime files unless the symptom is runtime-specific.
