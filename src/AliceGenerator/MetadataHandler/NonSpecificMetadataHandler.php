<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

use Solitus0\AliceGenerator\ValueContext;

/**
 * Default handler for cases without specific metadata; handles all objects by including everything.
 */
class NonSpecificMetadataHandler extends AbstractMetadataHandler
{
    public function canHandle(object $object): bool
    {
        return true;
    }

    public function preHandle(object $object): void
    {
    }

    public function shouldSkipProperty(ValueContext $valueContext): bool
    {
        return false;
    }
}
