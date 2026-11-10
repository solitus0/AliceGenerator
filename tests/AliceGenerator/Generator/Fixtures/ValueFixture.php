<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class ValueFixture
{
    public function __construct(
        public mixed $value = null,
    ) {
    }
}
