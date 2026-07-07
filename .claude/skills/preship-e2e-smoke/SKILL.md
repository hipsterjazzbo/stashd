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
2. Once downloads land, generate/verify each broadcast.

## Verify per broadcast

- Status reporting is honest (no freezing on "pending" after an actual failure).
- Transcode status, if a transcode ran, is reflected correctly in the UI.
- Storage/impact numbers are approximately accurate, not wildly off from real file sizes.

## Known landmines (not new bugs if you hit them)

- Playwright session flakiness: intermittent 401s against stashd.test, in-browser
  only, not reproducible via curl. Unresolved — retry before digging in.
- Transcode-failure UI bug: a failed transcode job never retriggers broadcast
  re-verify, so the card can freeze on "pending" indefinitely. Known, unfixed.

## Constraints

- No pinning specific videos/playlists. If a run breaks because upstream content
  changed, fix the run then — don't pre-pin now to preempt it.
- Don't mock this into a CI-safe version — the point is catching real integration
  drift against live YouTube.

## Report back

- Per stash: which downloads succeeded/failed and why.
- Per broadcast: status honesty, transcode reporting, impact accuracy.
- Any new issue found, distinct from the known landmines above.
