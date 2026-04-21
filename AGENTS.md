# Repository Guidelines

## Project Structure & Module Organization
Core library code lives in `src/AliceGenerator/` and is PSR-4 autoloaded under `Solitus0\AliceGenerator\`. Main areas are split by responsibility, for example `Generator/`, `MetadataHandler/`, `ObjectHandler/`, `PropertyTransformer/`, and `ReferenceNamer/`. Tests live in `tests/AliceGenerator/` with shared bootstrap logic in `tests/bootstrap.php`. Documentation is in `docs/`, and temporary analyzer output is written to `var/`.

## Build, Test, and Development Commands
Install dependencies with `composer install`.

- `make test` runs PHPUnit 10 with `phpunit.xml.dist`.
- `make ecs_check` runs EasyCodingStandard in check mode.
- `make ecs` runs ECS with `--fix`.
- `make rector` applies automated refactors from `rector.php`.
- `make phpstan` runs static analysis and writes a report to `var/phpstan/phpstan-report.json`.
- `make before_push` runs the full local quality gate: ECS fix, Rector, PHPStan, and tests.

Run commands from the repository root.

## Coding Style & Naming Conventions
Target PHP is `^8.1`. Follow existing PSR-4 namespace layout and keep new classes under `src/AliceGenerator/...`. Use 4-space indentation, LF line endings, and keep lines near the configured `120` character limit from `.editorconfig`. Match existing naming: classes in `PascalCase`, methods/properties in `camelCase`, and test classes ending with `Test`. Prefer small, focused classes over cross-cutting refactors.

## Testing Guidelines
This project uses PHPUnit 10. Add unit tests in `tests/AliceGenerator/` and mirror the production class or feature name where practical, for example `FixtureGeneratorBuilderTest.php`. Cover behavior changes and edge cases, especially around recursion, metadata handling, and fixture serialization. Run `make test` before submitting; run `make phpstan` for type and rule regressions.

## Commit & Pull Request Guidelines
Recent history uses short Conventional Commit style subjects such as `fix: ...`, `feat: ...`, `docs: ...`, and `style: ...`. Keep commit messages imperative and scoped to one change. Pull requests should include a brief description, rationale, and the verification performed. Link related issues when applicable and include output examples only when behavior or generated fixtures changed.

## Security & Configuration Tips
Do not commit generated reports from `var/` or local environment changes. Keep library changes backward-compatible unless the PR explicitly documents a breaking change.
