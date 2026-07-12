# Stashd Agent Session Template

Paste this at the start of a new coding-agent session when you need a controlled task.

```text
You are working in the Stashd repo.

First read AGENTS.md.
Work token-efficiently and do not scan the whole repo.
Check git status before editing.
Leave unrelated changes alone.
Inspect existing local patterns before adding new ones.
Never leak raw tokens/secrets into logs, activity, command/job metadata, public API, or XML.

Task:
[describe the narrow task]

Scope:
[files/features in scope]

Out of scope:
[explicit exclusions]

Acceptance checks:
[commands/tests]
```

## For messy cleanup

```text
Use /codebase-rescue-audit.
This is read-only.
Target area: [folder/feature].
Produce a phased cleanup plan; do not edit.
```

## For podcast token work

```text
Use /podcast-route-slice and /security-token-review.
Implement only [feed route / episode route / token rotation behavior].
Do not change unrelated broadcast formats.
Add valid, invalid, revoked, and non-leakage tests.
```

## For Docker runtime work

```text
Use /docker-smoke-triage.
Target symptom: [symptom].
Inspect Dockerfile, docker-compose.yml, docker/Caddyfile, docker/, and tests/docker only as needed.
Do not change domain code unless the failure proves it is necessary.
```
