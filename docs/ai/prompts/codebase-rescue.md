# Codebase rescue prompt

```text
Use /codebase-rescue-audit.
Read AGENTS.md and docs/architecture/code-organization.md.
Audit only [target folder/feature].
Do not edit.

Classify:
- naming drift
- duplicated DTO/result shapes
- overloaded classes
- feature-boundary scatter
- public response leakage risks
- test gaps
- stale docs

Return a phased cleanup plan with small reviewable slices.
```
