<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

use Solitus0\AliceGenerator\ValueContext;

class SimpleValueTransformer implements ValueTransformerInterface
{
    public function __construct(private $newValue)
    {
    }

    public function transform(ValueContext $valueContext): void
    {
        $valueContext->setValue($this->newValue);
    }
}
