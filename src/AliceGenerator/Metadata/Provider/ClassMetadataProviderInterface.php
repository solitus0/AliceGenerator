<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata\Provider;

use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

interface ClassMetadataProviderInterface
{
    /**
     * @return PropertyMetadata[]
     */
    public function getPropertyMetadata(\ReflectionClass $reflectionClass): array;
}
