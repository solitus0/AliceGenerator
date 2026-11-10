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
        return !$this->modified && is_string($this->value) ? $this->escapeLiteralString($this->value) : $this->value;
    }

    public function setLiteralString(string $value): void
    {
        $this->setValue($this->escapeLiteralString($value));
    }

    public function allowsStringable(): bool
    {
        return $this->allowsStringable;
    }

    private function escapeLiteralString(string $value): string
    {
        $containsMarkup = $value !== strip_tags($value);

        // Alice consumes escape markers and splits function arguments at literal newlines.
        if (str_contains($value, '\@') || str_contains($value, '\$') || ($containsMarkup && strpbrk($value, "\r\n") !== false)) {
            return "<base64_decode('" . base64_encode($value) . "')>";
        }

        if ($containsMarkup) {
            $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return "<htmlspecialchars_decode('$encoded', 3)>";
        }

        // Escape literal markers Alice could interpret as fixture or variable references.
        return str_replace(['@', '$'], ['\@', '\$'], $value);
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
