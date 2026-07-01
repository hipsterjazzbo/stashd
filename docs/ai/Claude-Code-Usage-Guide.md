# Claude Code Usage Guide for Stashd

This guide keeps Claude useful without letting context and cost explode.

## Start a session

From the repo root:

```bash
claude
```

Then:

```text
/memory
```

Confirm Claude sees:

```text
CLAUDE.md
AGENTS.md
.claude/rules/*.md where relevant
```

## Cheap default prompt

```text
Work token-efficiently.
Read AGENTS.md, then inspect only files needed for this task.
Do not scan vendor, media, data, generated files, logs, or lockfiles unless necessary.
Summarize the existing local pattern before editing.
Run the narrowest relevant check.

Task: ...
```

## Good task boundaries

Good:

```text
Implement only the public tokenized podcast feed route.
Do not implement episode media serving yet.
Add focused tests for valid, invalid, revoked, and non-podcast tokens.
```

Bad:

```text
Finish podcasts.
```

Good:

```text
Audit app/Broadcasts for naming drift. Do not edit. Produce a phased cleanup plan.
```

Bad:

```text
Clean up the app.
```

## When to clear or compact

Use `/clear` after unrelated tasks.

Use `/stashd-compact-checkpoint` before `/compact` during long sessions.

Preserve:

```text
current goal
files changed
decisions made
tests run
current blocker
next exact step
```

Discard:

```text
old exploration
full logs
rejected approaches
repeated file listings
```

## Model selection

Use Sonnet/default for most implementation.

Escalate only for:

- architecture decisions
- gnarly multi-system debugging
- security-sensitive route/token design
- large refactor planning

## MCP/tool discipline

Prefer local CLI tools where possible:

```bash
git grep BroadcastToken
composer test:feature -- --filter Podcast
scripts/claude/trim-test-output.sh
```

Disable unused MCP servers for coding sessions.

## Suggested skills

```text
/tiny-stashd-task
/narrow-debug
/stashd-slice-plan
/security-token-review
/podcast-route-slice
/broadcast-format-work
/provider-strategy-work
/docker-smoke-triage
/codebase-rescue-audit
/stashd-compact-checkpoint
/session-handoff
```
