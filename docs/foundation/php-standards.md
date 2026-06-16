# PHP coding standards (PSR)

Stashd follows applicable [PHP-FIG](https://www.php-fig.org/psr/) standards. Enforce them before committing.

## Applicable PSRs

| PSR | Scope | How we comply |
|-----|--------|----------------|
| **PSR-1** | Basic coding | `declare(strict_types=1);`, namespaces, **one class/enum/interface/trait per file** |
| **PSR-4** | Autoloading | `App\` → `app/`, `Tests\` → `tests/` (`composer.json`) |
| **PSR-12** | Code style | Laravel Pint preset `psr12` (`pint.json`) |
| **PSR-3** | Logging | Via Tempest log package when used (not custom loggers in domain) |
| **PSR-7 / 17** | HTTP messages | RoadRunner bridge uses `nyholm/psr7`; Tempest handles request/response internally |
| **PSR-11** | Container | Tempest `Container` / DI; prefer constructor injection |
| **PSR-15** | HTTP middleware | `RequireAuthMiddleware` implements Tempest `HttpMiddleware` |

PSRs we do **not** implement directly today: PSR-6/16 (cache), PSR-18 (HTTP client — Tempest wraps Guzzle), PSR-20 (clock — Tempest `Clock`).

## Commands

```bash
composer lint      # check PSR-12 (CI gate)
composer format    # auto-fix style
```

## Non-negotiables

1. **One type per file** — enums, records, services, exceptions each get their own file matching the type name (see `database-conventions.md`). Multi-type files break Tempest discovery with fatal redeclare errors.
2. **`declare(strict_types=1)`** on every new PHP file.
3. **Import types** — no `\Fully\Qualified\Class` in method bodies when a `use` statement suffices.
4. **Alphabetical `use` imports** — Pint enforces `ordered_imports`.
5. **No closing `?>`** in pure PHP files.

## API vs PHP naming

PSR-12 governs PHP source. REST JSON remains **snake_case** per the engineering spec; translate at controller boundaries only.
