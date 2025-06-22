<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Symfony\Component\Yaml\Yaml;

class YamlGenerator implements GeneratorInterface
{
    public function __construct(private readonly int $inline, private readonly int $indent)
    {
    }

    public function generate(array $data): string
    {
        return Yaml::dump($data, $this->inline, $this->indent);
    }
}
