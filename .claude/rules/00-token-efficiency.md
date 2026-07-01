---
paths:
  - "**/*"
---

# Token efficiency

Claude should work like a careful developer with an expensive context window.

## Default behavior

- Prefer targeted search over broad scans.
- Read only relevant files first.
- Do not inspect generated/vendor/cache/build artifacts unless needed.
- Do not paste full logs into the conversation; summarize or filter them.
- Ask for or create a narrow plan before broad edits.
- Use skills for repeated procedures instead of bloating `CLAUDE.md`.
- Use `/clear` between unrelated tasks and `/compact` before context gets messy.

## Avoid reading by default

```text
vendor/
node_modules/
coverage/
.cache/
.tempest/cache/
data/
media/
storage/
composer.lock
*.sqlite
*.sqlite-*
*.log
*.tar.gz
```

Read lockfiles, generated files, logs, or binary-ish artifacts only when the task specifically requires them.

## Good prompt shape

```text
Work token-efficiently.
Touch only files needed for this task.
Inspect existing local patterns first.
Run the narrowest relevant check.
Summarize briefly.

Task: ...
```
