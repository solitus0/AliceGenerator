<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;

/** @internal */
final class FixtureGraph
{
    /** @var \SplObjectStorage<object, string|null> */
    private readonly \SplObjectStorage $references;

    /** @var array<string, int> */
    private array $indicesByClass = [];

    /** @var array<string, array<array-key, array<string, mixed>>> */
    private array $definitions = [];

    /** @var list<string> */
    private array $discardedReferences = [];

    private int $depth = 0;

    public function __construct(
        public readonly FixtureGenerationContext $context,
        private readonly MetadataHandlerInterface $metadataHandler,
        private readonly ReferenceNamerInterface $referenceNamer,
    ) {
        $this->references = new \SplObjectStorage();
    }

    /** @param callable(object, string): array<string, mixed> $buildDefinition */
    public function referenceFor(object $object, callable $buildDefinition): ?string
    {
        if (!$this->context->getConstraintsCollection()->isAllowed($object)) {
            return null;
        }

        if ($this->references->offsetExists($object)) {
            return $this->references[$object];
        }

        if ($this->depth > $this->context->getMaximumRecursion()) {
            return null;
        }

        $class = $this->metadataHandler->getClass($object);
        $index = ($this->indicesByClass[$class] ?? 0) + 1;
        $this->indicesByClass[$class] = $index;
        $reference = $this->referenceNamer->createReference($object, $index);
        // Cycles can use the reserved name while the definition is being built.
        $this->references[$object] = $reference;

        $definition = [];
        $this->depth++;

        try {
            $definition = AliceValueEncoder::encodeDefinition($buildDefinition($object, $class));
        } finally {
            $this->depth--;
            if ($definition === []) {
                $this->references[$object] = null;
                $this->discardedReferences[] = $reference;
            }
        }

        if ($definition === []) {
            return null;
        }

        $this->definitions[$class][$reference] = $definition;

        return $reference;
    }

    /** @return array<string, array<array-key, array<string, mixed>>> */
    public function getDefinitions(): array
    {
        if ($this->discardedReferences === []) {
            return $this->definitions;
        }

        $definitions = $this->definitions;
        $dependents = [];
        foreach ($definitions as $class => $fixtures) {
            foreach ($fixtures as $reference => $definition) {
                // Inspect emitted values so references supplied by extensions also count.
                array_walk_recursive($definition, static function (mixed $value) use (&$dependents, $class, $reference): void {
                    if (is_string($value) && str_starts_with($value, '@')) {
                        $dependents[$value][$class][$reference] = true;
                    }
                });
            }
        }

        $discarded = $this->discardedReferences;
        while (($reference = array_pop($discarded)) !== null) {
            foreach ($dependents['@' . $reference] ?? [] as $class => $fixtures) {
                foreach (array_keys($fixtures) as $dependent) {
                    if (isset($definitions[$class][$dependent])) {
                        unset($definitions[$class][$dependent]);
                        $discarded[] = $dependent;
                    }
                }
            }
        }

        return array_filter($definitions);
    }
}
