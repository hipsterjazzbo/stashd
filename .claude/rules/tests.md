---
paths:
  - "tests/**/*.php"
  - "phpunit.xml"
  - "composer.json"
  - "app/**/*.php"
---

# Testing rules

Use focused checks during implementation and broader checks before completion when warranted.

## Commands

```bash
composer lint
composer test:unit
composer test:feature
composer test
composer test:docker-smoke:no-build
composer test:docker-smoke
```

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
