<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata\Provider;

use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

/** @internal */
final class ReflectionClassMetadataProvider implements ClassMetadataProviderInterface
{
    /** @var array<class-string, array<string, class-string>> */
    private array $propertiesByClass = [];

    /** @return array<string, PropertyMetadata> */
    public function getPropertyMetadata(\ReflectionClass $reflectionClass): array
    {
        $metadata = [];
        foreach ($this->getPropertyClasses($reflectionClass) as $name => $class) {
            $metadata[$name] = new PropertyMetadata($class, $name);
        }

        return $metadata;
    }

    /** @return array<string, class-string> */
    private function getPropertyClasses(\ReflectionClass $reflectionClass): array
    {
        $class = $reflectionClass->getName();
        if (isset($this->propertiesByClass[$class])) {
            return $this->propertiesByClass[$class];
        }

        $parent = $reflectionClass->getParentClass();
        $properties = $parent === false ? [] : $this->getPropertyClasses($parent);

        // Overwriting a name preserves its position from the parent and matches it on the current class.
        foreach ($reflectionClass->getProperties() as $property) {
            $properties[$property->getName()] = $class;
        }

        return $this->propertiesByClass[$class] = $properties;
    }
}
