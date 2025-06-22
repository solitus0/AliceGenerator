<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\ValueContext;

interface ObjectHandlerInterface
{
    /**
     * @return bool true if the handler changed the value, false otherwise
     */
    public function handle(ValueContext $valueContext);
}
