<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

final class PhpGenerator implements GeneratorInterface
{
    public function generate(array $data): string
    {
        $exported = var_export($data, true);

        return "<?php" . "\n\nreturn " . $exported . ";";
    }
}
