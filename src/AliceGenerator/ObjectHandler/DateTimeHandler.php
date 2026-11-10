<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\ValueContext;

class DateTimeHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $valueContext): bool
    {
        if (!($datetime = $valueContext->getValue()) instanceof \DateTime) {
            return false;
        }

        $valueContext->setValue(sprintf(
            '<(new \DateTime("%s", new \DateTimeZone("%s")))>',
            $datetime->format('Y-m-d H:i:s.u'),
            $datetime->getTimezone()->getName(),
        ));

        return true;
    }
}
