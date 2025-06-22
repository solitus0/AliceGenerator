<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Solitus0\AliceGenerator\ValueContext;

class RamseyUuidHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $valueContext): bool
    {
        $value = $valueContext->getValue();
        if (!class_exists(LazyUuidFromString::class) || !$value instanceof LazyUuidFromString) {
            return false;
        }

        $valueContext->setValue($value->__toString());

        return true;
    }
}
