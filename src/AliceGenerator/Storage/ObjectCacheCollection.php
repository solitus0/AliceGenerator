<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Storage;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Caches objects per class, tracking added and skipped items.
 */
class ObjectCacheCollection extends AbstractCollection
{
    public const OBJECT_SKIPPED = 'CACHE_STORE_OBJECT_SKIPPED';

    public const OBJECT_NOT_FOUND = 'CACHE_STORE_OBJECT_NOT_FOUND';

    /**
     * Marks this object as skipped in its class collection.
     */
    public function skip(object $object): void
    {
        $this->getValidCollection($object)->removeElement($object);
        $this->getSkippedCollection($object)->add($object);
    }

    private function getValidCollection(object $object): ArrayCollection
    {
        return $this->getCollection($object)->get('valid');
    }

    /**
     * Adds the object to the valid collection for its class and returns its index (1-based).
     */
    public function add(object $object): int
    {
        $collection = $this->getValidCollection($object);
        $collection->add($object);

        return $collection->count();
    }

    private function getSkippedCollection(object $object): ArrayCollection
    {
        return $this->getCollection($object)->get('skipped');
    }

    /**
     * Returns the key for this object if present, or a special constant if skipped/not found.
     */
    public function find(object $object): int|float|string
    {
        $key = $this->getValidCollection($object)->indexOf($object);
        if ($key !== false) {
            return $key + 1;
        }

        if ($this->getSkippedCollection($object)->contains($object)) {
            return self::OBJECT_SKIPPED;
        }

        return self::OBJECT_NOT_FOUND;
    }

    protected function createCollection(): ArrayCollection
    {
        $byClass = new ArrayCollection();
        $byClass->set('valid', new ArrayCollection());
        $byClass->set('skipped', new ArrayCollection());

        return $byClass;
    }
}
