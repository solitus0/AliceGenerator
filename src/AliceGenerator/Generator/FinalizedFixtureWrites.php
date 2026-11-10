<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

/** @internal */
final class FinalizedFixtureWrites
{
    /**
     * @param array<string, mixed> $definition
     * @param array<string, array<array-key, true>> $owningReferences
     */
    public function __construct(
        public readonly array $definition,
        private readonly array $owningReferences,
    ) {
    }

    public function writesOwningReference(string $field, string|int $reference): bool
    {
        return isset($this->owningReferences[$field][$reference]);
    }
}
