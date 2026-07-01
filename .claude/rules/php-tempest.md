---
paths:
  - "app/**/*.php"
  - "tests/**/*.php"
  - "composer.json"
  - "pint.json"
  - "phpstan.neon"
---

# PHP / Tempest rules

- Target PHP `^8.5` as declared by the project.
- Follow the repo's Pint/PSR-12 configuration.
- Prefer explicit types, enums for bounded state, small DTOs/value objects, and readable constructor property promotion.
- Do not add `@param`/`@return` docblocks for typed signatures.
- Class docblocks are welcome only when they explain purpose, boundary, or assumptions.
- Business logic does not belong in controllers.
- Follow Tempest-native conventions already present in the repo before importing Laravel/Symfony habits.
- Do not add dependencies without a clear repo-level reason.
- Avoid generic service/object names: `Manager`, `Helper`, `Util`, `Processor`, `Data`, `Info`, `Thing`.

## Public API output

Use explicit resource DTOs or arrays for public/security-sensitive output.

Avoid auto-serializing Tempest records directly to public API responses.

## Verification

Prefer:

```bash
composer lint
composer test:static
composer test:unit -- --filter RelevantTest
composer test:feature -- --filter RelevantTest
```

Then broaden when warranted:

```bash
composer test
```
