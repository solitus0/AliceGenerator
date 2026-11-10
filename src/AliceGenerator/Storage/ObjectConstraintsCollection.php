<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Storage;

use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;

/**
 * Tracks which objects of each class are allowed (constraints).
 */
class ObjectConstraintsCollection extends AbstractCollection
{
    /** @internal */
    public function forMetadataHandler(MetadataHandlerInterface $handler): self
    {
        $constraints = new self($handler);
        foreach ($this->getClassCollections() as $collection) {
            foreach ($collection as $object) {
                $constraints->addConstraint($object);
            }
        }

        return $constraints;
    }

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
