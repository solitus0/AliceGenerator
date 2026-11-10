<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class ConstructorDefaultsFixture
{
    private readonly string $first;

    private string $middle = 'fallback';

    private readonly string $last;

    public function __construct(
        string $first,
        string $middle = 'fallback',
        string $last = 'tail'
    ) {
        $this->first = $first;
        $this->middle = $middle;
        $this->last = $last;
    }

    public function debugState(): array
    {
        return [$this->first, $this->middle, $this->last];
    }
}
