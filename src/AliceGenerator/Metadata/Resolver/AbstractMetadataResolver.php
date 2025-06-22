<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata\Resolver;

use Solitus0\AliceGenerator\ValueContext;

abstract class AbstractMetadataResolver implements MetadataResolverInterface
{
    public function resolve(ValueContext $valueContext): void
    {
        $this->handle($valueContext);
    }
}
