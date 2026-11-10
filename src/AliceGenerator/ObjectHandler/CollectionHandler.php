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
        if (!$visitor instanceof ValueVisitor) {
            return false;
        }

        $context = $visitor->getGenerationContext();
        $ownerClass = $valueContext->getContextObjectClass();
        if ($ownerClass !== null && $context->shouldSkipCollectionsOwnedBy($ownerClass)) {
            $valueContext->setSkipped(true);

            return true;
        }

        $globalLimit = $ownerClass === null ? null : $context->getItemLimitForOwnerCollections($ownerClass);
        $itemLimits = $ownerClass === null ? [] : ($context->getCollectionItemLimitPerOwnerItem()[$ownerClass] ?? []);

        // Build array of items, applying per-item and per-item-class limits, and breaking on global limit
        $items = [];
        $itemCounts = [];
        foreach ($collection as $item) {
            $itemClass = is_object($item) ? ClassUtils::getRealClass($item::class) : null;

            // Per-item-class limit first (no callback)
            if ($itemClass !== null && isset($itemLimits[$itemClass])) {
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
            if ($ownerClass !== null && $context->shouldSkipCollectionItem($ownerClass, $item)) {
                continue;
            }

            // Count item-class occurrences
            if ($itemClass !== null && isset($itemLimits[$itemClass])) {
                $itemCounts[$itemClass] = ($itemCounts[$itemClass] ?? 0) + 1;
            }

            $items[] = $item;
        }

        $valueContext->setSourceValue($items);
        $visitor->visitArray($valueContext);

        return true;
    }
}
