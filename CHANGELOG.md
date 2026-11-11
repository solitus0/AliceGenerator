# Changelog

## v0.2.0 (2026-11-11)

### Added

- [Recipe-based fixture export](docs/fixture-export.md) with `alice-export preview`, `validate`, and `export`.
  Recipes select records and fields, and export writes YAML only after it reloads successfully.
- [Constructor bypass](docs/configuration.md#how-fixtures-are-written) for classes whose setters can restore their state,
  plus collection adder calls and discovery of children through unwritable Doctrine inverse collections.

### Changed

- Composer's runtime API 2.2 or later is required.
- Built-in property transformers treat strings as literals. Wrap intended Alice syntax in
  [`AliceExpression`](docs/property-transformers.md#literal-values-and-alice-expressions). Transformer callbacks
  now read values before Alice escaping through `ValueContext::getValue()`.
- Generated enum expressions use case names. Eligible nested `Stringable` values can become literal text when
  the destination accepts it.
- The generator resolves required constructor arguments from matching properties and initialized defaults.
  Doctrine export preserves mapped relationships and composite identifiers on reload.
- Reference names and object identity are tracked per generation run. Colliding names get distinct suffixes.

### Fixed

- Literal strings round-trip through Alice, including multiline HTML and text that resembles fixture syntax.
- Fixture generation no longer emits a reflection deprecation on PHP 8.5.

### Removed

- **Breaking for direct callers.** Removed the legacy JMS `Metadata\Driver\AttributeDriver` and
  `Metadata\Provider\ClassMetadataProvider`. Use `FixtureGeneratorBuilder` for reflection discovery or
  implement `ClassMetadataProviderInterface` for custom discovery.
- **Breaking for direct callers.** Removed `Storage\ObjectCacheCollection` and `ObjectHandler\RamseyUuidHandler`.
  Use the [object handler guide](docs/custom-object-handlers.md) if a value object needs custom reconstruction.

## v0.1.5 (2026-04-21)

- Added `__construct` and `__calls` output for non-writable values that match constructor arguments or public setters.

## v0.1.4 (2025-08-10)

- Added `CallbackValueTransformer` and `CallbackNamer`.
- Made skipping non-writable properties the default. Added the Doctrine options `skipIdentifiers` and
  `skipEmptyStrings`, both enabled by default.
- Fixed literal HTML/XML handling, mapped property checks, and generated or UUID/ULID identifier detection.

## v0.1.3 (2025-07-01)

- Added [property transformation rules](docs/property-transformers.md) and `ValueTransformerInterface`.

## v0.1.2 (2025-06-28)

- Added `setSkipNonWritableProperties()` and standardized collection configuration method names.

## v0.1.1 (2025-06-23)

- Added Symfony 7 support.

## v0.1.0 (2025-06-22)

- Initial release of the independently maintained fork.
