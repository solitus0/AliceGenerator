<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\Generator\AliceValueEncoder;
use Solitus0\AliceGenerator\ValueContext;

class DateTimeImmutableHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $valueContext): bool
    {
        $value = $valueContext->getValue();

        if (!$value instanceof \DateTimeImmutable) {
            return false;
        }

        $valueContext->setSourceValue(AliceValueEncoder::dateExpression($value));

        return true;
    }
}
