<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Doctrine\Common\Util\ClassUtils;

/** @internal */
final class CollectionSelection
{
    /**
     * @param iterable<mixed> $collection
     * @return list<mixed>|null Null when the owner excludes the entire collection.
     */
    public static function select(
        iterable $collection,
        ?string $ownerClass,
        FixtureGenerationContext $context,
    ): ?array {
        if ($ownerClass !== null && $context->shouldSkipCollectionsOwnedBy($ownerClass)) {
            return null;
        }

        $ownerLimit = $ownerClass === null ? null : $context->getItemLimitForOwnerCollections($ownerClass);
        $itemLimits = $ownerClass === null ? [] : ($context->getCollectionItemLimitPerOwnerItem()[$ownerClass] ?? []);

        $items = [];
        $itemCounts = [];
        foreach ($collection as $item) {
            $itemClass = is_object($item) ? ClassUtils::getRealClass($item::class) : null;
            $hasItemLimit = $itemClass !== null && isset($itemLimits[$itemClass]);

            if ($hasItemLimit && ($itemCounts[$itemClass] ?? 0) >= $itemLimits[$itemClass]) {
                continue;
            }

            if ($ownerLimit !== null && count($items) >= $ownerLimit) {
                break;
            }

            if ($ownerClass !== null && $context->shouldSkipCollectionItem($ownerClass, $item)) {
                continue;
            }

            if ($hasItemLimit) {
                $itemCounts[$itemClass] = ($itemCounts[$itemClass] ?? 0) + 1;
            }

            $items[] = $item;
        }

        return $items;
    }
}
