<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyNamer;

use Solitus0\AliceGenerator\ValueContext;

class PropertyNamer implements PropertyNamerInterface
{
    public function createName(ValueContext $valueContext): string
    {
        return $valueContext->getPropName();
    }
}
