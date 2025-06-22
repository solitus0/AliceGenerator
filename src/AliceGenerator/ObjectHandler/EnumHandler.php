<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use BackedEnum;
use Solitus0\AliceGenerator\ValueContext;

class EnumHandler implements ObjectHandlerInterface
{
    public function handle(ValueContext $valueContext): bool
    {
        $value = $valueContext->getValue();

        if (!is_object($value)) {
            return false;
        }

        if (!enum_exists($class = $value::class)) {
            return false;
        }

        if (!$value instanceof \UnitEnum) {
            return false;
        }

        if ($value instanceof BackedEnum) {
            $enumValue = $value->value;

            if (is_string($enumValue)) {
                $formatted = sprintf('<(%s::from("%s"))>', $class, $enumValue);
            } else {
                $formatted = sprintf('<(%s::from(%s))>', $class, var_export($enumValue, true));
            }
        } else {
            $formatted = sprintf('<(%s::%s)>', $class, $value->name);
        }

        $valueContext->setValue($formatted);

        return true;
    }
}
