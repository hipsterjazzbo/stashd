# FrankenPHP runtime

Stashd uses [FrankenPHP](https://frankenphp.dev/) (classic, non-worker mode) as the HTTP application server in Docker and production-like environments. PHP-FPM is not required for the default deployment.

## Why classic mode

FrankenPHP's worker mode (persistent PHP processes across requests, like RoadRunner) needs first-party Tempest support that doesn't exist yet ([tempestphp/tempest-framework#2172](https://github.com/tempestphp/tempest-framework/pull/2172) is still a draft foundation PR, with the actual worker application slated for a separate follow-up PR). Classic mode needs none of that: `public/index.php` already boots Tempest for traditional one-request-per-process SAPI, and FrankenPHP (via Caddy) serves it directly — no custom bridge, no per-request state-reset glue.

This also means Stashd previously ran a hand-written RoadRunner↔Tempest adapter (`TempestPsr7Bridge` and friends). That adapter is gone: it existed only because RoadRunner keeps workers alive across requests and needed manual cookie/`AuthContext`/SQLite-connection resets between them. Classic mode has no such worker to reset.

## The one thing classic mode needs that FPM/worker setups don't

Every request opens a fresh SQLite connection, and `busy_timeout`/`foreign_keys` are per-connection pragmas — `stashd:boot`'s pragma configuration (on its own throwaway CLI connection at container start) doesn't carry over to web requests. `App\Http\Middleware\ConfigureSqliteMiddleware` re-applies them at the very start of every request (via `#[Priority(Priority::FRAMEWORK - 30)]`, ahead of everything else, including auth) using the same `SqliteConfigurator` that `stashd:boot` and the worker/scheduler tick commands already use.

## Local development

```bash
# Traditional SAPI (single request, useful for quick debugging)
php -S localhost:8474 -t public public/index.php

# Production-like FrankenPHP/Caddy server (requires the `frankenphp` binary
# on PATH; ships in the Docker image, install separately for bare-metal use)
frankenphp run --config docker/Caddyfile
```

## Docker

`docker/Caddyfile` configures Caddy's `php_server` directive: serves anything under `public/` as a static file first (built assets, favicon), falls through to `public/index.php` otherwise. No explicit forbid list is needed — the document root is scoped to `public/`, which never contains `.env`, `.htaccess`, or any PHP file besides `index.php`.

The container entrypoint runs `stashd:boot`, then supervisord starts:

- FrankenPHP (`stashd serve`)
- Job poll loops, one per lane (`stashd worker interactive|discovery|bulk`) —
  interactive jobs (preflight/add-input) never queue behind downloads or
  channel backfills; `stashd worker` with no lane processes everything
  (local dev)
- Scheduler loop (`stashd scheduler`)

See `docker/supervisord.conf.template` and `docker/entrypoint.sh`.

## Generator response bodies

Tempest's stock `GenericResponseSender` only knows how to stream a `Generator` body when it's wrapped in `EventStream` (SSE framing) — any other Generator body (e.g. `PodcastEpisodeController`'s bounded-chunk file reads, used so a hundreds-of-MB episode is never buffered whole in memory) falls through to `echo $body`, which throws, corrupting the response after headers are already sent. `App\Http\Routing\StreamingResponseSender` decorates the framework's `ResponseSender` (via Tempest's `#[Decorates]` mechanism) to stream those chunks directly instead.

## Not yet done

Real-time updates (`/api/v1/events`) and podcast episode file serving still use the same hand-rolled SQLite-poll / range-request code that ran under RoadRunner (just no longer crashing under classic mode, per above) — porting them to FrankenPHP's built-in Mercure hub and X-Sendfile support is separate, later work.
