<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class ConstructorObjectWithTextSetterFixture
{
    private StringableText|string $text;

    public function __construct(
        StringableText $text,
        public readonly ValueFixture $relation,
    ) {
        $this->text = $text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getText(): StringableText|string
    {
        return $this->text;
    }
}
