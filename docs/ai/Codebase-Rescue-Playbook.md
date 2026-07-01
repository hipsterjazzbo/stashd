# Stashd Codebase Rescue Playbook

Use this when Stashd feels messy and you want an agent to help without making it worse.

## Ground rule

First pass is read-only. No edits until the cleanup slice is named and approved.

## Step 1: Stabilize orientation

Read:

```text
AGENTS.md
docs/TODO.md
docs/architecture/code-organization.md
```

Then inspect the target feature folder only.

## Step 2: Classify the mess

Look for:

- naming drift from older architecture
- concept scatter across features
- overloaded services
- thin pass-through handlers
- duplicated DTO/result classes
- tests coupled to implementation details
- stale docs
- token/secret leakage risks
- runtime assumptions not represented in Docker smoke

## Step 3: Protect what works

Before changing anything, identify:

- tests currently passing
- public routes/API shapes
- token/feed URL formats
- DB migration assumptions
- Docker startup assumptions
- fixtures/fake providers

## Step 4: Slice cleanup safely

Prefer this order:

1. Imports/moves only, no behavior change.
2. Naming cleanup with compatibility shims if needed.
3. DTO/result consolidation.
4. Thin handler/service simplification.
5. Tests around behavior and boundaries.
6. Docs/TODO update.

## Step 5: Avoid cleanup traps

Do not combine:

- route/security changes with naming refactors
- schema changes with feature moves
- Docker runtime fixes with domain cleanup
- podcast token changes with broad broadcast cleanup
- provider strategy changes with Vault schema changes

## Good prompt

```text
Use /codebase-rescue-audit.
Audit app/Broadcasts only.
Do not edit.
Classify naming drift, overloaded classes, test gaps, and token risks.
Produce a phased cleanup plan with 3-5 PR-sized slices.
```
