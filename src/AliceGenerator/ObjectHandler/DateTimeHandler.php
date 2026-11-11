<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\Generator\AliceValueEncoder;
use Solitus0\AliceGenerator\ValueContext;

class DateTimeHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $valueContext): bool
    {
        if (!($datetime = $valueContext->getValue()) instanceof \DateTime) {
            return false;
        }

        $valueContext->setSourceValue(AliceValueEncoder::dateExpression($datetime));

        return true;
    }
}
