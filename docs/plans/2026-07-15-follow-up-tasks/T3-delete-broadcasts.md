# T3 — Safely delete broadcasts

## Problem

The application has no broadcast deletion action. A broadcast is a disposable generated view, but deleting
its database record alone would orphan generated output, while recursively deleting an arbitrary configured
destination could destroy unrelated user files.

Existing plugin `prune()` behavior is not a full deletion primitive: podcast pruning removes the feed, and
series pruning preserves files that remain in the current plan.

## Scope

- Add an authenticated API deletion flow for a broadcast. Follow the existing command/job architecture:
  the controller validates/adapts and returns an accepted command; filesystem work runs outside the request.
- Add an explicit deletion/purge lifecycle operation rather than changing the semantics of routine prune.
- Remove only Stashd-owned generated broadcast artifacts:
  - use `BroadcastPathBuilder` ownership/path checks;
  - never delete Vault originals;
  - for a default Stashd-owned broadcast root, remove the generated root when safe;
  - for a user-selected/custom destination, remove only artifacts tracked/owned by this broadcast and empty
    directories created by it. Never recursively delete the destination root or unrelated files.
- After generated artifacts have been handled, delete the broadcast record and rely on/verify database
  cascades for items, triggers, and broadcast-owned asset records.
- Add a Delete action to the broadcast UI with a clear confirmation that generated broadcast files will be
  removed while Vault media remains. Disable it while accepted work is pending and surface command errors.
- Refresh or patch the UI after completion so the deleted broadcast disappears.

## Design checkpoint

Before editing, inspect `BroadcastLifecycleService`, `BroadcastPlugin`, both plugin `prune()` implementations,
`BroadcastPathBuilder`, broadcast asset records, and destination override behavior. Write down the exact owned
artifact set for podcast and series broadcasts. If the current records cannot distinguish owned files safely,
stop and propose the smallest metadata change instead of guessing from directory contents.

## Out of scope

- Deleting stashes or Vault media.
- A general retention/recycling-bin feature.
- Changing rebuild or routine prune behavior.
- Deleting arbitrary custom destination roots.

## Acceptance criteria

- An authenticated user can request deletion and receives a truthful accepted/pending response.
- Podcast and series broadcast records and dependent database rows are removed after successful cleanup.
- Generated files owned by the broadcast are removed; canonical Vault assets and unrelated destination files
  remain byte-for-byte intact.
- Unsafe or ambiguous paths fail closed with a visible, correlated error and leave the database record intact
  so cleanup can be retried.
- The UI confirms destructive intent, prevents duplicate submissions, shows errors, and removes the card only
  after successful completion.
- Feature tests cover default roots, custom destinations, Vault preservation, cascades, missing IDs, and
  cleanup failure. Browser coverage exercises confirmation and accepted/error states.
- Lint, TypeScript checks, static analysis, the parallel suite, and the relevant Docker/runtime smoke pass.
