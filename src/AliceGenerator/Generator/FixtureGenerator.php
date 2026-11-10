<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\ValueVisitor;

class FixtureGenerator
{
    /** @var \Closure(mixed, FixtureGenerationContext): array */
    private readonly \Closure $collectFixtures;

    /** @param ValueVisitor|(\Closure(mixed, FixtureGenerationContext): array) $valueVisitor */
    public function __construct(
        ValueVisitor|\Closure $valueVisitor,
        private readonly GeneratorInterface $generator
    ) {
        $this->collectFixtures = $valueVisitor instanceof ValueVisitor
            ? $valueVisitor->collectFixtures(...)
            : $valueVisitor;
    }

    public function generate($value, FixtureGenerationContext $generationContext): string
    {
        $results = ($this->collectFixtures)($value, $generationContext);

        ksort($results, SORT_NATURAL);
        foreach ($results as &$result) {
            ksort($result, SORT_NATURAL);
        }

        return $this->generator->generate($results);
    }
}
