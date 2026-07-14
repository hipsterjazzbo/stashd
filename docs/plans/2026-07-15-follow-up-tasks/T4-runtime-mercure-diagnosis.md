# T4 — Diagnose intermittent connectivity and Mercure restart failures

## Observed evidence

During the 2026-07-15 migration/verification run:

- The Lerd console path operated on the checkout database, while the active custom container served the
  persistent `/data/database/database.sqlite` database.
- Applying the stash-slug migration to the active database initially hit `database table is locked`.
- After stopping and restarting supervised processes, FrankenPHP repeatedly failed with Mercure Bolt
  `invalid transport: timeout`.
- A full Lerd site/container restart recovered all processes.

These may contribute to the reported intermittent “Couldn't connect to server” behavior, but causality has
not yet been established.

## Scope

This is a diagnosis-first task. Reproduce and characterize before changing code or configuration.

- Capture Lerd site/runtime, worker, and log state before and after:
  - normal requests;
  - process-only FrankenPHP stop/start;
  - full site/container restart;
  - a migration while application processes are active.
- Use `X-Stashd-Request-Id`, `Server-Timing`, browser timing warnings, application logs, and Lerd logs to
  distinguish browser/network, reverse proxy, application, SQLite-lock, and Mercure failures.
- Inspect the relevant `.lerd.yaml`, container/supervisor configuration, `docker/Caddyfile`, Mercure Bolt data
  path, and shutdown/startup lifecycle.
- Confirm which database each supported command path targets. Establish one safe documented command for
  migrations against the active custom-container database.
- Produce a compact reproduction matrix with timestamps, request IDs, expected/actual behavior, and the
  smallest credible root cause.
- If the root cause and fix are local, bounded, and supported by the evidence, implement it with a regression
  check. If it belongs to Lerd or Mercure upstream, stop at a handoff-quality report and local runbook.

## Constraints

- Do not delete or recreate the active database, Mercure data, or user media.
- Do not disable realtime or replace Mercure as a workaround.
- Do not bundle speculative UI optimizations or broad server rewrites.
- Redact cookies, tokens, request payloads, and internal secrets from reports.

## Acceptance criteria

- The process-only restart outcome is repeatable or the report demonstrates why it cannot be reproduced.
- The report identifies the active database path and the safe migration procedure without ambiguity.
- At least one correlated request traces browser duration, server duration, response status, and relevant
  server log entries.
- Any implemented fix survives repeated process and full-site restarts and leaves all supervised processes
  healthy.
- Relevant focused checks and Docker/runtime smoke pass, or every unrun/blocked check is explicitly recorded.
