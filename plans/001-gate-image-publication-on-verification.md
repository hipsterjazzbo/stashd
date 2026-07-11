# Plan 001: Gate image publication on application verification

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving to the next step. If anything in the "STOP conditions" section occurs, stop and report - do not improvise. When done, update the status row for this plan in `plans/README.md`.
>
> **Drift check (run first)**: `git diff --stat 6b5bb50..HEAD -- .github/workflows/docker-image.yml composer.json tests/docker/smoke.sh`
> If any in-scope file changed since this plan was written, compare the "Current state" excerpts against the live code before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: LOW
- **Depends on**: none
- **Category**: tests
- **Planned at**: commit `6b5bb50`, 2026-07-11

## Why this matters

The repository's only GitHub Actions workflow currently validates a Docker build, then publishes the image for pushes to `main` and version tags. It does not execute the existing PHP quality checks: Pest, Pint, PHPStan, or the Docker smoke workflow. That means a syntactically buildable image can be published even when domain behavior, formatting, static analysis, or the boot-to-HTTP Docker path is broken.

The resulting workflow must make successful PHP quality checks and Docker smoke verification prerequisites for the existing multi-architecture image build and publication. Pull requests must run the same gates without publishing any image.

## Current state

- `.github/workflows/docker-image.yml` is the only CI workflow. It has one `build` job, currently starting at line 16.
- The job checks out code, configures QEMU and Buildx, computes GHCR metadata, then runs `docker/build-push-action@v6` for `linux/amd64,linux/arm64` at lines 52-65. `push` is already false for pull requests.
- `composer.json` defines the canonical commands:

  ```json
  "test": "ENVIRONMENT=testing vendor/bin/pest",
  "test:docker-smoke": "tests/docker/smoke.sh",
  "lint": "vendor/bin/pint --test",
  "test:static": "vendor/bin/phpstan analyse --memory-limit=1G"
  ```

- `tests/docker/smoke.sh` builds a local `stashd:smoke` image by default, starts it with temporary `/data` and `/media` directories, and exercises the release container over HTTP. It chooses Docker when available and otherwise Podman; GitHub-hosted Ubuntu runners provide Docker.
- The repository's current PHP convention is Composer scripts, not direct `vendor/bin` invocations. The engineering instructions in `AGENTS.md` list `composer lint`, `composer test:static`, `composer test`, and `composer test:docker-smoke` as the standard checks.
- No test reports or coverage-upload integrations exist. Keep output in GitHub Actions logs and preserve the current GHCR metadata/publish behavior.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| PHP style | `composer lint` | exit 0; Pint reports no formatting violations |
| Static analysis | `composer test:static` | exit 0; PHPStan reports no errors against the committed baseline |
| Application tests | `composer test` | exit 0; Pest suite passes, with only documented opt-in tests skipped |
| Container smoke | `composer test:docker-smoke` | exit 0; image builds, starts, and smoke assertions pass |
| Workflow syntax review | inspect the changed YAML with `git diff --check` | exit 0; no whitespace errors |

## Scope

**In scope**:

- `.github/workflows/docker-image.yml`
- `plans/README.md` status row only

**Out of scope**:

- `Dockerfile`, `docker-compose.yml`, `tests/docker/smoke.sh`, and all application/test source.
- Adding a new CI provider, publishing test results, code coverage, browser testing, or TypeScript typechecking.
- Altering registry ownership, image tags, permissions, release triggers, or the existing build target/platforms.
- Replacing the existing smoke test with a different container test.

## Git workflow

- Branch: `advisor/001-gate-image-publication-on-verification`
- Commit message style: conventional lower-case messages such as `fix: retry...`, `test: add...`, and `docs: add...`; use `ci: gate image publication on verification`.
- Do not push or open a PR unless the operator instructs it.

## Steps

### Step 1: Add a PHP quality job

In `.github/workflows/docker-image.yml`, add a `quality` job before `build` that:

1. Runs on `ubuntu-latest`.
2. Checks out the repository with `actions/checkout@v4`.
3. Sets up PHP 8.5 with a maintained PHP setup action already accepted by the repository's CI policy. Include the extensions needed by Composer and the test suite, at minimum `sqlite3` and `mbstring`; do not add a production-only extension merely to satisfy CI.
4. Enables Composer's dependency cache keyed by `composer.lock` using the setup action's supported cache option or a scoped cache step.
5. Installs locked dependencies with `composer install --no-interaction --prefer-dist --no-progress`.
6. Runs these commands in separate named steps, in order: `composer lint`, `composer test:static`, `composer test`.

Do not use `composer update`, do not modify the lockfile, and do not suppress failures with `continue-on-error`.

**Verify**: `composer lint && composer test:static && composer test` locally or in the project container -> all commands exit 0.

### Step 2: Add a Docker smoke job

Add a `smoke` job that declares `needs: quality`, runs on `ubuntu-latest`, checks out the repository, and runs `composer test:docker-smoke` in a named step.

The script invokes Docker directly and builds its own `stashd:smoke` image, so do not add QEMU, Buildx, registry login, image metadata, or a second manual image-build command to this job. The smoke job must run for pull requests, `main`, tags, and manual dispatch; do not make it conditional on publication.

**Verify**: `composer test:docker-smoke` -> exits 0 with the container smoke checks passing. If Docker is unavailable locally, verify the YAML relationship with `git diff --check` and record that the smoke command must run in Actions.

### Step 3: Make image construction and publishing depend on verification

Update the existing `build` job to declare `needs: [quality, smoke]`.

Leave its QEMU setup, Buildx setup, non-PR GHCR login condition, metadata generation, build target, two platforms, cache configuration, and `push: ${{ github.event_name != 'pull_request' }}` behavior unchanged. This preserves PR build validation while preventing build/push from starting after a failed verification job.

**Verify**: review `.github/workflows/docker-image.yml` and confirm the dependency graph is `quality -> smoke -> build` plus `quality -> build` through the explicit `needs` list. A pull request still reaches `build` only after both checks succeed, and its `push` expression remains unchanged.

### Step 4: Review the workflow diff and update plan status

Confirm that only `.github/workflows/docker-image.yml` and the plan index status changed. Ensure the workflow does not expose registry credentials to pull requests and does not alter image publication permissions.

Update this plan's row in `plans/README.md` from `TODO` to `DONE` only after the checks above pass.

**Verify**: `git diff --check` -> exit 0; `git status --short` -> no changed files outside `.github/workflows/docker-image.yml` and `plans/README.md`, apart from pre-existing operator changes.

## Test plan

- Run the existing complete PHP suite through `composer test`; this covers authenticated HTTP, commands/jobs, migrations, Vault behavior, broadcasts, and provider fixtures.
- Run `composer lint` and `composer test:static` to prove the CI commands themselves are valid against the locked dependency set.
- Run `composer test:docker-smoke` to prove the job's release-container command works end to end.
- On the pull request that implements this plan, confirm the Actions UI shows `quality`, `smoke`, and `build`, and that `build` is skipped/not started when either prerequisite fails. Do not manufacture a failing commit merely to demonstrate dependency behavior.

## Done criteria

- [ ] `.github/workflows/docker-image.yml` defines a `quality` job that installs locked dependencies and runs `composer lint`, `composer test:static`, and `composer test`.
- [ ] The workflow defines a `smoke` job that needs `quality` and runs `composer test:docker-smoke` on every current workflow trigger.
- [ ] The existing `build` job needs both `quality` and `smoke`.
- [ ] Pull request image builds retain `push: false`; non-PR GHCR login remains conditional.
- [ ] `composer lint`, `composer test:static`, and `composer test` exit 0.
- [ ] `composer test:docker-smoke` exits 0 in an environment with Docker.
- [ ] `git diff --check` exits 0.
- [ ] No out-of-scope files are modified, excluding pre-existing operator changes.
- [ ] `plans/README.md` records the completed status.

## STOP conditions

- PHP 8.5 cannot be installed on GitHub-hosted runners with the selected setup action, or Composer cannot install the locked dependencies there. Stop and report the full action error; do not downgrade PHP or loosen the Composer constraint.
- `composer test` needs secrets, live-provider flags, or a non-SQLite service in CI. Stop and identify the exact test instead of skipping the suite wholesale.
- The smoke test requires privileged Docker configuration unavailable to the runner. Stop and report the runner error; do not replace it with a build-only check.
- Achieving the dependency graph requires changing Docker publishing semantics, image tags, registry permissions, or an out-of-scope file.
- The workflow has materially changed from the excerpts above since commit `6b5bb50`.

## Maintenance notes

- Keep the quality job's commands aligned with Composer scripts. When a new mandatory quality script is added, decide deliberately whether it belongs in this job.
- The Docker smoke test intentionally rebuilds a local single-platform image. The existing `build` job remains the multi-platform publication gate; do not try to share an image artifact across architectures without measuring the complexity and time trade-off.
- A future browser/TypeScript plan can add separate frontend gates. Do not fold those into this workflow change, since the frontend typecheck currently has an existing diagnostic that needs its own remediation plan.
