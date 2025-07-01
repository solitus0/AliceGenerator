<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

class PropertyTransformRule
{
    public function __construct(
        private PropertyMatcherInterface $matcher,
        private ValueTransformerInterface $transformer
    ) {
    }

    public function getMatcher(): PropertyMatcherInterface
    {
        return $this->matcher;
    }

    public function getTransformer(): ValueTransformerInterface
    {
        return $this->transformer;
    }
}
