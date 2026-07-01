---
paths:
  - "docs/**/*.md"
  - "AGENTS.md"
  - "CLAUDE.md"
  - ".hermes.md"
  - ".claude/**/*.md"
---

# Docs and specs

Stashd docs are source-of-truth material, not dumping grounds.

## Rules

- Update docs when behavior, terminology, routes, tokens, storage semantics, or runtime assumptions change.
- Do not duplicate long specs inside comments or agent summaries.
- Prefer links to canonical docs over copy/paste.
- Keep `AGENTS.md` concise.
- Put reusable procedures in `.claude/skills/`.
- Put long context/reference in `docs/ai/` or existing architecture docs.
- Keep snapshots dated or clearly marked as stale-risk.

## Before relying on docs

Docs can drift. Confirm against live code and `docs/TODO.md` before implementing.
