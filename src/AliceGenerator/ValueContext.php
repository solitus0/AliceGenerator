<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator;

use Solitus0\AliceGenerator\Metadata\PropertyMetadata;

class ValueContext
{
    private bool $modified = false;

    private bool $skipped = false;

    public function __construct(
        private mixed $value = null,
        private readonly ?string $contextObjectClass = null,
        private readonly ?object $contextObject = null,
        private readonly ?PropertyMetadata $propertyMetadata = null,
        private readonly ?ValueVisitor $valueVisitor = null
    ) {
    }

    public function getValueVisitor(): ?ValueVisitor
    {
        return $this->valueVisitor;
    }

    public function getValue(): mixed
    {
        if (!$this->modified && is_string($this->value)) {
            $s = $this->value;

            // if string is raw html or xml prevent alice to interpret as evaluated expression
            if ($s !== strip_tags($s)) {
                // Step 1: escape HTML special chars (<, >, ", ')
                $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                // Step 2: revert inside Alice expression at runtime
                return "<htmlspecialchars_decode('$s', 3)>";
            }
        }

        return $this->value;
    }

    public function setValue(mixed $value, bool $setModified = true): static
    {
        $this->value = $value;
        if ($setModified) {
            $this->modified = true;
        }

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
