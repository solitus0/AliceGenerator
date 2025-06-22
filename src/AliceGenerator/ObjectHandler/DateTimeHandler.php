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

        $formatted = $datetime->format('Y-m-d H:i:s');

        if (str_contains($formatted, ' 00:00:00')) {
            $valueContext->setValue(
                sprintf(
                    '<(new \DateTime("%s"))>',
                    str_replace(' 00:00:00', '', $datetime->format('Y-m-d H:i:s'))
                )
            );
        } else {
            $valueContext->setValue(
                sprintf(
                    '<(new \DateTime("%s", new \DateTimeZone("%s")))>',
                    $datetime->format('Y-m-d H:i:s'),
                    $datetime->getTimezone()->getName()
                )
            );
        }

        return true;
    }
}
