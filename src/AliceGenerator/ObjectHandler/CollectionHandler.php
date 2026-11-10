<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\ValueContext;

class CollectionHandler implements ObjectHandlerInterface
{
    /**
     * @return bool true if the handler changed the value, false otherwise
     */
    public function handle(ValueContext $valueContext): bool
    {
        if (!is_a($collection = $valueContext->getValue(), 'Doctrine\Common\Collections\Collection')) {
            return false;
        }

        return $valueContext->visitCollection($collection);
    }
}
