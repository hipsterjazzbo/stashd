# docs/agent-session-template.md

## Purpose

Use this file to start efficient Claude Code sessions without re-explaining Stashd every time.

The goal is to preserve Claude Pro usage by giving the agent a tight task, clear boundaries, and the right context.

## Default implementation prompt

```text
Read `CLAUDE.md`, then `AGENTS.md`.

Task:
[Describe the task clearly.]

Relevant context:
[Add links to docs, issue notes, or files if known.]

Scope:
- [What should change.]
- [What should change.]
- [What should change.]

Out of scope:
- [What must not change.]
- [What must not be refactored.]
- [What dependencies/services must not be introduced.]

Quality bar:
- Follow existing Tempest patterns in this repo.
- Keep the diff small.
- Use readable modern PHP.
- Use precise Stashd domain names.
- Preserve Stash → Vault → Broadcasts.
- Use `StashdUri` or specific URI value objects inside app/provider logic.
- Do not pass raw URL strings through domain internals.
- Do not shell out to `yt-dlp`; use existing download abstractions.
- Do not log secrets, tokens, private feed URLs, cookies, or provider credentials.
- Add/update tests for behaviour changes.
- Use fake providers/fixtures unless live-provider work is explicitly requested.

Before editing:
1. Inspect relevant existing files.
2. Summarise the current local pattern.
3. Propose a short plan.
4. List files likely to change.
5. List checks/tests to run.
6. Wait for approval.

After editing:
1. Run relevant checks.
2. Summarise changes.
3. Summarise checks/tests run.
4. Note risks or follow-up work.
```

## Small safe task prompt

```text
Read `CLAUDE.md` and `AGENTS.md`.

Implement this small task only:

[Task]

Keep the diff minimal.
Follow existing local patterns.
Do not refactor unrelated code.
Run the most relevant focused check.
Summarise the result.
```

## Review-only prompt

```text
Read `CLAUDE.md` and `AGENTS.md`.

Review the current branch only. Do not edit files yet.

Focus on:
- correctness
- readability
- Tempest idioms
- Stashd domain language
- naming
- class cohesion
- docblocks that explain what/why
- tests
- secret safety
- storage/filesystem drift handling
- provider strategy boundaries
- UI/UX
- accessibility
- accidental scope creep

Return:
1. Must fix
2. Should fix
3. Nice to have
4. Things that look good
```

## Class docblock prompt

```text
Read `CLAUDE.md` and `AGENTS.md`.

Add or improve class docblocks in the touched files only.

A good class docblock should explain:
- what domain concept or boundary the class represents
- why the class exists
- any important persistence, provider, storage, runtime, or security assumption

Do not add noisy docblocks that repeat the class name or obvious type signatures.
Do not change behaviour.
Run formatting if needed.
```

## Naming and cohesion prompt

```text
Read `CLAUDE.md` and `AGENTS.md`.

Review the touched code for naming and cohesion only.

Look for:
- vague names like Manager, Helper, Util, Processor, Data, Info, Thing
- unclear method names, especially product-name verbs like `stashd()`
- classes with more than one reason to change
- raw URL strings where a URI value object should be used
- provider-specific concepts leaking into generic core code
- controller code doing business logic
- download code bypassing the downloader abstraction

Do not edit yet.
Return a prioritized list of suggested changes.
```

## Stash preflight / creation prompt

```text
Read `CLAUDE.md`, `AGENTS.md`, and `docs/agent-context.md`.

Work on the Stash preflight/create flow.

Preserve this model:
- Preflight resolves provider/input and discovers enough information for review.
- Creation commits the reviewed preflight result.
- Stashes organize media items; they do not own canonical media.
- MediaItems are reused across stashes when provider identity matches.
- StashItems hold stash-specific relationship/editorial state.

Do not rediscover provider state during commit unless explicitly requested.
Use fake providers/fixtures in normal tests.
```

## Provider prompt

```text
Read `CLAUDE.md`, `AGENTS.md`, and `docs/agent-context.md`.

Work on provider behaviour.

Preserve these rules:
- Providers are capability bundles, not just downloaders.
- Strategy purpose and cost matter.
- Job intent should drive strategy selection.
- YouTube is the first provider, not the architecture.
- Use typed URI/value objects internally.
- Keep raw provider responses at boundaries or snapshots.
- Redact secrets before logs, activity, job payloads, or raw metadata storage.

Do not use live provider/network tests unless explicitly requested.
```

## Download prompt

```text
Read `CLAUDE.md`, `AGENTS.md`, and `docs/agent-context.md`.

Work on download behaviour.

Preserve these rules:
- All real downloads go through the download abstraction.
- `yt-dlp` access goes through `hazel/ytdlphp`.
- Domain code must not shell out directly.
- Downloads should be job-driven and recoverable.
- Partial files should stage into temp before moving into Vault.
- Real downloads stay disabled by default in tests/local safe flows.

Use fakes/mocks unless live-download behaviour is explicitly requested.
```

## Storage prompt

```text
Read `CLAUDE.md`, `AGENTS.md`, and `docs/agent-context.md`.

Work on storage/filesystem behaviour.

Preserve these rules:
- Database = expected state.
- Filesystem = verified reality.
- Missing individual files and unavailable storage roots are different conditions.
- Do not cascade-delete state just because a file is missing.
- Generated files should be atomic where practical.
- Broadcast assets should prefer hardlinks.
- Do not silently duplicate large media files.

Add tests for filesystem edge cases where possible.
```

## Broadcast prompt

```text
Read `CLAUDE.md`, `AGENTS.md`, and `docs/agent-context.md`.

Work on Broadcast behaviour.

Preserve these rules:
- Broadcasts are generated views of stashes.
- Broadcasts do not own canonical media.
- Broadcasts should be idempotent, rebuildable, verifiable, prunable, and disposable.
- Deleting broadcast output must not delete Vault assets.
- Broadcast trigger failure is not necessarily publication failure.
- Broadcasts live under Stashes in v1.

Do not add top-level Broadcasts navigation unless explicitly requested.
```

## UI polish prompt

```text
Read `CLAUDE.md`, `AGENTS.md`, and `docs/agent-context.md`.

Review this UI before editing.

Stashd should feel like a dense, calm, dark-mode-first self-hosted system dashboard.

Focus on:
- information hierarchy
- compactness without clutter
- keyboard access
- searchability
- status clarity
- empty states
- queued/progress states
- actionable errors
- accessibility
- monospace use for technical identifiers
- warm espresso/graphite/cream/amber brand direction
- avoiding YouTube-clone or SaaS marketing patterns

First propose changes.
Wait before editing.
```

## API prompt

```text
Read `CLAUDE.md`, `AGENTS.md`, and `docs/agent-context.md`.

Work on API behaviour.

Preserve these rules:
- REST-ish JSON under `/api/v1`.
- Everything the UI can do should be possible through the API.
- Long-running actions should become commands/jobs.
- Errors should use stable, actionable, secret-safe codes.
- No private UI-only backend routes for normal behaviour.
- Browser extension and future CLI should be able to use public API flows.

Add/update feature tests for API behaviour.
```

## End-of-session summary format

Ask the agent to finish with:

```text
Changed:
- ...

Checks:
- ...

Architecture notes:
- ...

Risks / follow-up:
- ...
```

## Memory maintenance prompt

Use this when Claude learns something that should persist:

```text
Update `AGENTS.md` or `docs/agent-context.md` only if this lesson should apply to future sessions.

Keep the update short.
Avoid duplicating existing docs.
Do not add task-specific temporary notes.
Prefer `docs/agent-context.md` for product/domain lessons and `AGENTS.md` for always-on rules.
```
