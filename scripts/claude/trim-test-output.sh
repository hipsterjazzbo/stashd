#!/usr/bin/env bash
set -euo pipefail

# Summarize noisy PHPUnit/Pest/Composer output for Claude.
# Usage: composer test 2>&1 | scripts/claude/trim-test-output.sh

input="$(cat)"

printf '%s
' "$input" \
  | grep -E -A 12 -B 4 'FAIL|FAILED|ERROR|Exception|Fatal error|Parse error|TypeError|Assertion|Tests:|WARN|Deprecated' \
  | sed -E 's/[[:cntrl:]]\[[0-9;]*m//g' \
  | head -240 || true

if printf '%s' "$input" | grep -qE 'FAIL|FAILED|ERROR|Exception|Fatal error|Parse error|TypeError'; then
  exit 1
fi
