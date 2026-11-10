<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class RequiredNullableConstructorFixture
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

    public function unsetFirst(): void
    {
        unset($this->first);
    }
}
