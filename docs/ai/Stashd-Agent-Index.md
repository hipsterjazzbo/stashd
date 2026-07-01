# Stashd Agent Index

Use this file to find the right instruction source without loading everything.

## Always-loaded / short

```text
AGENTS.md
CLAUDE.md
.hermes.md
```

## Claude-specific modular guidance

```text
.claude/rules/
.claude/skills/
.claude/hooks/
.claude/settings.example.json
```

## Existing product/architecture docs

```text
docs/Stashd-Engineering-Specification.md
docs/Stashd-Architecture-and-Vision-Updated.md
docs/Stashd-Branding-Plan.md
docs/Stashd-Browser-Extension-Spec.md
docs/TODO.md
docs/agent-context.md
docs/architecture/code-organization.md
docs/broadcasts/README.md
docs/providers/
docs/storage/
docs/runtime/
docs/media-servers/
docs/foundation/
```

## Local-model docs

```text
docs/ai/Hermes-Agent-Ollama-Setup.md
docs/ai/Stashd-Hermes-Agent-Reference.md
```

## New Claude docs

```text
docs/ai/Claude-Code-Usage-Guide.md
docs/ai/Stashd-Agent-Index.md
docs/ai/Codebase-Rescue-Playbook.md
docs/ai/Stashd-Agent-Session-Template.md
```

## Rule of thumb

- Need product truth? Read engineering spec/TODO.
- Need local implementation pattern? Read relevant `app/` folder and tests.
- Need repeated workflow? Use a skill.
- Need current phase status? Confirm against `docs/TODO.md` and recent commits.
