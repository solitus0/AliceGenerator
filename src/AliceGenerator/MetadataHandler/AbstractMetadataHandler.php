<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

use Doctrine\Common\Util\ClassUtils;

abstract class AbstractMetadataHandler implements MetadataHandlerInterface
{
    public function getClass(object $object): string
    {
        return ClassUtils::getRealClass($object::class);
    }
}
