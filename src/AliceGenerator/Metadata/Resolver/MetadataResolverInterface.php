<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata\Resolver;

use Solitus0\AliceGenerator\ValueContext;

interface MetadataResolverInterface
{
    public function resolve(ValueContext $valueContext);

    public function handle(ValueContext $valueContext);
}
