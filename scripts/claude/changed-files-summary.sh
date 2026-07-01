#!/usr/bin/env bash
set -euo pipefail

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  exit 0
fi

printf '\nChanged files:\n'
git status --short || true

printf '\nDiff stat:\n'
git diff --stat || true
