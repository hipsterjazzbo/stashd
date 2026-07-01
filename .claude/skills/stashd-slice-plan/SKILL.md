---
description: Plan a bounded Stashd feature slice before implementation. Use for multi-file work or architecture-sensitive changes.
---

# Stashd Slice Plan

Plan first. Do not edit until the user approves unless they explicitly asked for direct implementation.

## Inputs to inspect

- `AGENTS.md`
- `docs/TODO.md`
- relevant feature docs under `docs/`
- relevant feature folder under `app/`
- existing tests for the feature

## Plan format

```text
Goal:
- ...

Existing pattern:
- ...

Files likely touched:
- ...

Implementation steps:
1. ...
2. ...

Tests/checks:
- ...

Security/storage/runtime risks:
- ...

Out of scope:
- ...
```

Keep the slice small enough to review.
