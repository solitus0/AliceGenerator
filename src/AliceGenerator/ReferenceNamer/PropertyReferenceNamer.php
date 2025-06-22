<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ReferenceNamer;

use Doctrine\Common\Util\ClassUtils;

/**
 * Names by the value of a property from the generated object
 *
 * if class of object is not mapped to a property, the behaviour is similar to ClassNamer
 */
class PropertyReferenceNamer implements ReferenceNamerInterface
{
    /**
     * @param array $propertyNames should be an array following the shape of [$class => $propertyName]
     */
    public function __construct(protected array $propertyNames)
    {
    }

    public function createReference(object $object, int $key): string
    {
        $class = ClassUtils::getClass($object);

        $parts = explode('\\', $class);
        $className = $parts[count($parts) - 1];

        if (array_key_exists($class, $this->propertyNames)) {
            $propertyName = $this->propertyNames[$class];
            $reflectionProperty = new \ReflectionProperty($class, $propertyName);
            $reflectionProperty->setAccessible(true);
            $value = $reflectionProperty->getValue($object);

            return $className . '-' . $value;
        }

        return $className . '-' . $key;
    }
}
