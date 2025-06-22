<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata\Driver;

use Metadata\ClassMetadata;
use Metadata\Driver\DriverInterface;
use Metadata\MergeableClassMetadata;
use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class AttributeDriver implements DriverInterface
{
    public function loadMetadataForClass(\ReflectionClass $class): ?ClassMetadata
    {
        $mergeableClassMetadata = new MergeableClassMetadata($name = $class->name);
        $mergeableClassMetadata->fileResources[] = $class->getFileName();

        foreach ($class->getProperties() as $property) {
            $propertyMetadata = new PropertyMetadata($name, $property->getName());


            $mergeableClassMetadata->addPropertyMetadata($propertyMetadata);
        }

        return $mergeableClassMetadata;
    }
}
