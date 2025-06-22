<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\ValueContext;

class DateTimeImmutableHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $valueContext): bool
    {
        $value = $valueContext->getValue();

        if (!$value instanceof \DateTimeImmutable) {
            return false;
        }

        $formatted = $value->format('Y-m-d H:i:s');

        if (str_contains($formatted, ' 00:00:00')) {
            $valueContext->setValue(
                sprintf(
                    '<(new \DateTimeImmutable("%s"))>',
                    str_replace(' 00:00:00', '', $formatted)
                )
            );
        } else {
            $valueContext->setValue(
                sprintf(
                    '<(new \DateTimeImmutable("%s", new \DateTimeZone("%s")))>',
                    $formatted,
                    $value->getTimezone()->getName()
                )
            );
        }

        return true;
    }
}
