# Phase 6 · Slice 6 — task prompts

One prompt per task, optimized for a Sonnet coding agent. Full context: `../plan.md`.

**Shared rules (apply to every task):**
- Backend runs in a custom-FPM lerd site. Never run host `php`/`composer`/`vendor/bin` — use the
  **lerd MCP `exec`** tool (`action: composer | console | vendor_run`). See `AGENTS.md`.
- Frontend: `npm run build`, redeploy assets, restart worker. Tailwind scans statically — verify new
  classes compile.
- Follow existing patterns in neighbouring files. Run `composer test` + `composer lint` before done.

**Order:** 6A (T1–T4) → 6B (T5–T6) → 6C (T8, T9, T7, T10, T20) → 6F infra (T18 before 6D) →
6D (T11–T16) → 6F (T17, T19).

| Task | Area | Title |
|------|------|-------|
| T1  | 6A | Auto-download eligible items at commit end |
| T2  | 6A | Cheap identity resolution (owner id/name/avatar/count) |
| T3  | 6A | Slug ordinal-suffix fallback |
| T4  | 6A | Stash PATCH/DELETE + delete-impact |
| T5  | 6B | YouTube Data API discovery strategy |
| T6  | 6B | YouTube API key in Settings |
| T7  | 6C | Per-input filters (universal + provider) |
| T8  | 6C | POST /stashes + New Stash modal |
| T9  | 6C | Add-input pipeline (retire create_from_preflight) |
| T10 | 6C | Broadcast/policy-mismatch prompt |
| T20 | 6C | Input → broadcast season mapping (deferrable) |
| T11 | 6D | Detailed item list + live status |
| T12 | 6D | Vault/media-item detail enrichment |
| T13 | 6D | Dashboard refinement |
| T14 | 6D | Animated background-activity indicator |
| T15 | 6D | Activity disclosure persists across refresh |
| T16 | 6D | Channel avatar as stash icon |
| T17 | 6F | OpenAPI spec for /api/v1 |
| T18 | 6F | Incremental SSE over RoadRunner |
| T19 | 6F | canRegenerate / safeToDelete |
