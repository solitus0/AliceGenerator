<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Export;

final class ExportFailure extends \RuntimeException
{
    public function __construct(
        public readonly string $reason
    ) {
        parent::__construct($reason);
    }
}
