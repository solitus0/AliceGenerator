<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\AliceExpression;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;
use Solitus0\AliceGenerator\Storage\ObjectConstraintsCollection;

/**
 * @internal
 * @phpstan-type Fixture array{class: string, writes: FinalizedFixtureWrites}
 */
final class FixtureGraph
{
    private readonly ObjectConstraintsCollection $constraints;

    /** @var \SplObjectStorage<object, string|null> */
    private readonly \SplObjectStorage $references;

    /** @var array<string, int> */
    private array $indicesByClass = [];

    /** @var array<array-key, int> Last suffix used for each reserved name. */
    private array $reservedNames = [];

    /** @var array<array-key, Fixture> */
    private array $fixtures = [];

    /** @var list<string> */
    private array $discardedReferences = [];

    /** @var array<array-key, true> */
    private array $inverseOnlyReferences = [];

    /** @var array<array-key, list<array{child: string, owningField: string}>> */
    private array $inverseAssociations = [];

    private int $inverseDiscoveryDepth = 0;

    private int $depth = 0;

    public function __construct(
        public readonly FixtureGenerationContext $context,
        private readonly MetadataHandlerInterface $metadataHandler,
        private readonly ReferenceNamerInterface $referenceNamer,
    ) {
        $this->constraints = $context->getConstraintsCollection()->forMetadataHandler($metadataHandler);
        $this->references = new \SplObjectStorage();
    }

    /** @param callable(object, string): (FinalizedFixtureWrites|null) $buildDefinition */
    public function referenceFor(object $object, callable $buildDefinition): ?string
    {
        if (!$this->constraints->isAllowed($object)) {
            return null;
        }

        if ($this->references->offsetExists($object)) {
            $reference = $this->references[$object];
            if ($reference !== null && $this->inverseDiscoveryDepth === 0) {
                unset($this->inverseOnlyReferences[$reference]);
            }

            return $reference;
        }

        if ($this->depth > $this->context->getMaximumRecursion()) {
            return null;
        }

        $class = $this->metadataHandler->getClass($object);
        $index = ($this->indicesByClass[$class] ?? 0) + 1;
        $this->indicesByClass[$class] = $index;
        $reference = $this->reserveName($this->referenceNamer->createReference($object, $index));
        // Cycles can use the reserved name while the definition is being built.
        $this->references[$object] = $reference;

        $writes = null;
        $this->depth++;

        try {
            $writes = $buildDefinition($object, $class);
        } finally {
            $this->depth--;
            if ($writes === null) {
                $this->references[$object] = null;
                $this->discardedReferences[] = $reference;
            }
        }

        if ($writes === null) {
            return null;
        }

        $this->fixtures[$reference] = ['class' => $class, 'writes' => $writes];
        if ($this->inverseDiscoveryDepth > 0) {
            $this->inverseOnlyReferences[$reference] = true;
        }

        return $reference;
    }

    /** @param \Closure(): mixed $visit */
    public function discoverInverseCollection(object $owner, string $owningField, \Closure $visit): void
    {
        $ownerReference = $this->references[$owner];
        $this->inverseDiscoveryDepth++;

        try {
            $items = $visit();
        } finally {
            $this->inverseDiscoveryDepth--;
        }

        if ($ownerReference === null || !is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item instanceof AliceExpression && str_starts_with($item->syntax, '@')) {
                $this->inverseAssociations[$ownerReference][] = [
                    'child' => substr($item->syntax, 1),
                    'owningField' => $owningField,
                ];
            }
        }
    }

    /** @return array<string, array<array-key, array<string, mixed>>> */
    public function getDefinitions(): array
    {
        $fixtures = $this->discardedReferences === [] ? $this->fixtures : $this->pruneDiscardedReferences();
        if ($this->inverseOnlyReferences !== []) {
            $fixtures = $this->retainConnectedFixtures($fixtures);
        }

        $definitions = [];
        foreach ($fixtures as $reference => $fixture) {
            $definitions[$fixture['class']][$reference] = AliceValueEncoder::encodeDefinition(
                $fixture['writes']->definition,
            );
        }

        return $definitions;
    }

    private function reserveName(string $preferred): string
    {
        $name = $preferred;
        while (isset($this->reservedNames[$name])) {
            $name = $preferred . '-' . ++$this->reservedNames[$preferred];
        }
        $this->reservedNames[$name] = 1;

        return $name;
    }

    /**
     * @param array<array-key, Fixture> $fixtures
     * @return array<array-key, Fixture>
     */
    private function retainConnectedFixtures(array $fixtures): array
    {
        // Fixtures reached without inverse discovery keep their normal export behavior.
        $pending = array_keys(array_diff_key($fixtures, $this->inverseOnlyReferences));
        $retained = [];
        while (($reference = array_pop($pending)) !== null) {
            if (isset($retained[$reference]) || !isset($fixtures[$reference])) {
                continue;
            }

            $retained[$reference] = true;
            foreach ($this->referencedFixtures($fixtures[$reference]['writes']->definition) as $target) {
                $pending[] = $target;
            }

            foreach ($this->inverseAssociations[$reference] ?? [] as ['child' => $child, 'owningField' => $owningField]) {
                if (($fixtures[$child]['writes'] ?? null)?->writesOwningReference($owningField, $reference)) {
                    $pending[] = $child;
                }
            }
        }

        return array_intersect_key($fixtures, $retained);
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    private function referencedFixtures(array $definition): array
    {
        $references = [];
        array_walk_recursive($definition, static function (mixed $value) use (&$references): void {
            // Recognize direct references and property reads supplied by extensions.
            if ($value instanceof AliceExpression
                && preg_match('/\A@([^\s<>{}*$]+)(?:->[^\s<>{}()]+)?\z/', $value->syntax, $matches) === 1
            ) {
                $references[] = $matches[1];
            }
        });

        return $references;
    }

    /** @return array<array-key, Fixture> */
    private function pruneDiscardedReferences(): array
    {
        $fixtures = $this->fixtures;
        $dependents = [];
        foreach ($fixtures as $reference => $fixture) {
            foreach ($this->referencedFixtures($fixture['writes']->definition) as $target) {
                $dependents[$target][$reference] = true;
            }
        }

        $discarded = $this->discardedReferences;
        while (($reference = array_pop($discarded)) !== null) {
            foreach (array_keys($dependents[$reference] ?? []) as $dependent) {
                if (isset($fixtures[$dependent])) {
                    unset($fixtures[$dependent]);
                    $discarded[] = $dependent;
                }
            }
        }

        return $fixtures;
    }
}
