<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Storage;

use Doctrine\Common\Collections\ArrayCollection;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;

/**
 * Base implementation for collections keyed by an object's class name.
 * Uses a metadata handler to determine the real class (e.g. proxy unwrapping).
 */
abstract class AbstractCollection
{
    private ?MetadataHandlerInterface $metadataHandler = null;

    private array $collections = [];

    /**
     * @param MetadataHandlerInterface $handler Used to resolve object class names
     */
    public function setMetadataHandler(MetadataHandlerInterface $handler): void
    {
        $this->metadataHandler = $handler;
    }

    /**
     * Returns the collection for the given object's class, creating it on demand.
     */
    public function getCollection(object $object): ArrayCollection
    {
        $class = $this->determineClass($object);
        if (!isset($this->collections[$class])) {
            $this->collections[$class] = $this->createCollection();
        }

        return $this->collections[$class];
    }

    /**
     * Determines the class key for grouping, via the metadata handler.
     */
    protected function determineClass(object $object): string
    {
        return $this->metadataHandler->getClass($object);
    }

    /**
     * Creates a fresh backing collection (default is ArrayCollection).
     */
    protected function createCollection(): ArrayCollection
    {
        return new ArrayCollection();
    }
}
