---
description: Run the full in-browser stash-to-download-to-broadcast smoke test against live YouTube sources before shipping. Use for "pre-ship smoke test", "full E2E run", "the usual smoke test".
---

# Pre-Ship E2E Smoke Test

Live, real-data run through the whole pipeline: stash creation, download, broadcast
generation/verification. Deliberately uses real YouTube sources, not fixtures — a
rerun surfacing upstream drift (deleted video, reordered playlist) is a real finding,
not noise to eliminate.

## Setup

1. `stashd:rebuild` for a fresh install.
2. Start the app in-browser (use the `run` skill if it isn't already running).
3. Make sure job workers are actually running and can see the real database —
   dev containers auto-start one `stashd worker` process covering all lanes
   (interactive/discovery/bulk) on boot; don't assume you need to start one
   yourself. If you do start one manually, verify it's routed into the same
   container serving the site, not a different PHP container that can't see
   the app's data volume — a worker that "looks started" but can't open the
   database fails every tick silently, with no visible symptom.

A Playwright implementation of the full flow below lives at
`e2e/manual/preship-smoke.spec.ts` (excluded from the routine `playwright test`
sweep via `testIgnore`). Run it explicitly:
`npx playwright test e2e/manual/preship-smoke.spec.ts`

## Stashes to create

1. **oculusimperia** — `https://www.youtube.com/@oculusimperia`
   - Download: video
   - Broadcast: audio podcast
2. **criticalrole** — `https://www.youtube.com/@criticalrole`
   - Filter: regex `/Campaign 4, Episode \d+/`
   - Download: video
   - Broadcast: Jellyfin
3. **Vivariums by AntsCanada** — one stash, one input per "Season X" playlist (1–7)
   from `https://www.youtube.com/@AntsCanada/playlists`
   - Download: video
   - Broadcast: Jellyfin, each playlist mapped to a season

## Execute

1. Per stash, download at least 3 items successfully. Don't wait for full
   playlists — stop once 3 succeed per stash.
2. Once downloads land, generate/verify each broadcast. Creating a broadcast
   only inserts the record in `pending` — it does not auto-dispatch a build,
   the card's own "rebuild" action has to be triggered separately.

**Throughput note:** the local dev environment runs a single `stashd worker`
process across all lanes. oculusimperia's full-channel import (no filter) can
discover 200+ items and monopolize that one worker for a long time, starving
the other two stashes' discovery/download jobs entirely — this is an
environment characteristic (production runs separate per-lane workers per
`docker/supervisord.conf.template`), not an app bug. If a full run needs to
finish in reasonable time, either run multiple `stashd worker <lane>`
processes (one per lane, matching production), or scope oculusimperia down
(e.g. a title filter) so it doesn't dominate the queue.

## Verify per broadcast

- Status reporting is honest (no freezing on "pending" after an actual failure).
- Transcode status, if a transcode ran, is reflected correctly in the UI.
- Storage/impact numbers are approximately accurate, not wildly off from real file sizes.

## Known landmines (not new bugs if you hit them)

- Session collisions: logging in again (e.g. a diagnostic `curl` login while a
  Playwright browser session is active) revokes the other session — each
  login mints a fresh session and kicks out prior ones for that user. If you
  need to poll the API alongside a live browser session, use a separate API
  token (`POST /api/v1/auth/tokens`), not another username/password login.
  This is the likely real explanation for earlier "intermittent 401s,
  in-browser only" flakiness reports — treat that older theory as superseded
  unless a genuine unexplained 401 shows up with no concurrent second login.
- Client-side session-expiry redirect isn't instant: a 401 triggers
  `window.location.assign('/login')` from JS after the fetch resolves, not a
  server-side redirect at navigation time — check `page.url()` only after a
  short wait, not immediately after `page.goto()`.

## Constraints

- No pinning specific videos/playlists. If a run breaks because upstream content
  changed, fix the run then — don't pre-pin now to preempt it.
- Don't mock this into a CI-safe version — the point is catching real integration
  drift against live YouTube.
- Never embed a raw API token in a shell command string (e.g. `curl -H "Authorization:
  Bearer $(cat ...)"` via a file, not `TOKEN="stashd_pat_..."` inline) — inline tokens
  land in shell history and job/command transcripts, violating the project's own
  no-raw-token-in-logs rule even in a local dev/test context.

## Report back

- Per stash: which downloads succeeded/failed and why.
- Per broadcast: status honesty, transcode reporting, impact accuracy.
- Any new issue found, distinct from the known landmines above.
