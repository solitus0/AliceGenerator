<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\ValueContext;

interface ObjectHandlerRegistryInterface
{
    /**
     * @param ObjectHandlerInterface[] $handlers
     */
    public function registerHandlers(array $handlers);

    /**
     * @return bool
     */
    public function runHandlers(ValueContext $valueContext);
}
