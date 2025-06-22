<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata\Resolver;

use Solitus0\AliceGenerator\ValueContext;

class MetadataResolver extends AbstractMetadataResolver
{
    public function handle(ValueContext $valueContext): void
    {
        if ($valueContext->getMetadata()->ignore) {
            $valueContext->setSkipped(true);
        } elseif ($valueContext->getMetadata()->staticData !== null) {
            $valueContext->setValue($valueContext->getMetadata()->staticData);
        }
    }
}
