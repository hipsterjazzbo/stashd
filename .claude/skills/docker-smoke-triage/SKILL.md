---
description: Triage Stashd Docker smoke/runtime failures without drowning Claude in logs.
---

# Docker Smoke Triage

## Procedure

1. Identify the failing smoke step.
2. Inspect `tests/docker/smoke.sh` and the relevant runtime files.
3. Collect only the relevant log excerpt.
4. Distinguish build failure, boot failure, migration failure, storage permission failure, health failure, worker/scheduler failure, and persistence failure.
5. Patch the smallest runtime boundary.
6. Re-run the specific smoke path when possible.

## Runtime files

```text
Dockerfile
docker-compose.yml
docker/
.env.example
tests/docker/
```

## Homelab expectations

Errors should mention concrete paths, UID/GID where relevant, and the action the user can take.
