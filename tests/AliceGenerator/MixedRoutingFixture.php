<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests;

final class MixedRoutingFixture
{
    public string $name;

    private readonly string $identifier;

    private string $status;

    public function __construct(
        string $identifier,
        string $name,
        string $value
    ) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->status = $value;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function debugState(): array
    {
        return [$this->identifier, $this->status];
    }
}
