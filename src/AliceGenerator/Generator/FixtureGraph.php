<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\AliceExpression;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;
use Solitus0\AliceGenerator\ReferenceNamer\ReferenceNamerInterface;
use Solitus0\AliceGenerator\Storage\ObjectConstraintsCollection;

/** @internal */
final class FixtureGraph
{
    private readonly ObjectConstraintsCollection $constraints;

    /** @var \SplObjectStorage<object, string|null> */
    private readonly \SplObjectStorage $references;

    /** @var array<string, int> */
    private array $indicesByClass = [];

    /** @var array<array-key, int> Last suffix used for each reserved name. */
    private array $reservedNames = [];

    /** @var array<array-key, array{class: string, definition: array<string, mixed>}> */
    private array $fixtures = [];

    /** @var list<string> */
    private array $discardedReferences = [];

    private int $depth = 0;

    public function __construct(
        public readonly FixtureGenerationContext $context,
        private readonly MetadataHandlerInterface $metadataHandler,
        private readonly ReferenceNamerInterface $referenceNamer,
    ) {
        $this->constraints = $context->getConstraintsCollection()->forMetadataHandler($metadataHandler);
        $this->references = new \SplObjectStorage();
    }

    /** @param callable(object, string): array<string, mixed> $buildDefinition */
    public function referenceFor(object $object, callable $buildDefinition): ?string
    {
        if (!$this->constraints->isAllowed($object)) {
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
        $reference = $this->reserveName($this->referenceNamer->createReference($object, $index));
        // Cycles can use the reserved name while the definition is being built.
        $this->references[$object] = $reference;

        $definition = [];
        $this->depth++;

        try {
            $definition = $buildDefinition($object, $class);
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

        $this->fixtures[$reference] = ['class' => $class, 'definition' => $definition];

        return $reference;
    }

    /** @return array<string, array<array-key, array<string, mixed>>> */
    public function getDefinitions(): array
    {
        $fixtures = $this->discardedReferences === [] ? $this->fixtures : $this->pruneDiscardedReferences();
        $definitions = [];
        foreach ($fixtures as $reference => $fixture) {
            $definitions[$fixture['class']][$reference] = AliceValueEncoder::encodeDefinition($fixture['definition']);
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

    /** @return array<array-key, array{class: string, definition: array<string, mixed>}> */
    private function pruneDiscardedReferences(): array
    {
        $fixtures = $this->fixtures;
        $dependents = [];
        foreach ($fixtures as $reference => $fixture) {
            array_walk_recursive($fixture['definition'], static function (mixed $value) use (&$dependents, $reference): void {
                // Recognize direct references and property reads, including expressions supplied by extensions.
                if ($value instanceof AliceExpression
                    && preg_match('/\A@([^\s<>{}*$]+)(?:->[^\s<>{}()]+)?\z/', $value->syntax, $matches) === 1
                ) {
                    $dependents[$matches[1]][$reference] = true;
                }
            });
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
