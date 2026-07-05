# RoadRunner → FrankenPHP Migration — Scoped Plan

## Context

Stashd currently serves HTTP through RoadRunner with a hand-written bridge (`app/System/RoadRunner/TempestPsr7Bridge.php`) that its own docblock calls deletable glue. RR is used for **HTTP only** — no jobs/kv/metrics plugins. The custom SQLite job queue (lanes, atomic claims, owner-liveness recovery), scheduler, and event producers are all runtime-agnostic and survive this migration unchanged.

The migration's real prize is not the runtime swap itself — it's that FrankenPHP's built-ins delete Stashd's three most fragile subsystems:

1. **SSE via table-polling** (`EventsController` holds a PHP worker ~10s per poll cycle, capped at 4 concurrent connections out of 8 workers) → replaced by FrankenPHP's embedded **Mercure hub** (Go handles thousands of idle connections; push latency drops from ~1s to ~ms).
2. **Hand-rolled range/streaming for podcast episodes** (the code path behind the historical OOM crash) → replaced by **X-Accel-Redirect** to Caddy's battle-tested `file_server`.
3. **Worker-mode state-reset hacks** (`$_COOKIE` seeding, `AuthContext` reset, `CookieManager` unregister, `chunkSize` streaming knobs) → deleted entirely by running **classic mode**, where Tempest's stock `GenericResponseSender` already streams `EventStream`/generator bodies correctly (verified in vendor: `packages/router/src/GenericResponseSender.php` handles both with `flush()`).

**Decisions made (with Hazel):** classic mode first, worker mode later as an optional phase; Mercure subscriber auth via JWT cookie; job-queue options evaluated in this doc (all three presented, recommendation included).

---

## Feasibility: re-implementing Tempest PR #2172 vs waiting

**Status of the PR (checked 2026-07-05):** open **draft**, last activity 2026-06-19, changes requested by a reviewer, code owners haven't reviewed. Critically, the maintainer states this PR is only the *foundation* (resettable-container infrastructure: `Resettable` interface, `ConnectionReset`/`DatabaseReset`/`SessionAuthenticatorReset`, new kernel events, breaking `Kernel::shutdown()` change) — **the actual FrankenPHP worker application ships in a separate future PR**. Realistic availability: several months minimum.

**Verdict: don't re-implement the framework PR, and don't wait on it either.**

- Re-implementing #2172 in-framework means maintaining a fork across breaking changes to `Kernel`/`Connection` — poor return for a single app.
- A DIY app-level worker script (a `frankenphp_handle_request()` loop porting `TempestPsr7Bridge`'s dispatch + state resets, ~150–200 lines) **is** feasible — Stashd already maintains exactly this kind of glue for RR. But after Mercure and X-Sendfile offload SSE and file streaming to Go, the only PHP traffic left is small single-user JSON/HTML requests. Worker mode becomes a pure latency optimization (~10–30ms/request), not a dependency.
- **Classic mode decouples the entire migration from Tempest's timeline.** Worker mode becomes optional Phase 5: adopt Tempest's first-party support when it lands, or DIY earlier if perf ever demands it.

---

## Phased work plan

Each phase ships independently and leaves the app fully working.

### Phase 1 — FrankenPHP classic mode + Docker rewrite

**Runtime:**
- New `Caddyfile` (repo root or `docker/`): `frankenphp` global block, site on `:{$STASHD_HTTP_PORT:8474}` with `php_server` rooted at `public/` (only `index.php` is ever executed — RR's `forbid` list becomes unnecessary), `encode zstd gzip`, long-cache headers for `public/build/*` (hashed filenames → `Cache-Control: immutable`).
- `public/index.php` already boots Tempest for classic SAPI — **zero PHP entrypoint work**.
- **SQLite pragmas per request** (real gotcha): `busy_timeout` and `foreign_keys` are per-*connection* pragmas. Today the RR bridge applies them once per long-lived worker; under classic mode every request opens a fresh connection with neither set. Add a small Tempest initializer/decorator that applies `SqliteConfigurator::configure()` when the `Database` connection is first resolved in a web request (reuse `app/System/Boot/SqliteConfigurator.php`; WAL is already persistent in the DB file, so only the two cheap pragmas run per request).
- Verify Tempest discovery cache is generated in the prod image (per-request boot must not re-scan).

**Docker:**
- `Dockerfile`: base becomes `dunglas/frankenphp:1-php8.5-bookworm` (Debian, **not** Alpine — musl is measurably slower under ZTS). Extensions via the image's bundled `install-php-extensions`. **Delete the `rr get` step and the `GITHUB_TOKEN` build secret entirely** (also from `.github/workflows/docker-image.yml`). Dev stage no longer needs a host `./rr` binary — the image ships the server.
- `docker/entrypoint.sh`: `serve` role runs `frankenphp run --config /path/Caddyfile` instead of `./rr serve`. Keep the PUID/PGID/gosu dance; set `XDG_CONFIG_HOME`/`XDG_DATA_HOME` to a writable data path for Caddy state; port 8474 is unprivileged so non-root just works.
- `docker/supervisord.conf.template`: rename program `roadrunner` → `frankenphp`; `worker-{interactive,discovery,bulk}` and `scheduler` programs unchanged.
- Set `GOMEMLIMIT` from container limits (FrankenPHP docs recommendation).

**Deletions (Phase 1):** `.rr.yaml`, `bin/worker.php`, `app/System/RoadRunner/` (all 5 files — `TempestPsr7Bridge`, `GeneratorEventStream`, `GeneratorFileStream`, `RoadRunnerProcessLauncher`, +), `tests/Unit/System/RoadRunner/*` (both tests exist only to cover RR-specific behavior), composer deps `spiral/roadrunner-http` + `spiral/roadrunner-cli`, `APP_RUNTIME` env (already unused). Update `app/Console/StashdRuntimeCommand.php` `serve` role to exec frankenphp instead of `RoadRunnerProcessLauncher`. Replace `docs/runtime/roadrunner.md` with `docs/runtime/frankenphp.md`.

**Note:** the existing `/api/v1/events` SSE endpoint and generator file streaming *keep working* in this phase via Tempest's stock response sender — no interim regression while Phases 2–3 land.

### Phase 2 — Mercure real-time

**Hub (Caddyfile):** enable `mercure` block with `publisher_jwt {$MERCURE_JWT_SECRET}` and `subscriber_jwt {$MERCURE_JWT_SECRET}`; **no** `anonymous`. New env var `MERCURE_JWT_SECRET` (generated like `SIGNING_KEY`; never logged — token-leakage rules apply).

**Publishing:** add `symfony/mercure` (+ `lcobucci/jwt` factory). New small `MercurePublisher` used by `app/System/Event/EventPublisher.php` — same five event types (`job.created`, `job.progress`, `job.completed`, `job.failed`, `activity.created`), payload gains a `type` field, single topic (e.g. `stashd/events` — single-user app, no per-entity topic fan-out. YAGNI).
- **Must be HTTP publishing, not `mercure_publish()`:** job progress is published from the CLI `worker-tick` processes, which run *outside* FrankenPHP — the built-in function doesn't exist there. `symfony/mercure` POSTs to `http://127.0.0.1:8474/.well-known/mercure` and works from both web and CLI contexts.
- Publish failures must never fail a job: wrap in try/catch + log. Events are nudges; the UI re-fetches state anyway.

**Subscriber auth (JWT cookie):** the HTML page shells are *unauthenticated* (every `UiController` route opts out via `without: [RequireAuthMiddleware::class]`; auth is a client-side gate against `/api/v1`), so "set the cookie on authenticated page load" doesn't work here. Instead: a small authenticated endpoint (e.g. `GET /api/v1/events/subscription`, behind `RequireAuthMiddleware`) mints a subscriber JWT via `lcobucci/jwt` and sets the `mercureAuthorization` cookie (`HttpOnly`, `SameSite=Strict`, path `/.well-known/mercure`) using Tempest's cookie API — don't use `symfony/mercure`'s `Authorization` helper (coupled to `symfony/http-foundation`). The frontend calls this endpoint before opening the EventSource and re-calls it on reconnect errors. Same-origin `EventSource` sends the cookie automatically. (Per the auth section below, this minting lives in `AuthService`.)

**Frontend (`src/main.entrypoint.ts`):** consolidate the **five** separate `new EventSource('/api/v1/events')` sites (~lines 349, 723, 777, 1027, 1718) into **one shared EventSource** on `/.well-known/mercure?topic=stashd/events` with a tiny subscribe/unsubscribe registry. This simultaneously: fixes the browser's 6-connections-per-host HTTP/1.1 limit, removes the leak class behind the old sse-poll-leak fix, and removes the need for the server-side 4-connection cap. Keep the existing refresh-on-event and refresh-on-reconnect patterns; keep the coarse `setInterval` fallbacks for now (delete in a later cleanup once Mercure proves itself).

**Deletions (Phase 2):** `app/System/Event/EventsController.php` (the poll loop), `SseConnectionRecord`/`SseConnectionRepository` + drop-table migration + `tests/Feature/SseConnectionRepositoryTest.php`, `event_notifications` table + `EventNotificationRecord/Repository` + `PruneEventNotificationsCommand` (the table was pure SSE transport; `activity_events` remains the durable log), the `subscribeUntilTerminal` EventSource-per-retry machinery.

### Phase 3 — X-Sendfile podcast episode serving

**Caddyfile:** `request_header X-Sendfile-Type x-accel-redirect`, `X-Accel-Mapping` from `STASHD_MEDIA_PATH` to an internal URI prefix, plus the `intercept` block (`file_server` on the mapped root, header stripped after processing per the docs example).

**`app/Broadcasts/PodcastEpisodeController.php`:** keep token auth + path resolution (path tokens, never query tokens — unchanged), then return a bodyless response with `X-Accel-Redirect`, `Content-Type`, `Content-Disposition`. **Delete** `PodcastEpisodeByteRange`, the 206/416/`Content-Range` logic, HEAD special-casing, and the 1 MiB `streamFile()` generator — Caddy's `file_server` handles Range/HEAD/If-Range natively and more correctly than hand-rolled code (podcast clients are aggressive range-requesters; this is where the OOM class dies permanently).

**Tests:** rewrite episode tests to assert auth behavior + emitted headers instead of streamed bytes; add one docker-smoke `curl -H "Range: bytes=0-1023"` asserting a 206 with correct `Content-Range` end-to-end.

**Decide during implementation:** whether local non-FrankenPHP dev (lerd/`php tempest serve`) needs a config-gated fallback that streams the body directly when x-accel isn't in front. Prefer no fallback if lerd's nginx can honor `X-Accel-Redirect` with a one-line location block.

### Phase 4 — Background jobs: options evaluated (per Hazel's request)

Facts about Tempest `#[Async]` at v3.13.1: **experimental, explicitly outside the BC promise**. Commands serialize to `.txt` files **inside `vendor/tempest/framework/packages/command-bus/src/stored-commands/`**; `command:monitor` polls every 0.5s and spawns a full `php tempest command:handle <uuid>` subprocess per command, hardcoded max 5 concurrent; `markAsFailed` renames the file — **no retries, no backoff, no priorities, no scheduling, no lanes, no progress, no stale recovery**.

| Option | Work | What you gain | What you lose / risk |
|---|---|---|---|
| **A. Keep custom queue** (recommended) | None | Everything survives as-is: 3 lanes (bulk serialized for yt-dlp bot-avoidance), atomic claims, heartbeat + `/proc`-based owner-liveness recovery, typed retry backoff, progress events. Queue is already RR-free. | Bespoke dispatch API; no framework alignment yet. |
| **B. `#[Async]` facade over the queue** | Medium | Framework-native `command(new X)` dispatch ergonomics; a migration on-ramp for when Tempest's async matures. Implemented as a custom `CommandRepository` writing into the existing `jobs` table, discarding `MonitorAsyncCommands` in favor of the current worker loop. | The `CommandRepository` interface (store/find/markDone/markFailed) has no concept of lanes, priority, attempts, or scheduling — you'd bypass the interface for everything that matters, so the abstraction is mostly cosmetic. Experimental API may break under you (no BC promise). Net code grows. |
| **C. Stock `#[Async]` + `command:monitor`** | Medium (destructive) | Least owned code on paper. | Loses lane serialization (yt-dlp bot detection returns), retries, stale recovery, progress. Vendor-dir file storage is wiped on every image rebuild and fights read-only-container practice. Rejected. |
| **D. Ecotone (`ecotone/tempest`)** | Large | Genuinely robust messaging: retries with backoff, dead-letter channel, delayed messages, multiple async channels (maps 1:1 to lanes — one consumer process per channel keeps the supervisord model *and* yt-dlp serialization), outbox, first-party Tempest integration (reuses Tempest's `DatabaseConfig`, attribute auto-discovery). | The Tempest module shipped **2026-07-03 — two days old**, v1.320.0, 0 installs / 0 dependents on Packagist. Async channels run on `enqueue/dbal` over Doctrine DBAL: a second DB abstraction stacked on the same SQLite file (more write contention), and SQLite is not a documented/tested target for that transport. Ecotone is also a full CQRS/ES/saga architecture framework — a heavy worldview for "run yt-dlp with three lanes". Still leaves progress-reporting and `/proc`-based owner-liveness as custom code on top. |

**Recommendation: A.** The queue was never RoadRunner's — there is nothing FrankenPHP-shaped to do here. **D (Ecotone) is the strongest future candidate**, clearly more production-shaped than Tempest's `#[Async]`; revisit in ~6 months once `ecotone/tempest` has real-world adoption and the enqueue/dbal-on-SQLite question is answered (or if Stashd ever moves off SQLite, where Ecotone's transport story is proven). B is dominated by D and can be dropped from consideration.

**`defer()`:** works under FrankenPHP (it emulates `fastcgi_finish_request()`, which is exactly what Tempest's `defer()` requires — under RR it silently degraded to blocking). Nothing in the app uses `defer()` today and the jobs table covers all real background work; note it as newly *available* for future post-response micro-work (e.g. activity writes), adopt nothing now.

### Considered and rejected: JWT as the single auth system

Evaluated whether Mercure's JWT could become the app-wide auth mechanism. Finding: **Stashd's auth is already unified**, just around a better primitive for this app — opaque hashed tokens in the `api_tokens` table. `AuthService` shows the web "session" is itself a hidden rotating API token (`__web_session__`) in an HttpOnly cookie; Bearer headers and the session cookie resolve through the same `resolveFromToken()` path with instant revocation, expiry, scopes, and `lastUsedAt` auditing. Moving everything to JWT would *lose* instant revocation and last-used tracking (stateless tokens can't be recalled or observed), and the third auth surface — podcast feed/episode **path tokens** — cannot change regardless: podcast clients can't send headers or cookies, and path-token URLs are a hard project rule.

So the Mercure JWT stays what it is: a short-TTL (~1h, auto-reminted on page load) *transport credential derived from* the real session, required only because the hub speaks JWT. Scope it to subscribe-only on the events topic.

**Small unification win worth taking (Phase 2):** mint the Mercure JWT inside `AuthService` alongside the session cookie, so credential issuance lives in exactly one class and the security-token-review checklist has a single choke point. Don't reuse `SIGNING_KEY` as the Mercure secret — separate `MERCURE_JWT_SECRET` keeps blast radii independent.

### Considered and rejected: moving off SQLite (Postgres/MySQL)

Raised alongside the Ecotone question. Verdict: **stay on SQLite; this migration removes most of the SQLite pressure rather than adding to it.** The hottest write/poll path today (`event_notifications` + `sse_connections`, hammered by SSE poll loops) is deleted by Phase 2; remaining load is job state transitions, ~1/s progress updates per active download, and activity rows — far below WAL-mode SQLite's ceiling, with the bulk lane deliberately serialized anyway. Classic mode's connection-per-request also favors SQLite (a file open) over Postgres (would want pooling). Postgres's real draws — `SKIP LOCKED` claims, LISTEN/NOTIFY, a proven enqueue/dbal target for Ecotone — don't outweigh losing single-file canonical state (backup = copy the file, next to the Vault) and adding a second container to a self-hosted NAS app. **Trigger to revisit:** a multi-user/multi-node product pivot, at which point Postgres and Ecotone become right *together*.

### Phase 5 (optional, unscheduled) — Worker mode

Track tempestphp/tempest-framework#2172 + its follow-up "worker application" PR. When merged: `ENV FRANKENPHP_CONFIG="worker ./public/worker.php"` with Tempest's first-party worker entrypoint, and re-add the app-specific per-request resets only if Tempest's `Resettable` machinery doesn't cover them. If perf ever demands it sooner, a DIY port of the old bridge's dispatch loop is ~150–200 lines. Until then, classic mode is deliberately good enough.

---

## Gotchas & mitigations

1. **ZTS builds everywhere.** FrankenPHP requires thread-safe PHP; all extensions must be ZTS-safe. Stashd's set (pdo_sqlite etc.) is mainstream-safe; `install-php-extensions` compiles for ZTS automatically (including xdebug for the dev stage). ZTS also costs ~5–15% raw PHP throughput — irrelevant at this app's traffic.
2. **musl penalty.** Use the Debian (bookworm) image variant, never Alpine — PHP under musl is slower, especially threaded.
3. **Per-connection SQLite pragmas.** `busy_timeout`/`foreign_keys` reset on every new connection; classic mode = new connection per request. Without the Phase 1 initializer, web requests would run with `foreign_keys=OFF` and no busy timeout → FK violations silently pass and `SQLITE_BUSY` errors appear under write contention. This is the sneakiest item in the whole migration.
4. **More concurrent writers.** FrankenPHP defaults to 2×CPU threads (more than RR's 8 workers) and Mercure removes the SSE brake on concurrency. WAL is already enabled; keep `busy_timeout=5000`; job-queue claims are already contention-safe (`rowCount()===1` guarded UPDATE).
5. **CLI processes can't `mercure_publish()`.** Worker-tick/scheduler processes run outside FrankenPHP. Always publish via HTTP (`symfony/mercure`) to the local hub. Mitigation baked into Phase 2 design.
6. **Hub restart = missed events + no deep history.** The embedded hub's Last-Event-ID replay is best-effort/in-memory. Don't rebuild backfill: the UI already re-fetches full state on every event and on reconnect. Keep the coarse fallback `setInterval`s until Mercure has soaked.
7. **Browser 6-connections-per-host limit (HTTP/1.1, no TLS on :8474).** Five EventSources + normal fetches would starve the tab. Mitigation: the Phase 2 single-shared-EventSource consolidation (required, not optional).
8. **Users' reverse proxies buffering SSE.** Self-hosters front this with nginx/traefik/NPM. Mercure sets anti-buffering headers, but document `proxy_buffering off` / flush guidance in the deploy docs.
9. **Publish failure during FrankenPHP restart.** Workers keep running (supervisord) while the hub is down; publishes must be try/catch + log, never job-fatal.
10. **Non-root & Caddy state dirs.** Official image defaults to root; entrypoint's gosu drop must set writable `XDG_CONFIG_HOME`/`XDG_DATA_HOME` for Caddy. Port 8474 needs no capability.
11. **Per-request boot cost in classic mode.** Harmless only if the discovery cache is baked at image build — verify, and fail the build if generation fails.
12. **Progress-event publish rate.** yt-dlp progress could POST to the hub many times/sec. Throttle progress publishes to ~1/s in the job handler if not already throttled.
13. **Crash blast radius changes.** An RR worker segfault killed one worker; a FrankenPHP segfault kills the whole server process. supervisord (`startretries=999999`) + compose healthcheck on `/health` already cover restart; SSE clients auto-reconnect. Accept.
14. **Smoke test / program names.** `tests/docker/smoke.sh` asserts a `roadrunner` supervisord program and RR cookie behavior notes — update program name, add Mercure subscribe + x-sendfile range checks. This is the release gate.
15. **`$_ENV` leaks across requests, exit-before-handle backoff, singleton resets** — worker-mode-only hazards; deferred with Phase 5.

---

## Performance / resource / reliability: RR today vs FrankenPHP target

**HTTP request latency.** RR keeps Tempest booted (~sub-ms dispatch overhead); classic mode re-boots per request — with discovery cache + opcache expect ~10–30ms added per request. For a single-user dashboard doing small JSON calls, imperceptible. Worker mode (Phase 5) would return to RR-class latency or better (in-process Go↔PHP, no pipe IPC serialization).

**Real-time.** Today: each SSE connection holds a full PHP worker in a 1s-granularity poll loop; hard cap of 4 connections co-tuned against 8 workers; a fifth tab gets bounced. After: the Go hub holds effectively unlimited idle connections for ~KB each; push latency drops from ~1s (poll interval) to milliseconds; zero PHP processes occupied; the whole worker-starvation math disappears. **This is the largest single win of the migration.**

**File serving.** Today: podcast episodes stream through PHP in 1 MiB `fread` chunks over RR pipes, holding a worker for the full transfer (a slow podcast client could pin a worker for minutes — combined with 4 SSE slots, the 8-worker pool could genuinely starve), bounded by the 128MB `max_worker_memory` recycler. After: PHP does auth + one header, Caddy serves with kernel `sendfile` — no worker held, near-zero PHP CPU, correct Range/HEAD semantics for free.

**Memory.** Today: 8 persistent PHP worker processes (each a fully booted app, recycled at 128MB) + RR supervisor + per-lane CLI churn. After (classic): one FrankenPHP process; threads share the binary and opcache; no persistent per-worker app state. Expect steady-state RSS to drop meaningfully (roughly: 8×booted-app resident sets collapse into transient per-request footprints). Worker lanes/scheduler processes unchanged. Set `GOMEMLIMIT` to keep Go's allocator inside container limits.

**Reliability.** Removed: ~5 custom glue classes, the `chunkSize` streaming hacks, the worker state-reset bug class (the `CookieManagerWorkerResetTest` class of problem can't exist in classic mode), the SSE cap table, the rr-binary fetch + GitHub token in CI. Added risk: ZTS runtime is younger than RR's process model, and the Caddyfile becomes load-bearing config. Net: fewer bespoke moving parts, more battle-tested upstream ones. Blast-radius tradeoff noted in gotcha 13.

**Observability (new capability).** Caddy admin endpoint exposes Prometheus metrics (threads, workers, request timing); structured access logs; Ember for a live TUI. RR's equivalent plugins were never enabled — this is strictly additive.

---

## Additional wins found while exploring

- **CI secret deleted:** the `rr get` GitHub-token build secret and its rate-limit workaround (commit 95d63ec) disappear.
- **Asset serving upgrade:** Caddy `encode` (zstd/gzip) + immutable cache headers for `public/build/*` — RR's static middleware did neither.
- **Two tables + a scheduled prune command deleted** (`event_notifications`, `sse_connections`, `PruneEventNotificationsCommand`).
- **`defer()` becomes real:** RR silently lacked `fastcgi_finish_request()`; FrankenPHP emulates it, so Tempest's `defer()` now actually detaches — available for future micro-work.
- **Free TLS/HTTP2/HTTP3 option:** Caddy can terminate TLS with internal CA for LAN HTTPS later, which would also lift the EventSource connection ceiling via h2 multiplexing. Not scoped; noted.
- **Dev-container simplification:** dev stage no longer requires a host-fetched `./rr` binary.

---

## Files touched (representative)

**Modified:** `Dockerfile`, `docker/entrypoint.sh`, `docker/supervisord.conf.template`, `docker-compose.yml` / `docker-compose.dev.yml`, `.github/workflows/docker-image.yml`, `composer.json`, `app/System/Event/EventPublisher.php`, `app/Console/StashdRuntimeCommand.php`, `app/Broadcasts/PodcastEpisodeController.php`, `src/main.entrypoint.ts`, `tests/docker/smoke.sh`.
**Added:** `Caddyfile`, `MercurePublisher` (+ JWT cookie responder), SQLite-pragma connection initializer, `docs/runtime/frankenphp.md`.
**Deleted:** `.rr.yaml`, `bin/worker.php`, `app/System/RoadRunner/*`, `app/System/Event/EventsController.php`, `SseConnection*`, `EventNotification*` + prune command, `tests/Unit/System/RoadRunner/*`, `tests/Feature/SseConnectionRepositoryTest.php`.

## Verification

- Per phase: `composer test:parallel` (parallel variants per project convention).
- Docker smoke (`composer test:docker-smoke`) updated for the `frankenphp` program name; add: Mercure subscribe-and-receive during a fake job, `curl -H "Range: bytes=0-1023"` on an episode URL asserting 206 + `Content-Range` served by Caddy, restart-persistence unchanged.
- Manual: run a real download with the dashboard open — progress must update live via the single shared EventSource (verify exactly one connection in devtools); kill/restart the container mid-download — job resumes via stale recovery, UI reconnects.
- Compare `docker stats` RSS before/after under idle + one-download load.

## Immediate next step on approval

Commit this document to the repo as `docs/plans/frankenphp-migration.md` (the scoped plan *is* the deliverable of this task; implementation phases are separate future work, each sized to ship independently).
