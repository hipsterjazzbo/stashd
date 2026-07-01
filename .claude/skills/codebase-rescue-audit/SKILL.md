---
description: Audit messy Stashd areas and produce a phased cleanup plan before editing.
---

# Codebase Rescue Audit

Use when the repo feels messy, naming has drifted, or several features have grown sideways.

Do not edit during the first pass.

## Audit steps

1. Check `git status --short`.
2. Read `AGENTS.md`, `docs/TODO.md`, and `docs/architecture/code-organization.md` if relevant.
3. Inventory the target area only; do not audit the whole repo unless asked.
4. Identify clutter categories:
   - naming drift
   - feature-boundary scatter
   - thin pass-through handlers
   - overloaded services
   - duplicate DTO/result shapes
   - public response leakage risk
   - test gaps
   - stale docs
5. Propose phased cleanup slices.

## Output format

```text
Current shape:
- ...

Problems:
- ...

Keep:
- ...

Cleanup plan:
1. Safe imports/moves only
2. Names/DTO consolidation
3. Handler/service simplification
4. Tests/docs updates

Do not touch yet:
- ...
```
