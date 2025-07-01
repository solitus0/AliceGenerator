<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class ClassPropertyMatcher implements PropertyMatcherInterface
{
    public function __construct(private string $class, private string $property)
    {
    }

    public function matches(PropertyMetadata $metadata): bool
    {
        return $metadata->class === $this->class && $metadata->name === $this->property;
    }
}
