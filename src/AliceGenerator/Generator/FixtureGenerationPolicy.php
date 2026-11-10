<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\Export\ExportFailure;
use Solitus0\AliceGenerator\Metadata\PropertyMetadata;
use Solitus0\AliceGenerator\ValueContext;
use Solitus0\AliceGenerator\ValueVisitor;

/**
 * @internal
 * @phpstan-type SelectedFixture array{object: object, class: class-string, values: array<string, mixed>, links: array<string, string>}
 */
final class FixtureGenerationPolicy
{
    /**
     * @param array<string, SelectedFixture> $records
     * @param \WeakMap<object, string>|null $aliases
     */
    public function __construct(
        private readonly array $records = [],
        private readonly ?\WeakMap $aliases = null,
    ) {
    }

    public function aliasFor(object $object): ?string
    {
        if ($this->aliases === null) {
            return null;
        }

        return $this->aliases[$object] ?? throw new ExportFailure('forbidden_reference');
    }

    /** @return array<string, mixed>|null */
    public function selectedValuesFor(object $object): ?array
    {
        $alias = $this->aliasFor($object);

        return $alias === null ? null : $this->records[$alias]['values'];
    }

    /** @param array<string, mixed> $values */
    public function prepareSelectedProperty(
        array $values,
        PropertyMetadata $metadata,
        object $object,
        string $class,
        ValueVisitor $visitor,
    ): ?PreparedProperty {
        $name = $metadata->name;
        if (!array_key_exists($name, $values)) {
            return null;
        }

        $value = $values[$name];

        return new PreparedProperty(
            new \ReflectionProperty($metadata->class, $name),
            $name,
            new ValueContext($value, $class, $object, $metadata, $visitor),
            constructorOnly: false,
            needsTraversal: true,
            sourceIsGraphValue: is_object($value) || is_array($value),
            preserveEmptyArrays: true,
        );
    }

    public function missingRequiredConstructor(): void
    {
        if ($this->aliases !== null) {
            throw new ExportFailure('required_constructor_argument_missing');
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, array<array-key, true>> $owningReferences
     */
    public function finalizeWrites(array $definition, array $owningReferences): ?FinalizedFixtureWrites
    {
        return $definition === [] && $this->aliases === null
            ? null
            : new FinalizedFixtureWrites($definition, $owningReferences);
    }

    /** @param array<array-key, array{writes: FinalizedFixtureWrites}> $fixtures */
    public function checkWrites(array $fixtures): void
    {
        foreach ($this->records as $alias => $record) {
            $writes = $fixtures[$alias]['writes'] ?? throw new ExportFailure('fixture_set_mismatch');
            foreach ($record['links'] as $field => $target) {
                if (!$writes->writesOwningReference($field, $target)) {
                    throw new ExportFailure('required_link_mismatch');
                }
            }
        }
    }
}
