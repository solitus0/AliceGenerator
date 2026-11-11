<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class ConstructorOnlyFixture
{
    public function __construct(
        private readonly string $token
    ) {
    }

    public function debugState(): array
    {
        return [$this->token];
    }
}
