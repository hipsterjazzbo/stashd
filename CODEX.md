# Codex entrypoint for Stashd

Read `AGENTS.md` first. It is the canonical repo instruction file and Codex's source of truth.

Use `.agents/skills/` for reusable workflows. Read only the skill and supporting docs relevant to the current task.

## Always remember

- Stashd is preservation infrastructure: `Stash → Vault → Broadcasts`.
- Vault is canonical; Broadcasts are generated and disposable.
- PHP `^8.5`, Tempest `^3.0`, FrankenPHP, SQLite, Pest, Pint, Docker-first.
- Use existing feature-first `app/` patterns before inventing new structure.
- Keep diffs small and tests honest.
- Be token-efficient: targeted search, relevant files only, no broad repo scans unless requested.
- Never leak raw tokens/secrets in logs, activities, command/job metadata, public APIs, or feed XML.
- Podcast feed and episode URLs use path tokens, never query tokens.

## Startup rhythm

For non-trivial work:

```text
1. Read AGENTS.md.
2. Check git status.
3. Inspect local patterns in the relevant app/ feature folder.
4. State a narrow plan.
5. Edit only the necessary files.
6. Run focused checks, then broader checks if warranted.
```

Use the matching skill in `.agents/skills/` when the task fits one.
