<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class ConstructorTextWithObjectSetterFixture
{
    private StringableText|string $text;

    public function __construct(
        string $text,
        public readonly ValueFixture $relation,
    ) {
        $this->text = $text;
    }

    public function setText(StringableText $text): void
    {
        $this->text = $text;
    }

    public function getText(): StringableText|string
    {
        return $this->text;
    }
}
