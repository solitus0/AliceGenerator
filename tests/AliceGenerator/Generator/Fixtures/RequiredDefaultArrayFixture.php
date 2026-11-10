<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class RequiredDefaultArrayFixture
{
    /** @var list<string|array> */
    public array $items = ['@literal', []];

    /** @param list<string|array> $items */
    public function __construct(array $items)
    {
        $this->items = $items;
    }
}
