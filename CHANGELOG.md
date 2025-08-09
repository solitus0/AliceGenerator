# Changelog

## [v0.1.4] – 2025-08-10

### Added
- **`Solitus0\AliceGenerator\PropertyTransformer\CallbackValueTransformer`**  
  Generic value transformer that delegates transformation to a user-supplied callable.  
  The callable receives a `ValueContext` and must return the transformed value.

- **`Solitus0\AliceGenerator\ReferenceNamer\CallbackNamer`**  
  Flexible reference namer that generates fixture references via a user-provided  
  `\Closure (object $object, int $key): string`.

### Changed
- **`setSkipNonWritableProperties`** default value changed from `false` to `true` to skip non-writable properties by default.

### Fixed
- **HTML/XML literal handling in `getValue()`**  
  Prevent Alice from misinterpreting raw HTML/XML as evaluated expressions:
    - Encodes strings containing tags with  
      `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
    - Wraps the encoded value in an Alice expression using  
      `htmlspecialchars_decode($encoded, ENT_QUOTES)` at runtime.

---

## [v0.1.3] – 2025-07-01

### Added
- **Property Transformer Support**  
  Introduced the `PropertyTransformerInterface` and related infrastructure to allow  
  custom transformation of property values during fixture generation.  
  See [Property Transformers documentation](https://github.com/solitus0/AliceGenerator/blob/master/docs/property-transformers.md)  
  for usage examples and available built-in transformers.

---

## [v0.1.2] – 2025-06-28

### Added
- **`setSkipNonWritableProperties()` configuration option**  
  Skips non-writable object properties during fixture generation (e.g., read-only or without setters).  
  *Requires* `symfony/property-access`.

### Changed
- Minor API naming consistency improvements for collection-related configuration methods.

---

