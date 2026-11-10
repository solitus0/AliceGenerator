<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Doctrine\Common\Collections\Collection;
use Solitus0\AliceGenerator\MetadataHandler\MetadataExportPolicyInterface;
use Solitus0\AliceGenerator\ValueContext;

/** @internal */
final class CollectionRouting
{
    /** @var \WeakMap<ValueContext, array{itemType: string, matched: bool}> */
    private readonly \WeakMap $pendingAdders;

    /**
     * @param \Closure(ValueContext): void $visitValue
     * @param \Closure(ValueContext): void $visitArray
     */
    public function __construct(
        private readonly FixtureGraph $graph,
        private readonly ?MetadataExportPolicyInterface $metadataPolicy,
        private readonly \Closure $visitValue,
        private readonly \Closure $visitArray,
    ) {
        $this->pendingAdders = new \WeakMap();
    }

    /** @param iterable<mixed> $collection */
    public function visitCollection(
        ValueContext $context,
        iterable $collection,
        FixtureGenerationContext $generationContext,
    ): void {
        $items = CollectionSelection::select($collection, $context->getContextObjectClass(), $generationContext);
        if ($items === null) {
            $context->setSkipped(true);

            return;
        }

        $context->setSourceValue($items);
        if (isset($this->pendingAdders[$context])) {
            $itemType = $this->pendingAdders[$context]['itemType'];
            foreach ($items as $item) {
                if (!$item instanceof $itemType) {
                    $this->pendingAdders[$context]['matched'] = false;
                    $this->discoverInverse($context);

                    return;
                }
            }
            $this->pendingAdders[$context]['matched'] = true;
        }

        ($this->visitArray)($context);
    }

    /** @return array{method: string, arguments: array<array-key, mixed>}|null */
    public function routeUnwritable(
        PreparedProperty $property,
        \ReflectionClass $class,
        FixtureWriteRouteResolver $routes,
    ): ?array {
        $context = $property->context;
        $adder = $context->getUnencodedValue() instanceof Collection
            ? $routes->findCollectionAdder($class, $property->name)
            : null;
        if ($adder === null) {
            $this->discoverInverse($context);

            return null;
        }

        // The handler registry runs first and can skip or replace the built-in collection visit.
        $this->pendingAdders[$context] = ['itemType' => $adder['itemType'], 'matched' => false];

        try {
            ($this->visitValue)($context);

            return $this->adderWrite($context, $adder['method']);
        } finally {
            unset($this->pendingAdders[$context]);
        }
    }

    /** @return array{method: string, arguments: array<array-key, mixed>}|null */
    private function adderWrite(ValueContext $context, string $method): ?array
    {
        if (!$this->pendingAdders[$context]['matched'] || $context->isSkipped()) {
            return null;
        }

        $arguments = $context->getUnencodedValue();

        return is_array($arguments) ? ['method' => $method, 'arguments' => $arguments] : null;
    }

    private function discoverInverse(ValueContext $context): void
    {
        $owner = $context->getContextObject();
        $collection = $context->getUnencodedValue();
        if ($this->metadataPolicy === null || $owner === null
            || (!$collection instanceof Collection && !is_array($collection))
        ) {
            return;
        }

        $owningField = $this->metadataPolicy->findOwningFieldForInverseCollection($owner, $context->getPropName());
        if ($owningField !== null) {
            $this->graph->discoverInverseCollection($owner, $owningField, function () use ($context): mixed {
                ($this->visitValue)($context);

                return $context->isSkipped() ? null : $context->getUnencodedValue();
            });
        }
    }
}
