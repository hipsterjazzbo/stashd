---
paths:
  - "**/*"
---

# Git workflow

Before editing:

```bash
git status --short
```

Rules:

- Do not mix unrelated user changes into your patch.
- If unrelated files are modified/deleted/untracked, report them and leave them alone.
- Keep diffs small and reviewable.
- Prefer staged/phased refactors over broad rewrites.
- Summaries should include changed files, why they changed, checks run, and remaining risks.

For messy worktrees, create a plan before editing.
