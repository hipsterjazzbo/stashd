---
description: Complete a small Stashd coding task token-efficiently. Use for narrow edits, tiny bug fixes, or small tests.
---

# Tiny Stashd Task

Use the smallest useful context.

## Procedure

1. Check `git status --short`.
2. Restate the target change in one sentence.
3. Inspect only the most likely relevant files.
4. Identify the local pattern before editing.
5. Make the smallest coherent edit.
6. Run the narrowest relevant formatter/test/check.
7. Summarize briefly.

## Constraints

- Do not scan the whole repo.
- Do not refactor unrelated code.
- Do not change dependencies.
- Do not touch secrets/tokens/routes/schema unless the task explicitly asks.

## Completion format

```text
Changed:
- ...

Verified:
- ...

Notes:
- ...
```
