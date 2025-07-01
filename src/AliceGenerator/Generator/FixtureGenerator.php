<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\ValueVisitor;

class FixtureGenerator
{
    public function __construct(
        private readonly ValueVisitor $valueVisitor,
        private readonly GeneratorInterface $generator
    ) {
    }

    public function generate($value, FixtureGenerationContext $generationContext): string
    {
        $this->valueVisitor->setup($generationContext);
        $this->valueVisitor->visitSimpleValue($value);

        $results = $this->valueVisitor->getResults();

        ksort($results, SORT_NATURAL);
        foreach ($results as &$result) {
            ksort($result, SORT_NATURAL);
        }

        return $this->generator->generate($results);
    }
}
