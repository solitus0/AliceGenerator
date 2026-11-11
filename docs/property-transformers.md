# Property transformers

Use a rule to replace a property value in the generated fixture. Rules leave the source object intact when
the transformer returns a replacement or sets it on the value context.

## Replace a property value

```php
use App\Entity\User;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\PropertyTransformer\ClassPropertyMatcher;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyTransformRule;
use Solitus0\AliceGenerator\PropertyTransformer\SimpleValueTransformer;

$context = FixtureGenerationContext::create()
    ->addPropertyTransformRule(new PropertyTransformRule(
        new ClassPropertyMatcher(User::class, 'internalNote'),
        new SimpleValueTransformer('Redacted for tests')
    ));
```

Pass the context to `generate()`. For a `User` with a populated `internalNote`, the fixture contains the replacement.

```yaml
App\Entity\User:
    User-1:
        internalNote: 'Redacted for tests'
```

The first matching rule wins. Rules act on eligible properties before
recursive traversal and object handling. A property skipped by the metadata handler or supplied by a metadata
override does not pass through these rules. Redacting one field leaves other exported fields eligible.

## Matchers and transformers

- `PropertyMatcher` matches a property name across classes. `ClassPropertyMatcher` matches an exact class and
  property name from the property's metadata. Implement `PropertyMatcherInterface` for other matching rules.
- `SimpleValueTransformer` uses a fixed replacement. `CallbackValueTransformer` passes a `ValueContext` to
  your callable and uses its return value. `getValue()` returns the current value without Alice escaping.
- A custom `ValueTransformerInterface::transform()` can set a replacement with `setSourceValue()`.

## Literal values and Alice expressions

The built-in transformers treat strings as literal data, even when they begin with `@` or resemble an Alice
expression. To supply syntax intentionally, return or pass an `AliceExpression`:

```php
use Solitus0\AliceGenerator\AliceExpression;
use Solitus0\AliceGenerator\PropertyTransformer\SimpleValueTransformer;

$transformer = new SimpleValueTransformer(AliceExpression::reference('User-1'));
```

The referenced fixture key must exist in the load. For other syntax, use `new AliceExpression('<(1 + 2)>')`.
In custom transformers, use `setSourceValue()` with wrapped expressions to mix literals and syntax in an array.
`setValue()` is for values that are already in fixture syntax.
