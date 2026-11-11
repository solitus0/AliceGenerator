<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class RenamedConstructorFixture
{
    private string $legacy = 'seed';

    public function __construct(string $needed)
    {
        $this->legacy = $needed;
    }

    public function getLegacy(): string
    {
        return $this->legacy;
    }
}
