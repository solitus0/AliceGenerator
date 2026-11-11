<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class StringableText
{
    public function __construct(
        public string $text,
    ) {
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
