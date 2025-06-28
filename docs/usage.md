# Usage

This guide demonstrates how to generate fixtures from your entities using AliceGenerator.

## Basic Usage

The following example generates fixtures for an entity, including collections except those owned by the `User` class:

```php
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\MetadataHandler\DoctrineMetadataHandler;

$generator = FixtureGeneratorBuilder::create()
    ->setMetadataHandler(new DoctrineMetadataHandler($entityManager))
    ->build();

$context = FixtureGenerationContext::create()
    ->setMaximumRecursion(max: 2)
    ->addPersistedObjectConstraint($entity);

$yaml = $generator->generate($entity, $context);
```

## Limiting Collections

Restrict the number of items in all collections of the `User` entity:

```php
$context = FixtureGenerationContext::create()
    ->setMaximumRecursion(max: 2)
    ->addPersistedObjectConstraint($entity)
    ->addCollectionItemLimitPerOwner(User::class, 3);
```

## Conditional Skipping

Skip individual items in a collection based on a custom callback:

```php
$context = FixtureGenerationContext::create()
    ->setMaximumRecursion(max: 2)
    ->addPersistedObjectConstraint($entity)
    ->addLimitForOwnerItemCollection(User::class, Notification::class, 5)
    ->addCollectionItemSkipCondition(
        function (string $ownerClass, $item) {
            return $item instanceof Notification && $item->getReadAt() !== null;
        }
    );
```

## Further Configuration

AliceGenerator provides many configuration options, including naming strategies, recursion control, and collection handling. For details, see the [Configuration Guide](configuration.md).

## Custom Object Handlers

To support custom types beyond the built-in handlers, see the [Custom Object Handlers](custom-object-handlers.md) guide.