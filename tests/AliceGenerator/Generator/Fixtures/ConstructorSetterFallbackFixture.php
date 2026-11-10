<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class ConstructorSetterFallbackFixture
{
    private string $status;

    public function __construct(
        string $fallback,
        ?string $status = null,
    ) {
        $this->status = $status ?? $fallback;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
