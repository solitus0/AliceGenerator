<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Solitus0\AliceGenerator\Generator\AliceValueEncoder;
use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class ValueContext
{
    private bool $modified = false;

    private bool $fixtureSyntax = false;

    private bool $skipped = false;

    public function __construct(
        private mixed $value = null,
        private readonly ?string $contextObjectClass = null,
        private readonly ?object $contextObject = null,
        private readonly ?PropertyMetadata $propertyMetadata = null,
        private readonly ?ValueVisitor $valueVisitor = null,
        private readonly bool $allowsStringable = false,
    ) {
    }

    public function getValueVisitor(): ?ValueVisitor
    {
        return $this->valueVisitor;
    }

    public function getValue(): mixed
    {
        return AliceValueEncoder::unwrapExpressions($this->value);
    }

    /** @internal */
    public function getUnencodedValue(): mixed
    {
        return $this->value;
    }

    /** @internal */
    public function getFixtureValue(): mixed
    {
        return $this->fixtureSyntax ? AliceValueEncoder::markSyntax($this->value) : $this->value;
    }

    public function setLiteralString(string $value): void
    {
        $this->setSourceValue($value);
    }

    public function allowsStringable(): bool
    {
        return $this->allowsStringable;
    }

    /** Store a source value for encoding when the fixture is written. */
    public function setSourceValue(mixed $value): static
    {
        $this->value = $value;
        $this->modified = true;
        $this->fixtureSyntax = false;

        return $this;
    }

    /** Store a fixture value that may already contain Alice syntax. */
    public function setValue(mixed $value, bool $setModified = true): static
    {
        $this->value = $value;
        if ($setModified) {
            $this->modified = true;
        }
        $this->fixtureSyntax = $this->modified;

        return $this;
    }

    public function getContextObject(): ?object
    {
        return $this->contextObject;
    }

    public function getContextObjectClass(): ?string
    {
        return $this->contextObjectClass;
    }

    public function getPropName(): string
    {
        return $this->getMetadata()->name;
    }

    public function getMetadata(): ?PropertyMetadata
    {
        return $this->propertyMetadata;
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    public function setSkipped(bool $skipped): void
    {
        $this->skipped = $skipped;
    }
}
