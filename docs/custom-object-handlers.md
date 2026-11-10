# Custom Object Handlers

AliceGenerator uses object handlers to transform specific object types (e.g., DateTime, UUIDs)
into fixture-ready values. You can implement custom handlers to support any domain-specific type.

## Built-in Handlers

By default, AliceGenerator registers handlers for:

- `CollectionHandler` for Doctrine collections
- `DateTimeHandler` for `\DateTime`
- `DateTimeImmutableHandler` for `\DateTimeImmutable`
- `EnumHandler` for PHP enums

After registered handlers run, AliceGenerator can export an unmapped nested value with `__toString()`
as literal text. With the default property namer, it checks the destination property, constructor argument,
setter, or collection adder before converting the value. Mapped objects and root objects retain their fixture
identity. Register a custom handler for value objects that need a specific reconstruction expression.

## Implementing a Custom Handler

To add support for a custom type, create a class that implements `ObjectHandlerInterface`:

```php
namespace App\Alice\ObjectHandler;

use Solitus0\AliceGenerator\ObjectHandler\ObjectHandlerInterface;
use Solitus0\AliceGenerator\ValueContext;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $context): bool
    {
        $value = $context->getValue();
        if (!$value instanceof PhoneNumber) {
            return false;
        }

        $formatted = PhoneNumberUtil::getInstance()
            ->format($value, PhoneNumberFormat::E164);

        $context->setValue(sprintf('<phone(%s)>', $formatted));

        return true;
    }
}
```

## Registering the Handler

When building your fixture generator, register the handler via the `configureObjectHandlerRegistry()` callback:

```php
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use App\Alice\ObjectHandler\PhoneNumberHandler;

$generator = FixtureGeneratorBuilder::create()
    ->configureObjectHandlerRegistry(function ($registry) {
        $registry->registerHandler(new PhoneNumberHandler());
    })
    ->build();
```

## Handler Contract

- Returning `true` from `handle()` stops further processing and accepts the transformed value.
- Returning `false` allows the next registered handler to attempt processing.
- Use `setSourceValue()` for literal data and `setValue()` for fixture-ready Alice syntax.
