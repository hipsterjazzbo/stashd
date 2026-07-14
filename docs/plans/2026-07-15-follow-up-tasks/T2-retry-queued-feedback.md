# T2 — Make retry actions visibly queued and failure-aware

## Problem

`retryDownload()` and `retryAllFailed()` in `src/main.entrypoint.ts` post commands, immediately refresh,
and do not inspect `response.ok`. A failed item can therefore appear unchanged while its retry waits for a
worker, and an HTTP failure looks like a dead button.

The domain already has the correct queued state: `MediaItemState::DownloadPending`. Do not add another
persisted enum case. Display this state to users as **Queued**.

## Scope

- In `app/Downloads/ItemDownloadCommandHandler.php`, transition an eligible media item to
  `DownloadPending` when its download job is created, using `StateTransitionService`. Keep the worker's
  transition logic idempotent and preserve the existing allowed transition rules.
- Confirm retry-all fan-out continues to dispatch normal `item.download` commands so each child becomes
  queued through the same path. Do not create a parallel download implementation.
- Update the client retry helpers to:
  - disable the initiating control while the request is in flight;
  - inspect `response.ok` and show the server's safe error, including the request ID supplied by the shared
    API helper when present;
  - show immediate calm feedback such as “Retry queued” / “Retries queued” after acceptance;
  - refresh or apply realtime state so affected items display **Queued**, not stale **Failed**.
- Reuse existing command/job and realtime UI patterns. Avoid a new notification framework for this slice.

## Out of scope

- Changing worker concurrency or retry policy.
- Renaming the database value `download_pending`.
- Retrying non-download job types.
- A general redesign of activity/history UI.

## Acceptance criteria

- Dispatching a single retry changes the item to `DownloadPending` before the download worker starts.
- The UI labels `download_pending` as **Queued** and visibly acknowledges accepted single and bulk retries.
- Duplicate clicks are suppressed while submission is pending.
- A non-2xx command response produces a meaningful visible error rather than a silent refresh.
- Retry-all still uses the standard item-download command path and queued children progress normally to
  Downloading/Ready or Failed.
- Focused command, state-transition, and browser tests cover accepted and rejected requests; lint,
  TypeScript checks, static analysis, and the parallel suite pass.
