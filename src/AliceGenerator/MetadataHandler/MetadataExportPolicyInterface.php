<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

/** Optional export decisions for metadata handlers. */
interface MetadataExportPolicyInterface extends MetadataHandlerInterface
{
    /** Choose which representations are eligible before checking the destination write route. */
    public function getObjectExportMode(object $object): ObjectExportMode;

    /** Return the child field that owns the relationship, or null to skip inverse discovery. */
    public function findOwningFieldForInverseCollection(object $object, string $property): ?string;
}
