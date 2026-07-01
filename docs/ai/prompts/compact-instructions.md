# Compact instructions

Use with `/stashd-compact-checkpoint` before `/compact`.

```text
Preserve:
- current task goal
- accepted scope and out-of-scope items
- files changed/inspected
- local patterns discovered
- decisions made
- tests/checks run and results
- current blocker
- next exact step
- security/storage/runtime caveats

Discard:
- old exploration
- full logs
- rejected approaches
- repeated file listings
- general Stashd background already in AGENTS.md
```
