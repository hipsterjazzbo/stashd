#!/usr/bin/env bash
set -euo pipefail

# Lightweight reminder for agents before broad operations.
# This intentionally does not block; it prints warnings only.

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  exit 0
fi

changed_count="$(git status --short | wc -l | tr -d ' ')"
if [[ "$changed_count" != "0" ]]; then
  echo "Context guard: worktree has $changed_count changed/untracked file(s). Do not overwrite unrelated user changes."
fi

if [[ -f composer.json ]] && grep -q '"stashd/stashd"' composer.json; then
  echo "Context guard: Stashd repo detected. Read AGENTS.md before non-trivial edits."
fi
