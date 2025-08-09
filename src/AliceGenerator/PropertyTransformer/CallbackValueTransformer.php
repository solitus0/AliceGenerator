<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

use Solitus0\AliceGenerator\ValueContext;

class CallbackValueTransformer implements ValueTransformerInterface
{
    private $callback;

    /**
     * @param callable(ValueContext): mixed $callback A callback that receives the ValueContext and returns a transformed value
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function transform(ValueContext $valueContext): void
    {
        $newValue = call_user_func($this->callback, $valueContext);
        $valueContext->setValue($newValue);
    }
}
