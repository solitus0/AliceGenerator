# Custom object handlers

The builder registers handlers for Doctrine collections, `DateTime`, `DateTimeImmutable`, and PHP enums.
After these handlers, the generator can convert a nested `Stringable` object to literal text if the metadata
policy allows it and the destination accepts text. The Doctrine handler keeps mapped objects as fixtures.
Register a handler when a value object needs a specific reconstruction expression.

## Register a handler

A handler receives a `ValueContext`. Return `false` to let the next handler try or `true` after setting or
skipping the value. Newly registered handlers run first and can override the built-in handlers.

This handler reconstructs a `DateTimeZone` when Alice loads the fixture:

```php
use Solitus0\AliceGenerator\AliceExpression;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerInterface;
use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerRegistryInterface;
use Solitus0\AliceGenerator\ValueContext;

final class DateTimeZoneHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $context): bool
    {
        $value = $context->getValue();
        if (!$value instanceof \DateTimeZone) {
            return false;
        }

        $expression = sprintf('<(new \DateTimeZone(%s))>', var_export($value->getName(), true));
        $context->setSourceValue(new AliceExpression($expression));

        return true;
    }
}

$generator = FixtureGeneratorBuilder::create()
    ->configureObjectHandlerRegistry(static function (ObjectHandlerRegistryInterface $registry): void {
        $registry->registerHandler(new DateTimeZoneHandler());
    })
    ->build();
```

For a `Schedule` whose public `timeZone` property is set to `new \DateTimeZone('Europe/Vilnius')`, the generator emits
an Alice expression that reconstructs the value.

```yaml
Schedule:
    Schedule-1:
        timeZone: "<(new \\DateTimeZone('Europe/Vilnius'))>"
```

Use `setSourceValue()` for literal data and wrap deliberate Alice syntax in `AliceExpression`.
`setValue()` accepts fixture-ready syntax, including strings inside arrays. Use it only when those strings
should be interpreted by Alice.
