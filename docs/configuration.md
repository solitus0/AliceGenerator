# Configuration

Configure the generator with `FixtureGeneratorBuilder`. Use a [generation context](usage.md) for settings that vary by export,
such as traversal depth, collection filters, and [property transformations](property-transformers.md).

## Doctrine integration

Use your application's entity manager and pass an existing entity to the generator:

```php
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\MetadataHandler\DoctrineMetadataHandler;

$generator = FixtureGeneratorBuilder::create()
    ->setMetadataHandler(new DoctrineMetadataHandler($entityManager))
    ->build();

$yaml = $generator->generate($entity, FixtureGenerationContext::create());
```

The handler initializes lazy entities and uses Doctrine mapping to select fields, associations, and embeddables.
An unavailable related entity is omitted. Traversing lazy associations may query the database.

| Constructor option | Default behavior |
| --- | --- |
| `skipIdentifiers: true` | Omit generated single-field identifiers and single-field identifiers with recognized UUID or ULID mappings. Retain composite identifiers and other assigned identifiers. |
| `skipEmptyStrings: true` | Omit empty or whitespace-only strings from nullable mapped fields. |

Pass `false` for either option to keep those values. The default builder uses reflection without Doctrine metadata.
Use the Doctrine handler for mapped root entities. For other root objects, use the default handler or supply a custom one.

## Naming

The default property namer uses the source property name. A custom `PropertyNamerInterface` supplied through
`setPropertyNamer()` sees the value after any metadata override or matching transformer. The emitted name is
used to match the destination property, constructor parameter, or setter, so verify that renamed fields reload.

The default `ClassNamer` uses the short class name and a one-based per-class index, such as `Task-1`.
Repeated visits to an object reuse its name during a run. Colliding names receive a numeric suffix, and names
reserved for omitted fixtures are not reused in that run.

```php
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\ReferenceNamer\CallbackNamer;

$namer = new CallbackNamer(
    static fn (object $object, int $index): string =>
        strtolower((new \ReflectionClass($object))->getShortName()) . '-' . $index
);

$generator = FixtureGeneratorBuilder::create()->setReferenceNamer($namer)->build();
```

Return a fixture key such as `task-1`. AliceGenerator adds the `@` when emitting a reference to it.
Both `ClassNamer` and `CallbackNamer` accept a `referenceOffset` added to the per-class index.

## How fixtures are written

| When the value can be restored through | Generated fixture |
| --- | --- |
| Direct assignment | A normal Alice field. |
| A matching constructor parameter | An argument in `__construct`. A writable field or initialized default can also supply a required argument. |
| An explicit public instance setter | A setter call in `__calls`. |
| A supported collection adder | One adder call per item in `__calls`. |
| A Doctrine child's owning to-one field | The relationship is written on the child. The parent's inverse collection can still be used to discover children. |
| No supported route | The property is omitted by default. |

Collection adder calls require a public instance adder and remover with the same object parameter type.
For `items`, use `addItems` and `removeItems`. The adder appends to the collection initialized by the constructor.

Set `setSkipNonWritableProperties(false)` on the context to emit raw fields where possible. Reload those
fixtures with your Alice configuration to check that it can write them.

For a class whose required constructor arguments are unavailable, call
`$context->allowConstructorBypassFor(SetterBackedRecord::class)` when its setters can restore the needed state.
If generation uses that fallback, it emits `__construct: false` so Alice allocates the object before calling its setters.

## Output format

The default is YAML. Use `setGenerator()` to change it:

- `new YamlGenerator(inline: 3, indent: 4)` matches the default YAML formatting.
- `new PhpGenerator()` returns PHP source that returns the fixture definitions as an array when included.
- Implement `GeneratorInterface` to serialize the fixture definitions to another string format.

## Custom metadata

A custom `MetadataHandlerInterface` can decide which objects and properties to export. It can also implement
`MetadataExportPolicyInterface` to allow a nested object to become literal text and to identify the owning
field of an inverse collection. Text conversion still depends on the [destination write route](custom-object-handlers.md).
