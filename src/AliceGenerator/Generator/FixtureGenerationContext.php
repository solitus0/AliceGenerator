<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Generator;

use Solitus0\AliceGenerator\Exception\InvalidArgumentException;
use Solitus0\AliceGenerator\MetadataHandler\NonSpecificMetadataHandler;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyTransformRule;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyTransformRulesCollection;
use Solitus0\AliceGenerator\Storage\ObjectConstraintsCollection;

class FixtureGenerationContext
{
    private readonly ObjectConstraintsCollection $constraintsCollection;

    private readonly PropertyTransformRulesCollection $transformRulesCollection;

    private int $maximumRecursion = 5;

    /**
     * Whether to skip properties that are not writable using Symfony's PropertyAccessor.
     * If true, only writable properties will be included in the generated fixture.
     */
    private bool $skipNonWritableProperties = true;

    /**
     * @var callable[] Callbacks to skip specific collection items.
     * Each callback must have the signature: function(string $ownerClass, mixed $item): bool
     */
    private array $collectionItemSkipConditions = [];

    /**
     * @var string[] List of fully qualified class names.
     * Doctrine collection properties on these owner classes will be skipped during fixture generation.
     */
    private array $skipCollectionsOwnedBy = [];

    /**
     * @var array<string, int> Limits number of items per collection for a given owner class.
     * For each collection property owned by the class, this limit applies individually.
     *
     * Example: if limit is 2 and the owner has 3 collections, each collection will include up to 2 items.
     */
    private array $collectionItemLimitPerOwner = [];

    /**
     * @var array<string, array<string, int>> Max number of items allowed per owner and item class.
     * Limits specific item types in collections owned by a class.
     * Example: [ OwnerClass => [ ItemClass => limit, ... ], ... ]
     */
    private array $collectionItemLimitPerOwnerItem = [];

    public function __construct()
    {
        $this->constraintsCollection = new ObjectConstraintsCollection(new NonSpecificMetadataHandler());
        $this->transformRulesCollection = new PropertyTransformRulesCollection();
    }

    public static function create(): self
    {
        return new self();
    }

    public function getMaximumRecursion(): int
    {
        return $this->maximumRecursion;
    }

    public function setMaximumRecursion(int $max): static
    {
        $this->maximumRecursion = $max;

        return $this;
    }

    public function addPersistedObjectConstraint(array|object $objects): static
    {
        $objects = is_array($objects) ? $objects : [$objects];

        foreach ($objects as $object) {
            if (!is_object($object)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Non-object passed to addPersistedObjectConstraint() - "%s" given',
                        gettype($object)
                    )
                );
            }

            $this->constraintsCollection->addConstraint($object);
        }

        return $this;
    }

    public function getConstraintsCollection(): ObjectConstraintsCollection
    {
        return $this->constraintsCollection;
    }

    public function addOwnersWithSkippedCollections(string|array $classes): static
    {
        $classes = is_array($classes) ? $classes : [$classes];
        foreach ($classes as $class) {
            if (!is_string($class)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Non-string passed to addOwnersWithSkippedCollections() - "%s" given',
                        gettype($class)
                    )
                );
            }

            $this->skipCollectionsOwnedBy[] = $class;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getSkipCollectionsOwnedBy(): array
    {
        return $this->skipCollectionsOwnedBy;
    }

    public function shouldSkipCollectionsOwnedBy(string $ownerClass): bool
    {
        return in_array($ownerClass, $this->skipCollectionsOwnedBy, true);
    }

    public function addCollectionItemSkipCondition(callable $callback): static
    {
        $this->collectionItemSkipConditions[] = $callback;

        return $this;
    }

    public function shouldSkipCollectionItem(string $ownerClass, mixed $item): bool
    {
        foreach ($this->collectionItemSkipConditions as $itemSkipCondition) {
            if ($itemSkipCondition($ownerClass, $item)) {
                return true;
            }
        }

        return false;
    }

    public function addCollectionItemLimitPerOwner(string $ownerClass, int $limit): static
    {
        if ($limit < 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Limit must be a non-negative integer, %d given',
                    $limit
                )
            );
        }

        $this->collectionItemLimitPerOwner[$ownerClass] = $limit;

        return $this;
    }

    public function getCollectionItemLimitPerOwner(): array
    {
        return $this->collectionItemLimitPerOwner;
    }

    public function hasCollectionItemLimitForOwner(string $ownerClass): bool
    {
        return isset($this->collectionItemLimitPerOwner[$ownerClass]);
    }

    public function getItemLimitForOwnerCollections(string $ownerClass): ?int
    {
        return $this->collectionItemLimitPerOwner[$ownerClass] ?? null;
    }

    public function addLimitForOwnerItemCollection(string $ownerClass, string $itemClass, int $limit): static
    {
        if ($limit < 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Limit must be a non-negative integer, %d given',
                    $limit
                )
            );
        }

        $this->collectionItemLimitPerOwnerItem[$ownerClass][$itemClass] = $limit;

        return $this;
    }

    public function getCollectionItemLimitPerOwnerItem(): array
    {
        return $this->collectionItemLimitPerOwnerItem;
    }

    public function shouldLimitCollectionItemSize(string $ownerClass, string $itemClass): bool
    {
        return isset($this->collectionItemLimitPerOwnerItem[$ownerClass][$itemClass]);
    }

    public function getCollectionItemSizeLimit(string $ownerClass, string $itemClass): ?int
    {
        return $this->collectionItemLimitPerOwnerItem[$ownerClass][$itemClass] ?? null;
    }

    public function shouldSkipNonWritableProperties(): bool
    {
        return $this->skipNonWritableProperties;
    }

    public function setSkipNonWritableProperties(bool $skipNonWritableProperties): self
    {
        $this->skipNonWritableProperties = $skipNonWritableProperties;

        return $this;
    }

    public function getTransformRulesCollection(): PropertyTransformRulesCollection
    {
        return $this->transformRulesCollection;
    }

    public function addPropertyTransformRule(PropertyTransformRule $object): static
    {
        $this->transformRulesCollection->add($object);

        return $this;
    }
}
