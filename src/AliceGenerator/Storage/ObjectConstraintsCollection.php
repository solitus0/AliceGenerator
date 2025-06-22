<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Storage;

/**
 * Tracks which objects of each class are allowed (constraints).
 */
class ObjectConstraintsCollection extends AbstractCollection
{
    public function addConstraint(object $object): void
    {
        $this->getCollection($object)->add($object);
    }

    /**
     * Returns true if the object is allowed (or if no constraints exist for its class).
     */
    public function isAllowed(object $object): bool
    {
        $collection = $this->getCollection($object);
        if ($collection->count() > 0) {
            return $collection->contains($object);
        }

        return true;
    }
}
