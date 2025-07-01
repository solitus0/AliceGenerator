<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

use Solitus0\AliceGenerator\ValueContext;

interface ValueTransformerInterface
{
    public function transform(ValueContext $valueContext): void;
}
