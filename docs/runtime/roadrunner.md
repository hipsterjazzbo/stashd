# RoadRunner runtime

Stashd uses [RoadRunner](https://roadrunner.dev/) as the HTTP application server in Docker and production-like environments. PHP-FPM is not required for the default deployment.

## Why a custom PSR-7 bridge exists

Tempest's built-in `HttpApplication::run()` reads one request from PHP's SAPI globals and exits. RoadRunner instead:

1. Starts a long-lived PHP worker (`bin/worker.php`)
2. Sends each HTTP request as a PSR-7 message over the worker relay
3. Expects a PSR-7 response back

There is **no official Tempest ↔ RoadRunner integration** today ([tempest-framework#2011](https://github.com/tempestphp/tempest-framework/issues/2011)). Stashd therefore ships a small adapter:

| File | Role |
|------|------|
| `bin/worker.php` | Process entrypoint referenced from `.rr.yaml` |
| `app/Infrastructure/RoadRunner/TempestPsr7Bridge.php` | RR loop → `Router::dispatch()` → PSR-7 response |
| `.rr.yaml` | RoadRunner HTTP listener (port `8474`) |

This code is **framework glue**, not product/domain logic. Delete or replace it when Tempest adds a supported long-running server driver.

## Temporary vs permanent

**Temporary (adapter layer)**

- `TempestPsr7Bridge` and its `toPsr7()` conversion
- Manual `Container::reset()` after each request (resettable services such as `AuthContext`)
- `bin/worker.php` remaining separate from `public/index.php`

**Permanent (product decisions)**

- RoadRunner as the default runtime
- Role commands: `stashd serve`, `stashd worker`, `stashd scheduler`, `stashd all`
- Docker supervisord running RR + background roles

## Local development

```bash
# Traditional SAPI (single request, useful for quick debugging)
php -S localhost:8474 -t public public/index.php

# Production-like RoadRunner loop (requires `./rr` from `vendor/bin/rr get`)
./rr serve -c .rr.yaml
```

## Docker

The container entrypoint runs `stashd:boot`, then supervisord starts:

- RoadRunner (`stashd serve`)
- Job poll loop (`stashd worker`)
- Scheduler loop (`stashd scheduler`)

See `docker/supervisord.conf` and `docker/entrypoint.sh`.
