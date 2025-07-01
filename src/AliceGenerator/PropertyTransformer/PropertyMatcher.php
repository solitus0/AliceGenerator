<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class PropertyMatcher implements PropertyMatcherInterface
{
    public function __construct(private string $property)
    {
    }

    public function matches(PropertyMetadata $metadata): bool
    {
        return $metadata->name === $this->property;
    }
}
