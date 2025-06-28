<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ReferenceNamer;

use Doctrine\Common\Util\ClassUtils;

class ClassNamer implements ReferenceNamerInterface
{
    public function __construct(private int $referenceOffset = 0)
    {
    }

    public function createReference(object $object, int $key): string
    {
        $adjustedKey = $key + $this->referenceOffset;

        return $this->createPrefix($object) . $adjustedKey;
    }

    private function createPrefix($object): string
    {
        $class = ClassUtils::getClass($object);

        $parts = explode('\\', $class);
        $className = end($parts);

        return $className . '-';
    }
}
