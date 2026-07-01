---
paths:
  - "app/Database/**/*.php"
  - "app/**/*.php"
  - "tests/**/*.php"
---

# SQLite and Tempest records

Stashd v1 uses SQLite seriously.

## Conventions

- SQLite columns use camelCase to match Tempest record properties.
- Public API JSON uses snake_case.
- Prefer explicit migrations and tests for persistence changes.
- Enable/expect foreign keys where relevant.
- Keep transactions short.
- Be careful with write locks and long-running work.
- Use indexes for lookup paths that command/job/provider routes depend on.

## Avoid

- PostgreSQL-only SQL.
- MySQL-specific assumptions.
- Long write transactions around network/filesystem work.
- Background jobs that hold SQLite write locks while doing slow IO.
- Schema changes without migration/rollback thinking.

## Migration safety

Before touching migrations, consider:

```text
backup before migration
idempotence for dev/test
existing data shape
Docker startup behavior
test database behavior
```
