<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class RequiredRelationFixture
{
    public function __construct(
        public readonly ValueFixture $relation,
        public string $name,
    ) {
    }
}
