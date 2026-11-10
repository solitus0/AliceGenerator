<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\ValueContext;

/** @internal */
final class PreparedProperty
{
    public function __construct(
        public readonly \ReflectionProperty $reflection,
        public readonly string $name,
        public readonly ValueContext $context,
        public readonly bool $constructorOnly,
        public readonly bool $needsTraversal,
        public readonly bool $sourceIsGraphValue,
    ) {
    }
}
