<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Storage;

use Doctrine\Common\Collections\ArrayCollection;
use Solitus0\AliceGenerator\MetadataHandler\MetadataHandlerInterface;

abstract class AbstractCollection
{
    private array $collections = [];

    public function __construct(private MetadataHandlerInterface $metadataHandler)
    {
    }

    public function setMetadataHandler(MetadataHandlerInterface $handler): self
    {
        $this->metadataHandler = $handler;

        return $this;
    }

    public function getCollection(object $object): ArrayCollection
    {
        $class = $this->determineClass($object);
        if (!isset($this->collections[$class])) {
            $this->collections[$class] = $this->createCollection();
        }

        return $this->collections[$class];
    }

    protected function determineClass(object $object): string
    {
        return $this->metadataHandler->getClass($object);
    }

    protected function createCollection(): ArrayCollection
    {
        return new ArrayCollection();
    }
}
