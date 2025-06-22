<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Doctrine\Common\Util\ClassUtils;
use Solitus0\AliceGenerator\ValueContext;
use Solitus0\AliceGenerator\ValueVisitor;

class CollectionHandler implements ObjectHandlerInterface
{
    /**
     * @return bool true if the handler changed the value, false otherwise
     */
    public function handle(ValueContext $valueContext): bool
    {
        if (!is_a($collection = $valueContext->getValue(), 'Doctrine\Common\Collections\Collection')) {
            return false;
        }

        $visitor = $valueContext->getValueVisitor();
        if ($visitor instanceof ValueVisitor && $visitor->getGenerationContext()->shouldSkipCollectionsForOwnerClass(
            $valueContext->getContextObjectClass()
        )) {
            $valueContext->setSkipped(true);
            return true;
        }

        $ownerClass = $valueContext->getContextObjectClass();

        // Determine configured limits for this owner
        $globalLimit = null;
        if ($visitor instanceof ValueVisitor && $visitor->getGenerationContext(
        )->shouldLimitCollectionSizeForOwnerClass($ownerClass)) {
            $globalLimit = $visitor->getGenerationContext()->getCollectionSizeLimitForOwnerClass($ownerClass);
        }

        $itemLimits = [];
        if ($visitor instanceof ValueVisitor) {
            $itemLimits = $visitor->getGenerationContext()->getCollectionItemSizeLimits()[$ownerClass] ?? [];
        }

        // Build array of items, applying per-item and per-item-class limits, and breaking on global limit
        $items = [];
        $itemCounts = [];
        foreach ($collection as $item) {
            $itemClass = ClassUtils::getRealClass($item::class);

            // Per-item-class limit first (no callback)
            if (isset($itemLimits[$itemClass])) {
                $count = $itemCounts[$itemClass] ?? 0;
                if ($count >= $itemLimits[$itemClass]) {
                    continue;
                }
            }

            // Global owner-class limit break (no callback)
            if ($globalLimit !== null && count($items) >= $globalLimit) {
                break;
            }

            // Now apply skip callback (only while below limits)
            if ($visitor instanceof ValueVisitor && $visitor->getGenerationContext()->shouldSkipCollectionItem(
                $ownerClass,
                $item
            )) {
                continue;
            }

            // Count item-class occurrences
            if (isset($itemLimits[$itemClass])) {
                $itemCounts[$itemClass] = ($itemCounts[$itemClass] ?? 0) + 1;
            }

            $items[] = $item;
        }

        $valueContext->setValue($items);
        $valueContext->getValueVisitor()->visitArray($valueContext);

        return true;
    }
}
