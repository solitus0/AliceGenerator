# 🔧 Property Transformation Rules

Property transformation rules allow dynamic modification of object property values during fixture generation. This is useful for:

- Replacing sensitive values (e.g., passwords, emails)
- Providing deterministic outputs for tests
- Adjusting values to a specific range (e.g., dates)

---

## 📦 Components

### `PropertyTransformRule`

Represents a single rule combining a matcher and a value transformer:

```php
new PropertyTransformRule(
    PropertyMatcherInterface $matcher,
    ValueTransformerInterface $transformer
);
```

### `PropertyMatcherInterface`

Interface for matching a property.

Available implementations:
- `PropertyMatcher` — matches by property name
- `ClassPropertyMatcher` — matches by both class name and property name

### `ValueTransformerInterface`

Interface for transforming a value.

```php
public function transform(ValueContext $valueContext): void;
```

Available implementations:
- `SimpleValueTransformer` — replaces value with a static one
- `CallbackValueTransformer` — derives a value from the current `ValueContext`

---

## ✅ Basic Usage Example

Replace all `dateTo` values with a fixed date:

```php
$context = FixtureGenerationContext::create()
    ->addPropertyTransformRule(
        new PropertyTransformRule(
            new PropertyMatcher('dateTo'),
            new SimpleValueTransformer(new \DateTimeImmutable('+10 years'))
        )
    );
```

---

## 🧑‍💻 Example: Sanitize User Passwords

Ensure all passwords are set to a safe default when generating test fixtures:

```php
use Solitus0\AliceGenerator\PropertyTransformer\ClassPropertyMatcher;
use Solitus0\AliceGenerator\PropertyTransformer\SimpleValueTransformer;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyTransformRule;

$context->addPropertyTransformRule(
    new PropertyTransformRule(
        new ClassPropertyMatcher(\App\Entity\User::class, 'password'),
        new SimpleValueTransformer('test-password')
    )
);
```

> This ensures that any user password is overridden with a predictable value for login in test environments.

---

## ⚙️ Custom Value Transformers

Implement your own transformer:

```php
use Solitus0\AliceGenerator\PropertyTransformer\ValueTransformerInterface;
use Solitus0\AliceGenerator\ValueContext;

class RandomIntTransformer implements ValueTransformerInterface
{
    public function transform(ValueContext $valueContext): void
    {
        $valueContext->setValue(random_int(1, 100));
    }
}
```

---

## 🧠 How It Works

- Rules are evaluated in the order they were added.
- The first matching rule for a property is used — no further rules are checked once a match is found.
- If a rule's matcher applies, its transformer replaces the value.
- Unmatched properties are left unchanged.
- Rules are evaluated before the value is written to the fixture output and before any custom object handlers are applied.
