<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests;

final class InvalidConstructorRoutingFixture
{
    private ?string $first = null;

    private readonly string $second;

    public function __construct(
        ?string $first,
        string $second
    ) {
        $this->first = $first;
        $this->second = $second;
    }

    public function debugState(): array
    {
        return [$this->first, $this->second];
    }
}
