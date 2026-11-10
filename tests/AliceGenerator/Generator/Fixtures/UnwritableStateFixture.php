<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class UnwritableStateFixture
{
    public string $name = '';

    private mixed $state = null;

    public function replaceState(mixed $state): void
    {
        $this->state = $state;
    }

    public function getState(): mixed
    {
        return $this->state;
    }
}
