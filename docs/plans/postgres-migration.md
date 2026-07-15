# PostgreSQL migration plan and handoff

Status: foundation implemented on `agent/postgres-migration-foundation`; PostgreSQL is not yet a supported production runtime.

## Goal

Replace the default SQLite deployment with a PostgreSQL Docker sidecar while allowing an existing Docker installation to upgrade in place without losing data.

## Decisions

- Use Tempest's native `PostgresConfig`, migrations, query statements, transactions, and tagged databases where two simultaneous connections are needed during import.
- Keep database columns camelCase. Tempest maps record properties directly to column names, and the compatibility proof confirms quoted camelCase identifiers round-trip correctly on PostgreSQL.
- Keep public API JSON snake_case.
- Do not add Redis initially. PostgreSQL can provide durable job claiming with `FOR UPDATE SKIP LOCKED`; add Redis only if measured queue throughput or features justify another required service.
- Preserve SQLite support during the upgrade window and keep the legacy database as the rollback artifact.

## Completed foundation

- `DB_CONNECTION=sqlite|pgsql` selects the appropriate Tempest database config; SQLite remains the default.
- The application image includes both `pdo_sqlite` and `pdo_pgsql`.
- Test bootstrap creates a database per ParaTest worker when PostgreSQL is selected.
- A focused integration test proves Tempest model insert, select, update, booleans, floats, `DateTime`, and camelCase column names on PostgreSQL 18.
- Database config environment values are explicitly validated, removing the related PHPStan baseline entries.

Changed files:

- `.env.example`
- `Dockerfile`
- `app/Config/database.config.php`
- `app/Config/database.testing.config.php`
- `phpstan-baseline.neon`
- `tests/Pest.php`
- `tests/Unit/Database/TempestPostgresCompatibilityTest.php`

Verification completed:

- PostgreSQL compatibility proof: 1 passed, 7 assertions.
- `composer test:parallel`: 518 passed, 10 skipped, 2,686 assertions across 16 processes.
- Pint: passed.
- PHPStan: passed with no errors.

Local-only state: Lerd's PostgreSQL 18 service is installed and running, and the Stashd custom image has been rebuilt. Lerd currently routes `shell`/on-demand PHP commands for this custom-container site through the shared FPM runtime, so the PostgreSQL proof was executed directly in the Lerd-managed `lerd-custom-stashd` container.

## Remaining work

### 1. Make the application dialect-safe

- Replace concrete `SQLiteConfig` dependencies in boot, health, middleware, scheduler, worker, and rediscovery paths with `DatabaseConfig` or dialect-specific conditional behavior.
- Retain SQLite pragmas, WAL, and file backups only on SQLite.
- Convert raw SQLite/backtick migration fragments to Tempest query statements or the smallest dialect-specific statements required.
- Port job claiming to PostgreSQL row locking with `FOR UPDATE SKIP LOCKED`.
- Run the complete feature suite against both databases before changing deployment defaults.

### 2. Add the PostgreSQL sidecar

- Add a pinned PostgreSQL service, health check, persistent volume, and internal-only connection settings to `docker-compose.yml`.
- Make PostgreSQL the default for fresh installs only after the full suite and Docker smoke pass.
- Do not add Redis to the required deployment.

### 3. Implement the in-place importer

- Open the legacy SQLite database as a tagged, read-only Tempest connection while PostgreSQL is the primary connection.
- Stop application writes during import and create a timestamped SQLite backup first.
- Apply the normal Tempest schema migrations to an empty PostgreSQL database.
- Copy tables in dependency order inside bounded transactions while preserving IDs, timestamps, JSON payloads, and migration history.
- Verify per-table counts, foreign-key integrity, and critical domain invariants before success.
- On failure, leave SQLite untouched, exit startup clearly, and allow retry.
- On later boots, detect an already-completed matching import and skip safely; refuse to merge unrelated non-empty databases.

### 4. Prove upgrade and rollback

- Add a Docker smoke fixture representing the latest SQLite release.
- Boot the new compose stack, import it, verify auth/jobs/Vault/Broadcast state, restart, and verify persistence.
- Prove rollback by starting the prior image against the preserved SQLite database.

## Next action

Start with the concrete `SQLiteConfig` dependency inventory and migration SQL audit, then make one existing schema migration run unchanged on both SQLite and PostgreSQL before broad conversion.
