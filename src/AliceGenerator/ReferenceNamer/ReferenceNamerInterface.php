<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ReferenceNamer;

interface ReferenceNamerInterface
{
    public function createReference(object $object, int $key): string;
}
