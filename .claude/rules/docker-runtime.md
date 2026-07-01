---
paths:
  - "Dockerfile"
  - "docker-compose.yml"
  - ".rr.yaml"
  - "docker/**/*"
  - "scripts/**/*"
  - "tests/docker/**/*"
  - ".env.example"
---

# Docker and runtime rules

Stashd is Docker-first homelab infrastructure.

Default user promise:

```text
copy → paste → docker compose up → works
```

## Runtime expectations

- Default port: `8474`.
- No Nginx/PHP-FPM required in the default deployment.
- RoadRunner serves HTTP and supports worker/runtime roles.
- SQLite, app key/secrets, storage roots, and migrations must survive restart.
- Error messages should be clear and actionable for homelab users.

## Docker smoke is a release gate

Runtime-related changes should consider:

```bash
composer test:docker-smoke:no-build
composer test:docker-smoke
```

Docker smoke should cover boot, RoadRunner, Tempest, SQLite migration, storage roots, health, setup/auth, worker/scheduler, fake provider/download, fake broadcast, SSE, restart persistence, and clean shutdown.

## Avoid

- Requiring external DB/Redis/RabbitMQ for v1.
- Hiding permission problems.
- Assuming root runtime.
- Adding Kubernetes-first complexity.
- Breaking PUID/PGID-style homelab expectations without a deliberate decision.
