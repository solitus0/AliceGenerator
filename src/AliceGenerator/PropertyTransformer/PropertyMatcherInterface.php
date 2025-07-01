<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

interface PropertyMatcherInterface
{
    public function matches(PropertyMetadata $metadata): bool;
}
