<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

/** A value written to the fixture as Alice syntax. */
final class AliceExpression
{
    public function __construct(
        public readonly string $syntax
    ) {
    }

    public static function reference(string $name): self
    {
        return new self('@' . $name);
    }

    /** @internal */
    public static function php(string $expression): self
    {
        return new self('<(' . $expression . ')>');
    }
}
