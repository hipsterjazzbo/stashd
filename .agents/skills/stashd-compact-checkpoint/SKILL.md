---
description: Create a compact Stashd session checkpoint before /compact or handoff.
---

# Stashd Compact Checkpoint

Prepare context that survives compaction without dragging stale exploration forward.

## Preserve

- Current goal and accepted scope.
- Files changed.
- Key local patterns discovered.
- Decisions made.
- Failing/passing commands.
- Security/storage/runtime caveats.
- Exact next step.

## Discard

- Old exploration.
- Rejected approaches.
- Full logs.
- Repeated file listings.
- General explanations of Stashd already covered by `AGENTS.md`.

## Template

```text
Goal:
Scope:
Changed files:
Important decisions:
Relevant tests/checks:
Current blocker:
Next step:
Do not forget:
```
