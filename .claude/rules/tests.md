---
paths:
  - "tests/**/*.php"
  - "phpunit.xml"
  - "composer.json"
  - "app/**/*.php"
  - "phpstan.neon"
  - "phpstan-baseline.neon"
---

# Testing rules

Use focused checks during implementation and broader checks before completion when warranted.

## Commands

```bash
composer lint
composer test:static
composer test:unit
composer test:feature
composer test
composer test:docker-smoke:no-build
composer test:docker-smoke
```

`test:static` runs PHPStan at `level: max` against `app/`. New code must pass
clean — do not add entries to `phpstan-baseline.neon` for anything you write;
regenerate the baseline only to absorb pre-existing debt you're not touching,
never to silence a finding in your own diff.

## Test style

- Prefer behavior tests over implementation trivia.
- Use fake providers/fixtures for normal CI.
- Live provider/download tests are opt-in only.
- Security-sensitive behavior needs negative tests.
- Filesystem/broadcast tests should check both DB expectations and filesystem reality.
- Do not claim tests passed unless actually run and passed.

## Token-efficient test output

Prefer:

```bash
composer test 2>&1 | scripts/claude/trim-test-output.sh
```

or inspect the first relevant failure before reading full output.
