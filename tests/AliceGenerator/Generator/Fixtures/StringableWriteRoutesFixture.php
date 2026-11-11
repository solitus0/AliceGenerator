<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class StringableWriteRoutesFixture
{
    public mixed $requiredObject;

    public ?StringableText $typedProperty = null;

    private StringableText|string|null $setterValue = null;

    /** @var list<StringableText> */
    private array $labels = [];

    public function __construct(
        private StringableText|string $constructorValue,
        StringableText $requiredObject,
    ) {
        $this->requiredObject = $requiredObject;
    }

    public function getConstructorValue(): StringableText|string
    {
        return $this->constructorValue;
    }

    public function setSetterValue(StringableText|string $setterValue): void
    {
        $this->setterValue = $setterValue;
    }

    public function getSetterValue(): StringableText|string|null
    {
        return $this->setterValue;
    }

    /** @return list<StringableText> */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function addLabel(StringableText $label): void
    {
        $this->labels[] = $label;
    }

    public function removeLabel(StringableText $label): void
    {
        $this->labels = array_values(array_filter($this->labels, static fn (StringableText $item): bool => $item !== $label));
    }
}
