# Repository guidelines

Library code lives in `src/AliceGenerator/` under the PSR-4 namespace `Solitus0\AliceGenerator`.
The recipe CLI entry point is `bin/alice-export`. Tests live in `tests/AliceGenerator/` with shared setup
in `tests/bootstrap.php`. Keep user guides in `docs/` and temporary analysis output in `var/`.

## Local checks

Install dependencies with `composer install` and run commands from the repository root.
CI checks PHP 8.1. The Doctrine and export tests use `pdo_sqlite`, and the export tests also need `proc_open`.

| Command | Purpose |
| --- | --- |
| `make test` | Run PHPUnit 10 with `phpunit.xml.dist`. |
| `make test_coverage` | Run PHPUnit with Xdebug coverage and write HTML to `var/coverage/`. |
| `make ecs_check` | Check coding standards. |
| `make ecs` | Apply ECS fixes. |
| `make rector` | Apply automated refactors. |
| `make phpstan` | Run static analysis and write `var/phpstan/phpstan-report.json`. |
| `make before_push` | Apply ECS and Rector changes, then run PHPStan and PHPUnit. |

Run `make test` before submitting and `make phpstan` for type and rule regressions.
Cover behavior changes and edge cases, especially traversal, metadata, and fixture reloads.
Name tests after the production class or behavior they exercise.

## Changes and reviews

Target PHP `^8.1` and follow the existing namespace layout and naming conventions. Use four spaces, LF,
and the 120-character line length from `.editorconfig`. Keep changes focused.
Preserve backward compatibility unless the pull request documents a breaking change.

Use short, imperative Conventional Commit subjects and keep each commit scoped to one change.
In pull requests, describe the change, why it matters, and how it was verified. Link related issues
and include output examples when fixture output changes. Keep generated reports and local configuration out of commits.
