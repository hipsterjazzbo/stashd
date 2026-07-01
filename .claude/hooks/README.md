# Claude hook helper scripts

These scripts are safe helper commands for Claude sessions. They are not enabled automatically.

Review `.claude/settings.example.json` before copying it to `.claude/settings.json`.

Recommended use:

```bash
composer test 2>&1 | scripts/claude/trim-test-output.sh
scripts/claude/changed-files-summary.sh
scripts/claude/context-guard.sh
```

Keep hooks deterministic and boring. Hooks should reduce noise or block obvious mistakes; they should not hide failures.
