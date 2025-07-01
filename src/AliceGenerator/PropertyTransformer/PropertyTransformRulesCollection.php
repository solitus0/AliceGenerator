<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\PropertyTransformer;

use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class PropertyTransformRulesCollection
{
    private array $rules = [];

    public function add(PropertyTransformRule $rule): self
    {
        $this->rules[] = $rule;

        return $this;
    }

    public function getPropertyTransformer(PropertyMetadata $metadata): ?ValueTransformerInterface
    {
        /** @var PropertyTransformRule $rule */
        foreach ($this->rules as $rule) {
            $ruleMatcher = $rule->getMatcher();
            if ($ruleMatcher->matches($metadata)) {
                return $rule->getTransformer();
            }
        }

        return null;
    }
}
