<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata\Provider;

use Metadata\MetadataFactoryInterface;
use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class ClassMetadataProvider implements ClassMetadataProviderInterface
{
    public function __construct(private readonly MetadataFactoryInterface $metadataFactory)
    {
    }

    /**
     * @return PropertyMetadata[]
     */
    public function getPropertyMetadata(\ReflectionClass $reflectionClass): array
    {
        $classMetadata = $this->metadataFactory->getMetadataForClass($reflectionClass->name);

        if ($classMetadata === null) {
            return [];
        }

        /** @phpstan-ignore-next-line PropertyMetadata[] is ensured by tailored JMS driver */
        return $classMetadata->propertyMetadata;
    }
}
