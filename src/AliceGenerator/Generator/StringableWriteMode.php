<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

/** @internal */
enum StringableWriteMode
{
    case Reference;
    case Text;
    case ConstructorText;
    case FallbackText;

    public static function forRoutes(bool $constructor, bool $fallback): self
    {
        if ($constructor === $fallback) {
            return $constructor ? self::Text : self::Reference;
        }

        return $constructor ? self::ConstructorText : self::FallbackText;
    }

    public function dependsOnConstructor(): bool
    {
        return $this === self::ConstructorText || $this === self::FallbackText;
    }
}
