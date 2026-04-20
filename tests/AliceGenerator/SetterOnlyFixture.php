<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests;

final class SetterOnlyFixture
{
    private string $status;

    public function __construct(
        string $value
    ) {
        $this->status = $value;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function debugState(): array
    {
        return [$this->status];
    }
}
