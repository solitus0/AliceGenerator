<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

use Solitus0\AliceGenerator\ValueContext;

interface MetadataHandlerInterface
{
    /**
     * Return the real class name of the object (e.g. unwrap proxies).
     */
    public function getClass(object $object): string;

    /**
     * Determines if this handler should manage the given object.
     */
    public function canHandle(object $object): bool;

    /**
     * Preprocess the object before fixture extraction (e.g. load proxies).
     */
    public function preHandle(object $object): void;

    /**
     * Indicates whether a property should be skipped based on metadata.
     */
    public function shouldSkipProperty(ValueContext $valueContext): bool;
}
