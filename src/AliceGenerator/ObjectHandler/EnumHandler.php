<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\Generator\AliceValueEncoder;
use Solitus0\AliceGenerator\ValueContext;

class EnumHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $valueContext): bool
    {
        $value = $valueContext->getValue();

        if (!$value instanceof \UnitEnum) {
            return false;
        }

        $valueContext->setSourceValue(AliceValueEncoder::enumExpression($value));

        return true;
    }
}
