<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyNamer;

use Solitus0\AliceGenerator\ValueContext;

interface PropertyNamerInterface
{
    /**
     * @return string
     */
    public function createName(ValueContext $valueContext);
}
