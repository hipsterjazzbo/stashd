# Database conventions (Tempest + Stashd)

This document records naming decisions for SQLite schema and PHP models. Read it before adding migrations.

**Proof tests:** `tests/Unit/Database/TempestColumnMappingSpikeTest.php` (run with `vendor/bin/pest tests/Unit/Database/TempestColumnMappingSpikeTest.php`).

## Decision (verified against Tempest v3)

| Layer | Convention | Example |
|-------|------------|---------|
| **Table names** | snake_case, plural | `storage_locations`, `commands`, `jobs` |
| **Column names** | **camelCase** (must match PHP property names) | `createdAt`, `freeBytes`, `commandId` |
| **PHP model properties** | camelCase (identical to columns) | `$freeBytes`, `$supportsHardlinks` |
| **Public IDs** | prefixed ULID strings | `cmd_…`, `job_…`, `storage_…` |
| **REST/API JSON** | snake_case (per engineering spec) | `free_bytes`, `supports_hardlinks` — map at API boundary |

Foundation migrations use Tempest's `CreateTableStatement` with camelCase field names (e.g. `->integer('freeBytes')`). SQLite stores them as quoted camelCase identifiers.

## What Tempest actually does (spike results)

Tempest's database layer uses **PHP property names as SQL column identifiers** for SELECT, INSERT, UPDATE, and `save()`. There is no automatic snake_case ↔ camelCase translation at the database layer.

| Mechanism | Applies to DB SQL? | Notes |
|-----------|-------------------|-------|
| Property name | **Yes** | `createdAt` property → `` `createdAt` `` column in all queries |
| `NamingStrategy` (`PluralizedSnakeCaseStrategy`) | **Tables only** | `CommandRecord` → `command_records`; does not affect columns |
| `#[MapFrom]` / `#[MapTo]` | **No** | Generic array/object mapper only; does not change generated SQL |
| snake_case PHP properties | **Yes** | Tempest's own `DatabaseSession` model uses `$created_at` matching `created_at` columns |

### What does *not* work without pain

**snake_case SQLite columns + camelCase PHP properties** (preferred aesthetically, unsupported cleanly):

- `Model::select()` generates `SELECT … createdAt …` and fails against `created_at` columns
- `insert($model)` generates `` INSERT … (`createdAt`) `` and fails
- `save()` / `update()` have the same problem
- `#[MapFrom('created_at')]` on `$createdAt` does **not** fix SQL generation — only generic array hydration
- Array inserts with snake_case keys can write rows, but the model still cannot SELECT them back

### Viable alternatives considered

1. **camelCase columns + camelCase properties (chosen)** — full CRUD via `IsDatabaseModel` with zero mapping boilerplate
2. **snake_case columns + snake_case properties** — works, but PHP properties look unidiomatic (`$created_at`)
3. **snake_case columns + camelCase properties + MapFrom/MapTo on every field** — still broken for SELECT/INSERT/UPDATE; not viable

## Rules going forward

- **Table names:** snake_case plural, explicit via `#[Table(name: …)]` when the spec name differs from Tempest's default.
- **Column names:** camelCase in migrations and SQLite; must match model property names exactly.
- **API responses:** translate to snake_case in controllers/DTOs, not in the database layer.
- **Spec prose** showing `free_bytes` describes the API/document field; the SQLite column remains `freeBytes`.
- Do **not** mix conventions in one table (e.g. `created_at` column with `$createdAt` property).
- **One enum per file** (PSR-4): filename must match the enum class name or Tempest discovery will fatal on redeclare.
- **CompoundStatement migrations:** Tempest only merges `trailingStatements` (indexes/uniques) from the root statement — use `MigrationSchemaHelpers::tablesWithIndexes()` when bundling multiple `CreateTableStatement` instances.
- **SQLite foreign keys:** Tempest strips `foreignKey()` from `CREATE TABLE` on SQLite — use inline `REFERENCES` via `MigrationSchemaHelpers::fkColumn()` and enable `PRAGMA foreign_keys = ON` (see `SqliteConfigurator`).

## Spec field names vs SQLite columns

The engineering spec describes fields in snake_case for API/documentation (e.g. `provider_item_id`, `stash_id`). SQLite columns and PHP properties use camelCase (`providerItemId`, `stashId`). Only the HTTP/JSON boundary uses snake_case.

## Prefixed ULID primary keys

Use `VARCHAR(40) NOT NULL PRIMARY KEY` via migration `raw()` for prefixed IDs.

**Do not** call `$record->save()` with a pre-set string primary key — Tempest treats that as an UPDATE. Use `query(Model::class)->insert($record)` for creates (see `Infrastructure/Persistence/*Repository.php`).

## Reference: Tempest session model

Tempest's built-in session persistence uses snake_case for **both** columns and properties (`DatabaseSession::$created_at`). That pattern works, but Stashd keeps camelCase PHP properties and therefore camelCase columns.
