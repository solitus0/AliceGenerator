<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ReferenceNamer;

use Doctrine\Common\Util\ClassUtils;

class NamespaceNamer implements ReferenceNamerInterface
{
    public function createReference(object $object, int $key): string
    {
        return $this->createPrefix($object) . $key;
    }

    public function createPrefix(object $object): string
    {
        $class = ClassUtils::getClass($object);
        $parts = explode('\\', $class);

        $namespaceParts = array_slice($parts, 0, -1);
        $className = end($parts);
        $namespace = implode('', $namespaceParts);

        return sprintf('%s%s-', $namespace, $className);
    }
}
