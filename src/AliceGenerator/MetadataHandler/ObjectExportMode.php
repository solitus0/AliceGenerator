<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\MetadataHandler;

enum ObjectExportMode
{
    case FixtureOnly;
    case FixtureOrText;
    case TextOnly;

    /** Default policy for handlers that identify objects they can export as fixtures. */
    public static function fromMetadataSupport(bool $canHandle): self
    {
        return $canHandle ? self::FixtureOnly : self::TextOnly;
    }

    public function allowsFixture(): bool
    {
        return $this !== self::TextOnly;
    }

    public function allowsText(): bool
    {
        return $this !== self::FixtureOnly;
    }
}
