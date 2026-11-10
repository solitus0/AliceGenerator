<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Solitus0\AliceGenerator\Generator\AliceValueEncoder;
use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class ValueContext
{
    private bool $modified = false;

    private bool $skipped = false;

    /** @var (\Closure(bool): bool)|null */
    private ?\Closure $stringableWriteCheck = null;

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

    /**
     * @internal
     * @param iterable<mixed> $collection
     */
    public function visitCollection(iterable $collection): bool
    {
        $visitor = $this->getValueVisitor();
        if ($visitor === null) {
            return false;
        }

        $visitor->visitCollection($this, $collection);

        return true;
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

    public function setLiteralString(string $value): void
    {
        $this->setSourceValue($value);
    }

    public function allowsStringable(): bool
    {
        return $this->allowsStringable || ($this->stringableWriteCheck !== null && ($this->stringableWriteCheck)(false));
    }

    /** @internal */
    public function allowsStringableArrayItems(): bool
    {
        return $this->allowsStringable || ($this->stringableWriteCheck !== null && ($this->stringableWriteCheck)(true));
    }

    /**
     * @internal
     * @param \Closure(bool): bool $check
     */
    public function setStringableWriteCheck(\Closure $check): void
    {
        $this->stringableWriteCheck = $check;
    }

    /** Store a source value for encoding when the fixture is written. */
    public function setSourceValue(mixed $value): static
    {
        $this->value = $value;
        $this->modified = true;

        return $this;
    }

    /** Store a fixture value that may already contain Alice syntax. */
    public function setValue(mixed $value, bool $setModified = true): static
    {
        if ($setModified) {
            $this->modified = true;
        }
        $this->value = $this->modified ? AliceValueEncoder::markSyntax($value) : $value;

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
