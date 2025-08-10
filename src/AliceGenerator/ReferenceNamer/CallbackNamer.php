<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ReferenceNamer;

final class CallbackNamer implements ReferenceNamerInterface
{
    private readonly \Closure $callback;

    /**
     * @param \Closure $callback A callback that takes (object $object, int $key): string
     * @param int $referenceOffset An optional offset added to the key when generating references
     */
    public function __construct(
        \Closure $callback,
        private readonly int $referenceOffset = 0,
    ) {
        $this->callback = $callback;
    }

    public function createReference(object $object, int $key): string
    {
        $adjustedKey = $key + $this->referenceOffset;

        return ($this->callback)($object, $adjustedKey);
    }
}
