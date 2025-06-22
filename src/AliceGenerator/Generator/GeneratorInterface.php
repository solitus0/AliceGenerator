<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

interface GeneratorInterface
{
    public function generate(array $data): string;
}
