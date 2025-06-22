<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ReferenceNamer;

use Doctrine\Common\Util\ClassUtils;

class ClassNamer implements ReferenceNamerInterface
{
    public function createReference(object $object, int $key): string
    {
        return $this->createPrefix($object) . $key;
    }

    public function createPrefix($object): string
    {
        $class = ClassUtils::getClass($object);

        $parts = explode('\\', $class);
        $className = $parts[count($parts) - 1];

        return $className . '-';
    }
}
