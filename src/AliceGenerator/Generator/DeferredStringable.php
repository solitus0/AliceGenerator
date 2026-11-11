<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\AliceExpression;

/** @internal */
final class DeferredStringable
{
    /** @param \Closure(): (AliceExpression|null) $reference */
    public function __construct(
        public readonly \Stringable $source,
        private readonly StringableWriteMode $mode,
        private readonly \Closure $reference,
    ) {
    }

    public function resolve(bool $useConstructor, bool $resolveText): string|AliceExpression|self|null
    {
        $textMode = $useConstructor ? StringableWriteMode::ConstructorText : StringableWriteMode::FallbackText;
        if ($this->mode === $textMode) {
            return $resolveText ? (string) $this->source : $this;
        }

        return ($this->reference)();
    }
}
