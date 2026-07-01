---
paths:
  - "app/**/*.php"
  - "docs/**/*.md"
  - "tests/**/*.php"
---

# Stashd architecture

Core model:

```text
Stash → Vault → Broadcasts
```

## Meaning

- **Stash**: the user-facing thing to keep.
- **Input**: a technical upstream source inside a stash.
- **MediaItem**: canonical discovered item.
- **Vault**: canonical local archive; required in v1.
- **Broadcast**: generated/disposable view over Vault assets.

## Rules

- Vault owns canonical assets.
- Broadcasts do not mutate Vault assets.
- Broadcasts must be rebuildable, verifiable, pruneable, and disposable.
- Database records expected state; filesystem verification establishes reality.
- Treat filesystem drift as normal, not exceptional.
- State changes go through state-transition services; avoid ad-hoc state mutations.
- Long-running work goes through commands/jobs.
- Events support activity/edges; events are not the source of truth.
- v1 is plugin-ready but does not ship a third-party plugin runtime.

## When changing architecture

1. Inspect existing feature-local patterns.
2. Read the relevant canonical docs.
3. Explain why the existing pattern is insufficient before adding a new one.
4. Prefer a migration plan over a large surprise rewrite.
